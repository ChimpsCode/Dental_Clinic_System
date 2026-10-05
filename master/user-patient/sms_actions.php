<?php
/**
 * Patient SMS self-service endpoint.
 *
 * Lets a patient confirm that texts actually reach their number, which is the
 * fastest way to tell "SMS is off" apart from "the gateway is broken".
 */
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/sms_functions.php';
require_once __DIR__ . '/../../includes/patient_sms_test_limit.php';

$revoked = app_session_revoked_response();
if ($revoked !== null || !isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode($revoked ?? ['success' => false, 'message' => 'Authentication required']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$patientId = (int)($_SESSION['patient_id'] ?? 0);

if ($action !== 'send_test_sms') {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

if (!smsIsConfigured()) {
    echo json_encode([
        'success' => false,
        'message' => 'SMS is not available right now. Please contact the clinic.',
    ]);
    exit();
}

$phone = patientSmsPhone($pdo, $patientId);
if ($phone === '') {
    echo json_encode([
        'success' => false,
        'message' => 'No phone number is on file. Add one under My Profile first.',
    ]);
    exit();
}

// A test text proves delivery, so it ignores the notification preference.
$quota = sendPatientSmsTestLimited($userId, static fn() => sendPatientSmsTracked(
    $pdo,
    $patientId,
    'This is a test message from RF Dental Clinic. If you received it, SMS notifications are working on your number. - RF Dental Clinic',
    'test',
    null,
    true,
    false,
    $phone
));

if (!$quota['attempted']) {
    http_response_code($quota['reason'] === 'unavailable' ? 503 : 429);
    echo json_encode(['success' => false, 'message' => $quota['message'], 'quota' => $quota]);
    exit();
}

if (!$quota['sent']) {
    logSms($pdo, [
        'phone' => $phone,
        'message' => 'patient-initiated SMS test',
        'category' => 'test',
        'status' => 'failed',
        'error_message' => 'Test send failed',
        'patient_id' => $patientId,
        'requested_by' => $userId,
    ]);
    echo json_encode([
        'success' => false,
        'message' => 'The test message could not be sent. Please contact the clinic.',
        'quota' => $quota,
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Test SMS sent to ' . $phone . '. It should arrive within a minute.',
    'quota' => $quota,
]);
