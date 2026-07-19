<?php
function sendSessionNotification($pdo, $patient_id, $plan_name, $sessions, $threshold = 2) {
    if (empty($sessions) || !is_array($sessions)) return;

    $today = new DateTime();
    $soonSessions = [];
    foreach ($sessions as $s) {
        if (!empty($s['date'])) {
            $sessDate = new DateTime($s['date']);
            $diff = $today->diff($sessDate)->days;
            if ($sessDate >= $today && $diff <= $threshold) {
                $soonSessions[] = $s;
            }
        }
    }

    if (empty($soonSessions)) return;

    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient || empty($patient['email'])) return;

        $rows = '';
        foreach ($soonSessions as $i => $s) {
            $dateStr = date('F j, Y', strtotime($s['date']));
            $timeStr = !empty($s['time']) ? date('g:i A', strtotime($s['time'])) : 'TBD';
            $rows .= "<tr><td style='padding:6px 12px;border:1px solid #ddd;'>Session " . ($i+1) . "</td><td style='padding:6px 12px;border:1px solid #ddd;'>{$dateStr}</td><td style='padding:6px 12px;border:1px solid #ddd;'>{$timeStr}</td></tr>";
        }

        require_once __DIR__ . '/../config/mailer_config.php';
        $phpMailerBase = __DIR__ . '/../PHPMailer/src';
        if (!file_exists($phpMailerBase . '/PHPMailer.php')) return;

        require_once $phpMailerBase . '/Exception.php';
        require_once $phpMailerBase . '/PHPMailer.php';
        require_once $phpMailerBase . '/SMTP.php';

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
        $mailer->addAddress($patient['email']);
        $mailer->isHTML(true);
        $mailer->Subject = 'RF Dental Clinic - Upcoming Treatment Session';

        $name = trim($patient['first_name'] . ' ' . $patient['last_name']);
        $mailer->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:#005daa;padding:20px;text-align:center;'>
                    <h2 style='color:white;margin:0;'>RF Dental Clinic</h2>
                </div>
                <div style='padding:24px;background:#f9fafb;'>
                    <p>Hi {$name},</p>
                    <p>A treatment plan <strong>" . htmlspecialchars($plan_name) . "</strong> has been created with upcoming sessions:</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                        <thead><tr style='background:#005daa;color:white;'><th style='padding:8px 12px;text-align:left;'>Session</th><th style='padding:8px 12px;text-align:left;'>Date</th><th style='padding:8px 12px;text-align:left;'>Time</th></tr></thead>
                        <tbody>{$rows}</tbody>
                    </table>
                    <p style='color:#6b7280;font-size:14px;'>Please contact the clinic if you need to reschedule.</p>
                    <p style='color:#6b7280;font-size:14px;'><strong>Contact:</strong> 0949 347 2725</p>
                </div>
                <div style='padding:16px;text-align:center;color:#9ca3af;font-size:12px;'>
                    <p>RF Dental Clinic — Your trusted dental care partner</p>
                </div>
            </div>
        ";
        $mailer->AltBody = "Hi {$name},\n\nA treatment plan \"{$plan_name}\" has been created with upcoming sessions.\nPlease check the clinic portal for details.\n\nContact: 0949 347 2725";

        $mailer->send();
    } catch (Exception $e) {}
}
