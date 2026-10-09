<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/appointment_cancellation_requests.php';
require __DIR__ . '/../includes/notification_functions.php';

// These temporary tables shadow clinic tables only on this test connection.
// Patients intentionally have no user_id: the account link lives on users.
$pdo->exec('CREATE TEMPORARY TABLE patients (id INT PRIMARY KEY, email VARCHAR(100), phone VARCHAR(30))');
$pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, patient_id INT NULL, email VARCHAR(100), phone VARCHAR(30), is_archived INT DEFAULT 0)');
$pdo->exec('CREATE TEMPORARY TABLE appointments (id INT PRIMARY KEY, patient_id INT, status VARCHAR(30), cancel_reason TEXT, cancelled_by INT, cancelled_at DATETIME)');
$pdo->exec('CREATE TEMPORARY TABLE queue (appointment_id INT, status VARCHAR(30))');
$pdo->exec("CREATE TEMPORARY TABLE appointment_cancellation_requests (id INT PRIMARY KEY, appointment_id INT, patient_id INT, reason TEXT, status VARCHAR(20), reviewed_by INT, reviewed_at DATETIME, review_note TEXT)");
$pdo->exec("CREATE TEMPORARY TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, type VARCHAR(50), title VARCHAR(255), message TEXT, action_url VARCHAR(255), created_at DATETIME, is_read INT DEFAULT 0)");
$pdo->exec("INSERT INTO patients VALUES (7,'patient@example.test','09000000000'),(8,'legacy@example.test','09000000001')");
$pdo->exec("INSERT INTO users VALUES (70,7,'patient@example.test','09000000000',0),(80,NULL,'legacy@example.test','09000000001',0),(90,NULL,'staff@example.test',NULL,0)");
$pdo->exec("INSERT INTO appointments (id,patient_id,status) VALUES (1,7,'Scheduled'),(2,8,'Scheduled')");
$pdo->exec("INSERT INTO appointment_cancellation_requests (id,appointment_id,patient_id,reason,status) VALUES (1,1,7,'Cannot attend','pending'),(2,2,8,'Cannot attend','pending')");

// Exercise the notification code used by the review endpoint after each review.
$source = file_get_contents(__DIR__ . '/../master/shared/cancellation_requests.php');
$start = strpos($source, '$user = getUserIdForPatient(');
$end = strpos($source, "require_once __DIR__ . '/../../includes/audit_helper.php';", $start);
if ($start === false || $end === false) throw new RuntimeException('Review notification block not found');
$notificationCode = substr($source, $start, $end - $start);
foreach ([1 => 70, 2 => 80] as $requestId => $accountId) {
    $input = ['note' => 'Reviewed by the clinic'];
    $result = reviewAppointmentCancellationRequest($pdo, $requestId, 90, 'approve', $input['note']);
    eval($notificationCode);
    $notifications = getNotifications($pdo, $accountId);
    if (count($notifications) !== 1 || getUnreadCount($pdo, $accountId) !== 1) {
        throw new RuntimeException('Cancellation must appear as an unread notification for the patient');
    }
    $notification = $notifications[0];
    if ($notification['type'] !== 'appointment'
        || strpos($notification['message'], 'appointment has been cancelled') === false
        || strpos($notification['message'], $input['note']) === false
        || $notification['action_url'] !== 'appointments.php?tab=upcoming') {
        throw new RuntimeException('Cancellation notification must contain the decision, note and appointment link');
    }
}
if (getUnreadCount($pdo, 90) !== 0) throw new RuntimeException('Notification must go to the patient account');
echo "PASS: approved cancellation reaches the patient bell through linked and legacy accounts.\n";
