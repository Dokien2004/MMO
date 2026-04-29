<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

const APP_NAME = 'Affiliate MVP Laptop';
const APP_ENV = 'development';
const APP_DEBUG = true;
const APP_SITE_ID = 1;

const BASE_PATH = __DIR__ . '/../../..';
const APP_PATH = __DIR__ . '/..';
const APP_VIEWS_PATH = APP_PATH . '/views';
const STORAGE_PATH = BASE_PATH . '/storage';
const DATA_PATH = STORAGE_PATH . '/data';
const LOG_PATH = STORAGE_PATH . '/logs';

const OPENAI_API_KEY = '';
const OPENAI_MODEL = 'gpt-4o-mini';
const OPENAI_TIMEOUT_SECONDS = 45;

const FACEBOOK_PAGE_ID = '';
const FACEBOOK_PAGE_ACCESS_TOKEN = '';
const FACEBOOK_GRAPH_VERSION = 'v23.0';
const FACEBOOK_TIMEOUT_SECONDS = 45;

$localConfig = __DIR__ . '/local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', 'mmo_affiliate');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'mmo_app');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');
}


if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

function app_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function openai_api_key(): string
{
    $fromEnv = app_env('OPENAI_API_KEY', '');
    return $fromEnv !== '' ? $fromEnv : OPENAI_API_KEY;
}

function openai_model(): string
{
    $fromEnv = app_env('OPENAI_MODEL', '');
    return $fromEnv !== '' ? $fromEnv : OPENAI_MODEL;
}

function facebook_page_id(): string
{
    $fromEnv = app_env('FACEBOOK_PAGE_ID', '');
    return $fromEnv !== '' ? $fromEnv : FACEBOOK_PAGE_ID;
}

function facebook_page_access_token(): string
{
    $fromEnv = app_env('FACEBOOK_PAGE_ACCESS_TOKEN', '');
    return $fromEnv !== '' ? $fromEnv : FACEBOOK_PAGE_ACCESS_TOKEN;
}

function db_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', DB_HOST);
    $port = (int)app_env('DB_PORT', (string)DB_PORT);
    $database = app_env('DB_DATABASE', DB_DATABASE);
    $username = app_env('DB_USERNAME', DB_USERNAME);
    $password = app_env('DB_PASSWORD', DB_PASSWORD);

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
