<?php

declare(strict_types=1);

/**
 * UserProductService — Manages user-curated product selections.
 *
 * Users pick products from AI recommendations, add their own affiliate links,
 * then trigger content generation on these curated products.
 */
final class UserProductService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->ensureTable();
    }

    /**
     * List user's selected products with pagination.
     */
    public function list(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $siteId = currentSiteId();
        $userId = currentUserId();

        $where = ['usp.site_id = :site_id', 'usp.user_id = :user_id'];
        $params = [':site_id' => $siteId, ':user_id' => $userId];

        if (!empty($filters['status'])) {
            $where[] = 'usp.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'usp.product_name LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['platform'])) {
            $where[] = 'usp.source_platform = :platform';
            $params[':platform'] = $filters['platform'];
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countSql = "SELECT COUNT(*) FROM user_selected_products usp WHERE {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Data
        $sql = "SELECT usp.*, 
                       ps.overall_score, ps.recommendation, ps.trend_direction,
                       ps.sales_velocity
                FROM user_selected_products usp
                LEFT JOIN product_scores ps ON ps.product_id = usp.source_product_id AND ps.site_id = usp.site_id
                WHERE {$whereClause}
                ORDER BY usp.created_at DESC
                LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Pick a product from AI recommendations → user_selected_products.
     */
    public function pickFromRadar(int $sourceProductId): array
    {
        $siteId = currentSiteId();
        $userId = currentUserId();

        // Check duplicate
        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_selected_products 
             WHERE site_id = :sid AND user_id = :uid AND source_product_id = :pid'
        );
        $stmt->execute([':sid' => $siteId, ':uid' => $userId, ':pid' => $sourceProductId]);
        if ($stmt->fetchColumn()) {
            throw new \RuntimeException('Sản phẩm này đã được chọn trước đó.');
        }

        // Get product data
        $stmt = $this->pdo->prepare(
            'SELECT * FROM affiliate_products WHERE id = :id AND site_id = :sid'
        );
        $stmt->execute([':id' => $sourceProductId, ':sid' => $siteId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new \InvalidArgumentException('Sản phẩm không tồn tại.');
        }

        // Get AI score
        $stmt = $this->pdo->prepare(
            'SELECT overall_score FROM product_scores WHERE product_id = :pid AND site_id = :sid'
        );
        $stmt->execute([':pid' => $sourceProductId, ':sid' => $siteId]);
        $aiScore = (float)($stmt->fetchColumn() ?: 0);

        $data = [
            ':site_id' => $siteId,
            ':user_id' => $userId,
            ':source_product_id' => $sourceProductId,
            ':product_name' => $product['product_name'] ?? '',
            ':product_url' => $product['product_url'] ?? '',
            ':affiliate_url' => $product['affiliate_url'] ?? '',
            ':source_platform' => $product['source_platform'] ?? 'shopee',
            ':price' => (float)($product['price'] ?? 0),
            ':ai_score' => $aiScore,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_selected_products 
                (site_id, user_id, source_product_id, product_name, product_url, 
                 affiliate_url, source_platform, price, ai_score, status)
             VALUES 
                (:site_id, :user_id, :source_product_id, :product_name, :product_url,
                 :affiliate_url, :source_platform, :price, :ai_score, \'pending\')'
        );
        $stmt->execute($data);
        $id = (int)$this->pdo->lastInsertId();

        return ['id' => $id, 'product_name' => $data[':product_name']];
    }

    /**
     * Manually add a product (user enters data themselves).
     */
    public function addManual(array $input): array
    {
        $siteId = currentSiteId();
        $userId = currentUserId();

        $name = trim((string)($input['product_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Tên sản phẩm không được để trống.');
        }

        $data = [
            ':site_id' => $siteId,
            ':user_id' => $userId,
            ':product_name' => $name,
            ':product_url' => trim((string)($input['product_url'] ?? '')),
            ':affiliate_url' => trim((string)($input['affiliate_url'] ?? '')),
            ':source_platform' => trim((string)($input['source_platform'] ?? 'shopee')),
            ':price' => (float)($input['price'] ?? 0),
            ':commission_rate' => (float)($input['commission_rate'] ?? 0),
            ':notes' => trim((string)($input['notes'] ?? '')),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_selected_products 
                (site_id, user_id, product_name, product_url, affiliate_url, 
                 source_platform, price, commission_rate, notes, status)
             VALUES 
                (:site_id, :user_id, :product_name, :product_url, :affiliate_url,
                 :source_platform, :price, :commission_rate, :notes, \'pending\')'
        );
        $stmt->execute($data);

        return ['id' => (int)$this->pdo->lastInsertId(), 'product_name' => $name];
    }

    /**
     * Update a user-selected product (affiliate link, notes, status).
     */
    public function update(int $id, array $input): bool
    {
        $siteId = currentSiteId();
        $userId = currentUserId();

        $sets = [];
        $params = [':id' => $id, ':sid' => $siteId, ':uid' => $userId];

        $allowedFields = [
            'product_name' => 'string',
            'product_url' => 'string',
            'affiliate_url' => 'string',
            'source_platform' => 'string',
            'price' => 'float',
            'commission_rate' => 'float',
            'notes' => 'string',
            'status' => 'string',
        ];

        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $input)) {
                $val = $input[$field];
                if ($type === 'float') $val = (float)$val;
                else $val = trim((string)$val);
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $val;
            }
        }

        if (empty($sets)) return false;

        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE user_selected_products SET ' . implode(', ', $sets) .
               ' WHERE id = :id AND site_id = :sid AND user_id = :uid';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete (soft: archive) a user-selected product.
     */
    public function archive(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_selected_products SET status = \'archived\', updated_at = NOW()
             WHERE id = :id AND site_id = :sid AND user_id = :uid'
        );
        $stmt->execute([
            ':id' => $id,
            ':sid' => currentSiteId(),
            ':uid' => currentUserId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get a single product by ID (owned by current user).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT usp.*, ps.overall_score, ps.recommendation, ps.trend_direction
             FROM user_selected_products usp
             LEFT JOIN product_scores ps ON ps.product_id = usp.source_product_id AND ps.site_id = usp.site_id
             WHERE usp.id = :id AND usp.site_id = :sid AND usp.user_id = :uid'
        );
        $stmt->execute([
            ':id' => $id,
            ':sid' => currentSiteId(),
            ':uid' => currentUserId(),
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Summary stats for dashboard.
     */
    public function summary(): array
    {
        $siteId = currentSiteId();
        $userId = currentUserId();

        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = \'content_generated\' THEN 1 ELSE 0 END) AS content_ready,
                SUM(CASE WHEN status = \'published\' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN affiliate_url != \'\' THEN 1 ELSE 0 END) AS has_affiliate
             FROM user_selected_products
             WHERE site_id = :sid AND user_id = :uid AND status != \'archived\''
        );
        $stmt->execute([':sid' => $siteId, ':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'pending' => 0, 'active' => 0,
            'content_ready' => 0, 'published' => 0, 'has_affiliate' => 0,
        ];
    }

    /**
     * Get products ready for content generation (active + has affiliate link).
     */
    public function getReadyForContent(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_selected_products
             WHERE site_id = :sid AND user_id = :uid 
               AND status = \'active\' AND affiliate_url != \'\' AND content_status = \'none\'
             ORDER BY ai_score DESC, created_at ASC
             LIMIT :lim'
        );
        $stmt->bindValue(':sid', currentSiteId(), PDO::PARAM_INT);
        $stmt->bindValue(':uid', currentUserId(), PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Private: Table Setup ─────────────────────────

    private function ensureTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_selected_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    user_id INT UNSIGNED NOT NULL,
    source_product_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK affiliate_products.id (nullable if manual)',
    product_name VARCHAR(500) NOT NULL,
    product_url VARCHAR(2000) NOT NULL DEFAULT '',
    affiliate_url VARCHAR(2000) NOT NULL DEFAULT '' COMMENT 'User pastes their own aff link',
    source_platform VARCHAR(50) NOT NULL DEFAULT 'shopee',
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Commission %',
    ai_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'AI score at time of pick',
    status ENUM('pending','active','content_generated','published','paused','archived') NOT NULL DEFAULT 'pending',
    content_status VARCHAR(50) NOT NULL DEFAULT 'none',
    notes TEXT DEFAULT NULL,
    product_images JSON DEFAULT NULL COMMENT '["url1","url2"]',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usp_user (site_id, user_id, status),
    KEY idx_usp_source (source_product_id),
    KEY idx_usp_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
