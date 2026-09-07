<?php
/**
 * PCA Photo Hub - Application Configuration
 * Loads environment variables and provides application-wide configuration
 */

// Load .env file if it exists
// A hand-rolled line parser is used instead of parse_ini_file() because
// parse_ini_file() is unreliable across PHP builds with '#' comments and
// throws a syntax error on values/comments containing characters like
// parentheses, colons, or unescaped quotes.
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments (# or ;) and lines without an '='
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, if present
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '' && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

// Helper function to get environment variable with default
if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key) ?? $default;
        if ($value === null) {
            throw new \Exception("Missing required environment variable: $key");
        }
        return $value;
    }
}

// Helper function to get optional environment variable
if (!function_exists('getEnvOptional')) {
    function getEnvOptional($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?? $default;
    }
}

// Application Configuration
return [
    'app' => [
        'name' => getEnvOptional('APP_NAME', 'PCA Photo Hub'),
        'version' => '1.0.3',
        'release_date' => '2026-09-07',
        'debug' => getEnvOptional('APP_DEBUG', false) === 'true' || getEnvOptional('APP_DEBUG', false) === true,
        'base_url' => getEnvOptional('BASE_URL', 'http://localhost:8000'),
        'timezone' => getEnvOptional('TIMEZONE', 'America/Los_Angeles'),
    ],

    'google' => [
        'service_account_json' => getEnv('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'drive' => [
            'root_folder_id' => getEnv('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        ],
        'sheets' => [
            'config_id' => getEnv('GOOGLE_SHEETS_CONFIG_ID'),
            'config_range' => getEnvOptional('GOOGLE_SHEETS_CONFIG_RANGE', 'Sheet1!A:E'),
        ],
    ],

    'upload' => [
        'max_file_size_mb' => (int) getEnvOptional('MAX_FILE_SIZE_MB', 25),
        'max_file_size_bytes' => (int) getEnvOptional('MAX_FILE_SIZE_MB', 25) * 1024 * 1024,
        'allowed_mime_types' => explode(',', getEnvOptional('ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp')),
        'timeout_days' => (int) getEnvOptional('UPLOAD_TIMEOUT_DAYS', 7),
    ],

    'session' => [
        'cookie_name' => 'pca_uploader_id',
        'cookie_expiry_days' => (int) getEnvOptional('SESSION_COOKIE_EXPIRY', 180),
        'cookie_secure' => getEnvOptional('SESSION_COOKIE_SECURE', false) === 'true' || getEnvOptional('SESSION_COOKIE_SECURE', false) === true,
    ],
];
