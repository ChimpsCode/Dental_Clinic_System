<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

header('Content-Type: application/json');

if (!empty($GLOBALS['APP_SESSION_EXPIRED']) || !isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/patient_dashboard_status_helper.php';

    $patientId = $_SESSION['patient_id'] ?? null;
    if (empty($patientId)) {
        $userId = (int)$_SESSION['user_id'];
        $hasPatientUserId = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
        if ($hasPatientUserId) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $patientId = $stmt->fetchColumn() ?: null;
            if ($patientId) {
                $_SESSION['patient_id'] = $patientId;
            }
        }
    }

    $payload = getPatientDashboardStatusPayload($pdo, $patientId);
    ob_end_clean();
    echo json_encode(['success' => true] + $payload);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unable to load dashboard status']);
}
