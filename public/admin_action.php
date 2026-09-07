<?php
/**
 * PCA Photo Hub - Admin Action Endpoint
 *
 * Handles the destructive/irreversible admin operations: deleting photos
 * and publishing a selected set to Facebook or Instagram. Every action
 * requires an authenticated admin session plus a matching CSRF token.
 */

header('Content-Type: application/json');

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\AdminAuth;
use PCAPhotoHub\AlbumManager;
use PCAPhotoHub\FacebookPublisher;
use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\GoogleSheetsManager;
use PCAPhotoHub\InstagramPublisher;
use PCAPhotoHub\Logger;
use PCAPhotoHub\MediaLink;
use PCAPhotoHub\MetaGraph;
use PCAPhotoHub\SessionManager;

function adminJsonFail($message, $status = 400)
{
    http_response_code($status);
    $response = ['success' => false, 'error' => $message];
    if (Logger::isEnabled()) {
        $response['debug'] = Logger::getEntries();
    }
    echo json_encode($response);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        adminJsonFail('Invalid request method', 405);
    }

    $driveManager = new GoogleDriveManager($config);
    $sheetsManager = new GoogleSheetsManager($config);
    $auth = new AdminAuth($config);

    if (!$auth->isLoggedIn()) {
        adminJsonFail('Your admin session has expired. Please sign in again.', 401);
    }

    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        adminJsonFail('Invalid security token. Reload the page and try again.', 403);
    }

    $action = $_POST['action'] ?? '';
    $folderId = $_POST['folder_id'] ?? '';
    $fileIds = $_POST['file_ids'] ?? [];

    if (!is_array($fileIds)) {
        $fileIds = [];
    }
    $fileIds = array_values(array_filter(array_map('strval', $fileIds), 'strlen'));

    if ($folderId === '') {
        adminJsonFail('No album specified.');
    }
    if (empty($fileIds)) {
        adminJsonFail('No photos selected.');
    }

    $albumManager = new AlbumManager($config, $sheetsManager, $driveManager);
    $album = $albumManager->getAlbumByFolderId($folderId);
    if (!$album) {
        adminJsonFail('Album not found.');
    }

    // Admin rights are per album. Re-checked here rather than trusted from
    // the page, so a crafted request cannot reach an album this password
    // does not cover. Same message either way, so this cannot be used to
    // probe which albums exist.
    if (!$auth->canAdminAlbum($album)) {
        Logger::warning('admin_action: album access denied', ['folder_id' => $folderId, 'action' => $_POST['action'] ?? '']);
        adminJsonFail('You do not have admin access to that album.', 403);
    }

    // Confine every action to files that genuinely live in this album, so a
    // tampered request cannot reach arbitrary Drive files.
    $allowedIds = [];
    foreach ($driveManager->listFilesInFolder($folderId) as $file) {
        $allowedIds[$file->getId()] = $file->getName();
    }

    foreach ($fileIds as $id) {
        if (!isset($allowedIds[$id])) {
            adminJsonFail('One of the selected photos is not part of this album.');
        }
    }

    Logger::info('admin_action: request', [
        'action' => $action,
        'album' => $album['name'],
        'photos' => count($fileIds),
    ]);

    // ---- Delete -------------------------------------------------------
    if ($action === 'delete') {
        $sessionManager = new SessionManager($config);
        $deleted = 0;
        $errors = [];

        foreach ($fileIds as $id) {
            try {
                $driveManager->deleteFile($id);
                $sessionManager->removeTrackedFile($id);
                $deleted++;
            } catch (\Throwable $e) {
                $errors[] = $allowedIds[$id] . ': ' . $e->getMessage();
            }
        }

        Logger::info('admin_action: deleted photos', ['count' => $deleted, 'album' => $album['name']]);

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors,
            'message' => $deleted . ' photo(s) deleted.',
        ]);
        exit;
    }

    // ---- Publishing ---------------------------------------------------
    $mediaLink = new MediaLink($config);
    if (!$mediaLink->isConfigured()) {
        adminJsonFail('MEDIA_LINK_SECRET is not set in .env. Publishing needs it so Meta can fetch the photos.');
    }

    // Meta fetches each of these URLs from its own servers during publish.
    $urlById = [];
    foreach ($fileIds as $id) {
        $urlById[$id] = $mediaLink->sign($id);
    }
    $photoUrls = array_values($urlById);

    $graph = new MetaGraph($config);

    if ($action === 'publish_facebook') {
        $publisher = new FacebookPublisher($config, $graph);

        $albumName = trim((string) ($_POST['album_name'] ?? ''));
        if ($albumName === '') {
            $albumName = $album['name'];
        }
        $description = trim((string) ($_POST['description'] ?? ''));

        $coverId = $_POST['cover_file_id'] ?? '';
        $coverUrl = $urlById[$coverId] ?? null;

        $result = $publisher->publishAlbum($albumName, $description, $photoUrls, $coverUrl);

        echo json_encode([
            'success' => true,
            'message' => 'Published ' . $result['uploaded'] . ' photo(s) to the Facebook Page album "' . $albumName . '".',
            'link' => $result['link'],
            'errors' => $result['failed'],
        ]);
        exit;
    }

    if ($action === 'publish_instagram') {
        $publisher = new InstagramPublisher($config, $graph);

        $caption = trim((string) ($_POST['caption'] ?? ''));
        $tags = trim((string) ($_POST['tags'] ?? ''));

        if ($tags !== '') {
            // Accept "porsche, diablo" or "#porsche #diablo" alike.
            $parts = preg_split('/[\s,]+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
            $hashtags = [];
            foreach ($parts as $part) {
                $part = ltrim($part, '#');
                if ($part !== '') {
                    $hashtags[] = '#' . $part;
                }
            }
            if (!empty($hashtags)) {
                $caption = trim($caption . "\n\n" . implode(' ', $hashtags));
            }
        }

        $result = $publisher->publish($photoUrls, $caption);

        echo json_encode([
            'success' => true,
            'message' => 'Published ' . $result['count'] . ' photo(s) to Instagram.',
            'link' => $result['permalink'],
            'errors' => [],
        ]);
        exit;
    }

    adminJsonFail('Unknown action.');
} catch (\Throwable $e) {
    Logger::error('admin_action: failed', ['message' => $e->getMessage()]);
    error_log('Admin action error: ' . $e->getMessage());
    adminJsonFail($e->getMessage(), 400);
}
