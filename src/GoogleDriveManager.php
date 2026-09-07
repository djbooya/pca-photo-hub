<?php

namespace PCAPhotoHub;

use Google\Client;
use Google\Service\Drive;

/**
 * GoogleDriveManager - Handles all Google Drive API operations
 * Lists files/folders, uploads files, deletes files, retrieves metadata
 */
class GoogleDriveManager
{
    private $client;
    private $service;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->initializeClient();
    }

    /**
     * Initialize Google API client
     */
    private function initializeClient()
    {
        $jsonPath = $this->config['google']['service_account_json'];
        Logger::debug('GoogleDriveManager: validating service account JSON', ['path' => $jsonPath]);
        $email = $this->validateServiceAccountJson($jsonPath);

        Logger::debug('GoogleDriveManager: service account JSON is valid', ['client_email' => $email]);

        $this->client = new Client();
        $this->client->setAuthConfig($jsonPath);
        $this->client->addScope(Drive::DRIVE);

        $this->service = new Drive($this->client);
        Logger::debug('GoogleDriveManager: Google Drive client initialized');
    }

    /**
     * Validate the service account JSON file exists, is readable, and is valid
     * before handing it to the Google client. Without this check, a bad path
     * fails silently deep inside the client library and manifests as a
     * confusing "array offset on false" warning followed by broken auth.
     *
     * Returns the service account's client_email on success (useful for
     * logging -- it's the address that must be shared on the Drive folder).
     */
    private function validateServiceAccountJson($jsonPath)
    {
        if (empty($jsonPath)) {
            throw new \Exception("GOOGLE_SERVICE_ACCOUNT_JSON is not set in .env");
        }

        if (!file_exists($jsonPath)) {
            Logger::error('Service account JSON file not found', ['path' => $jsonPath]);
            throw new \Exception("Service account JSON file not found at: {$jsonPath}");
        }

        if (!is_readable($jsonPath)) {
            Logger::error('Service account JSON file is not readable', ['path' => $jsonPath]);
            throw new \Exception("Service account JSON file is not readable (check permissions): {$jsonPath}");
        }

        $contents = file_get_contents($jsonPath);
        $decoded = json_decode($contents, true);

        if ($decoded === null) {
            Logger::error('Service account JSON file is not valid JSON', ['path' => $jsonPath]);
            throw new \Exception("Service account JSON file is not valid JSON: {$jsonPath}");
        }

        if (empty($decoded['client_email']) || empty($decoded['private_key'])) {
            Logger::error('Service account JSON file is missing required fields', [
                'path' => $jsonPath,
                'has_client_email' => !empty($decoded['client_email']),
                'has_private_key' => !empty($decoded['private_key']),
            ]);
            throw new \Exception("Service account JSON file is missing required fields (client_email/private_key): {$jsonPath}");
        }

        return $decoded['client_email'];
    }

    /**
     * List all folders/albums in the root directory
     */
    public function listRootFolders()
    {
        $rootFolderId = $this->config['google']['drive']['root_folder_id'];
        Logger::debug('GoogleDriveManager: listing root folders', ['root_folder_id' => $rootFolderId]);

        try {
            $query = "'{$rootFolderId}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";

            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, createdTime, modifiedTime)',
                'pageSize' => 100,
                // Required for folders that live inside a Shared Drive --
                // without these, the query silently scopes to "My Drive"
                // only and returns zero results even with correct sharing.
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = $results->getFiles() ?: [];
            Logger::debug('GoogleDriveManager: listRootFolders succeeded', ['folder_count' => count($files)]);

            if (count($files) === 0) {
                Logger::warning('GoogleDriveManager: root folder returned zero subfolders', [
                    'root_folder_id' => $rootFolderId,
                    'possible_causes' => 'Wrong folder ID, folder has no subfolders yet, or subfolder names in Drive do not match the "Album Name" column in Google Sheets exactly (case-sensitive).',
                ]);
            }

            return $files;
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: listRootFolders failed', [
                'root_folder_id' => $rootFolderId,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to list folders: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * List all files in a specific folder
     */
    public function listFilesInFolder($folderId)
    {
        Logger::debug('GoogleDriveManager: listing files in folder', ['folder_id' => $folderId]);

        try {
            $query = "'{$folderId}' in parents and mimeType!='application/vnd.google-apps.folder' and trashed=false";

            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, mimeType, size, createdTime, modifiedTime, webContentLink, thumbnailLink)',
                'pageSize' => 1000,
                'orderBy' => 'createdTime desc',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = $results->getFiles() ?: [];
            Logger::debug('GoogleDriveManager: listFilesInFolder succeeded', ['file_count' => count($files)]);

            return $files;
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: listFilesInFolder failed', [
                'folder_id' => $folderId,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to list files: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * Upload a file to a specific folder
     * Returns file metadata including ID
     */
    public function uploadFile($folderId, $filePath, $fileName)
    {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: $filePath");
            }

            $mimeType = mime_content_type($filePath);
            if (!in_array($mimeType, $this->config['upload']['allowed_mime_types'])) {
                throw new \Exception("File type not allowed: $mimeType");
            }

            $fileSize = filesize($filePath);
            if ($fileSize > $this->config['upload']['max_file_size_bytes']) {
                throw new \Exception("File too large: " . ($fileSize / 1024 / 1024) . " MB");
            }

            $file = new Drive\DriveFile();
            $file->setName($fileName);
            $file->setParents([$folderId]);

            // A multipart upload needs the file contents as a string, not a
            // resource handle -- Google's client base64-encodes this value
            // internally, and PHP 8's base64_encode() throws a TypeError on
            // anything but a string. Files are capped at
            // upload.max_file_size_mb (25MB default) so reading the whole
            // thing into memory here is fine.
            $result = $this->service->files->create($file, [
                'data' => file_get_contents($filePath),
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'supportsAllDrives' => true,
            ]);

            Logger::info('GoogleDriveManager: file uploaded', ['folder_id' => $folderId, 'file_name' => $fileName, 'file_id' => $result->getId()]);

            return [
                'id' => $result->getId(),
                'name' => $result->getName(),
                'mimeType' => $result->getMimeType(),
                'createdTime' => $result->getCreatedTime(),
                'webContentLink' => $result->getWebContentLink(),
            ];
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: upload failed', [
                'folder_id' => $folderId,
                'file_name' => $fileName,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to upload file: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * Delete a file from Google Drive
     */
    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId, ['supportsAllDrives' => true]);
            Logger::info('GoogleDriveManager: file deleted', ['file_id' => $fileId]);
            return true;
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: delete failed', [
                'file_id' => $fileId,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to delete file: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata($fileId)
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, mimeType, size, createdTime, modifiedTime, webContentLink, thumbnailLink, parents',
                'supportsAllDrives' => true,
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'createdTime' => $file->getCreatedTime(),
                'modifiedTime' => $file->getModifiedTime(),
                'webContentLink' => $file->getWebContentLink(),
                'thumbnailLink' => $file->getThumbnailLink(),
                'parents' => $file->getParents() ?: [],
            ];
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: getFileMetadata failed', [
                'file_id' => $fileId,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to get file metadata: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * Check if file belongs to a specific folder
     */
    public function isFileInFolder($fileId, $folderId)
    {
        try {
            $metadata = $this->getFileMetadata($fileId);
            return in_array($folderId, $metadata['parents']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Create a folder in Google Drive
     */
    public function createFolder($folderName, $parentFolderId = null)
    {
        try {
            $file = new Drive\DriveFile();
            $file->setName($folderName);
            $file->setMimeType('application/vnd.google-apps.folder');

            if ($parentFolderId) {
                $file->setParents([$parentFolderId]);
            }

            $result = $this->service->files->create($file, [
                'fields' => 'id, name',
                'supportsAllDrives' => true,
            ]);

            return [
                'id' => $result->getId(),
                'name' => $result->getName(),
            ];
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: createFolder failed', [
                'folder_name' => $folderName,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to create folder: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }

    /**
     * Download the raw bytes of a file. Used by the signed media proxy so
     * Meta can fetch a photo during publishing without the Drive file ever
     * being made public.
     */
    public function downloadFile($fileId)
    {
        Logger::debug('GoogleDriveManager: downloading file', ['file_id' => $fileId]);

        try {
            $response = $this->service->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true,
            ]);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            Logger::error('GoogleDriveManager: downloadFile failed', [
                'file_id' => $fileId,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to download file: ' . ErrorSummarizer::summarize($e->getMessage()));
        }
    }
}
