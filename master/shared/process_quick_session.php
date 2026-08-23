<?php
/**
 * Process Quick Session - Add returning patient to queue
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_bootstrap.php';require_once '../../config/database.php';
require_once '../../includes/queue_management_helper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_id = $_POST['patient_id'] ?? null;
    $treatment_type = $_POST['treatment_type'] ?? 'General Checkup';
    $service_id = $_POST['service_id'] ?? null;
    $teeth_numbers = $_POST['teeth_numbers'] ?? '';
    $complaint = trim($_POST['complaint'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $priority = $_POST['priority'] ?? 5;

    if (empty($patient_id)) {
        throw new Exception('Patient ID is required');
    }

    if (empty($treatment_type)) {
        throw new Exception('Treatment type is required');
    }

    // Complete schema setup before opening the transaction; ALTER TABLE may
    // implicitly commit on MySQL.
    ensureQueueManagementSchema($pdo);

    $duplicateStmt = $pdo->prepare("SELECT id, queue_number, queue_label, patient_id FROM queue WHERE patient_id = ? AND status IN ('waiting', 'in_procedure', 'on_hold') AND COALESCE(is_archived, 0) = 0 AND (queue_date = CURDATE() OR (queue_date IS NULL AND DATE(created_at) = CURDATE())) ORDER BY id DESC LIMIT 1");
    $duplicateStmt->execute([(int)$patient_id]);
    $existingQueue = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingQueue) {
        echo json_encode([
            'success' => true,
            'already_in_queue' => true,
            'message' => 'This patient is already in the active queue.',
            'queue_id' => (int)$existingQueue['id'],
            'queue_number' => (int)($existingQueue['queue_number'] ?? 0),
            'queue_label' => $existingQueue['queue_label'] ?? ('#' . ($existingQueue['queue_number'] ?? $existingQueue['id']))
        ]);
        exit;
    }

    // Combine complaint and notes
    $full_notes = $complaint;
    if (!empty($notes)) {
        $full_notes .= "\n\nAdditional Notes: " . $notes;
    }

    $pdo->beginTransaction();

    // Get patient's name for queue display
    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, suffix FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    $patient_name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '') . ' ' . ($patient['suffix'] ?? '')) ?: 'Patient';

    // Resolve service ID and price — prefer posted service_id, fallback to name match
    $serviceId = !empty($service_id) ? intval($service_id) : null;
    $totalAmount = 0;
    try {
        if ($serviceId) {
            $svcStmt = $pdo->prepare("SELECT price FROM services WHERE id = ? AND is_active = 1 LIMIT 1");
            $svcStmt->execute([$serviceId]);
            $price = $svcStmt->fetchColumn();
            if ($price) $totalAmount = floatval($price);
        }
        if ($totalAmount <= 0) {
            $svcStmt = $pdo->prepare("SELECT id, price FROM services WHERE (LOWER(name) = LOWER(?) OR name LIKE ?) AND is_active = 1 LIMIT 1");
            $svcStmt->execute([$treatment_type, '%' . $treatment_type . '%']);
            $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);
            if ($svc) {
                if (!$serviceId) $serviceId = $svc['id'];
                $totalAmount = floatval($svc['price']);
            }
        }
    } catch (Exception $e) {}

    error_log("Quick Session - serviceId=$serviceId, treatment_type=$treatment_type, totalAmount=$totalAmount");

    // Create queue entry through helper
    $queueEntry = createQueueEntry($pdo, [
        'patient_id'     => $patient_id,
        'queue_date'     => date('Y-m-d'),
        'treatment_type' => $treatment_type,
        'service_id'     => $serviceId,
        'teeth_numbers'  => $teeth_numbers,
        'notes'          => $full_notes,
        'queue_time'     => date('H:i:s'),
        'status'         => 'waiting',
        'priority'       => $priority,
    ]);
    
    $queue_id = (int)$queueEntry['id'];
    $queue_number = (int)($queueEntry['queue_number'] ?? 0);

    // Create billing record
    $billingId = null;
    if ($totalAmount > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO billing (patient_id, total_amount, paid_amount, balance, payment_status, billing_date, due_date, notes, created_at, updated_at)
                VALUES (?, ?, 0, ?, 'unpaid', CURDATE(), CURDATE(), ?, NOW(), NOW())
            ");
            $stmt->execute([$patient_id, $totalAmount, $totalAmount, 'Billing from quick session - ' . $treatment_type]);
            $billingId = $pdo->lastInsertId();
            error_log("Quick Session - billing created: id=$billingId, total=$totalAmount");
        } catch (Exception $e) {
            error_log("Quick Session billing error: " . $e->getMessage());
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Patient added to queue successfully!',
        'queue_id' => $queue_id,
        'queue_number' => $queue_number,
        'queue_label' => '#' . $queue_number,
        'patient_name' => $patient_name
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Quick Session Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
