<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("SELECT id, created_at FROM patients ORDER BY created_at DESC LIMIT 1");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'latest_id' => (int)($latest['id'] ?? 0),
        'created_at' => $latest['created_at'] ?? null
    ]);
} catch (Exception $e) {
    error_log("Dentist Patients Poll Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
