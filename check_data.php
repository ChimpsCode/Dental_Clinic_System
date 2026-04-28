<?php
require 'master/includes/db_connect.php';

echo "=== Billing Records ===" . PHP_EOL;
$stmt = $pdo->query('SELECT COUNT(*) FROM billing');
echo "Total billing records: " . $stmt->fetchColumn() . PHP_EOL;

$stmt = $pdo->query('SELECT COUNT(*) FROM billing WHERE treatment_id IS NULL');
echo "Billing without treatment_id: " . $stmt->fetchColumn() . PHP_EOL;

$stmt = $pdo->query('SELECT COUNT(*) FROM billing WHERE treatment_id IS NOT NULL');
echo "Billing with treatment_id: " . $stmt->fetchColumn() . PHP_EOL;

echo PHP_EOL . "=== Sample Billing Records ===" . PHP_EOL;
$stmt = $pdo->query('SELECT id, patient_id, treatment_id, total_amount, paid_amount FROM billing LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo PHP_EOL . "=== Payments Table ===" . PHP_EOL;
$stmt = $pdo->query('SELECT COUNT(*) FROM payments');
echo "Total payments: " . $stmt->fetchColumn() . PHP_EOL;

$stmt = $pdo->query('SELECT SUM(amount) as total FROM payments');
echo "Total paid (from payments table): " . $stmt->fetchColumn() . PHP_EOL;
