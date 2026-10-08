<?php
function getAppointmentPaymentDetails(PDO $pdo, int $appointmentId): array
{
    $stmt = $pdo->prepare('SELECT patient_id FROM appointments WHERE id=?');
    $stmt->execute([$appointmentId]);
    $patientId = $stmt->fetchColumn();
    if ($patientId === false) throw new InvalidArgumentException('Appointment not found.');
    $stmt = $pdo->prepare('SELECT id, total_amount, paid_amount, balance, payment_status, due_date FROM billing WHERE appointment_id=? AND patient_id=? ORDER BY id');
    $stmt->execute([$appointmentId, $patientId]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT pay.id, pay.billing_id, pay.amount, pay.payment_date, pay.payment_method, pay.reference_number, pay.voided_at
        FROM payments pay JOIN billing b ON b.id=pay.billing_id
        WHERE b.appointment_id=? AND b.patient_id=? ORDER BY pay.payment_date DESC, pay.id DESC');
    $stmt->execute([$appointmentId, $patientId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $paid = $balance = 0.0;
    $active = 0;
    foreach ($invoices as $invoice) {
        if (in_array(strtolower($invoice['payment_status']), ['cancelled', 'voided'], true)) continue;
        ++$active;
        $total += (float)$invoice['total_amount'];
        $paid += (float)$invoice['paid_amount'];
        $balance += (float)$invoice['balance'];
    }
    $status = !$invoices ? 'Not billed' : (!$active ? 'Voided' : ($balance <= 0 && $total > 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid')));
    return ['success'=>true, 'invoices'=>$invoices, 'payments'=>$payments,
        'total'=>$total, 'paid'=>$paid, 'balance'=>$balance, 'status'=>$status];
}
