<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'admin'], true)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/queue_management_helper.php';
require_once '../../includes/treatment_plan_workflow.php';
ensureTreatmentPlanWorkflowSchema($pdo);

function tableExistsCheckIn(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function columnExistsCheckIn(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function checkInTreatmentType(PDO $pdo, array $appointment): string
{
    if (!empty($appointment['treatment_plan_id'])) {
        $stmt = $pdo->prepare('SELECT sessions_data FROM treatment_plans WHERE id = ?');
        $stmt->execute([$appointment['treatment_plan_id']]);
        $sessions = json_decode($stmt->fetchColumn() ?: '[]', true) ?: [];
        $treatment = $sessions[(int)$appointment['treatment_plan_session'] - 1]['treatment'] ?? '';
        if ($treatment !== '') return $treatment;
    }
    $treatmentType = trim((string)($appointment['treatment'] ?? ''));
    if ($treatmentType === '' && !empty($appointment['service_id'])) {
        $svc = $pdo->prepare('SELECT name FROM services WHERE id = ?');
        $svc->execute([(int)$appointment['service_id']]);
        $treatmentType = trim((string)$svc->fetchColumn());
    }
    return $treatmentType !== '' ? $treatmentType : 'General Checkup';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $appointmentId = (int)($input['appointment_id'] ?? 0);
    if ($appointmentId <= 0) {
        throw new Exception('Appointment ID is required.');
    }

    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.middle_name, p.last_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found.');
    }

    if (empty($appointment['patient_id'])) {
        throw new Exception('Appointment must be linked to a patient before check-in.');
    }

    $appointmentStatus = strtolower(trim((string)($appointment['status'] ?? 'scheduled')));
    if ($appointmentStatus !== 'scheduled' && $appointmentStatus !== 'arrived' && $appointmentStatus !== 'in queue' && $appointmentStatus !== '') {
        throw new Exception('Only scheduled appointments can be checked in.');
    }

    $appointmentDate = (string)($appointment['appointment_date'] ?? '');
    $today = date('Y-m-d');
    if ($appointmentDate !== $today) {
        throw new Exception('Check-in is only available for today\'s appointments.');
    }

    $existingQueue = fetchQueueEntryByAppointment($pdo, $appointmentId, $today);
    if ($existingQueue) {
        echo json_encode([
            'success' => true,
            'already_checked_in' => true,
            'message' => 'Patient already checked in as ' . ($existingQueue['queue_label'] ?? buildQueueLabel((int)($existingQueue['queue_number'] ?? 0))) . '.',
            'queue' => $existingQueue
        ]);
        exit();
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT status FROM appointments WHERE id = ? FOR UPDATE');
    $lock->execute([$appointmentId]);
    if (!in_array(strtolower((string)$lock->fetchColumn()), ['scheduled','arrived','in queue',''], true)) {
        throw new Exception('The appointment is no longer available for check-in.');
    }

    $queueEntry = createQueueEntry($pdo, [
        'patient_id' => (int)$appointment['patient_id'],
        'appointment_id' => $appointmentId,
        'queue_date' => $today,
        'treatment_type' => checkInTreatmentType($pdo, $appointment),
        'notes' => 'Checked in from scheduled appointment.',
        'queue_time' => date('H:i:s'),
        'status' => 'waiting'
    ]);

    $updateStmt = $pdo->prepare("UPDATE appointments SET status = 'In Queue' WHERE id = ?");
    $updateStmt->execute([$appointmentId]);

    syncTreatmentPlanForAppointment($pdo, $appointmentId);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Patient checked in successfully as ' . ($queueEntry['queue_label'] ?? buildQueueLabel((int)$queueEntry['queue_number'])) . '.',
        'queue' => $queueEntry
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Staff check-in error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
