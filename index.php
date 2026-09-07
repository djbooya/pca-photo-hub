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

// Path of this directory as the browser sees it, e.g. "/pca-photo-hub"
// -- computed dynamically so this works regardless of what the project
// folder is named or how deeply it's nested.
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

header('Location: ' . $basePath . '/public/', true, 302);
exit;
