<?php

/**
 * Remember the browser that signed in, and alert the account when a new one appears.
 * The first saved browser for an account is quiet. Later new browsers get an email
 * and an in-app notification. Failures here must not block login.
 */

function recordLoginDeviceAndAlert(PDO $pdo, array $user): void
{
    try {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        ensureLoginDeviceSchema($pdo);
        stampSessionAuthVersion($pdo, $userId);

        $token = loginDeviceToken();
        if ($token === null) {
            return;
        }

        $tokenHash = hash('sha256', $token);
        $userAgent = substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
        $ip = substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
        $userAgentValue = $userAgent !== '' ? $userAgent : null;
        $ipValue = $ip !== '' ? $ip : null;

        $known = $pdo->prepare('SELECT id FROM user_login_devices WHERE user_id = ? AND token_hash = ? LIMIT 1');
        $known->execute([$userId, $tokenHash]);
        $existingId = $known->fetchColumn();
        if ($existingId) {
            $upd = $pdo->prepare('UPDATE user_login_devices SET last_seen_at = NOW(), user_agent = ?, ip_address = ? WHERE id = ? AND user_id = ?');
            $upd->execute([$userAgentValue, $ipValue, (int)$existingId, $userId]);
            return;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM user_login_devices WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $priorDevices = (int)$countStmt->fetchColumn();

        $ins = $pdo->prepare('INSERT INTO user_login_devices (user_id, token_hash, user_agent, ip_address, last_seen_at) VALUES (?, ?, ?, ?, NOW())');
        $ins->execute([$userId, $tokenHash, $userAgentValue, $ipValue]);

        if ($priorDevices === 0) {
            return;
        }

        sendNewDeviceLoginAlert($pdo, $user, $userAgent);
    } catch (Throwable $e) {
        error_log('recordLoginDeviceAndAlert: ' . $e->getMessage());
    }
}

function stampSessionAuthVersion(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT auth_version FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $version = $stmt->fetchColumn();
        if ($version === false) {
            return;
        }
        $_SESSION['auth_version'] = (int)$version;
    } catch (Throwable $e) {
        // The column is added when a password is saved. Until then, a missing value counts as 0.
    }
}

function bumpUserAuthVersion(PDO $pdo, int $userId): void
{
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'auth_version'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec('ALTER TABLE users ADD COLUMN auth_version INT NOT NULL DEFAULT 0');
    }
    $upd = $pdo->prepare('UPDATE users SET auth_version = auth_version + 1 WHERE id = ? LIMIT 1');
    $upd->execute([$userId]);
    stampSessionAuthVersion($pdo, $userId);
}

function ensureLoginDeviceSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_login_devices` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_token` (`user_id`, `token_hash`),
            KEY `idx_user_login_devices_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

function loginDeviceToken(): ?string
{
    $cookieName = 'dcs_device';
    $existing = (string)($_COOKIE[$cookieName] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/i', $existing)) {
        return strtolower($existing);
    }

    if (headers_sent()) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $secure = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
    );

    setcookie($cookieName, $token, [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieName] = $token;

    return $token;
}

function describeLoginClient(string $userAgent): string
{
    $ua = $userAgent;
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false || stripos($ua, 'FxiOS/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    } else {
        $browser = 'Unknown browser';
    }

    if (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iPhone or iPad';
    } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
        $os = 'Mac';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    } else {
        $os = 'unknown device';
    }

    return $browser . ' on ' . $os;
}

function sendNewDeviceLoginAlert(PDO $pdo, array $user, string $userAgent): void
{
    $when = date('F j, Y g:i A');
    $client = describeLoginClient($userAgent);
    $title = 'New device sign-in';
    $message = 'Your account was signed in from ' . $client . ' on ' . $when . '. If this was not you, change your password.';

    $role = strtolower((string)($user['role'] ?? 'user'));
    if ($role === 'patient') {
        $role = 'user';
    }
    if ($role === 'administrator') {
        $role = 'admin';
    }
    $actionUrl = $role === 'user' ? 'settings.php' : null;

    require_once __DIR__ . '/notification_functions.php';
    createNotification($pdo, (int)$user['id'], 'system', $title, $message, $actionUrl);

    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $firstName = trim((string)($user['first_name'] ?? ''));
    if ($firstName === '') {
        $firstName = trim((string)($user['username'] ?? 'there'));
    }
    if ($firstName === '') {
        $firstName = 'there';
    }

    $resetUrl = '';
    try {
        $resetUrl = issueNewDevicePasswordLink($pdo, (int)$user['id']) ?? '';
    } catch (Throwable $e) {
        error_log('issueNewDevicePasswordLink: ' . $e->getMessage());
    }

    sendNewDeviceLoginEmail($email, $firstName, $client, $when, $resetUrl);
}

function ensureDeviceResetColumns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = [];
    $colStmt = $pdo->query('SHOW COLUMNS FROM users');
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }
    if (!in_array('device_reset_token_hash', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN device_reset_token_hash CHAR(64) NULL DEFAULT NULL');
    }
    if (!in_array('device_reset_expires', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN device_reset_expires DATETIME NULL DEFAULT NULL');
    }
    $ensured = true;
}

function newDevicePasswordPageUrl(string $token): string
{
    $https = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
    );
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');
    if (substr($dir, -12) !== 'welcome-page') {
        $masterPos = strpos($script, '/master/');
        $dir = $masterPos !== false
            ? substr($script, 0, $masterPos) . '/master/welcome-page'
            : '/master/welcome-page';
    }

    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/new-device-password.php?token=' . rawurlencode($token);
}

function issueNewDevicePasswordLink(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }
    ensureDeviceResetColumns($pdo);
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $upd = $pdo->prepare('UPDATE users SET device_reset_token_hash = ?, device_reset_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ? LIMIT 1');
    $upd->execute([$hash, $userId]);
    return newDevicePasswordPageUrl($token);
}

function findUserIdForDeviceResetToken(PDO $pdo, string $token): int
{
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return 0;
    }
    try {
        ensureDeviceResetColumns($pdo);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE device_reset_token_hash = ? AND device_reset_expires IS NOT NULL AND device_reset_expires > NOW() LIMIT 1');
        $stmt->execute([hash('sha256', strtolower($token))]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('findUserIdForDeviceResetToken: ' . $e->getMessage());
        return 0;
    }
}

function sendNewDeviceLoginEmail(string $email, string $firstName, string $client, string $when, string $resetUrl = ''): bool
{
    $configPath = __DIR__ . '/../config/mailer_config.php';
    if (!file_exists($configPath)) {
        error_log('sendNewDeviceLoginEmail: mailer_config.php not found');
        return false;
    }
    require_once $configPath;

    if (function_exists('mailer_config_validate')) {
        $configErrors = mailer_config_validate();
        if ($configErrors !== []) {
            error_log('sendNewDeviceLoginEmail: SMTP config invalid: ' . implode(' ', $configErrors));
            return false;
        }
    }

    $phpMailerBase = __DIR__ . '/../PHPMailer/src';
    if (!file_exists($phpMailerBase . '/PHPMailer.php')) {
        error_log('sendNewDeviceLoginEmail: PHPMailer not found');
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
        $mailer->isHTML(true);
        $mailer->Subject = 'RF Dental Clinic - New device sign-in';

        $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $safeClient = htmlspecialchars($client, ENT_QUOTES, 'UTF-8');
        $safeWhen = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
        $button = '';
        $plainLink = '';
        if ($resetUrl !== '') {
            $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
            $button = '<p style="margin:24px 0;"><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#0d5bd7;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Save and sign out other devices</a></p>';
            $plainLink = ' Save and sign out other devices: ' . $resetUrl;
        }
        $mailer->Body = '<p>Hi ' . $safeName . ',</p>'
            . '<p>Your account was signed in from <strong>' . $safeClient . '</strong> on ' . $safeWhen . '.</p>'
            . '<p>If this was you, you can ignore this email. If this was not you, change your password.</p>'
            . $button;
        $mailer->AltBody = 'Hi ' . $firstName . ', your account was signed in from ' . $client . ' on ' . $when
            . '. If this was you, you can ignore this email. If this was not you, change your password.'
            . $plainLink;

        $mailer->send();
        return true;
    } catch (Throwable $e) {
        error_log('sendNewDeviceLoginEmail: ' . $e->getMessage());
        return false;
    }
}
