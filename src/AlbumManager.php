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

        $now = new \DateTime('now', new \DateTimeZone($this->config['app']['timezone']));

        // Match sheets albums with drive folders
        $this->albums = [];
        foreach ($sheetsAlbums as $album) {
            // Sheets/cache store dates as plain strings (see
            // GoogleSheetsManager) -- parse them into DateTime objects here,
            // in memory, on every request. Never store the DateTime objects
            // themselves back into anything that gets json_encode()'d, or
            // they come back as plain arrays on the next read and fatal on
            // ->format()/->diff().
            $album['upload_start'] = $this->parseDate($album['upload_start']);
            $album['upload_end'] = $this->parseDate($album['upload_end']);

            $folderId = $folderMap[$album['name']] ?? null;

            if ($folderId === null) {
                // Configured in Sheets but no matching Drive folder yet.
                // Create one automatically -- but only if the album hasn't
                // already closed for uploads; no point creating a folder
                // for an album whose window has already passed.
                $alreadyClosed = $album['upload_end'] !== null && $now > $album['upload_end'];

                if ($alreadyClosed) {
                    continue;
                }

                try {
                    Logger::info('AlbumManager: no Drive folder found for configured album, creating it', ['name' => $album['name']]);
                    $rootFolderId = $this->config['google']['drive']['root_folder_id'];
                    $created = $this->driveManager->createFolder($album['name'], $rootFolderId);
                    $folderId = $created['id'];
                    Logger::info('AlbumManager: created Drive folder', ['name' => $album['name'], 'folder_id' => $folderId]);
                } catch (\Throwable $e) {
                    Logger::error('AlbumManager: failed to auto-create Drive folder', [
                        'name' => $album['name'],
                        'message' => $e->getMessage(),
                    ]);
                    continue; // Skip for this request; will retry on the next page load
                }
            }

            $album['folder_id'] = $folderId;
            $this->albums[] = $album;
        }
    }

    /**
     * Parse a raw date string (e.g. "2026-03-01") into a DateTime in the
     * app's configured timezone. Returns null for empty/invalid input.
     */
    private function parseDate($dateString)
    {
        if (empty($dateString) || !is_string($dateString)) {
            if (!empty($dateString)) {
                // Most likely a stale cache from before dates were stored as
                // strings (an old albums.json still has the JSON-serialized
                // DateTime shape). Not a string, so not safely parseable --
                // log it and move on instead of letting DateTime's
                // constructor throw a TypeError that catch(\Exception) won't
                // catch.
                Logger::warning('AlbumManager: expected a date string but got something else -- clear storage/cache/albums.json', [
                    'type' => gettype($dateString),
                ]);
            }
            return null;
        }

        try {
            return new \DateTime($dateString, new \DateTimeZone($this->config['app']['timezone']));
        } catch (\Throwable $e) {
            Logger::warning('AlbumManager: could not parse date', ['value' => $dateString]);
            return null;
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
