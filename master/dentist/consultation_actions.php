<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
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

function ensurePrescriptionSyncSchema(PDO $pdo): void {
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    try {
        $columns = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM prescriptions");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[] = $row['Field'];
        }
        $alterStatements = [];
        if (!in_array('doctor_id', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN doctor_id INT(11) NULL AFTER patient_id';
        }
        if (!in_array('medications', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN medications TEXT NULL AFTER doctor_id';
        }
        if (!in_array('diagnosis', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN diagnosis TEXT NULL AFTER medications';
        }
        if (!in_array('instructions', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN instructions TEXT NULL AFTER diagnosis';
        }
        if (!in_array('issue_date', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN issue_date DATE NOT NULL DEFAULT CURRENT_DATE() AFTER diagnosis';
        }
        if (!in_array('updated_at', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at';
        }
        if (!in_array('consultation_id', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN consultation_id INT(11) NULL AFTER doctor_id';
        }

        foreach ($alterStatements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                // ignore schema update failures
            }
        }

        try {
            $pdo->exec('CREATE UNIQUE INDEX idx_prescriptions_consultation_id ON prescriptions (consultation_id)');
        } catch (Exception $e) {
            // ignore if index already exists
        }
    } catch (Exception $e) {
        // ignore schema check failures
    }
}

function buildPrescriptionText(array $prescriptionData): array {
    $medications = [];
    $instructions = [];
    foreach ($prescriptionData as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim($item['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $parts = [];
        if (!empty($item['dosage'])) {
            $parts[] = trim($item['dosage']);
        }
        if (!empty($item['frequency'])) {
            $parts[] = trim($item['frequency']);
        }
        if (!empty($item['duration'])) {
            $parts[] = trim($item['duration']);
        }
        $line = $name;
        if (!empty($parts)) {
            $line .= ' (' . implode(', ', $parts) . ')';
        }
        $medications[] = $line;
        if (!empty($item['instructions'])) {
            $instructions[] = trim($item['instructions']);
        }
    }
    return [
        'medications' => implode("\n", $medications),
        'instructions' => implode("\n", $instructions),
    ];
}

function syncConsultationPrescriptionToPrescriptionTable(PDO $pdo, int $consultId, array $prescriptionData): void {
    if (!$consultId || empty($prescriptionData)) {
        return;
    }

    ensurePrescriptionSyncSchema($pdo);

    $stmt = $pdo->prepare("SELECT patient_id, dentist_id FROM consultations WHERE id = ?");
    $stmt->execute([$consultId]);
    $consult = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$consult || empty($consult['patient_id'])) {
        return;
    }

    $text = buildPrescriptionText($prescriptionData);
    if (trim($text['medications']) === '') {
        return;
    }

    $consultationId = $consultId;
    $patientId = (int)$consult['patient_id'];
    $doctorId = isset($consult['dentist_id']) ? (int)$consult['dentist_id'] : null;

    $select = $pdo->prepare("SELECT id FROM prescriptions WHERE consultation_id = ?");
    $select->execute([$consultationId]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $update = $pdo->prepare("UPDATE prescriptions SET patient_id = ?, doctor_id = ?, medications = ?, diagnosis = ?, instructions = ?, issue_date = CURDATE() WHERE consultation_id = ?");
        $update->execute([
            $patientId,
            $doctorId,
            $text['medications'],
            'Prescription from consultation',
            $text['instructions'],
            $consultationId
        ]);
    } else {
        $insert = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, medications, diagnosis, instructions, issue_date, consultation_id) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)");
        $insert->execute([
            $patientId,
            $doctorId,
            $text['medications'],
            'Prescription from consultation',
            $text['instructions'],
            $consultationId
        ]);
    }
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/occlusion_schema.php';
    ensureOcclusionSchema($pdo);
    require_once '../../includes/billing_helper.php';
    require_once '../../includes/notification_functions.php';
    require_once '../../includes/patient_dashboard_status_helper.php';
    require_once '../../includes/medical_certificate_helper.php';
    ensureNotificationSchema($pdo);
    ensureMedicalCertificateSchema($pdo);
    ensureMedicalCertificateService($pdo);

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
            'epilepsy' => "ALTER TABLE medical_history ADD COLUMN epilepsy TINYINT(1) DEFAULT 0 AFTER asthma",
            'std' => "ALTER TABLE medical_history ADD COLUMN std TINYINT(1) DEFAULT 0 AFTER epilepsy",
            'stomach_ulcer' => "ALTER TABLE medical_history ADD COLUMN stomach_ulcer TINYINT(1) DEFAULT 0 AFTER std",
            'fainting_seizure' => "ALTER TABLE medical_history ADD COLUMN fainting_seizure TINYINT(1) DEFAULT 0 AFTER stomach_ulcer",
            'rapid_weight_loss' => "ALTER TABLE medical_history ADD COLUMN rapid_weight_loss TINYINT(1) DEFAULT 0 AFTER fainting_seizure",
            'rheumatic_fever' => "ALTER TABLE medical_history ADD COLUMN rheumatic_fever TINYINT(1) DEFAULT 0 AFTER rapid_weight_loss",
            'hay_fever' => "ALTER TABLE medical_history ADD COLUMN hay_fever TINYINT(1) DEFAULT 0 AFTER rheumatic_fever",
            'respiratory_problems' => "ALTER TABLE medical_history ADD COLUMN respiratory_problems TINYINT(1) DEFAULT 0 AFTER hay_fever",
            'swollen_ankles' => "ALTER TABLE medical_history ADD COLUMN swollen_ankles TINYINT(1) DEFAULT 0 AFTER respiratory_problems",
            'kidney_disease' => "ALTER TABLE medical_history ADD COLUMN kidney_disease TINYINT(1) DEFAULT 0 AFTER swollen_ankles",
            'chest_pain' => "ALTER TABLE medical_history ADD COLUMN chest_pain TINYINT(1) DEFAULT 0 AFTER kidney_disease",
            'stroke' => "ALTER TABLE medical_history ADD COLUMN stroke TINYINT(1) DEFAULT 0 AFTER chest_pain",
            'others' => "ALTER TABLE medical_history ADD COLUMN others TEXT NULL AFTER stroke",
        ];

        foreach ($requiredMhCols as $field => $sql) {
            if (!isset($mhColumns[$field])) {
                try { $pdo->exec($sql); } catch (Exception $e) {}
            }
        }

        // Ensure diagnosis (ICD-10) columns exist on consultations + icd10_codes table
        $consultColumns = [];
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM consultations");
            foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $consultColumns[$col['Field']] = true;
            }
        } catch (Exception $e) {}

        $requiredConsultCols = [
            'primary_icd10' => "ALTER TABLE consultations ADD COLUMN primary_icd10 VARCHAR(10) DEFAULT NULL",
            'primary_icd10_desc' => "ALTER TABLE consultations ADD COLUMN primary_icd10_desc VARCHAR(255) DEFAULT NULL",
            'secondary_icd10' => "ALTER TABLE consultations ADD COLUMN secondary_icd10 VARCHAR(10) DEFAULT NULL",
            'secondary_icd10_desc' => "ALTER TABLE consultations ADD COLUMN secondary_icd10_desc VARCHAR(255) DEFAULT NULL",
            'diagnosis_narrative' => "ALTER TABLE consultations ADD COLUMN diagnosis_narrative TEXT DEFAULT NULL",
        ];
        foreach ($requiredConsultCols as $field => $sql) {
            if (!isset($consultColumns[$field])) {
                try { $pdo->exec($sql); } catch (Exception $e) {}
            }
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS icd10_codes (
                id int(11) NOT NULL AUTO_INCREMENT,
                code varchar(10) NOT NULL,
                description varchar(255) NOT NULL,
                chapter varchar(100) DEFAULT NULL,
                active tinyint(1) DEFAULT 1,
                created_at timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                UNIQUE KEY uq_icd10_code (code),
                KEY idx_icd10_description (description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}

        ob_clean();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $requestBody = [];
    if ($method === 'POST') {
        $decodedBody = json_decode(file_get_contents('php://input'), true);
        if (is_array($decodedBody)) {
            $requestBody = $decodedBody;
        }
    }
    if (!$action && !empty($requestBody['action'])) {
        $action = $requestBody['action'];
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

            ob_clean();
            echo json_encode(['success' => true, 'patients' => $patients]);
            break;

        case 'get_consultation':
            $queueId = (int)($_GET['queue_id'] ?? 0);
            if (!$queueId) {
                ob_clean();
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
                    ob_clean();
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
                $consultation['vitals'] = [
                    'blood_pressure' => $queue['blood_pressure'] ?? '',
                    'heart_rate' => $queue['heart_rate'] ?? '',
                    'temperature' => $queue['temperature'] ?? '',
                    'oxygen_saturation' => $queue['oxygen_saturation'] ?? '',
                    'height' => $queue['height'] ?? '',
                    'weight' => $queue['weight'] ?? ''
                ];
            } else {
                $stmt = $pdo->prepare("SELECT p.first_name, p.middle_name, p.last_name, p.suffix, q.queue_number, q.treatment_type,
                    q.blood_pressure, q.heart_rate, q.temperature, q.oxygen_saturation, q.height, q.weight
                    FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
                $stmt->execute([$queueId]);
                $qInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $parts = array_filter([$qInfo['first_name'] ?? '', $qInfo['middle_name'] ?? '', $qInfo['last_name'] ?? '', $qInfo['suffix'] ?? '']);
                $consultation['patient_name'] = implode(' ', $parts);
                $consultation['queue_number'] = $qInfo['queue_number'] ?? '';
                $consultation['treatment_type'] = $qInfo['treatment_type'] ?? '';
                $consultation['vitals'] = [
                    'blood_pressure' => $qInfo['blood_pressure'] ?? '',
                    'heart_rate' => $qInfo['heart_rate'] ?? '',
                    'temperature' => $qInfo['temperature'] ?? '',
                    'oxygen_saturation' => $qInfo['oxygen_saturation'] ?? '',
                    'height' => $qInfo['height'] ?? '',
                    'weight' => $qInfo['weight'] ?? ''
                ];
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
            if (!is_array($consultation['prescription_data'] ?? null)) {
                $consultation['prescription_data'] = [];
            }

            attachMedicalCertificateToConsultation($pdo, $consultation);

            ob_clean();
            echo json_encode(['success' => true, 'consultation' => $consultation]);
            break;

        case 'save_dental_exam':
            $input = $requestBody ?: $_POST;
            if (!$input) { $input = $_POST; }
            $molarClass = $input['occlusion_molar_class'] ?? null;
            if (!in_array($molarClass, [null, '', 'Class I', 'Class II', 'Class III'], true)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid molar classification']);
                break;
            }
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE consultations SET
                periodontal_screening = ?,
                occlusion_class_molar = ?,
                occlusion_molar_class = ?,
                occlusion_notes = ?,
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
                $molarClass ?: null,
                trim($input['occlusion_notes'] ?? '') ?: null,
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

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Dental examination saved']);
            break;

        case 'save_notes':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE consultations SET consultation_notes = ? WHERE id = ?");
            $stmt->execute([$input['consultation_notes'] ?? '', $consultId]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Notes saved']);
            break;

        case 'save_history':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE consultations SET dental_medical_history_notes = ? WHERE id = ?");
            $stmt->execute([$input['dental_medical_history_notes'] ?? '', $consultId]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'History saved']);
            break;

        case 'search_icd10':
            $q = trim($_GET['q'] ?? '');
            if ($q === '') {
                ob_clean();
                echo json_encode(['success' => true, 'results' => []]);
                break;
            }
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT code, description
                FROM icd10_codes
                WHERE active = 1 AND (code LIKE ? OR description LIKE ?)
                ORDER BY code ASC
                LIMIT 20
            ");
            $stmt->execute([$like, $like]);
            ob_clean();
            echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save_diagnosis':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            // Resolve + validate codes against the reference table (snapshots description)
            $resolveCode = function ($code) use ($pdo) {
                $code = strtoupper(trim((string)$code));
                if ($code === '') return [null, null];
                $stmt = $pdo->prepare("SELECT description FROM icd10_codes WHERE active = 1 AND UPPER(code) = ?");
                $stmt->execute([$code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return [false, null];
                return [$code, $row['description']];
            };

            list($primaryCode, $primaryDesc) = $resolveCode($input['primary_icd10'] ?? '');
            list($secondaryCode, $secondaryDesc) = $resolveCode($input['secondary_icd10'] ?? '');

            if ($primaryCode === false || $secondaryCode === false) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid ICD-10 code']);
                break;
            }

            $narrative = trim((string)($input['diagnosis_narrative'] ?? ''));
            if (mb_strlen($narrative) > 5000) {
                $narrative = mb_substr($narrative, 0, 5000);
            }

            $stmt = $pdo->prepare("UPDATE consultations SET
                primary_icd10 = ?,
                primary_icd10_desc = ?,
                secondary_icd10 = ?,
                secondary_icd10_desc = ?,
                diagnosis_narrative = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $primaryCode,
                $primaryDesc,
                $secondaryCode,
                $secondaryDesc,
                $narrative !== '' ? $narrative : null,
                $consultId
            ]);

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Diagnosis saved',
                'diagnosis' => [
                    'primary_icd10' => $primaryCode,
                    'primary_icd10_desc' => $primaryDesc,
                    'secondary_icd10' => $secondaryCode,
                    'secondary_icd10_desc' => $secondaryDesc,
                    'diagnosis_narrative' => $narrative
                ]
            ]);
            break;

        case 'save_medical_history':
            $input = $requestBody ?: $_POST;
            $patientId = (int)($input['patient_id'] ?? 0);
            $medData = $input['medical_data'] ?? [];
            if (!$patientId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Patient ID required']);
                break;
            }

            $checkboxFields = ['heart_condition', 'diabetes', 'bleeding_disorder', 'pregnancy', 'blood_pressure', 'hiv_aids', 'hepatitis', 'tuberculosis', 'asthma', 'epilepsy', 'std', 'stomach_ulcer', 'fainting_seizure', 'rapid_weight_loss', 'rheumatic_fever', 'hay_fever', 'respiratory_problems', 'swollen_ankles', 'kidney_disease', 'chest_pain', 'stroke'];
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

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Medical history saved']);
            break;

        case 'save_prescription':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $prescriptionData = $input['prescription_data'] ?? [];
            if (is_string($prescriptionData)) {
                $prescriptionData = json_decode($prescriptionData, true) ?: [];
            }
            if (!is_array($prescriptionData)) {
                $prescriptionData = [];
            }
            $prescJson = json_encode($prescriptionData);
            $stmt = $pdo->prepare("UPDATE consultations SET prescription_data = ? WHERE id = ?");
            $stmt->execute([$prescJson, $consultId]);

            syncConsultationPrescriptionToPrescriptionTable($pdo, $consultId, $prescriptionData);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Prescription saved']);
            break;

        case 'save_consent':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $patientSignature = $input['consent_patient_signature_data'] ?? $input['consent_patient_signature'] ?? null;
            $doctorSignature = $input['consent_doctor_signature_data'] ?? $input['consent_doctor_signature'] ?? null;
            $witnessSignature = $input['consent_witness_signature_data'] ?? $input['consent_witness_signature'] ?? null;

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
                $patientSignature,
                $doctorSignature,
                $witnessSignature,
                $input['consent_patient_sign_name'] ?? null,
                $input['consent_witness_sign_name'] ?? null,
                $consultId
            ]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Consent saved']);
            break;

        case 'complete_consultation':
            $input = $requestBody ?: $_POST;
            $consultId = (int)($input['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            // Mark consultation completed
            $stmt = $pdo->prepare("UPDATE consultations SET status = 'completed' WHERE id = ?");
            $stmt->execute([$consultId]);

            // Get queue_id and patient name
            $stmt = $pdo->prepare("
                SELECT c.queue_id, c.patient_id,
                       p.first_name, p.middle_name, p.last_name, p.suffix
                FROM consultations c
                LEFT JOIN patients p ON p.id = c.patient_id
                WHERE c.id = ?
            ");
            $stmt->execute([$consultId]);
            $cons = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cons) {
                // Mark queue as pending payment (billing continues next)
                $stmt = $pdo->prepare("UPDATE queue SET status = 'pending_payment', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$cons['queue_id']]);

                // Update appointment
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE patient_id = ? AND (status IN ('Scheduled', 'In Queue') OR status = '' OR status IS NULL)");
                $stmt->execute([$cons['patient_id']]);

                // Find the patient's completed appointment id for billing linkage
                $appointmentId = null;
                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND status = 'Completed' ORDER BY appointment_date DESC, id DESC LIMIT 1");
                $stmt->execute([$cons['patient_id']]);
                $appointmentId = $stmt->fetchColumn() ?: null;

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

                // Find-or-create billing record for this visit
                $visitDate = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT DATE(created_at) FROM queue WHERE id = ?");
                $stmt->execute([$cons['queue_id']]);
                $visitDate = $stmt->fetchColumn() ?: $visitDate;

                $billingInfo = findOrCreateBillingForVisit($pdo, (int)$cons['patient_id'], $visitDate, $estimatedAmount, $treatmentType, $appointmentId);

                $totalAmount = $billingInfo['total_amount'];
                $paidAmount = $billingInfo['paid_amount'];
                $balance = $billingInfo['balance'];
                $paymentStatus = $billingInfo['payment_status'];
                $billingId = $billingInfo['billing_id'];

                $outstanding = getPatientOutstandingBalance($pdo, (int)$cons['patient_id']);
                notifyPatientConsultationComplete($pdo, (int)$cons['patient_id'], $outstanding);

                $patientName = trim(implode(' ', array_filter([
                    $cons['first_name'] ?? '',
                    $cons['middle_name'] ?? '',
                    $cons['last_name'] ?? '',
                    $cons['suffix'] ?? '',
                ])));
                notifyStaffConsultationComplete($pdo, $patientName, $outstanding, (string)$treatmentType);

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Consultation completed. Patient moved to billing.',
                    'patient_id' => $cons['patient_id'],
                    'billing_id' => $billingId,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'payment_status' => $paymentStatus
                ]);
            } else {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Consultation completed']);
            }
            break;

        case 'get_patient_consultation_history':
            $patientId = (int)($_GET['patient_id'] ?? 0);
            $excludeId = (int)($_GET['exclude_consultation_id'] ?? 0);
            if (!$patientId) {
                ob_clean();
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
                if (!is_array($row['prescription_data'] ?? null)) {
                    $row['prescription_data'] = [];
                }
                $parts = array_filter([$row['dentist_first_name'] ?? '', $row['dentist_last_name'] ?? '']);
                $row['dentist_name'] = implode(' ', $parts) ?: 'Unknown';
            }
            unset($row);

            ob_clean();
            echo json_encode(['success' => true, 'consultations' => $rows]);
            break;

        case 'get_past_consultation':
            $consultId = (int)($_GET['id'] ?? 0);
            if (!$consultId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT c.*, q.treatment_type AS queue_treatment_type, q.queue_number,
                       q.blood_pressure, q.heart_rate, q.temperature, q.oxygen_saturation, q.height, q.weight,
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
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation not found']);
                break;
            }

            $parts = array_filter([$consultation['first_name'] ?? '', $consultation['middle_name'] ?? '', $consultation['last_name'] ?? '', $consultation['suffix'] ?? '']);
            $consultation['patient_name'] = implode(' ', $parts);
            $consultation['treatment_type'] = $consultation['queue_treatment_type'] ?? '';
            $consultation['dentist_name'] = implode(' ', array_filter([$consultation['dentist_first_name'] ?? '', $consultation['dentist_last_name'] ?? ''])) ?: 'Unknown';
            $consultation['vitals'] = [
                'blood_pressure' => $consultation['blood_pressure'] ?? '',
                'heart_rate' => $consultation['heart_rate'] ?? '',
                'temperature' => $consultation['temperature'] ?? '',
                'oxygen_saturation' => $consultation['oxygen_saturation'] ?? '',
                'height' => $consultation['height'] ?? '',
                'weight' => $consultation['weight'] ?? ''
            ];

            $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$consultation['patient_id']]);
            $consultation['medical_history'] = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_string($consultation['teeth_data'] ?? '')) {
                $consultation['teeth_data'] = json_decode($consultation['teeth_data'], true);
            }
            if (is_string($consultation['prescription_data'] ?? '')) {
                $consultation['prescription_data'] = json_decode($consultation['prescription_data'], true);
            }
            if (!is_array($consultation['prescription_data'] ?? null)) {
                $consultation['prescription_data'] = [];
            }

            attachMedicalCertificateToConsultation($pdo, $consultation);

            ob_clean();
            echo json_encode(['success' => true, 'consultation' => $consultation]);
            break;

        case 'save_certificate':
            $input = $requestBody;
            if (!$input) {
                $input = $_POST;
            }
            $consultId = (int)($input['consultation_id'] ?? 0);
            $result = saveMedicalCertificate($pdo, $consultId, $user_id, $input);
            ob_clean();
            echo json_encode($result);
            break;

        case 'get_doctor_signature':
            $stmt = $pdo->prepare("SELECT signature_data FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode([
                'success' => true,
                'signature_data' => $row['signature_data'] ?? null
            ]);
            break;

        case 'save_doctor_signature':
            $input = $requestBody;
            $sigData = $input['signature_data'] ?? null;
            if (!is_string($sigData) || strpos($sigData, 'data:image/') !== 0 || strlen($sigData) > 500000) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Draw a signature before saving.']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE users SET signature_data = ? WHERE id = ?");
            $stmt->execute([$sigData, $user_id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Doctor signature saved']);
            break;

        case 'delete_doctor_signature':
            $stmt = $pdo->prepare("UPDATE users SET signature_data = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Doctor signature removed']);
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Consultation Action Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
ob_end_flush();
?>
