<?php
/**
 * Process Quick Session - Add returning patient to queue
 */

header('Content-Type: application/json');

session_start();
require_once '../../config/database.php';

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

    // Generate queue number (simple increment)
    $stmt = $pdo->query("SELECT COUNT(*) + 1 as next_num FROM queue WHERE DATE(created_at) = CURDATE()");
    $nextNum = $stmt->fetch();
    $queue_number = 'Q-' . str_pad($nextNum['next_num'] ?? 1, 3, '0', STR_PAD_LEFT);

    // Insert into queue - status 'waiting'
    $stmt = $pdo->prepare("
        INSERT INTO queue (patient_id, treatment_type, service_id, teeth_numbers, status, priority, queue_time, notes, created_at)
        VALUES (?, ?, ?, ?, 'waiting', ?, NOW(), ?, NOW())
    ");
    $stmt->execute([$patient_id, $treatment_type, $serviceId, $teeth_numbers, $priority, $full_notes]);
    
    $queue_id = $pdo->lastInsertId();

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
