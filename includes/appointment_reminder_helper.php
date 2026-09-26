<?php

/**
 * Automatic appointment reminders.
 * One SMS and bell notice 24 hours before the visit, and another 2 hours before.
 * Each is sent once. Cancelled, completed, and not-yet-approved visits are skipped.
 * SMS is sent only when the patient turned SMS notifications on.
 */

function ensureAppointmentReminderSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (!function_exists('appointmentColumnExists')) {
        require_once __DIR__ . '/appointment_approval_helper.php';
    }
    if (!appointmentColumnExists($pdo, 'appointments', 'reminded_24h_at')) {
        $pdo->exec('ALTER TABLE appointments ADD COLUMN reminded_24h_at DATETIME NULL DEFAULT NULL');
    }
    if (!appointmentColumnExists($pdo, 'appointments', 'reminded_2h_at')) {
        $pdo->exec('ALTER TABLE appointments ADD COLUMN reminded_2h_at DATETIME NULL DEFAULT NULL');
    }
    $done = true;
}

function sendDueAppointmentReminders(PDO $pdo, bool $ignoreThrottle = false): void
{
    try {
        ensureAppointmentReminderSchema($pdo);

        $stamp = dirname(__DIR__) . '/cache/.appointment_reminders_last_run';
        if (!$ignoreThrottle && is_file($stamp)) {
            $last = (int)@file_get_contents($stamp);
            if ($last > 0 && (time() - $last) < 60) {
                return;
            }
        }
        $dir = dirname($stamp);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($stamp, (string)time());

        require_once __DIR__ . '/notification_functions.php';
        require_once __DIR__ . '/sms_functions.php';
        if (function_exists('ensureNotificationSchema')) {
            ensureNotificationSchema($pdo);
        }

        foreach (findDueAppointmentReminders($pdo) as $row) {
            deliverAppointmentReminder($pdo, $row, (string)$row['reminder_kind']);
        }
    } catch (Throwable $e) {
        error_log('Appointment reminders failed: ' . $e->getMessage());
    }
}

function findDueAppointmentReminders(PDO $pdo): array
{
    $appointmentAt = 'TIMESTAMP(a.appointment_date, a.appointment_time)';
    $base = "
        SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time,
               TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.middle_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_name,
               p.phone,
               COALESCE(s.name, 'General Checkup') AS service_name,
               '%s' AS reminder_kind
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        LEFT JOIN services s ON s.id = a.service_id
        WHERE COALESCE(a.is_archived, 0) = 0
          AND a.deleted_at IS NULL
          AND a.check_in_time IS NULL
          AND a.patient_id IS NOT NULL
          AND (
                LOWER(TRIM(a.status)) = 'scheduled'
                OR (TRIM(COALESCE(a.status, '')) = '' AND a.approved_at IS NOT NULL)
              )
    ";

    $sql24 = sprintf($base, '24h') . "
          AND a.reminded_24h_at IS NULL
          AND {$appointmentAt} <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND {$appointmentAt} > DATE_ADD(NOW(), INTERVAL 2 HOUR)
          AND a.created_at <= DATE_SUB({$appointmentAt}, INTERVAL 24 HOUR)
    ";
    $sql2 = sprintf($base, '2h') . "
          AND a.reminded_2h_at IS NULL
          AND {$appointmentAt} <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
          AND {$appointmentAt} > NOW()
          AND a.created_at <= DATE_SUB({$appointmentAt}, INTERVAL 2 HOUR)
    ";

    $due = $pdo->query($sql24)->fetchAll(PDO::FETCH_ASSOC);
    $soon = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
    return array_merge($due, $soon);
}

function deliverAppointmentReminder(PDO $pdo, array $row, string $kind): void
{
    $column = $kind === '2h' ? 'reminded_2h_at' : 'reminded_24h_at';
    $hoursBefore = $kind === '2h' ? 2 : 24;
    $appointmentId = (int)$row['id'];

    $claim = $pdo->prepare("UPDATE appointments SET {$column} = NOW() WHERE id = ? AND {$column} IS NULL");
    $claim->execute([$appointmentId]);
    if ($claim->rowCount() !== 1) {
        return;
    }

    $patientId = (int)($row['patient_id'] ?? 0);
    $serviceName = trim((string)($row['service_name'] ?? ''));
    if ($serviceName === '') {
        $serviceName = 'General Checkup';
    }
    $patientName = preg_replace('/\s+/', ' ', trim((string)($row['patient_name'] ?? '')));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    $contact = appointmentReminderContact($pdo, $patientId, $row['phone'] ?? '');
    $smsError = null;
    if ($contact['sms_enabled'] && $contact['phone'] !== '') {
        try {
            $result = sendAppointmentReminderSMS(
                $contact['phone'],
                $patientName,
                $row['appointment_date'],
                $row['appointment_time'],
                $serviceName,
                $hoursBefore,
                true
            );
            if (empty($result['success'])) {
                $smsError = (string)($result['error'] ?? 'Unknown SMS error');
            }
        } catch (Throwable $e) {
            $smsError = $e->getMessage();
        }
    }

    if ($smsError !== null && !appointmentReminderSmsErrorIsPermanent($smsError)) {
        $pdo->prepare("UPDATE appointments SET {$column} = NULL WHERE id = ?")->execute([$appointmentId]);
        error_log("Appointment reminder {$kind} failed for appointment {$appointmentId}: {$smsError}");
        return;
    }

    if ($smsError !== null) {
        error_log("Appointment reminder {$kind} SMS skipped for appointment {$appointmentId}: {$smsError}");
    }

    if ($patientId > 0) {
        notifyPatientAppointmentReminder(
            $pdo,
            $patientId,
            (string)$row['appointment_date'],
            (string)$row['appointment_time'],
            $serviceName,
            $hoursBefore
        );
    }
}

function appointmentReminderContact(PDO $pdo, int $patientId, $patientPhone): array
{
    $phone = trim((string)$patientPhone);
    if ($patientId <= 0) {
        return ['phone' => $phone, 'sms_enabled' => false];
    }

    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return ['phone' => $phone, 'sms_enabled' => false];
    }

    static $hasSmsColumn = null;
    if ($hasSmsColumn === null) {
        $hasSmsColumn = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'sms_notifications'")->fetch(PDO::FETCH_ASSOC);
    }
    if (!$hasSmsColumn) {
        return ['phone' => $phone, 'sms_enabled' => false];
    }

    $stmt = $pdo->prepare('SELECT sms_notifications, phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['phone' => $phone, 'sms_enabled' => false];
    }

    if ($phone === '' && !empty($user['phone'])) {
        $phone = trim((string)$user['phone']);
    }

    return [
        'phone' => $phone,
        'sms_enabled' => (int)($user['sms_notifications'] ?? 0) === 1,
    ];
}

function appointmentReminderSmsErrorIsPermanent(string $error): bool
{
    return stripos($error, 'not configured') !== false;
}
