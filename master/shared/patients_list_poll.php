<?php
/**
 * Patients List Poll Endpoint
 * Returns the fresh active-patient list (same SQL as the patient record pages)
 * so admin/staff/dentist lists can update live without a full page reload.
 */

ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userRole = strtolower($_SESSION['role'] ?? '');
if (!in_array($userRole, ['admin', 'dentist', 'staff'], true)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    require_once '../../config/database.php';
    require_once __DIR__ . '/../../includes/patient_list_overflow_helper.php';
    ensurePatientLogColumn($pdo);

    // Must stay byte-compatible with the initial query in
    // admin_patients.php / staff_patient_records.php / dentist_patients.php
    $stmt = $pdo->prepare("
        SELECT p.*,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               a.status as appointment_status,
               a.appointment_date,
               a.appointment_time,
               q.status as queue_status,
               COALESCE(svc.name, q.treatment_type, 'N/A') as service_name
        FROM patients p
        LEFT JOIN (
            SELECT a1.*
            FROM appointments a1
            WHERE a1.id = (
                SELECT MAX(a2.id) FROM appointments a2 WHERE a2.patient_id = a1.patient_id
            )
        ) a ON p.id = a.patient_id
        LEFT JOIN services svc ON a.service_id = svc.id
        LEFT JOIN (
            SELECT q1.*
            FROM queue q1
            WHERE q1.id = (
                SELECT MAX(q2.id) FROM queue q2 WHERE q2.patient_id = q1.patient_id
            )
        ) q ON p.id = q.patient_id
        WHERE p.is_archived = 0 AND COALESCE(p.in_patient_log, 0) = 0
        GROUP BY p.id
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total'   => count($patients),
        'patients'=> $patients
    ]);
} catch (Exception $e) {
    error_log("Patients List Poll Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
