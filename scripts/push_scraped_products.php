<?php

declare(strict_types=1);

/**
 * Push scraped products from a clean browser/machine to the MMO server.
 *
 * Usage:
 *   PRODUCT_IMPORT_TOKEN=... php scripts/push_scraped_products.php products.json https://mmo.sys-erp.id.vn shopee
 *
 * JSON can be either:
 *   [ { product... }, ... ]
 * or:
 *   { "platform": "shopee", "products": [ ... ] }
 */

$sourceFile = $argv[1] ?? '';
$baseUrl = rtrim($argv[2] ?? 'https://mmo.sys-erp.id.vn', '/');
$platform = $argv[3] ?? 'shopee';
$token = getenv('PRODUCT_IMPORT_TOKEN') ?: '';

if ($sourceFile === '' || !is_file($sourceFile)) {
    fwrite(STDERR, "Usage: PRODUCT_IMPORT_TOKEN=... php scripts/push_scraped_products.php products.json https://mmo.sys-erp.id.vn shopee\n");
    exit(1);
}
if ($token === '') {
    fwrite(STDERR, "Missing PRODUCT_IMPORT_TOKEN environment variable.\n");
    exit(1);
}

$decoded = json_decode((string)file_get_contents($sourceFile), true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Invalid JSON file: {$sourceFile}\n");
    exit(1);
}

$payload = isset($decoded['products'])
    ? $decoded
    : ['platform' => $platform, 'products' => $decoded];

$ch = curl_init($baseUrl . '/api/products/import');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Import-Token: ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error !== '') {
    fwrite(STDERR, "cURL error: {$error}\n");
    exit(1);
}

echo "HTTP {$httpCode}\n";
echo $response . PHP_EOL;
exit($httpCode >= 200 && $httpCode < 300 ? 0 : 1);
