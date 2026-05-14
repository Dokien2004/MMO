CREATE DATABASE IF NOT EXISTS mmo_affiliate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mmo_affiliate;

CREATE TABLE IF NOT EXISTS affiliate_products (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    source_platform VARCHAR(50) NOT NULL,
    source_product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_url VARCHAR(1000) NOT NULL,
    price DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'new',
    notes TEXT NULL,
    affiliate_url VARCHAR(2000) DEFAULT '',
    content_status VARCHAR(50) DEFAULT 'none',
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    UNIQUE KEY uk_source_product (source_platform, source_product_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_links (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    source_platform VARCHAR(50) NOT NULL,
    original_url VARCHAR(1000) NOT NULL,
    affiliate_url VARCHAR(2000) NOT NULL,
    campaign_code VARCHAR(100) DEFAULT 'MVP-LAPTOP',
    status VARCHAR(50) DEFAULT 'active',
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    KEY idx_product_status (product_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_contents (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    affiliate_link_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    hashtags VARCHAR(1000) DEFAULT '',
    call_to_action VARCHAR(500) DEFAULT '',
    ai_provider VARCHAR(50) DEFAULT 'template_engine',
    status VARCHAR(50) DEFAULT 'draft',
    notes TEXT NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    KEY idx_product_status (product_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_posts (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    content_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    social_channel_id INT UNSIGNED NULL,
    scheduled_at VARCHAR(40) NULL,
    posted_at VARCHAR(40) NULL,
    status VARCHAR(50) DEFAULT 'scheduled',
    result_note TEXT NULL,
    remote_post_id VARCHAR(255) DEFAULT '',
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    KEY idx_content_status (content_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_task_logs (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    task_name VARCHAR(150) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    payload JSON NULL,
    result_payload JSON NULL,
    error_message TEXT NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    KEY idx_task_status (task_name, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
