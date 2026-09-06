<?php
/**
 * Patient dashboard queue / billing summary helpers.
 */

function getPatientQueueCardStatus(PDO $pdo, $patientId): array
{
    $queueNumberText = 'None';
    $queueStatusText = 'No active queue today';

    if (empty($patientId)) {
        return [
            'queue_number' => $queueNumberText,
            'queue_status' => $queueStatusText,
        ];
    }

    try {
        $hasQueue = (bool)$pdo->query("SHOW TABLES LIKE 'queue'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasQueue) {
            return [
                'queue_number' => $queueNumberText,
                'queue_status' => $queueStatusText,
            ];
        }

        $stmt = $pdo->prepare("
            SELECT q.*
            FROM queue q
            WHERE q.patient_id = ?
              AND DATE(q.created_at) = CURDATE()
              AND COALESCE(q.is_archived, 0) = 0
              AND LOWER(COALESCE(q.status, 'waiting')) IN ('waiting', 'in_procedure', 'on_hold')
            ORDER BY q.created_at DESC, q.id DESC
            LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($queueItem) {
            $queueNumber = $queueItem['queue_number'] ?? null;
            if (!$queueNumber) {
                $queueNumber = $queueItem['id'];
            }
            $queueNumberText = '#' . str_pad((string)$queueNumber, 2, '0', STR_PAD_LEFT);
            $status = strtolower((string)($queueItem['status'] ?? 'waiting'));
            if ($status === 'in_procedure') {
                $queueStatusText = !empty($queueItem['treatment_type'])
                    ? 'In procedure for ' . $queueItem['treatment_type']
                    : 'Your procedure has started';
            } elseif ($status === 'on_hold') {
                $queueStatusText = 'Your queue position is on hold';
            } else {
                $queueStatusText = !empty($queueItem['treatment_type'])
                    ? 'In queue for ' . $queueItem['treatment_type']
                    : 'Checked in today';
            }
        }
    } catch (Exception $e) {
        // keep defaults
    }

    return [
        'queue_number' => $queueNumberText,
        'queue_status' => $queueStatusText,
    ];
}

function getPatientOutstandingBalance(PDO $pdo, $patientId): float
{
    if (empty($patientId)) {
        return 0.0;
    }

    try {
        $hasBilling = (bool)$pdo->query("SHOW TABLES LIKE 'billing'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasBilling) {
            return 0.0;
        }

        $stmt = $pdo->prepare("
            SELECT SUM(GREATEST(COALESCE(balance, 0), COALESCE(total_amount, 0) - COALESCE(paid_amount, 0))) AS outstanding
            FROM billing
            WHERE patient_id = ?
        ");
        $stmt->execute([$patientId]);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        return 0.0;
    }
}

function getPatientNextDueDateLabel(PDO $pdo, $patientId): string
{
    if (empty($patientId)) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT MIN(due_date) AS next_due
            FROM billing
            WHERE patient_id = ?
              AND due_date IS NOT NULL
              AND due_date != ''
              AND COALESCE(balance, 0) > 0
              AND LOWER(COALESCE(payment_status, 'unpaid')) != 'paid'
        ");
        $stmt->execute([$patientId]);
        $nextDue = $stmt->fetchColumn();
        if ($nextDue) {
            return 'due ' . date('M j', strtotime($nextDue));
        }
    } catch (Exception $e) {
        return '';
    }
    return '';
}

function getPatientBillingSummaryText(PDO $pdo, $patientId): string
{
    $outstanding = getPatientOutstandingBalance($pdo, $patientId);
    if ($outstanding > 0) {
        return 'PHP ' . number_format($outstanding, 2);
    }
    return 'No balance';
}

function getPatientDashboardStatusPayload(PDO $pdo, $patientId): array
{
    $queue = getPatientQueueCardStatus($pdo, $patientId);
    return [
        'queue_number' => $queue['queue_number'],
        'queue_status' => $queue['queue_status'],
        'billing_summary' => getPatientBillingSummaryText($pdo, $patientId),
        'billing_due' => getPatientNextDueDateLabel($pdo, $patientId),
    ];
}
