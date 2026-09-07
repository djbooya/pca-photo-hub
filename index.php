<?php
/**
 * PCA Photo Hub - Subdirectory Installation Redirect
 *
 * Lets this app work when the whole project folder is placed under a
 * domain (e.g. https://example.com/pca-photo-hub/) rather than pointing
 * the web server's document root directly at public/ -- many shared
 * hosting control panels only let you set a document root for an entire
 * domain/subdomain, not an arbitrary subdirectory within it.
 *
 * Visiting this directory redirects to public/, the actual application
 * entry point. This file is the only thing meant to be reachable at
 * this level -- everything else (config/, src/, storage/, vendor/,
 * .env, service account JSON) is denied by the accompanying .htaccess.
 */

// Derived from REQUEST_URI (what the client actually requested), not
// SCRIPT_NAME. SCRIPT_NAME's shape for a directory-index request (no
// filename in the URL) varies by SAPI and can produce the wrong base
// path on some hosts.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path ?? '', '/');

// If the request explicitly included the filename, drop it so we're
// left with just the directory.
if (substr($path, -10) === '/index.php') {
    $path = substr($path, 0, -10);
}

// Build a fully-qualified absolute URL rather than a scheme-relative
// path. This site runs behind a CDN/reverse proxy that terminates TLS
// and forwards to the origin over plain HTTP; a relative "Location:
// /foo/" header gets reconstructed by Apache into an absolute URL using
// its own (wrong) view of the scheme, silently downgrading HTTPS
// visitors to HTTP -- which then bounces back to HTTPS via the host's
// own HTTP->HTTPS redirect, and can loop. Providing the scheme
// ourselves, respecting X-Forwarded-Proto, avoids that entirely.
$forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || $forwardedProto === 'https'
    || ($_SERVER['SERVER_PORT'] ?? null) == 443;

$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

header('Location: ' . $scheme . '://' . $host . $path . '/public/', true, 302);
exit;
