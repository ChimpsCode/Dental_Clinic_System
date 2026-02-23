<?php
session_start();
require 'config/database.php';
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo 'no session'; exit; }
$pdo->exec('UPDATE notifications SET is_read=0 WHERE user_id='.(int)$userId);
echo 'reset done';
?>
