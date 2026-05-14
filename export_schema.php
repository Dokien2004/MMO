#!/usr/bin/env php
<?php
require_once __DIR__ . '/backend/app/bootstrap.php';

$pdo = db_pdo();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

$out = "-- MMO Database Schema\n";
$out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$out .= "-- Database: {$dbName}\n\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $tbl) {
    $create = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_ASSOC);
    $out .= "-- Table: {$tbl}\n";
    $out .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
    $out .= $create['Create Table'] . ";\n\n";
}

file_put_contents(__DIR__ . '/db_schema.sql', $out);
echo "Exported " . count($tables) . " tables to db_schema.sql\n";
echo "File size: " . strlen($out) . " bytes\n";