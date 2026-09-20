<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
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
    require_once '../../config/database.php';
    require_once __DIR__ . '/../../includes/patient_list_overflow_helper.php';
    ensurePatientLogColumn($pdo);
    $stmt = $pdo->query("SELECT id, created_at FROM patients WHERE COALESCE(is_archived, 0) = 0 AND COALESCE(in_patient_log, 0) = 0 ORDER BY id DESC LIMIT 1");
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
