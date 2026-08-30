<?php
/**
 * PCA Photo Hub - Delete Handler AJAX Endpoint
 * Allows users to delete their own uploaded files (within time window)
 */

header('Content-Type: application/json');

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\SessionManager;

try {
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Invalid request method');
    }

    $fileId = $_POST['file_id'] ?? null;
    if (!$fileId) {
        throw new \Exception('No file ID provided');
    }

    // Initialize managers
    $driveManager = new GoogleDriveManager($config);
    $sessionManager = new SessionManager($config);

    // Check if user can delete this file
    if (!$sessionManager->canDeleteFile($fileId)) {
        throw new \Exception('You can only delete files you uploaded within 7 days');
    }

    // Delete from Google Drive
    $driveManager->deleteFile($fileId);

    // Remove from tracking
    $sessionManager->removeTrackedFile($fileId);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully',
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    error_log("Delete error: " . $e->getMessage());
}
