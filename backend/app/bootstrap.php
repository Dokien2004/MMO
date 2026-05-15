<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/view_helper.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/services/SiteService.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/ModuleService.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/services/DatabaseStorage.php';
require_once __DIR__ . '/services/TaskLogService.php';
require_once __DIR__ . '/services/AutomationSettingsService.php';
require_once __DIR__ . '/services/IntegrationConfigService.php';
require_once __DIR__ . '/services/ProductSyncService.php';
require_once __DIR__ . '/services/AffiliateLinkService.php';
require_once __DIR__ . '/services/PromptTemplateService.php';
require_once __DIR__ . '/services/OpenAIContentProvider.php';
require_once __DIR__ . '/services/GeminiContentProvider.php';
require_once __DIR__ . '/services/FacebookPagePublisher.php';
require_once __DIR__ . '/services/ContentService.php';
require_once __DIR__ . '/services/ImageMediaService.php';
require_once __DIR__ . '/services/VideoMediaService.php';
require_once __DIR__ . '/services/PostingService.php';
require_once __DIR__ . '/services/ServerInfoService.php';
require_once __DIR__ . '/services/TelegramService.php';
require_once __DIR__ . '/services/PendingScrapeJobService.php';
require_once __DIR__ . '/services/ScraperService.php';
require_once __DIR__ . '/services/AIKeywordService.php';
require_once __DIR__ . '/services/AutoCrawlService.php';
require_once __DIR__ . '/services/UserSelectedProductService.php';
require_once __DIR__ . '/services/ProductScoringService.php';
require_once __DIR__ . '/services/UserProductService.php';
require_once __DIR__ . '/services/SocialChannelService.php';
require_once __DIR__ . '/services/FacebookGroupPublisher.php';
require_once __DIR__ . '/services/TikTokPublisher.php';

function bootstrap_runtime_schema_once(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $markerDir = STORAGE_PATH . '/cache';
    if (!is_dir($markerDir)) {
        mkdir($markerDir, 0775, true);
    }

    $schemaVersion = md5(implode('|', [
        'database-storage-v2',
        'site-service-v1',
        'automation-settings-v1',
        'integration-config-v1',
        'scraper-v1',
        'user-product-v1',
        'social-channel-v1',
        'product-scoring-v1',
    ]));
    $markerFile = $markerDir . '/runtime_schema.version';
    $currentVersion = is_file($markerFile) ? trim((string)file_get_contents($markerFile)) : '';

    if ($currentVersion !== $schemaVersion) {
        DatabaseStorage::bootstrapSchema();
        SiteService::bootstrapSchema();
        AutomationSettingsService::bootstrapSchema();
        IntegrationConfigService::bootstrapSchema();
        ScraperService::bootstrapSchema();
        UserProductService::bootstrapSchema();
        SocialChannelService::bootstrapSchema();
        ProductScoringService::bootstrapSchema();
        file_put_contents($markerFile, $schemaVersion, LOCK_EX);
    }

    $bootstrapped = true;
}

bootstrap_runtime_schema_once();

function cached_enabled_modules(int $ttlSeconds = 300): array
{
    $cached = $_SESSION['enabled_modules_cache'] ?? null;
    if (
        is_array($cached)
        && isset($cached['expires_at'], $cached['data'])
        && (int)$cached['expires_at'] >= time()
        && is_array($cached['data'])
    ) {
        return $cached['data'];
    }

    $modules = (new ModuleService())->getEnabledCodes();
    $_SESSION['enabled_modules'] = $modules;
    $_SESSION['enabled_modules_cache'] = [
        'expires_at' => time() + $ttlSeconds,
        'data' => $modules,
    ];

    return $modules;
}

function cached_active_sites_for_admin(int $ttlSeconds = 300): array
{
    $siteKey = 'active_sites_cache_' . currentSiteId();
    $cached = $_SESSION[$siteKey] ?? null;
    if (
        is_array($cached)
        && isset($cached['expires_at'], $cached['data'])
        && (int)$cached['expires_at'] >= time()
        && is_array($cached['data'])
    ) {
        return $cached['data'];
    }

    $sites = (new SiteService())->getActive();
    $_SESSION[$siteKey] = [
        'expires_at' => time() + $ttlSeconds,
        'data' => $sites,
    ];

    return $sites;
}
