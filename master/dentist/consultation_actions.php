<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array(strtolower($role), ['dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$user_id = (int)$_SESSION['user_id'];

try {
    require_once '../../config/database.php';

    // Ensure medical_history table has all required columns
    static $mhSchemaReady = false;
    if (!$mhSchemaReady) {
        $mhSchemaReady = true;
        $mhColumns = [];
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM medical_history");
            foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $mhColumns[$col['Field']] = true;
            }
        } catch (Exception $e) {}

        $requiredMhCols = [
            'current_medications' => "ALTER TABLE medical_history ADD COLUMN current_medications TEXT NULL AFTER allergies",
            'past_surgeries' => "ALTER TABLE medical_history ADD COLUMN past_surgeries TEXT NULL AFTER medical_conditions",
            'heart_condition' => "ALTER TABLE medical_history ADD COLUMN heart_condition TINYINT(1) DEFAULT 0 AFTER past_surgeries",
            'diabetes' => "ALTER TABLE medical_history ADD COLUMN diabetes TINYINT(1) DEFAULT 0 AFTER heart_condition",
            'bleeding_disorder' => "ALTER TABLE medical_history ADD COLUMN bleeding_disorder TINYINT(1) DEFAULT 0 AFTER diabetes",
            'pregnancy' => "ALTER TABLE medical_history ADD COLUMN pregnancy TINYINT(1) DEFAULT 0 AFTER bleeding_disorder",
            'blood_pressure' => "ALTER TABLE medical_history ADD COLUMN blood_pressure TINYINT(1) DEFAULT 0 AFTER pregnancy",
            'hiv_aids' => "ALTER TABLE medical_history ADD COLUMN hiv_aids TINYINT(1) DEFAULT 0 AFTER blood_pressure",
            'hepatitis' => "ALTER TABLE medical_history ADD COLUMN hepatitis TINYINT(1) DEFAULT 0 AFTER hiv_aids",
            'tuberculosis' => "ALTER TABLE medical_history ADD COLUMN tuberculosis TINYINT(1) DEFAULT 0 AFTER hepatitis",
            'asthma' => "ALTER TABLE medical_history ADD COLUMN asthma TINYINT(1) DEFAULT 0 AFTER tuberculosis",
            'others' => "ALTER TABLE medical_history ADD COLUMN others TEXT NULL AFTER asthma",
        ];

        foreach ($requiredMhCols as $field => $sql) {
            if (!isset($mhColumns[$field])) {
                try { $pdo->exec($sql); } catch (Exception $e) {}
            }
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($method === 'POST' && !$action) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) $action = $input['action'] ?? '';
    }

    switch ($action) {

        case 'get_queue_patients':
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT q.id as queue_id, q.queue_number, q.status, q.patient_id, q.treatment_type,
                       p.first_name, p.middle_name, p.last_name, p.suffix
                FROM queue q
                LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED)
                WHERE q.status IN ('in_procedure', 'waiting')
                  AND COALESCE(q.queue_date, DATE(q.created_at)) = ?
                ORDER BY q.queue_number ASC, q.created_at ASC
            ");
            $stmt->execute([$today]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($patients as &$pt) {
                $parts = array_filter([$pt['first_name'] ?? '', $pt['middle_name'] ?? '', $pt['last_name'] ?? '', $pt['suffix'] ?? '']);
                $pt['full_name'] = implode(' ', $parts);
            }
            unset($pt);

            echo json_encode(['success' => true, 'patients' => $patients]);
            break;

        case 'get_consultation':
            $queueId = (int)($_GET['queue_id'] ?? 0);
            if (!$queueId) {
                echo json_encode(['success' => false, 'message' => 'Queue ID required']);
                break;
            }

            // Get consultation or create one
            $stmt = $pdo->prepare("SELECT * FROM consultations WHERE queue_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$queueId]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$consultation) {
                // Get queue info
                $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
                $stmt->execute([$queueId]);
                $queue = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$queue) {
                    echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO consultations (queue_id, patient_id, dentist_id) VALUES (?, ?, ?)");
                $stmt->execute([$queueId, $queue['patient_id'], $user_id]);
                $consId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id = ?");
                $stmt->execute([$consId]);
                $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

                $parts = array_filter([$queue['first_name'] ?? '', $queue['middle_name'] ?? '', $queue['last_name'] ?? '', $queue['suffix'] ?? '']);
                $consultation['patient_name'] = implode(' ', $parts);
                $consultation['queue_number'] = $queue['queue_number'] ?? '';
                $consultation['treatment_type'] = $queue['treatment_type'] ?? '';
                $consultation['is_new'] = true;
            } else {
                $stmt = $pdo->prepare("SELECT p.first_name, p.middle_name, p.last_name, p.suffix, q.queue_number, q.treatment_type
                    FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
                $stmt->execute([$queueId]);
                $qInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $parts = array_filter([$qInfo['first_name'] ?? '', $qInfo['middle_name'] ?? '', $qInfo['last_name'] ?? '', $qInfo['suffix'] ?? '']);
                $consultation['patient_name'] = implode(' ', $parts);
                $consultation['queue_number'] = $qInfo['queue_number'] ?? '';
                $consultation['treatment_type'] = $qInfo['treatment_type'] ?? '';
            }

            // Get patient medical history
            $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$consultation['patient_id']]);
            $medicalHistory = $stmt->fetch(PDO::FETCH_ASSOC);

            $consultation['medical_history'] = $medicalHistory;

            // Decode JSON fields
            if (is_string($consultation['teeth_data'] ?? '')) {
                $consultation['teeth_data'] = json_decode($consultation['teeth_data'], true);
            }
            if (is_string($consultation['prescription_data'] ?? '')) {
                $consultation['prescription_data'] = json_decode($consultation['prescription_data'], true);
            }

            echo json_encode(['success' => true, 'consultation' => $consultation]);
            break;

        case 'save_dental_exam':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE consultations SET
                periodontal_screening = ?,
                occlusion_class_molar = ?,
                occlusion_overjet = ?,
                occlusion_overbite = ?,
                occlusion_midline_deviation = ?,
                occlusion_crossbite = ?,
                appliances_orthodontic = ?,
                appliances_skyplate = ?,
                appliances_others = ?,
                appliances_others_text = ?,
                tmd_clenching = ?,
                tmd_clicking = ?,
                tmd_trismus = ?,
                tmd_muscle_spasm = ?,
                teeth_data = ?
                WHERE id = ?
            ");
            $teethJson = !empty($input['teeth_data']) ? json_encode($input['teeth_data']) : null;
            $stmt->execute([
                $input['periodontal_screening'] ?? null,
                (int)($input['occlusion_class_molar'] ?? 0),
                (int)($input['occlusion_overjet'] ?? 0),
                (int)($input['occlusion_overbite'] ?? 0),
                (int)($input['occlusion_midline_deviation'] ?? 0),
                (int)($input['occlusion_crossbite'] ?? 0),
                (int)($input['appliances_orthodontic'] ?? 0),
                (int)($input['appliances_skyplate'] ?? 0),
                (int)($input['appliances_others'] ?? 0),
                $input['appliances_others_text'] ?? null,
                (int)($input['tmd_clenching'] ?? 0),
                (int)($input['tmd_clicking'] ?? 0),
                (int)($input['tmd_trismus'] ?? 0),
                (int)($input['tmd_muscle_spasm'] ?? 0),
                $teethJson,
                $consultId
            ]);

            echo json_encode(['success' => true, 'message' => 'Dental examination saved']);
            break;

        case 'save_notes':
            $input = json_decode(file_get_contents('php://input'), true);
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE consultations SET consultation_notes = ? WHERE id = ?");
            $stmt->execute([$input['consultation_notes'] ?? '', $consultId]);
            echo json_encode(['success' => true, 'message' => 'Notes saved']);
            break;

        case 'save_history':
            $input = json_decode(file_get_contents('php://input'), true);
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE consultations SET dental_medical_history_notes = ? WHERE id = ?");
            $stmt->execute([$input['dental_medical_history_notes'] ?? '', $consultId]);
            echo json_encode(['success' => true, 'message' => 'History saved']);
            break;

        case 'save_medical_history':
            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = (int)($input['patient_id'] ?? 0);
            $medData = $input['medical_data'] ?? [];
            if (!$patientId) {
                echo json_encode(['success' => false, 'message' => 'Patient ID required']);
                break;
            }

            $checkboxFields = ['heart_condition', 'diabetes', 'bleeding_disorder', 'pregnancy', 'blood_pressure', 'hiv_aids', 'hepatitis', 'tuberculosis', 'asthma'];
            $textFields = ['allergies', 'current_medications', 'medical_conditions', 'past_surgeries', 'others'];

            // Check if medical_history record exists for this patient
            $stmt = $pdo->prepare("SELECT id FROM medical_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $setParts = [];
                $values = [];
                foreach ($checkboxFields as $f) {
                    if (array_key_exists($f, $medData)) {
                        $setParts[] = "$f = ?";
                        $values[] = (int)$medData[$f];
                    }
                }
                foreach ($textFields as $f) {
                    if (array_key_exists($f, $medData)) {
                        $setParts[] = "$f = ?";
                        $values[] = $medData[$f];
                    }
                }
                if (!empty($setParts)) {
                    $values[] = $existing['id'];
                    $stmt = $pdo->prepare("UPDATE medical_history SET " . implode(', ', $setParts) . " WHERE id = ?");
                    $stmt->execute($values);
                }
            } else {
                $colList = ['patient_id'];
                $valList = [$patientId];
                foreach ($checkboxFields as $f) {
                    if (array_key_exists($f, $medData)) {
                        $colList[] = $f;
                        $valList[] = (int)$medData[$f];
                    }
                }
                foreach ($textFields as $f) {
                    if (array_key_exists($f, $medData)) {
                        $colList[] = $f;
                        $valList[] = $medData[$f];
                    }
                }
                $placeholders = implode(', ', array_fill(0, count($valList), '?'));
                $stmt = $pdo->prepare("INSERT INTO medical_history (" . implode(', ', $colList) . ") VALUES ($placeholders)");
                $stmt->execute($valList);
            }

            echo json_encode(['success' => true, 'message' => 'Medical history saved']);
            break;

        case 'save_prescription':
            $input = json_decode(file_get_contents('php://input'), true);
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $prescJson = !empty($input['prescription_data']) ? json_encode($input['prescription_data']) : null;
            $stmt = $pdo->prepare("UPDATE consultations SET prescription_data = ? WHERE id = ?");
            $stmt->execute([$prescJson, $consultId]);
            echo json_encode(['success' => true, 'message' => 'Prescription saved']);
            break;

        case 'save_consent':
            $input = json_decode(file_get_contents('php://input'), true);
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE consultations SET
                consent_provider = ?,
                consent_patient_address = ?,
                consent_procedure = ?,
                consent_by_guardian = ?,
                consent_guardian_name = ?,
                consent_given = ?,
                consent_date = ?,
                consent_place = ?,
                consent_patient_signature = ?,
                consent_doctor_signature = ?,
                consent_witness_signature = ?,
                consent_patient_sign_name = ?,
                consent_witness_sign_name = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $input['consent_provider'] ?? null,
                $input['consent_patient_address'] ?? null,
                $input['consent_procedure'] ?? null,
                (int)($input['consent_by_guardian'] ?? 0),
                $input['consent_guardian_name'] ?? null,
                (int)($input['consent_given'] ?? 0),
                $input['consent_date'] ?? null,
                $input['consent_place'] ?? null,
                $input['consent_patient_signature'] ?? null,
                $input['consent_doctor_signature'] ?? null,
                $input['consent_witness_signature'] ?? null,
                $input['consent_patient_sign_name'] ?? null,
                $input['consent_witness_sign_name'] ?? null,
                $consultId
            ]);
            echo json_encode(['success' => true, 'message' => 'Consent saved']);
            break;

        case 'complete_consultation':
            $input = json_decode(file_get_contents('php://input'), true);
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            // Mark consultation completed
            $stmt = $pdo->prepare("UPDATE consultations SET status = 'completed' WHERE id = ?");
            $stmt->execute([$consultId]);

            // Get queue_id
            $stmt = $pdo->prepare("SELECT queue_id, patient_id FROM consultations WHERE id = ?");
            $stmt->execute([$consultId]);
            $cons = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cons) {
                // Mark queue as completed
                $stmt = $pdo->prepare("UPDATE queue SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$cons['queue_id']]);

                // Update appointment
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE patient_id = ? AND status IN ('Scheduled', 'In Queue')");
                $stmt->execute([$cons['patient_id']]);

                // Get billing info
                $stmt = $pdo->prepare("SELECT treatment_type FROM queue WHERE id = ?");
                $stmt->execute([$cons['queue_id']]);
                $treatmentType = $stmt->fetchColumn() ?: 'consultation';

                $estimatedAmount = 0;
                $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1");
                $stmt->execute([$treatmentType]);
                $service = $stmt->fetch();
                if ($service) {
                    $estimatedAmount = floatval($service['price']);
                } else {
                    $treatmentNames = array_map('trim', explode(',', $treatmentType));
                    $stmt = $pdo->prepare("SELECT price FROM services WHERE name LIKE ? AND is_active = 1 LIMIT 1");
                    foreach ($treatmentNames as $name) {
                        if (empty($name)) continue;
                        $stmt->execute(['%' . $name . '%']);
                        $svc = $stmt->fetch();
                        if ($svc) $estimatedAmount += floatval($svc['price']);
                    }
                }

                // Get billing
                $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$cons['patient_id']]);
                $billing = $stmt->fetch(PDO::FETCH_ASSOC);

                $totalAmount = $estimatedAmount;
                $paidAmount = 0;
                $balance = $estimatedAmount;
                $paymentStatus = 'unpaid';

                if ($billing) {
                    $totalAmount = floatval($billing['total_amount']);
                    $paidAmount = floatval($billing['paid_amount']);
                    $balance = floatval($billing['balance']);
                    $paymentStatus = $billing['payment_status'];
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Consultation completed',
                    'patient_id' => $cons['patient_id'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'payment_status' => $paymentStatus
                ]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Consultation completed']);
            }
            break;

        case 'get_patient_consultation_history':
            $patientId = (int)($_GET['patient_id'] ?? 0);
            $excludeId = (int)($_GET['exclude_consultation_id'] ?? 0);
            if (!$patientId) {
                echo json_encode(['success' => false, 'message' => 'Patient ID required']);
                break;
            }

            $sql = "SELECT c.*, q.treatment_type AS queue_treatment_type, q.queue_number,
                           u.first_name AS dentist_first_name, u.last_name AS dentist_last_name
                    FROM consultations c
                    LEFT JOIN queue q ON q.id = c.queue_id
                    LEFT JOIN users u ON u.id = c.dentist_id
                    WHERE c.patient_id = ? AND c.status = 'completed'";
            $params = [$patientId];
            if ($excludeId) {
                $sql .= " AND c.id != ?";
                $params[] = $excludeId;
            }
            $sql .= " ORDER BY c.created_at DESC LIMIT 20";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                if (is_string($row['teeth_data'] ?? '')) {
                    $row['teeth_data'] = json_decode($row['teeth_data'], true);
                }
                if (is_string($row['prescription_data'] ?? '')) {
                    $row['prescription_data'] = json_decode($row['prescription_data'], true);
                }
                $parts = array_filter([$row['dentist_first_name'] ?? '', $row['dentist_last_name'] ?? '']);
                $row['dentist_name'] = implode(' ', $parts) ?: 'Unknown';
            }
            unset($row);

            echo json_encode(['success' => true, 'consultations' => $rows]);
            break;

        case 'get_past_consultation':
            $consultId = (int)($_GET['id'] ?? 0);
            if (!$consultId) {
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT c.*, q.treatment_type AS queue_treatment_type, q.queue_number,
                       p.first_name, p.middle_name, p.last_name, p.suffix,
                       u.first_name AS dentist_first_name, u.last_name AS dentist_last_name
                FROM consultations c
                LEFT JOIN queue q ON q.id = c.queue_id
                LEFT JOIN patients p ON p.id = CAST(c.patient_id AS UNSIGNED)
                LEFT JOIN users u ON u.id = c.dentist_id
                WHERE c.id = ?
            ");
            $stmt->execute([$consultId]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$consultation) {
                echo json_encode(['success' => false, 'message' => 'Consultation not found']);
                break;
            }

            $parts = array_filter([$consultation['first_name'] ?? '', $consultation['middle_name'] ?? '', $consultation['last_name'] ?? '', $consultation['suffix'] ?? '']);
            $consultation['patient_name'] = implode(' ', $parts);
            $consultation['treatment_type'] = $consultation['queue_treatment_type'] ?? '';
            $consultation['dentist_name'] = implode(' ', array_filter([$consultation['dentist_first_name'] ?? '', $consultation['dentist_last_name'] ?? ''])) ?: 'Unknown';

            $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$consultation['patient_id']]);
            $consultation['medical_history'] = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_string($consultation['teeth_data'] ?? '')) {
                $consultation['teeth_data'] = json_decode($consultation['teeth_data'], true);
            }
            if (is_string($consultation['prescription_data'] ?? '')) {
                $consultation['prescription_data'] = json_decode($consultation['prescription_data'], true);
            }

            echo json_encode(['success' => true, 'consultation' => $consultation]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Consultation Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
ob_end_flush();
?>