<?php

declare(strict_types=1);

final class DatabaseStorage
{
    private PDO $pdo;

    /** @var array<string, array{table:string, columns:array<int,string>, json:array<int,string>}> */
    private array $maps = [
        'products.json' => [
            'table' => 'affiliate_products',
            'columns' => ['id', 'site_id', 'source_platform', 'source_product_id', 'product_name', 'product_url', 'price', 'status', 'notes', 'affiliate_url', 'content_status', 'created_at', 'updated_at'],
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
                if (in_array($key, ['id', 'site_id', 'product_id', 'affiliate_link_id', 'content_id'], true)) {
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
                    if ($column === 'updated_at' && ($value === null || $value === '')) {
                        $value = $row['created_at'] ?? date('c');
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
            'payload', 'result_payload' => [],
            'affiliate_link_id', 'scheduled_at', 'posted_at' => null,
            'created_at', 'updated_at' => date('c'),
            default => '',
        };
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS scheduled_posts (
    id BIGINT UNSIGNED PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    content_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    scheduled_at VARCHAR(40) NULL,
    posted_at VARCHAR(40) NULL,
    status VARCHAR(50) DEFAULT 'scheduled',
    result_note TEXT NULL,
    remote_post_id VARCHAR(255) DEFAULT '',
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    KEY idx_content_status (content_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
