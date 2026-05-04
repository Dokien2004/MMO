-- ══════════════════════════════════════════════════════════
-- Migration 003: Sites / Multi-site foundation
-- Database: mmo_affiliate
-- Created: 2026-05-04
-- ══════════════════════════════════════════════════════════

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sites` (`id`, `code`, `name`, `address`, `is_master`, `is_active`) VALUES
(1, 'MAIN', 'Main Site', '', 1, 1)
ON DUPLICATE KEY UPDATE `id` = `id`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `role_id`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `users` SET `site_id` = 1 WHERE `site_id` IS NULL OR `site_id` = 0;

INSERT IGNORE INTO `user_site_access` (`user_id`, `site_id`, `is_default`)
SELECT `id`, `site_id`, 1 FROM `users`;

INSERT INTO `permissions` (`code`, `name`, `module_code`, `sort_order`) VALUES
('admin.sites', 'Quản lý Sites/Chi nhánh', 'ADMIN', 4)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module_code` = VALUES(`module_code`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `code` = 'admin.sites';
