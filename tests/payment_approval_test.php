<?php
ob_start();
session_start(['use_cookies'=>false, 'cache_limiter'=>'', 'save_path'=>__DIR__ . '/../cache']);
register_shutdown_function(function () { if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/payment_approval_helper.php';
// Shadow clinic tables for this connection; no user or financial records are changed.
foreach (['billing','payments','queue','audit_logs','notifications'] as $table) {
    $pdo->exec('CREATE TEMPORARY TABLE pa_test_' . $table . ' LIKE ' . $table);
    $pdo->exec('ALTER TABLE pa_test_' . $table . ' RENAME TO ' . $table);
}
$pdo->exec("CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, username VARCHAR(80), first_name VARCHAR(80), last_name VARCHAR(80), password VARCHAR(255), role VARCHAR(20), is_archived INT DEFAULT 0)");
$pdo->exec("CREATE TEMPORARY TABLE admin_payment_pins (user_id INT PRIMARY KEY, pin_hash VARCHAR(255), failed_attempts INT DEFAULT 0, locked_until DATETIME NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
ensurePaymentApprovalSchema($pdo);
function paymentCheck(bool $condition, string $message): void {
    if (!$condition) throw new LogicException($message);
}
function paymentReject(callable $action, string $message): void {
    try { $action(); } catch (RuntimeException $e) { return; }
    throw new LogicException($message);
}
function paymentFixture(PDO $pdo, string $table, array $values): void {
    foreach ($pdo->query('DESCRIBE ' . $table)->fetchAll() as $column) {
        $field = $column['Field'];
        if (array_key_exists($field, $values) || $column['Null'] === 'YES' || $column['Default'] !== null || strpos($column['Extra'], 'auto_increment') !== false) continue;
        $type = $column['Type'];
        if (strpos($type, 'enum(') === 0) { preg_match("/enum\('([^']+)'/", $type, $matches); $values[$field] = $matches[1]; }
        elseif (preg_match('/int|decimal|float|double/', $type)) $values[$field] = 0;
        elseif (strpos($type, 'datetime') === 0 || strpos($type, 'timestamp') === 0) $values[$field] = '2030-01-07 09:00:00';
        elseif (strpos($type, 'date') === 0) $values[$field] = '2030-01-07';
        elseif (strpos($type, 'time') === 0) $values[$field] = '09:00:00';
        else $values[$field] = 'test-' . $field;
    }
    $pdo->prepare('INSERT INTO ' . $table . ' (`' . implode('`, `', array_keys($values)) . '`) VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
foreach ([1=>'admin',2=>'staff',3=>'dentist',4=>'admin'] as $id=>$role) {
    paymentFixture($pdo,'users',['id'=>$id,'username'=>'test-'.$role.$id,'role'=>$role,'password'=>password_hash('test-password', PASSWORD_DEFAULT)]);
}
paymentFixture($pdo,'users',['id'=>5,'username'=>'archived-admin','role'=>'admin','is_archived'=>1,'password'=>password_hash('test-password',PASSWORD_DEFAULT)]);
paymentReject(fn()=>setPaymentApprovalPin($pdo,2,'test-password','246810','246810'),'Staff cannot configure an admin PIN');
paymentReject(fn()=>setPaymentApprovalPin($pdo,1,'wrong','246810','246810'),'Setting PIN must require the account password');
paymentReject(fn()=>setPaymentApprovalPin($pdo,1,'test-password','246810','123456'),'PIN confirmation must match');
setPaymentApprovalPin($pdo,1,'test-password','246810','246810');
$hash = $pdo->query('SELECT pin_hash FROM admin_payment_pins WHERE user_id=1')->fetchColumn();
paymentCheck($hash !== '246810' && password_verify('246810',$hash),'PIN must be stored as a hash');
paymentFixture($pdo,'billing',['id'=>1,'patient_id'=>1,'appointment_id'=>1,'total_amount'=>5000,'paid_amount'=>5000,'balance'=>0,'payment_status'=>'Paid']);
paymentFixture($pdo,'payments',['id'=>1,'patient_id'=>1,'billing_id'=>1,'amount'=>2000,'payment_method'=>'Cash','created_by'=>2]);
paymentFixture($pdo,'payments',['id'=>2,'patient_id'=>1,'billing_id'=>1,'amount'=>3000,'payment_method'=>'Cash','created_by'=>2]);
paymentFixture($pdo,'queue',['id'=>1,'patient_id'=>1,'appointment_id'=>1,'status'=>'completed']);
paymentReject(fn()=>voidApprovedPayment($pdo,3,1,'246810',1,'Mistake'),'Dentist cannot initiate a void');
paymentReject(fn()=>voidApprovedPayment($pdo,2,2,'246810',1,'Mistake'),'Staff cannot approve a void');
paymentReject(fn()=>voidApprovedPayment($pdo,2,4,'246810',1,'Mistake'),'An unconfigured PIN cannot approve a void');
paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'246810',1,''),'Reason is required');
for ($i=0;$i<5;$i++) paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'000000',1,'Mistake'),'Wrong PIN must be rejected');
paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'246810',1,'Mistake'),'Correct PIN cannot bypass temporary lockout');
paymentCheck($pdo->query('SELECT voided_at FROM payments WHERE id=1')->fetchColumn() === null,'Rejected approvals must not modify payment');
$pdo->exec('UPDATE admin_payment_pins SET locked_until=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id=1');
$result = voidApprovedPayment($pdo,2,1,'246810',1,'Payment recorded by mistake');
paymentCheck($result['paid_amount'] === 3000.0 && $result['balance'] === 2000.0 && $result['payment_status'] === 'Partial','Void must restore partial balance');
$payment = $pdo->query('SELECT * FROM payments WHERE id=1')->fetch();
paymentCheck((float)$payment['amount'] === 2000.0 && $payment['voided_at'] && (int)$payment['voided_by'] === 1 && (int)$payment['void_requested_by'] === 2 && (int)$payment['created_by'] === 2,'Retain original payment and both actors');
paymentCheck($pdo->query('SELECT status FROM queue WHERE id=1')->fetchColumn() === 'pending_payment','Reopen payment when money is still owed');
paymentCheck((float)$pdo->query('SELECT SUM(amount) FROM payments WHERE voided_at IS NULL')->fetchColumn() === 3000.0,'Exclude voided payments from money collected');
$audit = $pdo->query("SELECT * FROM audit_logs WHERE module='payments' ORDER BY id DESC LIMIT 1")->fetch();
paymentCheck((int)$audit['user_id']===2 && strpos($audit['description'],'Approved by test-admin1')!==false && strpos($audit['description'],'Payment recorded by mistake')!==false,'Audit must identify requester, approver and reason');
paymentCheck(strpos(json_encode($audit),'246810')===false && strpos(json_encode($audit),'test-password')===false,'Never audit credentials');
$notices = $pdo->query("SELECT * FROM notifications WHERE title='Payment Voided' ORDER BY user_id")->fetchAll();
paymentCheck(count($notices)===2 && (int)$notices[0]['user_id']===1 && (int)$notices[1]['user_id']===4,'Notify every active admin, including admins without a PIN, and exclude staff/archived accounts');
paymentCheck(strpos($notices[0]['message'],'PAY-00001')!==false && strpos($notices[0]['message'],'2,000.00')!==false
    && strpos($notices[0]['message'],'test-staff2')!==false && strpos($notices[0]['message'],'test-admin1')!==false
    && strpos($notices[0]['message'],'Payment recorded by mistake')!==false,'Notification must include payment, amount, actors and reason');
