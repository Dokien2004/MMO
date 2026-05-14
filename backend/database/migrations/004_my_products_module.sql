-- Phase 3: User Product Selection module
-- Register MY_PRODUCTS in system_modules

INSERT IGNORE INTO system_modules (code, name, description, is_enabled, sort_order) VALUES
('MY_PRODUCTS', 'SP Đã Chọn', 'Sản phẩm người dùng đã chọn từ AI Radar hoặc thêm thủ công', 1, 35);
