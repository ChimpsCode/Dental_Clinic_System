<?php
require 'config/database.php';
$stmt=$pdo->query("SELECT id, first_name, last_name, appointment_date, appointment_time, status FROM appointments ORDER BY appointment_date DESC");
var_export($stmt->fetchAll());
?>
