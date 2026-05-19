<?php

declare(strict_types=1);

final class AiAssistantService
{
    private string $baseUrl = 'http://127.0.0.1:20128/v1';
    private string $apiKey = 'AIzaSyCtl9-p9x74c9WqeN0yoM4LnG5xWlk5zfo';
    private string $chatModel = 'gemini/gemini-3.1-flash-lite-preview';

    // Skill descriptions for routing
    private array $skillMap = [
        'copywriting' => [
            'keywords' => ['copy', 'viết copy', 'headline', 'cta', 'quảng cáo', 'sales', 'aida', 'pas', 'fab'],
            'description' => 'Viết copy chuyển đổi cao (AIDA, PAS, FAB)',
        ],
        'article-writing' => [
            'keywords' => ['bài viết', 'blog', 'article', 'hướng dẫn', 'tutorial', 'dài'],
            'description' => 'Viết bài dài, blog post, hướng dẫn',
        ],
        'product-description-generator' => [
            'keywords' => ['mô tả sản phẩm', 'product description', 'shopee', 'amazon', 'ecommerce'],
            'description' => 'Viết mô tả sản phẩm chuẩn SEO',
        ],
        'social-media-content' => [
            'keywords' => ['social', 'facebook', 'tiktok', 'instagram', 'post', 'caption', 'hashtag'],
            'description' => 'Tạo content social media',
        ],
        'seo-keyword-researcher' => [
            'keywords' => ['seo', 'từ khóa', 'keyword', 'nghiên cứu', 'google', 'search volume'],
            'description' => 'Nghiên cứu từ khóa SEO',
        ],
        'prompt-engineering' => [
            'keywords' => ['prompt', ' Engineer', 'chain-of-thought', 'few-shot', 'system prompt'],
            'description' => 'Tối ưu prompt cho AI',
        ],
        'affiliate-link-injector' => [
            'keywords' => ['affiliate', 'link', 'chèn link', 'monetize'],
            'description' => 'Chèn affiliate link vào content',
        ],
        'web-scraping' => [
            'keywords' => ['scrape', 'cào', 'thu thập', 'web scraping', 'extraction'],
            'description' => 'Cào dữ liệu web',
        ],
        'content-generation' => [
            'keywords' => ['content', 'generate', 'tạo nội dung', 'article'],
            'description' => 'Tạo content đa format',
        ],
    ];

    public function chat(string $message, array $context = []): string
    {
        $skill = $this->detectSkill($message);
        $skillHint = $skill !== null ? $this->getSkillHint($skill) : '';

        $systemPrompt = $this->buildSystemPrompt($skill, $skillHint);
        $userPrompt = $this->buildUserPrompt($message, $context);

        return $this->callLLM($systemPrompt, $userPrompt);
    }

