<?php

declare(strict_types=1);

final class IntegrationConfigService
{
    private string $path;
    private PDO $pdo;
    private static bool $schemaBootstrapped = false;

    /** @var array<string, string> */
    private array $allowedKeys = [
        'OPENAI_API_KEY' => 'OpenAI API Key',
        'OPENAI_MODEL' => 'OpenAI Model',
        'OPENAI_FALLBACK_MODELS' => 'OpenAI-compatible Fallback Models',
        'OPENAI_BASE_URL' => 'OpenAI-compatible Base URL',
        'GEMINI_API_KEY' => 'Gemini API Key',
        'GEMINI_MODEL' => 'Gemini Model',
        'FACEBOOK_PAGE_ID' => 'Facebook Page ID',
        'FACEBOOK_PAGE_ACCESS_TOKEN' => 'Facebook Page Access Token',
        'IMAGE_OPENAI_API_KEY' => 'Image OpenAI API Key',
        'IMAGE_BASE_URL' => 'Image Base URL',
        'IMAGE_MODEL' => 'Image Model',
        'IMAGE_FALLBACK_MODEL' => 'Image Fallback Model',
        'IMAGE_SIZE' => 'Image Size',
        'IMAGE_PROVIDER' => 'Image Provider',
        'MEIGEN_API_TOKEN' => 'MeiGen API Token',
        'MEIGEN_BASE_URL' => 'MeiGen Base URL',
        'MEIGEN_MODEL' => 'MeiGen Model',
        'MEIGEN_ASPECT_RATIO' => 'MeiGen Aspect Ratio',
        'MEIGEN_RESOLUTION' => 'MeiGen Resolution',
        'MEIGEN_QUALITY' => 'MeiGen Quality',
        'VIDEO_PROVIDER' => 'Video Provider',
        'VIDEO_MODEL' => 'Video Model',
        'VIDEO_BASE_URL' => 'Video Base URL',
        'VIDEO_API_KEY' => 'Video API Key',
        'VIDEO_SIZE' => 'Video Size',
        'VIDEO_ASPECT_RATIO' => 'Video Aspect Ratio',
        'VIDEO_RESOLUTION' => 'Video Resolution',
        'VIDEO_DURATION_SECONDS' => 'Video Duration Seconds',
        'KLING_ACCESS_KEY' => 'Kling Access Key',
        'KLING_SECRET_KEY' => 'Kling Secret Key',
        'KLING_BASE_URL' => 'Kling Base URL',
        'KLING_MODEL' => 'Kling Model',
        'KLING_MODE' => 'Kling Mode',
        'TELEGRAM_BOT_TOKEN' => 'Telegram Bot Token',
        'TELEGRAM_CHAT_ID' => 'Telegram Chat ID',
        'SHOPEE_AFFILIATE_ID' => 'Shopee Affiliate ID',
        'PRODUCT_IMPORT_TOKEN' => 'Product Import Token',
        'APP_PUBLIC_URL' => 'Public App URL',
    ];

    public function __construct()
    {
        $this->path = __DIR__ . '/../config/local.php';
        $this->pdo = db_pdo();
    }

