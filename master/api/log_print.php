<?php
/**
 * Log Print API - records a print event for the Print Activity report
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/print_log_helper.php';

header('Content-Type: application/json');

$role = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'staff', 'dentist'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$allowedTypes = ['Invoice', 'Queue Ticket', 'Prescription', 'Medical Certificate', 'Consent Form', 'Report', 'Audit Log'];
$documentType = trim((string)($input['document_type'] ?? ''));
if (!in_array($documentType, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document type']);
    exit;
}

$details = trim(strip_tags((string)($input['details'] ?? '')));
$referenceTable = preg_replace('/[^a-z_]/', '', strtolower((string)($input['reference_table'] ?? '')));
$referenceId = $input['reference_id'] ?? null;
$page = trim(strip_tags((string)($input['page'] ?? '')));

$ok = logPrint(
    $pdo,
    $documentType,
    $details !== '' ? $details : null,
    $referenceTable !== '' ? $referenceTable : null,
    is_numeric($referenceId) ? (int)$referenceId : null,
    $page !== '' ? $page : null
);

echo json_encode(['success' => (bool)$ok]);
