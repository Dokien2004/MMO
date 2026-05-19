<?php

declare(strict_types=1);

final class MeigenPromptService
{
    private string $apiToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiToken = meigen_api_token();
        $this->baseUrl = rtrim(meigen_base_url(), '/');
    }

    /**
     * Search Meigen gallery for prompts matching a query.
     * @param string $query Search keyword
     * @param int $limit Max results
     * @return array Search results with id, text, model, likes, views, thumbnail_url
     */
    public function search(string $query, int $limit = 5): array
    {
        if ($this->apiToken === '') {
            return [];
        }

        $url = $this->baseUrl . '/search?' . http_build_query([
            'q' => $query,
            'limit' => $limit,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['data'])) {
            return [];
        }

        return $decoded['data'];
    }

    /**
     * Get a single prompt by its Meigen ID.
     * @param string $promptId Meigen prompt ID
     * @return array|null Prompt data
     */
    public function getById(string $promptId): ?array
    {
        if ($this->apiToken === '') {
            return null;
        }

        $url = $this->baseUrl . '/prompts/' . rawurlencode($promptId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get the best prompt for a platform/product from Meigen gallery.
     * Returns the prompt text ready to use, or null if none found.
     * @param string $platform facebook/tiktok/instagram/threads
     * @param array $product Product data (product_name, price, etc.)
     * @return string|null Best matching prompt text
     */
    public function bestPromptFor(string $platform, array $product): ?string
    {
        $productName = $product['product_name'] ?? '';

        // Build search query based on platform
        $searchQuery = $this->buildSearchQuery($platform, $productName);

        // Search Meigen
        $results = $this->search($searchQuery, 8);
        if (empty($results)) {
            return null;
        }

        // Pick the best one based on likes/views and quality
        $best = $this->selectBest($results, $platform);

        if ($best === null) {
            return null;
        }

        return $this->adaptPrompt($best['text'] ?? '', $product, $platform);
    }

    /**
     * Get multiple prompt options for a platform (for user to choose).
     * @param string $platform facebook/tiktok/instagram/threads
     * @param array $product Product data
     * @param int $count Number of options to return
     * @return array Array of prompt options with text, likes, views, thumbnail
     */
    public function promptOptions(string $platform, array $product, int $count = 4): array
    {
        $productName = $product['product_name'] ?? '';

        $results = $this->search($this->buildSearchQuery($platform, $productName), $count * 2);
        if (empty($results)) {
            return [];
        }

        $selected = array_slice($results, 0, $count);
        $options = [];
        foreach ($selected as $item) {
            $options[] = [
                'id' => $item['id'] ?? '',
                'text' => $this->adaptPrompt($item['text'] ?? '', $product, $platform),
                'raw_text' => $item['text'] ?? '',
                'model' => $item['model'] ?? '',
                'likes' => $item['likes'] ?? 0,
                'views' => $item['views'] ?? 0,
                'thumbnail_url' => $item['thumbnail_url'] ?? '',
            ];
        }

        return $options;
    }

    private function buildSearchQuery(string $platform, string $productName): string
    {
        $platformTerms = [
            'facebook' => 'clean product photo social media ad facebook',
            'tiktok' => 'bold thumbnail product tiktok vertical',
            'instagram' => 'aesthetic product photo instagram square',
            'threads' => 'minimal clean product photo casual',
        ];

        $term = $platformTerms[$platform] ?? 'product photo';

        // Add product name keywords if available
        if ($productName !== '') {
            $keywords = preg_split('/\s+/', $productName);
            $keywords = array_filter($keywords, fn($w) => mb_strlen($w) > 2);
            if (!empty($keywords)) {
                $term .= ' ' . implode(' ', array_slice($keywords, 0, 3));
            }
        }

        return $term;
    }

    private function selectBest(array $results, string $platform): ?array
    {
        $best = null;
        $bestScore = -1;

        foreach ($results as $r) {
            $likes = (int)($r['likes'] ?? 0);
            $views = (int)($r['views'] ?? 0);
            $model = $r['model'] ?? '';

            // Prefer nanobanana (high quality) for product photos
            $modelBonus = ($model === 'nanobanana') ? 500 : 0;

            // Prefer square/4:5 for facebook/instagram, vertical 9:16 for tiktok
            $aspectHint = $r['image_width'] ?? 0;
            $aspectBonus = 0;
            if ($platform === 'tiktok' && $aspectHint >= 1080) {
                $aspectBonus = 200; // likely vertical
            } elseif (in_array($platform, ['facebook', 'instagram', 'threads']) && $aspectHint <= 1200 && $aspectHint >= 960) {
                $aspectBonus = 200; // likely square or 4:5
            }

            $score = $likes + ($views / 10) + $modelBonus + $aspectBonus;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $r;
            }
        }

        return $best;
    }

    /**
     * Adapt a Meigen prompt for the specific product.
     * Handles {{PRODUCT}}, [product], placeholders, and JSON format prompts.
     */
    private function adaptPrompt(string $promptText, array $product, string $platform): string
    {
        $productName = $product['product_name'] ?? '';
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.') . ' VND';

        // Try to parse as JSON (Meigen often returns JSON format)
        $decoded = json_decode($promptText, true);
        if (is_array($decoded)) {
            // Handle different JSON structures
            if (!empty($decoded['FULL_PROMPT_STRING'])) {
                $promptText = $decoded['FULL_PROMPT_STRING'];
            } elseif (!empty($decoded['prompt'])) {
                $promptText = $decoded['prompt'];
            } elseif (!empty($decoded['USER_CUSTOMIZATION']['product_description'])) {
                $promptText = str_replace(
                    ['PRODUCT HERE', '{{USER_CUSTOMIZATION.product_description}}', '{{product}}', '[product]', '{{PRODUCT}}'],
                    [$productName, $productName, $productName, $productName, $productName],
                    $promptText
                );
            }

            // Also replace in nested structures
            $promptText = json_encode($decoded);
        }

        // Replace common placeholders
        $replacements = [
            '{{product}}' => $productName,
            '[product]' => $productName,
            '{{PRODUCT}}' => $productName,
            'PRODUCT HERE' => $productName,
            '{{product_name}}' => $productName,
            '{{price}}' => $price,
            '[price]' => $price,
            '{{title}}' => $productName,
        ];

        foreach ($replacements as $from => $to) {
            $promptText = str_replace($from, $to, $promptText);
        }

        // If still has unfilled placeholders, append product info
        if (preg_match('/\{\{[^}]+\}\}/', $promptText)) {
            $promptText .= "\n\nProduct details: " . $productName . " - Price: " . $price;
        }

        // Add platform instruction
        $platformInstructions = [
            'facebook' => "Style: clean product photo for Facebook ad, modern, professional. Format: 1:1 (1080x1080).",
            'tiktok' => "Style: bold thumbnail for TikTok, vertical 9:16 format, eye-catching, trendy.",
            'instagram' => "Style: aesthetic square product photo for Instagram, 1:1 or 4:5, clean, warm lighting.",
            'threads' => "Style: casual minimalist product photo for Threads, clean, authentic, 1:1 format.",
        ];

        $instr = $platformInstructions[$platform] ?? "Style: clean product photo. Format: 1:1.";

        return $promptText . "\n\n" . $instr;
    }
}