    public static function bootstrapSchema(): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }

        $service = new self();
        $service->ensureSchema();
        self::$schemaBootstrapped = true;
    }

    public function current(): array
    {
        return [
            'OPENAI_API_KEY' => openai_api_key(),
            'OPENAI_MODEL' => openai_model(),
            'OPENAI_FALLBACK_MODELS' => implode(',', openai_fallback_models()),
            'OPENAI_BASE_URL' => openai_base_url(),
            'GEMINI_API_KEY' => gemini_api_key(),
            'GEMINI_MODEL' => gemini_model(),
            'FACEBOOK_PAGE_ID' => facebook_page_id(),
            'FACEBOOK_PAGE_ACCESS_TOKEN' => facebook_page_access_token(),
            'IMAGE_OPENAI_API_KEY' => image_openai_api_key(),
            'IMAGE_BASE_URL' => image_base_url(),
            'IMAGE_MODEL' => image_model(),
            'IMAGE_FALLBACK_MODEL' => image_fallback_model(),
            'IMAGE_SIZE' => image_size(),
            'IMAGE_PROVIDER' => image_provider(),
            'MEIGEN_API_TOKEN' => meigen_api_token(),
            'MEIGEN_BASE_URL' => meigen_base_url(),
            'MEIGEN_MODEL' => meigen_model(),
            'MEIGEN_ASPECT_RATIO' => meigen_aspect_ratio(),
            'MEIGEN_RESOLUTION' => meigen_resolution(),
            'MEIGEN_QUALITY' => meigen_quality(),
            'VIDEO_PROVIDER' => video_provider(),
            'VIDEO_MODEL' => video_model(),
            'VIDEO_BASE_URL' => video_base_url(),
            'VIDEO_API_KEY' => video_api_key(),
            'VIDEO_SIZE' => video_size(),
            'VIDEO_ASPECT_RATIO' => video_aspect_ratio(),
            'VIDEO_RESOLUTION' => video_resolution(),
            'VIDEO_DURATION_SECONDS' => (string)video_duration_seconds(),
            'KLING_ACCESS_KEY' => kling_access_key(),
            'KLING_SECRET_KEY' => kling_secret_key(),
            'KLING_BASE_URL' => kling_base_url(),
            'KLING_MODEL' => kling_model(),
            'KLING_MODE' => kling_mode(),
            'TELEGRAM_BOT_TOKEN' => telegram_bot_token(),
            'TELEGRAM_CHAT_ID' => telegram_chat_id(),
            'SHOPEE_AFFILIATE_ID' => shopee_affiliate_id(),
            'PRODUCT_IMPORT_TOKEN' => product_import_token(),
            'APP_PUBLIC_URL' => app_public_url(),
        ];
    }

    public function masked(): array
    {
        $values = $this->current();
        foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'FACEBOOK_PAGE_ACCESS_TOKEN', 'IMAGE_OPENAI_API_KEY', 'MEIGEN_API_TOKEN', 'VIDEO_API_KEY', 'KLING_ACCESS_KEY', 'KLING_SECRET_KEY', 'TELEGRAM_BOT_TOKEN', 'PRODUCT_IMPORT_TOKEN'] as $secretKey) {
            $values[$secretKey . '_MASKED'] = $this->mask((string)($values[$secretKey] ?? ''));
            $values[$secretKey . '_SET'] = trim((string)($values[$secretKey] ?? '')) !== '';
            unset($values[$secretKey]);
        }
        return $values;
    }

    public function save(array $input): array
    {
        $current = $this->current();
        $next = $current;

        foreach ($this->allowedKeys as $key => $_label) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = trim((string)$input[$key]);
            if ($value === '') {
                continue;
            }
            if ($value === '__CLEAR__') {
                $next[$key] = '';
                continue;
            }
            $next[$key] = $value;
        }

        $this->writeSiteConfig($next);
        return $next;
    }

    private function writeSiteConfig(array $values): void
    {
        $siteId = currentSiteId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO site_integration_configs (site_id, config_key, config_value)
             VALUES (:site_id, :config_key, :config_value)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
        );

        foreach ($this->allowedKeys as $key => $_label) {
            $stmt->execute([
                ':site_id' => $siteId,
                ':config_key' => $key,
                ':config_value' => (string)($values[$key] ?? ''),
            ]);
        }
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS site_integration_configs (
            site_id INT UNSIGNED NOT NULL,
            config_key VARCHAR(100) NOT NULL,
            config_value TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (site_id, config_key),
            KEY idx_site_integration_configs_key (config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function writeLocalConfig(array $values): void
    {
        $existing = $this->parseExistingConstants();
        $merged = array_merge($existing, $values);

        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        $orderedKeys = [
            'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_FALLBACK_MODELS', 'OPENAI_BASE_URL',
            'GEMINI_API_KEY', 'GEMINI_MODEL',
            'FACEBOOK_PAGE_ID', 'FACEBOOK_PAGE_ACCESS_TOKEN',
            'IMAGE_OPENAI_API_KEY', 'IMAGE_BASE_URL', 'IMAGE_MODEL', 'IMAGE_FALLBACK_MODEL', 'IMAGE_SIZE', 'IMAGE_PROVIDER',
            'MEIGEN_API_TOKEN', 'MEIGEN_BASE_URL', 'MEIGEN_MODEL', 'MEIGEN_ASPECT_RATIO', 'MEIGEN_RESOLUTION', 'MEIGEN_QUALITY',
            'VIDEO_PROVIDER', 'VIDEO_MODEL', 'VIDEO_BASE_URL', 'VIDEO_API_KEY', 'VIDEO_SIZE', 'VIDEO_ASPECT_RATIO', 'VIDEO_RESOLUTION', 'VIDEO_DURATION_SECONDS',
            'KLING_ACCESS_KEY', 'KLING_SECRET_KEY', 'KLING_BASE_URL', 'KLING_MODEL', 'KLING_MODE',
            'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID',
            'APP_PUBLIC_URL', 'PRODUCT_IMPORT_TOKEN', 'SHOPEE_AFFILIATE_ID',
        ];

        foreach ($orderedKeys as $key) {
            if (!array_key_exists($key, $merged)) {
                continue;
            }
            $value = $merged[$key];
            if ($key === 'DB_PORT') {
                $content .= "const {$key} = " . (int)$value . ";\n";
                continue;
            }
            $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value);
            if (in_array($key, ['APP_PUBLIC_URL', 'PRODUCT_IMPORT_TOKEN', 'SHOPEE_AFFILIATE_ID'], true)) {
                $content .= "if (!defined('{$key}')) {\n    define('{$key}', '{$escaped}');\n}\n";
                continue;
            }
            $content .= "const {$key} = '{$escaped}';\n";
        }

        $tmp = $this->path . '.tmp';
        file_put_contents($tmp, $content, LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, $this->path);
    }

    private function parseExistingConstants(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $content = (string)file_get_contents($this->path);
        preg_match_all("/const\\s+([A-Z0-9_]+)\\s*=\\s*(?:'([^']*)'|(\\d+))\\s*;/", $content, $matches, PREG_SET_ORDER);
        $values = [];
        foreach ($matches as $match) {
            $values[$match[1]] = isset($match[3]) && $match[3] !== ''
                ? (int)$match[3]
                : stripcslashes((string)($match[2] ?? ''));
        }
        preg_match_all("/define\(\s*'([A-Z0-9_]+)'\s*,\s*'([^']*)'\s*\)/", $content, $defineMatches, PREG_SET_ORDER);
        foreach ($defineMatches as $match) {
            $values[$match[1]] = stripcslashes((string)($match[2] ?? ''));
        }
        return $values;
    }

    private function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Chưa cấu hình';
        }
        if (strlen($value) <= 10) {
            return str_repeat('•', strlen($value));
        }
        return substr($value, 0, 4) . '••••••' . substr($value, -4);
    }
}
