<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

// ── URL Parsing ──
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$phpSelf = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$knownPrefix = '/affiliate-mvp-laptop/backend/public';

$baseCandidates = array_values(array_unique(array_filter([
    rtrim(str_replace('\\', '/', dirname($scriptName)), '/'),
    rtrim(str_replace('\\', '/', dirname($phpSelf)), '/'),
    strpos($requestPath, $knownPrefix) === 0 ? $knownPrefix : '',
])));

$basePath = '';
foreach ($baseCandidates as $candidate) {
    if ($candidate !== '' && ($requestPath === $candidate || strpos($requestPath, $candidate . '/') === 0)) {
        $basePath = $candidate;
        break;
    }
}

$path = $basePath !== '' && strpos($requestPath, $basePath) === 0
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$path = '/' . ltrim((string)$path, '/');
$path = $path === '/index.php' ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── CSRF token (generate once per session) ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ══════════════════════════════════════════
//  AUTH: Public routes (no login required)
// ══════════════════════════════════════════
$publicRoutes = ['/login', '/health'];
$isPublicRoute = in_array($path, $publicRoutes, true);

// ── Login page (GET) ──
if ($path === '/login' && $method === 'GET') {
    if (isLoggedIn()) {
        redirect_to('/');
    }
    // Render standalone login page (no layout)
    $error = null;
    require APP_VIEWS_PATH . '/auth/login.php';
    exit;
}

// ── Login handler (POST) ──
if ($path === '/login' && $method === 'POST') {
    verify_csrf();

    $authService = new AuthService();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Check lock
    $lockSeconds = $authService->getRemainingLockSeconds($username);
    if ($lockSeconds > 0) {
        $minutes = (int)ceil($lockSeconds / 60);
        $error = "Tài khoản bị khóa tạm thời. Vui lòng thử lại sau {$minutes} phút.";
        require APP_VIEWS_PATH . '/auth/login.php';
        exit;
    }

    $user = $authService->attempt($username, $password);

    if ($user === null) {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        require APP_VIEWS_PATH . '/auth/login.php';
        exit;
    }

    $authService->createSession($user);

    // Redirect to original page (if saved)
    $redirectTo = $_SESSION['redirect_after_login'] ?? '/';
    unset($_SESSION['redirect_after_login']);
    redirect_to($redirectTo);
}

// ── Logout ──
if ($path === '/logout') {
    $authService = new AuthService();
    $authService->logout();
    // Start new session for flash message
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    set_flash('success', 'Đã đăng xuất thành công.');
    redirect_to('/login');
}

// ── Health check (public, no auth) ──
if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Affiliate MVP backend is running', 'data' => ['app' => APP_NAME, 'env' => APP_ENV, 'time' => date('c')]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════
//  AUTH MIDDLEWARE: All routes below require login
// ══════════════════════════════════════════
requireAuth();

// ── Initialize services (only for authenticated requests) ──
$productSyncService = new ProductSyncService();
$taskLogService = new TaskLogService();
$siteService = new SiteService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$postingService = new PostingService();
$automationSettingsService = new AutomationSettingsService();
$integrationConfigService = new IntegrationConfigService();
$scraperService = new ScraperService();

// ══════════════════════════════════════════
//  MODULE CHECK: Route → required module
// ══════════════════════════════════════════
$routeModuleMap = [
    '/scraper'  => 'SCRAPER',  '/products' => 'PRODUCTS',
    '/links'    => 'LINKS',    '/contents' => 'CONTENTS',
    '/posts'    => 'POSTS',    '/settings' => 'SETTINGS',
    '/logs'     => 'LOGS',
    '/admin/modules' => 'ADMIN', '/admin/permissions' => 'ADMIN',
    '/admin/users'   => 'ADMIN',
    '/admin/sites'   => 'ADMIN',
];

$requiredModule = $routeModuleMap[$path] ?? null;
if ($requiredModule && !isModuleEnabled($requiredModule)) {
    requireModule($requiredModule); // Will exit with 403
}

$getPermissionMap = [
    '/scraper'  => 'scraper.view',
    '/products' => 'products.view',
    '/links'    => 'links.view',
    '/contents' => 'contents.view',
    '/posts'    => 'posts.view',
    '/settings' => 'settings.view',
    '/logs'     => 'logs.view',
];

