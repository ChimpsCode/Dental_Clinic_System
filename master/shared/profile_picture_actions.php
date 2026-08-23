<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$userId) {
    ob_end_clean();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    } else {
        header('Location: ../welcome-page/login.php');
    }
    exit();
}

try {
    require_once __DIR__ . '/../../config/database.php';

    $colCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $colCheck->execute();
    if (!$colCheck->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER updated_at");
    }

    if ($action === 'upload') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error.');
        }

        $file = $_FILES['profile_picture'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Only JPG, PNG, GIF, and WebP images are allowed.');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size must be under 2MB.');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $userId . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../assets/images/profiles/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Failed to save file.');
        }

        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldPic = $stmt->fetchColumn();
        if ($oldPic) {
            $oldPath = __DIR__ . '/../../' . $oldPic;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $relativePath = 'assets/images/profiles/' . $filename;
        $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $updateStmt->execute([$relativePath, $userId]);

        $_SESSION['profile_picture'] = $relativePath;
        session_write_close();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture updated successfully.',
                'path' => $relativePath
            ]);
            exit();
        } else {
            $referer = $_SERVER['HTTP_REFERER'] ?? '../staff/staff_dashboard.php';
            ob_end_clean();
            header('Location: ' . $referer);
            exit();
        }
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $pic = $stmt->fetchColumn();
        if ($pic) {
            $filePath = __DIR__ . '/../../' . $pic;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $updateStmt->execute([$userId]);

        $_SESSION['profile_picture'] = null;
        session_write_close();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
            exit();
        } else {
            $referer = $_SERVER['HTTP_REFERER'] ?? '../staff/staff_dashboard.php';
            ob_end_clean();
            header('Location: ' . $referer);
            exit();
        }
    }

    throw new Exception('Invalid action.');
} catch (Exception $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        $referer = $_SERVER['HTTP_REFERER'] ?? '../staff/staff_dashboard.php';
        $_SESSION['upload_error'] = $e->getMessage();
        ob_end_clean();
        header('Location: ' . $referer);
    }
    exit();
}
