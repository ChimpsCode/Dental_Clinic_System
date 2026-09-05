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

    if (!tableExists($pdo, 'appointment_statuses')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS appointment_statuses (
                id INT(11) NOT NULL AUTO_INCREMENT,
                status_name VARCHAR(50) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_appointment_status_name (status_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $pdo->exec("INSERT IGNORE INTO appointment_statuses (status_name) VALUES ('Pending'), ('Confirmed'), ('Cancelled'), ('Completed')");
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

    if (inquiryModuleColumnExists($pdo, 'inquiries', 'source')) {
        $pdo->exec("ALTER TABLE inquiries MODIFY COLUMN source VARCHAR(50) NOT NULL DEFAULT 'Patient Portal'");
    }
    if (inquiryModuleColumnExists($pdo, 'inquiries', 'status')) {
        $pdo->exec("ALTER TABLE inquiries MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Unread'");
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

function ensureInquiryTypingSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `inquiry_typing_status` (
            `inquiry_id` INT(11) NOT NULL,
            `sender_type` ENUM('patient','staff') NOT NULL,
            `is_typing` TINYINT(1) NOT NULL DEFAULT 0,
            `user_id` INT(11) DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`inquiry_id`, `sender_type`),
            KEY `idx_inquiry_typing_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!inquiryModuleColumnExists($pdo, 'inquiry_typing_status', 'user_id')) {
        $pdo->exec("ALTER TABLE `inquiry_typing_status` ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `is_typing`");
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
