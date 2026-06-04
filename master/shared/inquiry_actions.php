<?php
ob_start();
session_start();

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$role = $_SESSION['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_REQUEST['action'] ?? '';

function inquiryJson(array $payload): void
{
    echo json_encode($payload);
    exit();
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

function inquiryCanManage(string $role): bool
{
    return in_array($role, ['staff', 'admin', 'dentist'], true);
}

function inquiryCanPatient(string $role): bool
{
    return $role === 'user';
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

function resolveInquiryStatusId(PDO $pdo, string $statusName): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE status_name = ? LIMIT 1");
    $stmt->execute([$statusName]);
    $statusId = $stmt->fetchColumn();
    return $statusId !== false ? (int)$statusId : null;
}

function resolveAppointmentStatusId(PDO $pdo, string $statusName): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM appointment_statuses WHERE status_name = ? LIMIT 1");
    $stmt->execute([$statusName]);
    $statusId = $stmt->fetchColumn();
    return $statusId !== false ? (int)$statusId : null;
}

function inquiryTimeToMinutes(?string $timeValue): ?int
{
    $timeValue = trim((string)$timeValue);
    if ($timeValue === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeValue)) {
        return null;
    }

    [$hours, $minutes] = array_map('intval', explode(':', substr($timeValue, 0, 5)));
    return ($hours * 60) + $minutes;
}

function inquiryResolveServiceDuration(PDO $pdo, ?int $serviceId): int
{
    if ($serviceId === null || $serviceId <= 0) {
        return 30;
    }

    $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ? LIMIT 1");
    $stmt->execute([$serviceId]);
    $duration = (int)$stmt->fetchColumn();
    return $duration > 0 ? $duration : 30;
}

