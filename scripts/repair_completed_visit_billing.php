<?php
// CLI-only recovery for visits whose same-day invoice was incorrectly reused.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/billing_helper.php';
$visitDate = $argv[1] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
    fwrite(STDERR, "Usage: php scripts/repair_completed_visit_billing.php YYYY-MM-DD [--apply]\n");
    exit(1);
}
ensureBillingCancelledStatus($pdo);
ensureDiscountSchema($pdo);
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT q.id, q.patient_id, q.appointment_id, q.treatment_type,
            DATE(q.created_at) AS visit_date
        FROM queue q JOIN appointments a ON a.id = q.appointment_id AND a.patient_id = q.patient_id
        WHERE DATE(q.created_at) = ? AND q.completed_at IS NOT NULL
          AND q.status IN ('pending_payment', 'completed') AND a.status = 'Completed'
          AND NOT EXISTS (SELECT 1 FROM billing b WHERE b.patient_id = q.patient_id AND b.appointment_id = q.appointment_id)
          AND NOT EXISTS (SELECT 1 FROM billing b WHERE b.patient_id = q.patient_id
              AND b.appointment_id IS NULL AND DATE(b.billing_date) = DATE(q.created_at))
        ORDER BY q.id FOR UPDATE");
    $stmt->execute([$visitDate]);
    $visits = $stmt->fetchAll();
    foreach ($visits as $visit) {
        $service = $pdo->prepare('SELECT price FROM services WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1');
        $service->execute([$visit['treatment_type']]);
        $amount = $service->fetchColumn();
        if ($amount === false) throw new RuntimeException('No exact service price for queue ' . $visit['id']);
        if (in_array('--apply', $argv, true)) {
            $bill = findOrCreateBillingForVisit($pdo, (int)$visit['patient_id'], $visit['visit_date'],
                (float)$amount, $visit['treatment_type'], (int)$visit['appointment_id']);
            echo 'Restored invoice ' . $bill['billing_id'] . ' for appointment ' . $visit['appointment_id']
                . ', balance ' . number_format($bill['balance'], 2, '.', '') . PHP_EOL;
        } else {
            echo 'Missing invoice for appointment ' . $visit['appointment_id'] . ', service price ' . $amount . PHP_EOL;
        }
    }
    $pdo->commit();
    echo count($visits) . " affected visits\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
