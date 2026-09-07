<?php

namespace PCAPhotoHub;

/**
 * AdminAuth - Per-album password gate for the admin area.
 *
 * Each album row carries its own admin password in column F of the config
 * sheet, and signing in with a password grants admin rights to exactly the
 * albums whose password matches it -- not to the whole site.
 *
 * Password reuse is a supported, deliberate pattern: putting the same
 * value on several rows gives one password admin access to that set of
 * albums, so an event lead can be given one password covering their
 * events while a different lead is scoped to theirs.
 *
 * Rather than recording which albums were unlocked at sign-in, the session
 * stores only a hash of the submitted password and re-derives the album
 * set on every request. That means changing or clearing a password in the
 * sheet revokes access as soon as the sheet cache refreshes, instead of
 * lingering until the session expires.
 */
class AdminAuth
{
    const HASH_KEY = 'pca_admin_pw_hash';
    const LAST_ACTIVITY_KEY = 'pca_admin_last_activity';
    const FAILED_KEY = 'pca_admin_failed_attempts';
    const LOCKOUT_KEY = 'pca_admin_lockout_until';
    const CSRF_KEY = 'pca_admin_csrf';

    const MAX_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 900; // 15 minutes

    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * True when at least one album has an admin password set. With none set
     * anywhere, the admin area stays shut rather than open.
     */
    public function isConfiguredForAny(array $albums)
    {
        foreach ($albums as $album) {
            if (self::albumPassword($album) !== '') {
                return true;
            }
        }
        return false;
    }

    public function isLockedOut()
    {
        return (int) ($_SESSION[self::LOCKOUT_KEY] ?? 0) > time();
    }

    public function lockoutSecondsRemaining()
    {
        return max(0, (int) ($_SESSION[self::LOCKOUT_KEY] ?? 0) - time());
    }

    /**
     * Verify a submitted password against every album. Succeeds if it
     * unlocks at least one.
     */
    public function attemptLogin($submitted, array $albums)
    {
        if ($this->isLockedOut()) {
            Logger::warning('AdminAuth: login attempted while locked out');
            return false;
        }

        $submitted = is_string($submitted) ? trim($submitted) : '';

        if ($submitted !== '') {
            $matched = $this->albumsMatching($submitted, $albums);

            if (!empty($matched)) {
                // Prevent session fixation. Guarded because regenerating
                // after output has started emits a warning and cannot work.
                if (!headers_sent()) {
                    session_regenerate_id(true);
                }

                $_SESSION[self::HASH_KEY] = self::hashPassword($submitted);
                $_SESSION[self::LAST_ACTIVITY_KEY] = time();
                unset($_SESSION[self::FAILED_KEY], $_SESSION[self::LOCKOUT_KEY]);

                Logger::info('AdminAuth: admin login succeeded', ['albums_unlocked' => count($matched)]);
                return true;
            }
        }

        $failed = ((int) ($_SESSION[self::FAILED_KEY] ?? 0)) + 1;
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
     * Whether this session holds an admin password and has not gone idle.
     * Says nothing about which albums it can reach -- see
     * authorizedAlbums() and canAdminAlbum().
     */
    public function isLoggedIn()
    {
        if (empty($_SESSION[self::HASH_KEY])) {
            return false;
        }

        $timeout = ((int) ($this->config['admin']['session_timeout_minutes'] ?? 60)) * 60;
        $last = (int) ($_SESSION[self::LAST_ACTIVITY_KEY] ?? 0);

        if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout) {
            Logger::info('AdminAuth: admin session timed out');
            $this->logout();
            return false;
        }

        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
        return true;
    }

    /**
     * The albums this session may administer. Recomputed per request, so a
     * password changed in the sheet takes effect without waiting for the
     * session to expire.
     */
    public function authorizedAlbums(array $albums)
    {
        if (!$this->isLoggedIn()) {
            return [];
        }

        $hash = (string) $_SESSION[self::HASH_KEY];
        $authorized = [];

        foreach ($albums as $album) {
            $password = self::albumPassword($album);
            if ($password === '') {
                continue; // No admin password on this album: never reachable.
            }
            if (hash_equals($hash, self::hashPassword($password))) {
                $authorized[] = $album;
            }
        }

        return $authorized;
    }

    /**
     * Whether this session may administer one specific album.
     */
    public function canAdminAlbum($album)
    {
        if (!$this->isLoggedIn() || !is_array($album)) {
            return false;
        }

        $password = self::albumPassword($album);
        if ($password === '') {
            return false;
        }

        return hash_equals((string) $_SESSION[self::HASH_KEY], self::hashPassword($password));
    }

    public function logout()
    {
        unset(
            $_SESSION[self::HASH_KEY],
            $_SESSION[self::LAST_ACTIVITY_KEY],
            $_SESSION[self::CSRF_KEY]
        );
    }

    /**
     * CSRF token for admin actions. Deleting is destructive and publishing
     * is irreversible, so neither may be triggerable by a cross-site post.
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

    /**
     * Albums unlocked by a given plaintext password.
     */
    private function albumsMatching($submitted, array $albums)
    {
        $matched = [];

        foreach ($albums as $album) {
            $password = self::albumPassword($album);
            if ($password !== '' && hash_equals($password, $submitted)) {
                $matched[] = $album;
            }
        }

        return $matched;
    }

    private static function albumPassword($album)
    {
        if (!is_array($album) || !isset($album['admin_password'])) {
            return '';
        }
        return trim((string) $album['admin_password']);
    }

    private static function hashPassword($password)
    {
        return hash('sha256', $password);
    }
}
