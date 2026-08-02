<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();

try {
    require_once '../../config/database.php';
    require_once '../../includes/appointment_approval_helper.php';
    require_once '../../includes/sms_functions.php';
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
    
    // Anti-spam: patient can only have one pending approval request at a time
    if (patientHasPendingAppointment($pdo, (int)$patient_id)) {
        throw new Exception('You already have a booking request waiting for staff approval. Please wait for the clinic to approve or decline it before requesting another.');
    }

    // Anti-spam: patient can't have another active appointment the same day
    if (patientHasActiveAppointmentOnDate($pdo, (int)$patient_id, $appointment_date)) {
        throw new Exception('You already have an appointment (or pending request) for ' . $appointment_date . '. Please choose a different date.');
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
          AND (is_archived = 0 OR is_archived IS NULL)
          AND LOWER(COALESCE(status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show', 'pending approval')
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
        $params[] = 'Pending Approval';
    }
    
    $sql = "INSERT INTO appointments (" . implode(', ', $colList) . ") VALUES (" . implode(', ', array_fill(0, count($params), '?')) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $appointment_id = $pdo->lastInsertId();

    // Check if patient should transition from New Patient to Returning Patient
    try {
        require_once __DIR__ . '/../../includes/patient_type_helper.php';
        maybeTransitionToReturningPatient($pdo, $patient_id);
    } catch (Exception $e) {
        // Silent fail - non-critical
    }

    $pdo->commit();

    $formattedDate = date('F j, Y', strtotime($appointment_date));
    $formattedTime = date('g:i A', strtotime($appointment_time));

    $response = [
        'success' => true,
        'message' => "Your booking request for {$formattedDate} at {$formattedTime} has been submitted and is pending staff approval. You'll be notified once it is confirmed.",
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
} catch (Error $e) {
    error_log("Book Appointment Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred. Please try again.'
    ]);
}
