<?php
/**
 * Notification System Diagnostic Test
 * Access via browser: master/shared/test_notification.php?patient_id=40
 * Tests: getUserIdForPatient, createNotification, and notification retrieval
 */
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/notification_functions.php';
ensureNotificationSchema($pdo);

header('Content-Type: application/json');

$patientId = (int)($_GET['patient_id'] ?? 0);
$results = ['steps' => [], 'success' => false];

if (!$patientId) {
    $results['error'] = 'Pass ?patient_id=N to test';
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

$userId = getUserIdForPatient($pdo, $patientId);
$results['steps'][] = [
    'step' => 'getUserIdForPatient',
    'patient_id' => $patientId,
    'user_id' => $userId,
    'ok' => $userId !== null,
];

if (!$userId) {
    $results['error'] = 'getUserIdForPatient returned null — cannot create notification';
    $results['debug'] = [];

    try {
        $stmt = $pdo->prepare("SELECT id, patient_id, email, phone, role FROM users WHERE id IN (SELECT id FROM users WHERE LOWER(role) = 'patient' OR LOWER(role) = 'Patient') LIMIT 10");
        $stmt->execute();
        $results['debug']['patient_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $results['debug']['patient_users_error'] = $e->getMessage();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, phone FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $results['debug']['patient_record'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $results['debug']['patient_record_error'] = $e->getMessage();
    }

    try {
        $hasUserIdCol = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
        $results['debug']['patients_has_user_id_col'] = $hasUserIdCol;
    } catch (Exception $e) {
        $results['debug']['show_columns_error'] = $e->getMessage();
    }

    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

$testTitle = 'Test Notification';
$testMessage = 'This is a test notification sent at ' . date('Y-m-d H:i:s') . ' for patient #' . $patientId;
$notifId = createNotification($pdo, $userId, 'system', $testTitle, $testMessage, '../user-patient/patient-dashboard.php');
$results['steps'][] = [
    'step' => 'createNotification',
    'user_id' => $userId,
    'notification_id' => $notifId,
    'ok' => $notifId !== false,
];

if ($notifId) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
    $stmt->execute([$notifId]);
    $created = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['steps'][] = [
        'step' => 'verify_notification_in_db',
        'notification' => $created,
        'ok' => $created !== false,
    ];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalNotifs = (int)$stmt->fetchColumn();
    $results['steps'][] = [
        'step' => 'total_notifications_for_user',
        'count' => $totalNotifs,
        'ok' => $totalNotifs > 0,
    ];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unread = (int)$stmt->fetchColumn();
    $results['steps'][] = [
        'step' => 'unread_count',
        'count' => $unread,
        'ok' => $unread > 0,
    ];
}

$results['success'] = $notifId !== false;
echo json_encode($results, JSON_PRETTY_PRINT);
