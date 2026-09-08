<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$patient_id = $_SESSION['patient_id'] ?? null;

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));

function patientDecodeJson($value): array {
    if (is_array($value)) {
        $decoded = $value;
    } else {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        if (!is_array($decoded)) {
            return [];
        }
    }
    foreach ($decoded as &$item) {
        if (is_array($item) && isset($item['tooth'])) {
            $item['tooth'] = (int)$item['tooth'];
        }
    }
    unset($item);
    return $decoded;
}

function patientIdPlaceholders(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        $ids = [0];
    }
    return [$ids, implode(',', array_fill(0, count($ids), '?'))];
}

function patientFormatDate(?string $value, string $format = 'F j, Y'): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '';
}

$mhFlagLabels = [
    'heart_condition' => 'Heart Condition',
    'diabetes' => 'Diabetes',
    'bleeding_disorder' => 'Bleeding Disorder',
    'pregnancy' => 'Pregnancy',
    'blood_pressure' => 'Blood Pressure Issues',
    'hiv_aids' => 'HIV/AIDS',
    'hepatitis' => 'Hepatitis',
    'tuberculosis' => 'Tuberculosis',
    'asthma' => 'Asthma',
    'epilepsy' => 'Epilepsy',
    'std' => 'STD',
    'stomach_ulcer' => 'Stomach Ulcer',
    'fainting_seizure' => 'Fainting / Seizure',
    'rapid_weight_loss' => 'Rapid Weight Loss',
    'rheumatic_fever' => 'Rheumatic Fever',
    'hay_fever' => 'Hay Fever / Allergies',
    'respiratory_problems' => 'Respiratory Problems',
    'swollen_ankles' => 'Swollen Ankles',
    'kidney_disease' => 'Kidney Disease',
    'chest_pain' => 'Chest Pain',
    'stroke' => 'Stroke',
];

$consultations = [];
$dentalHistory = [];
$treatmentPlans = [];
$xraysByConsultation = [];
$xraysUnassigned = [];
$positiveFlags = [];
$patient = [];
$medical = [];
$prescriptions = [];
$patientIds = $patient_id ? [(int)$patient_id] : [0];

