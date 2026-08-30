<?php

namespace PCAPhotoHub;

/**
 * AlbumManager - Handles album visibility, password validation, and metadata
 */
class AlbumManager
{
    private $sheetsManager;
    private $driveManager;
    private $config;
    private $albums;

    public function __construct($config, GoogleSheetsManager $sheetsManager, GoogleDriveManager $driveManager)
    {
        $this->config = $config;
        $this->sheetsManager = $sheetsManager;
        $this->driveManager = $driveManager;
        $this->loadAndMatchAlbums();
    }

    /**
     * Load albums from Sheets and match them with Drive folders
     */
    private function loadAndMatchAlbums()
    {
        $sheetsAlbums = $this->sheetsManager->getAlbumConfig();
        $driveFolders = $this->driveManager->listRootFolders();

        // Create a map of folder names to IDs
        $folderMap = [];
        foreach ($driveFolders as $folder) {
            $folderMap[$folder->getName()] = $folder->getId();
        }

        // Match sheets albums with drive folders
        $this->albums = [];
        foreach ($sheetsAlbums as $album) {
            if (isset($folderMap[$album['name']])) {
                $album['folder_id'] = $folderMap[$album['name']];
                $this->albums[] = $album;
            }
        }
    }

    /**
     * Get all albums that are currently accepting uploads
     */
    public function getActiveAlbums()
    {
        $activeAlbums = [];
        $now = new \DateTime('now', new \DateTimeZone($this->config['app']['timezone']));

        foreach ($this->albums as $album) {
            if ($this->isAlbumAcceptingUploads($album, $now)) {
                $activeAlbums[] = $album;
            }
        }

        return $activeAlbums;
    }

    /**
     * Get all albums (including inactive ones)
     */
    public function getAllAlbums()
    {
        return $this->albums;
    }

    /**
     * Get album by folder ID
     */
    public function getAlbumByFolderId($folderId)
    {
        foreach ($this->albums as $album) {
            if ($album['folder_id'] === $folderId) {
                return $album;
            }
        }
        return null;
    }

    /**
     * Check if an album is currently accepting uploads
     */
    public function isAlbumAcceptingUploads($album, $now = null)
    {
        if ($now === null) {
            $now = new \DateTime('now', new \DateTimeZone($this->config['app']['timezone']));
        }

        if ($album['upload_start'] && $now < $album['upload_start']) {
            return false; // Before start date
        }

        if ($album['upload_end'] && $now > $album['upload_end']) {
            return false; // After end date
        }

        return true;
    }

    /**
     * Check if an album is visible (should show on homepage)
     * Albums are visible if they're currently accepting uploads or coming soon
     */
    public function isAlbumVisible($album, $now = null)
    {
        if ($now === null) {
            $now = new \DateTime('now', new \DateTimeZone($this->config['app']['timezone']));
        }

        // Show if currently accepting uploads
        if ($this->isAlbumAcceptingUploads($album, $now)) {
            return true;
        }

        // Show if coming soon (start date in future)
        if ($album['upload_start'] && $now < $album['upload_start']) {
            return true;
        }

        return false;
    }

    /**
     * Get album status text
     */
    public function getAlbumStatus($album)
    {
        $now = new \DateTime('now', new \DateTimeZone($this->config['app']['timezone']));

        if (!$this->isAlbumVisible($album, $now)) {
            return 'hidden';
        }

        if ($this->isAlbumAcceptingUploads($album, $now)) {
            if ($album['upload_end']) {
                $daysLeft = $album['upload_end']->diff($now)->days;
                return $daysLeft > 1 ? "Accepting uploads ({$daysLeft} days left)" : "Accepting uploads (closes soon)";
            }
            return 'Accepting uploads';
        }

        if ($album['upload_start'] && $now < $album['upload_start']) {
            $daysUntil = $now->diff($album['upload_start'])->days;
            return $daysUntil > 1 ? "Coming soon (opens in {$daysUntil} days)" : "Coming soon (opens soon)";
        }

        return 'Closed';
    }

    /**
     * Verify album password
     */
    public function verifyPassword($album, $password)
    {
        if (empty($album['password'])) {
            return true; // No password required
        }

        return $password === $album['password'];
    }

    /**
     * Get formatted album info for frontend
     */
    public function getAlbumInfo($album)
    {
        return [
            'folder_id' => $album['folder_id'],
            'name' => $album['name'],
            'notes' => $album['notes'],
            'status' => $this->getAlbumStatus($album),
            'accepting_uploads' => $this->isAlbumAcceptingUploads($album),
            'upload_start' => $album['upload_start'] ? $album['upload_start']->format('M d, Y') : null,
            'upload_end' => $album['upload_end'] ? $album['upload_end']->format('M d, Y') : null,
            'requires_password' => !empty($album['password']),
        ];
    }
}
