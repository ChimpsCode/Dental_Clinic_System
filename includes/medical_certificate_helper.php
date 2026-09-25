<?php

function ensureDentistSignatureColumn(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature_data'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN signature_data MEDIUMTEXT NULL');
        }
    } catch (Exception $e) {
        error_log('Failed to ensure dentist signature column: ' . $e->getMessage());
    }
}

function ensureMedicalCertificateSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    ensureDentistSignatureColumn($pdo);
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS medical_certificates (
            id INT(11) NOT NULL AUTO_INCREMENT,
            consultation_id INT(11) NOT NULL,
            patient_id INT(11) NOT NULL,
            dentist_id INT(11) NOT NULL,
            purpose VARCHAR(100) NOT NULL DEFAULT 'Employment',
            findings TEXT NULL,
            recommendation TEXT NULL,
            rest_from DATE NULL,
            rest_to DATE NULL,
            remarks TEXT NULL,
            issued_at DATE NULL,
            dentist_name VARCHAR(150) NULL,
            dentist_signature MEDIUMTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_medcert_consultation (consultation_id),
            KEY idx_medcert_patient (patient_id),
            KEY idx_medcert_dentist (dentist_id),
            CONSTRAINT fk_medcert_consultation FOREIGN KEY (consultation_id) REFERENCES consultations (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_medcert_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_medcert_dentist FOREIGN KEY (dentist_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log('Failed to ensure medical_certificates table: ' . $e->getMessage());
    }
}

function ensureMedicalCertificateService(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    try {
        $stmt = $pdo->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
        $stmt->execute(['Medical Certificate']);
        if ($stmt->fetchColumn()) {
            return;
        }
        $description = 'Certificate for employment, issued after an in-clinic visit.';
        try {
            $insert = $pdo->prepare("INSERT INTO services (name, mode, price, duration_minutes, description, is_active, is_archived) VALUES (?, 'NONE', 0, 20, ?, 1, 0)");
            $insert->execute(['Medical Certificate', $description]);
        } catch (Exception $e) {
            $nextId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM services')->fetchColumn();
            $insert = $pdo->prepare("INSERT INTO services (id, name, mode, price, duration_minutes, description, is_active, is_archived) VALUES (?, ?, 'NONE', 0, 20, ?, 1, 0)");
            $insert->execute([$nextId, 'Medical Certificate', $description]);
        }
    } catch (Exception $e) {
        error_log('Failed to ensure Medical Certificate service: ' . $e->getMessage());
    }
}

function loadPatientCertificateProfile(PDO $pdo, int $patientId): array
{
    $profile = ['age' => '', 'gender' => '', 'address' => ''];
    if ($patientId <= 0) {
        return $profile;
    }
    try {
        $stmt = $pdo->prepare('SELECT date_of_birth, gender, street_address, city, province FROM patients WHERE id = ?');
        $stmt->execute([$patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare('SELECT date_of_birth, gender FROM patients WHERE id = ?');
            $stmt->execute([$patientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e2) {
            $row = [];
        }
    }
    if (!empty($row['date_of_birth'])) {
        try {
            $dob = new DateTime((string)$row['date_of_birth']);
            $profile['age'] = (string)$dob->diff(new DateTime('today'))->y;
        } catch (Exception $e) {
            $profile['age'] = '';
        }
    }
    $profile['gender'] = (string)($row['gender'] ?? '');
    $profile['address'] = implode(', ', array_filter([
        trim((string)($row['street_address'] ?? '')),
        trim((string)($row['city'] ?? '')),
        trim((string)($row['province'] ?? '')),
    ]));
    return $profile;
}

function attachMedicalCertificateToConsultation(PDO $pdo, array &$consultation): void
{
    ensureMedicalCertificateSchema($pdo);
    $consultId = (int)($consultation['id'] ?? 0);
    $patientId = (int)($consultation['patient_id'] ?? 0);
    $dentistId = (int)($consultation['dentist_id'] ?? 0);

    $cert = null;
    if ($consultId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM medical_certificates WHERE consultation_id = ? LIMIT 1');
        $stmt->execute([$consultId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($cert) {
            foreach (['rest_from', 'rest_to', 'issued_at'] as $dateField) {
                if (!empty($cert[$dateField])) {
                    $cert[$dateField] = substr((string)$cert[$dateField], 0, 10);
                }
            }
        }
    }
    $consultation['medical_certificate'] = $cert;

    $signature = null;
    $name = trim((string)($consultation['dentist_name'] ?? ''));
    if ($dentistId > 0) {
        $stmt = $pdo->prepare('SELECT signature_data, first_name, middle_name, last_name FROM users WHERE id = ?');
        $stmt->execute([$dentistId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $signature = $user['signature_data'] ?: null;
            $built = trim(implode(' ', array_filter([
                $user['first_name'] ?? '',
                $user['middle_name'] ?? '',
                $user['last_name'] ?? '',
            ])));
            if ($built !== '') {
                $name = $built;
            }
        }
    }
    $consultation['dentist_signature'] = $signature;
    $consultation['dentist_name'] = $name;

    $profile = loadPatientCertificateProfile($pdo, $patientId);
    $consultation['patient_age'] = $profile['age'];
    $consultation['patient_gender'] = $profile['gender'];
    $consultation['patient_address'] = $profile['address'];
}

function saveMedicalCertificate(PDO $pdo, int $consultId, int $userId, array $input): array
{
    ensureMedicalCertificateSchema($pdo);
    if ($consultId <= 0) {
        return ['success' => false, 'message' => 'Consultation ID required'];
    }

    $stmt = $pdo->prepare('SELECT id, patient_id, dentist_id FROM consultations WHERE id = ?');
    $stmt->execute([$consultId]);
    $consult = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$consult) {
        return ['success' => false, 'message' => 'Consultation not found'];
    }

    $purpose = trim((string)($input['purpose'] ?? ''));
    $findings = trim((string)($input['findings'] ?? ''));
    $recommendation = trim((string)($input['recommendation'] ?? ''));
    $remarks = trim((string)($input['remarks'] ?? ''));
    $issuedAt = trim((string)($input['issued_at'] ?? ''));
    $restFrom = trim((string)($input['rest_from'] ?? ''));
    $restTo = trim((string)($input['rest_to'] ?? ''));

    if ($purpose === '' || $findings === '' || $recommendation === '') {
        return ['success' => false, 'message' => 'Purpose, findings, and recommendation are required'];
    }
    if ($issuedAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedAt)) {
        $issuedAt = date('Y-m-d');
    }
    $restFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $restFrom) ? $restFrom : null;
    $restTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $restTo) ? $restTo : null;

    $userStmt = $pdo->prepare('SELECT signature_data, first_name, middle_name, last_name FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $liveSignature = (string)($user['signature_data'] ?? '');
    $liveName = trim(implode(' ', array_filter([
        $user['first_name'] ?? '',
        $user['middle_name'] ?? '',
        $user['last_name'] ?? '',
    ])));

    $existingStmt = $pdo->prepare('SELECT id, dentist_name, dentist_signature FROM medical_certificates WHERE consultation_id = ? LIMIT 1');
    $existingStmt->execute([$consultId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $signature = (string)($existing['dentist_signature'] ?? '');
        $dentistName = trim((string)($existing['dentist_name'] ?? ''));
        if ($signature === '' || strpos($signature, 'data:image/') !== 0) {
            if (strpos($liveSignature, 'data:image/') !== 0) {
                return ['success' => false, 'message' => 'Save your signature in Profile & Settings before issuing this certificate.'];
            }
            $signature = $liveSignature;
            $dentistName = $liveName;
        }
        $update = $pdo->prepare('UPDATE medical_certificates SET purpose = ?, findings = ?, recommendation = ?, rest_from = ?, rest_to = ?, remarks = ?, issued_at = ?, dentist_name = ?, dentist_signature = ? WHERE id = ?');
        $update->execute([$purpose, $findings, $recommendation, $restFrom, $restTo, $remarks !== '' ? $remarks : null, $issuedAt, $dentistName, $signature, (int)$existing['id']]);
    } else {
        if (strpos($liveSignature, 'data:image/') !== 0) {
            return ['success' => false, 'message' => 'Save your signature in Profile & Settings before issuing this certificate.'];
        }
        $insert = $pdo->prepare('INSERT INTO medical_certificates (consultation_id, patient_id, dentist_id, purpose, findings, recommendation, rest_from, rest_to, remarks, issued_at, dentist_name, dentist_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $consultId,
            (int)$consult['patient_id'],
            $userId > 0 ? $userId : (int)$consult['dentist_id'],
            $purpose,
            $findings,
            $recommendation,
            $restFrom,
            $restTo,
            $remarks !== '' ? $remarks : null,
            $issuedAt,
            $liveName,
            $liveSignature,
        ]);
    }

    $saved = $pdo->prepare('SELECT * FROM medical_certificates WHERE consultation_id = ? LIMIT 1');
    $saved->execute([$consultId]);
    $certificate = $saved->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($certificate) {
        foreach (['rest_from', 'rest_to', 'issued_at'] as $dateField) {
            if (!empty($certificate[$dateField])) {
                $certificate[$dateField] = substr((string)$certificate[$dateField], 0, 10);
            }
        }
    }

    return ['success' => true, 'message' => 'Medical certificate saved', 'certificate' => $certificate];
}
