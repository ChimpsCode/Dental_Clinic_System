<?php
/**
 * Billing Helper - Shared find-or-create billing logic for completed visits
 */

require_once __DIR__ . '/discount_helper.php';

function ensureBillingCancelledStatus(PDO $pdo): void
{
    static $schemaReady = false;
    if ($schemaReady) {
        return;
    }
    $schemaReady = true;

    try {
        $enumStmt = $pdo->query("SHOW COLUMNS FROM billing LIKE 'payment_status'");
        $statusCol = $enumStmt ? $enumStmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$statusCol) {
            return;
        }
        $type = (string)($statusCol['Type'] ?? '');
        if (stripos($type, 'enum(') === false) {
            return;
        }
        if (stripos($type, 'Cancelled') === false) {
            $pdo->exec("ALTER TABLE billing MODIFY COLUMN payment_status ENUM('Paid', 'Partial', 'Unpaid', 'Overdue', 'Cancelled') DEFAULT 'Unpaid'");
        }
    } catch (Exception $e) {
        // non-fatal; continue without blocking
    }
}

function getQueueVisitDate(array $queueItem): string
{
    if (!empty($queueItem['queue_date'])) {
        return substr((string)$queueItem['queue_date'], 0, 10);
    }
    if (!empty($queueItem['created_at'])) {
        return substr((string)$queueItem['created_at'], 0, 10);
    }
    return date('Y-m-d');
}

