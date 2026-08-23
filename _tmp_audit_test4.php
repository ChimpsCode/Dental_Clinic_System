<?php
require_once __DIR__ . '/includes/session_bootstrap.php';
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'staff';
$_SESSION['username'] = 'test';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/audit_helper.php';
require_once __DIR__ . '/includes/discount_helper.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
logDiscountAudit($pdo, 'discount', 59, 'REPRO A');
$r = logAudit($pdo, 2, 'test', 'staff', 'discount', 'Billing', 'REPRO B direct', 59, 'billing');
var_dump($r);
$c = $pdo->query("SELECT id, description FROM audit_logs WHERE description LIKE 'REPRO%' OR description LIKE 'TEST%' ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
print_r($c);
