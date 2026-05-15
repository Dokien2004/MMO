<?php
require_once __DIR__ . '/backend/app/bootstrap.php';
require_once __DIR__ . '/backend/app/services/DatabaseStorage.php';
require_once __DIR__ . '/backend/app/services/ContentService.php';
require_once __DIR__ . '/backend/app/services/UserSelectedProductService.php';

$contentService = new ContentService();
$userSelectedProductService = new UserSelectedProductService();

$contents = $contentService->allContents();
$products = $userSelectedProductService->all();

$contentByProductId = [];
foreach ($contents as $content) {
    $contentByProductId[(int)($content['product_id'] ?? 0)] = $content;
}
$productsNeedContent = array_values(array_filter($products ?? [], static function (array $product) use ($contentByProductId): bool {
    return !empty($product['affiliate_url'] ?? '') && !isset($contentByProductId[(int)($product['id'] ?? 0)]);
}));

echo count($productsNeedContent) . " products need content\n";
