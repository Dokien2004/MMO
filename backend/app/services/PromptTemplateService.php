<?php

declare(strict_types=1);

/**
 * Quản lý prompt templates từ database.
 * Hỗ trợ placeholder: {{product_name}}, {{price}}, {{platform}}, {{affiliate_url}},
 *                      {{title}}, {{body}}, {{call_to_action}}, {{sold_count}}, {{notes}},
 *                      {{price_context}}, {{sold_context}}, {{benefits_context}}, {{hashtags}}
 */
final class PromptTemplateService
{
    private PDO $pdo;

    /** @var array<string, array> Cache loaded templates per site+key */
    private static array $cache = [];

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->ensureTable();
    }

    // =========================================================================
    // READ — Lấy template active theo key
    // =========================================================================

    /**
     * Lấy template active cho 1 key (content_text, image, image_fallback, video).
     * Trả về null nếu chưa có template nào trong DB cho key này.
     */
    public function getActive(string $templateKey): ?array
    {
        $cacheKey = $this->currentSiteId() . ':' . $templateKey;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM prompt_templates
             WHERE site_id = :site_id AND template_key = :key AND is_active = 1
             ORDER BY sort_order ASC, id DESC
             LIMIT 1'
        );
        $stmt->execute([':site_id' => $this->currentSiteId(), ':key' => $templateKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $result = $row ?: null;
        self::$cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Lấy tất cả templates (active + inactive) cho site hiện tại.
     */
    public function all(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM prompt_templates
             WHERE site_id = :site_id
             ORDER BY template_key ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([':site_id' => $this->currentSiteId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lấy tất cả templates theo key (bao gồm inactive — cho UI quản lý).
     */
    public function allByKey(string $templateKey): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM prompt_templates
             WHERE site_id = :site_id AND template_key = :key
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':site_id' => $this->currentSiteId(), ':key' => $templateKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM prompt_templates WHERE id = :id AND site_id = :site_id'
        );
        $stmt->execute([':id' => $id, ':site_id' => $this->currentSiteId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    // RENDER — Thay thế placeholder trong template
    // =========================================================================

    /**
     * Render prompt template với dữ liệu sản phẩm/content.
     * Trả về null nếu chưa có template trong DB (để fallback về hardcode).
     * @param string $templateKey Loại template (content_text, image, ...)
     * @param array $product Dữ liệu sản phẩm
     * @param array $contentData Dữ liệu content (title, body, ...)
     * @param string|null $socialPlatform Mạng xã hội đích: facebook|tiktok|instagram|threads (khác với source_platform)
     */
    public function renderForProduct(string $templateKey, array $product, array $contentData = [], ?string $socialPlatform = null): ?string
    {
        // Ưu tiên platform-specific template
        if ($socialPlatform !== null && $socialPlatform !== 'general') {
            $platformKey = $templateKey . '_' . $socialPlatform;
            $template = $this->getActive($platformKey);
            if ($template !== null) {
                return $this->render($template['user_prompt'] ?? '', $product, $contentData, $socialPlatform);
            }
        }

        // Fallback về base template
        $template = $this->getActive($templateKey);
        if ($template === null) {
            return null;
        }

        return $this->render($template['user_prompt'] ?? '', $product, $contentData, $socialPlatform);
    }

    /**
     * Lấy system prompt từ template.
     * Trả về null nếu chưa có template trong DB.
     * @param string $templateKey Base template key (e.g., 'content_text')
     * @param string|null $platform Platform cụ thể (facebook/tiktok/instagram/threads)
     */
    public function systemPromptFor(string $templateKey, ?string $platform = null): ?string
    {
        // Ưu tiên platform-specific template
        if ($platform !== null && $platform !== 'general') {
            $platformKey = $templateKey . '_' . $platform;
            $template = $this->getActive($platformKey);
            if ($template !== null) {
                $systemPrompt = trim((string)($template['system_prompt'] ?? ''));
                if ($systemPrompt !== '') {
                    return $systemPrompt;
                }
            }
        }

        // Fallback về base template
        $template = $this->getActive($templateKey);
        if ($template === null) {
            return null;
        }

        $systemPrompt = trim((string)($template['system_prompt'] ?? ''));
        return $systemPrompt !== '' ? $systemPrompt : null;
    }

    /**
     * Render 1 chuỗi prompt text bằng cách thay thế {{placeholder}} bằng giá trị thực.
     * @param string|null $socialPlatform Mạng xã hội đích (facebook/tiktok/instagram/threads)
     */
    public function render(string $promptText, array $product, array $contentData = [], ?string $socialPlatform = null): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        $soldCount = (int)($product['sold_count'] ?? 0);

        // Tạo context phụ (cho video prompt)
        $priceContext = (float)($product['price'] ?? 0) > 0
            ? 'Show the product as an affordable deal, approximate price context: ' . $price . ' VND.'
            : '';
        $soldContext = $soldCount > 0
            ? 'Social proof: product has high sales, about ' . number_format($soldCount) . ' sold.'
            : '';
        $body = trim((string)($contentData['body'] ?? ''));
        $benefitsContext = $body !== ''
            ? 'Benefits to visualize: ' . mb_substr(preg_replace('/\s+/u', ' ', $body) ?? '', 0, 350)
            : '';

        // Social platform đích override {{platform}} để prompt biết đang viết cho mạng xã hội nào
        // Nếu không truyền thì dùng source_platform (shopee/tiki/lazada)
        $platformValue = $socialPlatform ?? (string)($product['source_platform'] ?? '');

        $replacements = [
            '{{product_name}}'     => (string)($product['product_name'] ?? ''),
            '{{price}}'            => $price,
            '{{platform}}'         => $platformValue,
            '{{affiliate_url}}'    => (string)($product['affiliate_url'] ?? ''),
            '{{sold_count}}'       => number_format($soldCount),
            '{{notes}}'            => (string)($product['notes'] ?? ''),
            '{{title}}'            => (string)($contentData['title'] ?? ''),
            '{{body}}'             => $body,
            '{{call_to_action}}'   => (string)($contentData['call_to_action'] ?? 'xem link mua trong phần mô tả'),
            '{{hashtags}}'         => (string)($contentData['hashtags'] ?? ''),
            '{{price_context}}'    => $priceContext,
            '{{sold_context}}'     => $soldContext,
            '{{benefits_context}}' => $benefitsContext,
        ];

        $result = str_replace(array_keys($replacements), array_values($replacements), $promptText);

        // Xóa dòng trống thừa do placeholder không có giá trị
        $result = preg_replace('/\n{3,}/', "\n\n", $result) ?? $result;
        return trim($result);
    }

    // =========================================================================
    // WRITE — CRUD
    // =========================================================================

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO prompt_templates (site_id, template_key, template_name, system_prompt, user_prompt, platform, is_active, sort_order)
             VALUES (:site_id, :key, :name, :system, :user, :platform, :active, :sort)'
        );
        $stmt->execute([
            ':site_id' => $this->currentSiteId(),
            ':key'     => trim((string)($data['template_key'] ?? '')),
            ':name'    => trim((string)($data['template_name'] ?? '')),
            ':system'  => trim((string)($data['system_prompt'] ?? '')),
            ':user'    => trim((string)($data['user_prompt'] ?? '')),
            ':platform'=> trim((string)($data['platform'] ?? 'general')),
            ':active'  => (int)($data['is_active'] ?? 1),
            ':sort'    => (int)($data['sort_order'] ?? 0),
        ]);

        self::$cache = []; // Xóa cache
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE prompt_templates
             SET template_name = :name, system_prompt = :system, user_prompt = :user,
                 platform = :platform, is_active = :active, sort_order = :sort
             WHERE id = :id AND site_id = :site_id'
        );
        $result = $stmt->execute([
            ':name'    => trim((string)($data['template_name'] ?? $existing['template_name'])),
            ':system'  => trim((string)($data['system_prompt'] ?? $existing['system_prompt'])),
            ':user'    => trim((string)($data['user_prompt'] ?? $existing['user_prompt'])),
            ':platform'=> trim((string)($data['platform'] ?? $existing['platform'])),
            ':active'  => (int)($data['is_active'] ?? $existing['is_active']),
            ':sort'    => (int)($data['sort_order'] ?? $existing['sort_order']),
            ':id'      => $id,
            ':site_id' => $this->currentSiteId(),
        ]);

        self::$cache = [];
        return $result;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM prompt_templates WHERE id = :id AND site_id = :site_id'
        );
        $result = $stmt->execute([':id' => $id, ':site_id' => $this->currentSiteId()]);
        self::$cache = [];
        return $result;
    }

    /**
     * Kích hoạt 1 template (tắt các template khác cùng key).
     */
    public function activate(int $id): bool
    {
        $template = $this->findById($id);
        if ($template === null) {
            return false;
        }

        // Tắt tất cả template cùng key
        $stmt = $this->pdo->prepare(
            'UPDATE prompt_templates SET is_active = 0
             WHERE site_id = :site_id AND template_key = :key'
        );
        $stmt->execute([':site_id' => $this->currentSiteId(), ':key' => $template['template_key']]);

        // Bật template được chọn
        $stmt = $this->pdo->prepare(
            'UPDATE prompt_templates SET is_active = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        self::$cache = [];
        return true;
    }

    /**
     * Danh sách các template_key hợp lệ với mô tả.
     */
    public static function availableKeys(): array
    {
        return [
            'content_text'   => 'Sinh bài viết (Text) — prompt gửi cho LLM',
            'image'          => 'Sinh ảnh AI — prompt chính khi có đủ thông tin sản phẩm',
            'image_fallback' => 'Sinh ảnh AI — prompt dự phòng khi thiếu thông tin',
            'video'          => 'Sinh video AI — prompt cho Kling/MeiGen/FFmpeg',
        ];
    }

    /**
     * Danh sách placeholders khả dụng kèm mô tả.
     */
    public static function availablePlaceholders(): array
    {
        return [
            '{{product_name}}'     => 'Tên sản phẩm',
            '{{price}}'            => 'Giá sản phẩm (đã format, VD: 559.000)',
            '{{platform}}'         => 'Nền tảng (shopee, tiki, lazada...)',
            '{{affiliate_url}}'    => 'Link affiliate',
            '{{sold_count}}'       => 'Số lượng đã bán',
            '{{notes}}'            => 'Ghi chú sản phẩm',
            '{{title}}'            => 'Tiêu đề bài viết đã sinh (dùng cho ảnh/video)',
            '{{body}}'             => 'Nội dung bài viết đã sinh (dùng cho video)',
            '{{call_to_action}}'   => 'Câu kêu gọi hành động',
            '{{hashtags}}'         => 'Hashtag đã sinh',
            '{{price_context}}'    => '[Video] Dòng mô tả giá bằng tiếng Anh',
            '{{sold_context}}'     => '[Video] Dòng social proof bằng tiếng Anh',
            '{{benefits_context}}' => '[Video] Trích xuất lợi ích từ body (350 ký tự)',
        ];
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    private function currentSiteId(): int
    {
        return function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;
    }

    private function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        // Skip DDL inside a transaction — CREATE TABLE triggers implicit commit in MySQL
        if ($this->pdo->inTransaction()) {
            return;
        }

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS prompt_templates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id INT NOT NULL DEFAULT 1,
            template_key VARCHAR(50) NOT NULL,
            template_name VARCHAR(200) NOT NULL DEFAULT '',
            system_prompt TEXT NOT NULL DEFAULT '',
            user_prompt TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_prompt_site_key (site_id, template_key, is_active),
            KEY idx_prompt_active (site_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
