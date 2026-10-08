<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $revoked = app_session_revoked_response();
    if ($revoked !== null) { echo json_encode($revoked); exit; }
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','staff','dentist'], true)) {
        http_response_code(403);
        throw new InvalidArgumentException('Please sign in to a clinic account.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new InvalidArgumentException('Invalid request method.');
    }
    $appointmentId = (int)($_GET['appointment_id'] ?? 0);
    if ($appointmentId <= 0) throw new InvalidArgumentException('Appointment ID is required.');
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/appointment_payment_details.php';
    echo json_encode(getAppointmentPaymentDetails($pdo, $appointmentId));
} catch (InvalidArgumentException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Appointment payment details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Unable to load payment details. Please try again.']);
}
