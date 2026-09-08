<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/input_sanitize.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $emailResult = sanitize_email($_POST['email'] ?? '', false);
    if (!$emailResult['ok']) {
        echo json_encode(['exists' => false, 'error' => $emailResult['error']]);
        exit;
    }

    $email = $emailResult['value'];

    if ($email === '') {
        echo json_encode(['exists' => false]);
        exit;
    }

    $emailLower = $email;

    $stmt = $pdo->prepare(
           "SELECT id, email, username, is_archived FROM users
            WHERE (is_archived = 0 OR is_archived IS NULL) AND (LOWER(email) = :email OR LOWER(username) = :username)"
        );
        $stmt->execute(['email' => $emailLower, 'username' => $emailLower]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'exists' => true,
            'email' => $user['email'],
            'is_archived' => (bool)($user['is_archived'] ?? 0)
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }

} catch (Exception $e) {
    error_log('User check error: ' . $e->getMessage());
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>