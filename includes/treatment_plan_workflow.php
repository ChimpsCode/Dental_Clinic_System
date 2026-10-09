<?php

function ensureTreatmentPlanWorkflowSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $columns = $pdo->query('SHOW COLUMNS FROM appointments')->fetchAll(PDO::FETCH_COLUMN);
    $changes = [];
    if (!in_array('treatment_plan_id', $columns, true)) $changes[] = 'ADD COLUMN IF NOT EXISTS treatment_plan_id INT NULL, ADD INDEX idx_appointment_treatment_plan (treatment_plan_id)';
    if (!in_array('treatment_plan_session', $columns, true)) $changes[] = 'ADD COLUMN IF NOT EXISTS treatment_plan_session INT NULL, ADD UNIQUE KEY uq_treatment_plan_session (treatment_plan_id, treatment_plan_session)';
    if ($changes) $pdo->exec('ALTER TABLE appointments ' . implode(', ', $changes));
    $hasSessionIndex = $pdo->query("SHOW INDEX FROM appointments WHERE Key_name = 'uq_treatment_plan_session'")->fetch();
    if (!$hasSessionIndex) $pdo->exec('ALTER TABLE appointments ADD UNIQUE KEY uq_treatment_plan_session (treatment_plan_id, treatment_plan_session)');
    $statusColumn = $pdo->query("SHOW COLUMNS FROM treatment_plans LIKE 'overall_status'")->fetch();
    if (strpos($statusColumn['Type'], "'In-Progress'") === false) {
        $pdo->exec("ALTER TABLE treatment_plans MODIFY overall_status ENUM('Active','In-Progress','Completed','On-Hold','Cancelled') DEFAULT 'Active'");
    }
    $ready = true;
    if ($changes) {
        foreach ($pdo->query('SELECT * FROM treatment_plans')->fetchAll() as $plan) linkLegacyTreatmentPlanSessions($pdo, $plan);
    }
}

// Match legacy sessions only when the appointment belongs to the same patient
// and has the recorded date/time. Never guess a link from the patient's ID alone.
function linkLegacyTreatmentPlanSessions(PDO $pdo, array $plan): void
{
    $sessions = json_decode($plan['sessions_data'] ?? '', true) ?: [];
    foreach ($sessions as $index => $session) {
        if (empty($session['date']) || empty($session['time'])) continue;
        $stmt = $pdo->prepare('SELECT a.id FROM appointments a LEFT JOIN services s ON s.id = a.service_id
            WHERE a.patient_id = ? AND a.appointment_date = ? AND a.appointment_time = ?
            AND a.treatment_plan_id IS NULL AND (a.id = ? OR s.name = ? OR a.service_id IS NULL)');
        $stmt->execute([$plan['patient_id'], $session['date'], $session['time'], $index === 0 ? ($plan['appointment_id'] ?? 0) : 0, $session['treatment'] ?? '']);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) === 1) {
            $pdo->prepare('UPDATE appointments SET treatment_plan_id = ?, treatment_plan_session = ? WHERE id = ? AND treatment_plan_id IS NULL')
                ->execute([$plan['id'], $index + 1, $ids[0]]);
        }
    }
}

