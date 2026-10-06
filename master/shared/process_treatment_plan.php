<?php
/**
 * Process Treatment Plan - Create/Update
 * Auto-creates appointment when date/time provided
 * Includes double-booking prevention
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['dentist', 'admin', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/appointment_approval_helper.php';
require_once '../../includes/treatment_plan_workflow.php';
ensureTreatmentPlanWorkflowSchema($pdo);
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
            linkLegacyTreatmentPlanSessions($pdo, $plan);
            $progress = syncTreatmentPlanProgress($pdo, (int)$plan['id']);
            $plan = array_merge($plan, $progress);
            $plan['status'] = $progress['overall_status'];
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

if (!in_array(strtolower($_SESSION['role'] ?? ''), ['dentist', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only dentists or admins can change treatment plans']);
    exit();
}

// HANDLE POST REQUEST - SAVE/UPDATE
$plan_id = $_POST['plan_id'] ?? null;
$patient_id = $_POST['patient_id'] ?? null;
$plan_name = $_POST['treatment_name'] ?? $_POST['plan_name'] ?? '';
$total_sessions = (int)($_POST['total_sessions'] ?? 1);
if ($total_sessions < 1 || $total_sessions > 8) {
    echo json_encode(['success' => false, 'message' => 'Choose between 1 and 8 sessions']);
    exit();
}

// If no plan_name provided, derive from first session's treatment or use default
if (empty($plan_name)) {
    for ($i = 1; $i <= $total_sessions; $i++) {
        $stx = $_POST["session_treatment_{$i}"] ?? '';
        $stxOther = $_POST["session_treatment_other_{$i}"] ?? '';
        $treatment = !empty($stxOther) ? $stxOther : $stx;
        if (!empty($treatment)) {
            $plan_name = $treatment;
            break;
        }
    }
}
if (empty($plan_name)) {
    $plan_name = 'Treatment Plan';
}
$estimated_cost = $_POST['estimated_cost'] ?? null;
if ($estimated_cost !== null && $estimated_cost !== '' && (!is_numeric($estimated_cost) || !is_finite((float)$estimated_cost) || (float)$estimated_cost < 0)) {
    echo json_encode(['success' => false, 'message' => 'Estimated cost must be a non-negative amount']);
    exit();
}
$estimated_cost = $estimated_cost === null || $estimated_cost === '' ? null : round((float)$estimated_cost, 2);
$status = 'Active'; // Progress is derived from the linked sessions.
$notes = $_POST['notes'] ?? '';
$teeth_numbers = $_POST['teeth_numbers'] ?? '';
$session_date = $_POST['next_session_date'] ?? null;
$session_time = $_POST['session_time'] ?? $_POST['next_session_time'] ?? null;
$doctor_id = $_SESSION['user_id'];

// Build sessions_data from individual session fields
$sessionsData = [];
for ($i = 1; $i <= $total_sessions; $i++) {
    $sd = $_POST["session_date_{$i}"] ?? null;
    $st = $_POST["session_time_{$i}"] ?? null;
    $stx = $_POST["session_treatment_{$i}"] ?? '';
    $stxOther = $_POST["session_treatment_other_{$i}"] ?? '';
    $snotes = $_POST["session_notes_{$i}"] ?? '';
    $sessionCost = $_POST["session_estimated_cost_{$i}"] ?? null;
    if ($sessionCost !== null && (!is_numeric($sessionCost) || !is_finite((float)$sessionCost) || (float)$sessionCost < 0)) {
        echo json_encode(['success' => false, 'message' => "Invalid estimated cost for Session {$i}"]);
        exit();
    }
    if (!empty($sd)) {
        $entry = ['date' => $sd];
        if (!empty($st)) $entry['time'] = date("H:i:s", strtotime($st));
        $treatment = !empty($stxOther) ? $stxOther : $stx;
        if (!empty($treatment)) $entry['treatment'] = $treatment;
        if (!empty($snotes)) $entry['notes'] = $snotes;
        if ($sessionCost !== null) $entry['estimated_cost'] = round((float)$sessionCost, 2);
        $sessionsData[] = $entry;
    }
}
$sessionsJson = !empty($sessionsData) ? json_encode($sessionsData) : null;
if (count($sessionsData) !== $total_sessions || count(array_filter($sessionsData, fn($s) => empty($s['time']) || empty($s['treatment'])))) {
    echo json_encode(['success' => false, 'message' => 'Every session needs a treatment, date and time']);
    exit();
}

if (!$patient_id || !$plan_name) {
    echo json_encode(['success' => false, 'message' => 'Patient and Plan Name required']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Parse first session time to 24-hour format for MySQL
    $apptTime = null;
    if (!empty($session_time)) {
        $apptTime = date("H:i:s", strtotime($session_time));
    }

    if ($plan_id) {
        $existingStmt = $pdo->prepare('SELECT * FROM treatment_plans WHERE id = ? AND patient_id = ? FOR UPDATE');
        $existingStmt->execute([$plan_id, $patient_id]);
        $existingPlan = $existingStmt->fetch();
        if (!$existingPlan) throw new Exception('Treatment plan not found');
        linkLegacyTreatmentPlanSessions($pdo, $existingPlan);
        $existingPlan = syncTreatmentPlanProgress($pdo, (int)$plan_id);
        $oldSessions = json_decode($existingPlan['sessions_data'] ?? '', true) ?: [];
        if (count($sessionsData) < count($oldSessions)) throw new Exception('Cancel individual sessions instead of removing them');
        foreach ($oldSessions as $index => $oldSession) {
            if (in_array($oldSession['status'] ?? '', ['Waiting','In-Progress','Completed','Cancelled'], true)) {
                foreach (['date','time','treatment'] as $field) {
                    if (($oldSession[$field] ?? '') !== ($sessionsData[$index][$field] ?? '')) throw new Exception('Started, completed or cancelled sessions cannot be changed');
                }
            }
            if (isset($oldSession['status'])) $sessionsData[$index]['status'] = $oldSession['status'];
            if (!isset($sessionsData[$index]['estimated_cost']) && isset($oldSession['estimated_cost'])) $sessionsData[$index]['estimated_cost'] = $oldSession['estimated_cost'];
        }
        $sessionsJson = json_encode($sessionsData);
        $doctor_id = (int)$existingPlan['doctor_id'];
        $stmt = $pdo->prepare("UPDATE treatment_plans SET plan_name = ?, total_sessions = ?, total_estimated_cost = ?, overall_status = ?, notes = ?, teeth_numbers = ?, sessions_data = ?, next_session_date = ?, next_session_time = ?, updated_at = NOW() WHERE id = ? AND patient_id = ?");
        $stmt->execute([$plan_name, $total_sessions, $estimated_cost, $status, $notes, $teeth_numbers, $sessionsJson, $session_date ?: null, $apptTime ?: null, $plan_id, $patient_id]);
        $message = 'Treatment plan updated';
    } else {
        $stmt = $pdo->prepare("INSERT INTO treatment_plans (patient_id, doctor_id, plan_name, total_sessions, total_estimated_cost, overall_status, notes, teeth_numbers, sessions_data, next_session_date, next_session_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$patient_id, $doctor_id, $plan_name, $total_sessions, $estimated_cost, $status, $notes, $teeth_numbers, $sessionsJson, $session_date ?: null, $apptTime ?: null]);

        $planId = $pdo->lastInsertId();
        $message = 'Treatment plan created';

    }

    $planId = (int)($planId ?? $plan_id);
    $createdAppointments = reserveTreatmentPlanSessions($pdo, $planId, (int)$patient_id, (int)$doctor_id, $sessionsData);
    $updatedPlan = syncTreatmentPlanProgress($pdo, $planId);
    $sessionsData = json_decode($updatedPlan['sessions_data'], true);
    $message = $plan_id ? 'Treatment plan updated.' : 'Treatment plan created. ' . count($sessionsData) . ' session(s) scheduled.';

    $pdo->commit();

    // Send email notification for sessions within threshold
    if (!empty($sessionsData)) {
        try {
            require_once __DIR__ . '/../../includes/session_notification_helper.php';
            $thresholdStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'session_notification_threshold' LIMIT 1");
            $thresholdStmt->execute();
            $threshold = (int)($thresholdStmt->fetchColumn() ?: 2);
            sendSessionNotification($pdo, $patient_id, $plan_name, $sessionsData, $threshold);
        } catch (Exception $e) {}
    }

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'plan_id' => $planId ?? $plan_id,
        'appointment_id' => $updatedPlan['appointment_id'] ?? null,
        'appointments' => $createdAppointments ?? [],
        'sessions' => $sessionsData ?: []
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