function inquiryScheduleConflictExists(PDO $pdo, string $date, string $time, ?int $serviceId, int $excludeInquiryId = 0): bool
{
    $targetStart = inquiryTimeToMinutes($time);
    if ($targetStart === null) {
        return false;
    }

    $targetEnd = $targetStart + inquiryResolveServiceDuration($pdo, $serviceId);

    $apptStmt = $pdo->prepare("
        SELECT a.appointment_time,
               COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
        FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id
        WHERE a.appointment_date = ?
          AND COALESCE(a.is_archived, 0) = 0
          AND LOWER(COALESCE(a.status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show')
    ");
    $apptStmt->execute([$date]);
    foreach ($apptStmt->fetchAll(PDO::FETCH_ASSOC) as $appointment) {
        $start = inquiryTimeToMinutes((string)($appointment['appointment_time'] ?? ''));
        if ($start === null) {
            continue;
        }
        $end = $start + max(1, (int)($appointment['duration_minutes'] ?? 30));
        if ($targetStart < $end && $targetEnd > $start) {
            return true;
        }
    }

    $inquiryStmt = $pdo->prepare("
        SELECT i.confirmed_time,
               COALESCE(s.duration_minutes, 30) AS duration_minutes
        FROM inquiries i
        LEFT JOIN services s ON s.id = i.service_id
        WHERE i.confirmed_date = ?
          AND COALESCE(i.is_archived, 0) = 0
          AND i.confirmed_time IS NOT NULL
          AND i.confirmed_time <> ''
          AND i.id <> ?
          AND LOWER(COALESCE(i.status, 'pending')) IN ('scheduled', 'awaiting confirmation', 'contacted')
    ");
    $inquiryStmt->execute([$date, $excludeInquiryId]);
    foreach ($inquiryStmt->fetchAll(PDO::FETCH_ASSOC) as $existingInquiry) {
        $start = inquiryTimeToMinutes((string)($existingInquiry['confirmed_time'] ?? ''));
        if ($start === null) {
            continue;
        }
        $end = $start + max(1, (int)($existingInquiry['duration_minutes'] ?? 30));
        if ($targetStart < $end && $targetEnd > $start) {
            return true;
        }
    }

    return false;
}

function fetchCurrentPatient(PDO $pdo, int $userId): ?array
{
    $userStmt = $pdo->prepare("
        SELECT id, first_name, middle_name, last_name, email, phone
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (inquiryColumnExists($pdo, 'patients', 'user_id')) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.first_name AS user_first_name, u.middle_name AS user_middle_name,
                   u.last_name AS user_last_name, u.email AS user_email, u.phone AS user_phone
            FROM patients p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
    } elseif (!empty($_SESSION['patient_id'])) {
        $stmt = $pdo->prepare("
            SELECT p.*, ? AS user_first_name, ? AS user_middle_name,
                   ? AS user_last_name, ? AS user_email, ? AS user_phone
            FROM patients p
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            (int)$_SESSION['patient_id'],
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, ? AS user_first_name, ? AS user_middle_name,
                   ? AS user_last_name, ? AS user_email, ? AS user_phone
            FROM patients p
            WHERE p.email = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            $user['email'] ?? '',
        ]);
    }

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchInquiryById(PDO $pdo, int $id): ?array
{
    $resolvedPhoneExpr = inquiryColumnExists($pdo, 'inquiries', 'contact_info')
        ? "COALESCE(i.phone, i.contact_info)"
        : "i.phone";

    $stmt = $pdo->prepare("
        SELECT i.*,
               TRIM(CONCAT(COALESCE(i.first_name, ''), ' ', COALESCE(i.middle_name, ''), ' ', COALESCE(i.last_name, ''))) AS resolved_full_name,
               {$resolvedPhoneExpr} AS resolved_phone,
               COALESCE(i.concern, i.inquiry_message) AS resolved_concern,
               s.name AS service_name,
               ass.first_name AS assigned_first_name,
               ass.last_name AS assigned_last_name,
               ass.username AS assigned_username
        FROM inquiries i
        LEFT JOIN services s ON s.id = i.service_id
        LEFT JOIN users ass ON ass.id = i.assigned_staff_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function splitInquiryFullName(string $fullName): array
{
    $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
    if ($fullName === '') {
        return ['', null, ''];
    }

    $parts = preg_split('/\s+/', $fullName);
    $firstName = array_shift($parts) ?: '';
    $lastName = $parts ? array_pop($parts) : $firstName;
    $middleName = $parts ? trim(implode(' ', $parts)) : null;

    return [$firstName, $middleName !== '' ? $middleName : null, $lastName];
}

function resolveInquiryPatientId(PDO $pdo, array $inquiry): ?int
{
    $existingPatientId = (int)($inquiry['patient_id'] ?? 0);
    if ($existingPatientId > 0) {
        return $existingPatientId;
    }

    $fullName = trim((string)($inquiry['resolved_full_name'] ?? ''));
    [$firstName, $middleName, $lastName] = splitInquiryFullName($fullName);
    $email = trim((string)($inquiry['email'] ?? ''));
    $phone = trim((string)($inquiry['resolved_phone'] ?? $inquiry['phone'] ?? ''));

    if ($email !== '' && inquiryColumnExists($pdo, 'patients', 'email')) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM patients
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $patientId = $stmt->fetchColumn();
        if ($patientId !== false) {
            return (int)$patientId;
        }
    }

    if ($phone !== '' && inquiryColumnExists($pdo, 'patients', 'phone')) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM patients
            WHERE phone = ?
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $patientId = $stmt->fetchColumn();
        if ($patientId !== false) {
            return (int)$patientId;
        }
    }

    if ($firstName !== '' && $lastName !== '') {
        $where = ["first_name = ?", "last_name = ?"];
        $values = [$firstName, $lastName];

        if ($middleName !== null && inquiryColumnExists($pdo, 'patients', 'middle_name')) {
            $where[] = "COALESCE(middle_name, '') = ?";
            $values[] = $middleName;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM patients
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ");
        $stmt->execute($values);
        $patientId = $stmt->fetchColumn();
        if ($patientId !== false) {
            return (int)$patientId;
        }
    }

    if ($firstName === '' || $lastName === '') {
        return null;
    }

    $patientData = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'created_at' => 'NOW()',
    ];

    $columns = [];
    $placeholders = [];
    $values = [];
    foreach ($patientData as $column => $value) {
        if (!inquiryColumnExists($pdo, 'patients', $column)) {
            continue;
        }

        $columns[] = $column;
        if ($value === 'NOW()') {
            $placeholders[] = 'NOW()';
        } else {
            $placeholders[] = '?';
            $values[] = $value;
        }
    }

    if ($columns === []) {
        return null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO patients (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")
    ");
    $stmt->execute($values);

    return (int)$pdo->lastInsertId();
}

if ($action === 'services') {
    inquiryJson(['success' => true, 'services' => fetchInquiryServices($pdo)]);
}

if ($action === 'staff_list') {
    if (!inquiryCanManage($role)) {
        inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
    }
    inquiryJson(['success' => true, 'staff' => fetchAssignableStaff($pdo)]);
}

if ($action === 'patient_context') {
    if (!inquiryCanPatient($role)) {
        inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
    }

    $patient = fetchCurrentPatient($pdo, (int)$_SESSION['user_id']);
    if (!$patient) {
        inquiryJson(['success' => false, 'message' => 'Patient record not found.']);
    }

    $fullName = buildPatientFullName([
        'first_name' => $patient['first_name'] ?: ($patient['user_first_name'] ?? ''),
        'middle_name' => $patient['middle_name'] ?: ($patient['user_middle_name'] ?? ''),
        'last_name' => $patient['last_name'] ?: ($patient['user_last_name'] ?? ''),
    ]);

    inquiryJson([
        'success' => true,
        'patient' => [
            'patient_id' => (int)$patient['id'],
            'full_name' => $fullName ?: 'Patient',
            'phone' => $patient['phone'] ?: ($patient['user_phone'] ?? ''),
            'email' => $patient['email'] ?: ($patient['user_email'] ?? ''),
        ],
        'services' => fetchInquiryServices($pdo),
    ]);
}

if ($action === 'patient_history') {
    if (!inquiryCanPatient($role)) {
        inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
    }

    $patient = fetchCurrentPatient($pdo, (int)$_SESSION['user_id']);
    if (!$patient) {
        inquiryJson(['success' => false, 'message' => 'Patient record not found.']);
    }

    $stmt = $pdo->prepare("
        SELECT i.*,
               s.name AS service_name,
               ass.first_name AS assigned_first_name,
               ass.last_name AS assigned_last_name
        FROM inquiries i
        LEFT JOIN services s ON s.id = i.service_id
        LEFT JOIN users ass ON ass.id = i.assigned_staff_id
        WHERE i.patient_id = ? AND COALESCE(i.is_archived, 0) = 0
        ORDER BY i.created_at DESC, i.id DESC
    ");
    $stmt->execute([(int)$patient['id']]);
    inquiryJson(['success' => true, 'inquiries' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        inquiryJson(['success' => false, 'message' => 'Invalid inquiry.']);
    }

    $inquiry = fetchInquiryById($pdo, $id);
    if (!$inquiry || (int)($inquiry['is_archived'] ?? 0) === 1) {
        inquiryJson(['success' => false, 'message' => 'Inquiry not found.']);
    }

    if (inquiryCanPatient($role)) {
        $patient = fetchCurrentPatient($pdo, (int)$_SESSION['user_id']);
        if (!$patient || (int)$inquiry['patient_id'] !== (int)$patient['id']) {
            inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
        }
    } elseif (!inquiryCanManage($role)) {
        inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
    }

    inquiryJson(['success' => true, 'inquiry' => $inquiry]);
}

if ($method !== 'POST') {
    inquiryJson(['success' => false, 'message' => 'Invalid request method.']);
}

if ($action === 'create') {
    if (inquiryCanPatient($role)) {
        $patient = fetchCurrentPatient($pdo, (int)$_SESSION['user_id']);
        if (!$patient) {
            inquiryJson(['success' => false, 'message' => 'Patient record not found.']);
        }

        $fullName = buildPatientFullName([
            'first_name' => $patient['first_name'] ?: ($patient['user_first_name'] ?? ''),
            'middle_name' => $patient['middle_name'] ?: ($patient['user_middle_name'] ?? ''),
            'last_name' => $patient['last_name'] ?: ($patient['user_last_name'] ?? ''),
        ]);
        $phone = trim($patient['phone'] ?: ($patient['user_phone'] ?? ''));
        $email = trim($patient['email'] ?: ($patient['user_email'] ?? ''));
        $patientId = (int)$patient['id'];
        $source = 'Patient Portal';
    } else {
        inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
    }

    $concern = trim($_POST['concern'] ?? '');

    if ($concern === '') {
        inquiryJson(['success' => false, 'message' => 'Concern / Message is required.']);
    }

    [$firstName, $middleName, $lastName] = splitInquiryFullName($fullName);

    $insertData = [
        'patient_id' => $patientId,
        'phone' => $phone,
        'email' => $email,
        'concern' => $concern,
        'status' => 'Open',
        'assigned_staff_id' => null,
        'notes' => null,
        'source' => $source,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_info' => $phone,
        'inquiry_message' => $concern,
    ];

    $columns = [];
    $placeholders = [];
    $values = [];
    foreach ($insertData as $column => $value) {
        if (inquiryColumnExists($pdo, 'inquiries', $column)) {
            $columns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }
    }

    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $statusId = null;
        $statusLookup = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE status_name = ? LIMIT 1");
        $statusLookup->execute([$status]);
        $statusId = $statusLookup->fetchColumn();
        if ($statusId !== false) {
            $columns[] = 'status_id';
            $placeholders[] = '?';
            $values[] = (int)$statusId;
        }
    }

    if (inquiryColumnExists($pdo, 'inquiries', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $stmt = $pdo->prepare("
        INSERT INTO inquiries (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")
    ");
    $stmt->execute($values);

    inquiryJson(['success' => true, 'message' => 'Inquiry submitted successfully.']);
}

if (!inquiryCanManage($role)) {
    inquiryJson(['success' => false, 'message' => 'Unauthorized.']);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    inquiryJson(['success' => false, 'message' => 'Invalid inquiry.']);
}

$inquiry = fetchInquiryById($pdo, $id);
if (!$inquiry) {
    inquiryJson(['success' => false, 'message' => 'Inquiry not found.']);
}

if ($action === 'update') {
    $fullName = trim((string)($inquiry['resolved_full_name'] ?? ''));
    $phone = trim((string)($inquiry['resolved_phone'] ?? $inquiry['phone'] ?? ''));
    $email = trim((string)($inquiry['email'] ?? ''));
    $concern = trim((string)($inquiry['resolved_concern'] ?? $inquiry['concern'] ?? ''));
    $serviceId = !empty($inquiry['service_id']) ? (int)$inquiry['service_id'] : (!empty($inquiry['topic_service_id']) ? (int)$inquiry['topic_service_id'] : null);
    $preferredDate = trim((string)($inquiry['preferred_date'] ?? '')) ?: null;
    $preferredTime = trim((string)($inquiry['preferred_time'] ?? '')) ?: null;
    $painLevel = trim((string)($inquiry['pain_level'] ?? '')) ?: null;
    $urgency = in_array($_POST['urgency'] ?? '', ['Low', 'Medium', 'High'], true) ? $_POST['urgency'] : ($inquiry['urgency'] ?? 'Low');
    $status = normalizeInquiryStatus($_POST['status'] ?? ($inquiry['status'] ?? 'Pending'));
    $assignedStaffId = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
    $confirmedDate = trim($_POST['confirmed_date'] ?? '') ?: null;
    $confirmedTime = trim($_POST['confirmed_time'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? ($inquiry['notes'] ?? ''));

    if ($fullName === '' || $concern === '') {
        inquiryJson(['success' => false, 'message' => 'Full name and concern are required.']);
    }

    if ($status === 'Scheduled' && ($confirmedDate === null || $confirmedTime === null)) {
        inquiryJson(['success' => false, 'message' => 'Confirmed date and time are required when status is Scheduled.']);
    }

    if ($confirmedDate !== null && $confirmedTime !== null && inquiryScheduleConflictExists($pdo, $confirmedDate, $confirmedTime, $serviceId, $id)) {
        inquiryJson(['success' => false, 'message' => 'The selected confirmed date and time is already reserved. Please choose a different slot.']);
    }

    if ($assignedStaffId !== null) {
        $staffStmt = $pdo->prepare("
            SELECT 1
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
              AND LOWER(COALESCE(r.role_name, '')) IN ('staff', 'admin', 'dentist')
              AND COALESCE(u.status, 'Active') = 'Active'
            LIMIT 1
        ");
        $staffStmt->execute([$assignedStaffId]);
        if (!$staffStmt->fetchColumn()) {
            inquiryJson(['success' => false, 'message' => 'Selected staff member is not available for assignment.']);
        }
    }

    [$firstName, $middleName, $lastName] = splitInquiryFullName($fullName);

    $updateData = [
        'phone' => $phone,
        'email' => $email,
        'concern' => $concern,
        'service_id' => $serviceId,
        'preferred_date' => $preferredDate,
        'preferred_time' => $preferredTime,
        'pain_level' => $painLevel,
        'urgency' => $urgency,
        'status' => $status,
        'assigned_staff_id' => $assignedStaffId,
        'confirmed_date' => $confirmedDate,
        'confirmed_time' => $confirmedTime,
        'notes' => $notes,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_info' => $phone,
        'inquiry_message' => $concern,
        'topic' => $serviceId,
        'topic_service_id' => $serviceId,
    ];
    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $updateData['status_id'] = resolveInquiryStatusId($pdo, $status);
    }
    updateInquiryRecord($pdo, $id, $updateData);

    inquiryJson(['success' => true, 'message' => 'Inquiry updated successfully.']);
}

if ($action === 'status') {
    $status = normalizeInquiryStatus($_POST['status'] ?? '');
    $statusUpdate = ['status' => $status];
    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $statusUpdate['status_id'] = resolveInquiryStatusId($pdo, $status);
    }
    updateInquiryRecord($pdo, $id, $statusUpdate);
    inquiryJson(['success' => true, 'message' => 'Status updated successfully.']);
}

if ($action === 'update_staff_schedule') {
    $status = normalizeInquiryStatus($_POST['status'] ?? ($inquiry['status'] ?? 'Pending'));
    $assignedStaffId = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
    $confirmedDate = trim($_POST['confirmed_date'] ?? '') ?: null;
    $confirmedTime = trim($_POST['confirmed_time'] ?? '') ?: null;
    $assignedDentist = trim($_POST['assigned_dentist'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if ($status === 'Scheduled' && ($confirmedDate === null || $confirmedTime === null)) {
        inquiryJson(['success' => false, 'message' => 'Confirmed appointment date and time are required when status is Scheduled.']);
    }

    $serviceId = !empty($inquiry['service_id']) ? (int)$inquiry['service_id'] : (!empty($inquiry['topic_service_id']) ? (int)$inquiry['topic_service_id'] : null);
    if ($confirmedDate !== null && $confirmedTime !== null && inquiryScheduleConflictExists($pdo, $confirmedDate, $confirmedTime, $serviceId, $id)) {
        inquiryJson(['success' => false, 'message' => 'The selected confirmed date and time is already reserved. Please choose a different slot.']);
    }

    if ($assignedStaffId !== null) {
        $staffStmt = $pdo->prepare("
            SELECT 1
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
              AND LOWER(COALESCE(r.role_name, '')) IN ('staff', 'admin', 'dentist')
              AND COALESCE(u.status, 'Active') = 'Active'
            LIMIT 1
        ");
        $staffStmt->execute([$assignedStaffId]);
        if (!$staffStmt->fetchColumn()) {
            inquiryJson(['success' => false, 'message' => 'Selected staff member is not available for assignment.']);
        }
    }

    $updateData = [
        'status' => $status,
        'assigned_staff_id' => $assignedStaffId,
        'confirmed_date' => $confirmedDate,
        'confirmed_time' => $confirmedTime,
        'assigned_dentist' => $assignedDentist,
        'notes' => $notes,
    ];
    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $updateData['status_id'] = resolveInquiryStatusId($pdo, $status);
    }

    updateInquiryRecord($pdo, $id, $updateData);
    inquiryJson(['success' => true, 'message' => 'Inquiry details updated successfully.']);
}

if ($action === 'assign') {
    $assignedStaffId = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
    if ($assignedStaffId !== null) {
        $staffStmt = $pdo->prepare("
            SELECT 1
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
              AND LOWER(COALESCE(r.role_name, '')) IN ('staff', 'admin', 'dentist')
              AND COALESCE(u.status, 'Active') = 'Active'
            LIMIT 1
        ");
        $staffStmt->execute([$assignedStaffId]);
        if (!$staffStmt->fetchColumn()) {
            inquiryJson(['success' => false, 'message' => 'Selected staff member is not available for assignment.']);
        }
    }
    updateInquiryRecord($pdo, $id, ['assigned_staff_id' => $assignedStaffId]);
    inquiryJson(['success' => true, 'message' => 'Staff assignment updated successfully.']);
}

if ($action === 'notes') {
    $notes = trim($_POST['notes'] ?? '');
    updateInquiryRecord($pdo, $id, ['notes' => $notes]);
    inquiryJson(['success' => true, 'message' => 'Internal notes saved successfully.']);
}

if ($action === 'archive') {
    updateInquiryRecord($pdo, $id, ['is_archived' => 1, 'deleted_at' => date('Y-m-d H:i:s')]);
    inquiryJson(['success' => true, 'message' => 'Inquiry archived successfully.']);
}

if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    inquiryJson(['success' => true, 'message' => 'Inquiry deleted permanently.']);
}

if ($action === 'convert_to_appointment') {
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $appointmentTime = trim($_POST['appointment_time'] ?? '');
    $assignedStaffId = !empty($_POST['assigned_staff_id']) ? (int)$_POST['assigned_staff_id'] : null;
    $assignedDentist = trim($_POST['assigned_dentist'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if ($appointmentDate === '' || $appointmentTime === '') {
        inquiryJson(['success' => false, 'message' => 'Appointment date and time are required.']);
    }

    $serviceId = !empty($inquiry['service_id']) ? (int)$inquiry['service_id'] : (!empty($inquiry['topic_service_id']) ? (int)$inquiry['topic_service_id'] : null);
    if (inquiryScheduleConflictExists($pdo, $appointmentDate, $appointmentTime, $serviceId, $id)) {
        inquiryJson(['success' => false, 'message' => 'The selected appointment slot is already reserved. Please choose a different date or time.']);
    }

    if (inquiryColumnExists($pdo, 'appointments', 'inquiry_id')) {
        $existingAppointmentStmt = $pdo->prepare("
            SELECT id
            FROM appointments
            WHERE inquiry_id = ?
            LIMIT 1
        ");
        $existingAppointmentStmt->execute([$id]);
        $existingAppointmentId = $existingAppointmentStmt->fetchColumn();
        if ($existingAppointmentId !== false) {
            inquiryJson([
                'success' => false,
                'message' => 'This inquiry has already been converted to an appointment.',
                'appointment_id' => (int)$existingAppointmentId,
            ]);
        }
    }

    $pdo->beginTransaction();

    $appointmentData = [
        'patient_id' => resolveInquiryPatientId($pdo, $inquiry),
        'service_id' => $inquiry['service_id'] ?: null,
        'inquiry_id' => $id,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'treatment' => $inquiry['service_name'] ?: 'Consultation',
        'notes' => trim("Converted from inquiry #{$id}. " . ($inquiry['resolved_concern'] ?? '') . ' ' . $notes),
        'status' => 'scheduled',
        'created_by' => (int)$_SESSION['user_id'],
        'is_registered_patient' => !empty($inquiry['patient_id']) ? 1 : 0,
        'booked_by_id' => $assignedStaffId ?: (int)$_SESSION['user_id'],
        'booking_type' => 'staff',
    ];
    $appointmentColumns = [];
    $appointmentPlaceholders = [];
    $appointmentValues = [];
    foreach ($appointmentData as $column => $value) {
        if (inquiryColumnExists($pdo, 'appointments', $column)) {
            $appointmentColumns[] = $column;
            $appointmentPlaceholders[] = '?';
            $appointmentValues[] = $value;
        }
    }
    if (inquiryColumnExists($pdo, 'appointments', 'status_id')) {
        $appointmentColumns[] = 'status_id';
        $appointmentPlaceholders[] = '?';
        $appointmentValues[] = resolveAppointmentStatusId($pdo, 'Scheduled');
    }
    if (inquiryColumnExists($pdo, 'appointments', 'created_at')) {
        $appointmentColumns[] = 'created_at';
        $appointmentPlaceholders[] = 'NOW()';
    }

    if ($appointmentColumns === [] || !in_array('appointment_date', $appointmentColumns, true) || !in_array('appointment_time', $appointmentColumns, true)) {
        $pdo->rollBack();
        inquiryJson(['success' => false, 'message' => 'Appointments table is missing required fields.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO appointments (" . implode(', ', $appointmentColumns) . ")
        VALUES (" . implode(', ', $appointmentPlaceholders) . ")
    ");
    $stmt->execute($appointmentValues);

    $inquiryStatusUpdate = [
        'status' => 'Scheduled',
        'confirmed_date' => $appointmentDate,
        'confirmed_time' => $appointmentTime,
        'assigned_staff_id' => $assignedStaffId,
        'assigned_dentist' => $assignedDentist,
        'notes' => $notes !== '' ? $notes : ($inquiry['notes'] ?? null),
    ];
    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $inquiryStatusUpdate['status_id'] = resolveInquiryStatusId($pdo, 'Scheduled');
    }
    updateInquiryRecord($pdo, $id, $inquiryStatusUpdate);

    $pdo->commit();
    inquiryJson(['success' => true, 'message' => 'Inquiry converted to appointment successfully.']);
}

if ($action === 'staff_reply') {
    $replyMessage = trim($_POST['message'] ?? '');
    if ($replyMessage === '') {
        inquiryJson(['success' => false, 'message' => 'Reply message is required.']);
    }

    $fullName = $inquiry['resolved_full_name'] ?? 'Patient';
    $timestamp = date('Y-m-d H:i:s');
    $replyEntry = "\n[Staff replied on {$timestamp}]\n{$replyMessage}";
    $existingNotes = $inquiry['notes'] ?? '';
    $newNotes = $existingNotes . $replyEntry;

    if (($inquiry['source'] ?? '') === 'Patient Portal' && !empty($inquiry['patient_id'])) {
        $patientId = (int)$inquiry['patient_id'];
        $staffUserId = (int)$_SESSION['user_id'];

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `messages` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `patient_id` INT(11) NOT NULL,
                `message` TEXT NOT NULL,
                `sent_by` ENUM('patient','admin') NOT NULL DEFAULT 'patient',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_messages_patient_id` (`patient_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        if (!inquiryColumnExists($pdo, 'messages', 'sender_type')) {
            $pdo->exec("ALTER TABLE `messages` ADD COLUMN `sender_type` ENUM('patient','admin','system') NOT NULL DEFAULT 'patient'");
        }
        if (!inquiryColumnExists($pdo, 'messages', 'sender_id')) {
            $pdo->exec("ALTER TABLE `messages` ADD COLUMN `sender_id` INT NULL");
        }
        if (!inquiryColumnExists($pdo, 'messages', 'is_read')) {
            $pdo->exec("ALTER TABLE `messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0");
        }

        $msgStmt = $pdo->prepare("
            INSERT INTO messages (patient_id, sender_id, message, sender_type, sent_by, created_at)
            VALUES (?, ?, ?, 'admin', 'admin', NOW())
        ");
        $msgStmt->execute([$patientId, $staffUserId, $replyMessage]);
    } elseif (in_array($inquiry['source'] ?? '', ['Website', 'Facebook', 'Walk-in', 'Referral']) || empty($inquiry['patient_id'])) {
        $recipientEmail = trim($inquiry['email'] ?? '');
        if ($recipientEmail !== '') {
            $mailerConfig = __DIR__ . '/../../config/mailer_config.php';
            $phpMailerBase = __DIR__ . '/../../PHPMailer/src';
            if (file_exists($mailerConfig) && file_exists($phpMailerBase . '/PHPMailer.php')) {
                require_once $mailerConfig;
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
                    $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $mailer->addAddress($recipientEmail, $fullName);
                    $mailer->isHTML(true);
                    $mailer->Subject = 'Re: Your Inquiry - RF Dental Clinic';
                    $mailer->Body = '<p>Dear ' . htmlspecialchars($fullName) . ',</p>'
                        . '<p>' . nl2br(htmlspecialchars($replyMessage)) . '</p>'
                        . '<hr><p style="color:#64748b;font-size:13px;">This is a reply to your inquiry submitted through our website.</p>';
                    $mailer->AltBody = "Dear {$fullName},\n\n{$replyMessage}\n\n-- RF Dental Clinic";
                    $mailer->send();
                } catch (\Exception $e) {
                    inquiryJson(['success' => false, 'message' => 'Failed to send email reply. ' . $e->getMessage()]);
                }
            }
        }
    }

    updateInquiryRecord($pdo, $id, ['status' => 'Replied', 'notes' => $newNotes, 'assigned_staff_id' => (int)$_SESSION['user_id']]);
    inquiryJson(['success' => true, 'message' => 'Reply sent successfully.']);
}

if ($action === 'staff_close') {
    $existingNotes = $inquiry['notes'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    $newNotes = $existingNotes . "\n[Closed by staff on {$timestamp}]";
    updateInquiryRecord($pdo, $id, ['status' => 'Closed', 'notes' => $newNotes]);
    inquiryJson(['success' => true, 'message' => 'Inquiry closed.']);
}

inquiryJson(['success' => false, 'message' => 'Unknown action.']);
