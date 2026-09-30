<?php
header('Content-Type: application/json');
$inCookie = $_COOKIE[session_name()] ?? null;
$before = [
    'save_path' => ini_get('session.save_path'),
    'strict' => ini_get('session.use_strict_mode'),
    'sid_perm' => session_save_path(),
    'cookie' => $inCookie,
];
// emulate what session_bootstrap does
$saveDir = __DIR__ . '/storage/sessions';
ini_set('session.save_path', $saveDir);
ini_set('session.gc_maxlifetime', '86400');
ini_set('session.use_strict_mode', '1');
$ok = @session_start();
$err = error_get_last();
echo json_encode([
    'before' => $before,
    'file_expected' => $saveDir . '/sess_' . $inCookie,
    'file_exists' => $inCookie ? is_file($saveDir . '/sess_' . $inCookie) : null,
    'start_returned' => $ok,
    'session_id' => session_id(),
    'last_error' => $err['message'] ?? null,
], JSON_PRETTY_PRINT);
