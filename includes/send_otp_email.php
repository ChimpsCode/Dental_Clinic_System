<?php

function sendOtpEmail(string $email, string $firstName, string $otpCode): bool {
    $configPath = __DIR__ . '/../config/mailer_config.php';
    if (!file_exists($configPath)) {
        error_log('sendOtpEmail: mailer_config.php not found');
        return false;
    }
    require_once $configPath;

    $phpMailerBase = __DIR__ . '/../PHPMailer/src';
    if (!file_exists($phpMailerBase . '/PHPMailer.php')) {
        error_log('sendOtpEmail: PHPMailer not found at ' . $phpMailerBase);
        return false;
    }

    require_once $phpMailerBase . '/Exception.php';
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = gethostbyname(SMTP_HOST);
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USER;
        $mailer->Password = SMTP_PASS;
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = SMTP_PORT;
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mailer->addAddress($email, $firstName);

        $mailer->isHTML(true);
        $mailer->Subject = 'RF Dental Clinic - Email Verification Code';
        $mailer->Body = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>'
            . '<p>Thank you for registering. Use the verification code below to complete your registration:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:5px;text-align:center;padding:16px;background:#f3f4f6;border-radius:8px;">'
            . htmlspecialchars($otpCode) . '</p>'
            . '<p>This code expires in <strong>3 minutes</strong>.</p>'
            . '<p>If you did not create an account, please ignore this email.</p>';
        $mailer->AltBody = 'Hi ' . $firstName . ', your verification code is: ' . $otpCode . '. This code expires in 3 minutes.';

        $mailer->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('sendOtpEmail PHPMailer error: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('sendOtpEmail error: ' . $e->getMessage());
        return false;
    }
}
