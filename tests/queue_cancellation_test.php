<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/queue_cancellation_helper.php';

// Temporary copies isolate every fixture and update from clinic records.
foreach (['queue', 'appointments', 'patients', 'users', 'billing', 'notifications', 'treatment_plans'] as $table) {
    $pdo->exec('CREATE TEMPORARY TABLE qc_test_' . $table . ' LIKE ' . $table);
    $pdo->exec('ALTER TABLE qc_test_' . $table . ' RENAME TO ' . $table);
}
function checkCancellation(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function checkPaymentDisplay(PDO $pdo, int $billingId, string $expected, bool $hasOlderPaidBill = false): void {
    foreach (['staff/staff_payment.php', 'admin/admin_payment.php'] as $page) {
        // Exercise each page's actual CASE expression using isolated account totals.
        // Avoid reopening the same MySQL temporary table in aggregate subqueries.
        $source = file_get_contents(__DIR__ . '/../master/' . $page);
        preg_match('/\$overallStatusExpr = "(.*?)";/s', $source, $matches);
        checkCancellation(isset($matches[1]), 'Payment status expression must exist');
        $expression = strtr($matches[1], [
            '$overallUnpaidExists'=>'0', '$overallPartialExists'=>'0',
            '$overallPaidExists'=>$hasOlderPaidBill ? '1' : '0'
        ]);
        $stmt = $pdo->prepare('SELECT (' . $expression . ') FROM billing b WHERE b.id = ?');
        $stmt->execute([$billingId]);
        checkCancellation($stmt->fetchColumn() === $expected, $page . ' must display ' . $expected);
    }
}
function cancellationFixture(PDO $pdo, string $table, array $values): void {
    // Supply required legacy fields whose names differ between installations.
    foreach ($pdo->query('DESCRIBE ' . $table)->fetchAll() as $column) {
        $field = $column['Field'];
        if (array_key_exists($field, $values) || $column['Null'] === 'YES'
            || $column['Default'] !== null || strpos($column['Extra'], 'auto_increment') !== false) continue;
        $type = $column['Type'];
        if (strpos($type, 'enum(') === 0) {
            preg_match("/enum\('([^']+)'/", $type, $matches);
            $values[$field] = $matches[1];
        } elseif (preg_match('/int|decimal|float|double/', $type)) $values[$field] = 0;
        elseif (strpos($type, 'datetime') === 0 || strpos($type, 'timestamp') === 0) $values[$field] = '2030-01-07 09:00:00';
        elseif (strpos($type, 'date') === 0) $values[$field] = '2030-01-07';
        elseif (strpos($type, 'time') === 0) $values[$field] = '09:00:00';
        else $values[$field] = 'test-' . $field;
    }
    $pdo->prepare('INSERT INTO ' . $table . ' (`' . implode('`, `', array_keys($values)) . '`)
        VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
cancellationFixture($pdo, 'patients', ['id'=>1, 'first_name'=>'Queue', 'last_name'=>'Patient', 'email'=>'queue-test@example.test']);
cancellationFixture($pdo, 'users', ['id'=>1, 'email'=>'queue-test@example.test', 'is_archived'=>0]);
foreach ([1, 2, 3, 4] as $id) {
    cancellationFixture($pdo, 'appointments', ['id'=>$id, 'patient_id'=>1, 'appointment_date'=>'2030-01-07', 'status'=>'In Queue']);
    cancellationFixture($pdo, 'queue', ['id'=>$id, 'patient_id'=>1, 'appointment_id'=>$id, 'queue_number'=>$id, 'queue_date'=>'2030-01-07', 'created_at'=>'2030-01-07 09:00:00', 'status'=>'waiting']);
}
cancellationFixture($pdo, 'billing', ['id'=>1, 'patient_id'=>1, 'appointment_id'=>2, 'billing_date'=>'2030-01-07', 'total_amount'=>900, 'paid_amount'=>0, 'balance'=>900, 'payment_status'=>'Unpaid']);
cancellationFixture($pdo, 'billing', ['id'=>2, 'patient_id'=>1, 'appointment_id'=>3, 'billing_date'=>'2030-01-07', 'total_amount'=>500, 'paid_amount'=>500, 'balance'=>0, 'payment_status'=>'Paid']);
cancelQueueVisit($pdo, 1);
checkCancellation($pdo->query('SELECT status FROM queue WHERE id=1')->fetchColumn() === 'cancelled', 'Queue must be cancelled');
checkCancellation($pdo->query('SELECT status FROM appointments WHERE id=1')->fetchColumn() === 'Cancelled', 'Ordinary appointment must be cancelled');
checkCancellation($pdo->query('SELECT status FROM appointments WHERE id=2')->fetchColumn() === 'In Queue', 'Other appointments must stay unchanged');
$voided = $pdo->query('SELECT * FROM billing WHERE appointment_id=1')->fetch();
checkCancellation($voided && $voided['payment_status'] === 'Cancelled' && (float)$voided['balance'] === 0.0, 'Cancellation before billing must create a voided payment entry');
checkCancellation($pdo->query('SELECT payment_status FROM billing WHERE id=1')->fetchColumn() === 'Unpaid', 'Another visit on the same date must not be voided');
$notice = $pdo->query('SELECT * FROM notifications')->fetch();
checkCancellation($notice && (int)$notice['user_id'] === 1 && strpos($notice['message'], 'cancelled') !== false, 'Patient must receive a queue cancellation notice');
cancelQueueVisit($pdo, 1);
checkCancellation((int)$pdo->query('SELECT COUNT(*) FROM billing WHERE appointment_id=1')->fetchColumn() === 1, 'Retry must not duplicate billing');
checkCancellation((int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 1, 'Retry must not duplicate notifications');
checkPaymentDisplay($pdo, (int)$voided['id'], 'voided');
$queueItem = $pdo->query('SELECT * FROM queue WHERE id=1')->fetch();
checkCancellation(restoreVoidedBillingForQueue($pdo, $queueItem), 'Requeue must restore the voided visit bill');
$restored = $pdo->query('SELECT * FROM billing WHERE appointment_id=1')->fetch();
checkCancellation($restored['payment_status'] === 'Unpaid' && (float)$restored['total_amount'] === 0.0
    && (float)$restored['balance'] === 0.0, 'Requeue before treatment must restore Unpaid without adding a charge');
checkPaymentDisplay($pdo, (int)$restored['id'], 'unpaid');
checkPaymentDisplay($pdo, (int)$restored['id'], 'unpaid', true);
cancelQueueVisit($pdo, 2);
checkCancellation($pdo->query('SELECT payment_status FROM billing WHERE id=1')->fetchColumn() === 'Cancelled', 'Existing unpaid visit bill must be voided');
checkCancellation((float)$pdo->query('SELECT balance FROM billing WHERE id=1')->fetchColumn() === 0.0, 'Voided bill must have no balance');
checkCancellation(restoreVoidedBillingForQueue($pdo, $pdo->query('SELECT * FROM queue WHERE id=2')->fetch()), 'Requeue must restore an existing unpaid bill');
checkCancellation((float)$pdo->query('SELECT balance FROM billing WHERE id=1')->fetchColumn() === 900.0, 'Requeue must preserve an existing charge');
cancelQueueVisit($pdo, 3);
checkCancellation($pdo->query('SELECT payment_status FROM billing WHERE id=2')->fetchColumn() === 'Paid', 'Recorded payments must be preserved');
// A failed portal notice must not leave a successful-looking cancellation.
$pdo->exec('DROP TEMPORARY TABLE notifications');
$pdo->exec('CREATE TEMPORARY TABLE notifications (id INT)');
try { cancelQueueVisit($pdo, 4); throw new LogicException('Expected notification failure'); }
catch (RuntimeException $e) {
    checkCancellation(strpos($e->getMessage(), 'notification') !== false, 'Notification failure must be reported');
}
checkCancellation($pdo->query('SELECT status FROM queue WHERE id=4')->fetchColumn() === 'waiting', 'Notification failure must roll back queue cancellation');
checkCancellation((int)$pdo->query('SELECT COUNT(*) FROM billing WHERE appointment_id=4')->fetchColumn() === 0, 'Notification failure must roll back the voided bill');
// Force a billing failure to verify that appointment and queue updates roll back together.
$pdo->exec('DROP TEMPORARY TABLE billing');
$pdo->exec('CREATE TEMPORARY TABLE billing (id INT)');
try { cancelQueueVisit($pdo, 4); throw new RuntimeException('Expected billing failure'); }
catch (PDOException $e) {}
checkCancellation($pdo->query('SELECT status FROM queue WHERE id=4')->fetchColumn() === 'waiting', 'Billing failure must roll back the queue');
checkCancellation($pdo->query('SELECT status FROM appointments WHERE id=4')->fetchColumn() === 'In Queue', 'Billing failure must roll back the appointment');
echo "PASS: appointment cancellation, patient notification, voided payment retention, retry safety, visit isolation, paid history and rollback.\n";
