<?php
// Configuration. On a live website this is filled in for you by install.php (it creates config/local.php).
// On localhost you can instead edit the default values below and import database.sql.
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
if (is_file(__DIR__ . '/local.php')) require __DIR__ . '/local.php';

foreach (['DB_HOST' => 'localhost', 'DB_NAME' => 'kakanin_ordering_db', 'DB_USER' => 'root', 'DB_PASS' => '',
          // Secret used to encrypt the copy of customer passwords that only the super admin can view.
          'ENC_KEY' => 'change-this-to-your-own-long-random-secret'] as $k => $v) {
    if (!defined($k)) define($k, $v);
}

// Address of the site folder, detected automatically ('' when the site is at the domain root).
// If a host reports it wrongly, add define('BASE_URL', '/your-folder'); to config/local.php.
if (!defined('BASE_URL')) {
    $docroot = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $rootDir = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);
    define('BASE_URL', ($docroot !== '' && str_starts_with($rootDir, $docroot)) ? substr($rootDir, strlen($docroot)) : '');
}

define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
define('APP_DEBUG', in_array($host, ['localhost', '127.0.0.1', '[::1]'], true));   // errors are shown only on your own computer

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/', 'secure' => IS_HTTPS]);
if (session_status() === PHP_SESSION_NONE) session_start();

set_exception_handler(function (Throwable $ex) {
    error_log('[ordering-system] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    echo APP_DEBUG ? '<pre>' . htmlspecialchars((string)$ex) . '</pre>' : 'Sorry, something went wrong. Please try again in a moment.';
});

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $ex) {
    if (is_file(ROOT_PATH . '/install.php') && !is_file(__DIR__ . '/local.php')) {   // first visit on a new website
        header('Location: ' . BASE_URL . '/install.php'); exit;
    }
    error_log('[ordering-system] database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit(APP_DEBUG ? 'Could not connect to the database: ' . htmlspecialchars($ex->getMessage()) : 'The shop is temporarily unavailable. Please try again soon.');
}

require_once __DIR__ . '/../includes/functions.php';
