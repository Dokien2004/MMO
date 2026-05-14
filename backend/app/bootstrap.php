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
