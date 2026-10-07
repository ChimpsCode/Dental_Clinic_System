<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'admin', 'dentist'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/appointment_approval_helper.php';
require_once __DIR__ . '/../../includes/sms_functions.php';
require_once __DIR__ . '/../../includes/notification_functions.php';
ensureNotificationSchema($pdo);

class ApprovalUserException extends Exception {}

function resolveAppointmentStatusIdSafe(PDO $pdo, string $statusName): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM appointment_statuses WHERE status_name = ? LIMIT 1");
    $stmt->execute([$statusName]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ApprovalUserException('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $appointment_id = (int)($input['appointment_id'] ?? 0);
    $decline_reason = trim($input['decline_reason'] ?? '');

    if (!$appointment_id) {
        throw new ApprovalUserException('Appointment ID is required');
    }

    if (!in_array($action, ['approve', 'decline'], true)) {
        throw new ApprovalUserException('Invalid action');
    }

    ensureAppointmentApprovalSchema($pdo);

    // Serialize approval/decline with the patient's one-time edit or cancellation.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.middle_name, p.last_name, p.phone, p.created_at,
               s.name AS service_name,
               COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new ApprovalUserException('Appointment not found');
    }

    $currentStatus = strtolower(trim((string)($appointment['status'] ?? '')));
    if ($currentStatus !== 'pending approval') {
        throw new ApprovalUserException('This appointment is not pending approval. Only pending requests can be approved or declined.');
    }

    $patientName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['middle_name'] ?? '') . ' ' . ($appointment['last_name'] ?? '')) ?: 'Patient';
    $patientPhone = $appointment['phone'] ?? null;

    $priorAppointmentsStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND id <> ?");
    $priorAppointmentsStmt->execute([(int)$appointment['patient_id'], $appointment_id]);
    $isNewPatient = ((int)$priorAppointmentsStmt->fetchColumn()) === 0;

    if ($action === 'approve') {
        // Re-check that the slot hasn't been taken by another confirmed appointment
        if (appointmentSlotIsTaken($pdo, $appointment['appointment_date'], $appointment['appointment_time'], (int)$appointment['duration_minutes'], $appointment_id)) {
            throw new ApprovalUserException('Cannot approve: this time slot has already been taken by another appointment. Please decline and ask the patient to pick a new time.');
        }

        $updates = [
            "status = 'Scheduled'",
            'approved_by = ?',
            'approved_at = NOW()',
            'cancel_reason = NULL'
        ];
        $params = [$_SESSION['user_id']];

        if (appointmentColumnExists($pdo, 'appointments', 'status_id')) {
            $statusId = resolveAppointmentStatusIdSafe($pdo, 'Scheduled') ?? resolveAppointmentStatusIdSafe($pdo, 'Confirmed');
            if ($statusId) {
                $updates[] = 'status_id = ?';
                $params[] = $statusId;
            }
        }

        $params[] = $appointment_id;
        $pdo->prepare("UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        $pdo->commit();

        try {
            require_once __DIR__ . '/../../includes/audit_helper.php';
            $auditService = $appointment['service_name'] ?? ($appointment['treatment'] ?? 'General Checkup');
            $auditTime = !empty($appointment['appointment_time'])
                ? date('g:i A', strtotime($appointment['appointment_time']))
                : '';
            $auditDate = $appointment['appointment_date'] ?? '';
            $auditDescription = 'Appointment accepted: APT-' . str_pad((string)$appointment_id, 4, '0', STR_PAD_LEFT)
                . ' for ' . $patientName
                . ($auditDate !== '' ? ' on ' . $auditDate : '')
                . ($auditTime !== '' ? ' at ' . $auditTime : '')
                . ' (' . $auditService . ')';
            logAudit(
                $pdo,
                $_SESSION['user_id'],
                $_SESSION['username'] ?? 'unknown',
                $role,
                'status_change',
                'appointments',
                $auditDescription,
                $appointment_id,
                'appointments',
                'Pending Approval',
                'Scheduled'
            );
        } catch (Exception $e) {}

        // Close session before external calls so other requests aren't blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Send confirmation SMS. Gated on the patient's Notification
        // Preferences (Account settings -> SMS Notifications) and recorded in
        // sms_log so a silent failure is visible instead of invisible.
        $smsError = null;
        $smsNotified = false;
        $patientId = (int)($appointment['patient_id'] ?? 0);
        if ($patientId > 0) {
            try {
                $smsNotified = sendPatientSmsTracked(
                    $pdo,
                    $patientId,
                    buildAppointmentApprovalSms(
                        $patientName,
                        (string)($appointment['appointment_date'] ?? ''),
                        (string)($appointment['appointment_time'] ?? ''),
                        (string)($appointment['service_name'] ?? ($appointment['treatment'] ?? 'General Checkup')),
                        $isNewPatient
                    ),
                    'appointment_approved',
                    $appointment_id,
                    false,
                    true,
                    $patientPhone ? (string)$patientPhone : null
                );
                if (!$smsNotified) {
                    $smsError = describeSmsSkipReason($pdo, $patientId);
                    error_log("Approve SMS not sent for appointment $appointment_id: $smsError");
                }
            } catch (Throwable $e) {
                $smsError = $e->getMessage();
                error_log("Approve SMS error for appointment $appointment_id: $smsError");
            }
        } else {
            $smsError = 'the appointment has no patient record';
        }

        $message = 'Appointment approved and added to the calendar.';
        if ($smsError) {
            $message .= ' The patient could not be notified by SMS because ' . $smsError . '.';
        }

        if ($patientId > 0) {
            $svcName = $appointment['service_name'] ?? ($appointment['treatment'] ?? 'General Checkup');
            notifyPatientAppointmentApproved($pdo, $patientId, $appointment['appointment_date'], $appointment['appointment_time'], $svcName);
            notifyPatientFollowUpReminder($pdo, $patientId, $appointment['appointment_date'], $appointment['appointment_time'], $svcName);
        }

        // Now that it is actually on the calendar, tell the clinic it is booked
        try {
            $apptDateLabel = !empty($appointment['appointment_date'])
                ? date('F j, Y', strtotime($appointment['appointment_date']))
                : 'an upcoming date';
            $apptTimeLabel = !empty($appointment['appointment_time'])
                ? date('g:i A', strtotime($appointment['appointment_time']))
                : null;
            notifyNewAppointment(
                $pdo,
                $patientName,
                $apptDateLabel,
                null,
                $apptTimeLabel,
                $appointment['service_name'] ?? ($appointment['treatment'] ?? null)
            );
        } catch (Exception $clinicNotifErr) {
            error_log("Approve clinic notification error for appointment $appointment_id: " . $clinicNotifErr->getMessage());
        }

        echo json_encode(['success' => true, 'message' => $message, 'sms_sent' => $smsNotified]);
    } else {
        $reason = $decline_reason !== '' ? $decline_reason : 'Declined by clinic staff';

        $updates = [
            "status = 'Cancelled'",
            'cancel_reason = ?',
            'cancelled_by = ?',
            'cancelled_at = NOW()'
        ];
        $params = [$reason, $_SESSION['user_id']];

        if (appointmentColumnExists($pdo, 'appointments', 'status_id')) {
            $statusId = resolveAppointmentStatusIdSafe($pdo, 'Cancelled');
            if ($statusId) {
                $updates[] = 'status_id = ?';
                $params[] = $statusId;
            }
        }

        $params[] = $appointment_id;
        $pdo->prepare("UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        $pdo->commit();

        // Close session before external calls so other requests aren't blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Send cancellation SMS. Same preference gate and audit trail as approve.
        $smsError = null;
        $smsNotified = false;
        $patientId = (int)($appointment['patient_id'] ?? 0);
        if ($patientId > 0) {
            try {
                $smsNotified = sendPatientSmsTracked(
                    $pdo,
                    $patientId,
                    buildAppointmentDeclineSms($patientName, (string)($appointment['appointment_date'] ?? ''), $reason),
                    'appointment_declined',
                    $appointment_id,
                    false,
                    true,
                    $patientPhone ? (string)$patientPhone : null
                );
                if (!$smsNotified) {
                    $smsError = describeSmsSkipReason($pdo, $patientId);
                    error_log("Decline SMS not sent for appointment $appointment_id: $smsError");
                }
            } catch (Throwable $e) {
                $smsError = $e->getMessage();
                error_log("Decline SMS error for appointment $appointment_id: $smsError");
            }
        }

        $declineMessage = 'Appointment request declined and cancelled.';
        if ($smsError) {
            $declineMessage .= ' The patient could not be notified by SMS because ' . $smsError . '.';
        }

        echo json_encode([
            'success' => true,
            'message' => $declineMessage,
            'sms_sent' => $smsNotified,
        ]);

        if ($patientId > 0) {
            notifyPatientAppointmentDeclined($pdo, $patientId, $appointment['appointment_date'], $reason);
        }
    }

} catch (ApprovalUserException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Approve Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Approve Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
} catch (Error $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Approve Appointment Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
