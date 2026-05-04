<?php

declare(strict_types=1);

/**
 * Auth helper functions — Available globally after bootstrap.
 * Pattern: mirrors factory-erp session_helper.php (isLoggedIn, hasPermission, etc.)
 */

// ── Authentication ──

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'       => (int)$_SESSION['user_id'],
        'name'     => $_SESSION['user_name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'role_name'=> $_SESSION['role_name'] ?? '',
        'avatar'   => $_SESSION['user_avatar'] ?? null,
        'site_id'  => (int)($_SESSION['site_id'] ?? APP_SITE_ID),
        'site_code'=> $_SESSION['site_code'] ?? 'MAIN',
        'site_name'=> $_SESSION['site_name'] ?? 'Main Site',
    ];
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentSite(): array
{
    return [
        'id' => (int)($_SESSION['site_id'] ?? APP_SITE_ID),
        'code' => $_SESSION['site_code'] ?? 'MAIN',
        'name' => $_SESSION['site_name'] ?? 'Main Site',
    ];
}

/**
 * Require authentication. Redirects to login if not logged in.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect_to('/login');
    }
}

// ── Authorization ──

/**
 * Check if current user has a specific permission.
 * Admin role always returns true.
 */
function hasPermission(string $code): bool
{
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        return true;
    }
    return in_array($code, $_SESSION['permissions'] ?? [], true);
}

/**
 * Require a specific permission. Returns 403 if denied.
 */
function requirePermission(string $code): void
{
    requireAuth();
    if (!hasPermission($code)) {
        http_response_code(403);
        if (is_ajax()) {
            json_response(false, 'Bạn không có quyền thực hiện thao tác này.');
        }
        render('errors/403', [
            'pageTitle'   => 'Từ chối truy cập',
            'currentPage' => '',
            'permission'  => $code,
        ]);
        exit;
    }
}

// ── Module Toggle ──

/**
 * Check if a module is enabled.
 */
function isModuleEnabled(string $code): bool
{
    return in_array($code, $_SESSION['enabled_modules'] ?? [], true);
}

/**
 * Require a module to be enabled. Returns 403 if disabled.
 */
function requireModule(string $code): void
{
    if (!isModuleEnabled($code)) {
        http_response_code(403);
        if (is_ajax()) {
            json_response(false, 'Module này đang bị tắt.');
        }
        render('errors/module_disabled', [
            'pageTitle'   => 'Module bị tắt',
            'currentPage' => '',
            'moduleCode'  => $code,
        ]);
        exit;
    }
}

// ── CSRF Protection ──

/**
 * Get current CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST body or X-CSRF-Token header.
 * Returns 419 status if invalid.
 */
function verify_csrf(): void
{
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        if (is_ajax()) {
            json_response(false, 'CSRF token không hợp lệ. Vui lòng tải lại trang.');
        }
        set_flash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
        redirect_to('/login');
    }
}
