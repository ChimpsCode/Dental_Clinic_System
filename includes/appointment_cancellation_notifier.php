<?php

require_once __DIR__ . '/sms_functions.php';

/**
 * Shared patient notification for appointment cancellations.
 *
 * Cancelling an appointment used to be completely silent: none of the three
 * role-specific cancel_appointment.php endpoints sent an SMS or an in-app
 * notification, so a patient whose visit was cancelled never found out.
 * All three now route through here.
 *
 * @return array{sms_sent:bool,sms_reason:string,notified:bool}
 */
function notifyPatientOfAppointmentCancellation(PDO $pdo, int $appointmentId, string $reason, ?int $actorId = null): array
{
    $result = ['sms_sent' => false, 'sms_reason' => '', 'notified' => false];

    try {
        $stmt = $pdo->prepare("
            SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, p.phone,
                   TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.middle_name,''), ' ', COALESCE(p.last_name,''))) AS patient_name,
                   COALESCE(s.name, 'General Checkup') AS service_name
            FROM appointments a
            LEFT JOIN patients p ON p.id = a.patient_id
            LEFT JOIN services s ON s.id = a.service_id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("notifyPatientOfAppointmentCancellation: lookup failed for #{$appointmentId}: " . $e->getMessage());
        return $result;
    }

    if (!$appt) {
        return $result;
    }

    $patientId = (int)($appt['patient_id'] ?? 0);
    if ($patientId <= 0) {
        return $result;
    }

    $patientName = trim((string)($appt['patient_name'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }
    $serviceName = trim((string)($appt['service_name'] ?? '')) ?: 'General Checkup';
    $appointmentDate = (string)($appt['appointment_date'] ?? '');

    // ---- SMS (respects the patient's Notification Preferences) -------------
    $result['sms_sent'] = sendPatientSmsTracked(
        $pdo,
        $patientId,
        buildAppointmentCancellationSms($patientName, $appointmentDate, $serviceName, $reason),
        'appointment_cancelled',
        $appointmentId,
        false,
        true,
        trim((string)($appt['phone'] ?? '')) ?: null
    );
    if (!$result['sms_sent']) {
        $result['sms_reason'] = describeSmsSkipReason($pdo, $patientId);
    }

    // ---- In-app notification (always, regardless of SMS preference) --------
    require_once __DIR__ . '/notification_functions.php';
    if (function_exists('ensureNotificationSchema')) {
        ensureNotificationSchema($pdo);
    }
    if (function_exists('notifyPatientAppointmentCancelled')) {
        notifyPatientAppointmentCancelled($pdo, $patientId, $appointmentDate, $reason, $serviceName);
        $result['notified'] = true;
    } else {
        $userId = function_exists('getUserIdForPatient') ? getUserIdForPatient($pdo, $patientId) : 0;
        if ($userId) {
            $title = 'Appointment Cancelled';
            $message = "Your {$serviceName} appointment on {$appointmentDate} was cancelled by the clinic."
                . ($reason !== '' ? " Reason: {$reason}" : '');
            createNotification($pdo, (int)$userId, 'appointment', $title, $message, 'appointments.php');
            $result['notified'] = true;
        }
    }

    return $result;
}
