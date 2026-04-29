CREATE TABLE affiliate_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    source_platform VARCHAR(50) NOT NULL,
    source_product_id VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_url VARCHAR(1000) NOT NULL,
    price DECIMAL(15,2) DEFAULT 0,
    status ENUM('new', 'linked', 'content_ready', 'posted', 'archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source_product (source_platform, source_product_id)
);

CREATE TABLE affiliate_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    source_platform VARCHAR(50) NOT NULL,
    original_url VARCHAR(1000) NOT NULL,
    affiliate_url VARCHAR(2000) NOT NULL,
    campaign_code VARCHAR(100) DEFAULT 'MVP-LAPTOP',
    status ENUM('active', 'expired', 'error') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_product_status (product_id, status)
);

CREATE TABLE generated_contents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    affiliate_link_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    hashtags VARCHAR(1000) DEFAULT '',
    call_to_action VARCHAR(500) DEFAULT '',
    ai_provider VARCHAR(50) DEFAULT 'template_engine',
    status ENUM('draft', 'approved', 'rejected', 'used') DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_product_status (product_id, status)
);

CREATE TABLE scheduled_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    content_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    scheduled_at TIMESTAMP NULL,
    posted_at TIMESTAMP NULL,
    status ENUM('scheduled', 'success', 'failed') DEFAULT 'scheduled',
    result_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_content_status (content_id, status)
);

CREATE TABLE affiliate_task_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    task_name VARCHAR(150) NOT NULL,
    status ENUM('pending', 'running', 'success', 'failed') DEFAULT 'pending',
    payload JSON NULL,
    result_payload JSON NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
