<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/view_helper.php';
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
require_once __DIR__ . '/services/PostingService.php';
