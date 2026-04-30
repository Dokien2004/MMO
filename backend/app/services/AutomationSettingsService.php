<?php

declare(strict_types=1);

final class AutomationSettingsService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->ensureSchema();
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_settings WHERE site_id = :site_id LIMIT 1');
        $stmt->execute([':site_id' => APP_SITE_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $defaults = $this->defaults();
            $this->save($defaults);
            return $defaults;
        }

        return $this->castRow($row);
    }

    public function save(array $input): array
    {
        $current = $this->getExistingRow();
        $settings = $this->sanitize($input, $current ?? $this->defaults());

        $sql = <<<SQL
INSERT INTO automation_settings (
    site_id,
    default_campaign_code,
    default_content_provider,
    default_channel,
    sync_limit,
    min_sold_count,
    top_selling_only,
    auto_approve,
    auto_schedule,
    auto_publish,
    publish_interval_minutes,
    created_at,
    updated_at
) VALUES (
    :site_id,
    :default_campaign_code,
    :default_content_provider,
    :default_channel,
    :sync_limit,
    :min_sold_count,
    :top_selling_only,
    :auto_approve,
    :auto_schedule,
    :auto_publish,
    :publish_interval_minutes,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    default_campaign_code = VALUES(default_campaign_code),
    default_content_provider = VALUES(default_content_provider),
    default_channel = VALUES(default_channel),
    sync_limit = VALUES(sync_limit),
    min_sold_count = VALUES(min_sold_count),
    top_selling_only = VALUES(top_selling_only),
    auto_approve = VALUES(auto_approve),
    auto_schedule = VALUES(auto_schedule),
    auto_publish = VALUES(auto_publish),
    publish_interval_minutes = VALUES(publish_interval_minutes),
    updated_at = VALUES(updated_at)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':site_id' => $settings['site_id'],
            ':default_campaign_code' => $settings['default_campaign_code'],
            ':default_content_provider' => $settings['default_content_provider'],
            ':default_channel' => $settings['default_channel'],
            ':sync_limit' => $settings['sync_limit'],
            ':min_sold_count' => $settings['min_sold_count'],
            ':top_selling_only' => $settings['top_selling_only'] ? 1 : 0,
            ':auto_approve' => $settings['auto_approve'] ? 1 : 0,
            ':auto_schedule' => $settings['auto_schedule'] ? 1 : 0,
            ':auto_publish' => $settings['auto_publish'] ? 1 : 0,
            ':publish_interval_minutes' => $settings['publish_interval_minutes'],
            ':created_at' => $current['created_at'] ?? date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->get();
    }

    public function integrationStatus(): array
    {
        $openAiCompatibleRouterConfigured = openai_base_url() !== 'https://api.openai.com/v1';
        $geminiViaRouterConfigured = $openAiCompatibleRouterConfigured && str_contains(strtolower(openai_model()), 'gemini');

        return [
            'openai_configured' => openai_api_key() !== '' || $openAiCompatibleRouterConfigured,
            'openai_router_configured' => $openAiCompatibleRouterConfigured,
            'gemini_configured' => gemini_api_key() !== '' || $geminiViaRouterConfigured,
            'gemini_direct_configured' => gemini_api_key() !== '',
            'gemini_router_configured' => $geminiViaRouterConfigured,
            'facebook_page_id_configured' => facebook_page_id() !== '',
            'facebook_access_token_configured' => facebook_page_access_token() !== '',
            'fanpage_api_ready' => facebook_page_id() !== '' && facebook_page_access_token() !== '',
        ];
    }

    private function defaults(): array
    {
        return [
            'site_id' => APP_SITE_ID,
            'default_campaign_code' => 'MVP-LAPTOP',
            'default_content_provider' => 'gemini',
            'default_channel' => 'fanpage_manual',
            'sync_limit' => 10,
            'min_sold_count' => 50,
            'top_selling_only' => true,
            'auto_approve' => true,
            'auto_schedule' => true,
            'auto_publish' => false,
            'publish_interval_minutes' => 15,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function sanitize(array $input, array $fallback): array
    {
        $allowedProviders = ['template_engine', 'openai', 'gemini', 'auto'];
        $allowedChannels = ['fanpage_manual', 'fanpage_api'];

        return [
            'site_id' => APP_SITE_ID,
            'default_campaign_code' => trim((string)($input['default_campaign_code'] ?? $fallback['default_campaign_code'])) ?: 'MVP-LAPTOP',
            'default_content_provider' => in_array((string)($input['default_content_provider'] ?? ''), $allowedProviders, true)
                ? (string)$input['default_content_provider']
                : $fallback['default_content_provider'],
            'default_channel' => in_array((string)($input['default_channel'] ?? ''), $allowedChannels, true)
                ? (string)$input['default_channel']
                : $fallback['default_channel'],
            'sync_limit' => max(1, min(50, (int)($input['sync_limit'] ?? $fallback['sync_limit']))),
            'min_sold_count' => max(0, (int)($input['min_sold_count'] ?? $fallback['min_sold_count'])),
            'top_selling_only' => $this->toBool($input['top_selling_only'] ?? $fallback['top_selling_only']),
            'auto_approve' => $this->toBool($input['auto_approve'] ?? $fallback['auto_approve']),
            'auto_schedule' => $this->toBool($input['auto_schedule'] ?? $fallback['auto_schedule']),
            'auto_publish' => $this->toBool($input['auto_publish'] ?? $fallback['auto_publish']),
            'publish_interval_minutes' => max(5, min(1440, (int)($input['publish_interval_minutes'] ?? $fallback['publish_interval_minutes']))),
            'created_at' => $fallback['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    private function castRow(array $row): array
    {
        return [
            'site_id' => (int)($row['site_id'] ?? APP_SITE_ID),
            'default_campaign_code' => (string)($row['default_campaign_code'] ?? 'MVP-LAPTOP'),
            'default_content_provider' => (string)($row['default_content_provider'] ?? 'template_engine'),
            'default_channel' => (string)($row['default_channel'] ?? 'fanpage_manual'),
            'sync_limit' => (int)($row['sync_limit'] ?? 10),
            'min_sold_count' => (int)($row['min_sold_count'] ?? 50),
            'top_selling_only' => (bool)($row['top_selling_only'] ?? false),
            'auto_approve' => (bool)($row['auto_approve'] ?? false),
            'auto_schedule' => (bool)($row['auto_schedule'] ?? false),
            'auto_publish' => (bool)($row['auto_publish'] ?? false),
            'publish_interval_minutes' => (int)($row['publish_interval_minutes'] ?? 15),
            'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string)($row['updated_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    private function getExistingRow(): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_settings WHERE site_id = :site_id LIMIT 1');
        $stmt->execute([':site_id' => APP_SITE_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->castRow($row) : null;
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS automation_settings (
    site_id INT NOT NULL,
    default_campaign_code VARCHAR(100) NOT NULL DEFAULT 'MVP-LAPTOP',
    default_content_provider VARCHAR(50) NOT NULL DEFAULT 'template_engine',
    default_channel VARCHAR(50) NOT NULL DEFAULT 'fanpage_manual',
    sync_limit INT NOT NULL DEFAULT 10,
    min_sold_count INT NOT NULL DEFAULT 50,
    top_selling_only TINYINT(1) NOT NULL DEFAULT 1,
    auto_approve TINYINT(1) NOT NULL DEFAULT 1,
    auto_schedule TINYINT(1) NOT NULL DEFAULT 1,
    auto_publish TINYINT(1) NOT NULL DEFAULT 0,
    publish_interval_minutes INT NOT NULL DEFAULT 15,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (site_id),
    CONSTRAINT chk_settings_provider CHECK (default_content_provider IN ('template_engine', 'openai', 'gemini', 'auto')),
    CONSTRAINT chk_settings_channel CHECK (default_channel IN ('fanpage_manual', 'fanpage_api')),
    CONSTRAINT chk_settings_sync_limit CHECK (sync_limit >= 1),
    CONSTRAINT chk_settings_min_sold_count CHECK (min_sold_count >= 0),
    CONSTRAINT chk_settings_publish_interval CHECK (publish_interval_minutes >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureProviderConstraintAllowsGemini();
    }

    private function ensureProviderConstraintAllowsGemini(): void
    {
        try {
            $this->pdo->exec('ALTER TABLE automation_settings DROP CONSTRAINT chk_settings_provider');
        } catch (Throwable) {
            // Constraint may not exist or may already be compatible.
        }

        try {
            $this->pdo->exec("ALTER TABLE automation_settings ADD CONSTRAINT chk_settings_provider CHECK (default_content_provider IN ('template_engine', 'openai', 'gemini', 'auto'))");
        } catch (Throwable) {
            // Ignore if DB already has an equivalent constraint.
        }
    }
}
