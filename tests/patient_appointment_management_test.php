<?php
require_once __DIR__ . '/../includes/patient_appointment_management.php';
date_default_timezone_set('Asia/Manila');
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE appointments (id INTEGER PRIMARY KEY, patient_id INTEGER, status TEXT, appointment_date TEXT, appointment_time TEXT, service_id INTEGER, duration_minutes INTEGER, notes TEXT, approved_at TEXT, approved_by INTEGER, patient_edited_at TEXT, is_archived INTEGER DEFAULT 0, deleted_at TEXT, check_in_time TEXT, is_reminded INTEGER DEFAULT 0, cancel_reason TEXT, cancelled_by INTEGER, cancelled_at TEXT)");
$pdo->exec('CREATE TABLE services (id INTEGER PRIMARY KEY, name TEXT, duration_minutes INTEGER, is_active INTEGER)');
$pdo->exec('CREATE TABLE queue (appointment_id INTEGER, status TEXT)');
ensureCancellationRequestSchema($pdo);
$pdo->exec("INSERT INTO services VALUES (1, 'Examination', 30, 1), (2, 'Cleaning', 60, 1), (3, 'Inactive', 30, 0)");
$date = date('Y-m-d', strtotime('+30 days'));
$insert = $pdo->prepare("INSERT INTO appointments (id, patient_id, status, appointment_date, appointment_time, service_id, duration_minutes, notes) VALUES (?, 7, 'Pending Approval', ?, '10:00:00', 1, 30, '')");
for ($id = 1; $id <= 14; $id++) $insert->execute([$id, $date]);
// Use distinct dates to avoid the patient's same-day rule in the default fixtures.
$pdo->exec("UPDATE appointments SET appointment_date = '2099-01-01' WHERE id <> 1");
$check = function ($value, $label) { if (!$value) throw new RuntimeException('FAIL: ' . $label); };
$read = function ($id) use ($pdo) { $s=$pdo->prepare('SELECT * FROM appointments WHERE id=?'); $s->execute([$id]); return $s->fetch(); };
$available = fn($db, $input) => ['success'=>true, 'is_open'=>true, 'available_slots'=>['11:00']];
$input = ['action'=>'edit', 'appointment_id'=>1, 'appointment_date'=>$date, 'appointment_time'=>'11:00', 'service_id'=>2, 'notes'=>'Updated notes'];
$reject = function ($data, $message, $patient=7, $slots=null) use ($pdo, $check, $available) {
    try { managePatientAppointment($pdo, $patient, 99, $data, $slots ?? $available); throw new RuntimeException('Expected rejection: ' . $message); }
    catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), $message), 'Expected rejection: ' . $message . ' (' . $e->getMessage() . ')'); }
    $check(!$pdo->inTransaction(), 'Rejected changes release transaction');
};
$reject($input, 'not found', 8);
$reject(array_replace($input, ['appointment_date'=>'2026-02-30']), 'valid upcoming date');
$reject(array_replace($input, ['appointment_time'=>'25:99']), 'valid time slot');
$reject(array_replace($input, ['service_id'=>3]), 'available service');
$reject(array_replace($input, ['appointment_time'=>'10:00', 'service_id'=>1, 'notes'=>'']), 'No changes');
$reject($input, 'no longer available', 7, fn()=>['success'=>true,'is_open'=>false]);
$check(empty($read(1)['patient_edited_at']), 'Validation errors do not consume the one-time edit');
$result = managePatientAppointment($pdo, 7, 99, $input, $available);
$after = $read(1);
$check($result['success'] && $after['status'] === 'Pending Approval' && !empty($after['patient_edited_at']), 'First edit succeeds and remains pending approval');
$check($after['service_id'] == 2 && $after['duration_minutes'] == 60 && $after['notes'] === 'Updated notes', 'Edited service, duration and notes persist');
$reject(array_replace($input, ['notes'=>'Another edit']), 'one-time edit');
$check($read(1)['notes'] === 'Updated notes', 'Second edit does not change saved content');
managePatientAppointment($pdo, 7, 99, ['action'=>'cancel','appointment_id'=>1,'cancel_reason'=>'Cannot attend']);
$check($read(1)['status'] === 'Cancelled' && $read(1)['cancelled_by'] == 99 && $read(1)['cancel_reason'] === 'Cannot attend', 'An edited pending request can still be cancelled');
$reject(['action'=>'cancel','appointment_id'=>1], 'no longer be cancelled');
$pdo->exec("UPDATE appointments SET status='Scheduled', approved_by=88, approved_at=CURRENT_TIMESTAMP WHERE id=2");
$reject(array_replace($input, ['appointment_id'=>2]), 'already been approved');
$reject(['action'=>'cancel','appointment_id'=>2], 'no longer be cancelled');
$reject(['action'=>'request_cancel','appointment_id'=>2], 'cancellation reason');
$request = managePatientAppointment($pdo,7,99,['action'=>'request_cancel','appointment_id'=>2,'cancel_reason'=>'Unable to attend']);
$check($read(2)['status'] === 'Scheduled', 'Cancellation requests leave the approved appointment scheduled');
$reject(['action'=>'request_cancel','appointment_id'=>2,'cancel_reason'=>'Duplicate'], 'already awaiting');
reviewAppointmentCancellationRequest($pdo, $request['request_id'], 88, 'decline', 'Please call us');
$check($read(2)['status'] === 'Scheduled', 'Declining a request preserves the appointment');
$request = managePatientAppointment($pdo,7,99,['action'=>'request_cancel','appointment_id'=>2,'cancel_reason'=>'Verified reason']);
reviewAppointmentCancellationRequest($pdo, $request['request_id'], 88, 'approve', 'Confirmed by phone');
$check($read(2)['status'] === 'Cancelled' && $read(2)['cancelled_by'] == 88, 'Only clinic approval cancels a scheduled appointment');
try { reviewAppointmentCancellationRequest($pdo, $request['request_id'], 88, 'approve', ''); throw new RuntimeException('Duplicate review accepted'); }
catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), 'already been reviewed'), 'Duplicate reviews rejected'); }
$pdo->exec("UPDATE appointments SET approved_at=CURRENT_TIMESTAMP WHERE id=3");
$reject(array_replace($input,['appointment_id'=>3]), 'already been approved');
$pdo->exec("UPDATE appointments SET check_in_time=CURRENT_TIMESTAMP WHERE id=4");
$reject(['action'=>'cancel','appointment_id'=>4], 'no longer be cancelled');
$pdo->exec("INSERT INTO queue VALUES (5, 'waiting')");
$reject(['action'=>'cancel','appointment_id'=>5], 'clinic queue');
foreach ([6=>'Completed',7=>'Cancelled',8=>'In Queue',9=>'Pending Payment'] as $id=>$status) {
    $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status,$id]);
    $reject(['action'=>'cancel','appointment_id'=>$id], 'no longer be cancelled');
}
$pdo->exec("UPDATE appointments SET is_archived=1 WHERE id=10");
$reject(['action'=>'cancel','appointment_id'=>10], 'no longer be cancelled');
$pdo->exec("UPDATE appointments SET deleted_at=CURRENT_TIMESTAMP WHERE id=11");
$reject(['action'=>'cancel','appointment_id'=>11], 'no longer be cancelled');
$pdo->exec("UPDATE appointments SET appointment_date='2000-01-01' WHERE id=12");
$reject(['action'=>'cancel','appointment_id'=>12], 'no longer be cancelled');
$pdo->prepare('UPDATE appointments SET appointment_date=? WHERE id=14')->execute([$date]);
$reject(array_replace($input,['appointment_id'=>13]), 'another appointment');

