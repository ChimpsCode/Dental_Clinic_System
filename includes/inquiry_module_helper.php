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

function ensureInquiryModuleSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (!inquiryModuleColumnExists($pdo, 'inquiries', 'status')) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER status_id");
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
        $pdo->exec("ALTER TABLE inquiries MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
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

    // Backfill modern fields from legacy data when legacy columns are available.
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

    $ensured = true;
}

function getInquiryStatuses(): array
{
    return ['Pending', 'Contacted', 'Awaiting Confirmation', 'Scheduled', 'Completed', 'Cancelled'];
}

function normalizeInquiryStatus(?string $status): string
{
    $allowed = getInquiryStatuses();
    return in_array($status, $allowed, true) ? $status : 'Pending';
}

function fetchInquiryServices(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchAssignableStaff(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.username, LOWER(r.role_name) AS role
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE LOWER(COALESCE(r.role_name, '')) IN ('staff', 'admin', 'dentist')
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
