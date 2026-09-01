<?php

function sendOtpEmail(string $email, string $firstName, string $otpCode, string $purpose = 'registration', ?string &$errorMessage = null): bool {
    $errorMessage = null;
    $configPath = __DIR__ . '/../config/mailer_config.php';
    if (!file_exists($configPath)) {
        $errorMessage = 'Mailer configuration file not found.';
        error_log('sendOtpEmail: mailer_config.php not found');
        return false;
    }
    require_once $configPath;

    $configErrors = mailer_config_validate();
    if ($configErrors !== []) {
        $errorMessage = implode(' ', $configErrors);
        error_log('sendOtpEmail: SMTP config invalid: ' . $errorMessage);
        return false;
    }

    $phpMailerBase = __DIR__ . '/../PHPMailer/src';
    if (!file_exists($phpMailerBase . '/PHPMailer.php')) {
        $errorMessage = 'PHPMailer library not found.';
        error_log('sendOtpEmail: PHPMailer not found at ' . $phpMailerBase);
        return false;
    }

    require_once $phpMailerBase . '/Exception.php';
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = trim((string)SMTP_HOST);
        $mailer->SMTPAuth = true;
        $mailer->Username = trim((string)SMTP_USER);
        $mailer->Password = preg_replace('/\s+/', '', (string)SMTP_PASS);
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = SMTP_PORT;
        $mailer->CharSet = 'UTF-8';
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mailer->addAddress($email, $firstName);

        if ($purpose === 'password_change') {
            $subject = 'RF Dental Clinic - Password Change Verification Code';
            $intro = 'You requested to change your account password. '
                . 'Use the verification code below to continue:';
            $ignore = 'If you did not request a password change, '
                . 'please ignore this email or contact the clinic.';
        } else {
            $subject = 'RF Dental Clinic - Email Verification Code';
            $intro = 'Thank you for registering. Use the verification code below to complete your registration:';
            $ignore = 'If you did not create an account, please ignore this email.';
        }

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>'
            . '<p>' . $intro . '</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:5px;text-align:center;padding:16px;background:#f3f4f6;border-radius:8px;">'
            . htmlspecialchars($otpCode) . '</p>'
            . '<p>This code expires in <strong>3 minutes</strong>.</p>'
            . '<p>' . $ignore . '</p>';
        $mailer->AltBody = 'Hi ' . $firstName . ', ' . $intro . ' Your verification code is: ' . $otpCode
            . ' This code expires in 3 minutes. ' . $ignore;

        $mailer->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $errorMessage = 'SMTP error: ' . $e->getMessage();
        error_log('sendOtpEmail PHPMailer error: ' . $e->getMessage());
        error_log('sendOtpEmail PHPMailer detailed error: ' . $mailer->ErrorInfo);
        return false;
    } catch (Exception $e) {
        $errorMessage = 'Mailer error: ' . $e->getMessage();
        error_log('sendOtpEmail error: ' . $e->getMessage());
        return false;
    }
}
