<?php
/**
 * Process Treatment Plan - Create/Update
 * Auto-creates appointment when date/time provided
 * Includes double-booking prevention
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';
header('Content-Type: application/json');

// HANDLE GET REQUEST - FETCH PLANS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patient_id = $_GET['patient_id'] ?? null;
    if (!$patient_id) {
        error_log("Treatment Plan Fetch Error: Missing patient_id");
        echo json_encode(['success' => false, 'message' => 'Patient ID required']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT tp.*, tp.plan_name as treatment_name, tp.overall_status as status,
                   s.name as service_name
            FROM treatment_plans tp
            LEFT JOIN services s ON (tp.plan_name REGEXP '^[0-9]+$' AND s.id = tp.plan_name)
            WHERE tp.patient_id = ?
            ORDER BY tp.created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug: log the number of plans found
        error_log("Treatment Plan Fetch: Found " . count($plans) . " plans for patient_id " . $patient_id);

        // Resolve plan_name if it's a service ID and normalize status
        foreach ($plans as &$plan) {
            if ($plan['service_name']) {
                $plan['treatment_name'] = $plan['service_name'];
            }
            
            // Normalize status for frontend: 'In-Progress' -> 'in_progress', 'Completed' -> 'completed', etc.
            $plan['status'] = strtolower(str_replace('-', '_', $plan['status'] ?? ''));
        }

        // FETCH TREATMENT HISTORY (DONE TREATMENTS)
        $historyStmt = $pdo->prepare("
            SELECT t.*, s.name as service_name, u.first_name as doctor_first, u.last_name as doctor_last
            FROM treatments t
            LEFT JOIN services s ON t.service_id = s.id
            LEFT JOIN users u ON t.doctor_id = u.id
            WHERE t.patient_id = ?
            ORDER BY t.treatment_date DESC, t.id DESC
        ");
        $historyStmt->execute([$patient_id]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'plans' => $plans,
            'history' => $history,
            'debug' => [
                'patient_id' => $patient_id,
                'count' => count($plans)
            ]
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Treatment Plan Fetch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error fetching plans: ' . $e->getMessage()]);
        exit();
    }
}

// HANDLE POST REQUEST - SAVE/UPDATE
$plan_id = $_POST['plan_id'] ?? null;
$patient_id = $_POST['patient_id'] ?? null;
$plan_name = $_POST['treatment_name'] ?? $_POST['plan_name'] ?? '';
$total_sessions = $_POST['total_sessions'] ?? 1;
$estimated_cost = $_POST['estimated_cost'] ?? null;
$status = $_POST['status'] ?? 'Active';
$notes = $_POST['notes'] ?? '';
$teeth_numbers = $_POST['teeth_numbers'] ?? '';
$session_date = $_POST['next_session_date'] ?? null;
$session_time = $_POST['session_time'] ?? $_POST['next_session_time'] ?? null;
$doctor_id = $_SESSION['user_id'];

if (!$patient_id || !$plan_name) {
    echo json_encode(['success' => false, 'message' => 'Patient and Plan Name required']);
    exit();
}

try {
    // Start a transaction so if the appointment fails, the plan rolls back too
    $pdo->beginTransaction();

    // Parse time to 24-hour format for MySQL
    $apptTime = null;
    if (!empty($session_time)) {
        $apptTime = date("H:i:s", strtotime($session_time));
    }

    if ($plan_id) {
        // UPDATE EXISTING PLAN
        $stmt = $pdo->prepare("UPDATE treatment_plans SET plan_name = ?, total_sessions = ?, total_estimated_cost = ?, overall_status = ?, notes = ?, teeth_numbers = ?, next_session_date = ?, next_session_time = ?, updated_at = NOW() WHERE id = ? AND patient_id = ?");
        $stmt->execute([$plan_name, $total_sessions, $estimated_cost ?: null, $status, $notes, $teeth_numbers, $session_date ?: null, $apptTime ?: null, $plan_id, $patient_id]);
        $message = 'Treatment plan updated';
    } else {
        // CREATE NEW PLAN
        $stmt = $pdo->prepare("INSERT INTO treatment_plans (patient_id, doctor_id, plan_name, total_sessions, total_estimated_cost, overall_status, notes, teeth_numbers, next_session_date, next_session_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$patient_id, $doctor_id, $plan_name, $total_sessions, $estimated_cost ?: null, $status, $notes, $teeth_numbers, $session_date ?: null, $apptTime ?: null]);
        
        $planId = $pdo->lastInsertId();
        $message = 'Treatment plan created';
        
        // AUTO-CREATE APPOINTMENT
        if (!empty($session_date) && !empty($apptTime)) {
            // DOUBLE-BOOKING CHECK: Check if there's already an appointment at this time
            $conflictCheck = $pdo->prepare("
                SELECT a.id, a.reference_code, p.first_name, p.last_name 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id
                WHERE a.appointment_date = ? 
                AND a.appointment_time = ?
                AND a.is_archived = 0
                AND a.status NOT IN ('Cancelled')
            ");
            $conflictCheck->execute([$session_date, $apptTime]);
            $conflict = $conflictCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($conflict) {
                throw new Exception("Time slot conflict! There is already an appointment ($conflict[reference_code]) for $conflict[first_name] $conflict[last_name] at this time.");
            }
            
            $refCode = 'APT' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Get service info
            $serviceId = null;
            $duration = 30;
            $svc = $pdo->prepare("SELECT id, duration_minutes FROM services WHERE name = ? LIMIT 1");
            $svc->execute([$plan_name]);
            if ($row = $svc->fetch(PDO::FETCH_ASSOC)) {
                $serviceId = $row['id'];
                $duration = $row['duration_minutes'] ?? 30;
            }
            
            // Create the appointment
            $ins = $pdo->prepare("
                INSERT INTO appointments 
                (reference_code, patient_id, service_id, status, appointment_date, appointment_time, duration_minutes, booked_by_id, created_by, created_at, is_archived) 
                VALUES (?, ?, ?, 'Scheduled', ?, ?, ?, ?, ?, NOW(), 0)
            ");
            $ins->execute([$refCode, $patient_id, $serviceId, $session_date, $apptTime, $duration, $doctor_id, $doctor_id]);
            
            $apptId = $pdo->lastInsertId();
            
            // Link the appointment to the treatment plan
            $linkStmt = $pdo->prepare("UPDATE treatment_plans SET appointment_id = ?, updated_at = NOW() WHERE id = ?");
            $linkStmt->execute([$apptId, $planId]);
            
            $message = "Treatment plan created. Appointment #$refCode has been scheduled for $session_date at $session_time.";
        }
    }
    
    // Commit the transaction
    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'plan_id' => $planId ?? $plan_id,
        'appointment_id' => $apptId ?? null
    ]);
    
} catch (Exception $e) {
    // If anything fails, revert all changes so we don't end up with ghost plans
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
