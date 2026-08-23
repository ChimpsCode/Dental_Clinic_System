<?php
/**
 * Billing Helper - Shared find-or-create billing logic for completed visits
 */

require_once __DIR__ . '/discount_helper.php';

function findOrCreateBillingForVisit(PDO $pdo, int $patientId, string $visitDate, float $estimatedAmount, string $treatmentType = '', $appointmentId = null): array
{
    // Look for an existing billing record for this patient on the visit date
    $stmt = $pdo->prepare("SELECT * FROM billing WHERE patient_id = ? AND DATE(billing_date) = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patientId, $visitDate]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($billing) {
        $billingId = (int)$billing['id'];
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
