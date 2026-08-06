<?php
/**
 * Bootstrap — loaded first by every page.
 * Sets constants, starts session, pulls in core modules.
 */

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Detect app URL prefix (works for root installs AND subfolders like /famsave).
// Strategy: count how deep current script is below BASE_PATH, then strip that
// many segments from SCRIPT_NAME to get the app root URL.
$_script_real = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$_base_real   = rtrim(realpath(BASE_PATH), DIRECTORY_SEPARATOR);
$_relative    = str_replace($_base_real, '', $_script_real); // e.g. \admin\page.php
$_depth       = max(0, substr_count(ltrim($_relative, '/\\'), DIRECTORY_SEPARATOR));
$_parts       = array_filter(explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '/', '/')));
$_app_parts   = array_slice(array_values($_parts), 0, count($_parts) - $_depth - 1);
define('APP_URL', $_app_parts ? '/' . implode('/', $_app_parts) : '');
unset($_script_real, $_base_real, $_relative, $_depth, $_parts, $_app_parts);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/layout.php';

// i18n — lang is stored in session, defaults to 'en'
$_lang = $_SESSION['lang'] ?? 'en';
if (!in_array($_lang, ['en', 'fr'], true)) $_lang = 'en';
require_once BASE_PATH . '/lang/' . $_lang . '.php';
