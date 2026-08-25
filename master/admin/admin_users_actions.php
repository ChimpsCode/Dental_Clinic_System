<?php
/**
 * AJAX endpoint for User Management (CRUD)
 * Only accessible to logged-in admins.
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/patient_user_sync_helper.php';
require_once __DIR__ . '/../../includes/audit_helper.php';

// Basic auth check – must be logged in admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}   

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function isStrongPassword($pwd) {
    return is_string($pwd)
        && strlen($pwd) >= 8
        && preg_match('/[A-Z]/', $pwd)
        && preg_match('/[a-z]/', $pwd)
        && preg_match('/[0-9]/', $pwd)
        && preg_match('/[^A-Za-z0-9]/', $pwd);
}

/**
 * Persist address / emergency-contact details to the linked patients row.
 * These fields live on the clinical record; the login account links to it.
 */
function savePatientContactDetails(PDO $pdo, int $userId, array $input): void {
    $patientId = getLinkedPatientId($pdo, $userId);
    if (!$patientId) {
        return;
    }
    $map = [
        'address' => 'street_address',
        'emergency_contact_name' => 'emergency_contact_name',
        'emergency_contact_phone' => 'emergency_contact_phone',
        'emergency_contact_relationship' => 'emergency_contact_relationship'
    ];
    $set = [];
    $vals = [];
    foreach ($map as $postKey => $col) {
        if (!pusync_tableColumn($pdo, 'patients', $col)) continue;
        if (!array_key_exists($postKey, $input)) continue;
        $set[] = "`$col` = ?";
        $vals[] = trim((string)$input[$postKey]) !== '' ? trim((string)$input[$postKey]) : null;
    }
    if (!$set) return;
    $vals[] = $patientId;
    $pdo->prepare("UPDATE patients SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);
}

try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatusColumn = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
    $firstLoginStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_login'");
    $hasFirstLoginColumn = (bool)$firstLoginStmt->fetch(PDO::FETCH_ASSOC);
    $phoneColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $hasPhoneColumn = (bool)$phoneColStmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Invalid user ID');
        }

        $sql = "SELECT u.id, u.username, u.first_name, u.middle_name, u.last_name, u.email, u.status, u.role, u.gender, u.date_of_birth";
        if ($hasPhoneColumn) {
            $sql .= ", u.phone";
        }
        $sql .= " FROM users u WHERE u.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            jsonError('User not found', 404);
        }

        if (!$hasStatusColumn) {
            // Provide a virtual status field for UI (all users treated as active for now)
            $user['status'] = 'active';
        }

        echo json_encode(['success' => true, 'user' => $user]);
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $firstName  = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $lastName   = trim($_POST['lastName'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phoneNumber'] ?? '');
        $gender   = trim($_POST['gender'] ?? '');
        $dateOfBirth = trim($_POST['dateOfBirth'] ?? '');
        $role     = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $status   = trim($_POST['status'] ?? 'active');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');

        if ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $role === '') {
            jsonError('Please fill in all required fields.');
        }

        if ($phoneNumber !== '' && !preg_match('/^[0-9]{0,11}$/', $phoneNumber)) {
            jsonError('Phone number must be up to 11 digits.');
        }

        if (!in_array($role, ['admin', 'dentist', 'staff', 'patient'], true)) {
            jsonError('Invalid role selected.');
        }

        if ($hasStatusColumn && !in_array(ucfirst($status), ['Active', 'Inactive'], true)) {
            jsonError('Invalid status selected.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email address.');
        }

        if ($action === 'create') {
            // Ensure username is unique
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                jsonError('Username is already taken.');
            }

            // Ensure email is unique among active accounts (duplicate emails make
            // login ambiguous - the password change may land on the wrong copy)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_archived = 0 LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                jsonError('Email is already used by another active account.');
            }

            // If password is empty, generate a random one
            if ($password === '') {
                $password = substr(bin2hex(random_bytes(8)), 0, 10);
            }

            if ($confirmPassword !== '' && $password !== $confirmPassword) {
                jsonError('Passwords do not match.');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $roleNameMap = ['admin' => 'Admin', 'dentist' => 'Dentist', 'staff' => 'Staff', 'patient' => 'Patient'];
            $roleValue = $roleNameMap[strtolower($role)] ?? 'Staff';

            $fields = ['username', 'password', 'email', 'first_name', 'middle_name', 'last_name', 'role', 'status'];
            $values = [$username, $hashed, $email, $firstName, $middleName, $lastName, $roleValue, ucfirst($status)];
            
            if ($gender !== '' && in_array($gender, ['Male', 'Female'])) {
                $fields[] = 'gender';
                $values[] = $gender;
            }
            if ($dateOfBirth !== '') {
                $fields[] = 'date_of_birth';
                $values[] = $dateOfBirth;
            }
            if ($hasPhoneColumn && $phoneNumber !== '') {
                $fields[] = 'phone';
                $values[] = $phoneNumber;
            }
            
            $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (?" . str_repeat(",?", count($fields) - 1) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $newId = (int)$pdo->lastInsertId();
            $displayName = trim($firstName . ' ' . $lastName);

            // Patient-role accounts always own a clinical record: create it and link it
            // so User Management and Patient Records stay one and the same person.
            if (strtolower($role) === 'patient') {
                try {
                    $patCols = ['first_name', 'middle_name', 'last_name', 'email', 'gender'];
                    $patVals = [$firstName, $middleName !== '' ? $middleName : null, $lastName, $email, null];
                    if ($hasPhoneColumn && $phoneNumber !== '') {
                        $patCols[] = 'phone';
                        $patVals[] = $phoneNumber;
                    }
                    if ($dateOfBirth !== '') {
                        $patCols[] = 'date_of_birth';
                        $patVals[] = $dateOfBirth;
                    }
                    if ($gender !== '' && in_array($gender, ['Male', 'Female'])) {
                        foreach ($patCols as $i => $c) {
                            if ($c === 'gender') { $patVals[$i] = $gender; }
                        }
                    } else {
                        foreach ($patCols as $i => $c) {
                            if ($c === 'gender') { $patVals[$i] = null; }
                        }
                    }
                    $patSql = "INSERT INTO patients (" . implode(', ', $patCols) . ") VALUES (" . implode(', ', array_fill(0, count($patCols), '?')) . ")";
                    $pdo->prepare($patSql)->execute($patVals);
                    $newPatientId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE users SET patient_id = ? WHERE id = ?")->execute([$newPatientId, $newId]);
                } catch (Exception $e) {
                    error_log('admin_users_actions create patient record failed: ' . $e->getMessage());
                }

                // Address / emergency contacts belong on the clinical record.
                savePatientContactDetails($pdo, $newId, $_POST);
            }

            echo json_encode([
                'success' => true,
                'message' => 'User created successfully.',
                'user' => [
                    'id' => $newId,
                    'username' => $username,
                    'full_name' => $displayName,
                    'email' => $email,
                    'role' => $role,
                    'status' => 'active',
                    // 'generatedPassword' is optionally returned for admin to note down
                    'generatedPassword' => $password
                ]
            ]);
            exit;
        }

        if ($action === 'update') {
            if ($id <= 0) {
                jsonError('Invalid user ID.');
            }

            // Prevent non-super admins from demoting or editing the primary admin user (id=1) unintentionally
            if ($id === 1 && $_SESSION['user_id'] !== 1) {
                jsonError('You cannot modify the primary admin account.');
            }

            $beforeStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $beforeStmt->execute([$id]);
            $oldUser = $beforeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldUser) {
                jsonError('User not found.', 404);
            }

            // Check username uniqueness (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                jsonError('Username is already taken by another user.');
            }

            // Check email uniqueness among active accounts (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? AND is_archived = 0 LIMIT 1");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                jsonError('Email is already used by another active account.');
            }

            // Build update query dynamically (password optional)
            $roleNameMap = ['admin' => 'Admin', 'dentist' => 'Dentist', 'staff' => 'Staff', 'patient' => 'Patient'];
            $roleValue = $roleNameMap[strtolower($role)] ?? 'Staff';
            $fields = ['username' => $username, 'email' => $email, 'first_name' => $firstName, 'middle_name' => $middleName, 'last_name' => $lastName, 'role' => $roleValue];
            $setParts = ['username = :username', 'email = :email', 'first_name = :first_name', 'middle_name = :middle_name', 'last_name = :last_name', 'role = :role'];

            if ($hasStatusColumn) {
                $fields['status'] = $status;
                $setParts[] = 'status = :status';
            }

            if ($gender !== '' && in_array($gender, ['Male', 'Female'])) {
                $fields['gender'] = $gender;
                $setParts[] = 'gender = :gender';
            } elseif ($gender === '') {
                $fields['gender'] = null;
                $setParts[] = 'gender = :gender';
            }

            if ($dateOfBirth !== '') {
                $fields['date_of_birth'] = $dateOfBirth;
                $setParts[] = 'date_of_birth = :date_of_birth';
            } elseif ($dateOfBirth === '') {
                $fields['date_of_birth'] = null;
                $setParts[] = 'date_of_birth = :date_of_birth';
            }

            if ($password !== '') {
                if ($password !== $confirmPassword) {
                    jsonError('Passwords do not match.');
                }
                $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
                $setParts[] = 'password = :password';
            }

            $fields['id'] = $id;
            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($fields);

            // Patient accounts share one identity with their clinical record:
            // mirror the change into the linked patients row.
            if ($roleValue === 'Patient') {
                syncUserToPatient($pdo, $id);
            }

            // Address / emergency contacts belong on the clinical record.
            savePatientContactDetails($pdo, $id, $_POST);

            $afterStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $afterStmt->execute([$id]);
            $newUser = $afterStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            logRecordChange($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? 'unknown', 'admin', 'update', 'users', 'users', $id, 'user record #' . $id, $oldUser, $newUser);

            // Refresh session display_name if editing own profile
            if ((int)$id === (int)$_SESSION['user_id']) {
                $firstNameOnly = explode(' ', $firstName)[0] ?? '';
                $roleLabel = ['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'User'];
                $_SESSION['display_name'] = trim($firstNameOnly ?: ($roleLabel[$role] ?? $username));
            }

            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully.'
            ]);
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Invalid user ID.');
        }

        // Prevent archiving yourself
        if ($id === (int)$_SESSION['user_id']) {
            jsonError('You cannot archive your own account.');
        }

        // Prevent archiving primary admin
        if ($id === 1) {
            jsonError('You cannot archive the primary admin account.');
        }

        // Check if is_archived column exists
        $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_archived'");
        if (!$checkCol->fetch(PDO::FETCH_ASSOC)) {
            jsonError('Archive feature not configured.');
        }

        $beforeStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $beforeStmt->execute([$id]);
        $oldUser = $beforeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldUser) {
            jsonError('User not found.', 404);
        }

        // Soft delete - archive the user together with its linked patient record
        // (one person = one archive entry, linked via users.patient_id).
        archiveUserWithPatient($pdo, $id, true);

        $newUser = $oldUser;
        $newUser['is_archived'] = 1;
        logRecordChange($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? 'unknown', 'admin', 'archive', 'users', 'users', $id, 'user record #' . $id . ' archived', $oldUser, $newUser);

        echo json_encode(['success' => true, 'message' => 'User archived successfully.']);
        exit;
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($id <= 0) {
            jsonError('Invalid user ID.');
        }

        if (!in_array(ucfirst($status), ['Active', 'Inactive'], true)) {
            jsonError('Invalid status.');
        }

        $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
        if (!$colStmt->fetch(PDO::FETCH_ASSOC)) {
            jsonError('Status column not available.', 400);
        }

        // Prevent deactivating primary admin
        if ($id === 1 && $status === 'inactive') {
            jsonError('You cannot deactivate the primary admin account.');
        }

        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        echo json_encode(['success' => true, 'message' => 'User status updated.']);
        exit;
    }

    // Default / unknown action
    jsonError('Invalid action.', 400);
} catch (Exception $e) {
    error_log('admin_users_actions error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}

