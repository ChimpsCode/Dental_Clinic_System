<?php
/**
 * AJAX endpoint for User Management (CRUD)
 * Only accessible to logged-in admins.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

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

    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('Invalid user ID');
        }

        $stmt = $hasStatusColumn
            ? $pdo->prepare("SELECT id, username, first_name, middle_name, last_name, email, role, status FROM users WHERE id = ?")
            : $pdo->prepare("SELECT id, username, first_name, middle_name, last_name, email, role FROM users WHERE id = ?");
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
        $role     = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $status   = trim($_POST['status'] ?? 'active');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');

        if ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $role === '') {
            jsonError('Please fill in all required fields.');
        }

        if (!in_array($role, ['admin', 'dentist', 'staff'], true)) {
            jsonError('Invalid role selected.');
        }

        if ($hasStatusColumn && !in_array($status, ['active', 'inactive'], true)) {
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

            if (!isStrongPassword($password)) {
                jsonError('Password must be at least 8 chars, with upper, lower, number, and symbol.');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            if ($hasStatusColumn && $hasFirstLoginColumn) {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, middle_name, last_name, role, status, first_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$username, $hashed, $email, $firstName, $middleName, $lastName, $role, $status]);
            } elseif ($hasStatusColumn) {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, middle_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed, $email, $firstName, $middleName, $lastName, $role, $status]);
            } elseif ($hasFirstLoginColumn) {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, middle_name, last_name, role, first_login) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$username, $hashed, $email, $firstName, $middleName, $lastName, $role]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, middle_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed, $email, $firstName, $middleName, $lastName, $role]);
            }

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
            $fields = ['username' => $username, 'email' => $email, 'first_name' => $firstName, 'middle_name' => $middleName, 'last_name' => $lastName, 'role' => $role];
            $setParts = ['username = :username', 'email = :email', 'first_name = :first_name', 'middle_name = :middle_name', 'last_name = :last_name', 'role = :role'];

            if ($hasStatusColumn) {
                $fields['status'] = $status;
                $setParts[] = 'status = :status';
            }

            if ($password !== '') {
                if ($password !== $confirmPassword) {
                    jsonError('Passwords do not match.');
                }
                if (!isStrongPassword($password)) {
                    jsonError('Password must be at least 8 chars, with upper, lower, number, and symbol.');
                }
                $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
                $setParts[] = 'password = :password';
            }

            $fields['id'] = $id;
            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($fields);

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

        // Prevent deleting yourself
        if ($id === (int)$_SESSION['user_id']) {
            jsonError('You cannot delete your own account.');
        }

        // Prevent deleting primary admin
        if ($id === 1) {
            jsonError('You cannot delete the primary admin account.');
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        exit;
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($id <= 0) {
            jsonError('Invalid user ID.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
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

