<?php
header('Content-Type: application/json');

session_start();
require_once '../../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, name, duration_minutes, price, mode FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("API services: " . count($services) . " found");
    foreach ($services as $s) {
        error_log("  - Service: id=" . $s['id'] . ", name=" . $s['name']);
    }
    
    echo json_encode([
        'success' => true,
        'services' => $services
    ]);
} catch (Exception $e) {
    error_log("API services error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load services: ' . $e->getMessage()
    ]);
}