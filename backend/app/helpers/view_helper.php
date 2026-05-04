<?php

declare(strict_types=1);

/**
 * Render a view file inside the main layout.
 *
 * @param string $view     Relative path under app/views (e.g. 'dashboard/index')
 * @param array  $data     Variables to extract into the view scope
 */
function render(string $view, array $data = []): void
{
    $data['__viewFile'] = APP_VIEWS_PATH . '/' . $view . '.php';
    if (!file_exists($data['__viewFile'])) {
        http_response_code(404);
        echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
        exit;
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $__viewFile;
    $__content = ob_get_clean();

    require APP_VIEWS_PATH . '/layouts/main.php';
}

/**
 * Set a flash message in session.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'text' => $message];
}

/**
 * Get and clear flash message.
 */
function get_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

/**
 * Redirect to a path (relative to base).
 */
function redirect_to(string $path): void
{
    $url = url($path);
    header('Location: ' . $url);
    exit;
}

/**
 * Return JSON response.
 */
function json_response(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Check if request is AJAX.
 */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/**
 * Escape output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Build a URL with base path prefix.
 */
function url(string $path = '/'): string
{
    global $basePath;
    $base = rtrim((string)($basePath ?? ''), '/');
    $normalized = normalize_internal_path($path, $base);

    if ($normalized === '/') {
        return ($base !== '' ? $base : '') . '/';
    }

    return ($base !== '' ? $base : '') . $normalized;
}

/**
 * Build an asset URL.
 */
function asset(string $path): string
{
    return url('/' . ltrim($path, '/'));
}

/**
 * Normalize an app-internal path so helpers can safely handle
 * raw REQUEST_URI values and already-prefixed paths.
 */
function normalize_internal_path(string $path, string $basePath = ''): string
{
    $path = trim($path);

    if ($path === '') {
        return '/';
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        $parsedPath = parse_url($path, PHP_URL_PATH) ?: '/';
        $query = parse_url($path, PHP_URL_QUERY);
        $path = $parsedPath . ($query ? '?' . $query : '');
    }

    $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
    $query = parse_url($path, PHP_URL_QUERY);

    if ($basePath !== '' && strpos($pathOnly, $basePath) === 0) {
        $pathOnly = substr($pathOnly, strlen($basePath)) ?: '/';
    }

    $pathOnly = '/' . ltrim($pathOnly, '/');
    if ($pathOnly === '/index.php') {
        $pathOnly = '/';
    }

    return $query ? $pathOnly . '?' . $query : $pathOnly;
}

/**
 * Render a status badge.
 */
function status_badge(string $status): string
{
    $labels = [
        'new' => 'Mới', 'linked' => 'Đã link', 'content_ready' => 'Có nội dung',
        'posted' => 'Đã đăng', 'archived' => 'Lưu trữ',
        'active' => 'Hoạt động', 'expired' => 'Hết hạn', 'error' => 'Lỗi',
        'draft' => 'Nháp', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'used' => 'Đã dùng',
        'scheduled' => 'Đã lên lịch', 'success' => 'Thành công', 'failed' => 'Thất bại',
        'running' => 'Đang chạy', 'pending' => 'Chờ xử lý', 'none' => 'Chưa có',
    ];
    $display = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $cssClass = 'badge-' . str_replace('_', '-', $status);
    return '<span class="badge ' . $cssClass . '">' . e($display) . '</span>';
}
