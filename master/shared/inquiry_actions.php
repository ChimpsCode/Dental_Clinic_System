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
               COALESCE(i.full_name, TRIM(CONCAT(COALESCE(i.first_name, ''), ' ', COALESCE(i.middle_name, ''), ' ', COALESCE(i.last_name, '')))) AS resolved_full_name,
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

    $fullName = trim((string)($inquiry['resolved_full_name'] ?? $inquiry['full_name'] ?? ''));
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
    $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $preferredDate = trim($_POST['preferred_date'] ?? '');
    $preferredTime = trim($_POST['preferred_time'] ?? '');
    $painLevel = trim($_POST['pain_level'] ?? '');
    $urgency = $_POST['urgency'] ?? 'Low';
    $status = normalizeInquiryStatus($_POST['status'] ?? 'Pending');

    if ($concern === '') {
        inquiryJson(['success' => false, 'message' => 'Concern / Message is required.']);
    }

    $urgency = in_array($urgency, ['Low', 'Medium', 'High'], true) ? $urgency : 'Low';
    $preferredDate = $preferredDate !== '' ? $preferredDate : null;
    $preferredTime = $preferredTime !== '' ? $preferredTime : null;
    $painLevel = $painLevel !== '' ? $painLevel : null;

    [$firstName, $middleName, $lastName] = splitInquiryFullName($fullName);

    $insertData = [
        'patient_id' => $patientId,
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'concern' => $concern,
        'service_id' => $serviceId,
        'preferred_date' => $preferredDate,
        'preferred_time' => $preferredTime,
        'pain_level' => $painLevel,
        'urgency' => $urgency,
        'status' => $status,
        'assigned_staff_id' => null,
        'notes' => null,
        'source' => $source,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_info' => $phone,
        'inquiry_message' => $concern,
        'topic' => $serviceId,
        'topic_service_id' => $serviceId,
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
    $fullName = trim($_POST['full_name'] ?? $inquiry['resolved_full_name']);
    $phone = trim($_POST['phone'] ?? ($inquiry['resolved_phone'] ?? ''));
    $email = trim($_POST['email'] ?? ($inquiry['email'] ?? ''));
    $concern = trim($_POST['concern'] ?? ($inquiry['resolved_concern'] ?? ''));
    $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $preferredDate = trim($_POST['preferred_date'] ?? '') ?: null;
    $preferredTime = trim($_POST['preferred_time'] ?? '') ?: null;
    $painLevel = trim($_POST['pain_level'] ?? '') ?: null;
    $urgency = in_array($_POST['urgency'] ?? '', ['Low', 'Medium', 'High'], true) ? $_POST['urgency'] : ($inquiry['urgency'] ?? 'Low');

    if ($fullName === '' || $concern === '') {
        inquiryJson(['success' => false, 'message' => 'Full name and concern are required.']);
    }

    [$firstName, $middleName, $lastName] = splitInquiryFullName($fullName);

    $updateData = [
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'concern' => $concern,
        'service_id' => $serviceId,
        'preferred_date' => $preferredDate,
        'preferred_time' => $preferredTime,
        'pain_level' => $painLevel,
        'urgency' => $urgency,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_info' => $phone,
        'inquiry_message' => $concern,
        'topic' => $serviceId,
        'topic_service_id' => $serviceId,
    ];
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

    if ($appointmentDate === '' || $appointmentTime === '') {
        inquiryJson(['success' => false, 'message' => 'Appointment date and time are required.']);
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
        'notes' => trim("Converted from inquiry #{$id}. " . ($inquiry['resolved_concern'] ?? '')),
        'status' => 'scheduled',
        'created_by' => (int)$_SESSION['user_id'],
        'is_registered_patient' => !empty($inquiry['patient_id']) ? 1 : 0,
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
        'preferred_date' => $appointmentDate,
        'preferred_time' => $appointmentTime,
    ];
    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $inquiryStatusUpdate['status_id'] = resolveInquiryStatusId($pdo, 'Scheduled');
    }
    updateInquiryRecord($pdo, $id, $inquiryStatusUpdate);

    $pdo->commit();
    inquiryJson(['success' => true, 'message' => 'Inquiry converted to appointment successfully.']);
}

inquiryJson(['success' => false, 'message' => 'Unknown action.']);
