CREATE DATABASE IF NOT EXISTS mmo_affiliate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mmo_affiliate;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS affiliate_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    source_platform VARCHAR(50) NOT NULL,
    source_product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_url VARCHAR(1000) NOT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sold_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    affiliate_url VARCHAR(2000) NOT NULL DEFAULT '',
    content_status VARCHAR(50) NOT NULL DEFAULT 'none',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_products_site_source (site_id, source_platform, source_product_id),
    KEY idx_products_site_status (site_id, status),
    KEY idx_products_site_content_status (site_id, content_status),
    KEY idx_products_sold_count (site_id, sold_count),
    KEY idx_products_updated_at (updated_at),
    CONSTRAINT chk_products_status CHECK (status IN ('new', 'linked', 'content_ready', 'posted', 'archived')),
    CONSTRAINT chk_products_content_status CHECK (content_status IN ('none', 'draft', 'approved', 'rejected', 'used')),
    CONSTRAINT chk_products_price_non_negative CHECK (price >= 0),
    CONSTRAINT chk_products_sold_count_non_negative CHECK (sold_count >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    source_platform VARCHAR(50) NOT NULL,
    original_url VARCHAR(1000) NOT NULL,
    affiliate_url VARCHAR(2000) NOT NULL,
    campaign_code VARCHAR(100) NOT NULL DEFAULT 'MVP-LAPTOP',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_links_site_product (site_id, product_id),
    KEY idx_links_site_status (site_id, status),
    KEY idx_links_campaign (campaign_code),
    KEY idx_links_updated_at (updated_at),
    CONSTRAINT fk_links_product
        FOREIGN KEY (product_id) REFERENCES affiliate_products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_links_status CHECK (status IN ('active', 'expired', 'error'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_contents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    affiliate_link_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    hashtags VARCHAR(1000) NOT NULL DEFAULT '',
    call_to_action VARCHAR(500) NOT NULL DEFAULT '',
    ai_provider VARCHAR(50) NOT NULL DEFAULT 'template_engine',
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_contents_site_product (site_id, product_id),
    KEY idx_contents_site_status (site_id, status),
    KEY idx_contents_provider (ai_provider),
    KEY idx_contents_updated_at (updated_at),
    CONSTRAINT fk_contents_product
        FOREIGN KEY (product_id) REFERENCES affiliate_products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_contents_link
        FOREIGN KEY (affiliate_link_id) REFERENCES affiliate_links (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_contents_status CHECK (status IN ('draft', 'approved', 'rejected', 'used'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    content_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    scheduled_at DATETIME NULL,
    posted_at DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    result_note TEXT NULL,
    remote_post_id VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_posts_site_content (site_id, content_id),
    KEY idx_posts_site_status_schedule (site_id, status, scheduled_at),
    KEY idx_posts_channel_status (channel, status),
    KEY idx_posts_product (product_id),
    CONSTRAINT fk_posts_content
        FOREIGN KEY (content_id) REFERENCES generated_contents (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_posts_product
        FOREIGN KEY (product_id) REFERENCES affiliate_products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_posts_status CHECK (status IN ('scheduled', 'success', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliate_task_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    task_name VARCHAR(150) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payload JSON NULL,
    result_payload JSON NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_site_task_status (site_id, task_name, status),
    KEY idx_logs_created_at (created_at),
    CONSTRAINT chk_task_logs_status CHECK (status IN ('pending', 'success', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_settings (
    site_id INT NOT NULL,
    default_campaign_code VARCHAR(100) NOT NULL DEFAULT 'MVP-LAPTOP',
    default_content_provider VARCHAR(50) NOT NULL DEFAULT 'template_engine',
    default_channel VARCHAR(50) NOT NULL DEFAULT 'fanpage_manual',
    sync_limit INT NOT NULL DEFAULT 10,
    min_sold_count INT NOT NULL DEFAULT 50,
    top_selling_only TINYINT(1) NOT NULL DEFAULT 1,
    auto_approve TINYINT(1) NOT NULL DEFAULT 1,
    auto_schedule TINYINT(1) NOT NULL DEFAULT 1,
    auto_publish TINYINT(1) NOT NULL DEFAULT 0,
    publish_interval_minutes INT NOT NULL DEFAULT 15,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (site_id),
    CONSTRAINT chk_settings_provider CHECK (default_content_provider IN ('template_engine', 'openai')),
    CONSTRAINT chk_settings_channel CHECK (default_channel IN ('fanpage_manual', 'fanpage_api')),
    CONSTRAINT chk_settings_sync_limit CHECK (sync_limit >= 1),
    CONSTRAINT chk_settings_min_sold_count CHECK (min_sold_count >= 0),
    CONSTRAINT chk_settings_publish_interval CHECK (publish_interval_minutes >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
