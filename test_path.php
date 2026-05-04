<?php
$scriptName = '/affiliate-mvp-laptop/backend/public/index.php';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$basePath = $basePath === '/' ? '' : $basePath;
$requestPath = '/affiliate-mvp-laptop/backend/public/admin/sites';
$path = $basePath !== '' && strpos($requestPath, $basePath) === 0
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$path = $path === '' ? '/' : $path;
echo "Path: " . $path . "\n";
