<?php
/**
 * Notification Functions
 * Functions to create and manage notifications
 */

require_once __DIR__ . '/../config/database.php';

function notifLog(string $msg): void {
    $dir = dirname(__DIR__) . '/cache';
    @mkdir($dir, 0755, true);
    $file = $dir . '/notification_debug.log';
    $line = date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
}

/**
 * Create a notification
 * 
 * @param int $userId User ID to notify (0 for broadcast to role)
 * @param string $type Type of notification (appointment, payment, inquiry, patient, system)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $actionUrl Optional URL to redirect when clicked
 * @return int|false Returns notification ID on success, false on failure
 */
function createNotification($pdo, $userId, $type, $title, $message, $actionUrl = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, action_url, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $type, $title, $message, $actionUrl]);
        $id = (int)$pdo->lastInsertId();
        notifLog("createNotification OK: id={$id} user_id={$userId} type={$type} title={$title}");
        return $id;
    } catch (Exception $e) {
        notifLog("createNotification FAILED: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for all users in a specific role
 * 
 * @param PDO $pdo Database connection
 * @param string $role Role to notify (admin, dentist, staff)
 * @param string $type Type of notification
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $actionUrl Optional URL
 */
function notifyRole($pdo, $role, $type, $title, $message, $actionUrl = null) {
    try {
        $userColumns = array_column($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (in_array('role', $userColumns, true)) {
            $activeClause = in_array('is_archived', $userColumns, true) ? ' AND COALESCE(is_archived, 0) = 0' : '';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(role) = LOWER(?)$activeClause");
        } elseif (in_array('role_id', $userColumns, true)) {
            $stmt = $pdo->prepare("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE LOWER(r.role_name) = LOWER(?)");
        } else {
            return;
        }
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            return;
        }
        
        $insertStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, action_url, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($users as $user) {
            $insertStmt->execute([$user['id'], $type, $title, $message, $actionUrl]);
        }
    } catch (Exception $e) {
        error_log("Error notifying role: " . $e->getMessage());
    } catch (Error $e) {
        error_log("Error notifying role (Error): " . $e->getMessage());
    }
}

/**
 * Create notification for admin users
 */
function notifyAdmin($pdo, $type, $title, $message, $actionUrl = null) {
    notifyRole($pdo, 'admin', $type, $title, $message, $actionUrl);
}

/**
 * Create notification for dentist users
 */
function notifyDentist($pdo, $type, $title, $message, $actionUrl = null) {
    notifyRole($pdo, 'dentist', $type, $title, $message, $actionUrl);
}

/**
 * Create notification for staff users
 */
function notifyStaff($pdo, $type, $title, $message, $actionUrl = null) {
    notifyRole($pdo, 'staff', $type, $title, $message, $actionUrl);
}

/**
 * Get unread notification count for a user
 */
function getUnreadCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get notifications for a user
 */
function getNotifications($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Mark notification as read
 */
function markAsRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllAsRead($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// Notification Trigger Functions
// Call these functions when events occur
// ============================================

/**
 * Notify when new patient is registered
 */
function notifyNewPatient($pdo, $patientName, $patientId) {
    $title = "New Patient Registration";
    $message = "$patientName has been registered as a new patient";
    $actionUrl = "admin_patients.php";
    
    notifyAdmin($pdo, 'patient', $title, $message, $actionUrl);
    notifyStaff($pdo, 'patient', $title, $message, $actionUrl);
    notifyDentist($pdo, 'patient', $title, $message, "dentist_patients.php");
}

/**
 * Notify when a new appointment is CONFIRMED (already approved).
 * Use this only once the appointment is actually on the clinic calendar.
 */
function notifyNewAppointment($pdo, $patientName, $appointmentDate, $dentistName = null, $appointmentTime = null, $serviceName = null) {
    $title = "New Appointment";

    $message = "New appointment scheduled for $patientName on $appointmentDate";
    if ($appointmentTime) {
        $message .= " at $appointmentTime";
    }
    if ($serviceName) {
        $message .= " ($serviceName)";
    }

    $actionUrl = "admin_appointments.php";

    notifyAdmin($pdo, 'appointment', $title, $message, $actionUrl);
    notifyDentist($pdo, 'appointment', $title, $message, "dentist_appointments.php");
    notifyStaff($pdo, 'appointment', $title, $message, "staff_appointments.php");
}

/**
 * Notify when a patient REQUESTED an appointment that still needs approval.
 * This is deliberately worded differently from notifyNewAppointment() so the
 * clinic can tell an unconfirmed request apart from a confirmed booking.
 */
function notifyAppointmentPendingApproval($pdo, $patientName, $appointmentDate, $appointmentTime = null, $serviceName = null) {
    $title = "New Appointment Needs Approval";

    $name = trim((string)$patientName) !== '' ? $patientName : 'A patient';
    $message = "New appointment needs approval: $name requested";
    if ($serviceName) {
        $message .= " $serviceName";
    }
    $message .= " on $appointmentDate";
    if ($appointmentTime) {
        $message .= " at $appointmentTime";
    }
    $message .= ". Approve or decline the request.";

    notifyAdmin($pdo, 'approval', $title, $message, "admin_appointments.php");
    notifyDentist($pdo, 'approval', $title, $message, "dentist_appointments.php");
    notifyStaff($pdo, 'approval', $title, $message, "staff_appointments.php");
}

/**
 * Notify when there's a pending payment
 */
function notifyPendingPayment($pdo, $patientName, $amount) {
    $title = "Pending Payment";
    $message = "Payment of ₱" . number_format($amount, 2) . " pending from $patientName";
    $actionUrl = "admin_payment.php";
    
    notifyAdmin($pdo, 'payment', $title, $message, $actionUrl);
    notifyStaff($pdo, 'payment', $title, $message, $actionUrl);
}

/**
 * Notify when new inquiry is received
 */
function notifyNewInquiry($pdo, $inquiryName, $source, string $actionUrl = 'master/staff/staff_inquiries.php') {
    $title = "New Inquiry";
    $message = "New inquiry received from $inquiryName via $source";
    
    notifyAdmin($pdo, 'inquiry', $title, $message, $actionUrl);
    notifyStaff($pdo, 'inquiry', $title, $message, $actionUrl);
}

/**
 * Notify appointment reminder
 */
function notifyAppointmentReminder($pdo, $patientName, $appointmentDate, $appointmentTime, $role = 'all') {
    $title = "Appointment Reminder";
    $message = "Appointment with $patientName scheduled for $appointmentDate at $appointmentTime";
    
    if ($role === 'all' || $role === 'admin') {
        notifyAdmin($pdo, 'reminder', $title, $message, "admin_appointments.php");
    }
    if ($role === 'all' || $role === 'staff') {
        notifyStaff($pdo, 'reminder', $title, $message, "master/staff/staff_appointments.php");
    }
    if ($role === 'all' || $role === 'dentist') {
        notifyDentist($pdo, 'reminder', $title, $message, "dentist_appointments.php");
    }
}

/**
 * Notify queue update
 */
function notifyQueueUpdate($pdo, $patientName, $status, $dentistName = null) {
    $statusText = [
        'waiting' => 'waiting',
        'in_procedure' => 'now in procedure',
        'completed' => 'completed',
        'on_hold' => 'on hold'
    ];
    
    $title = "Queue Update";
    $message = "Patient $patientName is " . ($statusText[$status] ?? $status);
    $actionUrl = "master/staff/staff_queue.php";
    
    notifyStaff($pdo, 'queue', $title, $message, $actionUrl);
    
    if ($dentistName && $status === 'waiting') {
        notifyDentist($pdo, 'queue', $title, $message, "dentist_queue.php");
    }
}

/**
 * Notify appointment cancellation
 */
function notifyAppointmentCancelled($pdo, $patientName, $appointmentDate, $reason = '') {
    $title = "Appointment Cancelled";
    $message = "Appointment for $patientName on $appointmentDate has been cancelled";
    if ($reason) {
        $message .= ". Reason: $reason";
    }
    $actionUrl = "admin_appointments.php";

    notifyAdmin($pdo, 'cancellation', $title, $message, $actionUrl);
    notifyStaff($pdo, 'cancellation', $title, $message, "master/staff/staff_appointments.php");
}

/**
 * Ensure the notifications table exists with all needed columns.
 */
function ensureNotificationSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $cacheFile = dirname(__DIR__) . '/cache/.notification_schema_ok';
    if (file_exists($cacheFile) && (time() - (int)filemtime($cacheFile)) < 3600) {
        $ensured = true;
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `type` VARCHAR(50) DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `message` TEXT NOT NULL,
            `action_url` VARCHAR(255) DEFAULT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_notif_user` (`user_id`),
            CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $existingColumns = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM `notifications`");
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['Field'];
    }

    if (!in_array('type', $existingColumns)) {
        $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(50) DEFAULT NULL AFTER `user_id`");
    }
    if (!in_array('action_url', $existingColumns)) {
        $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `action_url` VARCHAR(255) DEFAULT NULL AFTER `message`");
    }

    @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, (string)time());
    $ensured = true;

    backfillUserPatientLinks($pdo);
}

function backfillUserPatientLinks(PDO $pdo): void
{
    $backfillCache = dirname(__DIR__) . '/cache/.user_patient_backfill_done';
    if (file_exists($backfillCache) && (time() - (int)filemtime($backfillCache)) < 86400) {
        return;
    }
    try {
        $userColumns = array_column($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (!in_array('patient_id', $userColumns, true)) {
            return;
        }
        $pdo->exec("
            UPDATE users u
            INNER JOIN patients p ON (p.email = u.email AND p.email IS NOT NULL AND p.email != '')
            SET u.patient_id = p.id
            WHERE u.patient_id IS NULL
              AND u.role_id IS NULL
        ");
        $hasPhone = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch(PDO::FETCH_ASSOC);
        if ($hasPhone) {
            $pdo->exec("
                UPDATE users u
                INNER JOIN patients p ON (p.phone = u.phone AND p.phone IS NOT NULL AND p.phone != '' AND u.phone IS NOT NULL AND u.phone != '')
                SET u.patient_id = p.id
                WHERE u.patient_id IS NULL
                  AND u.role_id IS NULL
            ");
        }
        @mkdir(dirname($backfillCache), 0755, true);
        @file_put_contents($backfillCache, (string)time());
    } catch (Exception $e) {
        notifLog("backfillUserPatientLinks failed: " . $e->getMessage());
    }
}

/**
 * Look up user_id from a patients table record.
 */
function getUserIdForPatient(PDO $pdo, int $patientId): ?int
{
    $userColumns = [];
    try {
        $userColumns = array_column($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } catch (Exception $e) {
        notifLog("getUserIdForPatient: unable to inspect users columns: " . $e->getMessage());
        return null;
    }

    $activeClause = in_array('is_archived', $userColumns, true)
        ? ' AND COALESCE(u.is_archived, 0) = 0'
        : '';

    if (in_array('patient_id', $userColumns, true)) {
        try {
            $stmt = $pdo->prepare("SELECT u.id FROM users u WHERE u.patient_id = ?{$activeClause} LIMIT 1");
            $stmt->execute([$patientId]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        } catch (Exception $e) {
            notifLog("getUserIdForPatient patient_id lookup failed: " . $e->getMessage());
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT u.id
            FROM users u
            INNER JOIN patients p ON p.email = u.email
            WHERE p.id = ?
              AND p.email IS NOT NULL AND p.email != ''{$activeClause}
            LIMIT 1");
        $stmt->execute([$patientId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
    } catch (Exception $e) {
        notifLog("getUserIdForPatient patient_id lookup failed: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("SELECT u.id
            FROM users u
            INNER JOIN patients p ON p.phone = u.phone
            WHERE p.id = ?
              AND p.phone IS NOT NULL AND p.phone != ''{$activeClause}
            LIMIT 1");
        $stmt->execute([$patientId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            persistPatientUserLink($pdo, (int)$id, $patientId);
            return (int)$id;
        }
    } catch (Exception $e) {
        notifLog("getUserIdForPatient phone fallback failed: " . $e->getMessage());
    }

    notifLog("getUserIdForPatient: no user found for patient_id={$patientId}");
    return null;
}

function persistPatientUserLink(PDO $pdo, int $userId, int $patientId): void
{
    try {
        $pdo->prepare("UPDATE users SET patient_id = ? WHERE id = ? AND (patient_id IS NULL OR patient_id = 0)")->execute([$patientId, $userId]);
    } catch (Exception $e) {
        notifLog("persistPatientUserLink failed: " . $e->getMessage());
    }
}

// ============================================
// Patient-Facing Notification Triggers
// ============================================

/**
 * Notify patient when their queue status changes.
 */
function notifyPatientQueueUpdate(PDO $pdo, int $patientId, string $queueNumber, string $status): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $labels = [
        'waiting'        => 'You are now in the waiting queue',
        'in_procedure'   => 'Your procedure has started',
        'pending_payment' => 'Your procedure is complete. Please proceed to payment',
        'completed'      => 'Your visit has been completed',
        'on_hold'        => 'Your queue position is currently on hold',
        'cancelled'      => 'Your queue position has been cancelled',
    ];
    $label = $labels[$status] ?? 'Your queue status has changed to ' . $status;
    $title = 'Queue Update';
    $message = "Queue #{$queueNumber}: {$label}";
    createNotification($pdo, $userId, 'queue', $title, $message, 'patient-dashboard.php');
}

/**
 * Notify patient when staff replies to their inquiry.
 */
function notifyPatientInquiryResponse(PDO $pdo, int $patientId, string $inquiryConcern, string $staffName): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $title = 'Inquiry Response';
    $message = "{$staffName} replied to your inquiry: " . mb_substr($inquiryConcern, 0, 80);
    createNotification($pdo, $userId, 'inquiry', $title, $message, 'inquiry_consultation.php');
}

/**
 * Notify patient with a follow-up / appointment reminder.
 */
function notifyPatientFollowUpReminder(PDO $pdo, int $patientId, string $appointmentDate, string $appointmentTime, string $serviceName): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $title = 'Follow-up Reminder';
    $message = "You have an upcoming appointment on {$appointmentDate} at {$appointmentTime} for {$serviceName}";
    createNotification($pdo, $userId, 'reminder', $title, $message, 'appointments.php');
}

/**
 * Bell notice for an automatic appointment reminder (24 hours or 2 hours before).
 */
function notifyPatientAppointmentReminder(PDO $pdo, int $patientId, string $appointmentDate, string $appointmentTime, string $serviceName, int $hoursBefore): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));
    $title = 'Appointment Reminder';
    if ($hoursBefore === 2) {
        $message = "Your appointment is today at {$formattedTime} for {$serviceName}";
    } else {
        $message = "You have an appointment on {$formattedDate} at {$formattedTime} for {$serviceName}";
    }
    createNotification($pdo, $userId, 'reminder', $title, $message, 'appointments.php');
}

/**
 * Notify patient that consultation is complete, with current pending balance.
 */
function notifyPatientConsultationComplete(PDO $pdo, int $patientId, ?float $balance = null): bool
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        notifLog("notifyPatientConsultationComplete: no user_id for patient_id={$patientId}");
        return false;
    }

    if ($balance === null) {
        if (function_exists('getPatientOutstandingBalance')) {
            $balance = getPatientOutstandingBalance($pdo, $patientId);
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT SUM(GREATEST(COALESCE(balance, 0), COALESCE(total_amount, 0) - COALESCE(paid_amount, 0))) AS outstanding
                    FROM billing
                    WHERE patient_id = ?
                      AND LOWER(COALESCE(payment_status, 'unpaid')) NOT IN ('paid', 'cancelled')
                ");
                $stmt->execute([$patientId]);
                $balance = (float)($stmt->fetchColumn() ?: 0);
            } catch (Exception $e) {
                $balance = 0.0;
            }
        }
    }

    $balance = (float)$balance;
    $title = 'Consultation complete';
    $message = 'Your consultation is complete. Pending balance: ₱' . number_format($balance, 2);
    createNotification($pdo, $userId, 'payment', $title, $message, 'billing.php');
    notifLog("notifyPatientConsultationComplete OK: user_id={$userId} patient_id={$patientId} balance={$balance}");
    return true;
}

/**
 * Notify staff that a consultation is complete and a bill may need settling.
 */
function notifyStaffConsultationComplete(PDO $pdo, string $patientName, float $amount, string $treatmentType = 'consultation'): void
{
    $patientName = trim($patientName);
    if ($patientName === '') {
        $patientName = 'A patient';
    }

    $treatmentLabel = trim($treatmentType);
    $amount = (float)$amount;

    if ($amount > 0) {
        $title = 'Bill ready to settle';
        if ($treatmentLabel !== '' && strtolower($treatmentLabel) !== 'consultation') {
            $message = "{$patientName} is done with consultation ({$treatmentLabel}). Outstanding balance: ₱" . number_format($amount, 2);
        } else {
            $message = "{$patientName} is done with consultation. Outstanding balance: ₱" . number_format($amount, 2);
        }
    } else {
        $title = 'Consultation complete';
        $message = "{$patientName} consultation complete. No outstanding balance.";
    }

    notifyStaff($pdo, 'payment', $title, $message, 'staff_payment.php');
    notifLog("notifyStaffConsultationComplete OK: patient={$patientName} amount={$amount} treatment={$treatmentLabel}");
}

/**
 * Notify patient of a pending payment.
 */
function notifyPatientPaymentReminder(PDO $pdo, int $patientId, float $amount, string $treatmentType): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $title = 'Payment Reminder';
    $message = "You have a pending payment of ₱" . number_format($amount, 2) . " for {$treatmentType}. Please settle your balance at your convenience.";
    createNotification($pdo, $userId, 'payment', $title, $message, 'billing.php');
}

/**
 * Notify patient when appointment is approved.
 */
function notifyPatientAppointmentApproved(PDO $pdo, int $patientId, string $appointmentDate, string $appointmentTime, string $serviceName): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $title = 'Appointment Approved';
    $message = "Your appointment for {$serviceName} on {$appointmentDate} at {$appointmentTime} has been confirmed";
    createNotification($pdo, $userId, 'appointment', $title, $message, 'appointments.php');
}

/**
 * Notify patient when appointment is declined.
 */
function notifyPatientAppointmentDeclined(PDO $pdo, int $patientId, string $appointmentDate, string $reason): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $title = 'Appointment Declined';
    $message = "Your appointment request on {$appointmentDate} has been declined";
    if ($reason) {
        $message .= ". Reason: {$reason}";
    }
    createNotification($pdo, $userId, 'appointment', $title, $message, 'appointments.php');
}

/**
 * Notify patient when an appointment is cancelled by clinic staff.
 * Distinct from a declined request: this one was previously confirmed.
 */
function notifyPatientAppointmentCancelled(PDO $pdo, int $patientId, string $appointmentDate, string $reason, string $serviceName = ''): void
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        return;
    }
    $label = trim($serviceName) !== '' ? trim($serviceName) : 'your';
    $title = 'Appointment Cancelled';
    $message = "The clinic cancelled your {$label} appointment on {$appointmentDate}";
    if (trim($reason) !== '') {
        $message .= ". Reason: {$reason}";
    }
    $message .= ". Please book a new slot when convenient.";
    createNotification($pdo, $userId, 'appointment', $title, $message, 'appointments.php');
}

/**
 * Notify patient when a payment has been recorded on their account.
 */
function notifyPatientPaymentRecorded(PDO $pdo, int $patientId, float $amount, string $paymentMethod, string $treatmentType, float $balance, array $billingIds = []): bool
{
    $methodLabels = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'bank_transfer' => 'Bank Transfer',
        'installment' => 'Installment',
        'downpayment' => 'Downpayment',
    ];
    $methodLabel = $methodLabels[strtolower($paymentMethod)] ?? ucfirst($paymentMethod);
    $title = 'Payment Recorded';
    $message = "Payment of ₱" . number_format($amount, 2) . " ({$methodLabel}) received for {$treatmentType}";
    if ($balance > 0) {
        $message .= ". Remaining balance: ₱" . number_format($balance, 2);
    } else {
        $message .= ". Account fully paid. Thank you!";
    }
    // Discounts are never texted when applied; they are reported in this single payment SMS instead
    $smsMessage = $message;
    $discountNote = describeCustomDiscountsForSms($pdo, $billingIds);
    if ($discountNote !== '') {
        $smsMessage = "Payment of ₱" . number_format($amount, 2) . " ({$methodLabel}) received for {$treatmentType} ({$discountNote})"
            . ($balance > 0 ? ". Remaining balance: ₱" . number_format($balance, 2) : ". Account fully paid. Thank you!");
    }
    $smsSent = sendPatientSms($pdo, $patientId, $smsMessage);

    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        notifLog("notifyPatientPaymentRecorded: no user_id for patient_id={$patientId}, SMS only");
        return $smsSent;
    }
    notifLog("notifyPatientPaymentRecorded: user_id={$userId} patient_id={$patientId} amount={$amount} method={$paymentMethod} treatment={$treatmentType} balance={$balance}");
    createNotification($pdo, $userId, 'payment', $title, $message, 'billing.php');
    return true;
}

/**
 * Summarize manual (custom) discounts on the given bills, e.g. "15% discount applied: -₱300.00, Promo".
 */
function describeCustomDiscountsForSms(PDO $pdo, array $billingIds): string
{
    $billingIds = array_values(array_unique(array_filter(array_map('intval', $billingIds))));
    if (empty($billingIds)) {
        return '';
    }
    try {
        $placeholders = implode(',', array_fill(0, count($billingIds), '?'));
        $stmt = $pdo->prepare("SELECT discount_rate, discount_amount, discount_reason FROM billing
            WHERE id IN ({$placeholders}) AND discount_type = 'custom' AND discount_amount > 0");
        $stmt->execute($billingIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        notifLog("describeCustomDiscountsForSms failed: " . $e->getMessage());
        return '';
    }
    if (empty($rows)) {
        return '';
    }
    if (count($rows) === 1) {
        $row = $rows[0];
        $rate = (float)$row['discount_rate'];
        $label = $rate > 0 ? rtrim(rtrim(number_format($rate, 2), '0'), '.') . '% discount' : 'Discount';
        $note = "{$label} applied: -₱" . number_format((float)$row['discount_amount'], 2);
        $reason = trim((string)($row['discount_reason'] ?? ''));
        return $reason !== '' ? "{$note}, {$reason}" : $note;
    }
    $total = array_sum(array_map(function ($r) { return (float)$r['discount_amount']; }, $rows));
    return "Discounts applied: -₱" . number_format($total, 2);
}

/**
 * Text a patient using the phone number on their record.
 *
 * Respects the patient's Notification Preferences and leaves an audit row in
 * sms_log. Returns false when SMS is not configured, the patient has no phone,
 * the patient opted out, or the gateway rejected the message.
 */
function sendPatientSms(PDO $pdo, int $patientId, string $message): bool
{
    require_once __DIR__ . '/sms_functions.php';

    // Peso sign forces UCS-2 encoding, which cuts each SMS segment from 160 to 70 characters
    $smsText = str_replace('₱', 'PHP ', $message) . ' - RF Dental Clinic';

    $sent = sendPatientSmsTracked($pdo, $patientId, $smsText, 'payment');
    if (!$sent) {
        notifLog("sendPatientSms: not sent for patient_id={$patientId}: " . describeSmsSkipReason($pdo, $patientId));
    }
    return $sent;
}

/**
 * Notify patient when staff sets or changes a billing due date.
 */
function notifyPatientDueDateSet(PDO $pdo, int $patientId, int $billingId, string $dueDate, float $balance): bool
{
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        notifLog("notifyPatientDueDateSet: no user_id for patient_id={$patientId}");
        return false;
    }
    $invoice = 'INV-' . str_pad((string)$billingId, 4, '0', STR_PAD_LEFT);
    $dueLabel = date('M j, Y', strtotime($dueDate));
    $title = 'Payment due';
    $message = "{$invoice} has a payment of ₱" . number_format($balance, 2) . " due by {$dueLabel}. Please settle your balance at the clinic.";
    notifLog("notifyPatientDueDateSet: user_id={$userId} patient_id={$patientId} billing_id={$billingId} due={$dueDate} balance={$balance}");
    createNotification($pdo, $userId, 'payment', $title, $message, 'billing.php');
    return true;
}

/**
 * Notify patient when staff/admin manually applies a discount to their bill.
 * $ratePercent is null for fixed-amount discounts.
 */
function notifyPatientDiscountApplied(PDO $pdo, int $patientId, int $billingId, float $discountAmount, ?float $ratePercent, float $newTotal, float $balance, string $reason): bool
{
    $invoice = 'INV-' . str_pad((string)$billingId, 4, '0', STR_PAD_LEFT);
    $label = $ratePercent !== null
        ? 'A ' . rtrim(rtrim(number_format($ratePercent, 2), '0'), '.') . '% discount'
        : 'A discount';
    $title = 'Discount Applied';
    $message = "{$label} (-₱" . number_format($discountAmount, 2) . ") was applied to {$invoice}.";
    if ($reason !== '') {
        $message .= " Reason: {$reason}.";
    }
    $message .= " New total: ₱" . number_format($newTotal, 2) . ". Remaining balance: ₱" . number_format($balance, 2) . ".";
    $userId = getUserIdForPatient($pdo, $patientId);
    if (!$userId) {
        notifLog("notifyPatientDiscountApplied: no user_id for patient_id={$patientId}, discount is reported in the payment SMS only");
        return false;
    }
    notifLog("notifyPatientDiscountApplied: user_id={$userId} patient_id={$patientId} billing_id={$billingId} discount={$discountAmount}");
    createNotification($pdo, $userId, 'payment', $title, $message, 'billing.php');
    return true;
}

function checkAndNotifyOverduePayments(PDO $pdo): void
{
    try {
        $cacheFile = dirname(__DIR__) . '/cache/.overdue_notif_check';
        if (file_exists($cacheFile) && (time() - (int)filemtime($cacheFile)) < 3600) {
            return;
        }

        $stmt = $pdo->query("
            SELECT b.id, b.patient_id, b.balance, b.due_date,
                   COALESCE(s.name, b.notes, 'Dental Service') AS treatment_name
            FROM billing b
            LEFT JOIN treatments t ON t.id = b.treatment_id
            LEFT JOIN services s ON s.id = t.service_id
            WHERE b.balance > 0
              AND b.due_date IS NOT NULL
              AND b.due_date < CURDATE()
              AND b.payment_status NOT IN ('Paid', 'Cancelled')
              AND COALESCE(b.is_archived, 0) = 0
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $patientId = (int)$row['patient_id'];
            $userId = getUserIdForPatient($pdo, $patientId);
            if (!$userId) {
                continue;
            }

            $daysOverdue = (int)((time() - strtotime($row['due_date'])) / 86400);
            $treatmentType = $row['treatment_name'] ?: 'consultation';

            $checkKey = "overdue_notif_{$row['id']}_{$daysOverdue}";
            $chk = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = 'Overdue Payment' AND message LIKE ? LIMIT 1");
            $chk->execute([$userId, "%Billing #{$row['id']}%"]);
            if ($chk->fetchColumn()) {
                continue;
            }

            $title = 'Overdue Payment';
            $plural = ($daysOverdue > 1) ? 's' : '';
            $message = "Billing #{$row['id']} for {$treatmentType} is ₱" . number_format($row['balance'], 2) . " overdue ({$daysOverdue} day{$plural}). Please settle your balance.";
            createNotification($pdo, $userId, 'payment', $title, $message, '../user-patient/billing.php');
        }

        @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, (string)time());
    } catch (Exception $e) {
        notifLog("checkAndNotifyOverduePayments failed: " . $e->getMessage());
    }
}
