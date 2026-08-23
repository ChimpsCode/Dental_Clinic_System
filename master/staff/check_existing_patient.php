<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
ob_clean();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$firstName = trim($_GET['first_name'] ?? '');
$lastName = trim($_GET['last_name'] ?? '');
$birthdate = trim($_GET['birthdate'] ?? '');

if ($firstName === '' || $lastName === '' || $birthdate === '') {
    echo json_encode(['success' => true, 'exists' => false]);
    exit();
}

$dentalHistory = null;
$patient = null;

try {
    $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, suffix, date_of_birth FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ? LIMIT 1");
    $stmt->execute([$firstName, $lastName, $birthdate]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        $dhStmt = $pdo->prepare("SELECT previous_dentist, last_visit_date, reason_last_visit, previous_treatments, current_complaints FROM dental_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
        $dhStmt->execute([$patient['id']]);
        $dentalHistory = $dhStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

echo json_encode([
    'success' => true,
    'exists' => (bool)$patient,
    'patient' => $patient ?: null,
    'dental_history' => $dentalHistory
]);