try {
    $patientJoinClause = 'LEFT JOIN users u ON u.id = p.user_id';
    $patientUserWhere = 'p.user_id = ?';
    try {
        $hasUsersPatientId = false;
        foreach ($pdo->query('SHOW COLUMNS FROM users') as $col) {
            $field = strtolower((string)($col['Field'] ?? $col[0] ?? ''));
            if ($field === 'patient_id') { $hasUsersPatientId = true; break; }
        }
        $hasPatientsUserId = false;
        foreach ($pdo->query('SHOW COLUMNS FROM patients') as $col) {
            $field = strtolower((string)($col['Field'] ?? $col[0] ?? ''));
            if ($field === 'user_id') { $hasPatientsUserId = true; break; }
        }
        if ($hasUsersPatientId || !$hasPatientsUserId) {
            $patientJoinClause = 'LEFT JOIN users u ON u.patient_id = p.id';
            $patientUserWhere = 'u.id = ?';
        }
    } catch (Exception $e) {
        // keep default schema fallback
    }

    $patientQuery = "
        SELECT p.*, COALESCE(u.first_name, p.first_name) AS first_name, COALESCE(u.middle_name, p.middle_name) AS middle_name, COALESCE(u.last_name, p.last_name) AS last_name, u.email, u.phone,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p
        $patientJoinClause
    ";

    $stmt = $pdo->prepare($patientQuery . " WHERE $patientUserWhere");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient && $patient_id) {
        $stmt = $pdo->prepare($patientQuery . ' WHERE p.id = ?');
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$patient && !$patient_id) {
        throw new Exception('Patient not found');
    }
    
    if (!$patient_id && $patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }

    $patientIds = [];
    if ($patient_id) {
        $patientIds[] = (int)$patient_id;
    }
    if (!empty($patient['id'])) {
        $patientIds[] = (int)$patient['id'];
    }
    try {
        $linkStmt = $pdo->prepare('SELECT patient_id FROM users WHERE id = ? LIMIT 1');
        $linkStmt->execute([(int)$user_id]);
        $linkedPid = (int)$linkStmt->fetchColumn();
        if ($linkedPid > 0) {
            $patientIds[] = $linkedPid;
        }
    } catch (Exception $e) {
        // users.patient_id may not exist
    }
    try {
        $uStmt = $pdo->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([(int)$user_id]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $matchConds = [];
        $matchParams = [];
        if (!empty($uRow['email'])) {
            $matchConds[] = 'email = ?';
            $matchParams[] = $uRow['email'];
        }
        if (!empty($uRow['phone'])) {
            $matchConds[] = 'phone = ?';
            $matchParams[] = $uRow['phone'];
        }
        if ($matchConds) {
            $pStmt = $pdo->prepare('SELECT id FROM patients WHERE ' . implode(' OR ', $matchConds));
            $pStmt->execute($matchParams);
            foreach ($pStmt->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $patientIds[] = (int)$pid;
            }
        }
    } catch (Exception $e) {
        // optional identity match
    }
    [$patientIds, $patientIdPh] = patientIdPlaceholders($patientIds);
    
    $stmt = $pdo->prepare("
        SELECT c.*,
               q.blood_pressure, q.heart_rate, q.temperature, q.oxygen_saturation, q.height, q.weight,
               q.treatment_type AS queue_treatment_type, q.queue_number, q.completed_at, q.teeth_numbers,
               q.appointment_id AS queue_appointment_id, q.procedure_notes, q.notes AS queue_notes,
               s.name AS service_name,
               u.first_name AS dentist_first_name, u.last_name AS dentist_last_name
        FROM consultations c
        LEFT JOIN queue q ON q.id = c.queue_id
        LEFT JOIN services s ON s.id = q.service_id
        LEFT JOIN users u ON u.id = c.dentist_id
        WHERE c.patient_id IN ($patientIdPh)
           OR c.queue_id IN (SELECT q2.id FROM queue q2 WHERE CAST(q2.patient_id AS UNSIGNED) IN ($patientIdPh))
        ORDER BY c.created_at DESC
        LIMIT 80
    ");
    $stmt->execute(array_merge($patientIds, $patientIds));
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($consultations as &$visit) {
        $visit['teeth_data'] = patientDecodeJson($visit['teeth_data'] ?? null);
        $visit['prescription_data'] = patientDecodeJson($visit['prescription_data'] ?? null);
        $visit['findings_count'] = count($visit['teeth_data']);
        $visit['dentist_name'] = trim(($visit['dentist_first_name'] ?? '') . ' ' . ($visit['dentist_last_name'] ?? ''));
        $visit['service_label'] = $visit['service_name'] ?: ($visit['queue_treatment_type'] ?: 'Consultation');
    }
    unset($visit);
} catch (Exception $e) {
    $consultations = [];
}
if (!$patient) {
    $patient = [];
}
[$patientIds, $patientIdPh] = patientIdPlaceholders($patientIds ?? []);

$visits = [];
$usedQueueIds = [];
$usedAppointmentIds = [];
$seenConsultIds = [];

foreach ($consultations as $c) {
    $cid = (int)($c['id'] ?? 0);
    if ($cid && isset($seenConsultIds[$cid])) {
        continue;
    }
    if ($cid) {
        $seenConsultIds[$cid] = true;
    }
    $qid = (int)($c['queue_id'] ?? 0);
    $aid = (int)($c['queue_appointment_id'] ?? 0);
    if ($qid) {
        $usedQueueIds[$qid] = true;
    }
    if ($aid) {
        $usedAppointmentIds[$aid] = true;
    }
    $visitDate = $c['completed_at'] ?: ($c['created_at'] ?? '');
    $visits[] = array_merge($c, [
        'visit_key' => 'c-' . (int)$c['id'],
        'has_chart' => true,
        'consultation_id' => (int)$c['id'],
        'queue_id' => $qid ?: null,
        'appointment_id' => $aid ?: null,
        'service_label' => $c['service_label'] ?? 'Consultation',
        'visit_date' => $visitDate,
        'teeth_numbers' => $c['teeth_numbers'] ?? '',
        'summary_notes' => trim((string)($c['procedure_notes'] ?? $c['queue_notes'] ?? '')),
    ]);
}

try {
    $stmt = $pdo->prepare("
        SELECT q.id, q.treatment_type, q.teeth_numbers, q.completed_at, q.created_at,
               q.service_id, q.appointment_id, q.procedure_notes, q.notes AS queue_notes,
               q.dentist_id, q.doctor_id,
               q.blood_pressure, q.heart_rate, q.temperature, q.oxygen_saturation, q.height, q.weight,
               s.name AS service_name,
               CONCAT(u.first_name, ' ', u.last_name) AS dentist_name
        FROM queue q
        LEFT JOIN services s ON s.id = q.service_id
        LEFT JOIN users u ON u.id = COALESCE(q.dentist_id, q.doctor_id)
        WHERE CAST(q.patient_id AS UNSIGNED) IN ($patientIdPh)
          AND LOWER(COALESCE(q.status, '')) = 'completed'
          AND COALESCE(q.is_archived, 0) = 0
        ORDER BY COALESCE(q.completed_at, q.created_at) DESC
        LIMIT 80
    ");
    $stmt->execute($patientIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $qRow) {
        $qid = (int)$qRow['id'];
        if (isset($usedQueueIds[$qid])) {
            continue;
        }
        $usedQueueIds[$qid] = true;
        $aid = (int)($qRow['appointment_id'] ?? 0);
        if ($aid) {
            $usedAppointmentIds[$aid] = true;
        }
        $visitDate = $qRow['completed_at'] ?: ($qRow['created_at'] ?? '');
        $visits[] = [
            'visit_key' => 'q-' . $qid,
            'has_chart' => false,
            'consultation_id' => null,
            'queue_id' => $qid,
            'appointment_id' => $aid ?: null,
            'service_label' => $qRow['service_name'] ?: ($qRow['treatment_type'] ?: 'Dental service'),
            'visit_date' => $visitDate,
            'created_at' => $visitDate,
            'dentist_name' => trim((string)($qRow['dentist_name'] ?? '')),
            'teeth_numbers' => $qRow['teeth_numbers'] ?? '',
            'summary_notes' => trim((string)($qRow['procedure_notes'] ?: $qRow['queue_notes'] ?? '')),
            'teeth_data' => [],
            'prescription_data' => [],
            'blood_pressure' => $qRow['blood_pressure'] ?? null,
            'heart_rate' => $qRow['heart_rate'] ?? null,
            'temperature' => $qRow['temperature'] ?? null,
            'oxygen_saturation' => $qRow['oxygen_saturation'] ?? null,
            'height' => $qRow['height'] ?? null,
            'weight' => $qRow['weight'] ?? null,
            'consultation_notes' => trim((string)($qRow['procedure_notes'] ?: $qRow['queue_notes'] ?? '')),
        ];
    }
} catch (Exception $e) {
    // queue table/columns may differ; keep consultation visits
}

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.service_id, a.notes,
               s.name AS service_name,
               CONCAT(u.first_name, ' ', u.last_name) AS dentist_name
        FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id
        LEFT JOIN users u ON u.id = a.dentist_id
        WHERE a.patient_id IN ($patientIdPh)
          AND LOWER(COALESCE(a.status, '')) = 'completed'
          AND COALESCE(a.is_archived, 0) = 0
          AND a.deleted_at IS NULL
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 80
    ");
    $stmt->execute($patientIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $apt) {
        $aid = (int)$apt['id'];
        if (isset($usedAppointmentIds[$aid])) {
            continue;
        }
        $usedAppointmentIds[$aid] = true;
        $visitDate = trim(($apt['appointment_date'] ?? '') . ' ' . ($apt['appointment_time'] ?? ''));
        $visits[] = [
            'visit_key' => 'a-' . $aid,
            'has_chart' => false,
            'consultation_id' => null,
            'queue_id' => null,
            'appointment_id' => $aid,
            'service_label' => $apt['service_name'] ?: 'Dental service',
            'visit_date' => $visitDate,
            'created_at' => $visitDate,
            'dentist_name' => trim((string)($apt['dentist_name'] ?? '')),
            'teeth_numbers' => '',
            'summary_notes' => trim((string)($apt['notes'] ?? '')),
            'teeth_data' => [],
            'prescription_data' => [],
            'consultation_notes' => trim((string)($apt['notes'] ?? '')),
        ];
    }
} catch (Exception $e) {
    // appointments fallback is optional
}

usort($visits, static function ($a, $b) {
    return strtotime((string)($b['visit_date'] ?? '')) <=> strtotime((string)($a['visit_date'] ?? ''));
});

try {
    $orphanQueueIds = [];
    foreach ($visits as $visitRow) {
        if (!empty($visitRow['queue_id'])) {
            $orphanQueueIds[] = (int)$visitRow['queue_id'];
        }
    }
    $orphanQueueIds = array_values(array_unique(array_filter($orphanQueueIds)));
    if ($orphanQueueIds) {
        $placeholders = implode(',', array_fill(0, count($orphanQueueIds), '?'));
        $stmt = $pdo->prepare("
            SELECT c.id, c.queue_id, c.teeth_data, c.consultation_notes, c.dental_medical_history_notes,
                   c.prescription_data, c.periodontal_screening,
                   c.occlusion_class_molar, c.occlusion_overjet, c.occlusion_overbite,
                   c.occlusion_midline_deviation, c.occlusion_crossbite,
                   c.appliances_orthodontic, c.appliances_skyplate, c.appliances_others, c.appliances_others_text,
                   c.tmd_clenching, c.tmd_clicking, c.tmd_trismus, c.tmd_muscle_spasm,
                   c.primary_icd10, c.primary_icd10_desc, c.secondary_icd10, c.secondary_icd10_desc,
                   c.diagnosis_narrative
            FROM consultations c
            WHERE c.queue_id IN ($placeholders)
        ");
        $stmt->execute($orphanQueueIds);
        $extraByQueue = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $extra) {
            $extra['teeth_data'] = patientDecodeJson($extra['teeth_data'] ?? null);
            $extra['prescription_data'] = patientDecodeJson($extra['prescription_data'] ?? null);
            $extraByQueue[(int)$extra['queue_id']] = $extra;
        }
        foreach ($visits as &$visitRow) {
            $qid = (int)($visitRow['queue_id'] ?? 0);
            if ($qid && isset($extraByQueue[$qid])) {
                $extra = $extraByQueue[$qid];
                $visitRow['has_chart'] = true;
                if (empty($visitRow['consultation_id'])) {
                    $visitRow['consultation_id'] = (int)$extra['id'];
                    $visitRow['id'] = (int)$extra['id'];
                }
                foreach ([
                    'teeth_data', 'prescription_data', 'consultation_notes', 'dental_medical_history_notes',
                    'periodontal_screening', 'occlusion_class_molar', 'occlusion_overjet', 'occlusion_overbite',
                    'occlusion_midline_deviation', 'occlusion_crossbite', 'appliances_orthodontic',
                    'appliances_skyplate', 'appliances_others', 'appliances_others_text',
                    'tmd_clenching', 'tmd_clicking', 'tmd_trismus', 'tmd_muscle_spasm',
                    'primary_icd10', 'primary_icd10_desc', 'secondary_icd10', 'secondary_icd10_desc',
                    'diagnosis_narrative',
                ] as $chartKey) {
                    $current = $visitRow[$chartKey] ?? null;
                    $incoming = $extra[$chartKey] ?? null;
                    $currentEmpty = $current === null || $current === '' || $current === [] || $current === '[]';
                    if ($currentEmpty && $incoming !== null && $incoming !== '') {
                        $visitRow[$chartKey] = $incoming;
                    }
                }
                if (empty($visitRow['consultation_notes']) && !empty($visitRow['summary_notes'])) {
                    $visitRow['consultation_notes'] = $visitRow['summary_notes'];
                }
            }
        }
        unset($visitRow);
    }
} catch (Exception $e) {
    // keep visits without extra chart attach
}

try {
    $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id IN ($patientIdPh) ORDER BY id DESC LIMIT 1");
    $stmt->execute($patientIds);
    $medical = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ($mhFlagLabels as $flagKey => $flagLabel) {
        if (!empty($medical[$flagKey]) && (string)$medical[$flagKey] !== '0') {
            $positiveFlags[] = $flagLabel;
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT previous_dentist, last_visit_date, reason_last_visit,
               previous_treatments, current_complaints, created_at
        FROM dental_history
        WHERE patient_id IN ($patientIdPh)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute($patientIds);
    $dentalHistory = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->prepare("
        SELECT pr.*, CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.patient_id IN ($patientIdPh)
        ORDER BY pr.issue_date DESC, pr.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($patientIds);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medical = $medical ?: [];
    $dentalHistory = $dentalHistory ?: [];
    $prescriptions = $prescriptions ?: [];
}

try {
    $stmt = $pdo->prepare("
        SELECT tp.*, tp.plan_name as treatment_name, tp.overall_status as status,
               s.name as service_name
        FROM treatment_plans tp
        LEFT JOIN services s ON (tp.plan_name REGEXP '^[0-9]+$' AND s.id = tp.plan_name)
        WHERE tp.patient_id IN ($patientIdPh)
        ORDER BY tp.created_at DESC
    ");
    $stmt->execute($patientIds);
    $treatmentPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($treatmentPlans as &$plan) {
        if (!empty($plan['service_name'])) {
            $plan['treatment_name'] = $plan['service_name'];
        }
        $plan['status'] = strtolower(str_replace('-', '_', (string)($plan['status'] ?? '')));
        $plan['sessions'] = patientDecodeJson($plan['sessions_data'] ?? null);
    }
    unset($plan);
} catch (Exception $e) {
    $treatmentPlans = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT id, consultation_id, patient_id, file_path, original_filename, mime_type, file_size, created_at
        FROM patient_xrays
        WHERE patient_id IN ($patientIdPh)
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute($patientIds);
    $xraysUnassigned = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $xray) {
        $cid = (int)($xray['consultation_id'] ?? 0);
        if ($cid > 0) {
            if (!isset($xraysByConsultation[$cid])) {
                $xraysByConsultation[$cid] = [];
            }
            $xraysByConsultation[$cid][] = $xray;
        } else {
            $xraysUnassigned[] = $xray;
        }
    }
} catch (Exception $e) {
    $xraysByConsultation = [];
    $xraysUnassigned = [];
}

try {
    $xrayConsultIds = [];
    foreach ($visits as $visitRow) {
        if (!empty($visitRow['consultation_id'])) {
            $xrayConsultIds[] = (int)$visitRow['consultation_id'];
        }
    }
    $xrayConsultIds = array_values(array_unique(array_filter($xrayConsultIds)));
    if ($xrayConsultIds) {
        $ph = implode(',', array_fill(0, count($xrayConsultIds), '?'));
        $stmt = $pdo->prepare("
            SELECT id, consultation_id, patient_id, file_path, original_filename, mime_type, file_size, created_at
            FROM patient_xrays
            WHERE consultation_id IN ($ph)
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute($xrayConsultIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $xray) {
            $cid = (int)$xray['consultation_id'];
            if (!isset($xraysByConsultation[$cid])) {
                $xraysByConsultation[$cid] = [];
            }
            $exists = false;
            foreach ($xraysByConsultation[$cid] as $existing) {
                if ((int)$existing['id'] === (int)$xray['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $xraysByConsultation[$cid][] = $xray;
            }
        }
    }
} catch (Exception $e) {
    // keep x-rays already loaded by patient_id
}

$recordPayload = [
    'patient' => [
        'id' => (int)($patient['id'] ?? $patient_id ?? 0),
        'first_name' => $patient['first_name'] ?? '',
        'middle_name' => $patient['middle_name'] ?? '',
        'last_name' => $patient['last_name'] ?? '',
        'gender' => $patient['gender'] ?? '',
        'date_of_birth' => $patient['date_of_birth'] ?? '',
        'age' => $patient['age'] ?? '',
        'phone' => $patient['phone'] ?? '',
        'email' => $patient['email'] ?? '',
        'emergency_contact_name' => $patient['emergency_contact_name'] ?? '',
        'emergency_contact_phone' => $patient['emergency_contact_phone'] ?? '',
        'emergency_contact_relationship' => $patient['emergency_contact_relationship'] ?? '',
        'street_address' => $patient['street_address'] ?? ($patient['address'] ?? ''),
    ],
    'medical_history' => $medical,
    'intake_dental_history' => $dentalHistory,
    'consultations' => $consultations,
    'visits' => $visits,
    'prescriptions' => $prescriptions,
    'treatment_plans' => $treatmentPlans,
    'xrays' => $xraysByConsultation,
    'xrays_unassigned' => $xraysUnassigned,
    'positive_flags' => $positiveFlags,
];

$pageTitle = 'Medical Records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; }
        .sidebar { width: 220px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 32px 0; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; color: white; box-shadow: 2px 0 8px rgba(0,0,0,0.1); z-index: 40; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 24px; margin-bottom: 40px; text-decoration: none; color: white; }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-brand span { font-size: 16px; font-weight: 700; white-space: nowrap; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 8px; padding: 0 12px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 14px; font-weight: 500; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .menu-item i { width: 18px; text-align: center; }
        .main-wrapper { flex: 1; margin-left: 220px; display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
        .user-role { font-size: 12px; color: #6b7280; }
        .dropdown-toggle { font-size: 16px; color: #6b7280; cursor: pointer; }
        .user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 1000; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .dropdown-item:hover { background: #f3f4f6; }
        .dropdown-item.logout { color: #dc3545; border-top: 1px solid #e5e7eb; }
        .dashboard-main { flex: 1; padding: 24px 32px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 14px; }
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .tabs { display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .tab-btn { padding: 12px 24px; background: none; border: none; font-size: 14px; font-weight: 500; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn:hover { color: #1f2937; }
        .tab-btn.active { color: #005daa; border-bottom-color: #005daa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .record-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .record-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .record-type { font-size: 16px; font-weight: 600; color: #1a1a2e; }
        .record-date { font-size: 13px; color: #6b7280; }
        .record-content { font-size: 14px; color: #374151; line-height: 1.6; }
        .record-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; display: flex; gap: 16px; font-size: 13px; color: #6b7280; }
        .record-footer span { display: flex; align-items: center; gap: 6px; }
        .prescription-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .prescription-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .rx-number { font-size: 12px; color: #6b7280; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; }
        .medicine-list { list-style: none; }
        .medicine-item { padding: 12px 0; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; }
        .medicine-item:last-child { border-bottom: none; }
        .medicine-name { font-weight: 600; color: #1a1a2e; }
        .medicine-dosage { font-size: 13px; color: #6b7280; }
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; color: #d1d5db; }
        .empty-state h3 { font-size: 18px; color: #374151; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .info-card { background: #f9fafb; padding: 16px; border-radius: 8px; }
        .info-label { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 16px; font-weight: 600; color: #1a1a2e; word-break: break-word; }
        .section-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .section-head .section-title { margin-bottom: 0; }
        .btn-full { display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-full:hover { background: #1d4ed8; }
        .btn-full.outline { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .btn-full.outline:hover { background: #dbeafe; }
        .flag-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .flag-chip { font-size: 12px; font-weight: 600; background: #ecfdf5; color: #047857; border: 1px solid #bbf7d0; border-radius: 999px; padding: 4px 10px; }
        .visit-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 18px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .plan-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: capitalize; background: #dbeafe; color: #1d4ed8; }
        .plan-status.completed { background: #dcfce7; color: #15803d; }
        .plan-status.cancelled, .plan-status.discontinued { background: #fee2e2; color: #b91c1c; }
        .fullscreen-modal-overlay, .odontogram-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 2000; align-items: stretch; justify-content: center; }
        .fullscreen-modal-overlay.active, .odontogram-modal-overlay.active { display: flex; }
        .fullscreen-modal-content, .odontogram-modal-box { background: #fff; width: min(920px, 96vw); max-height: 92vh; margin: auto; border-radius: 14px; display: flex; flex-direction: column; overflow: hidden; }
        .fullscreen-modal-header, .odontogram-modal-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }
        .fullscreen-modal-body, #odontogramModalContent { padding: 20px; overflow-y: auto; }
        .fullscreen-modal-close, .odontogram-modal-close { background: none; border: none; font-size: 1.6rem; color: #6b7280; cursor: pointer; line-height: 1; }
        .odx-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; }
        .odx-section h5 { font-size: 0.78rem; font-weight: 700; color: #0f172a; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.03em; }
        .odx-vitals-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 8px; }
        .odx-vital { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; }
        .odx-vital-label { font-size: 0.62rem; color: #64748b; text-transform: uppercase; font-weight: 700; }
        .odx-vital-value { font-size: 0.88rem; color: #0f172a; font-weight: 600; margin-top: 1px; }
        .odx-subrow { display: flex; flex-wrap: wrap; gap: 14px; }
        .odx-subblock { flex: 1; min-width: 200px; }
        .odx-subblock-title { font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
        .odx-check-row { display: flex; flex-wrap: wrap; gap: 6px; }
        .odx-chip { font-size: 0.72rem; background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 9px; color: #94a3b8; }
        .odx-chip.on { background: #ecfdf5; border-color: #bbf7d0; color: #047857; font-weight: 600; }
        .odx-text { font-size: 0.85rem; color: #374151; line-height: 1.55; white-space: pre-wrap; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
        .odx-empty { color: #94a3b8; font-size: 0.8rem; font-style: italic; }
        .odx-rx-item, .odx-tx-plan { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; margin-bottom: 8px; }
        .odx-rx-name, .odx-tx-name { font-weight: 700; color: #0f172a; font-size: 0.88rem; }
        .odx-rx-meta, .odx-tx-meta { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
        .odx-rx-instr, .odx-tx-session { font-size: 0.78rem; color: #475569; margin-top: 4px; }
        .odx-dx-chip { display: inline-flex; gap: 6px; background: white; border: 1px solid #bae6fd; color: #0369a1; font-size: 0.78rem; font-weight: 600; padding: 4px 12px; border-radius: 999px; margin: 0 6px 6px 0; }
        .odx-tx-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .odx-tx-status { font-size: 0.68rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; text-transform: capitalize; }
        .odx-tx-status.completed { background: #dcfce7; color: #15803d; }
        .odx-xray-row { display: flex; align-items: center; justify-content: space-between; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 12px; margin-bottom: 6px; }
        .odx-xray-name { font-size: 0.8rem; color: #334155; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 10px; }
        .odx-eye-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }
        .odontogram-chart-section-label { font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 8px 0; }
        .odontogram-quadrant-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
        .odontogram-quadrant-label { font-size: 0.65rem; color: #94a3b8; text-align: center; margin-bottom: 4px; }
        .odontogram-tooth-row { display: flex; gap: 1px; }
        .odontogram-tooth-col { width: 46px; text-align: center; }
        .odontogram-tooth-num-label { font-size: 0.62rem; color: #64748b; font-weight: 700; }
        .odontogram-tooth-findings-below { display: flex; flex-wrap: wrap; justify-content: center; gap: 1px; min-height: 12px; }
        .odontogram-tooth-finding-badge { font-size: 0.5rem; font-weight: 700; padding: 0 3px; border-radius: 2px; }
        .odontogram-tooth-finding-badge.pathology { background: #fecaca; color: #991b1b; }
        .odontogram-tooth-finding-badge.treatment { background: #bae6fd; color: #0369a1; }
        .odontogram-finding-chip { display: inline-block; font-size: 0.75rem; background: #f1f5f9; border-radius: 6px; padding: 3px 8px; margin: 0 4px 4px 0; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; padding: 24px 0; }
            .sidebar-brand span, .menu-item span { display: none; }
            .sidebar-brand { justify-content: center; padding: 0; }
            .menu-item { justify-content: center; padding: 14px; }
            .main-wrapper { margin-left: 60px; }
            .dashboard-header { padding: 12px 16px; }
            .user-details { display: none; }
            .dashboard-main { padding: 16px; }
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
</head>
<body>
    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
        </div>
        <nav class="sidebar-menu">
            <a href="patient-dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="inquiry_consultation.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Inquiry / Consultation</span>
            </a>
            <a href="appointments.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>My appointments</span>
            </a>
            <a href="medical_records.php" class="menu-item active">
                <i class="fas fa-file-medical"></i>
                <span>Medical records</span>
            </a>
            <a href="billing.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Account settings</span>
            </a>
        </nav>
    </aside>
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div>
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Medical Records</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Your health summary and visit history for reference</p>
                </div>
            </div>
            <div class="header-right">
                <?php include '../../includes/patient_tour_widget.php'; ?>
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            $first = ($patient['first_name'] ?? '') ?: ($_SESSION['user_name'] ?? $_SESSION['display_name'] ?? '');
                            $last = $patient['last_name'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__, 2) . '/' . $profPic)):
                        ?>
                            <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        <?php 
                            else:
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                        ?>
                            <?php echo htmlspecialchars($initials ?: 'P'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name">
                            <?php 
                                $displayName = trim($first ?: 'Patient');
                                echo htmlspecialchars($displayName);
                            ?>
                        </div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="../../logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="section">
                <h3 class="section-title">Dental History</h3>
                <div class="tabs">
                    <button class="tab-btn active" data-tab="records">Visits (<?php echo count($visits); ?>)</button>
                    <button class="tab-btn" data-tab="prescriptions">Prescriptions (<?php echo count($prescriptions); ?>)</button>
                    <button class="tab-btn" data-tab="plans">Treatment Plans (<?php echo count($treatmentPlans); ?>)</button>
                </div>

                <div id="records" class="tab-content active">
                    <?php if (!empty($visits)): ?>
                        <?php foreach ($visits as $visit): ?>
                            <?php
                                $visitDate = patientFormatDate($visit['visit_date'] ?? $visit['created_at'] ?? '');
                                $dentistLabel = trim((string)($visit['dentist_name'] ?? ''));
                            ?>
                            <div class="visit-card">
                                <div>
                                    <div class="record-type"><?php echo htmlspecialchars($visit['service_label'] ?: 'Dental service'); ?></div>
                                    <div class="record-date"><?php echo htmlspecialchars($visitDate ?: 'Date not recorded'); ?></div>
                                    <?php if ($dentistLabel !== ''): ?>
                                        <div class="record-date" style="margin-top:4px;"><?php echo htmlspecialchars('Dr. ' . $dentistLabel); ?></div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-full outline" onclick="openVisitFullView('<?php echo htmlspecialchars($visit['visit_key'], ENT_QUOTES); ?>')">
                                    View full record
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical-alt"></i>
                            <h3>No visits yet</h3>
                            <p>Completed services will appear here after your visits.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="prescriptions" class="tab-content">
                    <?php if (!empty($prescriptions)): ?>
                        <?php foreach ($prescriptions as $rx): ?>
                            <div class="prescription-card">
                                <div class="prescription-header">
                                    <div>
                                        <div class="record-type"><?php echo htmlspecialchars($rx['diagnosis'] ?: 'Prescription'); ?></div>
                                        <div class="record-date"><?php echo htmlspecialchars(patientFormatDate($rx['issue_date'] ?? $rx['created_at'] ?? '') ?: ''); ?></div>
                                    </div>
                                    <?php if (!empty($rx['doctor_name'])): ?>
                                        <div class="rx-number"><?php echo htmlspecialchars('Dr. ' . trim($rx['doctor_name'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="record-content">
                                    <?php echo nl2br(htmlspecialchars($rx['medications'] ?: 'No medications listed.')); ?>
                                </div>
                                <?php if (!empty($rx['instructions'])): ?>
                                    <div class="record-footer">
                                        <span><?php echo nl2br(htmlspecialchars($rx['instructions'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-pills"></i>
                            <h3>No Prescriptions</h3>
                            <p>Your prescriptions will appear here after your visits.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="plans" class="tab-content">
                    <?php if (!empty($treatmentPlans)): ?>
                        <?php foreach ($treatmentPlans as $plan): ?>
                            <div class="record-card">
                                <div class="record-header">
                                    <div class="record-type"><?php echo htmlspecialchars($plan['treatment_name'] ?: 'Treatment Plan'); ?></div>
                                    <span class="plan-status <?php echo htmlspecialchars($plan['status'] ?: ''); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $plan['status'] ?: 'active')); ?></span>
                                </div>
                                <?php if (!empty($plan['teeth_numbers'])): ?>
                                    <div class="record-content">Teeth: <?php echo htmlspecialchars($plan['teeth_numbers']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($plan['sessions'])): ?>
                                    <?php foreach ($plan['sessions'] as $idx => $session): ?>
                                        <div class="record-date" style="margin-top:6px;">
                                            Session <?php echo (int)$idx + 1; ?>:
                                            <?php echo htmlspecialchars(trim(($session['date'] ?? '') . ' ' . ($session['time'] ?? '')) ?: 'Not scheduled'); ?>
                                            <?php echo !empty($session['treatment']) ? ' — ' . htmlspecialchars($session['treatment']) : ''; ?>
                                            <?php echo !empty($session['status']) ? ' (' . htmlspecialchars($session['status']) . ')' : ''; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($plan['notes'])): ?>
                                    <div class="record-content" style="margin-top:8px;"><?php echo nl2br(htmlspecialchars($plan['notes'])); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No Treatment Plans</h3>
                            <p>Plans from your dentist will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="odontogramModalOverlay" class="odontogram-modal-overlay" onclick="if(event.target===this)closeVisitFullView()">
        <div class="odontogram-modal-box">
            <div class="odontogram-modal-header">
                <h3 id="odontogramModalTitle" style="margin:0;font-size:1.05rem;">Visit details</h3>
                <button type="button" class="odontogram-modal-close" onclick="closeVisitFullView()">&times;</button>
            </div>
            <div id="odontogramModalContent"></div>
        </div>
    </div>

    <div id="odXrayViewerOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);z-index:2100;align-items:center;justify-content:center;" onclick="if(event.target===this)closeXrayViewer()">
        <div style="background:white;border-radius:14px;max-width:640px;width:92%;max-height:88vh;padding:16px;display:flex;flex-direction:column;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <div id="odXrayViewerTitle" style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-right:12px;">X-Ray</div>
                <button type="button" onclick="closeXrayViewer()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">&times;</button>
            </div>
            <div style="overflow:auto;text-align:center;background:#f8fafc;border-radius:8px;padding:8px;">
                <img id="odXrayViewerImg" src="" alt="X-Ray" style="max-width:100%;max-height:60vh;">
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                <a id="odXrayDownloadBtn" href="#" download style="display:inline-flex;align-items:center;gap:6px;background:#0ea5e9;color:white;font-size:0.82rem;font-weight:600;padding:8px 16px;border-radius:8px;text-decoration:none;">Download</a>
            </div>
        </div>
    </div>

    <script>
        window.PATIENT_RECORD = <?php
            $payloadJson = json_encode($recordPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            echo ($payloadJson === false) ? '{}' : $payloadJson;
        ?>;
    </script>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        function escapeHtml(str) {
            return String(str == null ? '' : str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function dash(v) { var s = String(v == null ? '' : v).trim(); return s || '—'; }
        function formatVisitDate(value) {
            if (!value) return 'Date not recorded';
            var d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return String(value);
            return d.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }) + ' ' + d.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
        }
        function odxSection(title, inner) {
            return '<div class="odx-section"><h5>' + title + '</h5>' + inner + '</div>';
        }
        function odxVital(label, value, suffix) {
            var v = String(value == null ? '' : value).trim();
            return '<div class="odx-vital"><div class="odx-vital-label">' + label + '</div><div class="odx-vital-value">' + (v ? escapeHtml(v) + (suffix || '') : '&mdash;') + '</div></div>';
        }
        function odxChip(label, val) {
            var on = !!parseInt(val, 10);
            return '<span class="odx-chip' + (on ? ' on' : '') + '">' + (on ? '✓ ' : '') + label + '</span>';
        }

        var odontogramTeethFindings = [];
        var odAdultUpperRight = [18,17,16,15,14,13,12,11];
        var odAdultUpperLeft = [21,22,23,24,25,26,27,28];
        var odAdultLowerRight = [48,47,46,45,44,43,42,41];
        var odAdultLowerLeft = [31,32,33,34,35,36,37,38];
        var odPedUpperRight = [55,54,53,52,51];
        var odPedUpperLeft = [61,62,63,64,65];
        var odPedLowerRight = [85,84,83,82,81];
        var odPedLowerLeft = [71,72,73,74,75];
        var OD_SVG_SIZE = 44, OD_CX = 22, OD_CY = 22, OD_R = 10;

        function odGetSurfaceColor(toothNum, surface) {
            var f = odontogramTeethFindings.find(function(x) { return Number(x.tooth) === Number(toothNum) && x.surface === surface; });
            if (!f) return null;
            if (f.type === 'pathology') return '#ef4444';
            if (f.type === 'treatment') return '#7dd3fc';
            return null;
        }
        function odGetWholeToothColor(toothNum) {
            var f = odontogramTeethFindings.find(function(x) { return Number(x.tooth) === Number(toothNum) && !x.surface; });
            if (!f) return null;
            return '#ef4444';
        }
        function odCreateToothSVG(toothNum) {
            var S = OD_SVG_SIZE, C = OD_CX, CY = OD_CY;
            var wholeFill = odGetWholeToothColor(toothNum);
            var svg = '<svg width="'+S+'" height="'+S+'" viewBox="0 0 '+S+' '+S+'" xmlns="http://www.w3.org/2000/svg">';
            svg += '<rect x="1" y="1" width="'+(S-2)+'" height="'+(S-2)+'" fill="'+(wholeFill || 'white')+'" stroke="#94a3b8" stroke-width="1.5" rx="2"/>';
            var bFill = odGetSurfaceColor(toothNum,'B') || wholeFill || 'white';
            var pFill = odGetSurfaceColor(toothNum,'P') || wholeFill || 'white';
            var leftFill = odGetSurfaceColor(toothNum,'D') || wholeFill || 'white';
            var rightFill = odGetSurfaceColor(toothNum,'M') || wholeFill || 'white';
            var oFill = odGetSurfaceColor(toothNum,'O') || wholeFill || 'white';
            svg += '<polygon points="1,1 '+C+','+CY+' '+(S-1)+',1" fill="'+bFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
            svg += '<polygon points="1,'+(S-1)+' '+C+','+CY+' '+(S-1)+','+(S-1)+'" fill="'+pFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
            svg += '<polygon points="1,1 '+C+','+CY+' 1,'+(S-1)+'" fill="'+leftFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
            svg += '<polygon points="'+(S-1)+',1 '+C+','+CY+' '+(S-1)+','+(S-1)+'" fill="'+rightFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
            svg += '<circle cx="'+C+'" cy="'+CY+'" r="'+OD_R+'" fill="'+oFill+'" stroke="#94a3b8" stroke-width="1"/>';
            svg += '</svg>';
            return svg;
        }
        function odRenderToothColumn(toothNum) {
            var findings = odontogramTeethFindings.filter(function(f) { return Number(f.tooth) === Number(toothNum); });
            var badgesHtml = '';
            findings.forEach(function(f) {
                var txt = f.surface ? f.surface+':'+f.code : f.code;
                badgesHtml += '<span class="odontogram-tooth-finding-badge ' + (f.type || '') + '">'+escapeHtml(txt)+'</span>';
            });
            return '<div class="odontogram-tooth-col"><div class="odontogram-tooth-num-label">'+toothNum+'</div><div>'+odCreateToothSVG(toothNum)+'</div><div class="odontogram-tooth-findings-below">'+badgesHtml+'</div></div>';
        }
        function odRenderQuadrant(containerId, teeth, label) {
            var container = document.getElementById(containerId);
            if (!container) return;
            var html = '<div><div class="odontogram-quadrant-label">'+label+'</div><div class="odontogram-tooth-row">';
            teeth.forEach(function(t) { html += odRenderToothColumn(t); });
            html += '</div></div>';
            container.innerHTML = html;
        }
        function odRenderToothChart() {
            var upperRow = document.getElementById('odAdultTeethChart');
            var lowerRow = document.getElementById('odPedTeethChart');
            if (!upperRow || !lowerRow) return;
            upperRow.innerHTML = '<div class="odontogram-quadrant-row"><div id="odAdultUR"></div><div id="odAdultUL"></div></div><div class="odontogram-quadrant-row"><div id="odAdultLR"></div><div id="odAdultLL"></div></div>';
            lowerRow.innerHTML = '<div class="odontogram-quadrant-row"><div id="odPedUR"></div><div id="odPedUL"></div></div><div class="odontogram-quadrant-row"><div id="odPedLR"></div><div id="odPedLL"></div></div>';
            odRenderQuadrant('odAdultUR', odAdultUpperRight, 'Upper Right');
            odRenderQuadrant('odAdultUL', odAdultUpperLeft, 'Upper Left');
            odRenderQuadrant('odAdultLR', odAdultLowerRight, 'Lower Right');
            odRenderQuadrant('odAdultLL', odAdultLowerLeft, 'Lower Left');
            odRenderQuadrant('odPedUR', odPedUpperRight, 'Upper Right');
            odRenderQuadrant('odPedUL', odPedUpperLeft, 'Upper Left');
            odRenderQuadrant('odPedLR', odPedLowerRight, 'Lower Right');
            odRenderQuadrant('odPedLL', odPedLowerLeft, 'Lower Left');
        }
        function odRenderFindings() {
            var container = document.getElementById('odFindingsChips');
            if (!container) return;
            if (!odontogramTeethFindings.length) {
                container.innerHTML = '<span class="odx-empty">No findings recorded.</span>';
                return;
            }
            container.innerHTML = odontogramTeethFindings.map(function(f) {
                var label = '#' + f.tooth;
                if (f.surface) label += ' [' + f.surface + ']';
                label += ' - ' + (f.code || '') + ' ' + (f.label || '');
                return '<span class="odontogram-finding-chip">' + escapeHtml(label) + '</span>';
            }).join('');
        }

        function renderPlansHtml(plans) {
            if (!plans || !plans.length) return '<span class="odx-empty">No treatment plans.</span>';
            return plans.map(function(plan) {
                var status = (plan.status || plan.overall_status || 'active');
                var html = '<div class="odx-tx-plan"><div class="odx-tx-head"><span class="odx-tx-name">' + escapeHtml(plan.treatment_name || plan.plan_name || 'Treatment Plan') + '</span><span class="odx-tx-status ' + escapeHtml(String(status).toLowerCase().replace(/\s+/g,'-')) + '">' + escapeHtml(String(status).replace(/_/g,' ')) + '</span></div>';
                if (plan.teeth_numbers) html += '<div class="odx-tx-meta">Teeth: ' + escapeHtml(plan.teeth_numbers) + '</div>';
                var sessions = plan.sessions;
                if (!sessions && plan.sessions_data) {
                    try { sessions = typeof plan.sessions_data === 'string' ? JSON.parse(plan.sessions_data) : plan.sessions_data; } catch (e) { sessions = []; }
                }
                (sessions || []).forEach(function(s, idx) {
                    html += '<div class="odx-tx-session">Session ' + (idx + 1) + ': ' + escapeHtml(trimJoin([s.date, s.time], ' ') || 'Not scheduled') + (s.treatment ? ' — ' + escapeHtml(s.treatment) : '') + (s.status ? ' (' + escapeHtml(s.status) + ')' : '') + '</div>';
                });
                if (plan.notes) html += '<div class="odx-tx-session">' + escapeHtml(plan.notes) + '</div>';
                return html + '</div>';
            }).join('');
        }
        function trimJoin(parts, sep) {
            return parts.filter(function(p) { return p && String(p).trim(); }).join(sep);
        }
        function renderXraysHtml(session) {
            var rec = window.PATIENT_RECORD || {};
            var consultId = session.consultation_id || session.id;
            var xrays = (rec.xrays || {})[consultId] || (rec.xrays || {})[String(consultId)] || [];
            if (!xrays.length) {
                xrays = rec.xrays_unassigned || [];
            }
            window._odXrayList = xrays;
            if (!xrays.length) return '<span class="odx-empty">No x-ray images uploaded for this visit.</span>';
            return xrays.map(function(x, idx) {
                var name = x.original_filename || (x.file_path || '').split('/').pop();
                return '<div class="odx-xray-row"><span class="odx-xray-name">' + escapeHtml(name) + '</span><button type="button" class="odx-eye-btn" onclick="openXrayViewer(' + idx + ')"><i class="fas fa-eye"></i></button></div>';
            }).join('');
        }
        function renderRxHtml(items, session) {
            var list = Array.isArray(items) ? items.slice() : [];
            if (!list.length && session) {
                var cid = Number(session.consultation_id || session.id || 0);
                ((window.PATIENT_RECORD || {}).prescriptions || []).forEach(function(rx) {
                    if (cid && Number(rx.consultation_id) === cid) {
                        list.push({ name: rx.medications || rx.diagnosis || 'Prescription', instructions: rx.instructions || '' });
                    }
                });
            }
            if (!list.length) return '<span class="odx-empty">No medications prescribed.</span>';
            return list.map(function(item) {
                if (!item || (!item.name && !item.medications)) return '';
                var html = '<div class="odx-rx-item"><div class="odx-rx-name">' + escapeHtml(item.name || item.medications) + '</div>';
                var meta = [item.dosage, item.frequency, item.duration].filter(function(x) { return x && String(x).trim(); }).map(escapeHtml).join(' · ');
                if (meta) html += '<div class="odx-rx-meta">' + meta + '</div>';
                if (item.instructions) html += '<div class="odx-rx-instr">' + escapeHtml(item.instructions) + '</div>';
                return html + '</div>';
            }).join('') || '<span class="odx-empty">No medications prescribed.</span>';
        }

        function normalizeFindings(findings) {
            if (typeof findings === 'string') {
                try { findings = JSON.parse(findings); } catch (e) { findings = []; }
            }
            if (!Array.isArray(findings)) return [];
            return findings.map(function(f) {
                var copy = Object.assign({}, f || {});
                copy.tooth = Number(copy.tooth);
                return copy;
            });
        }

        function buildVisitChartHtml(session) {
            odontogramTeethFindings = normalizeFindings(session.teeth_data);
            var content = '<div class="odontogram-chart-section-label">Adult Teeth</div><div id="odAdultTeethChart"></div>';
            content += '<div class="odontogram-chart-section-label">Pediatric Teeth</div><div id="odPedTeethChart"></div>';
            var findingsExtra = '';
            if (session.teeth_numbers) {
                findingsExtra = '<div class="odx-text" style="margin-top:8px;">Treated teeth: ' + escapeHtml(session.teeth_numbers) + '</div>';
            }
            content += odxSection('Tooth Findings', '<div id="odFindingsChips"></div>' + findingsExtra);

            var examHtml = '<div class="odx-vitals-grid">' + odxVital('Blood Pressure', session.blood_pressure) + odxVital('Heart Rate', session.heart_rate, ' bpm') + odxVital('Temperature', session.temperature, ' °C') + odxVital('O2 Saturation', session.oxygen_saturation, ' %') + odxVital('Height', session.height, ' cm') + odxVital('Weight', session.weight, ' kg') + '</div>';
            examHtml += '<div class="odx-subrow" style="margin-top:10px;"><div class="odx-subblock"><div class="odx-subblock-title">Periodontal Screening</div>' + (session.periodontal_screening ? '<span class="odx-chip on">' + escapeHtml(session.periodontal_screening) + '</span>' : '<span class="odx-empty">Not recorded</span>') + '</div>';
            examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">Occlusion</div><div class="odx-check-row">' + odxChip('Class (Molar)', session.occlusion_class_molar) + odxChip('Overjet', session.occlusion_overjet) + odxChip('Overbite', session.occlusion_overbite) + odxChip('Midline Deviation', session.occlusion_midline_deviation) + odxChip('Crossbite', session.occlusion_crossbite) + '</div></div></div>';
            var appChips = odxChip('Orthodontic', session.appliances_orthodontic) + odxChip('Skyplate', session.appliances_skyplate) + odxChip('Others', session.appliances_others);
            if (parseInt(session.appliances_others, 10) && session.appliances_others_text) {
                appChips += '<span class="odx-chip on">' + escapeHtml(session.appliances_others_text) + '</span>';
            }
            var tmdChips = odxChip('Clenching', session.tmd_clenching) + odxChip('Clicking', session.tmd_clicking) + odxChip('Trismus', session.tmd_trismus) + odxChip('Muscle Spasm', session.tmd_muscle_spasm);
            examHtml += '<div class="odx-subrow" style="margin-top:10px;"><div class="odx-subblock"><div class="odx-subblock-title">Appliances</div><div class="odx-check-row">' + appChips + '</div></div>';
            examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">TMD</div><div class="odx-check-row">' + tmdChips + '</div></div></div>';
            content += odxSection('Dental Examination', examHtml);
            content += odxSection('X-Ray', renderXraysHtml(session));
            var notesVal = String(session.consultation_notes || session.summary_notes || '').trim();
            content += odxSection('Consultation Notes', notesVal ? '<div class="odx-text">' + escapeHtml(notesVal) + '</div>' : '<span class="odx-empty">No consultation notes recorded.</span>');
            var dhNotes = String(session.dental_medical_history_notes || '').trim();
            content += odxSection('Dental History Notes', dhNotes ? '<div class="odx-text">' + escapeHtml(dhNotes) + '</div>' : '<span class="odx-empty">No dental history notes recorded.</span>');
            content += odxSection('Treatment Plan', renderPlansHtml((window.PATIENT_RECORD || {}).treatment_plans || []));
            content += odxSection('Prescription', renderRxHtml(session.prescription_data || [], session));
            var dxHtml = '';
            if (session.primary_icd10) dxHtml += '<span class="odx-dx-chip"><strong>' + escapeHtml(session.primary_icd10) + '</strong> ' + escapeHtml(session.primary_icd10_desc || '') + '</span>';
            if (session.secondary_icd10) dxHtml += '<span class="odx-dx-chip"><strong>' + escapeHtml(session.secondary_icd10) + '</strong> ' + escapeHtml(session.secondary_icd10_desc || '') + '</span>';
            if (String(session.diagnosis_narrative || '').trim()) dxHtml += '<div class="odx-text" style="margin-top:8px;">' + escapeHtml(session.diagnosis_narrative) + '</div>';
            content += odxSection('Diagnosis', dxHtml || '<span class="odx-empty">No diagnosis recorded.</span>');
            return content;
        }

        function openVisitFullView(visitKey) {
            var visits = (window.PATIENT_RECORD || {}).visits || [];
            var session = visits.find(function(v) { return String(v.visit_key) === String(visitKey); });
            if (!session) return;
            var dentist = session.dentist_name ? 'Dr. ' + session.dentist_name : '';
            document.getElementById('odontogramModalTitle').textContent = (session.service_label || 'Visit') + ' · ' + formatVisitDate(session.visit_date || session.created_at) + (dentist ? ' | ' + dentist : '');
            document.getElementById('odontogramModalContent').innerHTML = buildVisitChartHtml(session);
            document.getElementById('odontogramModalOverlay').classList.add('active');
            odRenderToothChart();
            odRenderFindings();
        }
        function closeVisitFullView() {
            document.getElementById('odontogramModalOverlay').classList.remove('active');
        }

        function openXrayViewer(idx) {
            var x = (window._odXrayList || [])[idx];
            if (!x) return;
            var name = x.original_filename || (x.file_path || '').split('/').pop();
            var src = '../../' + String(x.file_path || '').replace(/^\/+/, '');
            document.getElementById('odXrayViewerTitle').textContent = name;
            document.getElementById('odXrayViewerImg').src = src;
            var dl = document.getElementById('odXrayDownloadBtn');
            dl.href = src;
            dl.setAttribute('download', name);
            document.getElementById('odXrayViewerOverlay').style.display = 'flex';
        }
        function closeXrayViewer() {
            document.getElementById('odXrayViewerOverlay').style.display = 'none';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            closeXrayViewer();
            closeVisitFullView();
        });
    </script>
    <script>
        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            const unreadBadge = document.getElementById('unreadBadge');
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) { overlay = document.createElement('div'); overlay.id = 'floatingChatOverlay'; overlay.className = 'floating-chat-overlay'; document.body.appendChild(overlay); }
            if (!panel) { panel = document.createElement('div'); panel.id = 'floatingChatPanel'; panel.className = 'floating-chat-panel'; panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`; document.body.appendChild(panel); }
            function closeChat() { overlay.classList.remove('is-open'); panel.classList.remove('is-open'); }
            function openChat(event) {
                event.preventDefault();
                overlay.classList.add('is-open');
                panel.classList.add('is-open');
                const frame = panel.querySelector('.floating-chat-frame');
                if (frame && frame.contentWindow) {
                    window.setTimeout(() => {
                        frame.contentWindow.postMessage({ type: 'patient-chat-opened' }, window.location.origin);
                    }, 120);
                }
            }
            function updateUnreadBadge(count) {
                if (!unreadBadge) return;
                if (count > 0) {
                    unreadBadge.textContent = count > 99 ? '99+' : String(count);
                    unreadBadge.style.display = 'inline-flex';
                } else {
                    unreadBadge.style.display = 'none';
                }
            }
            async function pollUnreadCount() {
                try {
                    const payload = new FormData();
                    payload.append('ajax', '1');
                    payload.append('action', 'get_unread_count');
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    updateUnreadBadge(Number(data.unread_count || 0));
                } catch (_err) {}
            }
            launcher.addEventListener('click', openChat);
            overlay.addEventListener('click', closeChat);
            panel.querySelector('[data-chat-close]').addEventListener('click', closeChat);
            panel.querySelector('[data-chat-minimize]').addEventListener('click', closeChat);
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'patient-chat-unread') {
                    updateUnreadBadge(Number(event.data.count || 0));
                }
            });
            pollUnreadCount();
            setInterval(pollUnreadCount, 3000);
        })();
    </script>
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
</body>
</html>
