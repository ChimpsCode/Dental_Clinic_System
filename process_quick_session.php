<?php
/**
 * Process Quick Session - Add returning patient to queue
 */

header('Content-Type: application/json');

session_start();
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_id = $_POST['patient_id'] ?? null;
    $treatment_type = $_POST['treatment_type'] ?? 'General Checkup';
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

    // Generate queue number (simple increment)
    $stmt = $pdo->query("SELECT COUNT(*) + 1 as next_num FROM queue WHERE DATE(created_at) = CURDATE()");
    $nextNum = $stmt->fetch();
    $queue_number = 'Q-' . str_pad($nextNum['next_num'] ?? 1, 3, '0', STR_PAD_LEFT);

    // Insert into queue
    $stmt = $pdo->prepare("
        INSERT INTO queue (patient_id, treatment_type, teeth_numbers, status, priority, queue_time, notes, created_by, created_at)
        VALUES (?, ?, ?, 'waiting', ?, NOW(), ?, ?, NOW())
    ");
    $stmt->execute([$patient_id, $treatment_type, $teeth_numbers, $priority, $full_notes, $_SESSION['user_id'] ?? 1]);
    
    $queue_id = $pdo->lastInsertId();

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
