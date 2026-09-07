<?php
/**
 * PCA Photo Hub - Application Configuration
 * Loads environment variables and provides application-wide configuration
 */

use PCAPhotoHub\Logger;

$envPath = __DIR__ . '/../.env';
$bootLog = []; // buffered here; flushed into Logger once we know APP_DEBUG below

// Load .env file if it exists
// A hand-rolled line parser is used instead of parse_ini_file() because
// parse_ini_file() is unreliable across PHP builds with '#' comments and
// throws a syntax error on values/comments containing characters like
// parentheses, colons, or unescaped quotes.
if (file_exists($envPath)) {
    $bootLog[] = ".env file found at: $envPath";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $bootLog[] = 'Read ' . count($lines) . ' line(s) from .env';

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

        if ($key === '') {
            continue;
        }

        // Only skip loading from .env if this key already has a genuinely
        // non-empty value (e.g. a real value set by the hosting panel or
        // php-fpm pool). Some hosts pre-declare env vars as blank
        // placeholders, which are "set" but useless -- .env should win
        // over those rather than be silently ignored.
        $existing = $_ENV[$key] ?? getenv($key);
        $existingIsUsable = !($existing === false || $existing === null || $existing === '');

        if ($existingIsUsable) {
            $bootLog[] = "{$key}: a non-empty value already exists in the host environment -- keeping it, ignoring .env";
        } else {
            $_ENV[$key] = $value;
            $bootLog[] = "{$key}: loaded from .env" . ($value === '' ? ' [WARNING: value is empty on this line]' : '');
        }
    }
} else {
    $bootLog[] = ".env file NOT found at: $envPath -- relying entirely on host/webserver environment variables";
}

// Helper function to get a required environment variable, throwing if missing.
//
// IMPORTANT: this must NOT be named getEnv(). PHP function names are
// case-insensitive, so a function called getEnv() is the *same function*
// as PHP's built-in getenv() -- you cannot define/override it. A prior
// version of this file did exactly that: the function_exists('getEnv')
// guard silently detected the built-in and skipped defining our version,
// so every "getEnv(...)" call in this file was secretly calling PHP's
// real getenv(), which only reads the OS process environment (populated
// via putenv()) and knows nothing about the $_ENV values our .env loader
// sets above -- so it always returned false, no matter what .env said.
if (!function_exists('requireEnv')) {
    function requireEnv($key, $default = null) {
        $value = envLookup($key, $default);
        if ($value === null) {
            throw new \Exception("Missing required environment variable: $key");
        }
        return $value;
    }
}

// Helper function to get optional environment variable
if (!function_exists('getEnvOptional')) {
    function getEnvOptional($key, $default = null) {
        return envLookup($key, $default);
    }
}

// Shared lookup: $_ENV first (that's where the .env loader above puts
// things), then the real process environment, then the default.
//
// This deliberately does NOT use "$_ENV[$key] ?? getenv($key) ?? $default".
// The ?? operator only falls through on null, but getenv() returns FALSE
// for a key that isn't set -- so that chain returns false and the default
// is never reached. That silently turned every absent optional setting
// into false: MAX_FILE_SIZE_MB became (int)false = 0 (rejecting every
// upload as "too large"), ALLOWED_MIME_TYPES became [''] (permitting no
// file type at all), and so on.
if (!function_exists('envLookup')) {
    function envLookup($key, $default) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }
}

// Determine debug flag now that .env has been merged into $_ENV, and turn
// on the diagnostic logger before resolving anything else so every step
// below is captured. Logging is a complete no-op (no file writes, nothing
// shown on screen) whenever APP_DEBUG is not true.
$debugRaw = getEnvOptional('APP_DEBUG', false);
$debugEnabled = $debugRaw === 'true' || $debugRaw === true || $debugRaw === '1';
Logger::setEnabled($debugEnabled);

foreach ($bootLog as $bootLine) {
    Logger::debug($bootLine);
}

if ($debugEnabled) {
    Logger::info('APP_DEBUG is enabled -- diagnostic logging active for this request');
}

// Resolve each config value individually (rather than inline in the
// array below) so we can log what was actually found before anything
// that's missing has a chance to throw.
$serviceAccountJson = getEnvOptional('GOOGLE_SERVICE_ACCOUNT_JSON');
$driveRootFolderId = getEnvOptional('GOOGLE_DRIVE_ROOT_FOLDER_ID');
$sheetsConfigId = getEnvOptional('GOOGLE_SHEETS_CONFIG_ID');