function findBillingForQueue(PDO $pdo, array $queueItem): ?array
{
    $patientId = (int)($queueItem['patient_id'] ?? 0);
    if ($patientId <= 0) {
        return null;
    }

    $appointmentId = !empty($queueItem['appointment_id']) ? (int)$queueItem['appointment_id'] : 0;
    if ($appointmentId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND appointment_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patientId, $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    $visitDate = getQueueVisitDate($queueItem);
    $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND DATE(billing_date) = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patientId, $visitDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function isUnpaidVoidableBilling(?array $billing): bool
{
    if (!$billing) {
        return false;
    }
    $status = strtolower((string)($billing['payment_status'] ?? 'unpaid'));
    $paid = floatval($billing['paid_amount'] ?? 0);
    return $paid <= 0 && in_array($status, ['unpaid', 'pending', 'overdue'], true);
}

function restoreBillingFromCancelled(PDO $pdo, array $billing): array
{
    ensureBillingCancelledStatus($pdo);

    $billingId = (int)($billing['id'] ?? 0);
    $total = floatval($billing['total_amount'] ?? 0);
    $paid = floatval($billing['paid_amount'] ?? 0);
    $balance = max(0, $total - $paid);
    if ($paid <= 0) {
        $newStatus = 'Unpaid';
        $balance = $total;
    } elseif ($balance <= 0) {
        $newStatus = 'Paid';
        $balance = 0;
    } else {
        $newStatus = 'Partial';
    }

    $notes = str_replace(' [Voided: queue cancelled]', '', (string)($billing['notes'] ?? ''));
    $stmt = $pdo->prepare("
        UPDATE billing
        SET payment_status = ?,
            balance = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $balance, $notes, $billingId]);

    $billing['payment_status'] = $newStatus;
    $billing['balance'] = $balance;
    $billing['notes'] = $notes;
    return $billing;
}

function voidUnpaidBillingForQueue(PDO $pdo, array $queueItem): bool
{
    ensureBillingCancelledStatus($pdo);
    $billing = findBillingForQueue($pdo, $queueItem);
    if (!isUnpaidVoidableBilling($billing)) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE billing
        SET payment_status = 'Cancelled',
            balance = 0,
            notes = CONCAT(COALESCE(notes, ''), ' [Voided: queue cancelled]'),
            updated_at = NOW()
        WHERE id = ?
          AND COALESCE(paid_amount, 0) = 0
    ");
    $stmt->execute([(int)$billing['id']]);
    return $stmt->rowCount() > 0;
}

function restoreVoidedBillingForQueue(PDO $pdo, array $queueItem): bool
{
    ensureBillingCancelledStatus($pdo);
    $billing = findBillingForQueue($pdo, $queueItem);
    if (!$billing) {
        return false;
    }
    if (strtolower((string)($billing['payment_status'] ?? '')) !== 'cancelled') {
        return false;
    }

    restoreBillingFromCancelled($pdo, $billing);
    return true;
}

function findOrCreateBillingForVisit(PDO $pdo, int $patientId, string $visitDate, float $estimatedAmount, string $treatmentType = '', $appointmentId = null): array
{
    ensureBillingCancelledStatus($pdo);

    // Look for an existing billing record for this patient on the visit date
    if (!empty($appointmentId)) {
        $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND appointment_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patientId, (int)$appointmentId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing) {
            $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND DATE(billing_date) = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$patientId, $visitDate]);
            $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND DATE(billing_date) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patientId, $visitDate]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($billing) {
        $billingId = (int)$billing['id'];
        if (strtolower((string)($billing['payment_status'] ?? '')) === 'cancelled') {
            $billing = restoreBillingFromCancelled($pdo, $billing);
        }
        // Backfill total only if it was never set (never clobber a manually-set amount)
        if (floatval($billing['total_amount']) <= 0 && $estimatedAmount > 0) {
            $disc = getPatientDiscount($pdo, $patientId);
            $totals = computeDiscountedTotals($estimatedAmount, $disc);
            if ($disc['eligible']) {
                $stmt = $pdo->prepare("UPDATE billing SET total_amount = ?, subtotal_amount = ?, discount_type = ?, discount_rate = ?, discount_amount = ?, discount_id_no = ?, discount_holder_name = ?, balance = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$totals['total'], $totals['subtotal'], $disc['type'], $disc['rate'], $totals['discount_amount'], $disc['id_no'], $disc['holder_name'], $totals['total'], $billingId]);
                logDiscountAudit($pdo, 'discount', $billingId, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on backfilled bill');
            } else {
                $stmt = $pdo->prepare("UPDATE billing SET total_amount = ?, balance = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$estimatedAmount, $estimatedAmount, $billingId]);
            }
            $billing['total_amount'] = $totals['total'];
            $billing['balance'] = $totals['total'];
        }
        return [
            'billing_id' => $billingId,
            'total_amount' => floatval($billing['total_amount']),
            'paid_amount' => floatval($billing['paid_amount']),
            'balance' => floatval($billing['balance']),
            'payment_status' => $billing['payment_status'] ?: 'Unpaid',
        ];
    }

    // Create a new unpaid billing record for this visit
    $bCols = $pdo->query("DESCRIBE billing");
    $bAvailable = array_column($bCols->fetchAll(), 'Field');
    $bFieldMap = [
        'patient_id' => $patientId,
        'appointment_id' => $appointmentId,
        'total_amount' => $estimatedAmount,
        'paid_amount' => 0,
        'balance' => $estimatedAmount,
        'payment_status' => 'Unpaid',
        'billing_date' => $visitDate,
        'due_date' => $visitDate,
        'notes' => 'Billing from completed visit - ' . ($treatmentType ?: 'General Service'),
    ];

    // Auto-apply Senior/PWD discount when eligible
    $disc = getPatientDiscount($pdo, $patientId);
    $totals = computeDiscountedTotals($estimatedAmount, $disc);
    $bFieldMap['total_amount'] = $totals['total'];
    $bFieldMap['balance'] = $totals['total'];
    if ($disc['eligible']) {
        $bFieldMap['subtotal_amount'] = $totals['subtotal'];
        $bFieldMap['discount_type'] = $disc['type'];
        $bFieldMap['discount_rate'] = $disc['rate'];
        $bFieldMap['discount_amount'] = $totals['discount_amount'];
        $bFieldMap['discount_id_no'] = $disc['id_no'];
        $bFieldMap['discount_holder_name'] = $disc['holder_name'];
    }

    $bColList = [];
    $bValues = [];
    foreach ($bFieldMap as $col => $val) {
        if (in_array($col, $bAvailable)) {
            $bColList[] = $col;
            $bValues[] = $val;
        }
    }
    if (empty($bColList)) {
        throw new RuntimeException('Billing table has no usable columns');
    }

    $sql = "INSERT INTO billing (" . implode(', ', $bColList) . ") VALUES (" . implode(', ', array_fill(0, count($bValues), '?')) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bValues);
    $billingId = (int)$pdo->lastInsertId();

    if ($disc['eligible']) {
        logDiscountAudit($pdo, 'discount', $billingId, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on new bill');
    }

    return [
        'billing_id' => $billingId,
        'total_amount' => $totals['total'],
        'paid_amount' => 0,
        'balance' => $totals['total'],
        'payment_status' => 'Unpaid',
    ];
}
