<?php
require_once __DIR__ . '/../../includes/require_admin.php';
require_once __DIR__ . '/../../config/database.php';

echo "<h2>Database Verification</h2>";
echo "<pre>";

// Check tables
$tables = ['patient_types', 'appointment_statuses', 'queue_statuses'];
foreach ($tables as $table) {
    $result = $pdo->query("SELECT COUNT(*) as count FROM $table");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ $table: " . $row['count'] . " records\n";
}

// Check appointments columns
$result = $pdo->query("SHOW COLUMNS FROM appointments");
echo "\nAppointments columns:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Check sample appointment
$result = $pdo->query("SELECT * FROM appointments LIMIT 2");
echo "\nSample appointments:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: " . $row['id'] . " | Patient: " . $row['patient_id'] . " | Date: " . $row['appointment_date'] . " | Status ID: " . $row['status_id'] . "\n";
}

echo "\n✅ Database is ready!\n";
?>
