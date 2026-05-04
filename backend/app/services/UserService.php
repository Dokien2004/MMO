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
        $stmt = $this->pdo->query(
            "SELECT u.*, r.name AS role_name, r.code AS role_code,
                    s.code AS site_code, s.name AS site_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN sites s ON s.id = u.site_id
             ORDER BY u.id ASC"
        );
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
             WHERE u.id = :id"
        );
        $stmt->execute([':id' => $id]);
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
            ':site'     => max(1, (int)($data['site_id'] ?? APP_SITE_ID)),
            ':active'   => 1,
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->syncDefaultSiteAccess($userId, max(1, (int)($data['site_id'] ?? APP_SITE_ID)));

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

        $siteId = max(1, (int)($data['site_id'] ?? APP_SITE_ID));
        $sql = "UPDATE users SET username = :username, email = :email,
                full_name = :name, role_id = :role, site_id = :site WHERE id = :id";
        $params = [
            ':username' => $data['username'],
            ':email'    => $data['email'],
            ':name'     => $data['full_name'],
            ':role'     => (int)$data['role_id'],
            ':site'     => $siteId,
            ':id'       => $id,
        ];

        // Update password if provided
        if (!empty($data['password'])) {
            $sql = "UPDATE users SET username = :username, email = :email,
                    full_name = :name, role_id = :role, site_id = :site,
                    password_hash = :pass WHERE id = :id";
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

        $stmt = $this->pdo->prepare("UPDATE users SET is_active = :active WHERE id = :id");
        return $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    /**
     * Reset login attempts and unlock.
     */
    public function unlockUser(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all roles for dropdown.
     */
    public function getAllRoles(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM roles WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
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
}
