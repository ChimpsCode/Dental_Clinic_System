<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../config/database.php';
    
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'Dentist' AND (is_archived = 0 OR is_archived IS NULL) ORDER BY first_name ASC");
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'dentists' => $dentists]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
