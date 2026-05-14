#!/usr/bin/env php
<?php
require_once __DIR__ . '/backend/app/bootstrap.php';

$pdo = db_pdo();

$sql = file_get_contents(__DIR__ . '/db_schema.sql');
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => $s && strpos($s, '--') !== 0 && strpos($s, '/*') !== 0
);

$ok = 0;
$err = 0;
$lastErr = '';

foreach ($statements as $stmt) {
    if (!$stmt) continue;
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Exception $e) {
        $err++;
        $lastErr = $e->getMessage();
    }
}

echo "OK: $ok statements, ERR: $err\n";
if ($lastErr) echo "Last error: $lastErr\n";

// Verify
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in DB: " . count($tables) . "\n";
foreach ($tables as $t) echo "  - $t\n";

// Check affiliate_products count
$cnt = $pdo->query('SELECT COUNT(*) FROM affiliate_products')->fetchColumn();
echo "affiliate_products rows: $cnt\n";