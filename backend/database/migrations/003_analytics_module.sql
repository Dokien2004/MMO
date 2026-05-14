-- ══════════════════════════════════════════════════════════
-- Migration 003: AI Analytics & Product Scoring
-- Database: mmo_affiliate
-- Created: 2026-05-14
-- ══════════════════════════════════════════════════════════

-- ── Add ANALYTICS module ──
INSERT IGNORE INTO `system_modules` (`code`, `name`, `icon`, `description`, `sort_order`, `is_enabled`) VALUES
('ANALYTICS', 'Phân tích AI', 'bi-pie-chart-fill', 'Phân tích AI, chấm điểm sản phẩm, biểu đồ xu hướng', 3, 1);

-- Reorder: shift PRODUCTS and others down
UPDATE `system_modules` SET `sort_order` = 4 WHERE `code` = 'PRODUCTS' AND `sort_order` = 3;
UPDATE `system_modules` SET `sort_order` = 5 WHERE `code` = 'LINKS' AND `sort_order` = 4;
UPDATE `system_modules` SET `sort_order` = 6 WHERE `code` = 'CONTENTS' AND `sort_order` = 5;
UPDATE `system_modules` SET `sort_order` = 7 WHERE `code` = 'POSTS' AND `sort_order` = 6;
UPDATE `system_modules` SET `sort_order` = 8 WHERE `code` = 'SETTINGS' AND `sort_order` = 7;
UPDATE `system_modules` SET `sort_order` = 9 WHERE `code` = 'LOGS' AND `sort_order` = 8;
UPDATE `system_modules` SET `sort_order` = 10 WHERE `code` = 'ADMIN' AND `sort_order` = 9;

-- ── Add SERVER_INFO module (if not exists) ──
INSERT IGNORE INTO `system_modules` (`code`, `name`, `icon`, `description`, `sort_order`, `is_enabled`) VALUES
('SERVER_INFO', 'Server Info', 'bi-hdd-stack-fill', 'Thông tin server và hệ thống', 11, 1);

-- ── Expand product_market_snapshots (if columns don't exist) ──
-- These are handled by ensureColumn in ProductScoringService::ensureScoresTable()

-- ── Grant ANALYTICS permissions to admin role ──
-- (Analytics reuses products.view permission, no new permission codes needed)
