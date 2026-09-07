<?php

namespace PCAPhotoHub;

/**
 * SessionManager - Tracks user sessions and their uploaded files
 * Uses persistent cookies to track uploader IDs across sessions
 */
class SessionManager
{
    private $config;
    private $uploaderId;
    private $storageFile;

    public function __construct($config)
    {
        $this->config = $config;
        $this->storageFile = __DIR__ . '/../storage/uploads/metadata.json';
        $this->initializeSession();
    }

    /**
     * Initialize or retrieve user session
     */
    private function initializeSession()
    {
        $cookieName = $this->config['session']['cookie_name'];

        if (isset($_COOKIE[$cookieName])) {
            $this->uploaderId = $_COOKIE[$cookieName];
        } else {
            // Generate new UUID for uploader
            $this->uploaderId = $this->generateUUID();
            $this->setUploadCookie($this->uploaderId);
        }
    }

    /**
     * Set persistent upload tracking cookie
     */
    private function setUploadCookie($uploaderId)
    {
        $expiryDays = $this->config['session']['cookie_expiry_days'];
        $expiry = time() + ($expiryDays * 24 * 60 * 60);

        setcookie(
            $this->config['session']['cookie_name'],
            $uploaderId,
            $expiry,
            '/',
            $_SERVER['HTTP_HOST'] ?? 'localhost',
            $this->config['session']['cookie_secure'],
            true // httponly
        );
    }

    /**
     * Get current uploader ID
     */
    public function getUploaderId()
    {
        return $this->uploaderId;
    }

    /**
     * Track uploaded file
     */
    public function trackFileUpload($fileId, $fileName, $folderId, $uploadedAt = null)
    {
        if ($uploadedAt === null) {
            $uploadedAt = date('Y-m-d H:i:s');
        }

        $metadata = $this->loadMetadata();
        $deleteDeadline = date('Y-m-d H:i:s', strtotime("+{$this->config['upload']['timeout_days']} days"));

        if (!isset($metadata[$this->uploaderId])) {
            $metadata[$this->uploaderId] = [];
        }

        $metadata[$this->uploaderId][$fileId] = [
            'file_id' => $fileId,
            'file_name' => $fileName,
            'folder_id' => $folderId,
            'uploaded_at' => $uploadedAt,
            'delete_deadline' => $deleteDeadline,
        ];

        $this->saveMetadata($metadata);
    }

    /**
     * Get files uploaded by current user
     */
    public function getUserUploadedFiles()
    {
        $metadata = $this->loadMetadata();
        if (isset($metadata[$this->uploaderId])) {
            return $metadata[$this->uploaderId];
        }
        return [];
    }

    /**
     * Get files uploaded by user in a specific folder
     */
    public function getUserUploadedFilesInFolder($folderId)
    {
        $allFiles = $this->getUserUploadedFiles();
        $folderFiles = [];

        foreach ($allFiles as $fileId => $fileData) {
            if ($fileData['folder_id'] === $folderId) {
                $folderFiles[$fileId] = $fileData;
            }
        }

        return $folderFiles;
    }

    /**
     * Check if file can be deleted by current user
     */
    public function canDeleteFile($fileId)
    {
        // Deliberately no admin bypass here. This is the member-facing path
        // (public/delete.php), which receives only a file id and so cannot
        // tell which album the file belongs to -- a session-wide admin flag
        // would therefore let an admin of one album delete from any other.
        // Admin deletion goes through admin_action.php instead, which
        // verifies both album membership and per-album authorization.
        $userFiles = $this->getUserUploadedFiles();

        if (!isset($userFiles[$fileId])) {
            return false; // User didn't upload this file
        }

        $deleteDeadline = new \DateTime($userFiles[$fileId]['delete_deadline']);
        $now = new \DateTime();

        return $now < $deleteDeadline;
    }

    /**
     * Get delete deadline for a file
     */
    public function getDeleteDeadline($fileId)
    {
        $userFiles = $this->getUserUploadedFiles();
        if (isset($userFiles[$fileId])) {
            return $userFiles[$fileId]['delete_deadline'];
        }
        return null;
    }

    /**
     * Remove file from tracking (called after deletion)
     */
    public function removeTrackedFile($fileId)
    {
        $metadata = $this->loadMetadata();

        if (isset($metadata[$this->uploaderId][$fileId])) {
            unset($metadata[$this->uploaderId][$fileId]);
            $this->saveMetadata($metadata);
            return true;
        }

        return false;
    }

    /**
     * Load metadata from storage
     */
    private function loadMetadata()
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $content = file_get_contents($this->storageFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Save metadata to storage
     */
    private function saveMetadata($metadata)
    {
        $storageDir = dirname($this->storageFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        file_put_contents($this->storageFile, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Generate UUID v4
     */
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get days remaining until delete deadline
     */
    public function getDaysUntilDeleteDeadline($fileId)
    {
        $deadline = $this->getDeleteDeadline($fileId);
        if (!$deadline) {
            return 0;
        }

        $now = new \DateTime();
        $deadlineTime = new \DateTime($deadline);
        $diff = $deadlineTime->diff($now);

        return $diff->invert ? $diff->days : 0;
    }
}
