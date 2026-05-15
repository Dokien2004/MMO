<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

const APP_NAME = 'Affiliate MVP Laptop';
const APP_ENV = 'development';
const APP_DEBUG = true;
const APP_SITE_ID = 1;
if (!defined('APP_PUBLIC_URL')) {
    define('APP_PUBLIC_URL', 'https://mmo.sys-erp.id.vn');
}

const BASE_PATH = __DIR__ . '/../../..';
const APP_PATH = __DIR__ . '/..';
const APP_VIEWS_PATH = APP_PATH . '/views';
const STORAGE_PATH = BASE_PATH . '/storage';
const DATA_PATH = STORAGE_PATH . '/data';
const LOG_PATH = STORAGE_PATH . '/logs';

$localConfig = __DIR__ . '/local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', '');
}
if (!defined('OPENAI_MODEL')) {
    define('OPENAI_MODEL', 'gpt-4o-mini');
}
if (!defined('OPENAI_FALLBACK_MODELS')) {
    define('OPENAI_FALLBACK_MODELS', 'minimax/MiniMax-M2.7,if/iflow-rome-30ba3b,gemini/gemini-3-flash-preview');
}
if (!defined('OPENAI_BASE_URL')) {
    define('OPENAI_BASE_URL', 'https://api.openai.com/v1');
}
if (!defined('OPENAI_TIMEOUT_SECONDS')) {
    define('OPENAI_TIMEOUT_SECONDS', 45);
}
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}
if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-1.5-flash');
}
if (!defined('GEMINI_TIMEOUT_SECONDS')) {
    define('GEMINI_TIMEOUT_SECONDS', 45);
}
if (!defined('FACEBOOK_PAGE_ID')) {
    define('FACEBOOK_PAGE_ID', '');
}
if (!defined('FACEBOOK_PAGE_ACCESS_TOKEN')) {
    define('FACEBOOK_PAGE_ACCESS_TOKEN', '');
}
if (!defined('FACEBOOK_GRAPH_VERSION')) {
    define('FACEBOOK_GRAPH_VERSION', 'v23.0');
}
if (!defined('FACEBOOK_TIMEOUT_SECONDS')) {
    define('FACEBOOK_TIMEOUT_SECONDS', 45);
}
if (!defined('IMAGE_OPENAI_API_KEY')) {
    define('IMAGE_OPENAI_API_KEY', '');
}
if (!defined('IMAGE_BASE_URL')) {
    define('IMAGE_BASE_URL', 'http://127.0.0.1:20128/v1');
}
if (!defined('IMAGE_MODEL')) {
    define('IMAGE_MODEL', 'gemini/gemini-2.5-flash-image');
}
if (!defined('IMAGE_FALLBACK_MODEL')) {
    define('IMAGE_FALLBACK_MODEL', 'minimax/minimax-image-01,cx/gpt-5.4-image');
}
if (!defined('IMAGE_SIZE')) {
    define('IMAGE_SIZE', '1024x1024');
}
if (!defined('IMAGE_TIMEOUT_SECONDS')) {
    define('IMAGE_TIMEOUT_SECONDS', 120);
}
if (!defined('IMAGE_PROVIDER')) {
    define('IMAGE_PROVIDER', 'direct');
}
if (!defined('MEIGEN_API_TOKEN')) {
    define('MEIGEN_API_TOKEN', '');
}
if (!defined('MEIGEN_BASE_URL')) {
    define('MEIGEN_BASE_URL', 'https://www.meigen.ai/api');
}
if (!defined('MEIGEN_MODEL')) {
    define('MEIGEN_MODEL', 'gpt-image-2');
}
if (!defined('MEIGEN_ASPECT_RATIO')) {
    define('MEIGEN_ASPECT_RATIO', 'auto');
}
if (!defined('MEIGEN_RESOLUTION')) {
    define('MEIGEN_RESOLUTION', '1K');
}
if (!defined('MEIGEN_QUALITY')) {
    define('MEIGEN_QUALITY', 'low');
}
if (!defined('VIDEO_PROVIDER')) {
    define('VIDEO_PROVIDER', 'local');
}
if (!defined('VIDEO_MODEL')) {
    define('VIDEO_MODEL', 'ffmpeg-promo');
}
if (!defined('VIDEO_BASE_URL')) {
    define('VIDEO_BASE_URL', '');
}
if (!defined('VIDEO_API_KEY')) {
    define('VIDEO_API_KEY', '');
}
if (!defined('VIDEO_SIZE')) {
    define('VIDEO_SIZE', '720x1280');
}
if (!defined('VIDEO_ASPECT_RATIO')) {
    define('VIDEO_ASPECT_RATIO', '9:16');
}
if (!defined('VIDEO_RESOLUTION')) {
    define('VIDEO_RESOLUTION', '720p');
}
if (!defined('VIDEO_DURATION_SECONDS')) {
    define('VIDEO_DURATION_SECONDS', 8);
}
if (!defined('KLING_ACCESS_KEY')) {
    define('KLING_ACCESS_KEY', '');
}
if (!defined('KLING_SECRET_KEY')) {
    define('KLING_SECRET_KEY', '');
}
if (!defined('KLING_BASE_URL')) {
    define('KLING_BASE_URL', 'https://api.klingai.com');
}
if (!defined('KLING_MODEL')) {
    define('KLING_MODEL', 'kling-v1-6');
}
if (!defined('KLING_MODE')) {
    define('KLING_MODE', 'std');
}
if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '');
}
if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', '');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', '100.84.215.4');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', 'mmo_affiliate');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'mmo_app');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', 'CQjKqOsclR8znHRSfs2iHrUzOqWkgxex');
}
if (!defined('PRODUCT_IMPORT_TOKEN')) {
    define('PRODUCT_IMPORT_TOKEN', '');
}
if (!defined('SHOPEE_AFFILIATE_ID')) {
    define('SHOPEE_AFFILIATE_ID', '');
}


