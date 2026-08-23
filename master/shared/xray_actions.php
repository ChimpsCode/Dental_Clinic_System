<?php
/**
 * X-Ray Actions API
 * Upload / list / delete consultation x-ray images.
 * Roles: dentist + admin can upload/delete; staff can view only.
 */

ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['dentist', 'admin', 'staff'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$canModify = in_array($role, ['dentist', 'admin']);

try {
    require_once __DIR__ . '/../../config/database.php';

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_xrays (
        id int(11) NOT NULL AUTO_INCREMENT,
        consultation_id int(11) NOT NULL,
        patient_id int(11) NOT NULL,
        dentist_id int(11) DEFAULT NULL,
        file_path varchar(255) NOT NULL,
        original_filename varchar(255) DEFAULT NULL,
        mime_type varchar(100) DEFAULT NULL,
        file_size int(11) DEFAULT NULL,
        created_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY idx_xray_patient (patient_id),
        KEY idx_xray_consultation (consultation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $uploadDirRel = 'assets/images/xrays';
    $uploadDirAbs = __DIR__ . '/../../' . $uploadDirRel;
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    switch ($action) {

        case 'get':
            $consultId = (int)($_GET['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }
            $stmt = $pdo->prepare("SELECT id, file_path, original_filename, mime_type, file_size, created_at
                FROM patient_xrays WHERE consultation_id = ? ORDER BY created_at DESC, id DESC");
            $stmt->execute([$consultId]);
            ob_end_clean();
            echo json_encode(['success' => true, 'xrays' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'upload':
            if (!$canModify) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Not allowed']);
                break;
            }
            $consultId = (int)($_POST['consultation_id'] ?? 0);
            if (!$consultId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation ID required']);
                break;
            }

            // Verify the consultation exists and capture ids
            $stmt = $pdo->prepare("SELECT id, patient_id, dentist_id FROM consultations WHERE id = ?");
            $stmt->execute([$consultId]);
            $consult = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$consult) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Consultation not found']);
                break;
            }

            if (!isset($_FILES['xray_file']) || $_FILES['xray_file']['error'] !== UPLOAD_ERR_OK) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
                break;
            }
            $file = $_FILES['xray_file'];

            if ($file['size'] > $maxSize) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'File size must be under 10MB.']);
                break;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, and WebP images are allowed.']);
                break;
            }

            // Verify real mime type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMime)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid image file.']);
                break;
            }

            if (!is_dir($uploadDirAbs)) {
                mkdir($uploadDirAbs, 0755, true);
            }

            $filename = 'xray_' . $consultId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDirAbs . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
                break;
            }

            $relativePath = $uploadDirRel . '/' . $filename;
            $stmt = $pdo->prepare("INSERT INTO patient_xrays
                (consultation_id, patient_id, dentist_id, file_path, original_filename, mime_type, file_size)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $consultId,
                $consult['patient_id'],
                $consult['dentist_id'] ?: $userId,
                $relativePath,
                $file['name'],
                $mime,
                $file['size']
            ]);
            $xrayId = $pdo->lastInsertId();

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'X-ray uploaded',
                'xray' => [
                    'id' => (int)$xrayId,
                    'file_path' => $relativePath,
                    'original_filename' => $file['name'],
                    'mime_type' => $mime,
                    'file_size' => $file['size'],
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'delete':
            if (!$canModify) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Not allowed']);
                break;
            }
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'X-ray ID required']);
                break;
            }
            $stmt = $pdo->prepare("SELECT file_path FROM patient_xrays WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'X-ray not found']);
                break;
            }
            $absPath = __DIR__ . '/../../' . $row['file_path'];
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
            $stmt = $pdo->prepare("DELETE FROM patient_xrays WHERE id = ?");
            $stmt->execute([$id]);

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'X-ray deleted']);
            break;

        default:
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log('X-Ray actions error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
