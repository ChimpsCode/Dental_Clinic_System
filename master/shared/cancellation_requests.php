<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $revoked = app_session_revoked_response();
    if ($revoked !== null) { echo json_encode($revoked); exit; }
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','staff','dentist'], true)) {
        http_response_code(403);
        throw new InvalidArgumentException('Please sign in to a clinic account.');
    }
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/appointment_cancellation_requests.php';
    ensureCancellationRequestSchema($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        $where = $id ? 'cr.id=?' : "cr.status='pending'";
        $stmt = $pdo->prepare("SELECT cr.*, a.appointment_date, a.appointment_time, a.status AS appointment_status,
            p.first_name, p.last_name, p.phone, s.name AS service_name
            FROM appointment_cancellation_requests cr
            JOIN appointments a ON a.id=cr.appointment_id
            LEFT JOIN patients p ON p.id=cr.patient_id
            LEFT JOIN services s ON s.id=a.service_id
            WHERE $where ORDER BY cr.requested_at ASC");
        $stmt->execute($id ? [$id] : []);
        echo json_encode(['success'=>true, 'requests'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Invalid request method.');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!is_string($input['csrf'] ?? null) || empty($_SESSION['cancellation_review_csrf']) || !hash_equals($_SESSION['cancellation_review_csrf'], $input['csrf'])) {
        http_response_code(403);
        throw new InvalidArgumentException('Please reload the page and try again.');
    }
    require_once __DIR__ . '/../../includes/treatment_plan_workflow.php';
    ensureTreatmentPlanWorkflowSchema($pdo);
    $result = reviewAppointmentCancellationRequest($pdo, (int)($input['request_id'] ?? 0), (int)$_SESSION['user_id'], (string)($input['decision'] ?? ''), trim((string)($input['note'] ?? '')));
    try {
        require_once __DIR__ . '/../../includes/notification_functions.php';
        $user = getUserIdForPatient($pdo, (int)$result['patient_id']);
        if ($user) createNotification($pdo, (int)$user, 'appointment', 'Cancellation Request Reviewed', $result['message'] . (!empty($input['note']) ? ' Clinic note: ' . trim($input['note']) : ''), 'appointments.php?tab=upcoming');
        require_once __DIR__ . '/../../includes/audit_helper.php';
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? '', $_SESSION['role'], 'update', 'appointments', $result['message'], $result['appointment_id'], 'appointments');
    } catch (Throwable $e) { error_log('Cancellation review notification: ' . $e->getMessage()); }
    echo json_encode($result);
} catch (InvalidArgumentException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Cancellation requests: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to process the cancellation request. Please try again.']);
}
