<?php

namespace PCAPhotoHub;

/**
 * Logger - Lightweight diagnostic logger for troubleshooting configuration
 * and Google API issues.
 *
 * Everything this class does -- writing to storage/logs/debug.log and
 * rendering the on-screen debug panel -- is a no-op until setEnabled(true)
 * is called (config.php does this only when APP_DEBUG=true). Messages are
 * buffered in memory first so a handful of early calls made before the
 * debug flag is known are not lost if debug turns out to be enabled.
 */
class Logger
{
    private static $enabled = false;
    private static $buffer = [];
    private static $logFile = null;

    /**
     * Enable or disable logging. Must be called once the APP_DEBUG value
     * is known. If enabling, any messages already buffered are flushed
     * to the log file immediately.
     */
    public static function setEnabled($enabled)
    {
        self::$enabled = (bool) $enabled;

        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../storage/logs/debug.log';
        }

        if (self::$enabled && !empty(self::$buffer)) {
            foreach (self::$buffer as $line) {
                self::writeLine($line);
            }
        }
    }

    public static function isEnabled()
    {
        return self::$enabled;
    }

    public static function debug($message, array $context = [])
    {
        self::record('DEBUG', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::record('INFO', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::record('WARNING', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::record('ERROR', $message, $context);
    }

    private static function record($level, $message, array $context)
    {
        $line = self::formatLine($level, $message, $context);
        self::$buffer[] = $line;

        if (self::$enabled) {
            self::writeLine($line);
        }
    }

    private static function formatLine($level, $message, array $context)
    {
        $line = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), $level, $message);
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        return $line;
    }

    private static function writeLine($line)
    {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../storage/logs/debug.log';
        }

        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents(self::$logFile, $line . PHP_EOL, FILE_APPEND);
    }

    /**
     * Buffered log entries for this request. Empty unless enabled, so
     * callers never need to check isEnabled() separately.
     */
    public static function getEntries()
    {
        return self::$enabled ? self::$buffer : [];
    }

    /**
     * Render a readable on-screen debug panel for this request.
     * Returns an empty string when disabled or nothing was logged.
     */
    public static function renderHtml()
    {
        if (!self::$enabled || empty(self::$buffer)) {
            return '';
        }

        $rows = '';
        foreach (self::$buffer as $line) {
            $rows .= htmlspecialchars($line) . "\n";
        }

        return '<div style="background:#1a1a1a;color:#7fffa0;font-family:Consolas,Monaco,monospace;'
            . 'font-size:0.82rem;line-height:1.5;padding:1.25rem;margin:2rem 0;border-radius:8px;'
            . 'max-height:450px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;">'
            . '<strong style="color:#fff;display:block;margin-bottom:0.75rem;">'
            . '🐛 Debug Log (visible because APP_DEBUG=true) &mdash; also written to storage/logs/debug.log'
            . '</strong>' . $rows . '</div>';
    }

    /**
     * Reset buffered entries. Mainly useful between requests in tests.
     */
    public static function reset()
    {
        self::$buffer = [];
    }
}
