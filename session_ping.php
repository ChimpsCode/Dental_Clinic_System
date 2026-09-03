<?php
/**
 * Lightweight keepalive used by the inactivity modal ("Stay logged in")
 * and throttled activity pings. Sliding the session here keeps the
 * server-side window in sync with real use of the page.
 */
require_once __DIR__ . '/includes/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!empty($GLOBALS['APP_SESSION_EXPIRED']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
    exit;
}

echo json_encode(['ok' => true]);
