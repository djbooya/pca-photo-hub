<?php

namespace PCAPhotoHub;

/**
 * MediaLink - Builds and verifies short-lived, signed URLs to a photo.
 *
 * Both the Facebook and Instagram publishing APIs fetch media themselves,
 * server-side, from a URL we hand them -- they will not accept raw bytes.
 * Our photos live in a private Drive folder, so publishing needs some
 * publicly reachable URL.
 *
 * Rather than flipping Drive files to "anyone with the link" (which would
 * outlive the publish and is easy to forget to undo), this mints a URL to
 * public/media.php carrying an expiry and an HMAC. The link works for a
 * few minutes, only for the exact file it names, and nothing in Drive
 * changes.
 */
class MediaLink
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function isConfigured()
    {
        return !empty($this->config['media_link']['secret']);
    }

    /**
     * Absolute URL to public/media.php for one Drive file.
     * Absolute because Meta fetches it from their own servers.
     */
    public function sign($fileId)
    {
        $secret = $this->config['media_link']['secret'] ?? '';
        if ($secret === '') {
            throw new \Exception('MEDIA_LINK_SECRET is not set in .env -- required before publishing to Facebook or Instagram.');
        }

        $ttl = (int) ($this->config['media_link']['ttl_minutes'] ?? 15);
        $expires = time() + ($ttl * 60);
        $signature = self::computeSignature($fileId, $expires, $secret);

        return $this->publicBaseUrl() . '/media.php?' . http_build_query([
            'f' => $fileId,
            'exp' => $expires,
            'sig' => $signature,
        ]);
    }

    /**
     * Validate an incoming request's signature and expiry.
     */
    public function verify($fileId, $expires, $signature)
    {
        $secret = $this->config['media_link']['secret'] ?? '';
        if ($secret === '' || !is_string($fileId) || $fileId === '') {
            return false;
        }

        if (!ctype_digit((string) $expires) || (int) $expires < time()) {
            return false;
        }

        $expected = self::computeSignature($fileId, (int) $expires, $secret);
        return is_string($signature) && hash_equals($expected, $signature);
    }

    private static function computeSignature($fileId, $expires, $secret)
    {
        return hash_hmac('sha256', $fileId . '|' . $expires, $secret);
    }

    /**
     * Public URL of the public/ directory. Uses PUBLIC_BASE_URL when set,
     * otherwise derives it from the current request (respecting
     * X-Forwarded-Proto, since this app runs behind a TLS-terminating CDN).
     */
    private function publicBaseUrl()
    {
        $configured = trim((string) ($this->config['media_link']['public_base_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $forwarded = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || $forwarded === 'https'
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // Directory of the running script, e.g. "/pca-photo-hub/public"
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $dir = rtrim(dirname($path), '/');

        return $scheme . '://' . $host . $dir;
    }
}
