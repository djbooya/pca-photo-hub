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
        $jsonPath = $this->config['google']['service_account_json'];
        Logger::debug('GoogleSheetsManager: validating service account JSON', ['path' => $jsonPath]);
        $email = $this->validateServiceAccountJson($jsonPath);

        Logger::debug('GoogleSheetsManager: service account JSON is valid', ['client_email' => $email]);

        $this->client = new Client();
        $this->client->setAuthConfig($jsonPath);
        $this->client->addScope(Sheets::SPREADSHEETS_READONLY);

        $this->service = new Sheets($this->client);
        Logger::debug('GoogleSheetsManager: Google Sheets client initialized');
    }

    /**
     * Validate the service account JSON file exists, is readable, and is valid
     * before handing it to the Google client. Without this check, a bad path
     * fails silently deep inside the client library and manifests as a
     * confusing "array offset on false" warning followed by broken auth.
     *
     * Returns the service account's client_email on success (useful for
     * logging -- it's the address that must be shared on the Sheet/Drive).
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
                Logger::debug('GoogleSheetsManager: returning cached album config', ['age_seconds' => $fileAge]);
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
        $spreadsheetId = $this->config['google']['sheets']['config_id'];
        $range = $this->config['google']['sheets']['config_range'];

        Logger::debug('GoogleSheetsManager: fetching from Google Sheets API', [
            'spreadsheet_id' => $spreadsheetId,
            'range' => $range,
        ]);

        try {
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            Logger::debug('GoogleSheetsManager: fetch succeeded', ['row_count' => count($values ?? [])]);

            if (empty($values)) {
                return [];
            }

            $albums = [];
            $headers = array_shift($values); // Get headers from first row

            foreach ($values as $row) {
                if (empty($row) || empty($row[0])) {
                    continue; // Skip empty rows
                }

                // Dates are kept as raw strings here, not DateTime objects.
                // This array gets json_encode()'d into storage/cache/albums.json;
                // a DateTime object round-tripped through JSON comes back as a
                // plain array (its internal {date, timezone_type, timezone}
                // representation), not a DateTime, which fatals as soon as
                // AlbumManager calls ->format() or ->diff() on it after a cache
                // hit. AlbumManager parses these strings into DateTime objects
                // itself, in memory, on every request.
                $album = [
                    'name' => $row[0] ?? '',
                    'password' => $row[1] ?? '',
                    'upload_start' => (isset($row[2]) && trim($row[2]) !== '') ? trim($row[2]) : null,
                    'upload_end' => (isset($row[3]) && trim($row[3]) !== '') ? trim($row[3]) : null,
                    'notes' => $row[4] ?? '',
                    'folder_id' => null, // Will be populated by AlbumManager
                ];

                if (!empty($album['name'])) {
                    $albums[] = $album;
                }
            }

            return $albums;
        } catch (\Throwable $e) {
            Logger::error('GoogleSheetsManager: fetch failed', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'raw_error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw new \Exception('Failed to fetch album config: ' . ErrorSummarizer::summarize($e->getMessage()));
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
