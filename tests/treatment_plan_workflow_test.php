<?php
ob_start();
// Integration checks use temporary tables, never patient/appointment fixtures
// in the real tables. The additive workflow schema is installed first.
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/treatment_plan_workflow.php';
ensureTreatmentPlanWorkflowSchema($pdo);
foreach (['appointments', 'treatment_plans', 'queue', 'services', 'inquiries', 'settings', 'schedules', 'dentist_unavailable'] as $table) {
    $pdo->exec('CREATE TEMPORARY TABLE tp_test_' . $table . ' LIKE ' . $table);
    $pdo->exec('ALTER TABLE tp_test_' . $table . ' RENAME TO ' . $table);
}
function checkPlan(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function expectPlanRejection(callable $call, string $message): void {
    try { $call(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException($message);
}
$pdo->exec("INSERT INTO services (id, name, price, duration_minutes, is_active) VALUES (1, 'Cleaning', 800, 60, 1), (2, 'Extraction', 500, 30, 1)");
$sessions = [
    ['date' => '2030-01-07', 'time' => '09:00:00', 'treatment' => 'Cleaning', 'estimated_cost' => 800],
    ['date' => '2030-01-08', 'time' => '10:00:00', 'treatment' => 'Extraction', 'estimated_cost' => 500]
];
$pdo->prepare('INSERT INTO treatment_plans (id, patient_id, doctor_id, plan_name, total_sessions, sessions_data, total_estimated_cost) VALUES (1, 1, 1, ?, 2, ?, 1300)')->execute(['Test plan', json_encode($sessions)]);
$pdo->beginTransaction();
reserveTreatmentPlanSessions($pdo, 1, 1, 1, $sessions);
$pdo->commit();
$appointments = $pdo->query('SELECT * FROM appointments ORDER BY treatment_plan_session')->fetchAll();
checkPlan(count($appointments) === 2, 'Every session must create one appointment');
$pdo->beginTransaction(); reserveTreatmentPlanSessions($pdo, 1, 1, 1, $sessions); $pdo->commit();
checkPlan((int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn() === 2, 'Saving a plan again must reuse its appointments');
checkPlan((int)$appointments[0]['duration_minutes'] === 60, 'Service duration must reserve the full interval');
checkPlan(syncTreatmentPlanProgress($pdo, 1)['overall_status'] === 'Active', 'A scheduled plan must be Active');
expectPlanRejection(fn() => assertTreatmentPlanSlotAvailable($pdo, '2030-01-07', '09:30', 30), 'Overlapping booking should be blocked');
expectPlanRejection(fn() => assertTreatmentPlanSlotAvailable($pdo, '2030-01-07', '17:00', 30), 'After-hours booking should be blocked');
expectPlanRejection(fn() => assertTreatmentPlanSlotAvailable($pdo, '2030-01-06', '10:00', 30), 'Closed-day booking should be blocked');
$firstId = (int)$appointments[0]['id']; $secondId = (int)$appointments[1]['id'];
$pdo->prepare("INSERT INTO queue (id, patient_id, appointment_id, queue_number, status) VALUES (1, 1, ?, 1, 'waiting')")->execute([$firstId]);
$pdo->prepare("UPDATE appointments SET status = 'In Queue' WHERE id = ?")->execute([$firstId]);
syncTreatmentPlanForQueue($pdo, 1);
checkPlan(syncTreatmentPlanProgress($pdo, 1)['overall_status'] === 'Active', 'Check-in alone must not start the plan');
$pdo->exec("UPDATE queue SET status = 'in_procedure' WHERE id = 1");
syncTreatmentPlanForQueue($pdo, 1);
checkPlan(syncTreatmentPlanProgress($pdo, 1)['overall_status'] === 'In-Progress', 'Starting the dentist procedure must start the plan');
$pdo->exec("UPDATE queue SET status = 'pending_payment' WHERE id = 1");
syncTreatmentPlanForQueue($pdo, 1, true);
$plan = syncTreatmentPlanProgress($pdo, 1);
checkPlan((int)$plan['completed_sessions'] === 1, 'Only Session 1 must be completed');
checkPlan($plan['overall_status'] === 'In-Progress', 'Remaining sessions must keep the plan in progress');
checkPlan(json_decode($plan['sessions_data'], true)[0]['status'] === 'Completed', 'Session 1 complete label requires completed state');
$stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ?'); $stmt->execute([$secondId]);
checkPlan($stmt->fetchColumn() === 'Scheduled', 'Completing one visit must not complete future appointments');
syncTreatmentPlanForQueue($pdo, 1, true);
checkPlan((int)syncTreatmentPlanProgress($pdo, 1)['completed_sessions'] === 1, 'Repeated completion must be idempotent');
expectPlanRejection(fn() => changeTreatmentPlanSchedule($pdo, 1, 'reschedule_session', 1, '2030-01-09', '11:00'), 'Completed session must not be rescheduled');
changeTreatmentPlanSchedule($pdo, 1, 'reschedule_session', 2, '2030-01-09', '11:00');
$stmt = $pdo->prepare('SELECT appointment_date FROM appointments WHERE id = ?'); $stmt->execute([$secondId]);
checkPlan($stmt->fetchColumn() === '2030-01-09', 'Reschedule must move the existing appointment');
assertTreatmentPlanSlotAvailable($pdo, '2030-01-08', '10:00', 30);
expectPlanRejection(fn() => assertTreatmentPlanSlotAvailable($pdo, '2030-01-09', '11:00', 30), 'New time must be reserved');
changeTreatmentPlanSchedule($pdo, 1, 'cancel_plan');
checkPlan(syncTreatmentPlanProgress($pdo, 1)['overall_status'] === 'Cancelled', 'Cancelling remaining sessions must cancel the unfinished plan');
checkPlan((int)syncTreatmentPlanProgress($pdo, 1)['completed_sessions'] === 1, 'Cancellation must preserve completed history');
assertTreatmentPlanSlotAvailable($pdo, '2030-01-09', '11:00', 30);
// Complete a second independent plan to verify the final transition.
$pdo->prepare('INSERT INTO treatment_plans (id, patient_id, doctor_id, plan_name, total_sessions, sessions_data) VALUES (2, 2, 1, ?, 1, ?)')->execute(['One session', json_encode([$sessions[1]])]);
$pdo->beginTransaction(); reserveTreatmentPlanSessions($pdo, 2, 2, 1, [$sessions[1]]); $pdo->commit();
$lastId = (int)$pdo->query('SELECT id FROM appointments WHERE treatment_plan_id = 2')->fetchColumn();
$pdo->prepare("INSERT INTO queue (id, patient_id, appointment_id, queue_number, status) VALUES (2, 2, ?, 2, 'pending_payment')")->execute([$lastId]);
syncTreatmentPlanForQueue($pdo, 2, true);
checkPlan(syncTreatmentPlanProgress($pdo, 2)['overall_status'] === 'Completed', 'All finished sessions must complete the plan');
echo "PASS: reservations, overlap protection, clinic hours, Active/start/completion, exact appointment completion, idempotency, rescheduling, cancellation and completed history.\n";
// Verify the actual public availability endpoint sees the plan reservation.
$pdo->prepare('INSERT INTO treatment_plans (id, patient_id, doctor_id, plan_name, total_sessions, sessions_data) VALUES (3, 3, 1, ?, 1, ?)')->execute(['Availability test', json_encode([$sessions[0]])]);
$pdo->beginTransaction(); reserveTreatmentPlanSessions($pdo, 3, 3, 1, [$sessions[0]]); $pdo->commit();
$_GET = ['date' => '2030-01-07', 'service_id' => 2, 'dentist_id' => 1];
$_SERVER['REQUEST_METHOD'] = 'GET';
$level = ob_get_level();
ob_start();
chdir(__DIR__ . '/../master/user-patient');
require 'get_slots.php';
$json = '';
while (ob_get_level() > $level) $json = ob_get_clean() . $json;
$availability = json_decode($json, true);
checkPlan(!empty($availability['success']), 'Availability endpoint must return valid slots');
checkPlan(in_array('09:00', $availability['booked_slots'], true) && in_array('09:30', $availability['booked_slots'], true), 'Patients must see the whole plan session as occupied');
checkPlan(!in_array('09:00', $availability['available_slots'], true), 'Reserved time must not be offered for booking');
echo "PASS: patient get_slots endpoint blocks the plan appointment and its full duration.\n";
// The same linked reservation must be visible in the staff/admin calendar feed.
$_SESSION['user_id'] = 1; $_SESSION['role'] = 'staff';
$_GET = ['cal' => '2030-01'];
$level = ob_get_level(); ob_start();
chdir(__DIR__ . '/../master/shared');
require 'calendar_data.php';
$json = '';
while (ob_get_level() > $level) $json = ob_get_clean() . $json;
$calendar = json_decode($json, true);
checkPlan(!empty($calendar['success']), 'Calendar feed must load');
$visible = array_filter($calendar['appointments']['2030-01-07'] ?? [], fn($a) => $a['status'] === 'Scheduled' && strpos($a['service_name'], 'Plan session 1') !== false);
checkPlan(count($visible) === 1, 'Staff/admin calendar must identify the reserved plan session');
echo "PASS: staff/admin calendar feed includes the scheduled plan session.\n";
require __DIR__ . '/../includes/treatment_plan_queue_items.php';
$merged = mergeTreatmentPlanQueueItems($pdo, [['id' => 1, 'appointment_id' => $firstId, 'status' => 'completed', 'queue_time' => '09:00:00']]);
$scheduledRows = array_values(array_filter($merged, fn($item) => $item['status'] === 'scheduled'));
checkPlan(count($scheduledRows) === 0 && count($merged) === 1, 'Scheduled plan appointments must stay out of the queue until arrival');
checkPlan(count(array_filter($merged, fn($item) => $item['status'] === 'waiting')) === 0, 'Scheduled sessions must not increase waiting counts');
checkPlan(!empty(array_values(array_filter($merged, fn($item) => ($item['id'] ?? 0) === 1))[0]['treatment_plan_id']), 'Existing queue rows must retain their plan badge');
$thirdId = (int)$pdo->query('SELECT id FROM appointments WHERE treatment_plan_id = 3')->fetchColumn();
$pdo->prepare("INSERT INTO queue (id, patient_id, appointment_id, queue_number, status) VALUES (3, 3, ?, 3, 'waiting')")->execute([$thirdId]);
$pdo->prepare("UPDATE appointments SET status = 'In Queue' WHERE id = ?")->execute([$thirdId]);
$merged = mergeTreatmentPlanQueueItems($pdo, [['id' => 3, 'appointment_id' => $thirdId, 'status' => 'waiting', 'queue_time' => '09:00:00']]);
checkPlan(count($merged) === 1 && !empty($merged[0]['treatment_plan_id']) && $merged[0]['status'] === 'waiting', 'Check-in must replace the scheduled row without duplicates and preserve its badge');
echo "PASS: scheduled sessions stay in Appointments; only checked-in sessions enter the queue and retain the plan marker.\n";
// Exercise the same arrival endpoint used by both Appointments pages.
$pdo->prepare('INSERT INTO treatment_plans (id, patient_id, doctor_id, plan_name, total_sessions, sessions_data) VALUES (4, 4, 1, ?, 1, ?)')->execute(['Arrival test', json_encode([['date' => '2030-01-10', 'time' => '10:00:00', 'treatment' => 'Cleaning']])]);
$pdo->beginTransaction(); reserveTreatmentPlanSessions($pdo, 4, 4, 1, [['date' => '2030-01-10', 'time' => '10:00:00', 'treatment' => 'Cleaning']]); $pdo->commit();
$arrivalId = (int)$pdo->query('SELECT id FROM appointments WHERE treatment_plan_id = 4')->fetchColumn();
// Run the actual scheduled-card query; this also detects missing SQL joins.
$whereClause = "WHERE COALESCE(a.is_archived, 0) = 0";
$adminSource = file_get_contents(__DIR__ . '/../master/admin/admin_appointments.php');
$start = strpos($adminSource, '$allStmt = $pdo->query("SELECT');
$end = strpos($adminSource, '$allAppointments =', $start);
eval(substr($adminSource, $start, $end - $start));
$cards = $allStmt->fetchAll();
checkPlan(count(array_filter($cards, fn($card) => (int)$card['treatment_plan_id'] === 4 && $card['status'] === 'Scheduled')) === 1, 'Plan session must appear in Scheduled appointments');
$_POST = ['appt_id' => $arrivalId]; $_SERVER['REQUEST_METHOD'] = 'POST';
$level = ob_get_level(); ob_start();
require 'mark_arrived.php';
$json = '';
while (ob_get_level() > $level) $json = ob_get_clean() . $json;
$arrival = json_decode($json, true);
checkPlan(!empty($arrival['success']) && (int)$arrival['queue_number'] > 0, 'Mark as Arrived must assign the normal queue number');
$stmt = $pdo->prepare('SELECT * FROM queue WHERE id = ?'); $stmt->execute([$arrival['queue_id']]); $entry = $stmt->fetch();
checkPlan((int)$entry['appointment_id'] === $arrivalId && $entry['status'] === 'waiting' && $entry['treatment_type'] === 'Cleaning', 'Arrival must link the plan appointment to its waiting queue row');
checkPlan(syncTreatmentPlanProgress($pdo, 4)['overall_status'] === 'Active', 'Arrival must keep the plan Active until the dentist starts');
echo "PASS: Scheduled appointment query and real Mark as Arrived endpoint produce a linked waiting queue entry with its normal number.\n";
