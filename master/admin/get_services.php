<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, name, price, mode, duration_minutes FROM services WHERE is_active = 1 ORDER BY name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['services' => $services]);