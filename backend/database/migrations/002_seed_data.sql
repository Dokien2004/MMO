-- ══════════════════════════════════════════════════════════
-- Migration 002: Seed Data — Roles, Admin User, Modules, Permissions
-- Database: mmo_affiliate
-- Created: 2026-05-04
-- ══════════════════════════════════════════════════════════

-- ── Roles ──
INSERT INTO `roles` (`id`, `code`, `name`, `description`) VALUES
(1, 'admin',    'Quản trị viên', 'Toàn quyền hệ thống'),
(2, 'operator', 'Vận hành',      'Xem + thực thi pipeline, không sửa cấu hình hệ thống'),
(3, 'viewer',   'Xem',           'Chỉ xem dữ liệu, không thao tác');

-- ── Admin user (password: admin123) ──
-- Generate new hash: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role_id`) VALUES
('admin', 'admin@affiliate.local',
 '$2y$10$EfvOQ6i.v5e0F/9k5Hec4uVc5uYOzXVak12jLCfGkFSDVbGP4Mj0W',
 'Administrator', 1);

-- ── System Modules (map 1:1 với sidebar pages) ──
INSERT INTO `system_modules` (`code`, `name`, `icon`, `description`, `sort_order`) VALUES
('DASHBOARD', 'Dashboard',       'bi-grid-1x2-fill', 'Tổng quan hệ thống',               1),
('SCRAPER',   'Cào dữ liệu',    'bi-search',         'Cào sản phẩm từ sàn TMĐT',         2),
('PRODUCTS',  'Sản phẩm',        'bi-box-seam-fill',  'Quản lý danh sách sản phẩm',       3),
('LINKS',     'Affiliate Links', 'bi-link-45deg',     'Tạo và quản lý affiliate links',   4),
('CONTENTS',  'Nội dung AI',     'bi-file-earmark-text', 'Sinh nội dung bằng AI',         5),
('POSTS',     'Đăng bài',        'bi-send-fill',      'Lên lịch và đăng bài lên MXH',     6),
('SETTINGS',  'Cấu hình',        'bi-gear-fill',      'Cấu hình tự động hóa & API keys', 7),
('LOGS',      'Nhật ký',          'bi-activity',       'Xem log hoạt động hệ thống',       8),
('ADMIN',     'Quản trị',        'bi-shield-lock-fill','Quản lý users, modules, quyền',   9);

-- ── Permissions (20 quyền, grouped by module) ──
INSERT INTO `permissions` (`code`, `name`, `module_code`, `sort_order`) VALUES
-- Scraper
('scraper.view',       'Xem cấu hình scraper',         'SCRAPER',  1),
('scraper.run',        'Chạy scraper',                  'SCRAPER',  2),
('scraper.config',     'Tạo/sửa/xóa cấu hình scraper', 'SCRAPER', 3),
-- Products
('products.view',      'Xem danh sách sản phẩm',       'PRODUCTS', 1),
('products.sync',      'Đồng bộ sản phẩm',             'PRODUCTS', 2),
('products.delete',    'Xóa sản phẩm',                 'PRODUCTS', 3),
-- Links
('links.view',         'Xem affiliate links',           'LINKS',    1),
('links.generate',     'Tạo affiliate link',            'LINKS',    2),
-- Contents
('contents.view',      'Xem nội dung',                  'CONTENTS', 1),
('contents.generate',  'Sinh nội dung AI',              'CONTENTS', 2),
('contents.approve',   'Duyệt/từ chối nội dung',       'CONTENTS', 3),
-- Posts
('posts.view',         'Xem bài đăng',                  'POSTS',    1),
('posts.schedule',     'Lên lịch đăng bài',             'POSTS',    2),
('posts.manage',       'Quản lý trạng thái bài đăng',   'POSTS',    3),
-- Settings
('settings.view',      'Xem cấu hình hệ thống',        'SETTINGS', 1),
('settings.edit',      'Sửa cấu hình hệ thống',        'SETTINGS', 2),
-- Logs
('logs.view',          'Xem nhật ký hoạt động',          'LOGS',     1),
-- Admin
('admin.users',        'Quản lý người dùng',            'ADMIN',    1),
('admin.modules',      'Bật/tắt modules',               'ADMIN',    2),
('admin.permissions',  'Phân quyền theo role',           'ADMIN',    3),
('admin.sites',        'Quản lý Sites/Chi nhánh',        'ADMIN',    4);

-- ── Role → Permission mapping ──

-- Admin = tất cả quyền
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Operator = view + actions, không admin/settings.edit/products.delete/scraper.config
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `code` NOT IN (
    'settings.edit', 'products.delete', 'scraper.config',
    'admin.users', 'admin.modules', 'admin.permissions', 'admin.sites'
);

-- Viewer = chỉ *.view
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `code` LIKE '%.view';
