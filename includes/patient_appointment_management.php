<?php
require_once __DIR__ . '/appointment_approval_helper.php';
require_once __DIR__ . '/appointment_slots_helper.php';
require_once __DIR__ . '/appointment_cancellation_requests.php';

function ensurePatientAppointmentManagementSchema(PDO $pdo): void
{
    ensureCancellationRequestSchema($pdo);
    if (!appointmentColumnExists($pdo, 'appointments', 'patient_edited_at')) {
        $pdo->exec('ALTER TABLE appointments ADD COLUMN patient_edited_at DATETIME NULL');
    }
}

function patientAppointmentPermissions(array $appointment, string $today): array
{
    $status = strtolower(trim((string)($appointment['status'] ?? '')));
    $active = empty($appointment['is_archived']) && empty($appointment['deleted_at'])
        && empty($appointment['check_in_time']) && ($appointment['appointment_date'] ?? '') >= $today;
    return [
        'edit' => $active && $status === 'pending approval' && empty($appointment['approved_at'])
            && empty($appointment['approved_by']) && empty($appointment['patient_edited_at']),
        'cancel' => $active && $status === 'pending approval' && empty($appointment['approved_at']) && empty($appointment['approved_by']),
        'request_cancel' => $active && $status === 'scheduled' && ($appointment['cancellation_request_status'] ?? '') !== 'pending',
    ];
}

function patientAppointmentEditBlockReason(array $appointment): string
{
    if (!empty($appointment['patient_edited_at'])) return 'Your one-time edit has already been used. Please contact the clinic for further changes.';
    if (!empty($appointment['approved_at']) || !empty($appointment['approved_by']) || strtolower(trim($appointment['status'] ?? '')) === 'scheduled') {
        return 'This appointment has already been approved. Please contact the clinic to request a schedule change.';
    }
    if (!empty($appointment['check_in_time'])) return 'This appointment has already been checked in. Please contact the clinic.';
    if (($appointment['appointment_date'] ?? '') < date('Y-m-d')) return 'The appointment date has passed. Please contact the clinic.';
    return 'This appointment is no longer awaiting approval and cannot be edited. Please refresh your appointments.';
}

function managePatientAppointment(PDO $pdo, int $patientId, int $userId, array $input, ?callable $slotLoader = null): array
{
    $action = $input['action'] ?? '';
    $id = (int)($input['appointment_id'] ?? 0);
    if (!in_array($action, ['edit', 'cancel', 'request_cancel'], true) || $id <= 0) {
        throw new InvalidArgumentException('Invalid appointment action.');
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?' . $lock);
        $stmt->execute([$id, $patientId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appointment) throw new InvalidArgumentException('Appointment not found.');
        $permissions = patientAppointmentPermissions($appointment, date('Y-m-d'));
        if (!$permissions[$action]) {
            throw new InvalidArgumentException($action === 'edit'
                ? patientAppointmentEditBlockReason($appointment)
                : 'This appointment can no longer be cancelled online. Please contact the clinic.');
        }
        // A scheduled appointment may already have entered the queue while this
        // page was open, even if its check-in timestamp has not been populated.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE appointment_id = ? AND status IN ('waiting', 'on_hold', 'in_procedure', 'pending_payment', 'completed')");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn()) throw new InvalidArgumentException('This appointment has already entered the clinic queue. Please contact the clinic.');

        if ($action === 'edit') {
            $date = (string)($input['appointment_date'] ?? '');
            $time = (string)($input['appointment_time'] ?? '');
            $serviceId = (int)($input['service_id'] ?? 0);
            $notes = trim((string)($input['notes'] ?? ''));
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
                throw new InvalidArgumentException('Please choose a valid upcoming date.');
            }
            if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) throw new InvalidArgumentException('Please choose a valid time slot.');
            if (strlen($notes) > 5000) throw new InvalidArgumentException('Notes must not exceed 5,000 characters.');
            $stmt = $pdo->prepare('SELECT name, duration_minutes FROM services WHERE id = ? AND is_active = 1');
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$service) throw new InvalidArgumentException('Please choose an available service.');
            if ($date === $appointment['appointment_date'] && $time === substr($appointment['appointment_time'], 0, 5)
                && $serviceId === (int)$appointment['service_id'] && $notes === trim((string)$appointment['notes'])) {
                throw new InvalidArgumentException('No changes were made. Your one-time edit has not been used.');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND id <> ? AND appointment_date = ? AND COALESCE(is_archived, 0) = 0 AND deleted_at IS NULL AND LOWER(status) IN ('scheduled', 'in queue', 'pending approval')");
            $stmt->execute([$patientId, $id, $date]);
            if ((int)$stmt->fetchColumn()) throw new InvalidArgumentException('You already have another appointment on that date.');
            $slots = ($slotLoader ?? 'getAppointmentSlots')($pdo, [
                'date' => $date, 'service_id' => $serviceId, 'exclude_appointment_id' => $id,
                'dentist_id' => (int)($appointment['dentist_id'] ?? 0),
            ]);
            if (empty($slots['success']) || empty($slots['is_open']) || !in_array($time, $slots['available_slots'] ?? [], true)) {
                throw new InvalidArgumentException('That time is no longer available. Please choose another available slot.');
            }
            $sets = 'appointment_date = ?, appointment_time = ?, service_id = ?, duration_minutes = ?, notes = ?, patient_edited_at = CURRENT_TIMESTAMP, is_reminded = 0';
            $params = [$date, $time, $serviceId, (int)($service['duration_minutes'] ?: 30), $notes];
            if (array_key_exists('treatment', $appointment)) {
                $sets .= ', treatment = ?';
                $params[] = $service['name'];
            }
            $params[] = $id;
            $params[] = $patientId;
            $stmt = $pdo->prepare("UPDATE appointments SET $sets WHERE id = ? AND patient_id = ? AND LOWER(status) = 'pending approval' AND approved_at IS NULL AND approved_by IS NULL AND patient_edited_at IS NULL");
            $stmt->execute($params);
            if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('The appointment changed while you were editing. Please reload the page.');
            $message = 'Your appointment has been updated and remains pending staff approval. Your one-time edit has now been used.';
        } elseif ($action === 'request_cancel') {
            $requestId = submitAppointmentCancellationRequest($pdo, $appointment, trim((string)($input['cancel_reason'] ?? '')));
            $message = 'Your cancellation request has been submitted. Your appointment remains scheduled until the clinic approves cancellation. The clinic may contact you to verify your request.';
        } else {
            $reason = trim((string)($input['cancel_reason'] ?? '')) ?: 'Cancelled by patient';
            if (strlen($reason) > 1000) throw new InvalidArgumentException('Cancellation reason is too long.');
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled', cancel_reason = ?, cancelled_by = ?, cancelled_at = CURRENT_TIMESTAMP WHERE id = ? AND patient_id = ? AND LOWER(status) = 'pending approval' AND approved_at IS NULL AND approved_by IS NULL");
            $stmt->execute([$reason, $userId, $id, $patientId]);
            if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('The appointment changed. Please reload the page.');
            if (function_exists('syncTreatmentPlanForAppointment')) syncTreatmentPlanForAppointment($pdo, $id);
            $message = 'Your appointment has been cancelled.';
        }
        $pdo->commit();
        return ['success' => true, 'message' => $message, 'appointment_id' => $id, 'request_id' => $requestId ?? null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
