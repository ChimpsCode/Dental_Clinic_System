<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$role = strtolower((string)($_SESSION['role'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId || !in_array($role, ['admin', 'staff'], true) || !empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/payment_approval_helper.php';
try {
    ensurePaymentApprovalSchema($pdo);
    $_SESSION['payment_approval_csrf'] = $_SESSION['payment_approval_csrf'] ?? bin2hex(random_bytes(32));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $admins = $pdo->query('SELECT a.id, a.username, a.first_name, a.last_name FROM (' . paymentApprovalUsersSql($pdo)
            . ") a JOIN admin_payment_pins pins ON pins.user_id = a.id WHERE LOWER(a.role) = 'admin' AND pins.pin_hash IS NOT NULL ORDER BY a.first_name, a.username")
            ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'admins'=>$admins, 'csrf'=>$_SESSION['payment_approval_csrf']]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST is required.');
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (!hash_equals($_SESSION['payment_approval_csrf'], (string)($data['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Your confirmation expired. Reload the page and try again.');
    }
    if (($data['action'] ?? '') === 'set_pin') {
        if ($role !== 'admin') throw new RuntimeException('Only admins can configure their own approval PIN.');
        setPaymentApprovalPin($pdo, $userId, (string)($data['password'] ?? ''), (string)($data['pin'] ?? ''), (string)($data['confirmation'] ?? ''));
        echo json_encode(['success'=>true, 'message'=>'Payment Approval PIN configured.']);
    } elseif (($data['action'] ?? '') === 'void_payment') {
        $result = voidApprovedPayment($pdo, $userId, (int)($data['admin_id'] ?? 0), (string)($data['pin'] ?? ''),
            (int)($data['payment_id'] ?? 0), (string)($data['reason'] ?? ''));
        echo json_encode(['success'=>true, 'message'=>'Payment voided. Invoice balance updated.', 'billing'=>$result]);
    } else throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    if ($e instanceof PDOException) {
        error_log('Payment approval: ' . $e->getMessage());
        $message = 'Unable to save payment approval. Please try again.';
    } else $message = $e->getMessage();
    echo json_encode(['success'=>false, 'message'=>$message]);
}
