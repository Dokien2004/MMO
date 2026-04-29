<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

const APP_NAME = 'Affiliate MVP Laptop';
const APP_ENV = 'development';
const APP_DEBUG = true;
const APP_SITE_ID = 1;

const BASE_PATH = __DIR__ . '/../../..';
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
