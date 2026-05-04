<?php

declare(strict_types=1);

/**
 * SiteService — Lightweight multi-site administration.
 */
class SiteService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(500) NULL,
    `parent_site_id` INT UNSIGNED NULL,
    `is_master` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_code` (`code`),
    KEY `idx_sites_parent` (`parent_site_id`),
    KEY `idx_sites_active` (`is_active`),
    CONSTRAINT `fk_sites_parent`
        FOREIGN KEY (`parent_site_id`) REFERENCES `sites`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(
            "INSERT INTO `sites` (`id`, `code`, `name`, `address`, `is_master`, `is_active`)
             VALUES (1, 'MAIN', 'Main Site', '', 1, 1)
             ON DUPLICATE KEY UPDATE `id` = `id`"
        );

        if (!$this->columnExists('users', 'site_id')) {
            $this->pdo->exec(
                "ALTER TABLE `users`
                 ADD COLUMN `site_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `role_id`"
            );
        }

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_site_access` (
    `user_id` INT UNSIGNED NOT NULL,
    `site_id` INT UNSIGNED NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `site_id`),
    KEY `idx_user_site_default` (`user_id`, `is_default`),
    CONSTRAINT `fk_usa_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usa_site`
        FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec("UPDATE `users` SET `site_id` = 1 WHERE `site_id` IS NULL OR `site_id` = 0");
        $this->pdo->exec(
            "INSERT IGNORE INTO `user_site_access` (`user_id`, `site_id`, `is_default`)
             SELECT `id`, `site_id`, 1 FROM `users`"
        );

        if ($this->tableExists('permissions') && $this->tableExists('role_permissions')) {
            $this->pdo->exec(
                "INSERT INTO `permissions` (`code`, `name`, `module_code`, `sort_order`)
                 VALUES ('admin.sites', 'Quản lý Sites/Chi nhánh', 'ADMIN', 4)
                 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module_code` = VALUES(`module_code`)"
            );
            $this->pdo->exec(
                "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
                 SELECT 1, `id` FROM `permissions` WHERE `code` = 'admin.sites'"
            );
        }
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT s.*,
                    p.name AS parent_name,
                    p.code AS parent_code,
                    COUNT(u.id) AS user_count
             FROM sites s
             LEFT JOIN sites p ON p.id = s.parent_site_id
             LEFT JOIN users u ON u.site_id = s.id
             GROUP BY s.id, p.name, p.code
             ORDER BY s.is_master DESC, s.id ASC"
        );
        return $stmt->fetchAll();
    }

    public function getActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM sites WHERE is_active = 1 ORDER BY is_master DESC, code ASC"
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sites WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $site = $stmt->fetch();
        return $site ?: null;
    }

    public function create(array $data): int
    {
        $site = $this->normalizeSiteData($data);
        $this->assertUniqueCode($site['code']);

        $stmt = $this->pdo->prepare(
            "INSERT INTO sites (code, name, address, parent_site_id, is_master, is_active)
             VALUES (:code, :name, :address, :parent, :master, :active)"
        );
        $stmt->execute([
            ':code' => $site['code'],
            ':name' => $site['name'],
            ':address' => $site['address'],
            ':parent' => $site['parent_site_id'],
            ':master' => $site['is_master'],
            ':active' => $site['is_active'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($id <= 0 || $this->findById($id) === null) {
            throw new InvalidArgumentException('Site không tồn tại.');
        }

        $site = $this->normalizeSiteData($data, $id);
        $this->assertUniqueCode($site['code'], $id);

        $stmt = $this->pdo->prepare(
            "UPDATE sites
             SET code = :code,
                 name = :name,
                 address = :address,
                 parent_site_id = :parent,
                 is_master = :master,
                 is_active = :active
             WHERE id = :id"
        );

        return $stmt->execute([
            ':code' => $site['code'],
            ':name' => $site['name'],
            ':address' => $site['address'],
            ':parent' => $site['parent_site_id'],
            ':master' => $site['is_master'],
            ':active' => $site['is_active'],
            ':id' => $id,
        ]);
    }

    public function toggleActive(int $id, bool $active): bool
    {
        $site = $this->findById($id);
        if ($site === null) {
            throw new InvalidArgumentException('Site không tồn tại.');
        }
        if (!$active && (int)($_SESSION['site_id'] ?? APP_SITE_ID) === $id) {
            throw new InvalidArgumentException('Không thể tắt site đang làm việc.');
        }

        $stmt = $this->pdo->prepare("UPDATE sites SET is_active = :active WHERE id = :id");
        return $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    public function changeCurrentSite(int $id): array
    {
        $site = $this->findById($id);
        if ($site === null || !(int)$site['is_active']) {
            throw new InvalidArgumentException('Site không tồn tại hoặc đang tắt.');
        }

        $_SESSION['site_id'] = (int)$site['id'];
        $_SESSION['user_site_id'] = (int)$site['id'];
        $_SESSION['site_code'] = $site['code'];
        $_SESSION['site_name'] = $site['name'];

        return $site;
    }

    private function normalizeSiteData(array $data, int $currentId = 0): array
    {
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?: '';
        $name = trim((string)($data['name'] ?? ''));
        $parentId = (int)($data['parent_site_id'] ?? 0);

        if ($code === '') {
            throw new InvalidArgumentException('Mã site không hợp lệ.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Tên site không được để trống.');
        }
        if ($parentId > 0 && $parentId === $currentId) {
            throw new InvalidArgumentException('Site không thể trực thuộc chính nó.');
        }
        if ($parentId > 0 && $this->findById($parentId) === null) {
            throw new InvalidArgumentException('Site cha không tồn tại.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'address' => trim((string)($data['address'] ?? '')),
            'parent_site_id' => $parentId > 0 ? $parentId : null,
            'is_master' => !empty($data['is_master']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function assertUniqueCode(string $code, int $excludeId = 0): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sites WHERE code = :code AND id != :id"
        );
        $stmt->execute([':code' => $code, ':id' => $excludeId]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Mã site đã tồn tại.');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        // MariaDB: SHOW COLUMNS LIKE does not support prepared statement placeholders
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = addslashes($column);
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return (bool)$stmt->fetch();
    }

    private function tableExists(string $table): bool
    {
        // MariaDB: SHOW TABLES LIKE does not support prepared statement placeholders
        $safeTable = addslashes($table);
        $stmt = $this->pdo->query("SHOW TABLES LIKE '{$safeTable}'");
        return (bool)$stmt->fetch();
    }
}
