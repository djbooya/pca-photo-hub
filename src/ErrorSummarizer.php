<?php

namespace PCAPhotoHub;

/**
 * ErrorSummarizer - Turns raw Google API error output into something a
 * human can actually read.
 *
 * When authentication is broken, Google sometimes responds with a full
 * HTML page (e.g. the docs.google.com "Sorry, unable to open the file at
 * present" page) instead of a clean JSON API error. Dumping that raw HTML
 * into an on-screen error message is unreadable. This class detects that
 * case and returns a short, actionable summary instead. The full raw
 * response is still written to storage/logs/debug.log (via Logger) when
 * APP_DEBUG=true, so nothing is lost -- it's just not thrown on screen.
 */
class ErrorSummarizer
{
    public static function summarize($rawMessage)
    {
        $trimmed = ltrim($rawMessage);

        if (self::looksLikeHtml($trimmed)) {
            return 'Google returned a web page instead of API data (this usually means the Sheets/Drive API '
                . 'is not enabled, the ID in .env is wrong, or the file/sheet is not shared with the service '
                . "account's email). Set APP_DEBUG=true in .env and reload to see the full response in "
                . 'storage/logs/debug.log.';
        }

        // Google API client errors are often JSON already -- pull out the
        // 'message' field if present so we show one clean sentence instead
        // of the whole nested error object.
        $decoded = json_decode($rawMessage, true);
        if (is_array($decoded)) {
            $message = $decoded['error']['message']
                ?? $decoded['error']['errors'][0]['message']
                ?? null;

            if ($message) {
                $reason = $decoded['error']['errors'][0]['reason'] ?? null;
                return $reason ? "{$message} (reason: {$reason})" : $message;
            }
        }

        // Fall back to the raw message, but keep it to a readable length.
        return strlen($rawMessage) > 300 ? substr($rawMessage, 0, 300) . '...' : $rawMessage;
    }

    private static function looksLikeHtml($text)
    {
        return stripos($text, '<!doctype html') === 0
            || stripos($text, '<html') === 0
            || (strpos($text, '<') === 0 && stripos($text, '</html>') !== false);
    }
}