if ($method === 'GET' && isset($getPermissionMap[$path])) {
    requirePermission($getPermissionMap[$path]);
}

// ══════════════════════════════════════════
//  API ENDPOINTS (JSON only, no layout)
// ══════════════════════════════════════════

if ($path === '/api/products') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $productSyncService->allProducts()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/products/import' && $method === 'POST') {
    header('Content-Type: application/json');

    $expectedToken = product_import_token();
    $providedToken = $_SERVER['HTTP_X_IMPORT_TOKEN'] ?? ($_POST['import_token'] ?? '');
    if ($expectedToken === '' || !hash_equals($expectedToken, (string)$providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized import token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $platform = trim((string)($payload['platform'] ?? 'shopee'));
            $products = $payload['products'] ?? $payload;
        } else {
            $platform = trim((string)($_POST['platform'] ?? 'shopee'));
            $products = json_decode(trim((string)($_POST['products_json'] ?? '')), true, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_array($products)) {
            throw new InvalidArgumentException('Payload phải là mảng JSON hoặc object có key products.');
        }

        $result = $productSyncService->syncBatch($platform !== '' ? $platform : 'shopee', array_values($products));
        echo json_encode(['success' => true, 'message' => 'Imported products.', 'data' => $result['summary']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $throwable) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $throwable->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($path === '/api/links') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $linkService->allLinks()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/contents') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $contentService->allContents()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/posts') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $postingService->allPosts()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════
//  POST ACTION HANDLERS
//  CSRF validated + permission checked
// ══════════════════════════════════════════

// POST → Permission map
$postPermissionMap = [
    '/scraper/run'           => 'scraper.run',
    '/scraper/run-all'       => 'scraper.run',
    '/scraper/trending'      => 'scraper.run',
    '/scraper/save-config'   => 'scraper.config',
    '/scraper/delete-config' => 'scraper.config',
    '/sync/manual'           => 'products.sync',
    '/links/generate'        => 'links.generate',
    '/links/generate-all'    => 'links.generate',
    '/contents/generate'     => 'contents.generate',
    '/contents/generate-all' => 'contents.generate',
    '/contents/approve'      => 'contents.approve',
    '/contents/reject'       => 'contents.approve',
    '/posts/schedule'        => 'posts.schedule',
    '/posts/schedule-all'    => 'posts.schedule',
    '/posts/mark-success'    => 'posts.manage',
    '/posts/mark-failed'     => 'posts.manage',
    '/settings/automation'   => 'settings.edit',
    '/settings/integrations' => 'settings.edit',
    '/admin/modules/toggle'  => 'admin.modules',
    '/admin/permissions/save' => 'admin.permissions',
    '/admin/users/store'      => 'admin.users',
    '/admin/users/update'     => 'admin.users',
    '/admin/users/toggle'     => 'admin.users',
    '/admin/users/unlock'     => 'admin.users',
    '/admin/sites/store'     => 'admin.sites',
    '/admin/sites/update'    => 'admin.sites',
    '/admin/sites/toggle'    => 'admin.sites',
    '/admin/sites/change-current' => 'admin.sites',
];

if ($method === 'POST') {
    // CSRF validation for all POST
    verify_csrf();

    // Permission check
    if (isset($postPermissionMap[$path])) {
        requirePermission($postPermissionMap[$path]);
    }

    $handled = true;

    try {
        switch ($path) {
            case '/sync/manual':
                $platform = trim((string)($_POST['platform'] ?? 'affiliate_api'));
                $products = json_decode(trim((string)($_POST['products_json'] ?? '')), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($products)) throw new InvalidArgumentException('Payload phải là mảng JSON.');
                $result = $productSyncService->syncBatch($platform, $products);
                json_response(true, 'Đã đồng bộ ' . $result['summary']['received_count'] . ' sản phẩm. Thêm mới: ' . $result['summary']['inserted_count'] . ', cập nhật: ' . $result['summary']['updated_count'] . '.');
                break;

            case '/links/generate':
                $productId = (int)($_POST['product_id'] ?? 0);
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $linkService->generateForProduct($productId, $campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP');
                json_response(true, 'Đã tạo affiliate link cho sản phẩm #' . $productId . '.');
                break;

            case '/links/generate-all':
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $linkService->generateForEligibleProducts($campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP', $limit);
                json_response(true, 'Đã tạo ' . $result['count'] . ' affiliate link tự động.');
                break;

            case '/contents/generate':
                $productId = (int)($_POST['product_id'] ?? 0);
                $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
                $contentService->generateDraftForProduct($productId, $provider !== '' ? $provider : 'template_engine');
                json_response(true, 'Đã sinh draft content cho sản phẩm #' . $productId . '.');
                break;

            case '/contents/generate-all':
                $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $contentService->generateForEligibleProducts($limit, $provider !== '' ? $provider : 'template_engine');
                json_response(true, 'Đã sinh ' . $result['count'] . ' draft content.');
                break;

            case '/contents/approve':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $contentService->approveContent($contentId);
                json_response(true, 'Đã approve content #' . $contentId . '.');
                break;

            case '/contents/reject':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $contentService->rejectContent($contentId);
                json_response(true, 'Đã reject content #' . $contentId . '.');
                break;

            case '/posts/schedule':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
                $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
                $postingService->schedulePost($contentId, $channel !== '' ? $channel : 'fanpage_manual', $scheduledAt);
                json_response(true, 'Đã schedule bài đăng cho content #' . $contentId . '.');
                break;

            case '/posts/schedule-all':
                $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $postingService->scheduleForApprovedContents($limit, $channel !== '' ? $channel : 'fanpage_manual');
                json_response(true, 'Đã tạo lịch đăng cho ' . $result['count'] . ' bài.');
                break;

            case '/posts/mark-success':
                $postId = (int)($_POST['post_id'] ?? 0);
                $resultNote = trim((string)($_POST['result_note'] ?? ''));
                $postingService->markPosted($postId, $resultNote);
                json_response(true, 'Đã đánh dấu bài #' . $postId . ' là đã đăng.');
                break;

            case '/posts/mark-failed':
                $postId = (int)($_POST['post_id'] ?? 0);
                $resultNote = trim((string)($_POST['result_note'] ?? ''));
                $postingService->markFailed($postId, $resultNote);
                json_response(true, 'Đã đánh dấu bài #' . $postId . ' là thất bại.');
                break;

            case '/settings/automation':
                $settings = $automationSettingsService->save($_POST);
                json_response(true, 'Đã lưu cấu hình tự động hóa.', ['data' => $settings]);
                break;

            case '/settings/integrations':
                $integrations = $integrationConfigService->save($_POST);
                json_response(true, 'Đã lưu cấu hình API/tài khoản. Nếu vừa đổi key, reload trang để cập nhật trạng thái.', ['data' => array_keys($integrations)]);
                break;

            // ── Scraper actions ──
            case '/scraper/save-config':
                $configId = $scraperService->saveConfig($_POST);
                json_response(true, 'Đã lưu cấu hình cào #' . $configId . '.');
                break;

            case '/scraper/delete-config':
                $configId = (int)($_POST['config_id'] ?? 0);
                $scraperService->deleteConfig($configId);
                json_response(true, 'Đã xóa cấu hình #' . $configId . '.');
                break;

            case '/scraper/run':
                $configId = (int)($_POST['config_id'] ?? 0);
                $result = $scraperService->runScrapeJob($configId);
                $msg = "Đã cào {$result['scraped']} SP, lọc {$result['filtered']} SP bán chạy, đồng bộ {$result['synced']} SP.";
                if (!empty($result['errors'])) $msg .= ' (' . count($result['errors']) . ' lỗi)';
                json_response(true, $msg);
                break;

            case '/scraper/run-all':
                $results = $scraperService->runAllActive();
                $totalSynced = 0;
                foreach ($results as $r) $totalSynced += ($r['result']['synced'] ?? 0);
                json_response(true, 'Đã chạy ' . count($results) . ' cấu hình, đồng bộ tổng ' . $totalSynced . ' SP.');
                break;

            case '/scraper/trending':
                $platform = trim((string)($_POST['platform'] ?? 'shopee'));
                $minSold = max(0, (int)($_POST['min_sold_count'] ?? 100));
                $maxPages = max(1, min(3, (int)($_POST['max_pages'] ?? 2)));
                $categoryIds = array_map('intval', (array)($_POST['category_ids'] ?? []));
                $result = $scraperService->scrapeTrending($platform, $categoryIds, $minSold, $maxPages);
                $msg = "Đã cào {$result['scraped']} SP trending, lọc {$result['filtered']} SP bán chạy, đồng bộ {$result['synced']} SP.";
                if (!empty($result['errors'])) $msg .= ' (' . count($result['errors']) . ' lỗi)';
                json_response(true, $msg);
                break;

            // ── Admin: Module toggle ──
            case '/admin/modules/toggle':
                $moduleId = (int)($_POST['module_id'] ?? 0);
                $enabled = (bool)($_POST['enabled'] ?? false);
                $moduleService = new ModuleService();
                $moduleService->toggle($moduleId, $enabled);
                json_response(true, $enabled ? 'Đã bật module.' : 'Đã tắt module.');
                break;

            // ── Admin: Permission matrix save ──
            case '/admin/permissions/save':
                $permService = new PermissionService();
                $result = $permService->saveMatrix($_POST);
                json_response(true, 'Đã lưu phân quyền cho ' . $result['updated'] . ' roles.');
                break;

            // ── Admin: User CRUD ──
            case '/admin/users/store':
                $userService = new UserService();
                $userService->create([
                    'username'  => trim((string)($_POST['username'] ?? '')),
                    'email'     => trim((string)($_POST['email'] ?? '')),
                    'password'  => (string)($_POST['password'] ?? ''),
                    'full_name' => trim((string)($_POST['full_name'] ?? '')),
                    'role_id'   => (int)($_POST['role_id'] ?? 2),
                    'site_id'   => (int)($_POST['site_id'] ?? APP_SITE_ID),
                ]);
                json_response(true, 'Đã tạo người dùng mới.');
                break;

            case '/admin/users/update':
                $userService = new UserService();
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) throw new InvalidArgumentException('User ID không hợp lệ.');
                $userService->update($userId, [
                    'username'  => trim((string)($_POST['username'] ?? '')),
                    'email'     => trim((string)($_POST['email'] ?? '')),
                    'password'  => (string)($_POST['password'] ?? ''),
                    'full_name' => trim((string)($_POST['full_name'] ?? '')),
                    'role_id'   => (int)($_POST['role_id'] ?? 2),
                    'site_id'   => (int)($_POST['site_id'] ?? APP_SITE_ID),
                ]);
                json_response(true, 'Đã cập nhật người dùng.');
                break;

            case '/admin/users/toggle':
                $userService = new UserService();
                $userService->toggleActive((int)($_POST['user_id'] ?? 0), (bool)($_POST['active'] ?? false));
                json_response(true, 'Đã cập nhật trạng thái.');
                break;

            case '/admin/users/unlock':
                $userService = new UserService();
                $userService->unlockUser((int)($_POST['user_id'] ?? 0));
                json_response(true, 'Đã gỡ khóa tạm thời.');
                break;

            // ── Admin: Site CRUD ──
            case '/admin/sites/store':
                $siteService->create($_POST);
                json_response(true, 'Đã tạo site mới.');
                break;

            case '/admin/sites/update':
                $siteId = (int)($_POST['site_id'] ?? 0);
                if ($siteId <= 0) throw new InvalidArgumentException('Site ID không hợp lệ.');
                $siteService->update($siteId, $_POST);
                json_response(true, 'Đã cập nhật site.');
                break;

            case '/admin/sites/toggle':
                $siteService->toggleActive((int)($_POST['site_id'] ?? 0), (bool)($_POST['active'] ?? false));
                json_response(true, 'Đã cập nhật trạng thái site.');
                break;

            case '/admin/sites/change-current':
                $site = $siteService->changeCurrentSite((int)($_POST['site_id'] ?? 0));
                json_response(true, 'Đã chuyển sang site ' . $site['code'] . '.');
                break;

            default:
                $handled = false;
                break;
        }
    } catch (Throwable $throwable) {
        $taskLogService->create($path, 'failed', $_POST, [], $throwable->getMessage());
        json_response(false, $throwable->getMessage());
    }

    if ($handled) exit;
}

// ══════════════════════════════════════════
//  PAGE ROUTES (GET — rendered with layout)
// ══════════════════════════════════════════

$samplePayload = json_encode([
    ['source_product_id' => 'SP-1001', 'product_name' => 'Máy xay mini cầm tay', 'product_url' => 'https://example.com/products/sp-1001', 'price' => 199000, 'sold_count' => 180, 'status' => 'new'],
    ['source_product_id' => 'SP-1002', 'product_name' => 'Đèn ngủ LED để bàn', 'product_url' => 'https://example.com/products/sp-1002', 'price' => 259000, 'sold_count' => 74, 'status' => 'new'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$automationSettings = $automationSettingsService->get();

switch ($path) {
    case '/':
        render('dashboard/index', [
            'pageTitle'      => 'Dashboard',
            'currentPage'    => 'dashboard',
            'productSummary' => $productSyncService->dashboardSummary(),
            'linkSummary'    => $linkService->summary(),
            'contentSummary' => $contentService->summary(),
            'postSummary'    => $postingService->summary(),
            'recentLogs'     => $taskLogService->recent(5),
            'automationSettings' => $automationSettings,
            'integrationStatus' => $automationSettingsService->integrationStatus(),
            'topSellingProducts' => $productSyncService->topSellingProducts(5, (int)($automationSettings['min_sold_count'] ?? 0)),
        ]);
        break;

    case '/products':
        render('products/index', [
            'pageTitle'      => 'Sản phẩm',
            'currentPage'    => 'products',
            'productSummary' => $productSyncService->dashboardSummary(),
            'products'       => $productSyncService->allProducts(),
            'samplePayload'  => $samplePayload,
            'automationSettings' => $automationSettings,
        ]);
        break;

    case '/links':
        render('links/index', [
            'pageTitle'   => 'Affiliate Links',
            'currentPage' => 'links',
            'linkSummary' => $linkService->summary(),
            'links'       => $linkService->allLinks(),
        ]);
        break;

    case '/contents':
        render('contents/index', [
            'pageTitle'      => 'Nội dung',
            'currentPage'    => 'contents',
            'contentSummary' => $contentService->summary(),
            'contents'       => $contentService->allContents(),
        ]);
        break;

    case '/posts':
        render('posts/index', [
            'pageTitle'   => 'Đăng bài',
            'currentPage' => 'posts',
            'postSummary' => $postingService->summary(),
            'posts'       => $postingService->allPosts(),
        ]);
        break;

    case '/logs':
        render('logs/index', [
            'pageTitle'   => 'Nhật ký',
            'currentPage' => 'logs',
            'logs'        => $taskLogService->recent(50),
        ]);
        break;

    case '/scraper':
        render('scraper/index', [
            'pageTitle'       => 'Cào dữ liệu',
            'currentPage'     => 'scraper',
            'scraperSummary'  => $scraperService->summary(),
            'productSummary'  => $productSyncService->dashboardSummary(),
            'configs'         => $scraperService->allConfigs(),
            'categories'      => $scraperService->getCategories(),
            'topProducts'     => $productSyncService->topSellingProducts(10, 50),
        ]);
        break;

    case '/settings':
        render('settings/index', [
            'pageTitle' => 'Tự động hóa',
            'currentPage' => 'settings',
            'automationSettings' => $automationSettings,
            'integrationStatus' => $automationSettingsService->integrationStatus(),
            'integrationConfig' => $integrationConfigService->masked(),
            'topSellingProducts' => $productSyncService->topSellingProducts(10, 0),
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Module List (system_modules/index)
    // ══════════════════════════════════════════
    case '/admin/modules':
        requirePermission('admin.modules');
        $moduleService = new ModuleService();
        render('admin/system_modules/index', [
            'pageTitle'    => 'Quản trị — Modules',
            'currentPage'  => 'admin_modules',
            'modules'      => $moduleService->getAll(),
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Permission Matrix
    // ══════════════════════════════════════════
    case '/admin/permissions':
        requirePermission('admin.permissions');
        $permService = new PermissionService();
        $matrixData = $permService->buildMatrix();
        render('admin/roles/permissions', [
            'pageTitle'    => 'Quản trị — Phân quyền',
            'currentPage'  => 'admin_permissions',
            'roles'        => $matrixData['roles'],
            'groups'       => $matrixData['groups'],
            'matrix'       => $matrixData['matrix'],
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — User Management
    // ══════════════════════════════════════════
    case '/admin/users':
        requirePermission('admin.users');
        $userService = new UserService();
        render('admin/users/index', [
            'pageTitle'    => 'Quản trị — Người dùng',
            'currentPage'  => 'admin_users',
            'users'        => $userService->getAll(),
            'roles'        => $userService->getAllRoles(),
            'sites'        => $siteService->getActive(),
        ]);
        break;

    case '/admin/users/add':
        requirePermission('admin.users');
        $userService = new UserService();
        render('admin/users/add', [
            'pageTitle'     => 'Thêm người dùng',
            'currentPage'   => 'admin_users',
            'csrf_token'    => $_SESSION['csrf_token'] ?? '',
            'sites'         => $siteService->getActive(),
            'roles'         => $userService->getAllRoles(),
            'departments'   => [],
            'full_name'     => '', 'email' => '', 'username' => '',
            'default_site_id' => (int)($_SESSION['site_id'] ?? APP_SITE_ID),
            'department_id' => '', 'role_id' => 2,
            'access_sites'  => [],
            'full_name_err' => '', 'email_err' => '', 'username_err' => '',
            'password_err'  => '', 'confirm_password_err' => '',
        ]);
        break;

    case '/admin/users/profile':
        // Any logged-in user can view their own profile
        $userService = new UserService();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $user = $userService->findById($userId);
        if (!$user) { http_response_code(404); echo '404 — User not found.'; exit; }
        render('admin/users/profile', [
            'pageTitle'            => 'Hồ sơ cá nhân',
            'currentPage'         => 'admin_users',
            'csrf_token'          => $_SESSION['csrf_token'] ?? '',
            'user'                => (object)$user,
            'all_users'           => $userService->getAll(),
            'other_session_count' => 0,
            'old_password'        => '', 'new_password' => '', 'confirm_password' => '',
            'old_password_err'    => '', 'new_password_err' => '', 'confirm_password_err' => '',
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Site Management
    // ══════════════════════════════════════════
    case '/admin/sites':
        requirePermission('admin.sites');
        render('admin/sites/index', [
            'pageTitle'    => 'Quản trị — Sites',
            'currentPage'  => 'admin_sites',
            'sites'        => $siteService->getAll(),
            'activeSites'  => $siteService->getActive(),
            'currentSite'  => currentSite(),
        ]);
        break;

    case '/admin/sites/add':
        requirePermission('admin.sites');
        render('admin/sites/add', [
            'pageTitle'    => 'Thêm Site mới',
            'currentPage'  => 'admin_sites',
            'csrf_token'   => $_SESSION['csrf_token'] ?? '',
            'all_sites'    => $siteService->getAll(),
            'code' => '', 'name' => '', 'address' => '',
            'parent_site_id' => '', 'is_master' => 0, 'is_active' => 1,
            'code_err' => '', 'name_err' => '',
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Role Management
    // ══════════════════════════════════════════
    case '/admin/roles':
        requirePermission('admin.permissions');
        $permService = new PermissionService();
        render('admin/roles/index', [
            'pageTitle'    => 'Quản trị — Vai trò',
            'currentPage'  => 'admin_permissions',
            'roles'        => $permService->getAllRoles(),
            'csrf_token'   => $_SESSION['csrf_token'] ?? '',
        ]);
        break;

    default:
        // ══════════════════════════════════════════
        //  DYNAMIC ADMIN ROUTES (parameterized IDs)
        //  Pattern: /admin/{section}/{action}/{id}
        // ══════════════════════════════════════════
        $handled = false;

        // ── /admin/users/edit/{id} ──
        if (preg_match('#^/admin/users/edit/(\d+)$#', $path, $m)) {
            requirePermission('admin.users');
            $userService = new UserService();
            $editUser = $userService->findById((int)$m[1]);
            if (!$editUser) { http_response_code(404); echo '404 — User not found.'; exit; }
            render('admin/users/edit', [
                'pageTitle'     => 'Sửa người dùng',
                'currentPage'   => 'admin_users',
                'csrf_token'    => $_SESSION['csrf_token'] ?? '',
                'id'            => $editUser['id'],
                'username'      => $editUser['username'],
                'email'         => $editUser['email'],
                'full_name'     => $editUser['full_name'],
                'role_id'       => $editUser['role_id'],
                'default_site_id' => $editUser['site_id'],
                'is_active'     => $editUser['is_active'] ?? 1,
                'department_id' => $editUser['department_id'] ?? '',
                'employee_id'   => $editUser['employee_id'] ?? '',
                'employee_name' => $editUser['employee_name'] ?? '',
                'sites'         => $siteService->getActive(),
                'roles'         => $userService->getAllRoles(),
                'departments'   => [],
                'access_sites'  => [],
                'all_warehouses'=> [],
                'user_warehouses'=> [],
                'full_name_err' => '', 'email_err' => '',
                'password_err'  => '', 'confirm_password_err' => '',
            ]);
            $handled = true;
        }

        // ── /admin/sites/edit/{id} ──
        if (!$handled && preg_match('#^/admin/sites/edit/(\d+)$#', $path, $m)) {
            requirePermission('admin.sites');
            $editSite = $siteService->findById((int)$m[1]);
            if (!$editSite) { http_response_code(404); echo '404 — Site not found.'; exit; }
            render('admin/sites/edit', [
                'pageTitle'    => 'Sửa Site',
                'currentPage'  => 'admin_sites',
                'csrf_token'   => $_SESSION['csrf_token'] ?? '',
                'id'           => $editSite['id'],
                'code'         => $editSite['code'],
                'name'         => $editSite['name'],
                'address'      => $editSite['address'] ?? '',
                'parent_site_id' => $editSite['parent_site_id'] ?? '',
                'is_master'    => $editSite['is_master'] ?? 0,
                'is_active'    => $editSite['is_active'] ?? 1,
                'all_sites'    => $siteService->getAll(),
                'code_err' => '', 'name_err' => '',
            ]);
            $handled = true;
        }

        // ── /admin/sites/change/{id} (GET — switch current site) ──
        if (!$handled && preg_match('#^/admin/sites/change/(\d+)$#', $path, $m)) {
            requirePermission('admin.sites');
            try {
                $siteService->changeCurrentSite((int)$m[1]);
                set_flash('success', 'Đã chuyển đổi Site thành công.');
            } catch (Throwable $ex) {
                set_flash('error', $ex->getMessage());
            }
            redirect_to('/admin/sites');
        }

        // ── /admin/roles/permissions/{id} (POST — save permissions) ──
        if (!$handled && $method === 'POST' && preg_match('#^/admin/roles/permissions/(\d+)$#', $path, $m)) {
            requirePermission('admin.permissions');
            $roleId = (int)$m[1];
            $permIds = $_POST['perm'] ?? [];
            $permService = new PermissionService();
            $permService->saveRolePermissions($roleId, $permIds);
            set_flash('success', 'Đã lưu phân quyền thành công.');
            redirect_to('/admin/roles/permissions/' . $roleId);
            $handled = true;
        }

        // ── /admin/roles/permissions/{id} (GET — permission matrix for specific role) ──
        if (!$handled && preg_match('#^/admin/roles/permissions/(\d+)$#', $path, $m)) {
            requirePermission('admin.permissions');
            $permService = new PermissionService();
            $matrixData = $permService->buildMatrix();
            render('admin/roles/permissions', [
                'pageTitle'    => 'Phân quyền — Role #' . (int)$m[1],
                'currentPage'  => 'admin_permissions',
                'roles'        => $matrixData['roles'],
                'groups'       => $matrixData['groups'],
                'matrix'       => $matrixData['matrix'],
                'focusRoleId'  => (int)$m[1],
            ]);
            $handled = true;
        }

        if (!$handled) {
            http_response_code(404);
            echo '404 — Không tìm thấy trang: ' . e($path) . '<br>';
            echo '<!-- Debug Info: requestPath=' . e($requestPath) . ', basePath=' . e($basePath) . ', scriptName=' . e($_SERVER['SCRIPT_NAME'] ?? '') . ' -->';
        }
        break;
}
