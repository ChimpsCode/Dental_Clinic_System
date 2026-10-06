<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['dentist', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only dentists or admins can change treatment plans']);
    exit;
}
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST required');
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/treatment_plan_workflow.php';
    ensureTreatmentPlanWorkflowSchema($pdo);
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    changeTreatmentPlanSchedule($pdo, (int)($input['plan_id'] ?? 0), $input['action'] ?? '', (int)($input['session_number'] ?? 0), $input['date'] ?? '', $input['time'] ?? '');
    echo json_encode(['success' => true, 'message' => ($input['action'] ?? '') === 'reschedule_session' ? 'Session rescheduled. Calendar availability updated.' : 'Cancelled. The reserved time is now available.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
