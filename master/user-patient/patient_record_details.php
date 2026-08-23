<?php
/**
 * Patient Record Details API
 */

ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
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

$_columnCache = [];

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    global $_columnCache;
    $key = $table . '.' . $column;
    if (array_key_exists($key, $_columnCache)) {
        return $_columnCache[$key];
    }
    if (!isset($_columnCache[$table . '._all'])) {
        try {
            $result = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "`");
            $cols = [];
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $cols[strtolower($row[0])] = true;
                }
            }
            $_columnCache[$table . '._all'] = $cols;
        } catch (Exception $e) {
            $_columnCache[$table . '._all'] = [];
        }
    }
    $found = isset($_columnCache[$table . '._all'][strtolower($column)]);
    $_columnCache[$key] = $found;
    return $found;
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
        'p.updated_at',
        'TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age',
    ];

    $joinClause = '';
    if (tableHasColumn($pdo, 'users', 'patient_id')) {
        $joinClause = ' LEFT JOIN users u ON u.patient_id = p.id ';
    } elseif (tableHasColumn($pdo, 'patients', 'user_id')) {
        $joinClause = ' LEFT JOIN users u ON u.id = p.user_id ';
    }

    // Patient account photos are stored on users; retain the patient-column fallback for legacy records.
    $hasPatientProfilePicture = tableHasColumn($pdo, 'patients', 'profile_picture');
    $hasUserProfilePicture = tableHasColumn($pdo, 'users', 'profile_picture');
    if ($hasPatientProfilePicture && $hasUserProfilePicture && $joinClause !== '') {
        $selectList[] = "COALESCE(NULLIF(p.profile_picture, ''), u.profile_picture) as profile_picture";
    } elseif ($hasPatientProfilePicture) {
        $selectList[] = 'p.profile_picture as profile_picture';
    } elseif ($hasUserProfilePicture && $joinClause !== '') {
        $selectList[] = 'u.profile_picture as profile_picture';
    } else {
        $selectList[] = "NULL as profile_picture";
    }

    // Include user-related fields if users table is joined
    if (tableHasColumn($pdo, 'users', 'id')) {
        if (tableHasColumn($pdo, 'users', 'id')) $selectList[] = 'u.id as user_id';
        if (tableHasColumn($pdo, 'users', 'username')) $selectList[] = 'u.username as username';
    }

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

    $patientPersonalInfoUpdate = null;
    try {
        $auditStmt = $pdo->prepare("SELECT created_at, description, old_value, new_value FROM audit_logs WHERE affected_table = 'patients' AND affected_id = ? AND module = 'patients' AND action_type = 'update' AND description LIKE 'patient_self_service_personal_info:%' ORDER BY created_at DESC, id DESC LIMIT 20");
        $auditStmt->execute([$patientId]);
        $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($auditRows) {
            $latestTimestamp = $auditRows[0]['created_at'];
            $changedFields = [];
            foreach ($auditRows as $auditRow) {
                if ($auditRow['created_at'] !== $latestTimestamp) break;
                $field = substr($auditRow['description'], strlen('patient_self_service_personal_info:'));
                $fieldLabels = [
                    'first_name' => 'First Name',
                    'middle_name' => 'Middle Name',
                    'last_name' => 'Last Name',
                    'date_of_birth' => 'Date of Birth',
                    'gender' => 'Gender'
                ];
                if ($field === 'gender') {
                    $oldGender = strtolower(trim((string)($auditRow['old_value'] ?? '')));
                    $newGender = strtolower(trim((string)($auditRow['new_value'] ?? '')));
                    $oldGender = ['m' => 'male', 'f' => 'female'][$oldGender] ?? $oldGender;
                    $newGender = ['m' => 'male', 'f' => 'female'][$newGender] ?? $newGender;
                    if ($oldGender === $newGender) continue;
                }
                if (isset($fieldLabels[$field])) $changedFields[] = $fieldLabels[$field];
            }
            $patientPersonalInfoUpdate = [
                'updated_at' => $latestTimestamp,
                'changed_fields' => array_values(array_unique($changedFields))
            ];
        }
    } catch (Exception $e) {
        $patientPersonalInfoUpdate = null;
    }

    // Always resolve the linked account after loading the patient. This ensures the
    // account photo is returned even when the patient row has no photo value.
    // When multiple users share the same patient_id (data issue), we prefer the
    // user who actually has a profile_picture.
    try {
        if (tableHasColumn($pdo, 'users', 'id')) {
            $bestUser = null;
            $userSelect = 'id, username';
            if ($hasUserProfilePicture) {
                $userSelect .= ', profile_picture';
            }

            // Helper: check if a user is a better match than the current best
            // (prefers users that have a profile_picture set)
            $considerUser = function(&$best, $candidate) {
                if (!$candidate) return;
                if ($best === null) { $best = $candidate; return; }
                // Prefer the candidate if it has a profile_picture and best does not
                $bestHasPic = !empty($best['profile_picture']);
                $candidateHasPic = !empty($candidate['profile_picture']);
                if (!$bestHasPic && $candidateHasPic) {
                    $best = $candidate;
                }
            };

            // 1. Try the patients.user_id relation
            if (!empty($patient['user_id'])) {
                $s = $pdo->prepare('SELECT ' . $userSelect . ' FROM users WHERE id = ? LIMIT 1');
                $s->execute([$patient['user_id']]);
                $considerUser($bestUser, $s->fetch(PDO::FETCH_ASSOC) ?: null);
            }

            // 2. Try the users.patient_id relation
            if (tableHasColumn($pdo, 'users', 'patient_id')) {
                $s = $pdo->prepare('SELECT ' . $userSelect . ' FROM users WHERE patient_id = ?');
                $s->execute([$patientId]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $considerUser($bestUser, $u);
                }
            }

            // 3. Match by email or phone (most reliable for duplicate patient_id cases)
            $conds = [];
            $params = [];
            if (!empty($patient['email']) && tableHasColumn($pdo, 'users', 'email')) {
                $conds[] = 'email = ?';
                $params[] = $patient['email'];
            }
            if (!empty($patient['phone']) && tableHasColumn($pdo, 'users', 'phone')) {
                $conds[] = 'phone = ?';
                $params[] = $patient['phone'];
            }
            if (!empty($conds)) {
                $sql = 'SELECT ' . $userSelect . ' FROM users WHERE (' . implode(' OR ', $conds) . ') LIMIT 10';
                $s = $pdo->prepare($sql);
                $s->execute($params);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $considerUser($bestUser, $u);
                }
            }

            // 4. Last resort: match by first_name + last_name + phone
            if (!empty($patient['first_name']) && !empty($patient['last_name']) && !empty($patient['phone'])) {
                $namePhoneSql = 'SELECT ' . $userSelect . ' FROM users WHERE first_name = ? AND last_name = ? AND phone = ? LIMIT 10';
                $s = $pdo->prepare($namePhoneSql);
                $s->execute([$patient['first_name'], $patient['last_name'], $patient['phone']]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $considerUser($bestUser, $u);
                }
            }

            if ($bestUser) {
                $patient['user_id'] = $bestUser['id'];
                if (isset($bestUser['username'])) $patient['username'] = $bestUser['username'];
                if (!empty($bestUser['profile_picture'])) {
                    $patient['profile_picture'] = $bestUser['profile_picture'];
                }
                // Write back patient_id so future JOINs work instantly
                if (tableHasColumn($pdo, 'users', 'patient_id')) {
                    try {
                        $linkStmt = $pdo->prepare("UPDATE users SET patient_id = ? WHERE id = ? AND (patient_id IS NULL OR patient_id != ?)");
                        $linkStmt->execute([$patientId, $bestUser['id'], $patientId]);
                    } catch (Exception $e) { /* non-fatal */ }
                }
            }
        }
    } catch (Exception $e) {
        // Non-fatal: leave patient as-is if lookup fails
    }

    // Verify profile picture file exists; if not, null it to avoid broken image links
    if (!empty($patient['profile_picture'])) {
        $candidate = $patient['profile_picture'];
        $fullPath = dirname(__DIR__, 2) . '/' . ltrim($candidate, '/\\');
        if (!file_exists($fullPath)) {
            $patient['profile_picture'] = null;
        }
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
    
    // Get dental history AND current queue item in one query
    $dentalHistory = [];
    $queueItem = null;
    try {
        $stmt = $pdo->prepare("
            SELECT q.id, q.treatment_type, q.teeth_numbers, q.completed_at,
                   q.status, q.patient_id, q.service_id, q.created_at, q.is_archived,
                   s.name as service_name, s.price as service_price
            FROM queue q
            LEFT JOIN services s ON q.service_id = s.id
            WHERE q.patient_id = ? AND q.is_archived = 0
            ORDER BY q.created_at DESC
            LIMIT 21
        ");
        $stmt->execute([$patientId]);
        $allQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allQueue as $row) {
            if ($row['status'] === 'completed' && count($dentalHistory) < 20) {
                $dentalHistory[] = $row;
            } elseif (in_array($row['status'], ['waiting', 'in_procedure']) && $queueItem === null) {
                $queueItem = $row;
            }
        }
    } catch (Exception $e) {
        $dentalHistory = [];
        $queueItem = null;
    }
    
    // Get billing data - ALL records for this patient with service names
    $billing = null;
    $allBillings = [];
    try {
        $stmt = $pdo->prepare("
            SELECT b.id, b.total_amount, b.paid_amount, b.balance, b.payment_status,
                   b.billing_date, b.due_date, b.notes, b.appointment_id, b.patient_id,
                    b.subtotal_amount, b.discount_type, b.discount_amount,
                    COALESCE(svc.name, q.treatment_type, 'General Service') as service_name,
                    svc.price as service_price
            FROM billing b
            LEFT JOIN appointments a ON b.appointment_id = a.id
            LEFT JOIN services svc ON a.service_id = svc.id
            LEFT JOIN queue q ON q.patient_id = b.patient_id AND q.service_id = a.service_id
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
                   c.primary_icd10, c.primary_icd10_desc, c.secondary_icd10, c.secondary_icd10_desc,
                   c.diagnosis_narrative,
                   q.blood_pressure, q.heart_rate, q.temperature, q.oxygen_saturation, q.height, q.weight,
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

    // Intake dental history questionnaire (filled once by staff during new admission)
    $intakeDentalHistory = null;
    try {
        $stmt = $pdo->prepare("
            SELECT previous_dentist, last_visit_date, reason_last_visit,
                   previous_treatments, current_complaints, created_at
            FROM dental_history
            WHERE patient_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $intakeDentalHistory = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $intakeDentalHistory = null;
    }

    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'patient_personal_info_update' => $patientPersonalInfoUpdate,
        'medical_history' => $medicalHistory,
        'dental_history' => $dentalHistory,
        'intake_dental_history' => $intakeDentalHistory,
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