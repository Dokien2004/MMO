<?php

declare(strict_types=1);

/**
 * AuthService — Handles login, logout, brute-force protection, session management.
 * Pattern: Simplified from factory-erp AuthController + SessionManager.
 */
class AuthService
{
    private PDO $pdo;

    /** Brute-force: max failed attempts before temporary lock */
    private const MAX_ATTEMPTS = 5;

    /** Brute-force: lock duration in minutes */
    private const LOCK_MINUTES = 15;

    public function __construct()
    {
        $this->pdo = db_pdo();
        new SiteService();
    }

    /**
     * Attempt login with username/password.
     *
     * @return array|null User row on success, null on failure
     */
    public function attempt(string $username, string $password): ?array
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT u.*, r.code AS role_code, r.name AS role_name,
                    s.code AS site_code, s.name AS site_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN sites s ON s.id = u.site_id
             WHERE u.username = :username
             LIMIT 1"
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // Check account active
        if (!(int)$user['is_active']) {
            return null;
        }

        // Check brute-force lock
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementLoginAttempts((int)$user['id']);
            return null;
        }

        // Success — reset attempts & update login info
        $this->resetLoginAttempts((int)$user['id']);

        return $user;
    }

    /**
     * Create authenticated session after successful login.
     * Pattern: mirrors ERP's createUserSession() but simplified.
     */
    public function createSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_name']     = $user['full_name'] ?: $user['username'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role_code'];
        $_SESSION['role_id']       = (int)$user['role_id'];
        $_SESSION['role_name']     = $user['role_name'];
        $_SESSION['user_avatar']   = $user['avatar_url'];
        $_SESSION['site_id']       = (int)($user['site_id'] ?? APP_SITE_ID);
        $_SESSION['user_site_id']  = (int)($user['site_id'] ?? APP_SITE_ID);
        $_SESSION['site_code']     = $user['site_code'] ?: 'MAIN';
        $_SESSION['site_name']     = $user['site_name'] ?: 'Main Site';
        $_SESSION['permissions']   = $this->loadPermissions((int)$user['role_id']);
        $_SESSION['enabled_modules'] = $this->loadEnabledModules();

        // Regenerate CSRF token on login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Audit log
        $this->writeAuditLog((int)$user['id'], 'LOGIN', null, null, 'Login thành công');
    }

    /**
     * Destroy session and log out.
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            $this->writeAuditLog($userId, 'LOGOUT', null, null, 'Logout');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Load permission codes for a role.
     * @return string[] Array of permission code strings
     */
    private function loadPermissions(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.code
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :rid
             ORDER BY p.code ASC"
        );
        $stmt->execute([':rid' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Load enabled module codes.
     * @return string[] Array of module code strings
     */
    private function loadEnabledModules(): array
    {
        $stmt = $this->pdo->query(
            "SELECT code FROM system_modules WHERE is_enabled = 1 ORDER BY sort_order ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Increment failed login attempts; lock if exceeds max.
     */
    private function incrementLoginAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $userId]);

        // Check if should lock
        $stmt = $this->pdo->prepare("SELECT login_attempts FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            $stmt = $this->pdo->prepare(
                "UPDATE users SET locked_until = :lock WHERE id = :id"
            );
            $stmt->execute([':lock' => $lockUntil, ':id' => $userId]);

            $this->writeAuditLog($userId, 'ACCOUNT_LOCKED', 'users', (string)$userId,
                "Khóa tài khoản " . self::LOCK_MINUTES . " phút sau " . self::MAX_ATTEMPTS . " lần sai");
        }
    }

    /**
     * Reset login attempts on successful login.
     */
    private function resetLoginAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET login_attempts = 0,
                 locked_until = NULL,
                 last_login_at = NOW(),
                 last_login_ip = :ip
             WHERE id = :id"
        );
        $stmt->execute([
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':id' => $userId,
        ]);
    }

    /**
     * Write to audit_logs table.
     */
    private function writeAuditLog(?int $userId, string $action, ?string $table, ?string $targetId, ?string $details): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, target_table, target_id, details, ip_address)
                 VALUES (:uid, :action, :tbl, :tid, :details, :ip)"
            );
            $stmt->execute([
                ':uid'     => $userId,
                ':action'  => $action,
                ':tbl'     => $table,
                ':tid'     => $targetId,
                ':details' => $details,
                ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (\Throwable $e) {
            error_log("[AUTH] Audit log error: " . $e->getMessage());
        }
    }

    /**
     * Get remaining lock seconds (0 if not locked).
     */
    public function getRemainingLockSeconds(string $username): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT locked_until FROM users WHERE username = :u AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':u' => $username]);
        $lockedUntil = $stmt->fetchColumn();

        if (!$lockedUntil) return 0;

        $remaining = strtotime($lockedUntil) - time();
        return max(0, $remaining);
    }
}
