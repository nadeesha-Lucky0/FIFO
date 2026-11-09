<?php
/**
 * Application Configuration
 * FIFO Management System
 */

// Define root directory
define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('DATABASE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'database');

// Set Sri Lanka Timezone
date_default_timezone_set('Asia/Colombo');

// Application metadata
define('APP_NAME', 'Electricity Generation Lanka (Pvt) Ltd');
define('APP_SHORT_NAME', 'EGL');
define('APP_TAGLINE', 'FIFO Inventory & Energy Generation Operations Platform');
define('APP_VERSION', '1.0.0');

// Dynamic Base URL detection for standard XAMPP (e.g., http://localhost/FIFO or http://127.0.0.1:8000)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detect subfolder in htdocs if any (e.g. /FIFO)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Extract base project root url
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$realRoot = str_replace('\\', '/', ROOT_PATH);
$subPath = '';
if ($docRoot && strpos($realRoot, $docRoot) === 0) {
    $subPath = substr($realRoot, strlen($docRoot));
}
$subPath = '/' . trim($subPath, '/');
if ($subPath === '/') {
    $subPath = '';
}

define('BASE_PATH', $subPath);
define('BASE_URL', rtrim($protocol . $host . $subPath, '/'));

// Database configuration
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fifo');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('SQLITE_FILE', DATABASE_PATH . DIRECTORY_SEPARATOR . 'fifo_system.db');

// Autoload helper
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\Core\\' => APP_PATH . '/core/',
        'App\\Models\\' => APP_PATH . '/models/',
        'App\\Controllers\\' => APP_PATH . '/controllers/',
        'App\\Config\\' => APP_PATH . '/config/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strpos($class, $prefix) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Start session securely
require_once APP_PATH . '/core/Session.php';
\App\Core\Session::start();
