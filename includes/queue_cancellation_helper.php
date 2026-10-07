<?php
require_once __DIR__ . '/billing_helper.php';
require_once __DIR__ . '/treatment_plan_workflow.php';
require_once __DIR__ . '/notification_functions.php';

/** Cancel one queue visit and retain its appointment and payment history. */
function cancelQueueVisit(PDO $pdo, int $queueId): array
{
    // Schema changes must happen before the transaction (MySQL DDL commits).
    ensureBillingCancelledStatus($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name
            FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED)
            WHERE q.id = ? FOR UPDATE");
        $stmt->execute([$queueId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$queueItem) {
            throw new RuntimeException('Queue item not found');
        }

        $alreadyCancelled = strtolower((string)$queueItem['status']) === 'cancelled';
        $pdo->prepare("UPDATE queue SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
            ->execute([$queueId]);
        if (!empty($queueItem['appointment_id'])) {
            $pdo->prepare("UPDATE appointments SET status = 'Cancelled'
                WHERE id = ? AND patient_id = ? AND LOWER(COALESCE(status, '')) <> 'completed'")
                ->execute([(int)$queueItem['appointment_id'], (int)$queueItem['patient_id']]);
            syncTreatmentPlanForAppointment($pdo, (int)$queueItem['appointment_id']);
        }
        voidUnpaidBillingForQueue($pdo, $queueItem);
        if (!$alreadyCancelled && !empty($queueItem['patient_id'])) {
            if (!notifyPatientQueueUpdate($pdo, (int)$queueItem['patient_id'],
                (string)(($queueItem['queue_number'] ?? 0) ?: $queueId), 'cancelled')) {
                throw new RuntimeException('Unable to save the patient cancellation notification');
            }
        }
        $pdo->commit();
        return $queueItem;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
