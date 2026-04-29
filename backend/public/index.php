<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/services/ProductSyncService.php';
require_once dirname(__DIR__) . '/app/services/TaskLogService.php';
require_once dirname(__DIR__) . '/app/services/AffiliateLinkService.php';
require_once dirname(__DIR__) . '/app/services/ContentService.php';
require_once dirname(__DIR__) . '/app/services/PostingService.php';

$productSyncService = new ProductSyncService();
$taskLogService = new TaskLogService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$postingService = new PostingService();

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$basePath = $basePath === '/' ? '' : $basePath;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $basePath !== '' && strpos($requestPath, $basePath) === 0 ? substr($requestPath, strlen($basePath)) : $requestPath;
$path = $path === '' ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$flashMessage = null;

$route = static function (string $routePath) use ($basePath): string {
    if ($routePath === '/') {
        return ($basePath === '' ? '' : $basePath) . '/';
    }
    return ($basePath === '' ? '' : $basePath) . $routePath;
};

if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Affiliate MVP backend is running',
        'data' => [
            'app' => APP_NAME,
            'env' => APP_ENV,
            'time' => date('c'),
            'base_path' => $basePath,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/products') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $productSyncService->allProducts()], JSON_UNESCAPED_UNICODE);
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

if ($path === '/sync/manual' && $method === 'POST') {
    $platform = trim((string)($_POST['platform'] ?? 'affiliate_api'));
    $productsJson = trim((string)($_POST['products_json'] ?? ''));

    try {
        $products = json_decode($productsJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($products)) {
            throw new InvalidArgumentException('Payload phai la mang JSON.');
        }
        $result = $productSyncService->syncBatch($platform, $products);
        $flashMessage = ['type' => 'success', 'text' => 'Da dong bo ' . $result['summary']['received_count'] . ' san pham. Them moi: ' . $result['summary']['inserted_count'] . ', cap nhat: ' . $result['summary']['updated_count'] . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('manual_product_sync', 'failed', ['platform' => $platform], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Dong bo that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/links/generate' && $method === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
    try {
        $linkService->generateForProduct($productId, $campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP');
        $flashMessage = ['type' => 'success', 'text' => 'Da tao affiliate link cho san pham #' . $productId . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('generate_affiliate_link', 'failed', ['product_id' => $productId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Tao affiliate link that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/links/generate-all' && $method === 'POST') {
    $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
    $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
    try {
        $result = $linkService->generateForEligibleProducts($campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP', $limit);
        $flashMessage = ['type' => 'success', 'text' => 'Da tao ' . $result['count'] . ' affiliate link tu dong.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('bulk_generate_affiliate_link', 'failed', ['limit' => $limit], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Tao affiliate link hang loat that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/contents/generate' && $method === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
    try {
        $contentService->generateDraftForProduct($productId, $provider !== '' ? $provider : 'template_engine');
        $flashMessage = ['type' => 'success', 'text' => 'Da sinh draft content cho san pham #' . $productId . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('generate_content_draft', 'failed', ['product_id' => $productId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Sinh content that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/contents/generate-all' && $method === 'POST') {
    $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
    $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
    try {
        $result = $contentService->generateForEligibleProducts($limit, $provider !== '' ? $provider : 'template_engine');
        $flashMessage = ['type' => 'success', 'text' => 'Da sinh ' . $result['count'] . ' draft content.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('bulk_generate_content_draft', 'failed', ['limit' => $limit], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Sinh content hang loat that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/contents/approve' && $method === 'POST') {
    $contentId = (int)($_POST['content_id'] ?? 0);
    try {
        $contentService->approveContent($contentId);
        $flashMessage = ['type' => 'success', 'text' => 'Da approve content #' . $contentId . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('approve_content', 'failed', ['content_id' => $contentId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Approve content that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/contents/reject' && $method === 'POST') {
    $contentId = (int)($_POST['content_id'] ?? 0);
    try {
        $contentService->rejectContent($contentId);
        $flashMessage = ['type' => 'success', 'text' => 'Da reject content #' . $contentId . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('reject_content', 'failed', ['content_id' => $contentId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Reject content that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/posts/schedule' && $method === 'POST') {
    $contentId = (int)($_POST['content_id'] ?? 0);
    $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
    $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
    try {
        $postingService->schedulePost($contentId, $channel !== '' ? $channel : 'fanpage_manual', $scheduledAt);
        $flashMessage = ['type' => 'success', 'text' => 'Da schedule bai dang cho content #' . $contentId . '.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('schedule_post', 'failed', ['content_id' => $contentId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Schedule bai dang that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/posts/schedule-all' && $method === 'POST') {
    $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
    $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
    try {
        $result = $postingService->scheduleForApprovedContents($limit, $channel !== '' ? $channel : 'fanpage_manual');
        $flashMessage = ['type' => 'success', 'text' => 'Da tao lich dang cho ' . $result['count'] . ' bai.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('bulk_schedule_post', 'failed', ['limit' => $limit], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Schedule hang loat that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/posts/mark-success' && $method === 'POST') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $resultNote = trim((string)($_POST['result_note'] ?? ''));
    try {
        $postingService->markPosted($postId, $resultNote);
        $flashMessage = ['type' => 'success', 'text' => 'Da danh dau bai #' . $postId . ' la da dang.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('mark_post_success', 'failed', ['post_id' => $postId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Cap nhat bai dang that bai: ' . $throwable->getMessage()];
    }
}

if ($path === '/posts/mark-failed' && $method === 'POST') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $resultNote = trim((string)($_POST['result_note'] ?? ''));
    try {
        $postingService->markFailed($postId, $resultNote);
        $flashMessage = ['type' => 'success', 'text' => 'Da danh dau bai #' . $postId . ' la that bai.'];
    } catch (Throwable $throwable) {
        $taskLogService->create('mark_post_failed', 'failed', ['post_id' => $postId], [], $throwable->getMessage());
        $flashMessage = ['type' => 'error', 'text' => 'Cap nhat bai dang that bai: ' . $throwable->getMessage()];
    }
}

$productSummary = $productSyncService->dashboardSummary();
$linkSummary = $linkService->summary();
$contentSummary = $contentService->summary();
$postSummary = $postingService->summary();
$recentProducts = $productSyncService->recentProducts(10);
$recentLogs = $taskLogService->recent(10);
$recentLinks = $linkService->recentLinks(10);
$recentContents = $contentService->recentContents(10);
$recentPosts = $postingService->recentPosts(10);
$samplePayload = json_encode([
    [
        'source_product_id' => 'SP-1001',
        'product_name' => 'May xay mini cam tay',
        'product_url' => 'https://example.com/products/sp-1001',
        'price' => 199000,
        'status' => 'new',
        'notes' => 'Danh cho dot test dau tien',
    ],
    [
        'source_product_id' => 'SP-1002',
        'product_name' => 'Den ngu LED de ban',
        'product_url' => 'https://example.com/products/sp-1002',
        'price' => 259000,
        'status' => 'linked',
        'notes' => 'Uu tien tao content',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        main { max-width: 1320px; margin: 0 auto; padding: 32px 20px 48px; }
        .hero, .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .hero { padding: 24px; margin-bottom: 20px; }
        .grid { display: grid; gap: 16px; }
        .stats { grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); margin-bottom: 20px; }
        .stat { padding: 18px; }
        .stat strong { display: block; font-size: 28px; margin-top: 8px; }
        .layout { grid-template-columns: 1.1fr 0.9fr; align-items: start; }
        .triple { grid-template-columns: 1fr 1fr 1fr; align-items: start; }
        .quad { grid-template-columns: 1fr 1fr; align-items: start; }
        .card { padding: 20px; }
        .muted { color: #475569; }
        textarea, select, input { width: 100%; box-sizing: border-box; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1; }
        textarea { min-height: 280px; font-family: monospace; }
        button { background: #0f766e; color: #fff; border: none; border-radius: 10px; padding: 12px 16px; cursor: pointer; }
        .button-secondary { background: #1d4ed8; }
        .button-light { background: #334155; }
        .button-approve { background: #15803d; }
        .button-reject { background: #b91c1c; }
        .button-post { background: #7c3aed; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .alert { padding: 14px 16px; border-radius: 10px; margin-bottom: 16px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        code { background: #e2e8f0; padding: 2px 6px; border-radius: 6px; }
        .mini-form { display: grid; gap: 8px; }
        .mono { font-family: monospace; font-size: 12px; word-break: break-all; }
        .inline-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        @media (max-width: 1100px) { .layout, .triple, .quad { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <h1><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="muted">Flow hien tai: dong bo san pham -> affiliate link -> draft content -> approve/reject -> schedule post -> mark posted/failed.</p>
        <p class="muted">API san pham: <code><?php echo htmlspecialchars($route('/api/products'), ENT_QUOTES, 'UTF-8'); ?></code> | API links: <code><?php echo htmlspecialchars($route('/api/links'), ENT_QUOTES, 'UTF-8'); ?></code> | API contents: <code><?php echo htmlspecialchars($route('/api/contents'), ENT_QUOTES, 'UTF-8'); ?></code> | API posts: <code><?php echo htmlspecialchars($route('/api/posts'), ENT_QUOTES, 'UTF-8'); ?></code></p>
    </section>

    <?php if ($flashMessage !== null): ?>
        <div class="alert <?php echo $flashMessage['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($flashMessage['text'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <section class="grid stats">
        <div class="card stat"><span class="muted">Tong san pham</span><strong><?php echo (int)$productSummary['total']; ?></strong></div>
        <div class="card stat"><span class="muted">Da co link</span><strong><?php echo (int)$productSummary['linked']; ?></strong></div>
        <div class="card stat"><span class="muted">Approved content</span><strong><?php echo (int)$contentSummary['approved']; ?></strong></div>
        <div class="card stat"><span class="muted">Tong links</span><strong><?php echo (int)$linkSummary['total']; ?></strong></div>
        <div class="card stat"><span class="muted">Tong drafts</span><strong><?php echo (int)$contentSummary['draft']; ?></strong></div>
        <div class="card stat"><span class="muted">Posts scheduled</span><strong><?php echo (int)$postSummary['scheduled']; ?></strong></div>
        <div class="card stat"><span class="muted">Posts success</span><strong><?php echo (int)$postSummary['success']; ?></strong></div>
        <div class="card stat"><span class="muted">Posts failed</span><strong><?php echo (int)$postSummary['failed']; ?></strong></div>
    </section>

    <section class="grid layout">
        <div class="card">
            <h2>Dong bo san pham theo dot</h2>
            <p class="muted">Paste mang JSON san pham vao day de test flow MVP. Moi record can co <code>source_product_id</code>, <code>product_name</code>, <code>product_url</code>.</p>
            <form method="POST" action="<?php echo htmlspecialchars($route('/sync/manual'), ENT_QUOTES, 'UTF-8'); ?>">
                <label for="platform">Nguon</label>
                <select id="platform" name="platform">
                    <option value="affiliate_api">Affiliate API</option>
                    <option value="shopee">Shopee</option>
                    <option value="tiktokshop">TikTok Shop</option>
                    <option value="lazada">Lazada</option>
                    <option value="manual">Manual</option>
                </select>
                <div style="height: 12px"></div>
                <label for="products_json">JSON payload</label>
                <textarea id="products_json" name="products_json"><?php echo htmlspecialchars($samplePayload, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div style="height: 16px"></div>
                <button type="submit">Dong bo san pham</button>
            </form>
        </div>

        <div class="card">
            <h2>Batch actions</h2>
            <div class="mini-form">
                <form method="POST" action="<?php echo htmlspecialchars($route('/links/generate-all'), ENT_QUOTES, 'UTF-8'); ?>" class="mini-form">
                    <label for="campaign_code">Campaign code</label>
                    <input id="campaign_code" name="campaign_code" value="MVP-LAPTOP">
                    <label for="link_limit">So san pham xu ly link</label>
                    <input id="link_limit" name="limit" type="number" min="1" max="20" value="5">
                    <button type="submit" class="button-secondary">Tao link hang loat</button>
                </form>
                <form method="POST" action="<?php echo htmlspecialchars($route('/contents/generate-all'), ENT_QUOTES, 'UTF-8'); ?>" class="mini-form">
                    <label for="provider">Provider</label>
                    <input id="provider" name="provider" value="template_engine">
                    <label for="content_limit">So san pham xu ly content</label>
                    <input id="content_limit" name="limit" type="number" min="1" max="20" value="5">
                    <button type="submit" class="button-secondary">Sinh draft hang loat</button>
                </form>
                <form method="POST" action="<?php echo htmlspecialchars($route('/posts/schedule-all'), ENT_QUOTES, 'UTF-8'); ?>" class="mini-form">
                    <label for="post_channel">Channel</label>
                    <input id="post_channel" name="channel" value="fanpage_manual">
                    <label for="post_limit">So bai schedule</label>
                    <input id="post_limit" name="limit" type="number" min="1" max="20" value="5">
                    <button type="submit" class="button-post">Schedule bai approved</button>
                </form>
            </div>
        </div>
    </section>

    <section class="grid quad" style="margin-top: 20px;">
        <div class="card">
            <h2>San pham gan day</h2>
            <?php if ($recentProducts === []): ?>
                <p class="muted">Chua co san pham nao duoc dong bo.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>San pham</th><th>Status</th><th>Xu ly</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td>#<?php echo (int)$product['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$product['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted"><?php echo htmlspecialchars((string)$product['source_platform'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <a href="<?php echo htmlspecialchars((string)$product['product_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer">Mo link goc</a>
                            </td>
                            <td><?php echo htmlspecialchars((string)$product['status'], ENT_QUOTES, 'UTF-8'); ?><br><span class="muted"><?php echo htmlspecialchars((string)($product['content_status'] ?? 'none'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td>
                                <div class="mini-form">
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/links/generate'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>"><input type="hidden" name="campaign_code" value="MVP-LAPTOP"><button type="submit" class="button-light">Tao link</button></form>
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/contents/generate'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>"><input type="hidden" name="provider" value="template_engine"><button type="submit" class="button-secondary">Sinh draft</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Affiliate links gan day</h2>
            <?php if ($recentLinks === []): ?>
                <p class="muted">Chua co affiliate link nao.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>Product</th><th>Campaign</th><th>Link</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentLinks as $link): ?>
                        <tr>
                            <td>#<?php echo (int)$link['id']; ?></td>
                            <td>#<?php echo (int)$link['product_id']; ?><br><span class="muted"><?php echo htmlspecialchars((string)$link['source_platform'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$link['campaign_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><div class="mono"><?php echo htmlspecialchars((string)$link['affiliate_url'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid quad" style="margin-top: 20px;">
        <div class="card">
            <h2>Draft content gan day</h2>
            <?php if ($recentContents === []): ?>
                <p class="muted">Chua co content nao.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>Noi dung</th><th>Status</th><th>Duyet</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentContents as $content): ?>
                        <tr>
                            <td>#<?php echo (int)$content['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$content['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="muted"><?php echo htmlspecialchars((string)$content['hashtags'], ENT_QUOTES, 'UTF-8'); ?></span><br><div class="mono"><?php echo htmlspecialchars((string)substr((string)$content['body'], 0, 180), ENT_QUOTES, 'UTF-8'); ?>...</div></td>
                            <td><?php echo htmlspecialchars((string)$content['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="inline-actions">
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/contents/approve'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="content_id" value="<?php echo (int)$content['id']; ?>"><button type="submit" class="button-approve">Approve</button></form>
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/contents/reject'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="content_id" value="<?php echo (int)$content['id']; ?>"><button type="submit" class="button-reject">Reject</button></form>
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/posts/schedule'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="content_id" value="<?php echo (int)$content['id']; ?>"><input type="hidden" name="channel" value="fanpage_manual"><input type="hidden" name="scheduled_at" value=""><button type="submit" class="button-post">Schedule</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Lich dang gan day</h2>
            <?php if ($recentPosts === []): ?>
                <p class="muted">Chua co bai dang nao duoc schedule.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>Channel</th><th>Status</th><th>Thoi gian</th><th>Xu ly</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPosts as $post): ?>
                        <tr>
                            <td>#<?php echo (int)$post['id']; ?><br><span class="muted">Content #<?php echo (int)$post['content_id']; ?></span></td>
                            <td><?php echo htmlspecialchars((string)$post['channel'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$post['status'], ENT_QUOTES, 'UTF-8'); ?><br><span class="muted"><?php echo htmlspecialchars((string)($post['result_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><span class="muted">Schedule:</span><br><?php echo htmlspecialchars((string)$post['scheduled_at'], ENT_QUOTES, 'UTF-8'); ?><br><span class="muted">Posted:</span><br><?php echo htmlspecialchars((string)($post['posted_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="mini-form">
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/posts/mark-success'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>"><input type="hidden" name="result_note" value="Da dang thu cong tren Fanpage"><button type="submit" class="button-approve">Mark posted</button></form>
                                    <form method="POST" action="<?php echo htmlspecialchars($route('/posts/mark-failed'), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>"><input type="hidden" name="result_note" value="Can dang lai thu cong"><button type="submit" class="button-reject">Mark failed</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" style="margin-top: 20px;">
        <h2>Job logs gan day</h2>
        <?php if ($recentLogs === []): ?>
            <p class="muted">Chua co log.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>ID</th><th>Task</th><th>Status</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td>#<?php echo (int)$log['id']; ?></td>
                        <td><?php echo htmlspecialchars((string)$log['task_name'], ENT_QUOTES, 'UTF-8'); ?><br><span class="muted"><?php echo htmlspecialchars((string)($log['error_message'] ?: ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars((string)$log['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
