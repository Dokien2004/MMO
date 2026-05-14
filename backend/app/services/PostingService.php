<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ContentService.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';
require_once __DIR__ . '/FacebookPagePublisher.php';

final class PostingService
{
    private DatabaseStorage $storage;
    private ContentService $contentService;
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;
    private FacebookPagePublisher $facebookPagePublisher;
    private string $fileName = 'scheduled_posts.json';

    public function __construct()
    {
        $this->storage = new DatabaseStorage();
        $this->contentService = new ContentService();
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
        $this->facebookPagePublisher = new FacebookPagePublisher();
    }

    public function schedulePost(int $contentId, string $channel = 'fanpage_manual', ?string $scheduledAt = null): array
    {
        $content = $this->contentService->findById($contentId);
        if ($content === null) {
            throw new InvalidArgumentException('Khong tim thay content de schedule.');
        }

        if (($content['status'] ?? '') !== 'approved') {
            throw new InvalidArgumentException('Chi content approved moi duoc schedule dang bai.');
        }

        $posts = $this->allPosts();
        $existing = $this->findByContentId($contentId);
        $scheduledTime = $this->normalizeScheduledAt($scheduledAt);
        $record = [
            'id' => $existing['id'] ?? $this->nextId($posts),
            'site_id' => (int)($content['site_id'] ?? currentSiteId()),
            'content_id' => $contentId,
            'product_id' => (int)$content['product_id'],
            'channel' => $channel,
            'scheduled_at' => $scheduledTime,
            'status' => 'scheduled',
            'posted_at' => $existing['posted_at'] ?? null,
            'result_note' => '',
            'remote_post_id' => $existing['remote_post_id'] ?? '',
            'created_at' => $existing['created_at'] ?? date('c'),
            'updated_at' => date('c'),
        ];

        $posts = $this->upsertPost($posts, $record);
        $this->savePosts($posts);
        $this->taskLogService->create('schedule_post', 'success', [
            'content_id' => $contentId,
            'channel' => $channel,
        ], [
            'scheduled_at' => $scheduledTime,
        ]);

        return $record;
    }

    public function markPosted(int $postId, string $resultNote = ''): array
    {
        return $this->changePostStatus($postId, 'success', $resultNote !== '' ? $resultNote : 'Da dang thu cong');
    }

    public function markFailed(int $postId, string $resultNote = ''): array
    {
        return $this->changePostStatus($postId, 'failed', $resultNote !== '' ? $resultNote : 'Dang bai that bai');
    }

    public function publishPost(int $postId): array
    {
        $post = $this->findById($postId);
        if ($post === null) {
            throw new InvalidArgumentException('Khong tim thay bai dang can publish.');
        }
        if (($post['status'] ?? '') !== 'scheduled') {
            throw new InvalidArgumentException('Chi bai dang scheduled moi duoc publish.');
        }

        $content = $this->contentService->findById((int)$post['content_id']);
        if ($content === null) {
            throw new InvalidArgumentException('Khong tim thay content de publish.');
        }

        if (($post['channel'] ?? '') === 'fanpage_api') {
            $result = $this->facebookPagePublisher->publish($content, $post);
            $remotePostId = (string)($result['facebook_post_id'] ?? '');
            $note = (string)$result['message'];

            $affiliateComment = $this->buildAffiliateComment($content);
            if ($remotePostId !== '' && $affiliateComment !== '') {
                try {
                    $commentResult = $this->facebookPagePublisher->commentOnPost($remotePostId, $affiliateComment);
                    $note .= ' ' . $commentResult['message'];
                    if (!empty($commentResult['comment_id'])) {
                        $note .= ' Comment ID: ' . $commentResult['comment_id'];
                    }
                    $this->taskLogService->create('facebook_affiliate_comment', 'success', [
                        'post_id' => $postId,
                        'content_id' => (int)$content['id'],
                        'remote_post_id' => $remotePostId,
                    ], [
                        'comment_id' => $commentResult['comment_id'] ?? '',
                    ]);
                } catch (Throwable $throwable) {
                    $note .= ' Tuy nhiên comment link affiliate lỗi: ' . $throwable->getMessage();
                    $this->taskLogService->create('facebook_affiliate_comment', 'failed', [
                        'post_id' => $postId,
                        'content_id' => (int)$content['id'],
                        'remote_post_id' => $remotePostId,
                    ], [], $throwable->getMessage());
                }
            }

            return $this->changePostStatus($postId, 'success', $note, $remotePostId);
        }

        throw new InvalidArgumentException('Channel nay khong ho tro publish tu dong. Dung mark posted cho che do manual.');
    }

    private function buildAffiliateComment(array $content): string
    {
        $product = $this->productSyncService->findProductById((int)($content['product_id'] ?? 0));
        $affiliateUrl = trim((string)($product['affiliate_url'] ?? ''));
        if ($affiliateUrl === '') {
            return '';
        }

        return "Link mua / săn deal: " . $affiliateUrl;
    }

