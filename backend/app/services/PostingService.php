<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ContentService.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';
require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ContentService.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';
require_once __DIR__ . '/SocialChannelService.php';
require_once __DIR__ . '/FacebookPagePublisher.php';
require_once __DIR__ . '/FacebookGroupPublisher.php';
require_once __DIR__ . '/TikTokPublisher.php';
require_once __DIR__ . '/InstagramPublisher.php';
require_once __DIR__ . '/ThreadsPublisher.php';

final class PostingService
{
    private DatabaseStorage $storage;
    private ContentService $contentService;
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;
    private FacebookPagePublisher $facebookPagePublisher;
    private ?FacebookGroupPublisher $facebookGroupPublisher = null;
    private ?TikTokPublisher $tiktokPublisher = null;
    private ?InstagramPublisher $instagramPublisher = null;
    private ?ThreadsPublisher $threadsPublisher = null;
    private PDO $pdo;
    private string $fileName = 'scheduled_posts.json';

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->storage = new DatabaseStorage();
        $this->contentService = new ContentService();
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
        $this->facebookPagePublisher = new FacebookPagePublisher();
        $this->channelService = new SocialChannelService();
    }

    public function schedulePost(int $contentId, string $channel = 'fanpage_manual', ?string $scheduledAt = null, ?int $socialChannelId = null, string $mediaType = 'auto'): array
    {
        $content = $this->contentService->findById($contentId);
        if ($content === null) {
            throw new InvalidArgumentException('Khong tim thay content de schedule.');
        }

        if (($content['status'] ?? '') !== 'approved') {
            throw new InvalidArgumentException('Chi content approved moi duoc schedule dang bai.');
        }

        $scheduledTime = $this->normalizeScheduledAt($scheduledAt);
        $record = $this->storage->mutate($this->fileName, function (array $posts) use ($content, $contentId, $channel, $scheduledTime, $socialChannelId, $mediaType): array {
            $existing = $this->findByContentIdInRows($posts, $contentId);
            $record = [
                'id' => $existing['id'] ?? $this->nextId($posts),
                'site_id' => (int)($content['site_id'] ?? currentSiteId()),
                'content_id' => $contentId,
                'product_id' => (int)$content['product_id'],
                'channel' => $channel,
                'social_channel_id' => $socialChannelId ?? (($existing['social_channel_id'] ?? null) !== null ? (int)$existing['social_channel_id'] : null),
                'media_type' => $mediaType,
                'scheduled_at' => $scheduledTime,
                'status' => 'scheduled',
                'posted_at' => $existing['posted_at'] ?? null,
                'result_note' => '',
                'remote_post_id' => $existing['remote_post_id'] ?? '',
                'created_at' => $existing['created_at'] ?? date('c'),
                'updated_at' => date('c'),
            ];

            $posts = $this->upsertPost($posts, $record);

            return [
                'rows' => $this->sortPosts($posts),
                'result' => $record,
            ];
        });

        $this->taskLogService->create('schedule_post', 'success', [
            'content_id' => $contentId,
            'channel' => $channel,
            'social_channel_id' => $socialChannelId,
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

    public function updatePostMediaType(int $postId, string $mediaType): array
    {
        return $this->storage->mutate($this->fileName, function (array $posts) use ($postId, $mediaType): array {
            foreach ($posts as &$post) {
                if ((int)($post['id'] ?? 0) !== $postId) {
                    continue;
                }
                $post['media_type'] = $mediaType;
                $post['updated_at'] = date('c');

                return [
                    'rows' => $this->sortPosts($posts),
                    'result' => $post,
                ];
            }
            unset($post);

            throw new InvalidArgumentException('Khong tim thay bai dang de cap nhat media_type.');
        });
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

        $channelMap = [
            'fanpage_api'     => 'facebook_page',
            'fanpage_manual'  => 'facebook_page',
            'facebook_group'  => 'facebook_group',
            'tiktok'          => 'tiktok',
            'instagram'       => 'instagram',
            'threads'         => 'threads',
        ];
        $channelType = $channelMap[$post['channel'] ?? ''] ?? '';

        if ($channelType === '') {
            throw new InvalidArgumentException('Channel không xác định: ' . ($post['channel'] ?? 'null'));
        }

        // Get social channel config if available
        $socialChannel = null;
        $socialChannelId = (int)($post['social_channel_id'] ?? 0);
        if ($socialChannelId > 0) {
            $socialChannel = $this->channelService->findById($socialChannelId);
        }

        $publisherResult = match ($channelType) {
            'facebook_page' => $this->facebookPagePublisher->publish($content, $post),
            'facebook_group' => $this->getFacebookGroupPublisher()->publish($content, $socialChannel ?? $post),
            'tiktok' => $this->getTikTokPublisher()->publish($content, $socialChannel ?? $post),
            'instagram' => $this->getInstagramPublisher()->publish($content, $socialChannel ?? $post),
            'threads' => $this->getThreadsPublisher()->publish($content, $socialChannel ?? $post),
            default => throw new InvalidArgumentException('Channel không hỗ trợ: ' . $channelType),
        };

        $remotePostId = (string)($publisherResult['facebook_post_id'] ?? $publisherResult['video_id'] ?? $publisherResult['post_id'] ?? $publisherResult['media_id'] ?? '');
        $note = (string)$publisherResult['message'];

        // Facebook Page: add affiliate comment
        if ($channelType === 'facebook_page' && $remotePostId !== '') {
            $affiliateComment = $this->buildAffiliateComment($content);
            if ($affiliateComment !== '') {
                try {
                    $commentResult = $this->facebookPagePublisher->commentOnPost($remotePostId, $affiliateComment);
                    $note .= ' ' . $commentResult['message'];
                    if (!empty($commentResult['comment_id'])) {
                        $note .= ' Comment ID: ' . $commentResult['comment_id'];
                    }
                } catch (Throwable $throwable) {
                    $note .= ' Comment affiliate lỗi: ' . $throwable->getMessage();
                }
            }
        }

        return $this->changePostStatus($postId, 'success', $note, $remotePostId);
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

    private function getFacebookGroupPublisher(): FacebookGroupPublisher
    {
        if ($this->facebookGroupPublisher === null) {
            $this->facebookGroupPublisher = new FacebookGroupPublisher();
        }
        return $this->facebookGroupPublisher;
    }

    private function getTikTokPublisher(): TikTokPublisher
    {
        if ($this->tiktokPublisher === null) {
            $this->tiktokPublisher = new TikTokPublisher();
        }
        return $this->tiktokPublisher;
    }

    private function getInstagramPublisher(): InstagramPublisher
    {
        if ($this->instagramPublisher === null) {
            $this->instagramPublisher = new InstagramPublisher();
        }
        return $this->instagramPublisher;
    }

    private function getThreadsPublisher(): ThreadsPublisher
    {
        if ($this->threadsPublisher === null) {
            $this->threadsPublisher = new ThreadsPublisher();
        }
        return $this->threadsPublisher;
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
            // Support all channel types for auto-publish
            $supportedChannels = ['fanpage_api', 'fanpage_manual', 'facebook_group', 'tiktok', 'instagram', 'threads'];
            if (!in_array(($post['channel'] ?? ''), $supportedChannels, true)) {
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

    public function scheduleForApprovedContents(int $limit = 10, string $channel = 'fanpage_manual', ?string $startAt = null, int $intervalMinutes = 15, string $mediaType = 'auto'): array
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

        return $this->scheduleSelectedContents($ids, $channel, $startAt, $intervalMinutes, $mediaType);
    }

    public function scheduleSelectedContents(array $contentIds, string $channel = 'fanpage_manual', ?string $startAt = null, int $intervalMinutes = 15, string $mediaType = 'auto'): array
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
                $scheduled[] = $this->schedulePost($contentId, $channel, $scheduledAt, null, $mediaType);
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
            if (!array_key_exists('social_channel_id', $post)) {
                $post['social_channel_id'] = null;
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

    public function summary(bool $global = false): array
    {
        $where = $global ? '' : 'WHERE site_id = :site_id';
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'scheduled\' THEN 1 ELSE 0 END) AS scheduled_count,
                SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) AS failed_count
             FROM scheduled_posts ' . $where
        );
        $params = [];
        if (!$global) {
            $params[':site_id'] = currentSiteId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'scheduled' => (int)($row['scheduled_count'] ?? 0),
            'success' => (int)($row['success_count'] ?? 0),
            'failed' => (int)($row['failed_count'] ?? 0),
        ];
    }

    public function fanpageApiAvailable(): bool
    {
        return $this->facebookPagePublisher->isAvailable();
    }

    private function changePostStatus(int $postId, string $status, string $resultNote, string $remotePostId = ''): array
    {
        $post = $this->storage->mutate($this->fileName, function (array $posts) use ($postId, $status, $resultNote, $remotePostId): array {
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
                }

                return [
                    'rows' => $this->sortPosts($posts),
                    'result' => $post,
                ];
            }
            unset($post);

            throw new InvalidArgumentException('Khong tim thay bai dang can cap nhat.');
        });

        if ($status === 'success') {
            $this->contentService->markUsed((int)$post['content_id']);
        }
        $this->taskLogService->create('post_status_change', 'success', [
            'post_id' => $postId,
            'status' => $status,
        ], [
            'content_id' => (int)$post['content_id'],
            'remote_post_id' => $remotePostId,
        ]);

        return $post;
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
        $this->storage->write($this->fileName, $this->sortPosts($posts));
    }

    private function nextId(array $posts): int
    {
        return $this->storage->nextIdForRows($posts);
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

    private function findByContentIdInRows(array $posts, int $contentId): ?array
    {
        foreach ($posts as $post) {
            if ((int)($post['content_id'] ?? 0) === $contentId) {
                return $post;
            }
        }

        return null;
    }

    private function sortPosts(array $posts): array
    {
        usort($posts, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });

        return $posts;
    }
}
