<?php
/**
 * Patient Record Details API
 */

ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['dentist', 'staff', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$patientId = $_GET['id'] ?? null;

if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'No patient ID provided']);
    exit();
}

try {
    require_once '../../config/database.php';
    
    // Get patient basic info - only use columns that definitely exist
    $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone, email, gender, date_of_birth FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Get medical history - skip if table doesn't exist or has issues
    $medicalHistory = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$patientId]);
        $medicalHistory = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Medical history table might not exist or have issues
        $medicalHistory = null;
    }
    
    // Get dental history - simplified query
    $dentalHistory = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, treatment_type, teeth_numbers, completed_at
            FROM queue
            WHERE patient_id = ? AND status_id = 5 AND is_archived = 0
            ORDER BY completed_at DESC
            LIMIT 20
        ");
        $stmt->execute([$patientId]);
        $dentalHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Queue table query failed
        $dentalHistory = [];
    }
    
    // Get current queue item - simplified
    $queueItem = null;
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, qs.status_name as status_text
            FROM queue q 
            LEFT JOIN queue_statuses qs ON q.status_id = qs.id
            WHERE q.patient_id = ? AND q.status_id IN (1, 2) AND q.is_archived = 0 
            ORDER BY q.created_at DESC LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $queueItem = null;
    }
    
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'medical_history' => $medicalHistory,
        'dental_history' => $dentalHistory,
        'queue_item' => $queueItem
    ]);
    
} catch (Exception $e) {
    error_log("Patient Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}