<?php

namespace PCAPhotoHub;

use Google\Client;
use Google\Service\Sheets;

/**
 * GoogleSheetsManager - Handles reading album configuration from Google Sheets
 * Caches results for 5 minutes to reduce API calls
 */
class GoogleSheetsManager
{
    private $client;
    private $service;
    private $config;
    private $cacheDir;
    private $cacheTTL = 300; // 5 minutes

    public function __construct($config)
    {
        $this->config = $config;
        $this->cacheDir = __DIR__ . '/../storage/cache';
        $this->initializeClient();
    }

    /**
     * Initialize Google API client
     */
    private function initializeClient()
    {
        $this->client = new Client();
        $this->client->setAuthConfig($this->config['google']['service_account_json']);
        $this->client->addScope(Sheets::SPREADSHEETS_READONLY);

        $this->service = new Sheets($this->client);
    }

    /**
     * Get album configuration from Google Sheets
     * Format: Album Name | Password | Upload Start Date | Upload End Date | Notes
     */
    public function getAlbumConfig()
    {
        $cacheFile = $this->cacheDir . '/albums.json';

        // Check cache
        if (file_exists($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            if ($fileAge < $this->cacheTTL) {
                return json_decode(file_get_contents($cacheFile), true);
            }
        }

        // Fetch from Google Sheets
        $albums = $this->fetchFromSheets();

        // Save to cache
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, json_encode($albums, JSON_PRETTY_PRINT));

        return $albums;
    }

    /**
     * Fetch album data from Google Sheets
     */
    private function fetchFromSheets()
    {
        try {
            $spreadsheetId = $this->config['google']['sheets']['config_id'];
            $range = $this->config['google']['sheets']['config_range'];

            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                return [];
            }

            $albums = [];
            $headers = array_shift($values); // Get headers from first row

            foreach ($values as $row) {
                if (empty($row) || empty($row[0])) {
                    continue; // Skip empty rows
                }

                $album = [
                    'name' => $row[0] ?? '',
                    'password' => $row[1] ?? '',
                    'upload_start' => isset($row[2]) ? $this->parseDate($row[2]) : null,
                    'upload_end' => isset($row[3]) ? $this->parseDate($row[3]) : null,
                    'notes' => $row[4] ?? '',
                    'folder_id' => null, // Will be populated by AlbumManager
                ];

                if (!empty($album['name'])) {
                    $albums[] = $album;
                }
            }

            return $albums;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch album config: " . $e->getMessage());
        }
    }

    /**
     * Parse date string to DateTime object
     */
    private function parseDate($dateString)
    {
        try {
            return new \DateTime($dateString, new \DateTimeZone($this->config['app']['timezone']));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clear cache (useful when album config changes)
     */
    public function clearCache()
    {
        $cacheFile = $this->cacheDir . '/albums.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Get album by name
     */
    public function getAlbumByName($name)
    {
        $albums = $this->getAlbumConfig();
        foreach ($albums as $album) {
            if ($album['name'] === $name) {
                return $album;
            }
        }
        return null;
    }
}
