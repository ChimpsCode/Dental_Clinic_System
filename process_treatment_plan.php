<?php
/**
 * Process Treatment Plan - Create/Update
 */

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';

try {
    $plan_id = $_POST['plan_id'] ?? null;
    $patient_id = $_POST['patient_id'] ?? null;
    $treatment_name = $_POST['treatment_name'] ?? '';
    $treatment_type = $_POST['treatment_type'] ?? '';
    $teeth_numbers = $_POST['teeth_numbers'] ?? '';
    $total_sessions = $_POST['total_sessions'] ?? 1;
    $completed_sessions = $_POST['completed_sessions'] ?? 0;
    $status = $_POST['status'] ?? 'active';
    $next_session_date = $_POST['next_session_date'] ?? null;
    $estimated_cost = $_POST['estimated_cost'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $doctor_id = $_SESSION['user_id'];
    
    if (!$patient_id || !$treatment_name) {
        echo json_encode(['success' => false, 'message' => 'Patient and Treatment Name are required']);
        exit();
    }
    
    if ($plan_id) {
        // Update existing plan
        $stmt = $pdo->prepare("
            UPDATE treatment_plans SET 
                treatment_name = ?,
                treatment_type = ?,
                teeth_numbers = ?,
                total_sessions = ?,
                completed_sessions = ?,
                status = ?,
                next_session_date = ?,
                estimated_cost = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ? AND patient_id = ?
        ");
        $result = $stmt->execute([
            $treatment_name,
            $treatment_type,
            $teeth_numbers,
            $total_sessions,
            $completed_sessions,
            $status,
            $next_session_date ?: null,
            $estimated_cost ?: null,
            $notes,
            $plan_id,
            $patient_id
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Treatment plan updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update treatment plan']);
        }
    } else {
        // Create new plan
        $stmt = $pdo->prepare("
            INSERT INTO treatment_plans (
                patient_id, treatment_name, treatment_type, teeth_numbers,
                total_sessions, completed_sessions, status, next_session_date,
                estimated_cost, notes, doctor_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $result = $stmt->execute([
            $patient_id,
            $treatment_name,
            $treatment_type,
            $teeth_numbers,
            $total_sessions,
            $completed_sessions,
            $status,
            $next_session_date ?: null,
            $estimated_cost ?: null,
            $notes,
            $doctor_id
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Treatment plan created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create treatment plan']);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
