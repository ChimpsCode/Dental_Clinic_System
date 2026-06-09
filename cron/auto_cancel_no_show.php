<?php
/**
 * Auto-Cancel No-Show Appointments
 * 
 * Run as a cron job or trigger from appointments page visits.
 * Cancels appointments that are past the no-show threshold
 * and have not been marked as arrived (check_in_time IS NULL).
 */

require_once __DIR__ . '/../config/database.php';

// Load auto-cancel settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('auto_cancel_enabled', 'auto_cancel_minutes')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$enabled = ($settings['auto_cancel_enabled'] ?? '0') === '1';
$minutes = (int)($settings['auto_cancel_minutes'] ?? 20);

if (!$enabled) {
    exit;
}

// Find appointments eligible for auto-cancel:
// 1. Not already completed (status_id = 4) or cancelled (status_id = 5)
// 2. status NOT completed/cancelled
// 3. check_in_time IS NULL (never arrived)
// 4a. Same date + past appointment_time + threshold minutes elapsed
// 4b. OR past dates that were never arrived
$sql = "SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time,
               p.email, p.first_name, p.last_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE (a.status_id NOT IN (4, 5) OR a.status_id IS NULL)
          AND (LOWER(a.status) NOT IN ('completed', 'cancelled') OR a.status IS NULL)
          AND a.check_in_time IS NULL
          AND (
            a.appointment_date < CURDATE()
            OR (
              a.appointment_date = CURDATE()
              AND ADDTIME(a.appointment_time, SEC_TO_TIME(? * 60)) <= NOW()
            )
          )";

$stmt = $pdo->prepare($sql);
$stmt->execute([$minutes]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($appointments)) {
    exit;
}

// PHPMailer
$mailerConfig = __DIR__ . '/../config/mailer_config.php';
$phpMailerBase = __DIR__ . '/../PHPMailer/src';
$mailerAvailable = file_exists($mailerConfig) && file_exists($phpMailerBase . '/PHPMailer.php');

if ($mailerAvailable) {
    require_once $mailerConfig;
    require_once $phpMailerBase . '/Exception.php';
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';
}

$updateStmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', status_id = 5, cancel_reason = ? WHERE id = ?");

foreach ($appointments as $appt) {
    $reason = 'Auto-cancelled due to no-show (patient did not arrive within ' . $minutes . ' minutes of appointment time)';

    $updateStmt->execute([$reason, $appt['id']]);

    if ($mailerAvailable && !empty($appt['email'])) {
        try {
            $fullName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')) ?: 'Patient';
            $apptDate = date('M d, Y', strtotime($appt['appointment_date']));
            $apptTime = date('h:i A', strtotime($appt['appointment_time'] ?? ''));

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = gethostbyname(SMTP_HOST);
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USER;
            $mailer->Password = SMTP_PASS;
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($appt['email'], $fullName);
            $mailer->isHTML(true);
            $mailer->Subject = 'Appointment Cancelled - No Show';
            $mailer->Body = '<p>Dear ' . htmlspecialchars($fullName) . ',</p>'
                . '<p>We regret to inform you that your appointment scheduled on <strong>' . htmlspecialchars($apptDate) . ' at ' . htmlspecialchars($apptTime) . '</strong> has been automatically cancelled due to a no-show.</p>'
                . '<p>If you still need to schedule an appointment, please contact our clinic or book online.</p>'
                . '<p>We are here to help you with your dental needs.</p>'
                . '<p>Best regards,<br><strong>RF Dental Clinic</strong></p>';
            $mailer->AltBody = "Dear {$fullName},\n\nWe regret to inform you that your appointment scheduled on {$apptDate} at {$apptTime} has been automatically cancelled due to a no-show.\n\nIf you still need to schedule an appointment, please contact our clinic or book online.\n\nBest regards,\nRF Dental Clinic";
            $mailer->send();
        } catch (\Exception $e) {
            error_log("Auto-cancel email failed for appointment #{$appt['id']}: " . $e->getMessage());
        }
    }
}
