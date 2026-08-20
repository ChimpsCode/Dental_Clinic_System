<?php
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        echo json_encode(['exists' => false]);
        exit;
    }

    $emailLower = strtolower($email);

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