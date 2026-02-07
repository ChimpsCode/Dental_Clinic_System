<?php
/**
 * Patient Record Details API
 * Returns detailed patient information including medical and dental history
 */

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['dentist', 'staff', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$patientId = $_GET['id'] ?? null;

if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Get patient basic info
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Build full_name from separate name fields
    $nameParts = array_filter([
        $patient['first_name'] ?? '',
        $patient['middle_name'] ?? '',
        $patient['last_name'] ?? '',
        $patient['suffix'] ?? ''
    ]);
    $patient['full_name'] = implode(' ', $nameParts);
    
    // Get medical history (most recent)
    $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$patientId]);
    $medicalHistory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get dental history (most recent)
    $stmt = $pdo->prepare("SELECT * FROM dental_history WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$patientId]);
    $dentalHistory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current queue item if any
    $stmt = $pdo->prepare("SELECT * FROM queue WHERE patient_id = ? AND status IN ('waiting', 'in_procedure') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$patientId]);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get treatment history (all completed treatments)
    $stmt = $pdo->prepare("SELECT * FROM treatments WHERE patient_id = ? ORDER BY created_at DESC");
    $stmt->execute([$patientId]);
    $treatmentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'medical_history' => $medicalHistory,
        'dental_history' => $dentalHistory,
        'queue_item' => $queueItem,
        'treatment_history' => $treatmentHistory
    ]);
    
} catch (Exception $e) {
    error_log("Patient Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading patient details']);
}
?>
