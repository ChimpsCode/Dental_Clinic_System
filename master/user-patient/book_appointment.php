<?php
header('Content-Type: application/json');

session_start();
require_once '../../config/database.php';
require_once '../../includes/sms_functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_id = $_SESSION['patient_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$patient_id) {
        throw new Exception('Patient not logged in');
    }

    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $appointment_date);
    if (!$dateObj) {
        throw new Exception('Invalid date format');
    }

    $dayOfWeek = (int)$dateObj->format('w');
    if ($dayOfWeek === 0) {
        throw new Exception('Clinic is closed on Sundays');
    }

    if ($dateObj < new DateTime('today')) {
        throw new Exception('Cannot book appointments in the past');
    }
    
    $treatment = 'General Checkup';
    $duration = 30;
    
    if ($service_id) {
        $stmt = $pdo->prepare("SELECT name, duration_minutes FROM services WHERE id = ? AND is_active = 1");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            $treatment = $service['name'];
            $duration = $service['duration_minutes'] ?? 30;
        }
    }
    
    // Check for overlap conflicts using duration-based overlap logic
    $timeParts = explode(':', $appointment_time);
    $newStartMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
    $newEndMinutes = $newStartMinutes + $duration;
    
    // Get existing bookings with their durations
    $stmt = $pdo->prepare("
        SELECT 
            TIME_FORMAT(appointment_time, '%H:%i') as start_time,
            COALESCE(duration_minutes, 
                (SELECT duration_minutes FROM services WHERE id = appointments.service_id), 
                30) as dur
        FROM appointments 
        WHERE appointment_date = ?
    ");
    $stmt->execute([$appointment_date]);
    $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($existingBookings as $existing) {
        $exParts = explode(':', $existing['start_time']);
        $exStart = (int)$exParts[0] * 60 + (int)$exParts[1];
        $exEnd = $exStart + (int)$existing['dur'];
        
        // Check overlap
        if ($newStartMinutes < $exEnd && $newEndMinutes > $exStart) {
            throw new Exception('This time slot overlaps with an existing booking. Please select a different time.');
        }
    }
    
    // Get the status_id for 'Scheduled'
    $stmt = $pdo->prepare("SELECT id FROM appointment_statuses WHERE LOWER(status_name) = 'scheduled' LIMIT 1");
    $stmt->execute();
    $status_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_id = $status_row['id'] ?? 1; // Default to 1 if not found

    $pdo->beginTransaction();

    // Check what columns exist
    $cols = $pdo->query("DESCRIBE appointments");
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    $colList = [];
    $valList = [];
    $params = [];
    
    if (in_array('patient_id', $columns)) {
        $colList[] = 'patient_id';
        $params[] = $patient_id;
    }
    if (in_array('service_id', $columns)) {
        $colList[] = 'service_id';
        $params[] = $service_id;
    }
    if (in_array('appointment_date', $columns)) {
        $colList[] = 'appointment_date';
        $params[] = $appointment_date;
    }
    if (in_array('appointment_time', $columns)) {
        $colList[] = 'appointment_time';
        $params[] = $appointment_time;
    }
    if (in_array('duration_minutes', $columns)) {
        $colList[] = 'duration_minutes';
        $params[] = $duration;
    }
    if (in_array('treatment', $columns)) {
        $colList[] = 'treatment';
        $params[] = $treatment;
    }
    if (in_array('notes', $columns)) {
        $colList[] = 'notes';
        $params[] = $notes;
    }
    if (in_array('created_by', $columns)) {
        $colList[] = 'created_by';
        $params[] = $_SESSION['user_id'] ?? null;
    }
    if (in_array('booked_by_id', $columns)) {
        $colList[] = 'booked_by_id';
        $params[] = $patient_id; // Patient booked their own
    }
    if (in_array('booking_type', $columns)) {
        $colList[] = 'booking_type';
        $params[] = 'self-booking';
    }
    if (in_array('reference_code', $columns)) {
        $colList[] = 'reference_code';
        $params[] = 'APT' . date('ymdHis') . mt_rand(10, 99);
    }
    if (in_array('status', $columns)) {
        $colList[] = 'status';
        $params[] = 'scheduled';
    }
    if (in_array('status_id', $columns)) {
        $colList[] = 'status_id';
        $params[] = 1;
    }
    
    $sql = "INSERT INTO appointments (" . implode(', ', $colList) . ") VALUES (" . implode(', ', array_fill(0, count($params), '?')) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $appointment_id = $pdo->lastInsertId();

    $pdo->commit();

    // Get patient phone for SMS
    $stmt = $pdo->prepare("SELECT phone FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_phone = $patient['phone'] ?? null;
    
    // Get patient name for SMS
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_name = ($patient_info['first_name'] ?? '') . ' ' . ($patient_info['last_name'] ?? '');
    
    // Send SMS confirmation
    $sms_sent = false;
    $sms_error = null;
    if ($patient_phone) {
        $sms_result = sendAppointmentConfirmationSMS(
            $patient_phone,
            $patient_name,
            $appointment_date,
            $appointment_time,
            $treatment
        );
        $sms_sent = $sms_result['success'];
        if (!$sms_sent) {
            $sms_error = $sms_result['error'];
            error_log("SMS failed for appointment $appointment_id: " . $sms_error);
        }
    }

    $formattedDate = date('F j, Y', strtotime($appointment_date));
    $formattedTime = date('g:i A', strtotime($appointment_time));

    $response = [
        'success' => true,
        'message' => "Appointment booked successfully for {$formattedDate} at {$formattedTime}!",
        'appointment_id' => $appointment_id
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Book Appointment Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
