<?php
session_start();
error_reporting(0);
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

    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.middle_name, p.last_name, p.phone,
               s.name AS service_name,
               COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
        LIMIT 1
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

        // Send confirmation SMS
        $smsError = null;
        if ($patientPhone) {
            try {
                $smsResult = sendAppointmentConfirmationSMS(
                    $patientPhone,
                    $patientName,
                    $appointment['appointment_date'],
                    $appointment['appointment_time'],
                    $appointment['service_name'] ?? ($appointment['treatment'] ?? 'General Checkup')
                );
                if (!$smsResult['success']) {
                    $smsError = $smsResult['error'] ?? 'Unknown SMS error';
                    error_log("Approve SMS failed for appointment $appointment_id: $smsError");
                }
            } catch (Error $e) {
                $smsError = $e->getMessage();
                error_log("Approve SMS error for appointment $appointment_id: $smsError");
            }
        }

        $message = 'Appointment approved and added to the calendar.';
        if ($smsError) {
            $message .= ' The patient could not be notified by SMS.';
        }
        echo json_encode(['success' => true, 'message' => $message]);
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

        // Send cancellation SMS
        if ($patientPhone) {
            try {
                $smsResult = sendAppointmentCancellationSMS($patientPhone, $patientName, $appointment['appointment_date'], $reason);
                if (!$smsResult['success']) {
                    error_log("Decline SMS failed for appointment $appointment_id: " . ($smsResult['error'] ?? 'Unknown'));
                }
            } catch (Error $e) {
                error_log("Decline SMS error for appointment $appointment_id: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Appointment request declined and cancelled.'
        ]);
    }

} catch (ApprovalUserException $e) {
    error_log("Approve Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Approve Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
} catch (Error $e) {
    error_log("Approve Appointment Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
