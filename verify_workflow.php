<?php
/**
 * Verify Patient-Appointment Workflow
 * Run this to check if the workflow is configured correctly
 */

require_once 'config/database.php';

echo "<h2>Patient-Appointment Workflow Verification</h2>";
echo "<div style='font-family: Arial; max-width: 800px; margin: 20px;'>";

$issues = [];
$success = [];

// 1. Check appointments table structure
echo "<h3>1. Checking Appointments Table</h3>";
$cols = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'patient_id'")->fetch();
if ($cols) {
    echo "✅ patient_id column exists<br>";
    echo "   Type: {$cols['Type']}<br>";
    echo "   Nullable: {$cols['Null']}<br>";
    if ($cols['Null'] === 'YES') {
        $success[] = "patient_id can be NULL (correct for new appointments)";
    } else {
        $issues[] = "patient_id is NOT NULL - new workflow won't work!";
    }
} else {
    $issues[] = "patient_id column not found!";
}

// 2. Check if there are appointments with NULL patient_id
echo "<h3>2. Checking Appointment Data</h3>";
$nullCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id IS NULL")->fetchColumn();
$totalCount = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
echo "Total appointments: $totalCount<br>";
echo "Appointments with NULL patient_id: $nullCount<br>";

if ($nullCount > 0) {
    $success[] = "Found $nullCount appointments with NULL patient_id (ready for workflow)";
}

// 3. Check recent appointments
echo "<h3>3. Recent Appointments</h3>";
$recent = $pdo->query("SELECT id, first_name, last_name, patient_id, appointment_date FROM appointments ORDER BY created_at DESC LIMIT 5")->fetchAll();
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Patient ID</th><th>Date</th></tr>";
foreach ($recent as $apt) {
    $patientId = $apt['patient_id'] ?? 'NULL';
    $style = $patientId === 'NULL' ? 'background: #fff3cd;' : '';
    echo "<tr style='$style'>";
    echo "<td>{$apt['id']}</td>";
    echo "<td>{$apt['first_name']} {$apt['last_name']}</td>";
    echo "<td>$patientId</td>";
    echo "<td>{$apt['appointment_date']}</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Summary
echo "<h3>4. Summary</h3>";
if (empty($issues)) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "<strong>✅ All checks passed!</strong><br>";
    echo "The workflow is configured correctly.<br><br>";
    echo "<strong>How it works:</strong><br>";
    echo "1. New appointment → patient_id = NULL → Only in Appointments list<br>";
    echo "2. Forward to New Admission → Complete form<br>";
    echo "3. Patient created → appointment updated with patient_id<br>";
    echo "4. Now visible in Patient Records + Queue<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<strong>⚠️ Issues found:</strong><br>";
    foreach ($issues as $issue) {
        echo "• $issue<br>";
    }
    echo "</div>";
}

echo "<br><a href='staff_appointments.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Appointments</a>";
echo " <a href='admin_patients.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Go to Patient Records</a>";

echo "</div>";
?>
