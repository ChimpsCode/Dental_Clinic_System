<?php
require_once __DIR__ . '/session_bootstrap.php';
$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || $role !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}
