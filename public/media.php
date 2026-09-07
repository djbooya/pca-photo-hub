<?php
/**
 * PCA Photo Hub - Signed Media Proxy
 *
 * Serves a single Drive photo over a short-lived, HMAC-signed URL. This
 * exists only because the Facebook and Instagram publishing APIs fetch
 * media themselves, server-side, from a URL we give them -- they will not
 * accept raw uploaded bytes. Rather than switching Drive files to "anyone
 * with the link" (which would outlive the publish), each selected photo
 * gets a link that expires in minutes.
 *
 * Anything not carrying a currently-valid signature gets a flat 403.
 */

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\Logger;
use PCAPhotoHub\MediaLink;

function mediaDeny($reason)
{
    Logger::warning('media.php: denied', ['reason' => $reason]);
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}

$fileId = $_GET['f'] ?? '';
$expires = $_GET['exp'] ?? '';
$signature = $_GET['sig'] ?? '';

if ($fileId === '' || $expires === '' || $signature === '') {
    mediaDeny('missing parameters');
}

$mediaLink = new MediaLink($config);

if (!$mediaLink->isConfigured()) {
    mediaDeny('MEDIA_LINK_SECRET is not set');
}

if (!$mediaLink->verify($fileId, $expires, $signature)) {
    mediaDeny('bad or expired signature');
}

try {
    $drive = new GoogleDriveManager($config);
    $meta = $drive->getFileMetadata($fileId);

    // A valid signature alone is not enough: confine the proxy to files
    // that actually live in one of the album folders under the configured
    // root, so it can never be used to read arbitrary files the service
    // account happens to have access to.
    $albumFolderIds = [];
    foreach ($drive->listRootFolders() as $folder) {
        $albumFolderIds[] = $folder->getId();
    }

    $parents = $meta['parents'] ?? [];
    if (empty(array_intersect($parents, $albumFolderIds))) {
        mediaDeny('file is not inside a configured album folder');
    }

    // Only ever serve images.
    $mimeType = $meta['mimeType'] ?? '';
    if (strpos($mimeType, 'image/') !== 0) {
        mediaDeny('not an image: ' . $mimeType);
    }

    $bytes = $drive->downloadFile($fileId);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (\Throwable $e) {
    Logger::error('media.php: failed to serve file', ['file_id' => $fileId, 'message' => $e->getMessage()]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to load image';
}