Logger::debug('Resolved GOOGLE_SERVICE_ACCOUNT_JSON', ['value' => $serviceAccountJson ?: '(empty)']);
Logger::debug('Resolved GOOGLE_DRIVE_ROOT_FOLDER_ID', ['value' => $driveRootFolderId ?: '(empty)']);
Logger::debug('Resolved GOOGLE_SHEETS_CONFIG_ID', ['value' => $sheetsConfigId ?: '(empty)']);

if (empty($serviceAccountJson)) {
    Logger::error('GOOGLE_SERVICE_ACCOUNT_JSON resolved to empty -- this will fail as soon as a Google API call is attempted');
}
if (empty($driveRootFolderId)) {
    Logger::error('GOOGLE_DRIVE_ROOT_FOLDER_ID resolved to empty -- this will fail as soon as a Google API call is attempted');
}
if (empty($sheetsConfigId)) {
    Logger::error('GOOGLE_SHEETS_CONFIG_ID resolved to empty -- this will fail as soon as a Google API call is attempted');
}

// Application Configuration
return [
    'app' => [
        'name' => getEnvOptional('APP_NAME', 'PCA Photo Hub'),
        'version' => '1.4.0',
        'release_date' => '2026-09-07',
        'debug' => $debugEnabled,
        'base_url' => getEnvOptional('BASE_URL', 'http://localhost:8000'),
        'timezone' => getEnvOptional('TIMEZONE', 'America/Los_Angeles'),
        'logo_url' => getEnvOptional(
            'LOGO_URL',
            'https://images.squarespace-cdn.com/content/v1/665cd77fbc252a40f650d79a/b5bd754d-1817-4f92-b1ff-0eadd08f98ee/PCA+Diablo+Logo+2+Black+2000px.png?format=1500w'
        ),
    ],

    'google' => [
        'service_account_json' => requireEnv('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'drive' => [
            'root_folder_id' => requireEnv('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        ],
        'sheets' => [
            'config_id' => requireEnv('GOOGLE_SHEETS_CONFIG_ID'),
            'config_range' => getEnvOptional('GOOGLE_SHEETS_CONFIG_RANGE', 'Sheet1!A:F'),
        ],
    ],

    'upload' => [
        'max_file_size_mb' => (int) getEnvOptional('MAX_FILE_SIZE_MB', 25),
        'max_file_size_bytes' => (int) getEnvOptional('MAX_FILE_SIZE_MB', 25) * 1024 * 1024,
        'allowed_mime_types' => explode(',', getEnvOptional('ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp')),
        'timeout_days' => (int) getEnvOptional('UPLOAD_TIMEOUT_DAYS', 7),
    ],

    'admin' => [
        // The admin password itself comes from column F of the config
        // sheet (see GoogleSheetsManager::getAdminPassword), not .env.
        'session_timeout_minutes' => (int) getEnvOptional('ADMIN_SESSION_TIMEOUT_MINUTES', 60),
    ],

    // Meta fetches photos by URL when publishing, so selected photos are
    // exposed through short-lived signed links rather than ever being made
    // public in Drive. See src/MediaLink.php and public/media.php.
    'media_link' => [
        'secret' => getEnvOptional('MEDIA_LINK_SECRET', ''),
        'ttl_minutes' => (int) getEnvOptional('MEDIA_LINK_TTL_MINUTES', 15),
        'public_base_url' => getEnvOptional('PUBLIC_BASE_URL', ''),
    ],

    'meta' => [
        'graph_version' => getEnvOptional('META_GRAPH_VERSION', 'v21.0'),
        'facebook' => [
            'page_id' => getEnvOptional('FACEBOOK_PAGE_ID', ''),
            'access_token' => getEnvOptional('FACEBOOK_PAGE_ACCESS_TOKEN', ''),
        ],
        'instagram' => [
            'business_account_id' => getEnvOptional('INSTAGRAM_BUSINESS_ACCOUNT_ID', ''),
            'access_token' => getEnvOptional('INSTAGRAM_ACCESS_TOKEN', ''),
            // Meta caps a carousel at 10 items.
            'max_carousel_items' => 10,
        ],
    ],

    'session' => [
        'cookie_name' => 'pca_uploader_id',
        'cookie_expiry_days' => (int) getEnvOptional('SESSION_COOKIE_EXPIRY', 180),
        'cookie_secure' => getEnvOptional('SESSION_COOKIE_SECURE', false) === 'true' || getEnvOptional('SESSION_COOKIE_SECURE', false) === true,
    ],
];
