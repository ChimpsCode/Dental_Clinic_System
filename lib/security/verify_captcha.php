<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

$result = ['valid' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_code = strtoupper(trim($_POST['captcha_code'] ?? ''));
    $session_code = strtoupper(trim($_SESSION['captcha_code'] ?? ''));
    
    if (!empty($input_code) && !empty($session_code) && $input_code === $session_code) {
        $result['valid'] = true;
    }
}

echo json_encode($result);
