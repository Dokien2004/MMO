<?php

declare(strict_types=1);

/**
 * Auto Publish Cron Job — Multi-channel
 *
 * Usage: php cron_jobs/auto_publish.php
 * Cron expression: every 15 minutes
 *
 * This script orchestrates the posting flow:
 * 1. Checks for contents that are 'ready' but not yet scheduled.
 * 2. Assigns them to available active channels (respecting daily limits).
 * 3. Executes the publish action (API or Playwright) for scheduled posts that are due.
 */

// Define execution context for bootstrap
define('CRON_JOB', true);
require_once dirname(__DIR__) . '/backend/app/bootstrap.php';

$options = getopt('', ['site::']);
$siteId = max(1, (int)($options['site'] ?? ($_SESSION['site_id'] ?? APP_SITE_ID)));
$_SESSION['site_id'] = $siteId;

echo "[Cron] Auto Publish Started: " . date('Y-m-d H:i:s') . "\n";
echo "[Cron] Site: {$siteId}\n";

try {
    $channelService = new SocialChannelService();
    $contentService = new ContentService();
    $postingService = new PostingService();
    $taskLogService = new TaskLogService();

    // 1. Reset daily limits if it's the start of a new day
    $lastResetFile = STORAGE_PATH . '/tmp/last_daily_reset_site_' . $siteId . '.txt';
    $today = date('Y-m-d');
    $lastReset = is_file($lastResetFile) ? file_get_contents($lastResetFile) : '';
    if ($lastReset !== $today) {
        $channelService->resetDailyCounters($siteId);
        file_put_contents($lastResetFile, $today);
        echo "[Cron] Daily channel counters reset.\n";
    }

    // 2. Fetch active channels
    $activeChannels = $channelService->getActiveChannels();
    if (empty($activeChannels)) {
        echo "[Cron] No active social channels found. Exiting.\n";
        exit(0);
    }

    // 3. Auto-Schedule 'ready' contents to channels that have capacity
    $readyContents = $contentService->allContents();
    $scheduledCount = 0;

    foreach ($readyContents as $content) {
        if (($content['status'] ?? '') !== 'ready') {
            continue;
        }

        // Check if this content is already scheduled or posted
        $existingPosts = array_filter($postingService->allPosts(), static function($p) use ($content) {
            return (int)($p['content_id'] ?? 0) === (int)$content['id'];
        });

        if (!empty($existingPosts)) {
            continue; // Already assigned
        }

        // Find a channel that can post today
        $selectedChannel = null;
        foreach ($activeChannels as $channel) {
            if ($channelService->canPostToday((int)$channel['id'])) {
                $selectedChannel = $channel;
                break;
            }
        }

        if (!$selectedChannel) {
            echo "[Cron] Warning: All active channels have reached their daily post limit.\n";
            break; // Stop scheduling if no capacity
        }

        // Schedule it for now (or soon)
        $postingService->schedulePost(
            (int)$content['id'],
            $selectedChannel['channel_type'],
            null,
            (int)$selectedChannel['id']
        );
        echo "[Cron] Scheduled content #" . $content['id'] . " for channel " . $selectedChannel['channel_type'] . "\n";
        $scheduledCount++;
    }

    // 4. Publish due scheduled posts
    $duePosts = array_filter($postingService->allPosts(), static function($p) {
        if (($p['status'] ?? '') !== 'scheduled') return false;
        $scheduledAt = strtotime((string)($p['scheduled_at'] ?? ''));
        return $scheduledAt !== false && $scheduledAt <= time();
    });

    $publishedCount = 0;
    foreach ($duePosts as $post) {
        $postId = (int)$post['id'];
        $contentId = (int)$post['content_id'];
        
        // Find which specific channel instance to use
        // We match by channel_type since schedulePost only stores type currently.
        // We pick the first active channel of that type that has capacity.
        $targetChannel = null;
        $boundChannelId = (int)($post['social_channel_id'] ?? 0);
        if ($boundChannelId > 0) {
            $boundChannel = $channelService->findById($boundChannelId);
            if ($boundChannel && $boundChannel['channel_type'] === $post['channel'] && $channelService->canPostToday($boundChannelId)) {
                $targetChannel = $boundChannel;
            }
        }

        if ($targetChannel === null) {
            foreach ($activeChannels as $channel) {
                if ($channel['channel_type'] === $post['channel'] && $channelService->canPostToday((int)$channel['id'])) {
                    $targetChannel = $channel;
                    break;
                }
            }
        }

        if (!$targetChannel) {
            echo "[Cron] Skipping post #{$postId}: No active/available channel of type '{$post['channel']}'.\n";
            continue;
        }

        $content = $contentService->findById($contentId);
        if (!$content) {
            $postingService->markFailed($postId, "Content not found");
            continue;
        }

        echo "[Cron] Publishing post #{$postId} to {$targetChannel['channel_name']}...\n";
        
        try {
            $result = [];
            switch ($targetChannel['channel_type']) {
                case 'facebook_page':
                    $fbPublisher = new FacebookPagePublisher();
                    // Temporary workaround: pass empty array since we rely on constants,
                    // but ideally FacebookPagePublisher should use channel token.
                    $result = $fbPublisher->publish($content, $targetChannel);
                    break;
                case 'facebook_group':
                    $fbGroupPublisher = new FacebookGroupPublisher();
                    $result = $fbGroupPublisher->publish($content, $targetChannel);
                    break;
                case 'tiktok':
                    $tiktokPublisher = new TikTokPublisher();
                    $result = $tiktokPublisher->publish($content, $targetChannel);
                    break;
                default:
                    throw new RuntimeException("Unsupported channel type: {$targetChannel['channel_type']}");
            }

            $channelService->incrementPostCount((int)$targetChannel['id']);
            $taskLogService->create('cron_publish', 'success', [
                'post_id' => $postId,
                'channel_id' => $targetChannel['id']
            ], $result);
            
            // Note: postingService->publishPost handles state changes, but since we bypassed it to use targetChannel directly,
            // we update status manually using reflection or markPosted (which sets to 'success')
            $postingService->markPosted($postId, $result['message'] ?? 'Published by Cron');

            echo "[Cron] Success: " . ($result['message'] ?? 'Posted') . "\n";
            $publishedCount++;

        } catch (Throwable $e) {
            echo "[Cron] Failed to publish post #{$postId}: " . $e->getMessage() . "\n";
            $postingService->markFailed($postId, $e->getMessage());
        }
    }

    echo "[Cron] Finished. Scheduled: {$scheduledCount}, Published: {$publishedCount}\n";

} catch (Throwable $e) {
    echo "[Cron] CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
