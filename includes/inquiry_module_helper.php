<?php

function inquiryModuleColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(" 
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function getColumnDefinition(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(" 
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function ensureInquiryModuleSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $cacheFile = dirname(__DIR__) . '/cache/.inquiry_schema_ok';
    if (file_exists($cacheFile) && (time() - (int)filemtime($cacheFile)) < 3600) {
        $ensured = true;
        return;
    }

    if (!tableExists($pdo, 'inquiry_statuses')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inquiry_statuses (
                id INT(11) NOT NULL AUTO_INCREMENT,
                status_name VARCHAR(50) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_inquiry_status_name (status_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $pdo->exec("INSERT IGNORE INTO inquiry_statuses (status_name) VALUES ('Unread'), ('Read'), ('Replied')");
    }

    if (!inquiryModuleColumnExists($pdo, 'inquiries', 'status')) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Unread' AFTER status_id");
    }

    $inquiryColumns = [
        'patient_id' => "ALTER TABLE inquiries ADD COLUMN patient_id INT NULL AFTER id",
        'phone' => "ALTER TABLE inquiries ADD COLUMN phone VARCHAR(50) NULL AFTER patient_id",
        'email' => "ALTER TABLE inquiries ADD COLUMN email VARCHAR(150) NULL AFTER phone",
        'concern' => "ALTER TABLE inquiries ADD COLUMN concern TEXT NULL AFTER email",
        'service_id' => "ALTER TABLE inquiries ADD COLUMN service_id INT NULL AFTER concern",
        'preferred_date' => "ALTER TABLE inquiries ADD COLUMN preferred_date DATE NULL AFTER service_id",
        'preferred_time' => "ALTER TABLE inquiries ADD COLUMN preferred_time TIME NULL AFTER preferred_date",
        'confirmed_date' => "ALTER TABLE inquiries ADD COLUMN confirmed_date DATE NULL AFTER preferred_time",
        'confirmed_time' => "ALTER TABLE inquiries ADD COLUMN confirmed_time TIME NULL AFTER confirmed_date",
        'assigned_dentist' => "ALTER TABLE inquiries ADD COLUMN assigned_dentist VARCHAR(150) NULL AFTER assigned_staff_id",
        'pain_level' => "ALTER TABLE inquiries ADD COLUMN pain_level VARCHAR(30) NULL AFTER preferred_time",
        'urgency' => "ALTER TABLE inquiries ADD COLUMN urgency ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low' AFTER pain_level",
        'assigned_staff_id' => "ALTER TABLE inquiries ADD COLUMN assigned_staff_id INT NULL AFTER status",
        'source' => "ALTER TABLE inquiries ADD COLUMN source VARCHAR(50) NOT NULL DEFAULT 'Patient Portal' AFTER assigned_staff_id",
        'notes' => "ALTER TABLE inquiries ADD COLUMN notes TEXT NULL AFTER source",
        'is_archived' => "ALTER TABLE inquiries ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
        'deleted_at' => "ALTER TABLE inquiries ADD COLUMN deleted_at DATETIME NULL AFTER is_archived",
    ];

    foreach ($inquiryColumns as $column => $sql) {
        if (!inquiryModuleColumnExists($pdo, 'inquiries', $column)) {
            $pdo->exec($sql);
        }
    }

    if (inquiryModuleColumnExists($pdo, 'inquiries', 'full_name')) {
        $pdo->exec("ALTER TABLE inquiries DROP COLUMN full_name");
    }

    if (!inquiryModuleColumnExists($pdo, 'appointments', 'service_id')) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN service_id INT NULL AFTER patient_id");
    }

    if (!inquiryModuleColumnExists($pdo, 'appointments', 'inquiry_id')) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN inquiry_id INT NULL AFTER service_id");
    }

    if (!inquiryModuleColumnExists($pdo, 'appointments', 'status')) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN status VARCHAR(30) NULL DEFAULT 'scheduled' AFTER notes");
    }

    $backfillAssignments = [];

    if (
        inquiryModuleColumnExists($pdo, 'inquiries', 'phone') &&
        inquiryModuleColumnExists($pdo, 'inquiries', 'contact_info')
    ) {
        $backfillAssignments[] = "phone = CASE WHEN phone IS NULL OR phone = '' THEN contact_info ELSE phone END";
    }

    if (
        inquiryModuleColumnExists($pdo, 'inquiries', 'concern') &&
        inquiryModuleColumnExists($pdo, 'inquiries', 'inquiry_message')
    ) {
        $backfillAssignments[] = "concern = CASE WHEN concern IS NULL OR concern = '' THEN inquiry_message ELSE concern END";
    }

    if (
        inquiryModuleColumnExists($pdo, 'inquiries', 'service_id') &&
        inquiryModuleColumnExists($pdo, 'inquiries', 'topic')
    ) {
        $backfillAssignments[] = "service_id = CASE WHEN service_id IS NULL AND topic REGEXP '^[0-9]+$' THEN CAST(topic AS UNSIGNED) ELSE service_id END";
    } elseif (
        inquiryModuleColumnExists($pdo, 'inquiries', 'service_id') &&
        inquiryModuleColumnExists($pdo, 'inquiries', 'topic_service_id')
    ) {
        $backfillAssignments[] = "service_id = CASE WHEN service_id IS NULL THEN topic_service_id ELSE service_id END";
    }

    if (inquiryModuleColumnExists($pdo, 'inquiries', 'source')) {
        $backfillAssignments[] = "source = CASE WHEN source IS NULL OR source = '' THEN 'Patient Portal' ELSE source END";
    }

    if ($backfillAssignments !== []) {
        $pdo->exec("
            UPDATE inquiries
            SET " . implode(",\n                ", $backfillAssignments) . "
            WHERE 1 = 1
        ");
    }

    if (!inquiryModuleColumnExists($pdo, 'inquiries', 'reply_token')) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN reply_token VARCHAR(64) NULL AFTER source, ADD UNIQUE KEY uk_reply_token (reply_token)");
    }

    ensureInquiryReplySchema($pdo);

    if (!inquiryModuleColumnExists($pdo, 'inquiries', 'fb_sender_id')) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN fb_sender_id VARCHAR(100) NULL AFTER source");
    }

    @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, (string)time());

    $ensured = true;
}

function ensureInquiryReplySchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inquiry_replies` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `inquiry_id` INT(11) NOT NULL,
            `message` TEXT NOT NULL,
            `sender_type` ENUM('staff','guest','patient') NOT NULL DEFAULT 'guest',
            `attachment` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_inquiry_id` (`inquiry_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (inquiryModuleColumnExists($pdo, 'inquiry_replies', 'sender_type')) {
        $pdo->exec("ALTER TABLE `inquiry_replies` MODIFY COLUMN `sender_type` ENUM('staff','guest','patient') NOT NULL DEFAULT 'guest'");
    }

    if (!inquiryModuleColumnExists($pdo, 'inquiry_replies', 'attachment')) {
        $pdo->exec("ALTER TABLE `inquiry_replies` ADD COLUMN `attachment` VARCHAR(500) DEFAULT NULL AFTER `sender_type`");
    }

    $ensured = true;
}

function inquiryProjectWebBase(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/master(?:/|$)#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        return rtrim(substr($projectRoot, strlen($docRoot)), '/') ?: '';
    }
    return '';
}

function inquiryPublicAssetUrl(string $relativePath): string
{
    $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
    $webBase = inquiryProjectWebBase();
    return ($webBase !== '' ? $webBase : '') . '/' . $relative;
}

/**
 * Read the clinic `settings` rows once per request.
 */
function inquiryClinicSettings(PDO $pdo): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Exception $e) {
        error_log('inquiryClinicSettings: ' . $e->getMessage());
    }

    return $settings;
}

function inquiryClinicName(PDO $pdo): string
{
    $name = trim((string)(inquiryClinicSettings($pdo)['clinic_name'] ?? ''));
    return $name !== '' ? $name : 'RF Dental Clinic';
}

/**
 * Build the site origin from the request, ignoring a spoofed Host header so the
 * links we email out cannot be poisoned.
 */
function inquiryRequestBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $host)) {
            $host = 'localhost';
        }
    }

    return $scheme . '://' . $host;
}

function inquiryConversationUrl(string $replyToken): string
{
    // Direct PHP links also work on hosts without the optional /inquiry rewrite.
    return inquiryRequestBaseUrl() . inquiryProjectWebBase()
        . '/master/welcome-page/inquiry_reply.php?token=' . rawurlencode($replyToken);
}

function inquiryStaffInquiriesUrl(): string
{
    return inquiryRequestBaseUrl() . inquiryProjectWebBase() . '/master/staff/staff_inquiries.php';
}

/**
 * Shared PHPMailer instance configured from config/mailer_config.php.
 * Returns null when SMTP is missing or incomplete so callers can degrade quietly.
 */
function inquiryCreateMailer(): ?object
{
    $mailerConfig = dirname(__DIR__) . '/config/mailer_config.php';
    $phpMailerBase = dirname(__DIR__) . '/PHPMailer/src';
    if (!file_exists($mailerConfig) || !file_exists($phpMailerBase . '/PHPMailer.php')) {
        error_log('inquiryCreateMailer: mailer not configured');
        return null;
    }

    require_once $mailerConfig;
    require_once $phpMailerBase . '/Exception.php';
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';

    $host = trim((string)SMTP_HOST);
    $user = trim((string)SMTP_USER);
    $pass = preg_replace('/\s+/', '', (string)SMTP_PASS);
    if ($host === '' || $user === '' || $pass === '') {
        error_log('inquiryCreateMailer: SMTP credentials are incomplete');
        return null;
    }

    $from = trim((string)MAIL_FROM);
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = $user;
    }

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $user;
    $mailer->Password = $pass;
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
    $mailer->setFrom($from, trim((string)MAIL_FROM_NAME) ?: 'RF Dental Clinic');

    return $mailer;
}

/**
 * Addresses that should hear about a new inquiry: the clinic inbox first,
 * then any active admin/staff/dentist account that has an email on file.
 */
function inquiryNotificationRecipients(PDO $pdo): array
{
    $recipients = [];

    $add = static function (?string $email) use (&$recipients): void {
        $email = trim((string)$email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[strtolower($email)] = $email;
        }
    };

    $add(inquiryClinicSettings($pdo)['clinic_email'] ?? '');

    try {
        $stmt = $pdo->query("
            SELECT email FROM users
            WHERE LOWER(COALESCE(role, '')) IN ('admin', 'staff', 'dentist')
              AND email IS NOT NULL AND email != ''
              AND COALESCE(is_archived, 0) = 0
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $add((string)$email);
        }
    } catch (Exception $e) {
        error_log('inquiryNotificationRecipients: ' . $e->getMessage());
    }

    return array_slice(array_values($recipients), 0, 10);
}

function resolveStaffProfilePictureUrl(PDO $pdo, ?int $userId = null): string
{
    $projectRoot = dirname(__DIR__);
    $candidates = [];

    if ($userId && $userId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $path = (string)$stmt->fetchColumn();
            if ($path !== '') {
                $candidates[] = $path;
            }
        } catch (Exception $e) {
        }
    }

    try {
        $fallback = $pdo->query("
            SELECT profile_picture
            FROM users
            WHERE LOWER(COALESCE(role, '')) IN ('staff', 'admin')
              AND profile_picture IS NOT NULL
              AND profile_picture != ''
            ORDER BY CASE WHEN LOWER(role) = 'staff' THEN 0 ELSE 1 END, id ASC
            LIMIT 1
        ");
        $path = $fallback ? (string)$fallback->fetchColumn() : '';
        if ($path !== '') {
            $candidates[] = $path;
        }
    } catch (Exception $e) {
    }

    foreach ($candidates as $path) {
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if ($relative !== '' && file_exists($projectRoot . '/' . $relative)) {
            return inquiryPublicAssetUrl($relative);
        }
    }

    return inquiryPublicAssetUrl('assets/images/Logo.png');
}

function fetchInquiryReplies(PDO $pdo, int $inquiryId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT id, message, sender_type, attachment, created_at
            FROM inquiry_replies
            WHERE inquiry_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("
            SELECT id, message, sender_type, NULL AS attachment, created_at
            FROM inquiry_replies
            WHERE inquiry_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function generateReplyToken(): string
{
    return bin2hex(random_bytes(32));
}

function sendInquiryConversationLinkEmail(
    string $email,
    string $firstName,
    string $conversationLink,
    ?PDO $pdo = null,
    string $subject = '',
    string $intro = ''
): bool
{
    $recipient = trim($email);
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log('sendInquiryConversationLinkEmail: invalid recipient');
        return false;
    }

    $mailer = inquiryCreateMailer();
    if ($mailer === null) {
        return false;
    }

    try {
        $clinicName = $pdo !== null ? inquiryClinicName($pdo) : 'RF Dental Clinic';
        $safeName = htmlspecialchars($firstName);
        $safeLink = htmlspecialchars($conversationLink);

        $mailer->addAddress($recipient, $firstName);
        $mailer->isHTML(true);
        $mailer->Subject = $subject !== '' ? $subject : 'We received your message - ' . $clinicName;
        $introHtml = $intro !== ''
            ? '<p>' . nl2br(htmlspecialchars($intro)) . '</p>'
            : '<p>Thank you for contacting ' . htmlspecialchars($clinicName) . '. We received your message and will reply by email.</p>';

        $mailer->Body = '<p>Hi ' . $safeName . ',</p>'
            . $introHtml
            . '<p>Use the button below any time to read the clinic replies and send an instant message back.</p>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:24px 0;">'
            . '<tr><td align="center" style="background:#2563eb;border-radius:12px;padding:14px 28px;">'
            . '<a href="' . $safeLink . '" style="color:#fff;text-decoration:none;font-size:15px;font-weight:700;display:inline-block;">Click here for instant reply &rarr;</a>'
            . '</td></tr></table>'
            . '<p style="color:#64748b;font-size:13px;">Keep this email so you can always return to your conversation.</p>';
        $mailer->AltBody = "Hi {$firstName},\n\nOpen your conversation here: {$conversationLink}\n";
        $mailer->send();
        return true;
    } catch (\Throwable $e) {
        error_log('sendInquiryConversationLinkEmail: ' . $e->getMessage());
        return false;
    }
}

/**
 * Alert the clinic that a new inquiry arrived on the website.
 * Without this the only notification path was the in-app bell, which staff
 * never see, so leads sat unread in the database.
 */
function sendNewInquiryStaffNotificationEmail(PDO $pdo, array $inquiry): bool
{
    $recipients = inquiryNotificationRecipients($pdo);
    if ($recipients === []) {
        error_log('sendNewInquiryStaffNotificationEmail: no recipient configured');
        return false;
    }

    $mailer = inquiryCreateMailer();
    if ($mailer === null) {
        return false;
    }

    try {
        $clinicName = inquiryClinicName($pdo);
        $fullName = trim(implode(' ', array_filter([
            $inquiry['first_name'] ?? '',
            $inquiry['middle_name'] ?? '',
            $inquiry['last_name'] ?? '',
        ]))) ?: 'A website visitor';

        $sentTo = 0;
        foreach ($recipients as $recipient) {
            $mailer->addAddress($recipient);
            $sentTo++;
        }

        $dashboardUrl = inquiryStaffInquiriesUrl();
        $replyToken = trim((string)($inquiry['reply_token'] ?? ''));
        $conversationLink = $replyToken !== '' ? inquiryConversationUrl($replyToken) : '';
        $source = trim((string)($inquiry['source'] ?? '')) ?: 'Website';
        $receivedAt = trim((string)($inquiry['created_at'] ?? ''));

        $detailRows = [
            'Name' => $fullName,
            'Email' => (string)($inquiry['email'] ?? ''),
            'Phone' => (string)($inquiry['phone'] ?? ''),
            'Source' => $source,
            'Received' => $receivedAt !== '' ? $receivedAt : date('Y-m-d H:i:s'),
        ];

        $rowsHtml = '';
        foreach ($detailRows as $label => $value) {
            $rowsHtml .= '<tr>'
                . '<td style="padding:6px 12px 6px 0;color:#64748b;font-size:13px;white-space:nowrap;">' . htmlspecialchars($label) . '</td>'
                . '<td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:600;">' . htmlspecialchars((string)$value) . '</td>'
                . '</tr>';
        }

        $mailer->isHTML(true);
        $mailer->Subject = 'New website inquiry from ' . $fullName;
        $mailer->Body = '<p style="margin:0 0 16px;">A new inquiry was submitted through the '
            . htmlspecialchars($clinicName) . ' website contact form.</p>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">' . $rowsHtml . '</table>'
            . '<p style="margin:0 0 6px;font-weight:700;color:#0f172a;">Message</p>'
            . '<blockquote style="padding:12px 16px;border-left:4px solid #2563eb;background:#f8fafc;margin:0 0 24px;white-space:pre-wrap;">'
            . nl2br(htmlspecialchars((string)($inquiry['concern'] ?? '')))
            . '</blockquote>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
            . '<tr><td align="center" style="background:#2563eb;border-radius:12px;padding:14px 28px;">'
            . '<a href="' . htmlspecialchars($dashboardUrl) . '" style="color:#fff;text-decoration:none;font-size:15px;font-weight:700;display:inline-block;">Open inquiries dashboard</a>'
            . '</td></tr></table>'
            . ($conversationLink !== ''
                ? '<p style="color:#64748b;font-size:13px;margin:0;">Guest conversation link: <a href="'
                    . htmlspecialchars($conversationLink) . '">' . htmlspecialchars($conversationLink) . '</a></p>'
                : '')
            . '<hr><p style="color:#64748b;font-size:12px;margin:0;">Automated alert from ' . htmlspecialchars($clinicName) . '.</p>';

        $altLines = ["New website inquiry from {$fullName}"];
        foreach ($detailRows as $label => $value) {
            $altLines[] = "{$label}: {$value}";
        }
        $altLines[] = '';
        $altLines[] = 'Message:';
        $altLines[] = (string)($inquiry['concern'] ?? '');
        $altLines[] = '';
        $altLines[] = "Open inquiries dashboard: {$dashboardUrl}";
        $mailer->AltBody = implode("\n", $altLines);

        $mailer->send();
        return $sentTo > 0;
    } catch (\Throwable $e) {
        error_log('sendNewInquiryStaffNotificationEmail: ' . $e->getMessage());
        return false;
    }
}

/**
 * Alert the clinic that a guest posted a new reply from the public portal.
 */
function sendInquiryGuestReplyNotificationEmail(PDO $pdo, array $inquiry, string $guestMessage): bool
{
    $recipients = inquiryNotificationRecipients($pdo);
    if ($recipients === []) {
        error_log('sendInquiryGuestReplyNotificationEmail: no recipient configured');
        return false;
    }

    $mailer = inquiryCreateMailer();
    if ($mailer === null) {
        return false;
    }

    try {
        $clinicName = inquiryClinicName($pdo);
        $fullName = trim(implode(' ', array_filter([
            $inquiry['first_name'] ?? '',
            $inquiry['middle_name'] ?? '',
            $inquiry['last_name'] ?? '',
        ]))) ?: 'A guest';

        $sentTo = 0;
        foreach ($recipients as $recipient) {
            $mailer->addAddress($recipient);
            $sentTo++;
        }

        $dashboardUrl = inquiryStaffInquiriesUrl();

        $mailer->isHTML(true);
        $mailer->Subject = 'New reply from ' . $fullName . ' on their inquiry';
        $mailer->Body = '<p style="margin:0 0 16px;">A guest replied through the '
            . htmlspecialchars($clinicName) . ' messaging portal.</p>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">'
            . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;font-size:13px;white-space:nowrap;">From</td>'
            . '<td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:600;">'
            . htmlspecialchars($fullName . ' (' . (string)($inquiry['email'] ?? '') . ')') . '</td></tr>'
            . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;font-size:13px;white-space:nowrap;">Phone</td>'
            . '<td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:600;">'
            . htmlspecialchars((string)($inquiry['phone'] ?? '')) . '</td></tr>'
            . '</table>'
            . '<p style="margin:0 0 6px;font-weight:700;color:#0f172a;">Reply</p>'
            . '<blockquote style="padding:12px 16px;border-left:4px solid #2563eb;background:#f8fafc;margin:0 0 24px;white-space:pre-wrap;">'
            . nl2br(htmlspecialchars($guestMessage))
            . '</blockquote>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">'
            . '<tr><td align="center" style="background:#2563eb;border-radius:12px;padding:14px 28px;">'
            . '<a href="' . htmlspecialchars($dashboardUrl) . '" style="color:#fff;text-decoration:none;font-size:15px;font-weight:700;display:inline-block;">Reply in dashboard</a>'
            . '</td></tr></table>'
            . '<hr><p style="color:#64748b;font-size:12px;margin:0;">Automated alert from ' . htmlspecialchars($clinicName) . '.</p>';
        $mailer->AltBody = "New reply from {$fullName} ({$inquiry['email']}):\n\n{$guestMessage}\n\nReply here: {$dashboardUrl}";

        $mailer->send();
        return $sentTo > 0;
    } catch (\Throwable $e) {
        error_log('sendInquiryGuestReplyNotificationEmail: ' . $e->getMessage());
        return false;
    }
}

/**
 * Email a staff reply to a guest, including their portal link.
 */
function sendInquiryStaffReplyEmail(
    string $email,
    string $fullName,
    string $replyMessage,
    string $conversationLink,
    ?PDO $pdo = null
): bool {
    $recipient = trim($email);
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log('sendInquiryStaffReplyEmail: invalid recipient');
        return false;
    }

    $mailer = inquiryCreateMailer();
    if ($mailer === null) {
        return false;
    }

    try {
        $clinicName = $pdo !== null ? inquiryClinicName($pdo) : 'RF Dental Clinic';
        $mailer->addAddress($recipient, $fullName);
        $mailer->isHTML(true);
        $mailer->Subject = 'Re: Your Inquiry - ' . $clinicName;
        $mailer->Body = '<p>Dear ' . htmlspecialchars($fullName) . ',</p>'
            . '<p>' . nl2br(htmlspecialchars($replyMessage)) . '</p>'
            . '<table border="0" cellpadding="0" cellspacing="0" style="margin:24px 0;">'
            . '<tr><td align="center" style="background:#2563eb;border-radius:12px;padding:14px 28px;">'
            . '<a href="' . htmlspecialchars($conversationLink) . '" style="color:#fff;text-decoration:none;font-size:15px;font-weight:700;display:inline-block;">Click here for instant reply &rarr;</a>'
            . '</td></tr></table>'
            . '<hr><p style="color:#64748b;font-size:13px;">This is a reply to your inquiry submitted through our website. Use the button above to view the full conversation and reply instantly.</p>';
        $mailer->AltBody = "Dear {$fullName},\n\n{$replyMessage}\n\nTo reply, visit: {$conversationLink}\n\n-- {$clinicName}";
        $mailer->send();
        return true;
    } catch (\Throwable $e) {
        error_log('sendInquiryStaffReplyEmail: ' . $e->getMessage());
        return false;
    }
}

function inquiryPatientAvatarJoinSql(PDO $pdo): string
{
    $matchClauses = [];

    if (inquiryColumnExists($pdo, 'inquiries', 'patient_id')) {
        $patientLinkClauses = [];
        if (inquiryColumnExists($pdo, 'users', 'patient_id')) {
            $patientLinkClauses[] = 'u2.patient_id = i.patient_id';
        }
        if (inquiryColumnExists($pdo, 'patients', 'user_id')) {
            $patientLinkClauses[] = 'u2.id = (SELECT p.user_id FROM patients p WHERE p.id = i.patient_id AND p.user_id IS NOT NULL LIMIT 1)';
        }
        if ($patientLinkClauses !== []) {
            $matchClauses[] = '(i.patient_id IS NOT NULL AND i.patient_id > 0 AND (' . implode(' OR ', $patientLinkClauses) . '))';
        }
    }

    if (inquiryColumnExists($pdo, 'inquiries', 'source')) {
        $matchClauses[] = "(LOWER(TRIM(COALESCE(i.source, ''))) = 'patient portal' AND i.email IS NOT NULL AND i.email != '' AND u2.email = i.email)";
    }

    if ($matchClauses === []) {
        return 'LEFT JOIN users up ON up.id = NULL';
    }

    $matchSql = implode("\n            OR ", $matchClauses);

    return "LEFT JOIN users up ON up.id = (
        SELECT u2.id FROM users u2
        WHERE COALESCE(u2.is_archived, 0) = 0
          AND (
            {$matchSql}
          )
        ORDER BY (u2.username = u2.email) DESC, u2.id ASC
        LIMIT 1
    )";
}

function inquiryColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function updateInquiryRecord(PDO $pdo, int $id, array $data, bool $touchUpdatedAt = true): void
{
    $setParts = [];
    $values = [];

    foreach ($data as $column => $value) {
        if (inquiryColumnExists($pdo, 'inquiries', $column)) {
            $setParts[] = "{$column} = ?";
            $values[] = $value;
        }
    }

    if ($touchUpdatedAt && inquiryColumnExists($pdo, 'inquiries', 'updated_at')) {
        $setParts[] = "updated_at = NOW()";
    }

    if ($setParts === []) {
        return;
    }

    $values[] = $id;
    $stmt = $pdo->prepare("
        UPDATE inquiries
        SET " . implode(', ', $setParts) . "
        WHERE id = ?
    ");
    $stmt->execute($values);
}

function getInquiryStatuses(): array
{
    return ['Unread', 'Read', 'Replied', 'Pending', 'Answered', 'Booked', 'New Admission'];
}

function getCompatibleEnumValue(PDO $pdo, string $table, string $column, ?string $preferredValue, array $fallbacks = [], ?string $defaultValue = null): string
{
    $preferredValue = trim((string)($preferredValue ?? ''));
    $columnDef = getColumnDefinition($pdo, $table, $column);

    if ($columnDef && preg_match('/^enum\((.*)\)$/i', (string)($columnDef['COLUMN_TYPE'] ?? ''), $matches)) {
        $allowedValues = array_map(static function (string $value): string {
            return trim($value, " '");
        }, explode(',', $matches[1]));

        $candidates = array_values(array_filter(array_unique(array_merge([$preferredValue], $fallbacks, $allowedValues)), static function (string $value): bool {
            return $value !== '';
        }));

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowedValues, true)) {
                return $candidate;
            }
        }

        return $allowedValues[0] ?? ($defaultValue ?? 'Pending');
    }

    return $preferredValue !== '' ? $preferredValue : ($defaultValue ?? 'Pending');
}

function getCompatibleInquiryStatus(PDO $pdo, ?string $preferredStatus): string
{
    return getCompatibleEnumValue($pdo, 'inquiries', 'status', $preferredStatus, ['Pending', 'Unread', 'Read', 'Replied', 'Answered', 'Booked', 'New Admission'], 'Pending');
}

function getCompatibleInquirySource(PDO $pdo, ?string $preferredSource): string
{
    return getCompatibleEnumValue($pdo, 'inquiries', 'source', $preferredSource, ['Patient Portal', 'Walk-in', 'Phone call', 'Fb messenger'], 'Walk-in');
}

function normalizeInquiryStatus(PDO $pdo, ?string $status): string
{
    $allowed = getInquiryStatuses();
    $preferred = trim((string)($status ?? ''));
    if ($preferred === '') {
        return getCompatibleInquiryStatus($pdo, 'Pending');
    }
    return in_array($preferred, $allowed, true) ? $preferred : getCompatibleInquiryStatus($pdo, $preferred);
}

function fetchInquiryServices(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchAssignableStaff(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.username, LOWER(u.role) AS role
        FROM users u
        WHERE LOWER(COALESCE(u.role, '')) IN ('staff', 'admin', 'dentist')
          AND COALESCE(u.status, 'Active') = 'Active'
        ORDER BY first_name, last_name, username
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildPatientFullName(array $patient): string
{
    return trim(implode(' ', array_filter([
        $patient['first_name'] ?? '',
        $patient['middle_name'] ?? '',
        $patient['last_name'] ?? '',
    ])));
}

function parseInquiryQuotedMessage(string $text): array
{
    if (preg_match('/^\[\[QUOTE\]\]\s*(.*?)\s*\[\[\/QUOTE\]\]\s*(.*)$/s', $text, $matches)) {
        return [
            'quote' => trim((string)$matches[1]),
            'message' => (string)$matches[2],
        ];
    }

    return ['quote' => '', 'message' => $text];
}

function renderInquiryQuotedMessageHtml(string $text): string
{
    $parsed = parseInquiryQuotedMessage($text);
    $html = '';
    if ($parsed['quote'] !== '') {
        $html .= '<div class="inq-quote">' . nl2br(htmlspecialchars($parsed['quote'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }
    if ($parsed['message'] !== '') {
        $html .= nl2br(htmlspecialchars($parsed['message'], ENT_QUOTES, 'UTF-8'));
    }
    return $html;
}