if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

function app_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function app_current_site_id_for_config(): int
{
    if (function_exists('currentSiteId')) {
        return currentSiteId();
    }
    return max(1, (int)($_SESSION['site_id'] ?? APP_SITE_ID));
}

function site_integration_default_for_site(string $key, string $default, int $siteId): string
{
    $siteSpecificKeys = [
        'OPENAI_API_KEY', 'GEMINI_API_KEY', 'FACEBOOK_PAGE_ID', 'FACEBOOK_PAGE_ACCESS_TOKEN',
        'IMAGE_OPENAI_API_KEY', 'MEIGEN_API_TOKEN', 'VIDEO_API_KEY', 'KLING_ACCESS_KEY', 'KLING_SECRET_KEY',
        'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID', 'PRODUCT_IMPORT_TOKEN', 'SHOPEE_AFFILIATE_ID',
    ];

    if ($siteId !== APP_SITE_ID && in_array($key, $siteSpecificKeys, true)) {
        return '';
    }

    return $default;
}

function site_integration_config_value(string $key, string $default = ''): string
{
    static $cache = [];
    $siteId = app_current_site_id_for_config();
    $cacheKey = $siteId . ':' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo = db_pdo();
        
        static $schemaChecked = false;
        if (!$schemaChecked) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS site_integration_configs (
                site_id INT UNSIGNED NOT NULL,
                config_key VARCHAR(100) NOT NULL,
                config_value TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (site_id, config_key),
                KEY idx_site_integration_configs_key (config_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $schemaChecked = true;
        }
        $stmt = $pdo->prepare('SELECT config_value FROM site_integration_configs WHERE site_id = :site_id AND config_key = :config_key LIMIT 1');
        $stmt->execute([':site_id' => $siteId, ':config_key' => $key]);
        $value = $stmt->fetchColumn();
        $cache[$cacheKey] = $value === false || $value === null
            ? site_integration_default_for_site($key, $default, $siteId)
            : (string)$value;
        return $cache[$cacheKey];
    } catch (Throwable) {
        $cache[$cacheKey] = site_integration_default_for_site($key, $default, $siteId);
        return $cache[$cacheKey];
    }
}

function app_public_url(): string
{
    $fromEnv = app_env('APP_PUBLIC_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('APP_PUBLIC_URL', APP_PUBLIC_URL), '/');
}

function app_absolute_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('#^https?://#i', $url) === 1) {
        return $url;
    }

    return app_public_url() . '/' . ltrim($url, '/');
}

