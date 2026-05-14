-- MMO Database Schema
-- Generated: 2026-05-14 12:34:31
-- Database: mmo_affiliate

-- Table: affiliate_links
DROP TABLE IF EXISTS `affiliate_links`;
CREATE TABLE `affiliate_links` (
  `id` bigint(20) unsigned NOT NULL,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `product_id` bigint(20) unsigned NOT NULL,
  `source_platform` varchar(50) NOT NULL,
  `original_url` varchar(1000) NOT NULL,
  `affiliate_url` varchar(2000) NOT NULL,
  `campaign_code` varchar(100) DEFAULT 'MVP-LAPTOP',
  `status` varchar(50) DEFAULT 'active',
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_status` (`product_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: affiliate_products
DROP TABLE IF EXISTS `affiliate_products`;
CREATE TABLE `affiliate_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `source_platform` varchar(50) NOT NULL,
  `source_product_id` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_url` varchar(1000) NOT NULL,
  `price` decimal(15,2) DEFAULT 0.00,
  `sold_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(50) DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `affiliate_url` varchar(2000) DEFAULT '',
  `content_status` varchar(50) DEFAULT 'none',
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_source_product` (`source_platform`,`source_product_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: affiliate_task_logs
DROP TABLE IF EXISTS `affiliate_task_logs`;
CREATE TABLE `affiliate_task_logs` (
  `id` bigint(20) unsigned NOT NULL,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `task_name` varchar(150) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `result_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_payload`)),
  `error_message` text DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_status` (`task_name`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'LOGIN, LOGOUT, SCRAPER_RUN, SETTINGS_UPDATE...',
  `target_table` varchar(100) DEFAULT NULL,
  `target_id` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: automation_settings
DROP TABLE IF EXISTS `automation_settings`;
CREATE TABLE `automation_settings` (
  `site_id` int(11) NOT NULL,
  `default_campaign_code` varchar(100) NOT NULL DEFAULT 'MVP-LAPTOP',
  `default_content_provider` varchar(50) NOT NULL DEFAULT 'template_engine',
  `default_channel` varchar(50) NOT NULL DEFAULT 'fanpage_manual',
  `sync_limit` int(11) NOT NULL DEFAULT 10,
  `min_sold_count` int(11) NOT NULL DEFAULT 50,
  `top_selling_only` tinyint(1) NOT NULL DEFAULT 1,
  `auto_approve` tinyint(1) NOT NULL DEFAULT 1,
  `auto_schedule` tinyint(1) NOT NULL DEFAULT 1,
  `auto_publish` tinyint(1) NOT NULL DEFAULT 0,
  `publish_interval_minutes` int(11) NOT NULL DEFAULT 15,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`site_id`),
  CONSTRAINT `chk_settings_channel` CHECK (`default_channel` in ('fanpage_manual','fanpage_api')),
  CONSTRAINT `chk_settings_sync_limit` CHECK (`sync_limit` >= 1),
  CONSTRAINT `chk_settings_min_sold_count` CHECK (`min_sold_count` >= 0),
  CONSTRAINT `chk_settings_publish_interval` CHECK (`publish_interval_minutes` >= 5),
  CONSTRAINT `chk_settings_provider` CHECK (`default_content_provider` in ('template_engine','openai','gemini','auto'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cron_job_logs
DROP TABLE IF EXISTS `cron_job_logs`;
CREATE TABLE `cron_job_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `job_name` varchar(100) NOT NULL,
  `status` enum('running','success','partial','failed') NOT NULL DEFAULT 'running',
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  PRIMARY KEY (`id`),
  KEY `idx_site_job` (`site_id`,`job_name`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: generated_contents
DROP TABLE IF EXISTS `generated_contents`;
CREATE TABLE `generated_contents` (
  `id` bigint(20) unsigned NOT NULL,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `product_id` bigint(20) unsigned NOT NULL,
  `affiliate_link_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `hashtags` varchar(1000) DEFAULT '',
  `call_to_action` varchar(500) DEFAULT '',
  `ai_provider` varchar(50) DEFAULT 'template_engine',
  `media_type` varchar(20) NOT NULL DEFAULT 'none',
  `media_url` varchar(2000) NOT NULL DEFAULT '',
  `media_prompt` text DEFAULT NULL,
  `media_status` varchar(30) NOT NULL DEFAULT 'none',
  `status` varchar(50) DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_status` (`product_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: permissions
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `module_code` varchar(50) DEFAULT NULL COMMENT 'Link tới system_modules.code',
  `sort_order` int(11) NOT NULL DEFAULT 99,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perm_code` (`code`),
  KEY `idx_module` (`module_code`)
) ENGINE=InnoDB AUTO_INCREMENT=1218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: product_market_snapshots
DROP TABLE IF EXISTS `product_market_snapshots`;
CREATE TABLE `product_market_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `product_id` bigint(20) unsigned NOT NULL,
  `source_platform` varchar(50) NOT NULL,
  `source_product_id` varchar(100) NOT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sold_count` int(10) unsigned NOT NULL DEFAULT 0,
  `review_count` int(10) unsigned NOT NULL DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `captured_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_market_snapshots_product_time` (`site_id`,`product_id`,`captured_at`),
  KEY `idx_market_snapshots_source_time` (`site_id`,`source_platform`,`source_product_id`,`captured_at`)
) ENGINE=InnoDB AUTO_INCREMENT=340 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: role_permissions
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: roles
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL COMMENT 'admin, operator, viewer',
  `name` varchar(100) NOT NULL COMMENT 'Quản trị viên, Vận hành, Xem',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: scheduled_posts
DROP TABLE IF EXISTS `scheduled_posts`;
CREATE TABLE `scheduled_posts` (
  `id` bigint(20) unsigned NOT NULL,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `content_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `channel` varchar(50) NOT NULL,
  `scheduled_at` varchar(40) DEFAULT NULL,
  `posted_at` varchar(40) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'scheduled',
  `result_note` text DEFAULT NULL,
  `remote_post_id` varchar(255) DEFAULT '',
  `created_at` varchar(40) NOT NULL,
  `updated_at` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_content_status` (`content_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: scraper_configs
DROP TABLE IF EXISTS `scraper_configs`;
CREATE TABLE `scraper_configs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `keyword` varchar(255) NOT NULL,
  `platform` varchar(50) NOT NULL DEFAULT 'shopee',
  `min_sold_count` int(10) unsigned NOT NULL DEFAULT 100,
  `max_pages` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `sort_by` varchar(20) NOT NULL DEFAULT 'sold',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `last_run_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`last_run_result`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scraper_site_active` (`site_id`,`is_active`),
  KEY `idx_scraper_platform` (`platform`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: site_integration_configs
DROP TABLE IF EXISTS `site_integration_configs`;
CREATE TABLE `site_integration_configs` (
  `site_id` int(10) unsigned NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`site_id`,`config_key`),
  KEY `idx_site_integration_configs_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sites
DROP TABLE IF EXISTS `sites`;
CREATE TABLE `sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(500) DEFAULT NULL,
  `parent_site_id` int(10) unsigned DEFAULT NULL,
  `is_master` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_code` (`code`),
  KEY `idx_sites_parent` (`parent_site_id`),
  KEY `idx_sites_active` (`is_active`),
  CONSTRAINT `fk_sites_parent` FOREIGN KEY (`parent_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: system_modules
DROP TABLE IF EXISTS `system_modules`;
CREATE TABLE `system_modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'bi-cube',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 99,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_site_access
DROP TABLE IF EXISTS `user_site_access`;
CREATE TABLE `user_site_access` (
  `user_id` int(10) unsigned NOT NULL,
  `site_id` int(10) unsigned NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`site_id`),
  KEY `idx_user_site_default` (`user_id`,`is_default`),
  KEY `fk_usa_site` (`site_id`),
  CONSTRAINT `fk_usa_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `role_id` int(10) unsigned NOT NULL DEFAULT 2 COMMENT 'FK roles — default operator',
  `site_id` int(10) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `avatar_url` varchar(500) DEFAULT NULL,
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL COMMENT 'Khóa tạm sau 5 lần sai',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════
-- AI Product Scoring (Phase 1)
-- ══════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `product_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 1,
  `product_id` bigint(20) unsigned NOT NULL,
  `overall_score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '0-100 overall score',
  `sales_velocity` decimal(10,2) DEFAULT 0.00 COMMENT 'Units sold per day',
  `price_stability` decimal(5,2) DEFAULT 0.00 COMMENT 'Price stability 0-100',
  `review_sentiment` decimal(5,2) DEFAULT 0.00 COMMENT 'Review sentiment 0-100',
  `competition_level` decimal(5,2) DEFAULT 0.00 COMMENT 'Competition level 0-100',
  `trend_direction` enum('rising','stable','declining') DEFAULT 'stable',
  `ai_analysis` text DEFAULT NULL COMMENT 'AI reasoning JSON',
  `recommendation` enum('strong_buy','buy','hold','avoid') DEFAULT 'hold',
  `scored_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scores_site_product` (`site_id`,`product_id`),
  KEY `idx_scores_overall` (`site_id`,`overall_score` DESC),
  KEY `idx_scores_recommendation` (`site_id`,`recommendation`),
  KEY `idx_scores_trend` (`site_id`,`trend_direction`),
  KEY `idx_scores_scored_at` (`scored_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
