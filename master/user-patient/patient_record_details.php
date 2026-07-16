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
    $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, phone, email, gender, date_of_birth, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age FROM patients WHERE id = ?");
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
            WHERE patient_id = ? AND status = 'completed' AND is_archived = 0
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
            SELECT q.*, q.status as status_text
            FROM queue q 
            WHERE q.patient_id = ? AND q.status IN ('waiting', 'in_procedure') AND q.is_archived = 0 
            ORDER BY q.created_at DESC LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $queueItem = null;
    }
    
    // Get billing data - ALL records for this patient with service names
    $billing = null;
    $allBillings = [];
    try {
        $stmt = $pdo->prepare("
            SELECT b.id, b.total_amount, b.paid_amount, b.balance, b.payment_status,
                   b.billing_date, b.due_date, b.notes, b.appointment_id, b.patient_id,
                   COALESCE(svc.name, q.treatment_type, 'General Service') as service_name
            FROM billing b
            LEFT JOIN appointments a ON b.appointment_id = a.id
            LEFT JOIN services svc ON a.service_id = svc.id
            LEFT JOIN queue q ON DATE(b.billing_date) = DATE(q.created_at) AND q.patient_id = b.patient_id
            WHERE b.patient_id = ?
            ORDER BY b.created_at ASC
        ");
        $stmt->execute([$patientId]);
        $allBillings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $billing = !empty($allBillings) ? end($allBillings) : null;
    } catch (Exception $e) {
        $billing = null;
    }
    
    // Get payment records
    $payments = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, billing_id, amount, payment_method, payment_date, reference_number, notes, created_at
            FROM payments
            WHERE patient_id = ?
            ORDER BY payment_date DESC, created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$patientId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $payments = [];
    }
    
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'medical_history' => $medicalHistory,
        'dental_history' => $dentalHistory,
        'queue_item' => $queueItem,
        'billing' => $billing,
        'all_billings' => $allBillings,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    error_log("Patient Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}