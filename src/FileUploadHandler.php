<?php

namespace PCAPhotoHub;

/**
 * FileUploadHandler - Validates and processes file uploads
 */
class FileUploadHandler
{
    private $config;
    private $errors = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Validate uploaded file
     */
    public function validateUpload($file)
    {
        $this->errors = [];

        if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['size'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }

        // Check file size
        if ($file['size'] > $this->config['upload']['max_file_size_bytes']) {
            $maxMB = $this->config['upload']['max_file_size_mb'];
            $this->errors[] = "File exceeds maximum size of {$maxMB} MB";
            return false;
        }

        // Check MIME type
        $mimeType = $this->getMimeType($file['tmp_name']);
        if (!in_array($mimeType, $this->config['upload']['allowed_mime_types'])) {
            $this->errors[] = "File type '{$mimeType}' is not allowed";
            return false;
        }

        // Check file exists
        if (!file_exists($file['tmp_name'])) {
            $this->errors[] = 'Uploaded file not found';
            return false;
        }

        return true;
    }

    /**
     * Get MIME type of uploaded file
     */
    private function getMimeType($filePath)
    {
        // Try finfo_file first (most reliable)
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType ?: 'application/octet-stream';
        }

        // Fallback to mime_content_type
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        // Final fallback: check file extension
        return $this->getMimeTypeByExtension($filePath);
    }

    /**
     * Get MIME type by file extension
     */
    private function getMimeTypeByExtension($filePath)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Generate safe filename
     */
    public function generateSafeFileName($originalFileName)
    {
        // Remove path information
        $fileName = basename($originalFileName);

        // Keep only alphanumeric, hyphens, underscores, and dots
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

        // Remove multiple dots
        $fileName = preg_replace('/\.+/', '.', $fileName);

        // Prepend timestamp to avoid collisions
        $pathInfo = pathinfo($fileName);
        $timestamp = date('Y-m-d-His');
        $fileName = $timestamp . '_' . $pathInfo['filename'] . '.' . ($pathInfo['extension'] ?? 'jpg');

        return $fileName;
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if file has duplicate
     * Compares file hash with existing files in Drive
     */
    public function getFileHash($filePath)
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Get human-readable file size
     */
    public static function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
