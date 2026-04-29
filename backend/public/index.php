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
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg: #eef3f8;
            --surface: rgba(255,255,255,.9);
            --surface-strong: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --blue: #2563eb;
            --purple: #7c3aed;
            --green: #16a34a;
            --red: #dc2626;
            --shadow: 0 18px 50px rgba(15, 23, 42, .10);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(20,184,166,.18), transparent 32rem),
                radial-gradient(circle at top right, rgba(59,130,246,.16), transparent 28rem),
                var(--bg);
            color: var(--text);
        }
        main { max-width: 1440px; margin: 0 auto; padding: 28px 18px 48px; }
        h1, h2 { margin: 0; letter-spacing: -0.03em; }
        h1 { font-size: clamp(30px, 4vw, 48px); line-height: 1.05; }
        h2 { font-size: 18px; margin-bottom: 12px; }
        a { color: var(--blue); text-decoration: none; font-weight: 700; }
        a:hover { text-decoration: underline; }
        .hero, .card {
            background: var(--surface);
            border: 1px solid rgba(226,232,240,.9);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .hero {
            padding: 28px;
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 22px;
            align-items: end;
            overflow: hidden;
            position: relative;
        }
        .hero::after {
            content: "";
            position: absolute;
            right: -80px;
            top: -80px;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(20,184,166,.22), rgba(59,130,246,.18));
            pointer-events: none;
        }
        .hero p { margin: 12px 0 0; line-height: 1.6; }
        .api-links { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .api-links code, code {
            display: inline-flex;
            align-items: center;
            background: #ecfeff;
            color: #155e75;
            border: 1px solid #cffafe;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .grid { display: grid; gap: 16px; }
        .stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 18px; }
        .stat {
            padding: 18px;
            position: relative;
            overflow: hidden;
            min-height: 112px;
        }
        .stat::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: linear-gradient(180deg, var(--primary), var(--blue));
            border-radius: var(--radius) 0 0 var(--radius);
        }
        .stat span { display: block; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .stat strong { display: block; font-size: 34px; line-height: 1; margin-top: 14px; letter-spacing: -0.05em; }
        .layout { grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr); align-items: start; }
        .triple { grid-template-columns: repeat(3, 1fr); align-items: start; }
        .quad { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
        .card { padding: 20px; }
        .card > h2 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card > h2::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--primary);
            box-shadow: 0 0 0 5px rgba(15,118,110,.10);
        }
        .muted { color: var(--muted); }
        label { display: block; font-size: 13px; font-weight: 800; color: #334155; margin-bottom: 7px; }
        textarea, select, input {
            width: 100%;
            padding: 12px 13px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: var(--text);
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        textarea:focus, select:focus, input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(15,118,110,.12); }
        textarea { min-height: 300px; font-family: "SFMono-Regular", Consolas, monospace; font-size: 13px; line-height: 1.55; resize: vertical; }
        button {
            width: fit-content;
            min-height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: 0;
            border-radius: 12px;
            padding: 11px 15px;
            cursor: pointer;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(15,118,110,.18);
            transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
        }
        button:hover { transform: translateY(-1px); filter: brightness(1.03); box-shadow: 0 14px 24px rgba(15,118,110,.22); }
        button:active { transform: translateY(0); }
        .button-secondary { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 10px 20px rgba(37,99,235,.18); }
        .button-light { background: linear-gradient(135deg, #475569, #334155); box-shadow: 0 10px 20px rgba(51,65,85,.16); }
        .button-approve { background: linear-gradient(135deg, #22c55e, #15803d); box-shadow: 0 10px 20px rgba(34,197,94,.18); }
        .button-reject { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 10px 20px rgba(239,68,68,.18); }
        .button-post { background: linear-gradient(135deg, #8b5cf6, #6d28d9); box-shadow: 0 10px 20px rgba(124,58,237,.18); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 900;
        }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        tbody tr { transition: background .16s ease; }
        tbody tr:hover { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: 0; }
        td:first-child { font-weight: 800; color: #334155; white-space: nowrap; }
        .alert { padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; font-weight: 800; border: 1px solid transparent; }
        .alert.success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .mini-form { display: grid; gap: 10px; }
        .mini-form form { padding: 14px; border: 1px solid var(--line); border-radius: 14px; background: #f8fafc; }
        .mono {
            font-family: "SFMono-Regular", Consolas, monospace;
            font-size: 12px;
            line-height: 1.45;
            word-break: break-word;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px;
            color: #334155;
        }
        .inline-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .inline-actions form { margin: 0; }
        .inline-actions button, td button { min-height: 36px; padding: 9px 11px; font-size: 12px; }
        .section-spaced { margin-top: 18px; }
        @media (max-width: 1180px) {
            .hero, .layout, .triple, .quad { grid-template-columns: 1fr; }
            .api-links { justify-content: flex-start; }
        }
        @media (max-width: 760px) {
            main { padding: 16px 10px 32px; }
            .hero, .card { border-radius: 16px; }
            .hero, .card { padding: 16px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            .mono { white-space: normal; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <h1><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="muted">Flow hien tai: dong bo san pham -> affiliate link -> draft content -> approve/reject -> schedule post -> mark posted/failed.</p>
        <div class="api-links"><code><?php echo htmlspecialchars($route('/api/products'), ENT_QUOTES, 'UTF-8'); ?></code><code><?php echo htmlspecialchars($route('/api/links'), ENT_QUOTES, 'UTF-8'); ?></code><code><?php echo htmlspecialchars($route('/api/contents'), ENT_QUOTES, 'UTF-8'); ?></code><code><?php echo htmlspecialchars($route('/api/posts'), ENT_QUOTES, 'UTF-8'); ?></code></div>
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

    <section class="grid quad section-spaced">
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

    <section class="grid quad section-spaced">
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

    <section class="card section-spaced">
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