    private function detectSkill(string $message): ?string
    {
        $msgLower = mb_strtolower($message);
        foreach ($this->skillMap as $skill => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (mb_strpos($msgLower, mb_strtolower($keyword)) !== false) {
                    return $skill;
                }
            }
        }
        return null;
    }

    private function getSkillHint(string $skill): string
    {
        return $this->skillMap[$skill]['description'] ?? '';
    }

    private function buildSystemPrompt(?string $skill, string $skillHint): string
    {
        $base = "Bạn là Trợ lý AI cho hệ thống MMO (Affiliate Marketing). " .
            "Bạn hỗ trợ người dùng viết content, nghiên cứu từ khóa, tạo mô tả sản phẩm, " .
            "và tối ưu prompt. Luôn trả lời bằng tiếng Việt, ngắn gọn và thực tế.\n\n";

        if ($skill !== null) {
            $base .= "Skill được sử dụng: {$skillHint}\n";
            $base .= "Áp dụng framework và best practices của skill này vào câu trả lời.\n\n";
        }

        $base .= "Nếu câu hỏi liên quan đến sản phẩm cụ thể, hỏi người dùng cung cấp thông tin sản phẩm (tên, giá, link) để có thể tạo content chất lượng.\n";
        $base .= "Nếu người dùng muốn tạo content cho một sản phẩm cụ thể, hỏi họ muốn viết cho nền tảng nào (Facebook, TikTok, Instagram, Threads).\n";

        return $base;
    }

    private function buildUserPrompt(string $message, array $context): string
    {
        $prompt = $message;

        // Add context if available
        if (!empty($context['product'])) {
            $p = $context['product'];
            $prompt .= "\n\nThông tin sản phẩm:\n";
            $prompt .= "- Tên: " . ($p['product_name'] ?? 'N/A') . "\n";
            $prompt .= "- Giá: " . number_format((float)($p['price'] ?? 0), 0, ',', '.') . " VND\n";
            $prompt .= "- Nguồn: " . ($p['source_platform'] ?? 'N/A') . "\n";
            $prompt .= "- Link: " . ($p['product_url'] ?? 'N/A') . "\n";
            $prompt .= "- Link affiliate: " . ($p['affiliate_url'] ?? 'Chưa có') . "\n";
        }

        if (!empty($context['platform'])) {
            $prompt .= "\n- Nền tảng: " . $context['platform'] . "\n";
        }

        return $prompt;
    }

    private function callLLM(string $systemPrompt, string $userPrompt): string
    {
        $payload = [
            'model' => $this->chatModel,
            'temperature' => 0.7,
            'max_tokens' => 2048,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 500) {
            return "⚠️ Lỗi kết nối AI: {$curlError}. Vui lòng thử lại sau.";
        }

        if ($httpCode >= 400) {
            $err = json_decode($response, true);
            return "⚠️ Lỗi API: " . ($err['error']['message'] ?? "HTTP {$httpCode}");
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return "⚠️ Không đọc được response từ AI.";
        }

        return trim((string)($decoded['choices'][0]['message']['content'] ?? 'Không có phản hồi.'));
    }

    // Return list of available skills
    public function listSkills(): array
    {
        return $this->skillMap;
    }

    // Generate content draft using specific skill
    public function generateContent(string $type, array $product, string $platform = 'facebook'): array
    {
        $promptMap = [
            'copywriting' => $this->buildCopywritingPrompt($product, $platform),
            'product-description' => $this->buildProductDescPrompt($product),
            'social-post' => $this->buildSocialPostPrompt($product, $platform),
            'seo-keyword' => $this->buildSeoPrompt($product),
        ];

        $prompt = $promptMap[$type] ?? $this->buildCopywritingPrompt($product, $platform);
        $result = $this->callLLM(
            "Bạn là copywriter chuyên nghiệp. Trả lời ngắn gọn, dùng bullet points nếu cần. Luôn tiếng Việt.",
            $prompt
        );

        return ['result' => $result, 'type' => $type, 'platform' => $platform];
    }

    private function buildCopywritingPrompt(array $product, string $platform): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        $platformNote = match ($platform) {
            'facebook' => 'Facebook: bài viết cảm xúc, gây tò mò, khuyến khích chia sẻ',
            'tiktok' => 'TikTok: nội dung ngắn, catchy, thích hợp trend',
            'instagram' => 'Instagram: caption đẹp, story telling, hashtag trending',
            'threads' => 'Threads: nội dung chặt chẽ, thân thiện, khuyến khích bình luận',
            default => 'Content tổng hợp',
        };

        return "Viết copy theo framework AIDA cho sản phẩm:\n" .
            "- Tên: " . ($product['product_name'] ?? '') . "\n" .
            "- Giá: {$price} VND\n" .
            "- Nền tảng: {$platformNote}\n" .
            "- Link aff: " . ($product['affiliate_url'] ?? 'chưa có') . "\n\n" .
            "Yêu cầu: Headline gây sốc, body 100-150 từ, 3-5 hashtag, 1 CTA rõ ràng";
    }

    private function buildProductDescPrompt(array $product): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        return "Viết mô tả sản phẩm chuẩn SEO cho Shopee/Amazon:\n" .
            "- Tên: " . ($product['product_name'] ?? '') . "\n" .
            "- Giá: {$price} VND\n" .
            "- Nguồn: " . ($product['source_platform'] ?? '') . "\n\n" .
            "Format: Features → Benefits → CTA. 150-250 từ. Có keywords tự nhiên.";
    }

    private function buildSocialPostPrompt(array $product, string $platform): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        return "Tạo 1 bài post cho {$platform}:\n" .
            "- Sản phẩm: " . ($product['product_name'] ?? '') . "\n" .
            "- Giá: {$price} VND\n" .
            "- Link: " . ($product['affiliate_url'] ?? $product['product_url'] ?? '') . "\n\n" .
            "Format: Caption ngắn (dưới 150 từ) + 5 hashtag + CTA. Thích hợp cho {$platform}.";
    }

    private function buildSeoPrompt(array $product): string
    {
        return "Nghiên cứu từ khóa SEO cho sản phẩm:\n" .
            "- Tên: " . ($product['product_name'] ?? '') . "\n" .
            "- Giá: " . number_format((float)($product['price'] ?? 0), 0, ',', '.') . " VND\n\n" .
            "Đề xuất: 1 primary keyword, 5 secondary keywords, 3 bài viết nên viết.";
    }
}