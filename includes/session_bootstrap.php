<?php
/**
 * Centralized session bootstrap
 * ------------------------------
 * Include this INSTEAD of calling session_start() directly:
 *
 *   require_once __DIR__ . '/../../includes/session_bootstrap.php';
 *
 * What it fixes (why this file exists):
 *  1. GC race: previously only the panel layouts raised session.gc_maxlifetime,
 *     while ~40 other endpoints ran bare session_start() with php.ini defaults
 *     (1440s on XAMPP). Any such request could garbage-collect ACTIVE
 *     dentist/staff sessions mid-work. This file raises gc_maxlifetime for
 *     EVERY request that touches sessions.
 *  2. Shared-hosting safety: sessions live in a private folder inside this app
 *     (storage/sessions) so GC triggered by OTHER sites on shared hosts
 *     (e.g. InfinityFree) cannot delete our session files.
 *  3. Absolute-cookie logouts: the session cookie is re-sent on every request
 *     with a fresh expiry (sliding cookie), so users are never dropped
 *     mid-work just because N minutes passed since LOGIN. The configured
 *     timeout (settings -> session_timeout, in minutes) now behaves as a
 *     true inactivity window for staff/admin/dentist. Patients use their
 *     own Settings toggle instead (off = no idle logout).
 *
 * If the session was already expired-on-entry, $GLOBALS['APP_SESSION_EXPIRED']
 * is set to true and the session is reset; page wrappers use that flag to
 * redirect to the login screen with ?expired=1.
 */

// Already running (e.g. captcha_lib included after a page started the session)
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

$appRoot = dirname(__DIR__);

// ---- 1. Resolve the inactivity timeout (minutes) from clinic settings ----
$timeoutMinutes = 60; // fallback matches login.php default
try {
    if (!isset($pdo)) {
        require_once $appRoot . '/config/database.php';
    }
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null && (int)$val > 0) {
            $timeoutMinutes = (int)$val;
        }
    }
} catch (Throwable $e) {
    error_log('session_bootstrap: could not read session_timeout setting, using default. ' . $e->getMessage());
}
$lifetimeSeconds = max(60, $timeoutMinutes * 60);

// Expose timeout in seconds so layout-end JS timers can use it
$__sessionTimeoutSeconds = $lifetimeSeconds;

// ---- 2. Private session storage (survives foreign GC on shared hosts) ----
$saveDir = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($saveDir)) {
    @mkdir($saveDir, 0775, true);
}
if (is_dir($saveDir) && is_writable($saveDir)) {
    ini_set('session.save_path', $saveDir);
} else {
    error_log('session_bootstrap: private session dir not writable, using system save_path');
}

// ---- 3. Lifetime + GC hardening ----
// Physical cookie/GC stay long; logical idle expiry is applied after we know the user.
ini_set('session.gc_maxlifetime', (string)(86400));
ini_set('session.cookie_lifetime', '0');
ini_set('session.use_strict_mode', '1');

$secureCookie = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || ($_SERVER['SERVER_PORT'] ?? '') == 443
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// ---- 4. Prevent browser from caching authenticated pages ----
// Without this, pressing Back after logout restores the dashboard from BFcache.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
}

// ---- 5. Sliding inactivity window (server-side) ----
$GLOBALS['APP_SESSION_EXPIRED'] = false;
$now = time();
$inactivityEnabled = true;

if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $authStmt = $pdo->prepare('SELECT auth_version FROM users WHERE id = ? LIMIT 1');
        $authStmt->execute([(int)$_SESSION['user_id']]);
        $dbAuthVersion = $authStmt->fetchColumn();
        if ($dbAuthVersion !== false && (int)$dbAuthVersion !== (int)($_SESSION['auth_version'] ?? 0)) {
            $GLOBALS['APP_SESSION_EXPIRED'] = true;
            $_SESSION = [];
            session_destroy();
            session_start();
        }
    } catch (Throwable $e) {
        // auth_version is created on password save; skip until that column exists.
    }
}

if (empty($GLOBALS['APP_SESSION_EXPIRED']) && ($_SESSION['role'] ?? '') === 'user' && !empty($_SESSION['user_id'])) {
    try {
        require_once $appRoot . '/includes/patient_session_helper.php';
        if (isset($pdo) && $pdo instanceof PDO) {
            $patientSto = getPatientSessionSettings($pdo, (int)$_SESSION['user_id']);
            if (empty($patientSto['enabled'])) {
                $inactivityEnabled = false;
            } else {
                $timeoutMinutes = max(1, (int)$patientSto['timeout_minutes']);
                $lifetimeSeconds = max(60, $timeoutMinutes * 60);
                $__sessionTimeoutSeconds = $lifetimeSeconds;
            }
        }
    } catch (Throwable $e) {
        error_log('session_bootstrap: could not read patient session settings. ' . $e->getMessage());
    }
}

if ($inactivityEnabled) {
    // Use last_activity so a leftover clinic-timeout stamp cannot expire a
    // patient who opted into a longer (or different) personal window.
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && $now > ($lastActivity + $lifetimeSeconds)) {
        $GLOBALS['APP_SESSION_EXPIRED'] = true;
        $_SESSION = [];
        session_destroy();
        session_start(); // fresh anonymous session so pages/AJAX can safely write $_SESSION
        $inactivityEnabled = true; // anonymous follow-up cookie uses clinic timeout
    } else {
        $_SESSION['last_activity'] = $now;
        $_SESSION['session_expires_at'] = $now + $lifetimeSeconds;
    }
} else {
    $_SESSION['last_activity'] = $now;
    unset($_SESSION['session_expires_at']);
}

// ---- 6. Sliding cookie (client-side) ----
// Re-send with a fresh expiry on EVERY request so the browser never drops the
// cookie while the user is active. Patients who turned timeout off get a
// session cookie (until Logout or closing the browser).
if (!headers_sent() && session_id() !== '') {
    $cookieOptions = [
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    $cookieOptions['expires'] = $inactivityEnabled ? ($now + $lifetimeSeconds) : 0;
    setcookie(session_name(), session_id(), $cookieOptions);
}
