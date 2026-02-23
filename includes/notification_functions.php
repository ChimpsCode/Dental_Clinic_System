<?php
/**
 * Notification Functions
 * Functions to create and manage notifications
 */

require_once __DIR__ . '/../config/database.php';

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
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
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
 * Notify when new appointment is created
 */
function notifyNewAppointment($pdo, $patientName, $appointmentDate, $dentistName = null) {
    $title = "New Appointment";
    $message = "New appointment scheduled for $patientName on $appointmentDate";
    $actionUrl = "admin_appointments.php";
    
    notifyAdmin($pdo, 'appointment', $title, $message, $actionUrl);
    notifyDentist($pdo, 'appointment', $title, $message, "dentist_appointments.php");
    notifyStaff($pdo, 'appointment', $title, $message, $actionUrl);
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
function notifyNewInquiry($pdo, $inquiryName, $source) {
    $title = "New Inquiry";
    $message = "New inquiry received from $inquiryName via $source";
    $actionUrl = "admin_inquiries.php";
    
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
        notifyStaff($pdo, 'reminder', $title, $message, "staff_appointments.php");
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
    $actionUrl = "staff_queue.php";
    
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
    notifyStaff($pdo, 'cancellation', $title, $message, "staff_appointments.php");
}
