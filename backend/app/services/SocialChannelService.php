<?php

declare(strict_types=1);

/**
 * SocialChannelService — Manages publishing channels (Facebook Pages, Groups, TikTok).
 *
 * Stores channel configurations in DB: access tokens, cookies, daily limits.
 * Used by PostingService to route content to the right publisher.
 */
final class SocialChannelService
{
    private \PDO $pdo;
    private static bool $schemaBootstrapped = false;

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    public static function bootstrapSchema(): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }

        $service = new self();
        $service->ensureTable();
        self::$schemaBootstrapped = true;
    }

    /**
     * List all channels for current site.
     */
    public function list(array $filters = []): array
    {
        $siteId = currentSiteId();
        $where = ['site_id = :sid'];
        $params = [':sid' => $siteId];

        if (!empty($filters['type'])) {
            $where[] = 'channel_type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql = 'SELECT * FROM social_channels WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get active channels for auto-posting.
     */
    public function getActiveChannels(): array
    {
        return $this->list(['status' => 'active']);
    }

    /**
     * Get a single channel by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_channels WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => currentSiteId()]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new channel.
     */
    public function create(array $data): int
    {
        $siteId = currentSiteId();
        $stmt = $this->pdo->prepare(
            'INSERT INTO social_channels 
                (site_id, channel_type, channel_name, channel_id, access_token, 
                 cookie_data, config, status, daily_post_limit, sort_order)
             VALUES 
                (:sid, :type, :name, :ch_id, :token, :cookie, :cfg, :status, :limit, :sort)'
        );
        $stmt->execute([
            ':sid' => $siteId,
            ':type' => $data['channel_type'] ?? 'facebook_page',
            ':name' => trim((string)($data['channel_name'] ?? '')),
            ':ch_id' => trim((string)($data['channel_id'] ?? '')),
            ':token' => trim((string)($data['access_token'] ?? '')),
            ':cookie' => trim((string)($data['cookie_data'] ?? '')),
            ':cfg' => json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE),
            ':status' => $data['status'] ?? 'active',
            ':limit' => max(1, (int)($data['daily_post_limit'] ?? 5)),
            ':sort' => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update a channel.
     */
    public function update(int $id, array $data): bool
    {
        $siteId = currentSiteId();
        $allowed = ['channel_name', 'channel_id', 'access_token', 'cookie_data', 'status', 'daily_post_limit', 'sort_order'];
        $sets = [];
        $params = [':id' => $id, ':sid' => $siteId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $field === 'daily_post_limit' ? max(1, (int)$data[$field]) : trim((string)$data[$field]);
            }
        }
        if (array_key_exists('config', $data)) {
            $sets[] = 'config = :config';
            $params[':config'] = is_string($data['config']) ? $data['config'] : json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($sets)) return false;

        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE social_channels SET ' . implode(', ', $sets) . ' WHERE id = :id AND site_id = :sid';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a channel.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM social_channels WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => currentSiteId()]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if channel can still post today (daily limit).
     */
    public function canPostToday(int $channelId): bool
    {
        $channel = $this->findById($channelId);
        if (!$channel || $channel['status'] !== 'active') return false;
        return (int)($channel['posts_today'] ?? 0) < (int)($channel['daily_post_limit'] ?? 5);
    }

    /**
     * Increment today's post count.
     */
    public function incrementPostCount(int $channelId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE social_channels SET posts_today = posts_today + 1, last_post_at = NOW(), updated_at = NOW() 
             WHERE id = :id AND site_id = :sid'
        );
        $stmt->execute([':id' => $channelId, ':sid' => currentSiteId()]);
    }

    /**
     * Reset daily post counters (called by cron at midnight).
     */
    public function resetDailyCounters(?int $siteId = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE social_channels SET posts_today = 0 WHERE site_id = :sid');
        $stmt->execute([':sid' => $siteId ?? currentSiteId()]);
    }

    /**
     * Summary stats.
     */
    public function summary(): array
    {
        $channels = $this->list();
        return [
            'total' => count($channels),
            'active' => count(array_filter($channels, fn($c) => $c['status'] === 'active')),
            'facebook_page' => count(array_filter($channels, fn($c) => $c['channel_type'] === 'facebook_page')),
            'facebook_group' => count(array_filter($channels, fn($c) => $c['channel_type'] === 'facebook_group')),
            'tiktok' => count(array_filter($channels, fn($c) => $c['channel_type'] === 'tiktok')),
        ];
    }

    /**
     * Get publish method label for a channel type.
     */
    public static function publishMethodLabel(string $type): string
    {
        return match ($type) {
            'facebook_page' => 'Facebook Page API',
            'facebook_group' => 'Facebook Group (Browser)',
            'tiktok' => 'TikTok (Browser Upload)',
            'instagram' => 'Instagram (API)',
            default => $type,
        };
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS social_channels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    channel_type ENUM('facebook_page','facebook_group','tiktok','instagram') NOT NULL DEFAULT 'facebook_page',
    channel_name VARCHAR(200) NOT NULL DEFAULT '',
    channel_id VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'Page ID, Group ID, TikTok username',
    access_token TEXT DEFAULT NULL,
    cookie_data TEXT DEFAULT NULL COMMENT 'Browser session cookies (encrypted)',
    config JSON DEFAULT NULL COMMENT 'Extra config per channel',
    status ENUM('active','paused','error') NOT NULL DEFAULT 'active',
    last_post_at DATETIME DEFAULT NULL,
    daily_post_limit INT NOT NULL DEFAULT 5,
    posts_today INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_channels_site (site_id, channel_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
