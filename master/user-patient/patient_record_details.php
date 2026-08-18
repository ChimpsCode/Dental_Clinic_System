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

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "` LIKE '" . str_replace("'", "\\'", $column) . "'");
        return $result !== false && $result->fetchColumn() !== false;
    } catch (Exception $e) {
        return false;
    }
}

try {
    require_once '../../config/database.php';

    $selectList = [
        'p.id',
        'p.first_name',
        'p.middle_name',
        'p.last_name',
        'COALESCE(p.phone, u.phone) as phone',
        'COALESCE(p.email, u.email) as email',
        'COALESCE(p.gender, u.gender) as gender',
        'p.date_of_birth',
        'TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age',
    ];

    if (tableHasColumn($pdo, 'patients', 'street_address')) {
        $selectList[] = 'p.street_address';
    } elseif (tableHasColumn($pdo, 'patients', 'address')) {
        $selectList[] = 'p.address as street_address';
    } elseif (tableHasColumn($pdo, 'users', 'street_address')) {
        $selectList[] = 'u.street_address as street_address';
    } elseif (tableHasColumn($pdo, 'users', 'address')) {
        $selectList[] = 'u.address as street_address';
    } else {
        $selectList[] = 'NULL as street_address';
    }

    if (tableHasColumn($pdo, 'patients', 'emergency_contact_name')) {
        $selectList[] = 'p.emergency_contact_name';
    } elseif (tableHasColumn($pdo, 'users', 'emergency_contact_name')) {
        $selectList[] = 'u.emergency_contact_name as emergency_contact_name';
    } else {
        $selectList[] = 'NULL as emergency_contact_name';
    }

    if (tableHasColumn($pdo, 'patients', 'emergency_contact_phone')) {
        $selectList[] = 'p.emergency_contact_phone';
    } elseif (tableHasColumn($pdo, 'users', 'emergency_contact_phone')) {
        $selectList[] = 'u.emergency_contact_phone as emergency_contact_phone';
    } else {
        $selectList[] = 'NULL as emergency_contact_phone';
    }

    if (tableHasColumn($pdo, 'patients', 'emergency_contact_relationship')) {
        $selectList[] = 'p.emergency_contact_relationship';
    } elseif (tableHasColumn($pdo, 'users', 'emergency_contact_relationship')) {
        $selectList[] = 'u.emergency_contact_relationship as emergency_contact_relationship';
    } else {
        $selectList[] = 'NULL as emergency_contact_relationship';
    }

    if (tableHasColumn($pdo, 'patients', 'city')) {
        $selectList[] = 'p.city';
    } elseif (tableHasColumn($pdo, 'users', 'city')) {
        $selectList[] = 'u.city as city';
    } else {
        $selectList[] = 'NULL as city';
    }

    if (tableHasColumn($pdo, 'patients', 'province')) {
        $selectList[] = 'p.province';
    } elseif (tableHasColumn($pdo, 'users', 'province')) {
        $selectList[] = 'u.province as province';
    } else {
        $selectList[] = 'NULL as province';
    }

    if (tableHasColumn($pdo, 'patients', 'zip_code')) {
        $selectList[] = 'p.zip_code';
    } elseif (tableHasColumn($pdo, 'users', 'zip_code')) {
        $selectList[] = 'u.zip_code as zip_code';
    } else {
        $selectList[] = 'NULL as zip_code';
    }

    $joinClause = '';
    if (tableHasColumn($pdo, 'users', 'patient_id')) {
        $joinClause = ' LEFT JOIN users u ON u.patient_id = p.id ';
    } elseif (tableHasColumn($pdo, 'patients', 'user_id')) {
        $joinClause = ' LEFT JOIN users u ON u.id = p.user_id ';
    }

    $query = "
        SELECT " . implode(', ', $selectList) . "
        FROM patients p
        " . $joinClause . "
        WHERE p.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($query);
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
            SELECT q.*, q.status as status_text, s.name as service_name, s.price as service_price
            FROM queue q 
            LEFT JOIN services s ON q.service_id = s.id
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
                    COALESCE(svc.name, q.treatment_type, 'General Service') as service_name,
                    svc.price as service_price
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
    
    // Get full consultation history (all consultations, not just those with teeth_data)
    $consultationHistory = [];
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.teeth_data, c.created_at, c.status AS consultation_status,
                   c.consultation_notes, c.dental_medical_history_notes, c.prescription_data,
                   c.periodontal_screening,
                   c.occlusion_class_molar, c.occlusion_overjet, c.occlusion_overbite,
                   c.occlusion_midline_deviation, c.occlusion_crossbite,
                   c.appliances_orthodontic, c.appliances_skyplate, c.appliances_others, c.appliances_others_text,
                   c.tmd_clenching, c.tmd_clicking, c.tmd_trismus, c.tmd_muscle_spasm,
                   c.consent_provider, c.consent_procedure, c.consent_date, c.consent_place,
                   c.consent_given, c.consent_patient_signature, c.consent_doctor_signature, c.consent_witness_signature,
                   c.consent_patient_sign_name, c.consent_witness_sign_name,
                   q.treatment_type AS queue_treatment_type, q.queue_number,
                   u.first_name AS dentist_first_name, u.last_name AS dentist_last_name
            FROM consultations c
            LEFT JOIN queue q ON q.id = c.queue_id
            LEFT JOIN users u ON u.id = c.dentist_id
            WHERE c.patient_id = ?
            ORDER BY c.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$patientId]);
        $consultationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($consultationHistory as &$ch) {
            if (is_string($ch['teeth_data'] ?? '')) {
                $ch['teeth_data'] = json_decode($ch['teeth_data'], true);
            }
            if (is_string($ch['prescription_data'] ?? '')) {
                $ch['prescription_data'] = json_decode($ch['prescription_data'], true);
            }
        }
        unset($ch);
    } catch (Exception $e) {
        $consultationHistory = [];
    }
    
    // Get prescriptions for this patient
    $prescriptions = [];
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.username AS doctor_name
            FROM prescriptions pr
            LEFT JOIN users u ON pr.doctor_id = u.id
            WHERE pr.patient_id = ?
            ORDER BY pr.issue_date DESC, pr.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$patientId]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $prescriptions = [];
    }

    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'medical_history' => $medicalHistory,
        'dental_history' => $dentalHistory,
        'queue_item' => $queueItem,
        'billing' => $billing,
        'all_billings' => $allBillings,
        'payments' => $payments,
        'odontogram_sessions' => $consultationHistory,
        'consultation_history' => $consultationHistory,
        'prescriptions' => $prescriptions
    ]);
    
} catch (Exception $e) {
    error_log("Patient Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}