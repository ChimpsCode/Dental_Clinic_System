<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/billing_helper.php';

// Temporary tables keep these regression checks separate from clinic records.
foreach (['billing', 'patients'] as $table) {
    $pdo->exec('CREATE TEMPORARY TABLE vb_test_' . $table . ' LIKE ' . $table);
    $pdo->exec('ALTER TABLE vb_test_' . $table . ' RENAME TO ' . $table);
}
ensureBillingCancelledStatus($pdo);
ensureDiscountSchema($pdo);
function checkVisitBilling(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$first = findOrCreateBillingForVisit($pdo, 7, '2026-10-08', 800, 'Cleaning', 201);
$pdo->prepare("UPDATE billing SET paid_amount=800, balance=0, payment_status='Paid' WHERE id=?")
    ->execute([$first['billing_id']]);
$second = findOrCreateBillingForVisit($pdo, 7, '2026-10-08', 5000, 'Bridge', 202);
checkVisitBilling($first['billing_id'] !== $second['billing_id'], 'Same-day appointments need separate invoices');
checkVisitBilling($second['payment_status'] === 'Unpaid' && $second['balance'] === 5000.0, 'New session must retain its unpaid charges');
$repeat = findOrCreateBillingForVisit($pdo, 7, '2026-10-08', 5000, 'Bridge', 202);
checkVisitBilling($repeat['billing_id'] === $second['billing_id'], 'Repeating completion must not duplicate the invoice');
$legacy = findOrCreateBillingForVisit($pdo, 8, '2026-10-08', 500, 'Consultation');
$linked = findOrCreateBillingForVisit($pdo, 8, '2026-10-08', 500, 'Consultation', 203);
checkVisitBilling($legacy['billing_id'] === $linked['billing_id'], 'Unambiguous legacy invoice should be retained');
$next = findOrCreateBillingForVisit($pdo, 8, '2026-10-08', 800, 'Cleaning', 204);
checkVisitBilling($next['billing_id'] !== $linked['billing_id'], 'Claimed legacy bill must not be reused for a new appointment');
$walkIn = findOrCreateBillingForVisit($pdo, 7, '2026-10-08', 300, 'Walk-in');
checkVisitBilling($walkIn['billing_id'] !== $second['billing_id'], 'Walk-in must not reuse an appointment invoice');
$pdo->beginTransaction();
findOrCreateBillingForVisit($pdo, 7, '2026-10-08', 900, 'Rollback', 205);
$pdo->rollBack();
checkVisitBilling((int)$pdo->query('SELECT COUNT(*) FROM billing WHERE appointment_id=205')->fetchColumn() === 0, 'Billing insert must support completion rollback');
echo "Visit billing regression checks passed\n";

$pdo->exec('CREATE TEMPORARY TABLE queue (id INT PRIMARY KEY, patient_id INT, appointment_id INT, created_at DATETIME, treatment_type VARCHAR(100))');
$pdo->exec("INSERT INTO queue VALUES (1,7,201,'2026-10-08 10:00:00','Cleaning'),(2,7,202,'2026-10-08 11:00:00','Bridge')");
foreach (['admin/admin_payment.php', 'staff/staff_payment.php'] as $page) {
    $source = file_get_contents(__DIR__ . '/../master/' . $page);
    preg_match('/\$queueJoin = "(.*?)";/s', $source, $match);
    $stmt = $pdo->prepare('SELECT q.treatment_type FROM billing b ' . $match[1] . ' WHERE b.id = ?');
    $stmt->execute([$second['billing_id']]);
    checkVisitBilling($stmt->fetchColumn() === 'Bridge', $page . ' must show the billed appointment service');
}
$source = file_get_contents(__DIR__ . '/../master/user-patient/billing.php');
preg_match('/FROM billing b\s+(LEFT JOIN queue q.*?\))\s+WHERE b.patient_id/s', $source, $match);
$stmt = $pdo->prepare('SELECT q.treatment_type FROM billing b ' . $match[1] . ' WHERE b.id = ?');
$stmt->execute([$second['billing_id']]);
checkVisitBilling($stmt->fetchColumn() === 'Bridge', 'Patient billing must show the billed appointment service');
echo "Payment listing appointment checks passed\n";
