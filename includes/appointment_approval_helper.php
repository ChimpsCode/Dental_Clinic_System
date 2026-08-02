<?php

const APPOINTMENT_STATUS_PENDING_APPROVAL = 'Pending Approval';
const APPOINTMENT_STATUS_SCHEDULED = 'Scheduled';
const APPOINTMENT_STATUS_CANCELLED = 'Cancelled';

function appointmentColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureAppointmentStatusEnumHasPendingApproval(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'status'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col || stripos($col['Type'], 'pending approval') !== false) {
        return;
    }
    if (!preg_match('/^enum\((.*)\)$/i', trim($col['Type']), $m)) {
        return;
    }
    preg_match_all("/'([^']*)'/", $m[1], $matches);
    $values = $matches[1];
    $values[] = APPOINTMENT_STATUS_PENDING_APPROVAL;
    $quoted = array_map(fn($value) => $pdo->quote($value), $values);
    $nullPart = strtoupper($col['Null']) === 'YES' ? 'NULL' : 'NOT NULL';
    $defaultPart = ($col['Default'] === null) ? '' : ' DEFAULT ' . $pdo->quote($col['Default']);
    $pdo->exec(
        "ALTER TABLE appointments MODIFY status ENUM(" . implode(',', $quoted) . ") " . $nullPart . $defaultPart
    );
}

function ensureAppointmentApprovalSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = [
        'approved_by' => "ALTER TABLE appointments ADD COLUMN approved_by INT NULL AFTER status",
        'approved_at' => "ALTER TABLE appointments ADD COLUMN approved_at DATETIME NULL AFTER approved_by",
    ];

    foreach ($columns as $column => $sql) {
        if (!appointmentColumnExists($pdo, 'appointments', $column)) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {}
        }
    }

    ensureAppointmentStatusEnumHasPendingApproval($pdo);

    $ensured = true;
}

function patientHasPendingAppointment(PDO $pdo, int $patientId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE patient_id = ?
          AND LOWER(status) = ?
          AND (is_archived = 0 OR is_archived IS NULL)
    ");
    $stmt->execute([$patientId, strtolower(APPOINTMENT_STATUS_PENDING_APPROVAL)]);
    return (int)$stmt->fetchColumn() > 0;
}

function patientHasActiveAppointmentOnDate(PDO $pdo, int $patientId, string $date): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE patient_id = ?
          AND DATE(appointment_date) = ?
          AND status IN ('Scheduled', 'In Queue', 'Pending Approval')
          AND (is_archived = 0 OR is_archived IS NULL)
    ");
    $stmt->execute([$patientId, $date]);
    return (int)$stmt->fetchColumn() > 0;
}

function autoDeclineExpiredPendingAppointments(PDO $pdo): int
{
    ensureAppointmentApprovalSchema($pdo);

    $stmt = $pdo->prepare("
        UPDATE appointments
        SET status = 'Cancelled',
            cancel_reason = 'Auto-declined: appointment date has already passed',
            cancelled_by = NULL,
            cancelled_at = NOW()
        WHERE LOWER(status) = 'pending approval'
          AND appointment_date < CURDATE()
          AND (is_archived = 0 OR is_archived IS NULL)
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

function appointmentSlotIsTaken(PDO $pdo, string $date, string $time, int $duration, int $excludeId = 0): bool
{
    $timeParts = explode(':', $time);
    $newStart = (int)$timeParts[0] * 60 + (int)$timeParts[1];
    $newEnd = $newStart + $duration;

    $stmt = $pdo->prepare("
        SELECT
            TIME_FORMAT(a.appointment_time, '%H:%i') AS start_time,
            COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date = ?
          AND a.id <> ?
          AND (a.is_archived = 0 OR a.is_archived IS NULL)
          AND LOWER(COALESCE(a.status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show', 'pending approval')
    ");
    $stmt->execute([$date, $excludeId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $booking) {
        $parts = explode(':', $booking['start_time']);
        $exStart = (int)$parts[0] * 60 + (int)$parts[1];
        $exEnd = $exStart + (int)$booking['duration_minutes'];
        if ($newStart < $exEnd && $newEnd > $exStart) {
            return true;
        }
    }

    return false;
}
