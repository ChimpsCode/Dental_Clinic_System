<?php

function autoCancelNoShows($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('auto_cancel_enabled', 'auto_cancel_minutes')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $enabled = ($settings['auto_cancel_enabled'] ?? '0') === '1';
    $minutes = (int)($settings['auto_cancel_minutes'] ?? 15);

    if (!$enabled) {
        return false;
    }

    $sql = "SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time,
                   p.email, p.first_name, p.last_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE (LOWER(a.status) NOT IN ('completed', 'cancelled', 'pending approval') OR a.status IS NULL)
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
        return false;
    }

    $mailerConfig = __DIR__ . '/../config/mailer_config.php';
    $phpMailerBase = __DIR__ . '/../PHPMailer/src';
    $mailerAvailable = file_exists($mailerConfig) && file_exists($phpMailerBase . '/PHPMailer.php');

    if ($mailerAvailable) {
        require_once $mailerConfig;
        require_once $phpMailerBase . '/Exception.php';
        require_once $phpMailerBase . '/PHPMailer.php';
        require_once $phpMailerBase . '/SMTP.php';
    }

    $updateStmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancel_reason = ? WHERE id = ?");

    foreach ($appointments as $appt) {
        $reason = 'Auto-cancelled due to no-show (patient did not arrive within ' . $minutes . ' minutes of appointment time)';
        $updateStmt->execute([$reason, $appt['id']]);

        if ($mailerAvailable && !empty($appt['email'])) {
            try {
                $fullName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')) ?: 'Patient';

                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = trim((string)SMTP_HOST);
                $mailer->SMTPAuth = true;
                $mailer->Username = SMTP_USER;
                $mailer->Password = preg_replace('/\s+/', '', (string)SMTP_PASS);
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = SMTP_PORT;
                $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mailer->addAddress($appt['email'], $fullName);
                $mailer->isHTML(true);
                $mailer->Subject = 'Appointment Cancelled - No Show';
                $mailer->Body = '<p>Please be advised that your appointment has been canceled as a missed session. To reschedule, please contact our clinic directly.</p>
                                <p><strong>Contact Number:</strong> 0949 347 2725</p>
                                <br>
                                <p>Best regards,<br>
                                <strong>RF Dental Clinic</strong></p>';

                $mailer->AltBody = "Please be advised that your appointment has been canceled as a missed session. To reschedule, please contact our clinic directly.\n\n" .
                                    "Contact Number: 0949 347 2725\n\n" .
                                    "Best regards,\n" .
                                    "RF Dental Clinic";
                $mailer->send();
            } catch (\Exception $e) {
                error_log("Auto-cancel email failed for appointment #{$appt['id']}: " . $e->getMessage());
            }
        }
    }

    return true;
}