    public function publishDueScheduledPosts(int $limit = 10): array
    {
        $scheduledPosts = $this->allPosts();
        $published = [];
        $now = time();

        foreach ($scheduledPosts as $post) {
            if (($post['status'] ?? '') !== 'scheduled') {
                continue;
            }
            if (($post['channel'] ?? '') !== 'fanpage_api') {
                continue;
            }
            $scheduledAt = strtotime((string)($post['scheduled_at'] ?? ''));
            if ($scheduledAt !== false && $scheduledAt > $now) {
                continue;
            }

            $published[] = $this->publishPost((int)$post['id']);
            if (count($published) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($published),
            'posts' => $published,
        ];
    }

    public function scheduleForApprovedContents(int $limit = 10, string $channel = 'fanpage_manual', ?string $startAt = null, int $intervalMinutes = 15): array
    {
        $contents = $this->contentService->allContents();
        $ids = [];

        foreach ($contents as $content) {
            if (($content['status'] ?? '') !== 'approved') {
                continue;
            }

            if ($this->findByContentId((int)$content['id']) !== null) {
                continue;
            }

            $ids[] = (int)$content['id'];
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $this->scheduleSelectedContents($ids, $channel, $startAt, $intervalMinutes);
    }

    public function scheduleSelectedContents(array $contentIds, string $channel = 'fanpage_manual', ?string $startAt = null, int $intervalMinutes = 15): array
    {
        $contentIds = array_values(array_unique(array_filter(array_map('intval', $contentIds), static fn(int $id): bool => $id > 0)));
        if (empty($contentIds)) {
            throw new InvalidArgumentException('Chua chon content/san pham nao de len lich.');
        }

        $intervalMinutes = max(1, min(1440, $intervalMinutes));
        $startTimestamp = $this->scheduledTimestamp($startAt);
        $scheduled = [];
        $errors = [];

        foreach ($contentIds as $index => $contentId) {
            $scheduledAt = date('c', $startTimestamp + ($index * $intervalMinutes * 60));
            try {
                $scheduled[] = $this->schedulePost($contentId, $channel, $scheduledAt);
            } catch (Throwable $throwable) {
                $errors[] = [
                    'content_id' => $contentId,
                    'error' => $throwable->getMessage(),
                ];
            }
        }

        return [
            'count' => count($scheduled),
            'posts' => $scheduled,
            'errors' => $errors,
        ];
    }

    public function allPosts(): array
    {
        $posts = $this->storage->read($this->fileName);
        foreach ($posts as &$post) {
            if (!isset($post['site_id'])) {
                $post['site_id'] = currentSiteId();
            }
            if (!isset($post['remote_post_id'])) {
                $post['remote_post_id'] = '';
            }
        }
        unset($post);
        usort($posts, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        return $posts;
    }

    public function recentPosts(int $limit = 10): array
    {
        return array_slice($this->allPosts(), 0, $limit);
    }

    public function summary(): array
    {
        $posts = $this->allPosts();
        return [
            'total' => count($posts),
            'scheduled' => count(array_filter($posts, static fn(array $row): bool => ($row['status'] ?? '') === 'scheduled')),
            'success' => count(array_filter($posts, static fn(array $row): bool => ($row['status'] ?? '') === 'success')),
            'failed' => count(array_filter($posts, static fn(array $row): bool => ($row['status'] ?? '') === 'failed')),
        ];
    }

    public function fanpageApiAvailable(): bool
    {
        return $this->facebookPagePublisher->isAvailable();
    }

    private function changePostStatus(int $postId, string $status, string $resultNote, string $remotePostId = ''): array
    {
        $posts = $this->allPosts();
        foreach ($posts as &$post) {
            if ((int)($post['id'] ?? 0) !== $postId) {
                continue;
            }
            $post['status'] = $status;
            $post['result_note'] = $resultNote;
            $post['updated_at'] = date('c');
            if ($remotePostId !== '') {
                $post['remote_post_id'] = $remotePostId;
            }
            if ($status === 'success') {
                $post['posted_at'] = date('c');
                $this->contentService->markUsed((int)$post['content_id']);
            }
            $this->savePosts($posts);
            $this->taskLogService->create('post_status_change', 'success', [
                'post_id' => $postId,
                'status' => $status,
            ], [
                'content_id' => (int)$post['content_id'],
                'remote_post_id' => $remotePostId,
            ]);
            return $post;
        }
        unset($post);

        throw new InvalidArgumentException('Khong tim thay bai dang can cap nhat.');
    }

    private function findByContentId(int $contentId): ?array
    {
        foreach ($this->allPosts() as $post) {
            if ((int)($post['content_id'] ?? 0) === $contentId) {
                return $post;
            }
        }
        return null;
    }

    private function findById(int $postId): ?array
    {
        foreach ($this->allPosts() as $post) {
            if ((int)($post['id'] ?? 0) === $postId) {
                return $post;
            }
        }
        return null;
    }

    private function upsertPost(array $posts, array $record): array
    {
        foreach ($posts as $index => $post) {
            if ((int)($post['content_id'] ?? 0) === (int)$record['content_id']) {
                $posts[$index] = $record;
                return $posts;
            }
        }

        array_unshift($posts, $record);
        return $posts;
    }

    private function savePosts(array $posts): void
    {
        usort($posts, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        $this->storage->write($this->fileName, $posts);
    }

    private function nextId(array $posts): int
    {
        return $this->storage->nextId($this->fileName);
    }

    private function normalizeScheduledAt(?string $scheduledAt): string
    {
        return date('c', $this->scheduledTimestamp($scheduledAt));
    }

    private function scheduledTimestamp(?string $scheduledAt): int
    {
        $candidate = trim((string)$scheduledAt);
        if ($candidate === '') {
            return time() + 3600;
        }

        $timestamp = strtotime($candidate);
        return $timestamp === false ? time() + 3600 : $timestamp;
    }
}
