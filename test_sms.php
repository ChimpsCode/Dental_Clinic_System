<?php
require_once 'includes/sms_functions.php';

// Test configuration
$test_phone = '09493472725';
$test_name = 'Test Patient';
$test_date = 'April 25, 2026';
$test_time = '10:00 AM';
$test_treatment = 'General Checkup';

echo "<h2>SMS Test - RF Dental Clinic</h2>";
echo "<p>Testing SMS to: $test_phone</p>";

echo "<pre>";
$result = sendAppointmentConfirmationSMS($test_phone, $test_name, $test_date, $test_time, $test_treatment);
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "<p style='color: green;'>✅ SMS sent successfully!</p>";
    echo "<p>SMS ID: " . ($result['sms_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>❌ SMS failed!</p>";
    echo "<p>Error: " . ($result['error'] ?? 'Unknown error') . "</p>";
}
?>