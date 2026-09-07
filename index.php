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
 * entry point. This file (and this directory's .htaccess) are the only
 * things meant to be reachable at this level -- everything else
 * (config/, src/, storage/, vendor/, .env, service account JSON) is
 * denied by the accompanying .htaccess.
 */

// Derived from REQUEST_URI (what the client actually requested), not
// SCRIPT_NAME. SCRIPT_NAME's shape for a directory-index request (no
// filename in the URL) varies by SAPI -- some report the resolved
// script path ("/pca-photo-hub/index.php", dirname() -> "/pca-photo-hub",
// correct), but others (common on PHP-FPM/LiteSpeed setups) report just
// the directory itself ("/pca-photo-hub/"). dirname() on that strips the
// whole "pca-photo-hub" segment, producing "/" as the base and sending
// the redirect to "/public/" at the domain root instead of
// "/pca-photo-hub/public/" -- which doesn't exist there, and can bounce
// into a redirect loop depending on the host's catch-all handling.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path ?? '', '/');

// If the request explicitly included the filename, drop it so we're
// left with just the directory.
if (substr($path, -10) === '/index.php') {
    $path = substr($path, 0, -10);
}

header('Location: ' . $path . '/public/', true, 302);
exit;
