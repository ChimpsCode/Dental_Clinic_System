<?php
/**
 * Lightweight keepalive used by the inactivity modal ("Stay logged in")
 * and throttled activity pings. Sliding the session here keeps the
 * server-side window in sync with real use of the page.
 */
require_once __DIR__ . '/includes/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$revoked = app_session_revoked_response();
if ($revoked !== null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true] + $revoked);
    exit;
}

session_write_close();
echo json_encode(['ok' => true]);
