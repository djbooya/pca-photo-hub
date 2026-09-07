<?php

namespace PCAPhotoHub;

/**
 * AdminAuth - Password gate for the admin area.
 *
 * The password comes from column F ("Admin Password") of the config
 * spreadsheet, so club officers can rotate it without touching the server.
 * Admin state lives in the PHP session only -- never in a cookie value --
 * so it cannot be forged client-side.
 */
class AdminAuth
{
    const SESSION_KEY = 'pca_admin_authenticated';
    const LAST_ACTIVITY_KEY = 'pca_admin_last_activity';
    const FAILED_KEY = 'pca_admin_failed_attempts';
    const LOCKOUT_KEY = 'pca_admin_lockout_until';
    const CSRF_KEY = 'pca_admin_csrf';

    const MAX_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 900; // 15 minutes

    private $config;
    private $adminPassword;

    /**
     * @param array       $config
     * @param string|null $adminPassword Value from the config sheet, or null
     *                                   if the sheet has no Admin Password column.
     */
    public function __construct($config, $adminPassword)
    {
        $this->config = $config;
        $this->adminPassword = is_string($adminPassword) ? trim($adminPassword) : '';
    }

    /**
     * Whether an admin password has actually been configured. With no
     * password set the admin area refuses to open at all, rather than
     * letting anyone in.
     */
    public function isConfigured()
    {
        return $this->adminPassword !== '';
    }

    public function isLockedOut()
    {
        $until = $_SESSION[self::LOCKOUT_KEY] ?? 0;
        return $until > time();
    }

    public function lockoutSecondsRemaining()
    {
        $until = $_SESSION[self::LOCKOUT_KEY] ?? 0;
        return max(0, $until - time());
    }

    /**
     * Verify a submitted password and start an admin session on success.
     */
    public function attemptLogin($submitted)
    {
        if (!$this->isConfigured()) {
            Logger::warning('AdminAuth: login attempted but no Admin Password is set in the config sheet');
            return false;
        }

        if ($this->isLockedOut()) {
            Logger::warning('AdminAuth: login attempted while locked out');
            return false;
        }

        // hash_equals avoids leaking the password through timing differences.
        if (is_string($submitted) && hash_equals($this->adminPassword, trim($submitted))) {
            // Prevent session fixation. Guarded because regenerating after
            // output has started emits a warning and cannot succeed.
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION[self::SESSION_KEY] = true;
            $_SESSION[self::LAST_ACTIVITY_KEY] = time();
            unset($_SESSION[self::FAILED_KEY], $_SESSION[self::LOCKOUT_KEY]);
            Logger::info('AdminAuth: admin login succeeded');
            return true;
        }

        $failed = ($_SESSION[self::FAILED_KEY] ?? 0) + 1;
        $_SESSION[self::FAILED_KEY] = $failed;

        if ($failed >= self::MAX_ATTEMPTS) {
            $_SESSION[self::LOCKOUT_KEY] = time() + self::LOCKOUT_SECONDS;
            $_SESSION[self::FAILED_KEY] = 0;
            Logger::warning('AdminAuth: too many failed logins, locking out', ['seconds' => self::LOCKOUT_SECONDS]);
        } else {
            Logger::warning('AdminAuth: failed login', ['attempt' => $failed]);
        }

        return false;
    }

    /**
     * True if this session is an authenticated admin and has not gone idle
     * past the configured timeout.
     */
    public function isLoggedIn()
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        $timeout = ((int) ($this->config['admin']['session_timeout_minutes'] ?? 60)) * 60;
        $last = (int) ($_SESSION[self::LAST_ACTIVITY_KEY] ?? 0);

        if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout) {
            $this->logout();
            return false;
        }

        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
        return true;
    }

    public function logout()
    {
        unset(
            $_SESSION[self::SESSION_KEY],
            $_SESSION[self::LAST_ACTIVITY_KEY],
            $_SESSION[self::CSRF_KEY]
        );
    }

    /**
     * CSRF token for admin actions. Delete and publish are destructive and
     * irreversible respectively, so they must not be triggerable by a
     * cross-site form post.
     */
    public function getCsrfToken()
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public function verifyCsrfToken($token)
    {
        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
