<?php
/**
 * AJAX endpoint for User Management (CRUD)
 * Only accessible to logged-in admins.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

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

            // Check username uniqueness (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                jsonError('Username is already taken by another user.');
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

        // Get user details first
        $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $userStmt->execute([$id]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        // Soft delete - set is_archived = 1
        $hasArchivedAt = $pdo->query("SHOW COLUMNS FROM users LIKE 'archived_at'")->fetch(PDO::FETCH_ASSOC);
        if ($hasArchivedAt) {
            $stmt = $pdo->prepare("UPDATE users SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_archived = 1 WHERE id = ?");
        }
        $stmt->execute([$id]);

        // Also check if there's a matching patient in patients table to archive
        if ($userData) {
            $firstName = trim($userData['first_name'] ?? '');
            $lastName = trim($userData['last_name'] ?? '');
            $email = trim($userData['email'] ?? '');
            
            // Try to find patient by email first (most reliable), then by name
            $patientMatch = null;
            if ($email) {
                $patientCheck = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND is_archived = 0 LIMIT 1");
                $patientCheck->execute([$email]);
                $patientMatch = $patientCheck->fetch(PDO::FETCH_ASSOC);
            }
            if (!$patientMatch && $firstName && $lastName) {
                $patientCheck = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? AND is_archived = 0 LIMIT 1");
                $patientCheck->execute([$firstName, $lastName]);
                $patientMatch = $patientCheck->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($patientMatch) {
                // Check if deleted_at column exists before using it
                $colCheck = $pdo->query("SHOW COLUMNS FROM patients LIKE 'deleted_at'");
                if ($colCheck->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE patients SET is_archived = 1, deleted_at = NOW() WHERE id = ?")->execute([$patientMatch['id']]);
                } else {
                    $pdo->prepare("UPDATE patients SET is_archived = 1 WHERE id = ?")->execute([$patientMatch['id']]);
                }
            }
        }

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