$insert->execute([15, $date]);
$pdo->exec("UPDATE appointments SET status='Scheduled', approved_at=CURRENT_TIMESTAMP WHERE id=15");
$reject(['action'=>'request_cancel','appointment_id'=>15,'cancel_reason'=>'Reason'], 'not found', 8);
$request = managePatientAppointment($pdo,7,99,['action'=>'request_cancel','appointment_id'=>15,'cancel_reason'=>'Reason']);
try { assertAppointmentCancellationReviewedBeforeArrival($pdo, 15); throw new RuntimeException('Pending cancellation allowed arrival'); }
catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), 'Decline'), 'Pending cancellation blocks arrival until declined'); }
$pdo->exec("INSERT INTO queue VALUES (15, 'on_hold')");
try { reviewAppointmentCancellationRequest($pdo, $request['request_id'], 88, 'approve', ''); throw new RuntimeException('Queued appointment cancelled'); }
catch (InvalidArgumentException $e) { $check(str_contains($e->getMessage(), 'clinic queue'), 'Approval rechecks queue state'); }
$check(!$pdo->inTransaction() && $read(15)['status'] === 'Scheduled', 'Failed review rolls back without cancelling');
reviewAppointmentCancellationRequest($pdo, $request['request_id'], 88, 'decline', 'Patient is in the clinic');
assertAppointmentCancellationReviewedBeforeArrival($pdo, 15);
$check(!isset(pendingAppointmentCancellationRequests($pdo)[15]), 'Declined requests clear the badge and arrival restriction');

echo "PASS: cancellation request ownership, required reason, duplicate prevention, decline/retry, clinic approval, and queue race protection\n";
echo "PASS: ownership, approval lock, one-time edit, no-op/invalid slot recovery, cancellation, queue and terminal-state restrictions\n";
