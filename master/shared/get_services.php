<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../config/database.php';
    
    $stmt = $pdo->query("SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 OR is_active IS NULL ORDER BY name ASC");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'services' => $services]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
