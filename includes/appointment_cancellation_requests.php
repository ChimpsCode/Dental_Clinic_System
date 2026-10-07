<?php
function ensureCancellationRequestSchema(PDO $pdo): void
{
    $id = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_cancellation_requests (
        id $id, appointment_id INT NOT NULL UNIQUE, patient_id INT NOT NULL,
        reason TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT NULL, reviewed_at DATETIME NULL, review_note TEXT NULL
    )");
}

function submitAppointmentCancellationRequest(PDO $pdo, array $appointment, string $reason): int
{
    if ($reason === '' || strlen($reason) > 1000) throw new InvalidArgumentException('Please enter a cancellation reason of up to 1,000 characters.');
    $stmt = $pdo->prepare('SELECT id, status FROM appointment_cancellation_requests WHERE appointment_id = ?');
    $stmt->execute([$appointment['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && $existing['status'] === 'pending') throw new InvalidArgumentException('Your cancellation request is already awaiting clinic review.');
    if ($existing) {
        $pdo->prepare("UPDATE appointment_cancellation_requests SET reason=?, status='pending', requested_at=CURRENT_TIMESTAMP, reviewed_by=NULL, reviewed_at=NULL, review_note=NULL WHERE id=?")
            ->execute([$reason, $existing['id']]);
        return (int)$existing['id'];
    }
    $pdo->prepare('INSERT INTO appointment_cancellation_requests (appointment_id, patient_id, reason) VALUES (?, ?, ?)')->execute([$appointment['id'], $appointment['patient_id'], $reason]);
    return (int)$pdo->lastInsertId();
}

function reviewAppointmentCancellationRequest(PDO $pdo, int $requestId, int $reviewer, string $decision, string $note): array
{
    if (!in_array($decision, ['approve', 'decline'], true) || strlen($note) > 1000) throw new InvalidArgumentException('Invalid review details.');
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        // Lock the appointment first, matching patient edits and booking approval.
        $s = $pdo->prepare('SELECT appointment_id FROM appointment_cancellation_requests WHERE id=?');
        $s->execute([$requestId]);
        $appointmentId = $s->fetchColumn();
        if (!$appointmentId) throw new InvalidArgumentException('Cancellation request not found.');
        $s = $pdo->prepare('SELECT * FROM appointments WHERE id=?' . $lock);
        $s->execute([$appointmentId]);
        $appointment = $s->fetch(PDO::FETCH_ASSOC);
        $s = $pdo->prepare('SELECT * FROM appointment_cancellation_requests WHERE id=?' . $lock);
        $s->execute([$requestId]);
        $request = $s->fetch(PDO::FETCH_ASSOC);
        if (!$request || $request['status'] !== 'pending') throw new InvalidArgumentException('This request has already been reviewed.');
        if ($decision === 'approve') {
            if (!$appointment || strtolower($appointment['status']) !== 'scheduled' || !empty($appointment['is_archived']) || !empty($appointment['deleted_at']) || !empty($appointment['check_in_time'])) {
                throw new InvalidArgumentException('This appointment can no longer be cancelled. Decline the request instead.');
            }
            $s = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE appointment_id=? AND status IN ('waiting','on_hold','in_procedure','pending_payment','completed')");
            $s->execute([$appointmentId]);
            if ($s->fetchColumn()) throw new InvalidArgumentException('The patient has entered the clinic queue. Decline the request instead.');
            $pdo->prepare("UPDATE appointments SET status='Cancelled', cancel_reason=?, cancelled_by=?, cancelled_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$request['reason'], $reviewer, $appointmentId]);
            if (function_exists('syncTreatmentPlanForAppointment')) syncTreatmentPlanForAppointment($pdo, (int)$appointmentId);
        }
        $pdo->prepare('UPDATE appointment_cancellation_requests SET status=?, reviewed_by=?, reviewed_at=CURRENT_TIMESTAMP, review_note=? WHERE id=?')
            ->execute([$decision === 'approve' ? 'approved' : 'declined', $reviewer, $note, $requestId]);
        $pdo->commit();
        return ['success'=>true, 'message'=>$decision === 'approve' ? 'Cancellation approved. The appointment has been cancelled.' : 'Cancellation request declined. The appointment remains unchanged.',
            'patient_id'=>(int)$request['patient_id'], 'appointment_id'=>(int)$appointmentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
