<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? 'Pending';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/inquiry_module_helper.php';

    // Ensure schema helpers are available (no-op if already present)
    ensureInquiryModuleSchema($pdo);

    // Normalize status value to a valid enum / compatible value
    if (function_exists('normalizeInquiryStatus')) {
        $status = normalizeInquiryStatus($pdo, $status);
    }

    $stmt = $pdo->prepare("UPDATE inquiries SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['success' => true, 'message' => 'Inquiry status updated']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