function syncTreatmentPlanProgress(PDO $pdo, int $planId, bool $persist = true): array
{
    $ownsTransaction = $persist && !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM treatment_plans WHERE id = ?' . ($persist ? ' FOR UPDATE' : ''));
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        if (!$plan) throw new RuntimeException('Treatment plan not found');
        $sessions = json_decode($plan['sessions_data'] ?? '', true) ?: [];
        $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name,
            (SELECT MAX(CASE q.status WHEN 'completed' THEN 3 WHEN 'pending_payment' THEN 3 WHEN 'in_procedure' THEN 2 WHEN 'waiting' THEN 1 ELSE 0 END)
             FROM queue q WHERE q.appointment_id = a.id) AS queue_progress
            FROM appointments a LEFT JOIN services s ON s.id = a.service_id
            WHERE a.treatment_plan_id = ? ORDER BY a.treatment_plan_session");
        $stmt->execute([$planId]);
        $linkedSessions = [];
        foreach ($stmt->fetchAll() as $appointment) {
            $index = (int)$appointment['treatment_plan_session'] - 1;
            if (!isset($sessions[$index])) continue;
            $linkedSessions[$index] = true;
            $session = &$sessions[$index];
            $session['appointment_id'] = (int)$appointment['id'];
            $session['date'] = $appointment['appointment_date'];
            $session['time'] = $appointment['appointment_time'];
            $session['service_id'] = $appointment['service_id'];
            $session['duration_minutes'] = (int)($appointment['duration_minutes'] ?? 30);
            $session['dentist_id'] = $appointment['dentist_id'];
            if ($appointment['service_name']) $session['treatment'] = $appointment['service_name'];
            if ($appointment['status'] === 'Completed' || (int)$appointment['queue_progress'] === 3) $session['status'] = 'Completed';
            elseif ($appointment['status'] === 'Cancelled' || (int)$appointment['is_archived']) $session['status'] = 'Cancelled';
            elseif ((int)$appointment['queue_progress'] === 2 || ($session['status'] ?? '') === 'In-Progress') $session['status'] = 'In-Progress';
            elseif ($appointment['status'] === 'In Queue' || (int)$appointment['queue_progress'] === 1) $session['status'] = 'Waiting';
            else $session['status'] = 'Scheduled';
            unset($session);
        }
        foreach ($sessions as $index => &$session) {
            if (!isset($linkedSessions[$index])) {
                unset($session['appointment_id']);
                $session['status'] = 'Appointment missing';
            }
        }
        unset($session);
        $missing = count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'Appointment missing'));
        $completed = count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'Completed'));
        $cancelled = count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'Cancelled'));
        $started = count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'In-Progress'));
        $total = max((int)$plan['total_sessions'], count($sessions));
        $status = $completed === $total ? 'Completed' : ($completed + $cancelled === $total ? 'Cancelled' : ($started || $completed ? 'In-Progress' : 'Active'));
        if ($missing || count($sessions) < $total) $status = 'On-Hold';
        $upcoming = array_values(array_filter($sessions, fn($s) => !in_array($s['status'] ?? '', ['Completed', 'Cancelled', 'Appointment missing'], true) && !empty($s['date'])));
        usort($upcoming, fn($a, $b) => strcmp($a['date'] . ($a['time'] ?? ''), $b['date'] . ($b['time'] ?? '')));
        $next = $upcoming[0] ?? [];
        if ($persist) $pdo->prepare('UPDATE treatment_plans SET sessions_data = ?, completed_sessions = ?, overall_status = ?, next_session_date = ?, next_session_time = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($sessions), $completed, $status, $next['date'] ?? null, $next['time'] ?? null, $planId]);
        if ($ownsTransaction) $pdo->commit();
        $plan['sessions_data'] = json_encode($sessions);
        $plan['completed_sessions'] = $completed;
        $plan['overall_status'] = $status;
        $plan['next_session_date'] = $next['date'] ?? null;
        $plan['next_session_time'] = $next['time'] ?? null;
        $plan['missing_appointments'] = $missing + max(0, $total - count($sessions));
        return $plan;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function assertTreatmentPlanAppointmentEditable(PDO $pdo, int $appointmentId): void
{
    $stmt = $pdo->prepare("SELECT a.treatment_plan_id, a.status,
        (SELECT COUNT(*) FROM queue q WHERE q.appointment_id = a.id AND q.status IN ('waiting','in_procedure','pending_payment','completed')) AS entered_queue
        FROM appointments a WHERE a.id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    if ($appointment && $appointment['treatment_plan_id'] && ($appointment['status'] !== 'Scheduled' || (int)$appointment['entered_queue'])) {
        throw new RuntimeException('Only a scheduled treatment plan session can be rescheduled or cancelled. This session has already entered the queue or is closed.');
    }
}

function changeTreatmentPlanSchedule(PDO $pdo, int $planId, string $action, int $sessionNumber = 0, string $date = '', string $time = ''): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM treatment_plans WHERE id = ? FOR UPDATE');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        if (!$plan) throw new RuntimeException('Treatment plan not found');
        linkLegacyTreatmentPlanSessions($pdo, $plan);
        if ($action === 'cancel_plan') {
            $stmt = $pdo->prepare("SELECT id FROM appointments WHERE treatment_plan_id = ? AND status NOT IN ('Completed','Cancelled') FOR UPDATE");
            $stmt->execute([$planId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) throw new RuntimeException('There are no remaining sessions to cancel');
            foreach ($ids as $id) assertTreatmentPlanAppointmentEditable($pdo, (int)$id);
            $pdo->prepare("UPDATE appointments SET status = 'Cancelled', cancel_reason = 'Treatment plan permanently cancelled' WHERE treatment_plan_id = ? AND status = 'Scheduled'")->execute([$planId]);
        } elseif ($action === 'reschedule_session' || $action === 'cancel_session') {
            $stmt = $pdo->prepare('SELECT * FROM appointments WHERE treatment_plan_id = ? AND treatment_plan_session = ? FOR UPDATE');
            $stmt->execute([$planId, $sessionNumber]);
            $appointment = $stmt->fetch();
            if (!$appointment) throw new RuntimeException('Scheduled session not found');
            assertTreatmentPlanAppointmentEditable($pdo, (int)$appointment['id']);
            if ($action === 'reschedule_session') {
                assertTreatmentPlanSlotAvailable($pdo, $date, $time, (int)($appointment['duration_minutes'] ?: 30), (int)$appointment['id'], (int)$appointment['dentist_id']);
                $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE id = ?')->execute([$date, $time, $appointment['id']]);
            } else {
                $pdo->prepare("UPDATE appointments SET status = 'Cancelled', cancel_reason = 'Treatment plan session permanently cancelled' WHERE id = ?")->execute([$appointment['id']]);
            }
        } else throw new RuntimeException('Invalid treatment plan action');
        syncTreatmentPlanProgress($pdo, $planId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function syncTreatmentPlanForAppointment(PDO $pdo, int $appointmentId): void
{
    if (!$appointmentId) return;
    $stmt = $pdo->prepare('SELECT treatment_plan_id, patient_id FROM appointments WHERE id = ?');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    if (!$appointment) return;
    if (empty($appointment['treatment_plan_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM treatment_plans WHERE patient_id = ?');
        $stmt->execute([$appointment['patient_id']]);
        foreach ($stmt->fetchAll() as $plan) linkLegacyTreatmentPlanSessions($pdo, $plan);
        $stmt = $pdo->prepare('SELECT treatment_plan_id FROM appointments WHERE id = ?');
        $stmt->execute([$appointmentId]);
        $appointment['treatment_plan_id'] = $stmt->fetchColumn();
    }
    if (!empty($appointment['treatment_plan_id'])) syncTreatmentPlanProgress($pdo, (int)$appointment['treatment_plan_id']);
}

function syncTreatmentPlanForQueue(PDO $pdo, int $queueId, bool $complete = false): ?int
{
    $stmt = $pdo->prepare('SELECT appointment_id FROM queue WHERE id = ?');
    $stmt->execute([$queueId]);
    $appointmentId = (int)$stmt->fetchColumn();
    if (!$appointmentId) return null;
    if ($complete) $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ? AND status <> 'Cancelled'")->execute([$appointmentId]);
    syncTreatmentPlanForAppointment($pdo, $appointmentId);
    return $appointmentId;
}

function assertTreatmentPlanClinicHours(PDO $pdo, string $date, string $time, int $duration, int $doctorId): void
{
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_hours','unavailable_dates')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $hours = json_decode($settings['business_hours'] ?? '', true) ?: [];
    $day = strtolower(date('l', strtotime($date)));
    $dayHours = $hours[$day] ?? ($day === 'sunday' ? ['is_open' => false] : ['is_open' => true, 'open' => $day === 'saturday' ? '09:00' : '08:00', 'close' => $day === 'saturday' ? '13:00' : '17:00']);
    foreach (json_decode($settings['unavailable_dates'] ?? '', true) ?: [] as $entry) {
        if (($entry['date'] ?? '') !== $date) continue;
        if (in_array($entry['status'] ?? '', ['closed','holiday'], true)) throw new RuntimeException('The clinic is closed on this date');
        if (($entry['status'] ?? '') === 'custom') $dayHours = ['is_open' => true, 'open' => $entry['custom_open'] ?? '08:00', 'close' => $entry['custom_close'] ?? '17:00'];
        if (($entry['status'] ?? '') === 'half_am') $dayHours = ['is_open' => true, 'open' => $dayHours['open'] ?? '08:00', 'close' => '12:00'];
        if (($entry['status'] ?? '') === 'half_pm') $dayHours = ['is_open' => true, 'open' => '13:00', 'close' => $dayHours['close'] ?? '17:00'];
    }
    if (empty($dayHours['is_open'])) throw new RuntimeException('The clinic is closed on this day');
    $minutes = fn($value) => (int)substr($value, 0, 2) * 60 + (int)substr($value, 3, 2);
    $start = $minutes($time); $open = $minutes($dayHours['open']); $close = $minutes($dayHours['close']);
    if ($doctorId) {
        $stmt = $pdo->prepare('SELECT * FROM schedules WHERE doctor_id = ? AND available_day = ?');
        $stmt->execute([$doctorId, ucfirst($day)]);
        $schedule = $stmt->fetch();
        $dentistOpen = !$schedule || (int)$schedule['is_available'];
        $doctorOpen = $schedule ? $minutes($schedule['start_time']) : $open;
        $doctorClose = $schedule ? $minutes($schedule['end_time']) : $close;
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dentist_unavailable'")->fetchColumn();
        if ($exists) {
            $stmt = $pdo->prepare('SELECT * FROM dentist_unavailable WHERE dentist_id = ? AND unavailable_date = ?');
            $stmt->execute([$doctorId, $date]);
            $override = $stmt->fetch();
            if ($override) {
                $dentistOpen = !(int)$override['is_full_day'];
                if ($dentistOpen) { $doctorOpen = $minutes($override['start_time']); $doctorClose = $minutes($override['end_time']); }
            }
        }
        if (!$dentistOpen) throw new RuntimeException('The dentist is unavailable on this date');
        $open = max($open, $doctorOpen); $close = min($close, $doctorClose);
    }
    if ($start < $open || $start + $duration > $close) throw new RuntimeException('This session is outside available clinic or dentist hours');
}

function assertTreatmentPlanSlotAvailable(PDO $pdo, string $date, string $time, int $duration, int $excludeAppointment = 0, int $doctorId = 0): void
{
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) throw new RuntimeException('Invalid session date or time');
    $when = new DateTimeImmutable($date . ' ' . $time, new DateTimeZone('Asia/Shanghai'));
    if ($when <= new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'))) throw new RuntimeException('Choose a future date and time');
    assertTreatmentPlanClinicHours($pdo, $date, $time, $duration, $doctorId);
    $stmt = $pdo->prepare("SELECT a.appointment_time AS start_time, COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
        FROM appointments a LEFT JOIN services s ON s.id = a.service_id WHERE a.appointment_date = ? AND a.id <> ?
        AND COALESCE(a.is_archived, 0) = 0 AND a.status NOT IN ('Cancelled','Completed','Pending Approval') FOR UPDATE");
    $stmt->execute([$date, $excludeAppointment]);
    $bookings = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT i.confirmed_time AS start_time, COALESCE(s.duration_minutes, 30) AS duration_minutes
        FROM inquiries i LEFT JOIN services s ON s.id = i.service_id WHERE i.confirmed_date = ?
        AND COALESCE(i.is_archived, 0) = 0 AND i.confirmed_time IS NOT NULL
        AND LOWER(i.status) IN ('scheduled','awaiting confirmation','contacted')
        AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.inquiry_id = i.id AND a.id = ?)");
    $stmt->execute([$date, $excludeAppointment]);
    $bookings = array_merge($bookings, $stmt->fetchAll());
    $start = (int)substr($time, 0, 2) * 60 + (int)substr($time, 3, 2);
    foreach ($bookings as $booking) {
        $other = (int)substr($booking['start_time'], 0, 2) * 60 + (int)substr($booking['start_time'], 3, 2);
        if ($start < $other + (int)$booking['duration_minutes'] && $start + $duration > $other) throw new RuntimeException('This session overlaps an existing booking. Choose another time.');
    }
}

function reserveTreatmentPlanSessions(PDO $pdo, int $planId, int $patientId, int $doctorId, array $sessions): array
{
    $result = [];
    foreach ($sessions as $index => &$session) {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE treatment_plan_id = ? AND treatment_plan_session = ? FOR UPDATE');
        $stmt->execute([$planId, $index + 1]);
        $existing = $stmt->fetch();
        if ($existing && in_array($existing['status'], ['Completed', 'In Queue', 'Pending Payment', 'Cancelled'], true)) {
            if ($existing['appointment_date'] !== $session['date'] || substr($existing['appointment_time'], 0, 5) !== substr($session['time'], 0, 5)) throw new RuntimeException('Started, completed or cancelled sessions cannot be changed in the plan form');
            continue;
        }
        $stmt = $pdo->prepare('SELECT id, duration_minutes FROM services WHERE name = ? LIMIT 1');
        $stmt->execute([$session['treatment'] ?? '']);
        $service = $stmt->fetch();
        $duration = max(1, (int)($service['duration_minutes'] ?? 30));
        assertTreatmentPlanSlotAvailable($pdo, $session['date'], $session['time'], $duration, (int)($existing['id'] ?? 0), $doctorId);
        if ($existing) {
            $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ?, service_id = ?, duration_minutes = ?, dentist_id = ? WHERE id = ?')
                ->execute([$session['date'], $session['time'], $service['id'] ?? null, $duration, $doctorId, $existing['id']]);
            $appointmentId = (int)$existing['id'];
        } else {
            $pdo->prepare("INSERT INTO appointments (reference_code, patient_id, service_id, dentist_id, status, appointment_date, appointment_time, duration_minutes, booked_by_id, created_by, is_archived, treatment_plan_id, treatment_plan_session, notes)
                VALUES (?, ?, ?, ?, 'Scheduled', ?, ?, ?, ?, ?, 0, ?, ?, ?)")
                ->execute(['APT' . strtoupper(bin2hex(random_bytes(6))), $patientId, $service['id'] ?? null, $doctorId, $session['date'], $session['time'], $duration, $doctorId, $doctorId, $planId, $index + 1, 'Treatment plan session ' . ($index + 1) . ': ' . ($session['treatment'] ?? '')]);
            $appointmentId = (int)$pdo->lastInsertId();
        }
        $session['appointment_id'] = $appointmentId;
        $session['status'] = 'Scheduled';
        $result[] = ['date' => $session['date'], 'time' => $session['time'], 'treatment' => $session['treatment'] ?? '', 'appointment_id' => $appointmentId];
    }
    unset($session);
    $pdo->prepare('UPDATE treatment_plans SET sessions_data = ?, appointment_id = (SELECT id FROM appointments WHERE treatment_plan_id = ? ORDER BY treatment_plan_session LIMIT 1) WHERE id = ?')
        ->execute([json_encode($sessions), $planId, $planId]);
    syncTreatmentPlanProgress($pdo, $planId);
    return $result;
}
