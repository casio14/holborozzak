<?php
declare(strict_types=1);

/**
 * Admin hitelesítés (session-alapú). A védett oldalak ezt töltik be, majd
 * require_admin()-t hívnak. A hitelesítő adatok a config.php 'admin' szekciójából
 * jönnek (felhasználónév + bcrypt jelszó-hash).
 */

require_once __DIR__ . '/../db.php';

/** A config.php 'admin' szekciója (user + pass_hash). */
function admin_config(): array
{
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) {
        $c = require $cfg;
        return is_array($c['admin'] ?? null) ? $c['admin'] : [];
    }
    return [];
}

/** Biztonságos session-indítás (httponly, SameSite=Lax, https-en secure). */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_name('hb_admin');
    session_start();
}

/** Be van-e lépve az admin? */
function admin_is_logged_in(): bool
{
    admin_session_start();
    return !empty($_SESSION['admin_ok']);
}

/** Védett oldal kapuja: ha nincs belépve, irány a login. Egyébként noindex fejléc. */
function require_admin(): void
{
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    header('X-Robots-Tag: noindex, nofollow');

    // A saját (admin) forgalom kizárása a mérésből: tartós „ne mérj engem" süti,
    // hogy a fejlesztés/tesztelés közbeni böngészés ne hígítsa a statisztikát.
    if ((string) ($_COOKIE['hb_notrack'] ?? '') !== '1' && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('hb_notrack', '1', [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
        $_COOKIE['hb_notrack'] = '1'; // az aktuális kérésben is érvényes legyen
    }
}

/** CSRF token (a módosító műveletekhez). */
function admin_csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** CSRF ellenőrzés. */
function admin_csrf_check(?string $t): bool
{
    admin_session_start();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}
