<?php
/**
 * PCA Photo Hub - Upload Handler AJAX Endpoint
 * Processes file uploads and stores them in Google Drive
 */

header('Content-Type: application/json');

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\FileUploadHandler;
use PCAPhotoHub\SessionManager;
use PCAPhotoHub\Logger;

try {
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Invalid request method');
    }

    if (empty($_FILES['file'])) {
        throw new \Exception('No file uploaded');
    }

    $folderId = $_POST['folder_id'] ?? null;
    if (!$folderId) {
        throw new \Exception('Invalid folder ID');
    }

    // Initialize managers
    $fileHandler = new FileUploadHandler($config);
    $driveManager = new GoogleDriveManager($config);
    $sessionManager = new SessionManager($config);

    // Validate file
    if (!$fileHandler->validateUpload($_FILES['file'])) {
        $errors = $fileHandler->getErrors();
        throw new \Exception($errors[0] ?? 'File validation failed');
    }

    // Generate safe filename
    $safeFileName = $fileHandler->generateSafeFileName($_FILES['file']['name']);

    // Upload to Google Drive
    $result = $driveManager->uploadFile(
        $folderId,
        $_FILES['file']['tmp_name'],
        $safeFileName
    );

    // Track file upload in session
    $sessionManager->trackFileUpload(
        $result['id'],
        $safeFileName,
        $folderId
    );

    // Clean up temp file
    if (file_exists($_FILES['file']['tmp_name'])) {
        unlink($_FILES['file']['tmp_name']);
    }

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'file_id' => $result['id'],
        'file_name' => $result['name'],
        'message' => 'File uploaded successfully',
    ]);

} catch (\Exception $e) {
    Logger::error('upload.php: upload failed', ['message' => $e->getMessage()]);
    error_log("Upload error: " . $e->getMessage());

    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
    if (Logger::isEnabled()) {
        $response['debug'] = Logger::getEntries();
    }
    echo json_encode($response);
}
