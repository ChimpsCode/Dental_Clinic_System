<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/sms_functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$patientId = (int)($_SESSION['patient_id'] ?? 0);

$action = $_POST['action'] ?? '';

function jsonError($msg) { echo json_encode(['success' => false, 'message' => $msg]); exit(); }

if ($action === 'send_otp') {
    $phone = trim($_POST['phone'] ?? '');
    if ($phone === '') jsonError('Phone number is required');
    // basic normalize: remove spaces
    $phoneNorm = preg_replace('/[^0-9+]/', '', $phone);
    if ($phoneNorm === '') jsonError('Invalid phone number');

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['phone_change_otp'] = $otp;
    $_SESSION['phone_change_otp_expires'] = time() + 300; // 5 minutes
    $_SESSION['phone_change_for'] = $phoneNorm;

    $message = "Your RF Dental verification code is: {$otp}";
    $sent = false;
    try {
        $sent = sendSMS($phoneNorm, $message);
    } catch (Exception $e) {
        // ignore send errors
        $sent = false;
    }

    // For safety, do not reveal the OTP in production. If SMS not configured, we still return success but indicate not sent.
    echo json_encode(['success' => true, 'sent' => $sent]);
    exit();
}

if ($action === 'verify_otp') {
    $phone = trim($_POST['phone'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    if ($phone === '' || $otp === '') jsonError('Phone and OTP are required');
    $phoneNorm = preg_replace('/[^0-9+]/', '', $phone);

    $stored = $_SESSION['phone_change_otp'] ?? null;
    $expires = (int)($_SESSION['phone_change_otp_expires'] ?? 0);
    $for = $_SESSION['phone_change_for'] ?? null;

    if (!$stored || time() > $expires || $for !== $phoneNorm) {
        // clear
        unset($_SESSION['phone_change_otp'], $_SESSION['phone_change_otp_expires'], $_SESSION['phone_change_for']);
        jsonError('OTP expired or invalid. Please request a new code.');
    }

    if (!hash_equals((string)$stored, (string)$otp)) {
        jsonError('Invalid OTP code.');
    }

    // OTP valid — update users and patients phone
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?');
        $stmt->execute([$phoneNorm, $userId]);

        if ($patientId > 0 && function_exists('inquiryColumnExists') && inquiryColumnExists($pdo, 'patients', 'phone')) {
            $stmt = $pdo->prepare('UPDATE patients SET phone = ? WHERE id = ?');
            $stmt->execute([$phoneNorm, $patientId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        jsonError('Failed to update phone number: ' . $e->getMessage());
    }

    // clear session OTP
    unset($_SESSION['phone_change_otp'], $_SESSION['phone_change_otp_expires'], $_SESSION['phone_change_for']);

    echo json_encode(['success' => true, 'message' => 'Phone updated']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
