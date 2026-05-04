<?php

declare(strict_types=1);

/**
 * Lightweight Tiki scraper client.
 *
 * This client does not depend on the app database, so it can be used both by
 * ScraperService and by standalone source-file workers when MySQL is offline.
 */
final class TikiScraperClient
{
    private int $maxRetries = 3;

    /** @var string[] */
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scrapeSearch(string $keyword, int $page = 1, string $sortBy = 'sold', int $limit = 40): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            $keyword = 'laptop';
        }

        $query = [
            'limit' => max(1, min(100, $limit)),
            'q' => $keyword,
            'page' => max(1, $page),
        ];

        $sort = $this->mapSort($sortBy);
        if ($sort !== '') {
            $query['sort'] = $sort;
        }

        $url = 'https://tiki.vn/api/v2/products?' . http_build_query($query);
        $payload = $this->fetchJson($url);
        $items = $payload['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $products = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $product = $this->normalizeProduct($item);
            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private function mapSort(string $sortBy): string
    {
        return match ($sortBy) {
            'sold' => 'top_seller',
            'price_asc' => 'price,asc',
            'price_desc' => 'price,desc',
            'relevance' => '',
            default => 'top_seller',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeProduct(array $item): ?array
    {
        $id = (string)($item['id'] ?? '');
        $name = trim((string)($item['name'] ?? ''));
        if ($id === '' || $name === '') {
            return null;
        }

        $urlPath = trim((string)($item['url_path'] ?? ''));
        $productUrl = $urlPath !== ''
            ? 'https://tiki.vn/' . ltrim($urlPath, '/')
            : 'https://tiki.vn/p' . $id . '.html';

        $soldCount = 0;
        if (isset($item['quantity_sold']['value'])) {
            $soldCount = (int)$item['quantity_sold']['value'];
        } elseif (isset($item['impression_info'][0]['metadata']['quantity_sold'])) {
            $soldCount = (int)$item['impression_info'][0]['metadata']['quantity_sold'];
        } elseif (isset($item['visible_impression_info']['amplitude']['all_time_quantity_sold'])) {
            $soldCount = (int)$item['visible_impression_info']['amplitude']['all_time_quantity_sold'];
        }

        $brand = trim((string)($item['brand_name'] ?? ''));
        $seller = trim((string)($item['seller_name'] ?? ''));
        $notes = 'Scraped from Tiki';
        if ($brand !== '' || $seller !== '') {
            $notes .= ' - ' . trim($brand . ($brand !== '' && $seller !== '' ? ' / ' : '') . $seller);
        }

        return [
            'source_product_id' => 'TK-' . $id,
            'product_name' => $name,
            'product_url' => $productUrl,
            'price' => (float)($item['price'] ?? 0),
            'sold_count' => max(0, $soldCount),
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJson(string $url): array
    {
        $lastError = '';
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $userAgent = $this->userAgents[array_rand($this->userAgents)];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/plain, */*',
                    'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                    'Referer: https://tiki.vn/',
                    'Origin: https://tiki.vn',
                    'User-Agent: ' . $userAgent,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $lastError = 'cURL error: ' . $curlError;
            } elseif ($httpCode >= 400) {
                $lastError = 'HTTP ' . $httpCode;
            } else {
                $decoded = json_decode((string)$body, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                $lastError = 'Invalid JSON response';
            }

            usleep(500_000 * $attempt);
        }

        throw new RuntimeException('Tiki scraper failed after ' . $this->maxRetries . ' attempts: ' . $lastError);
    }
}