function openai_api_key(): string
{
    $fromEnv = app_env('OPENAI_API_KEY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('OPENAI_API_KEY', OPENAI_API_KEY);
}

function openai_model(): string
{
    $fromEnv = app_env('OPENAI_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('OPENAI_MODEL', OPENAI_MODEL);
}

function openai_fallback_models(): array
{
    $fromEnv = app_env('OPENAI_FALLBACK_MODELS', '');
    $value = $fromEnv !== '' ? $fromEnv : site_integration_config_value('OPENAI_FALLBACK_MODELS', OPENAI_FALLBACK_MODELS);
    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $model): bool => $model !== ''));
}

function openai_base_url(): string
{
    $fromEnv = app_env('OPENAI_BASE_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('OPENAI_BASE_URL', OPENAI_BASE_URL), '/');
}

function openai_timeout_seconds(): int
{
    $fromEnv = app_env('OPENAI_TIMEOUT_SECONDS', '');
    return (int)($fromEnv !== '' ? $fromEnv : OPENAI_TIMEOUT_SECONDS);
}

function gemini_api_key(): string
{
    $fromEnv = app_env('GEMINI_API_KEY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('GEMINI_API_KEY', GEMINI_API_KEY);
}

function gemini_model(): string
{
    $fromEnv = app_env('GEMINI_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('GEMINI_MODEL', GEMINI_MODEL);
}

function facebook_page_id(): string
{
    $fromEnv = app_env('FACEBOOK_PAGE_ID', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('FACEBOOK_PAGE_ID', FACEBOOK_PAGE_ID);
}

function facebook_page_access_token(): string
{
    $fromEnv = app_env('FACEBOOK_PAGE_ACCESS_TOKEN', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('FACEBOOK_PAGE_ACCESS_TOKEN', FACEBOOK_PAGE_ACCESS_TOKEN);
}

function facebook_page_token(): string
{
    return facebook_page_access_token();
}

function facebook_graph_version(): string
{
    $fromEnv = app_env('FACEBOOK_GRAPH_VERSION', '');
    return $fromEnv !== '' ? $fromEnv : FACEBOOK_GRAPH_VERSION;
}

function image_openai_api_key(): string
{
    $fromEnv = app_env('IMAGE_OPENAI_API_KEY', '');
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $configured = site_integration_config_value('IMAGE_OPENAI_API_KEY', IMAGE_OPENAI_API_KEY);
    return $configured !== '' ? $configured : openai_api_key();
}

function image_base_url(): string
{
    $fromEnv = app_env('IMAGE_BASE_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('IMAGE_BASE_URL', IMAGE_BASE_URL), '/');
}

function image_model(): string
{
    $fromEnv = app_env('IMAGE_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('IMAGE_MODEL', IMAGE_MODEL);
}

function image_fallback_model(): string
{
    $fromEnv = app_env('IMAGE_FALLBACK_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('IMAGE_FALLBACK_MODEL', IMAGE_FALLBACK_MODEL);
}

function image_fallback_models(): array
{
    return array_values(array_filter(array_map('trim', explode(',', image_fallback_model())), static fn(string $model): bool => $model !== ''));
}

function image_size(): string
{
    $fromEnv = app_env('IMAGE_SIZE', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('IMAGE_SIZE', IMAGE_SIZE);
}

function image_timeout_seconds(): int
{
    $fromEnv = app_env('IMAGE_TIMEOUT_SECONDS', '');
    return (int)($fromEnv !== '' ? $fromEnv : IMAGE_TIMEOUT_SECONDS);
}

function image_provider(): string
{
    $fromEnv = app_env('IMAGE_PROVIDER', '');
    $provider = strtolower(trim($fromEnv !== '' ? $fromEnv : site_integration_config_value('IMAGE_PROVIDER', IMAGE_PROVIDER)));
    return in_array($provider, ['direct', 'meigen'], true) ? $provider : 'direct';
}

function meigen_api_token(): string
{
    $fromEnv = app_env('MEIGEN_API_TOKEN', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_API_TOKEN', MEIGEN_API_TOKEN);
}

function meigen_base_url(): string
{
    $fromEnv = app_env('MEIGEN_BASE_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_BASE_URL', MEIGEN_BASE_URL), '/');
}

function meigen_model(): string
{
    $fromEnv = app_env('MEIGEN_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_MODEL', MEIGEN_MODEL);
}

function meigen_aspect_ratio(): string
{
    $fromEnv = app_env('MEIGEN_ASPECT_RATIO', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_ASPECT_RATIO', MEIGEN_ASPECT_RATIO);
}

function meigen_resolution(): string
{
    $fromEnv = app_env('MEIGEN_RESOLUTION', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_RESOLUTION', MEIGEN_RESOLUTION);
}

function meigen_quality(): string
{
    $fromEnv = app_env('MEIGEN_QUALITY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('MEIGEN_QUALITY', MEIGEN_QUALITY);
}

function video_provider(): string
{
    $fromEnv = app_env('VIDEO_PROVIDER', '');
    $provider = strtolower(trim($fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_PROVIDER', VIDEO_PROVIDER)));
    return in_array($provider, ['local', 'meigen', 'kling', 'direct'], true) ? $provider : 'local';
}

function video_model(): string
{
    $fromEnv = app_env('VIDEO_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_MODEL', VIDEO_MODEL);
}

function video_base_url(): string
{
    $fromEnv = app_env('VIDEO_BASE_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_BASE_URL', VIDEO_BASE_URL), '/');
}

function video_api_key(): string
{
    $fromEnv = app_env('VIDEO_API_KEY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_API_KEY', VIDEO_API_KEY);
}

function video_size(): string
{
    $fromEnv = app_env('VIDEO_SIZE', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_SIZE', VIDEO_SIZE);
}

function video_aspect_ratio(): string
{
    $fromEnv = app_env('VIDEO_ASPECT_RATIO', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_ASPECT_RATIO', VIDEO_ASPECT_RATIO);
}

function video_resolution(): string
{
    $fromEnv = app_env('VIDEO_RESOLUTION', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_RESOLUTION', VIDEO_RESOLUTION);
}

function video_duration_seconds(): int
{
    $fromEnv = app_env('VIDEO_DURATION_SECONDS', '');
    return (int)($fromEnv !== '' ? $fromEnv : site_integration_config_value('VIDEO_DURATION_SECONDS', (string)VIDEO_DURATION_SECONDS));
}

function kling_access_key(): string
{
    $fromEnv = app_env('KLING_ACCESS_KEY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('KLING_ACCESS_KEY', KLING_ACCESS_KEY);
}

function kling_secret_key(): string
{
    $fromEnv = app_env('KLING_SECRET_KEY', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('KLING_SECRET_KEY', KLING_SECRET_KEY);
}

function kling_base_url(): string
{
    $fromEnv = app_env('KLING_BASE_URL', '');
    return rtrim($fromEnv !== '' ? $fromEnv : site_integration_config_value('KLING_BASE_URL', KLING_BASE_URL), '/');
}

function kling_model(): string
{
    $fromEnv = app_env('KLING_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('KLING_MODEL', KLING_MODEL);
}

function kling_mode(): string
{
    $fromEnv = app_env('KLING_MODE', '');
    $mode = strtolower(trim($fromEnv !== '' ? $fromEnv : site_integration_config_value('KLING_MODE', KLING_MODE)));
    return in_array($mode, ['std', 'pro'], true) ? $mode : 'std';
}

function telegram_bot_token(): string
{
    $fromEnv = app_env('TELEGRAM_BOT_TOKEN', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('TELEGRAM_BOT_TOKEN', TELEGRAM_BOT_TOKEN);
}

function telegram_chat_id(): string
{
    $fromEnv = app_env('TELEGRAM_CHAT_ID', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('TELEGRAM_CHAT_ID', TELEGRAM_CHAT_ID);
}

function product_import_token(): string
{
    $fromEnv = app_env('PRODUCT_IMPORT_TOKEN', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('PRODUCT_IMPORT_TOKEN', PRODUCT_IMPORT_TOKEN);
}

function shopee_affiliate_id(): string
{
    $fromEnv = app_env('SHOPEE_AFFILIATE_ID', '');
    return $fromEnv !== '' ? $fromEnv : site_integration_config_value('SHOPEE_AFFILIATE_ID', SHOPEE_AFFILIATE_ID);
}

function db_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', DB_HOST);
    $port = (int)app_env('DB_PORT', (string)DB_PORT);
    $database = app_env('DB_DATABASE', DB_DATABASE);
    $username = app_env('DB_USERNAME', DB_USERNAME);
    $password = app_env('DB_PASSWORD', DB_PASSWORD);

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
