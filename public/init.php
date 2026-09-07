<?php
/**
 * Bootstrap file - Initialize application
 * Include this at the top of every public PHP file
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require_once __DIR__ . '/../config/config.php';

// Set timezone
if (!empty($config['app']['timezone'])) {
    date_default_timezone_set($config['app']['timezone']);
} else {
    date_default_timezone_set('America/Los_Angeles');
}

// Set error log file
ini_set('error_log', __DIR__ . '/../storage/logs/php.log');

// Create necessary directories
$requiredDirs = [
    __DIR__ . '/../storage',
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/uploads',
    __DIR__ . '/../storage/logs',
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Header settings
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

return $config;
