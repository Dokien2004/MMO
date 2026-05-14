<?php
/**
 * UserSelectedProductService — quản lý bảng user_selected_products
 * SP người dùng chọn + link aff riêng, tách khỏi dữ liệu thô cào
 */
require_once __DIR__ . '/../bootstrap.php';

class UserSelectedProductService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    public function all(string $orderBy = 'created_at DESC', int $limit = 0): array
    {
        $siteId = (int)currentSiteId();
        $sql = "SELECT * FROM user_selected_products WHERE site_id = {$siteId}";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_selected_products WHERE id = ? AND site_id = ?");
        $stmt->execute([$id, (int)currentSiteId()]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function findBySourceProductId(string $sourceProductId, string $platform): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM user_selected_products WHERE source_product_id = ? AND source_platform = ? AND site_id = ?"
        );
        $stmt->execute([$sourceProductId, $platform, (int)currentSiteId()]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Thêm hoặc cập nhật 1 sản phẩm vào bảng chọn lọc.
     * Nếu đã tồn tại (theo source_product_id + platform) → chỉ cập nhật.
     */
    public function upsert(array $data): array
    {
        $siteId = (int)currentSiteId();
        $sourceProductId = trim((string)($data['source_product_id'] ?? ''));
        $platform = trim((string)($data['source_platform'] ?? 'unknown'));
        $existing = $sourceProductId ? $this->findBySourceProductId($sourceProductId, $platform) : null;

        $now = date('Y-m-d H:i:s');
        $fields = [
            'site_id' => $siteId,
            'user_id' => (int)($data['user_id'] ?? 0),
            'source_product_id' => $sourceProductId,
            'product_name' => trim((string)($data['product_name'] ?? '')),
            'product_url' => trim((string)($data['product_url'] ?? '')),
            'affiliate_url' => trim((string)($data['affiliate_url'] ?? '')),
            'source_platform' => $platform,
            'price' => (float)($data['price'] ?? 0),
            'commission_rate' => trim((string)($data['commission_rate'] ?? '')),
            'ai_score' => (float)($data['ai_score'] ?? 0),
            'status' => trim((string)($data['status'] ?? 'pending')),
            'content_status' => trim((string)($data['content_status'] ?? 'none')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'product_images' => trim((string)($data['product_images'] ?? '')),
            'updated_at' => $now,
        ];
        $fields['created_at'] = ($existing['created_at'] ?? $now);

        if ($existing) {
            $id = (int)$existing['id'];
            $sets = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $id;
            $this->pdo->prepare("UPDATE user_selected_products SET {$sets} WHERE id = ?")->execute($vals);
        } else {
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $this->pdo->prepare("INSERT INTO user_selected_products ({$cols}) VALUES ({$placeholders})")->execute(array_values($fields));
            $id = (int)$this->pdo->lastInsertId();
        }

        return $this->findById($id) ?: $fields;
    }

    /**
     * Cập nhật affiliate_url cho 1 sản phẩm đã chọn.
     */
    public function updateAffiliateUrl(int $id, string $affiliateUrl): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_selected_products SET affiliate_url = ?, updated_at = ? WHERE id = ? AND site_id = ?"
        );
        return $stmt->execute([$affiliateUrl, date('Y-m-d H:i:s'), $id, (int)currentSiteId()]);
    }

    /**
     * Xoá 1 sản phẩm khỏi bảng chọn lọc.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_selected_products WHERE id = ? AND site_id = ?");
        return $stmt->execute([$id, (int)currentSiteId()]);
    }

    /**
     * Tổng hợp thống kê.
     */
    public function summary(): array
    {
        $siteId = (int)currentSiteId();
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM user_selected_products WHERE site_id = {$siteId}")->fetchColumn();
        $withAff = (int)$this->pdo->query("SELECT COUNT(*) FROM user_selected_products WHERE site_id = {$siteId} AND affiliate_url != ''")->fetchColumn();
        $pending = (int)$this->pdo->query("SELECT COUNT(*) FROM user_selected_products WHERE site_id = {$siteId} AND status = 'pending'")->fetchColumn();
        return [
            'total' => $total,
            'with_affiliate_link' => $withAff,
            'pending_content' => $pending,
        ];
    }
}