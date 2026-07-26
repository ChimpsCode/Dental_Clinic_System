<?php

function ensureQueueManagementSchema(PDO $pdo): void
{
    static $schemaReady = false;

    if ($schemaReady) {
        return;
    }

    $schemaReady = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            appointment_id INT NULL,
            queue_number INT NULL,
            queue_label VARCHAR(20) NULL,
            queue_date DATE NULL,
            treatment_type VARCHAR(200) NULL,
            doctor_id INT NULL,
            teeth_numbers VARCHAR(500) NULL,
            procedure_notes TEXT NULL,
            status ENUM('waiting', 'in_procedure', 'pending_payment', 'completed', 'cancelled', 'on_hold') DEFAULT 'waiting',
            completed_at TIMESTAMP NULL DEFAULT NULL,
            is_processed TINYINT(1) DEFAULT 0,
            priority INT DEFAULT 5,
            queue_time TIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL,
            INDEX idx_patient_id (patient_id),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_queue_time (queue_time),
            INDEX idx_queue_date (queue_date),
            INDEX idx_queue_number (queue_number),
            INDEX idx_queue_appointment_id (appointment_id),
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [];
    $columnStmt = $pdo->query("SHOW COLUMNS FROM queue");
    foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = $column;
    }

    $requiredColumns = [
        'appointment_id' => "ALTER TABLE queue ADD COLUMN appointment_id INT NULL AFTER patient_id",
        'queue_number' => "ALTER TABLE queue ADD COLUMN queue_number INT NULL AFTER appointment_id",
        'queue_label' => "ALTER TABLE queue ADD COLUMN queue_label VARCHAR(20) NULL AFTER queue_number",
        'queue_date' => "ALTER TABLE queue ADD COLUMN queue_date DATE NULL AFTER queue_label",
        'doctor_id' => "ALTER TABLE queue ADD COLUMN doctor_id INT NULL AFTER treatment_type",
        'status_id' => "ALTER TABLE queue ADD COLUMN status_id INT NULL DEFAULT 1 AFTER status"
    ];

    foreach ($requiredColumns as $field => $sql) {
        if (!isset($columns[$field])) {
            try {
                $pdo->exec($sql);
                $columns[$field] = ['Field' => $field];
            } catch (Exception $e) {
                // Column might already exist or error, continue
            }
        }
    }
    
    // Add foreign key for status_id if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE queue ADD CONSTRAINT fk_queue_status FOREIGN KEY (status_id) REFERENCES queue_statuses(id) ON DELETE SET NULL ON UPDATE CASCADE");
    } catch (Exception $e) {
        // FK might already exist
    }

    $indexes = [];
    $indexStmt = $pdo->query("SHOW INDEX FROM queue");
    foreach ($indexStmt->fetchAll(PDO::FETCH_ASSOC) as $indexRow) {
        $indexes[$indexRow['Key_name']] = true;
    }

    if (!isset($indexes['idx_queue_date'])) {
        $pdo->exec("ALTER TABLE queue ADD INDEX idx_queue_date (queue_date)");
    }

    if (!isset($indexes['idx_queue_number'])) {
        $pdo->exec("ALTER TABLE queue ADD INDEX idx_queue_number (queue_number)");
    }

    if (!isset($indexes['idx_queue_appointment_id'])) {
        $pdo->exec("ALTER TABLE queue ADD INDEX idx_queue_appointment_id (appointment_id)");
    }

    if (!isset($indexes['idx_queue_doctor_id'])) {
        $pdo->exec("ALTER TABLE queue ADD INDEX idx_queue_doctor_id (doctor_id)");
    }

    $pdo->exec("
        UPDATE queue
        SET queue_date = DATE(created_at)
        WHERE queue_date IS NULL
    ");

    // Backfill queue_number for existing entries that are missing it, ordered by creation time per day
    try {
        $dailyGroups = $pdo->query("
            SELECT DISTINCT COALESCE(queue_date, DATE(created_at)) as qdate
            FROM queue
            WHERE queue_number IS NULL
            ORDER BY qdate ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($dailyGroups as $qdate) {
            $stmt = $pdo->prepare("
                SELECT id FROM queue
                WHERE COALESCE(queue_date, DATE(created_at)) = ?
                  AND queue_number IS NULL
                ORDER BY queue_time ASC, created_at ASC
            ");
            $stmt->execute([$qdate]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $num = 1;
            foreach ($rows as $rowId) {
                $upd = $pdo->prepare("UPDATE queue SET queue_number = ?, queue_label = ? WHERE id = ?");
                $upd->execute([$num, '#' . $num, $rowId]);
                $num++;
            }
        }
    } catch (Exception $e) {
        // Backfill is best-effort
    }
}

function getTodayQueueDateSql(string $alias = 'q'): string
{
    return "COALESCE($alias.queue_date, DATE($alias.created_at))";
}

function buildQueueLabel(int $queueNumber): string
{
    return '#' . $queueNumber;
}

function archiveStaleQueueEntries(PDO $pdo, string $today = ''): int
{
    ensureQueueManagementSchema($pdo);

    if ($today === '') {
        $today = date('Y-m-d');
    }

    $stmt = $pdo->prepare("
        UPDATE queue
        SET status = 'cancelled',
            notes = CONCAT(COALESCE(notes, ''), ' [Auto-archived: stale entry from previous day]'),
            updated_at = NOW()
        WHERE status IN ('waiting', 'in_procedure', 'on_hold')
          AND COALESCE(queue_date, DATE(created_at)) < ?
          AND COALESCE(is_archived, 0) = 0
    ");
    $stmt->execute([$today]);
    return $stmt->rowCount();
}

function getNextDailyQueueNumber(PDO $pdo, string $queueDate): int
{
    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(queue_number), 0)
        FROM queue
        WHERE COALESCE(queue_date, DATE(created_at)) = ?
          AND COALESCE(is_archived, 0) = 0
    ");
    $stmt->execute([$queueDate]);

    return ((int)$stmt->fetchColumn()) + 1;
}

function fetchQueueEntryByAppointment(PDO $pdo, int $appointmentId, string $queueDate): ?array
{
    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM queue
        WHERE appointment_id = ?
          AND COALESCE(queue_date, DATE(created_at)) = ?
          AND COALESCE(is_archived, 0) = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$appointmentId, $queueDate]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record ?: null;
}

function fetchLatestQueueEntryForPatient(PDO $pdo, int $patientId, string $queueDate): ?array
{
    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM queue
        WHERE patient_id = ?
          AND COALESCE(queue_date, DATE(created_at)) = ?
          AND COALESCE(is_archived, 0) = 0
        ORDER BY queue_number ASC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$patientId, $queueDate]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    return $record ?: null;
}

function createQueueEntry(PDO $pdo, array $payload): array
{
    ensureQueueManagementSchema($pdo);

    $patientId = (int)($payload['patient_id'] ?? 0);
    if ($patientId <= 0) {
        throw new InvalidArgumentException('Patient ID is required for queue creation.');
    }

    $queueDate = (string)($payload['queue_date'] ?? date('Y-m-d'));
    $appointmentId = isset($payload['appointment_id']) && $payload['appointment_id'] !== '' ? (int)$payload['appointment_id'] : null;

    if ($appointmentId) {
        $existing = fetchQueueEntryByAppointment($pdo, $appointmentId, $queueDate);
        if ($existing) {
            return $existing;
        }
    }

    $queueNumber = isset($payload['queue_number']) && (int)$payload['queue_number'] > 0
        ? (int)$payload['queue_number']
        : getNextDailyQueueNumber($pdo, $queueDate);
    $queueLabel = (string)($payload['queue_label'] ?? buildQueueLabel($queueNumber));
    $status = (string)($payload['status'] ?? 'waiting');
    $treatmentType = trim((string)($payload['treatment_type'] ?? 'General Checkup'));
    $teethNumbers = trim((string)($payload['teeth_numbers'] ?? ''));
    $procedureNotes = trim((string)($payload['procedure_notes'] ?? ''));
    $notes = trim((string)($payload['notes'] ?? ''));
    $priority = isset($payload['priority']) ? (int)$payload['priority'] : 5;
    $queueTime = trim((string)($payload['queue_time'] ?? date('H:i:s')));
    $doctorId = isset($payload['doctor_id']) && $payload['doctor_id'] !== '' ? (int)$payload['doctor_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO queue (
            patient_id,
            appointment_id,
            queue_number,
            queue_label,
            queue_date,
            treatment_type,
            doctor_id,
            teeth_numbers,
            procedure_notes,
            status,
            priority,
            queue_time,
            notes,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $patientId,
        $appointmentId,
        $queueNumber,
        $queueLabel,
        $queueDate,
        $treatmentType !== '' ? $treatmentType : 'General Checkup',
        $doctorId,
        $teethNumbers !== '' ? $teethNumbers : null,
        $procedureNotes !== '' ? $procedureNotes : null,
        $status,
        $priority,
        $queueTime !== '' ? $queueTime : null,
        $notes !== '' ? $notes : null
    ]);

    $queueId = (int)$pdo->lastInsertId();
    $selectStmt = $pdo->prepare("SELECT * FROM queue WHERE id = ? LIMIT 1");
    $selectStmt->execute([$queueId]);

    $record = $selectStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        throw new RuntimeException('Queue record was created but could not be loaded.');
    }

    return $record;
}

function getQueueDisplayStatus(string $status): string
{
    switch (strtolower(trim($status))) {
        case 'in_procedure':
            return 'Serving';
        case 'completed':
        case 'pending_payment':
            return 'Done';
        case 'waiting':
            return 'Waiting';
        case 'on_hold':
            return 'On Hold';
        case 'cancelled':
            return 'Cancelled';
        default:
            return ucwords(str_replace('_', ' ', trim($status)));
    }
}
