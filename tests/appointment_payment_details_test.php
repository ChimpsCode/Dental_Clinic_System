<?php
require_once __DIR__ . '/../includes/appointment_payment_details.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE appointments (id INTEGER, patient_id INTEGER)');
$pdo->exec('CREATE TABLE billing (id INTEGER, appointment_id INTEGER, patient_id INTEGER, total_amount REAL, paid_amount REAL, balance REAL, payment_status TEXT, due_date TEXT)');
$pdo->exec('CREATE TABLE payments (id INTEGER, billing_id INTEGER, amount REAL, payment_date TEXT, payment_method TEXT, reference_number TEXT, voided_at TEXT)');
$pdo->exec('INSERT INTO appointments VALUES (1,7),(2,7),(3,7)');
$pdo->exec("INSERT INTO billing VALUES (10,1,7,5000,200,4800,'Partial','2099-01-01'),(11,2,7,7000,7000,0,'Paid',NULL),(12,1,7,300,300,0,'Cancelled',NULL),(13,1,8,900,900,0,'Paid',NULL)");
$pdo->exec("INSERT INTO payments VALUES (1,10,200,'2026-10-07','Cash','ABC',NULL),(2,10,4800,'2026-10-07','Cash',NULL,'2026-10-07 19:00:00'),(3,11,7000,'2026-10-07','Cash',NULL,NULL),(4,13,900,'2026-10-07','Cash',NULL,NULL)");
$check = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$details = getAppointmentPaymentDetails($pdo, 1);
$check($details['total'] === 5000.0 && $details['paid'] === 200.0 && $details['balance'] === 4800.0, 'Voided bills must not contribute to the summary');
$check($details['status'] === 'Partial', 'Partial status');
$check(count($details['invoices']) === 2 && count($details['payments']) === 2, 'Only this appointment and patient invoices/payments appear');
$check(!empty($details['payments'][0]['voided_at']), 'Voided payment remains visible in history');
$check(getAppointmentPaymentDetails($pdo, 3)['status'] === 'Not billed', 'Unbilled appointment is not shown as unpaid');
$pdo->exec("UPDATE billing SET payment_status='Cancelled' WHERE id=10");
$check(getAppointmentPaymentDetails($pdo, 1)['status'] === 'Voided', 'Cancelled invoices show voided status');
try { getAppointmentPaymentDetails($pdo, 999); throw new RuntimeException('Missing appointment accepted'); }
catch (InvalidArgumentException $e) {}
echo "PASS: appointment payment isolation, partial balance, voided history/invoices, unbilled and missing appointments.\n";
