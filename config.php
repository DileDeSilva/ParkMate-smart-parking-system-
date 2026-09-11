<?php
/**
 * ParkMate - application configuration
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Database credentials
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'parkmate');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP's root password is blank by default

// ---------------------------------------------------------------------
// Application settings
// ---------------------------------------------------------------------
define('APP_NAME', 'ParkMate');
define('CURRENCY', 'LKR');
define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Asia/Colombo');


$docRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT']))
    : '';
$appPath = str_replace('\\', '/', APP_ROOT);
$guess   = ($docRoot !== '' && str_starts_with($appPath, $docRoot))
    ? substr($appPath, strlen($docRoot))
    : '';
define('BASE_URL', rtrim($guess, '/'));

// ---------------------------------------------------------------------
// Error reporting 
// ---------------------------------------------------------------------
define('DEBUG', true);
if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ---------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// Database connection (PDO, exceptions on, native prepared statements)
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Keep PHP and MySQL on the same clock so booking windows line up.
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            http_response_code(500);
            if (DEBUG) {
                exit('Database connection failed: ' . $e->getMessage());
            }
            exit('The site cannot reach its database right now.');
        }
    }

    return $pdo;
}

require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';
