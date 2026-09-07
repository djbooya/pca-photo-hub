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
        $this->validateServiceAccountJson($jsonPath);

        $this->client = new Client();
        $this->client->setAuthConfig($jsonPath);
        $this->client->addScope(Drive::DRIVE);

        $this->service = new Drive($this->client);
    }

    /**
     * Validate the service account JSON file exists, is readable, and is valid
     * before handing it to the Google client. Without this check, a bad path
     * fails silently deep inside the client library and manifests as a
     * confusing "array offset on false" warning followed by broken auth.
     */
    private function validateServiceAccountJson($jsonPath)
    {
        if (empty($jsonPath)) {
            throw new \Exception("GOOGLE_SERVICE_ACCOUNT_JSON is not set in .env");
        }

        if (!file_exists($jsonPath)) {
            throw new \Exception("Service account JSON file not found at: {$jsonPath}");
        }

        if (!is_readable($jsonPath)) {
            throw new \Exception("Service account JSON file is not readable (check permissions): {$jsonPath}");
        }

        $contents = file_get_contents($jsonPath);
        $decoded = json_decode($contents, true);

        if ($decoded === null) {
            throw new \Exception("Service account JSON file is not valid JSON: {$jsonPath}");
        }

        if (empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new \Exception("Service account JSON file is missing required fields (client_email/private_key): {$jsonPath}");
        }
    }

    /**
     * List all folders/albums in the root directory
     */
    public function listRootFolders()
    {
        try {
            $rootFolderId = $this->config['google']['drive']['root_folder_id'];
            $query = "'{$rootFolderId}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";

            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, createdTime, modifiedTime)',
                'pageSize' => 100,
            ]);

            return $results->getFiles() ?: [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to list folders: " . $e->getMessage());
        }
    }

    /**
     * List all files in a specific folder
     */
    public function listFilesInFolder($folderId)
    {
        try {
            $query = "'{$folderId}' in parents and mimeType!='application/vnd.google-apps.folder' and trashed=false";

            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, mimeType, size, createdTime, modifiedTime, webContentLink, thumbnailLink)',
                'pageSize' => 1000,
                'orderBy' => 'createdTime desc',
            ]);

            return $results->getFiles() ?: [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to list files: " . $e->getMessage());
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

            $result = $this->service->files->create($file, [
                'data' => fopen($filePath, 'r'),
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
            ]);

            return [
                'id' => $result->getId(),
                'name' => $result->getName(),
                'mimeType' => $result->getMimeType(),
                'createdTime' => $result->getCreatedTime(),
                'webContentLink' => $result->getWebContentLink(),
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to upload file: " . $e->getMessage());
        }
    }

    /**
     * Delete a file from Google Drive
     */
    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete file: " . $e->getMessage());
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
        } catch (\Exception $e) {
            throw new \Exception("Failed to get file metadata: " . $e->getMessage());
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
        } catch (\Exception $e) {
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
            ]);

            return [
                'id' => $result->getId(),
                'name' => $result->getName(),
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to create folder: " . $e->getMessage());
        }
    }
}
