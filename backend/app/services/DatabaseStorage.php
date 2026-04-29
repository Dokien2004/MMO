<?php

declare(strict_types=1);

final class DatabaseStorage
{
    private PDO $pdo;

    /** @var array<string, array{table:string, columns:array<int,string>, json:array<int,string>}> */
    private array $maps = [
        'products.json' => [
            'table' => 'affiliate_products',
            'columns' => ['id', 'site_id', 'source_platform', 'source_product_id', 'product_name', 'product_url', 'price', 'sold_count', 'status', 'notes', 'affiliate_url', 'content_status', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'affiliate_links.json' => [
            'table' => 'affiliate_links',
            'columns' => ['id', 'site_id', 'product_id', 'source_platform', 'original_url', 'affiliate_url', 'campaign_code', 'status', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'generated_contents.json' => [
            'table' => 'generated_contents',
            'columns' => ['id', 'site_id', 'product_id', 'affiliate_link_id', 'title', 'body', 'hashtags', 'call_to_action', 'ai_provider', 'status', 'notes', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'scheduled_posts.json' => [
            'table' => 'scheduled_posts',
            'columns' => ['id', 'site_id', 'content_id', 'product_id', 'channel', 'scheduled_at', 'posted_at', 'status', 'result_note', 'remote_post_id', 'created_at', 'updated_at'],
            'json' => [],
        ],
        'task_logs.json' => [
            'table' => 'affiliate_task_logs',
            'columns' => ['id', 'site_id', 'task_name', 'status', 'payload', 'result_payload', 'error_message', 'created_at', 'updated_at'],
            'json' => ['payload', 'result_payload'],
        ],
    ];

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->ensureSchema();
    }

    public function read(string $fileName): array
    {
        $map = $this->map($fileName);
        $table = $map['table'];
        $orderColumn = $fileName === 'task_logs.json' ? 'id' : 'updated_at';
        $stmt = $this->pdo->query("SELECT * FROM {$table} ORDER BY {$orderColumn} DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            foreach ($map['json'] as $jsonColumn) {
                $decoded = json_decode((string)($row[$jsonColumn] ?? ''), true);
                $row[$jsonColumn] = is_array($decoded) ? $decoded : [];
            }
            foreach ($row as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if (in_array($key, ['id', 'site_id', 'product_id', 'affiliate_link_id', 'content_id', 'sold_count'], true)) {
                    $row[$key] = (int)$value;
                }
                if ($key === 'price') {
                    $row[$key] = (float)$value;
                }
            }
        }
        unset($row);

        return $rows;
    }

    public function write(string $fileName, array $payload): void
    {
        $map = $this->map($fileName);
        $table = $map['table'];
        $columns = $map['columns'];
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM ' . $table);
            $stmt = $this->pdo->prepare($sql);
            foreach ($payload as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $params = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? $this->defaultValue($column);
                    if (in_array($column, $map['json'], true)) {
                        $value = json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE);
                    }
                    if (in_array($column, ['created_at', 'updated_at', 'scheduled_at', 'posted_at'], true)) {
                        $value = $this->normalizeDateValue($value);
                    }
                    if ($column === 'updated_at' && ($value === null || $value === '')) {
                        $value = $this->normalizeDateValue($row['created_at'] ?? date('Y-m-d H:i:s'));
                    }
                    $params[':' . $column] = $value;
                }
                $stmt->execute($params);
            }
            $this->pdo->commit();
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    private function map(string $fileName): array
    {
        if (!isset($this->maps[$fileName])) {
            throw new InvalidArgumentException('Storage DB chua ho tro file: ' . $fileName);
        }
        return $this->maps[$fileName];
    }

    private function defaultValue(string $column): mixed
    {
        return match ($column) {
            'site_id' => APP_SITE_ID,
            'price' => 0,
            'sold_count' => 0,
            'payload', 'result_payload' => [],
            'affiliate_link_id', 'scheduled_at', 'posted_at' => null,
            'created_at', 'updated_at' => date('Y-m-d H:i:s'),
            default => '',
        };
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return null;
        }

        $timestamp = strtotime($stringValue);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Gia tri thoi gian khong hop le: ' . $stringValue);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->ensureColumn('affiliate_products', 'sold_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER price');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}
