<?php
require_once __DIR__ . '/audit_helper.php';
require_once __DIR__ . '/notification_functions.php';

function ensurePaymentApprovalSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_payment_pins (
        user_id INT NOT NULL PRIMARY KEY, pin_hash VARCHAR(255) NULL,
        failed_attempts INT NOT NULL DEFAULT 0, locked_until DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $columns = array_column($pdo->query('SHOW COLUMNS FROM payments')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    foreach (['voided_at'=>'DATETIME NULL', 'voided_by'=>'INT NULL',
        'void_requested_by'=>'INT NULL', 'void_reason'=>'VARCHAR(500) NULL'] as $name=>$definition) {
        if (!in_array($name, $columns, true)) $pdo->exec("ALTER TABLE payments ADD COLUMN $name $definition");
    }
    $ready = true;
}

function paymentApprovalUsersSql(PDO $pdo): string
{
    $columns = array_column($pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $role = in_array('role', $columns, true) ? 'u.role' : '(SELECT role_name FROM roles WHERE id = u.role_id)';
    $active = in_array('is_archived', $columns, true) ? 'COALESCE(u.is_archived, 0) = 0' : '1=1';
    if (in_array('status', $columns, true)) $active .= " AND LOWER(COALESCE(u.status, 'active')) NOT IN ('inactive', 'disabled', 'suspended')";
    return "SELECT u.id, u.username, u.first_name, u.last_name, u.password, $role AS role FROM users u WHERE $active";
}

/** Credentials are checked under a database lock; failed attempts survive rejection. */
function verifyPaymentApprovalCredential(PDO $pdo, int $adminId, string $credential, bool $password = false): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(paymentApprovalUsersSql($pdo) . ' AND u.id = ? FOR UPDATE');
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin || strtolower((string)$admin['role']) !== 'admin') throw new RuntimeException('Select an active approving admin.');
        $pdo->prepare('INSERT IGNORE INTO admin_payment_pins (user_id) VALUES (?)')->execute([$adminId]);
        $stmt = $pdo->prepare('SELECT *, locked_until > NOW() AS locked FROM admin_payment_pins WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$adminId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings['locked']) throw new RuntimeException('Approval is temporarily locked. Try again in 15 minutes.');
        $hash = $password ? (string)$admin['password'] : (string)($settings['pin_hash'] ?? '');
        if (!password_verify($credential, $hash)) {
            $attempts = empty($settings['locked_until']) ? (int)$settings['failed_attempts'] + 1 : 1;
            $pdo->prepare('UPDATE admin_payment_pins SET failed_attempts = ?, locked_until = '
                . ($attempts >= 5 ? 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)' : 'NULL') . ' WHERE user_id = ?')
                ->execute([$attempts, $adminId]);
            $pdo->commit();
            throw new RuntimeException($attempts >= 5 ? 'Too many failed attempts. Try again in 15 minutes.'
                : ($password ? 'Current account password is incorrect.' : 'Admin approval PIN is incorrect or has not been configured.'));
        }
        $pdo->prepare('UPDATE admin_payment_pins SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?')->execute([$adminId]);
        $pdo->commit();
        unset($admin['password']);
        return $admin;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function setPaymentApprovalPin(PDO $pdo, int $adminId, string $password, string $pin, string $confirmation): void
{
    if (!preg_match('/^\d{6}$/D', $pin) || !hash_equals($pin, $confirmation)) {
        throw new RuntimeException('Enter and confirm the same six-digit PIN.');
    }
    $admin = verifyPaymentApprovalCredential($pdo, $adminId, $password, true);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE admin_payment_pins SET pin_hash = ?, failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE user_id = ?')
            ->execute([password_hash($pin, PASSWORD_DEFAULT), $adminId]);
        if (!logAudit($pdo, $adminId, $admin['username'], 'admin', 'update', 'users',
            'Configured Payment Approval PIN', $adminId, 'users', null, 'PIN configured')) throw new RuntimeException('Unable to save the audit record.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function voidApprovedPayment(PDO $pdo, int $actorId, int $adminId, string $pin, int $paymentId, string $reason): array
{
    $reason = trim($reason);
    if ($paymentId <= 0 || $reason === '' || strlen($reason) > 500) throw new RuntimeException('Select a payment and provide a reason of up to 500 characters.');
    $actorStmt = $pdo->prepare(paymentApprovalUsersSql($pdo) . ' AND u.id = ?');
    $actorStmt->execute([$actorId]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$actor || !in_array(strtolower((string)$actor['role']), ['staff', 'admin'], true)) throw new RuntimeException('You do not have permission to void payments.');
    ensureNotificationSchema($pdo);
    $admin = verifyPaymentApprovalCredential($pdo, $adminId, $pin);
    $pdo->beginTransaction();
    try {
        // Lock the invoice before its payment to match existing payment writers.
        $stmt = $pdo->prepare('SELECT billing_id FROM payments WHERE id = ?');
        $stmt->execute([$paymentId]);
        $billingId = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT * FROM billing WHERE id = ? FOR UPDATE');
        $stmt->execute([$billingId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bill || !$payment || (int)$payment['billing_id'] !== $billingId) throw new RuntimeException('Payment or invoice was not found.');
        if (!empty($payment['voided_at'])) throw new RuntimeException('This payment has already been voided.');
        $pdo->prepare('UPDATE payments SET voided_at = NOW(), voided_by = ?, void_requested_by = ?, void_reason = ? WHERE id = ? AND voided_at IS NULL')
            ->execute([$adminId, $actorId, $reason, $paymentId]);
        $paid = max(0, round((float)$bill['paid_amount'] - (float)$payment['amount'], 2));
        $cancelled = strtolower((string)$bill['payment_status']) === 'cancelled';
        $balance = $cancelled ? 0 : max(0, round((float)$bill['total_amount'] - $paid, 2));
        $status = $cancelled ? 'Cancelled' : ($paid <= 0 ? 'Unpaid' : ($balance > 0 ? 'Partial' : 'Paid'));
        $pdo->prepare('UPDATE billing SET paid_amount = ?, balance = ?, payment_status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$paid, $balance, $status, $billingId]);
        if ($balance > 0 && !empty($bill['appointment_id'])) {
            $pdo->prepare("UPDATE queue SET status = 'pending_payment', updated_at = NOW()
                WHERE appointment_id = ? AND patient_id = ? AND status = 'completed'")
                ->execute([(int)$bill['appointment_id'], (int)$bill['patient_id']]);
        }
        $description = 'Voided Payment #PAY-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT)
            . ' | PHP ' . number_format((float)$payment['amount'], 2) . ' | ' . $payment['payment_method']
            . ' | Recorded by user #' . ($payment['created_by'] ?? 'unknown')
            . ' | Initiated by ' . $actor['username'] . ' (#' . $actorId . ')'
            . ' | Approved by ' . $admin['username'] . ' (#' . $adminId . ') | Reason: ' . $reason;
        if (!logAudit($pdo, $actorId, $actor['username'], strtolower($actor['role']), 'update', 'payments',
            $description, $paymentId, 'payments', 'Recorded', 'VOIDED')) throw new RuntimeException('Unable to save the audit record.');
        $message = 'Payment #PAY-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT)
            . ' (₱' . number_format((float)$payment['amount'], 2) . ', ' . $payment['payment_method'] . ') was voided.'
            . ' Initiated by: ' . $actor['username'] . '. Approved by: ' . $admin['username'] . '. Reason: ' . $reason;
        $recipients = $pdo->query('SELECT a.id FROM (' . paymentApprovalUsersSql($pdo) . ") a WHERE LOWER(a.role) = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipients as $recipientId) {
            if (createNotification($pdo, (int)$recipientId, 'payment', 'Payment Voided', $message,
                'admin_payment_log.php?search=' . $paymentId) === false) {
                throw new RuntimeException('Unable to save the admin payment void notification.');
            }
        }
        $pdo->commit();
        return ['billing_id'=>$billingId, 'paid_amount'=>$paid, 'balance'=>$balance, 'payment_status'=>$status];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