paymentCheck($notices[0]['action_url']==='admin_payment_log.php?search=1','Notification must link to the payment ledger');
paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'246810',1,'Again'),'Cannot void twice');
paymentCheck((int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===2,'Repeated voids must not generate duplicate notifications');
voidApprovedPayment($pdo,1,1,'246810',2,'Second mistaken payment');
paymentCheck($pdo->query('SELECT payment_status FROM billing WHERE id=1')->fetchColumn()==='Unpaid','Voiding final payment makes invoice unpaid');
paymentCheck((float)$pdo->query('SELECT balance FROM billing WHERE id=1')->fetchColumn()===5000.0,'Restore full balance');
setPaymentApprovalPin($pdo,1,'test-password','135790','135790');
paymentReject(fn()=>verifyPaymentApprovalCredential($pdo,1,'246810'),'Old PIN must stop working after reset');
paymentFixture($pdo,'payments',['id'=>3,'patient_id'=>1,'billing_id'=>1,'amount'=>500,'payment_method'=>'Cash','created_by'=>2]);
$pdo->exec("UPDATE billing SET paid_amount=500, balance=4500, payment_status='Partial' WHERE id=1");
// Exercise the actual endpoint with a staff session, not only its helper functions.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['user_id'=>2,'role'=>'staff','payment_approval_csrf'=>'server-token'];
$_POST = ['action'=>'void_payment','payment_id'=>3,'admin_id'=>1,'pin'=>'135790','reason'=>'Mistake','csrf'=>'wrong-token'];
ob_start(); require __DIR__ . '/../master/shared/payment_approval.php'; $response = json_decode(ob_get_clean(),true);
paymentCheck(!$response['success'] && http_response_code()===403,'Endpoint must reject an invalid CSRF token');
paymentCheck($pdo->query('SELECT voided_at FROM payments WHERE id=3')->fetchColumn()===null,'CSRF rejection must not modify payments');
http_response_code(200);
$_POST = ['action'=>'set_pin','password'=>'test-password','pin'=>'975310','confirmation'=>'975310','csrf'=>'server-token'];
ob_start(); require __DIR__ . '/../master/shared/payment_approval.php'; $response = json_decode(ob_get_clean(),true);
paymentCheck(!$response['success'] && strpos($response['message'],'Only admins')!==false,'Endpoint must prevent staff configuring any approval PIN');
$_POST = ['action'=>'void_payment','payment_id'=>3,'admin_id'=>1,'pin'=>'135790','reason'=>'Mistake','csrf'=>'server-token'];
ob_start(); require __DIR__ . '/../master/shared/payment_approval.php'; $response = json_decode(ob_get_clean(),true);
paymentCheck($response['success'] && (float)$response['billing']['balance']===5000.0,'Valid supervisor approval must succeed through the endpoint');
paymentFixture($pdo,'payments',['id'=>4,'patient_id'=>1,'billing_id'=>1,'amount'=>500,'payment_method'=>'Cash','created_by'=>2]);
$pdo->exec("UPDATE billing SET paid_amount=500, balance=4500, payment_status='Partial' WHERE id=1");
$auditCount = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$pdo->exec('CREATE TEMPORARY TABLE saved_void_notifications LIKE notifications');
$pdo->exec('INSERT INTO saved_void_notifications SELECT * FROM notifications');
$pdo->exec('DROP TEMPORARY TABLE notifications');
$pdo->exec('CREATE TEMPORARY TABLE notifications (id INT)');
paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'135790',4,'Mistake'),'Notification failure must reject correction');
paymentCheck($pdo->query('SELECT voided_at FROM payments WHERE id=4')->fetchColumn()===null,'Notification failure rolls back payment');
paymentCheck((float)$pdo->query('SELECT paid_amount FROM billing WHERE id=1')->fetchColumn()===500.0,'Notification failure rolls back invoice amounts');
paymentCheck((int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn()===$auditCount,'Notification failure rolls back audit record');
$pdo->exec('DROP TEMPORARY TABLE notifications');
$pdo->exec('ALTER TABLE saved_void_notifications RENAME TO notifications');
$pdo->exec('DROP TEMPORARY TABLE audit_logs');
$pdo->exec('CREATE TEMPORARY TABLE audit_logs (id INT)');
paymentReject(fn()=>voidApprovedPayment($pdo,2,1,'135790',4,'Mistake'),'Audit failure must reject correction');
paymentCheck($pdo->query('SELECT voided_at FROM payments WHERE id=4')->fetchColumn()===null,'Audit failure rolls back payment metadata');
paymentCheck((float)$pdo->query('SELECT paid_amount FROM billing WHERE id=1')->fetchColumn()===500.0,'Audit failure rolls back invoice amounts');
paymentCheck((int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===6,'Failed void must not send a notification');
echo "PASS: private PIN setup/reset, roles, reason, lockout, supervisor attribution, balances, history, repeat protection, endpoint CSRF, admin notifications and notification/audit rollback.\n";
ob_end_flush();
