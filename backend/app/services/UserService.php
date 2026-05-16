<?php

declare(strict_types=1);

/**
 * UserService — CRUD for user management.
 */
class UserService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
        new SiteService();
    }

    /**
     * Get all users with role info.
     */
    public function getAll(): array
    {
        $sql = "SELECT u.*, r.name AS role_name, r.code AS role_code,
                    s.code AS site_code, s.name AS site_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN sites s ON s.id = u.site_id";

        if (!$this->isAdminSession()) {
            $stmt = $this->pdo->prepare($sql . " WHERE u.site_id = :site_id ORDER BY u.id ASC");
            $stmt->execute([':site_id' => currentSiteId()]);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->query($sql . " ORDER BY u.id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Get user by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.*, r.name AS role_name, r.code AS role_code,
                    s.code AS site_code, s.name AS site_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN sites s ON s.id = u.site_id
             WHERE u.id = :id" . ($this->isAdminSession() ? '' : ' AND u.site_id = :site_id')
        );
        $params = [':id' => $id];
        if (!$this->isAdminSession()) {
            $params[':site_id'] = currentSiteId();
        }
        $stmt->execute($params);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Create a new user.
     */
    public function create(array $data): int
    {
        // Validate unique
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE username = :u OR email = :e"
        );
        $stmt->execute([':u' => $data['username'], ':e' => $data['email']]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Username hoặc email đã tồn tại.');
        }

        $siteId = $this->sanitizeTargetSiteId((int)($data['site_id'] ?? currentSiteId()));

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, password_hash, full_name, role_id, site_id, is_active)
             VALUES (:username, :email, :pass, :name, :role, :site, :active)"
        );
        $stmt->execute([
            ':username' => $data['username'],
            ':email'    => $data['email'],
            ':pass'     => password_hash($data['password'], PASSWORD_BCRYPT),
            ':name'     => $data['full_name'],
            ':role'     => (int)$data['role_id'],
            ':site'     => $siteId,
            ':active'   => 1,
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->syncDefaultSiteAccess($userId, $siteId);

        return $userId;
    }

    /**
     * Update user info (not password).
     */
    public function update(int $id, array $data): bool
    {
        // Check unique (exclude self)
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE (username = :u OR email = :e) AND id != :id"
        );
        $stmt->execute([':u' => $data['username'], ':e' => $data['email'], ':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Username hoặc email đã tồn tại.');
        }

        $siteId = $this->sanitizeTargetSiteId((int)($data['site_id'] ?? currentSiteId()));
        $scopeWhere = $this->isAdminSession() ? ' WHERE id = :id' : ' WHERE id = :id AND site_id = :current_site_id';
        $sql = "UPDATE users SET username = :username, email = :email,
                full_name = :name, role_id = :role, site_id = :site" . $scopeWhere;
        $params = [
            ':username' => $data['username'],
            ':email'    => $data['email'],
            ':name'     => $data['full_name'],
            ':role'     => (int)$data['role_id'],
            ':site'     => $siteId,
            ':id'       => $id,
        ];
        if (!$this->isAdminSession()) {
            $params[':current_site_id'] = currentSiteId();
        }

        // Update password if provided
        if (!empty($data['password'])) {
            $sql = "UPDATE users SET username = :username, email = :email,
                    full_name = :name, role_id = :role, site_id = :site,
                    password_hash = :pass" . $scopeWhere;
            $params[':pass'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $stmt = $this->pdo->prepare($sql);
        $updated = $stmt->execute($params);
        if ($updated) {
            $this->syncDefaultSiteAccess($id, $siteId);
        }
        return $updated;
    }

    /**
     * Toggle user active/inactive.
     */
    public function toggleActive(int $id, bool $active): bool
    {
        // Don't deactivate yourself
        if ($id === (int)($_SESSION['user_id'] ?? 0) && !$active) {
            throw new \InvalidArgumentException('Không thể tắt chính tài khoản đang đăng nhập.');
        }

        $sql = "UPDATE users SET is_active = :active WHERE id = :id" . ($this->isAdminSession() ? '' : ' AND site_id = :site_id');
        $params = [':active' => $active ? 1 : 0, ':id' => $id];
        if (!$this->isAdminSession()) {
            $params[':site_id'] = currentSiteId();
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Reset login attempts and unlock.
     */
    public function unlockUser(int $id): bool
    {
        $sql = "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id" . ($this->isAdminSession() ? '' : ' AND site_id = :site_id');
        $params = [':id' => $id];
        if (!$this->isAdminSession()) {
            $params[':site_id'] = currentSiteId();
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get all roles for dropdown.
     */
    public function getAllRoles(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM roles WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    private function sanitizeTargetSiteId(int $siteId): int
    {
        $siteId = max(1, $siteId);
        if ($this->isAdminSession()) {
            return $siteId;
        }
        if ($siteId !== currentSiteId()) {
            throw new \InvalidArgumentException('Bạn chỉ được tạo/sửa user trong site hiện tại.');
        }
        return $siteId;
    }

    private function isAdminSession(): bool
    {
        return (string)($_SESSION['user_role'] ?? '') === 'admin';
    }

    private function syncDefaultSiteAccess(int $userId, int $siteId): void
    {
        $this->pdo->prepare("UPDATE user_site_access SET is_default = 0 WHERE user_id = :uid")
            ->execute([':uid' => $userId]);
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_site_access (user_id, site_id, is_default)
             VALUES (:uid, :sid, 1)
             ON DUPLICATE KEY UPDATE is_default = 1"
        );
        $stmt->execute([':uid' => $userId, ':sid' => $siteId]);
    }

    /**
     * Cập nhật Telegram Chat ID cho user.
     */
    public function updateTelegramChatId(int $userId, string $chatId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET telegram_chat_id = :chat_id WHERE id = :id");
        return $stmt->execute([':chat_id' => $chatId, ':id' => $userId]);
    }
}
