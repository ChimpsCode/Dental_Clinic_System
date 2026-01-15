<?php
ob_start();
session_start();

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
$action = $data['action'] ?? 'admission';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        exit();
    }
    
    if ($action === 'admission') {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Forwarded to Admission', updated_at = NOW() WHERE id = ?");
    } elseif ($action === 'appointment') {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Forwarded to Appointment', updated_at = NOW() WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Forwarded', updated_at = NOW() WHERE id = ?");
    }
    $stmt->execute([$id]);
    
    $redirectUrl = $action === 'admission' ? 'NewAdmission.php' : 'appointments.php';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Inquiry forwarded successfully',
        'redirect' => $redirectUrl . '?inquiry_id=' . $id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
