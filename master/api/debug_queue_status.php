<?php
require_once __DIR__ . '/../../includes/require_admin.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

echo "<h3>queue_statuses table:</h3>";
$stmt = $pdo->query("SELECT * FROM queue_statuses ORDER BY id");
$statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($statuses, true) . "</pre>";

echo "<h3>queue table columns:</h3>";
$stmt = $pdo->query("SHOW COLUMNS FROM queue");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($columns, true) . "</pre>";

echo "<h3>Sample queue records:</h3>";
$stmt = $pdo->query("SELECT id, patient_id, status_id, status FROM queue LIMIT 5");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($records, true) . "</pre>";
?>