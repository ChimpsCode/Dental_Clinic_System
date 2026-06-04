<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $appointmentDate = trim($_POST['preferred_date'] ?? '');
    $appointmentTime = trim($_POST['preferred_time'] ?? '');

    if (!$firstName || !$lastName || !$email || !$phone || !$appointmentDate || !$appointmentTime) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled out.']);
        exit;
    }

    if (!$serviceId) {
        echo json_encode(['success' => false, 'message' => 'Please select a service.']);
        exit;
    }

    // Validate date
    $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
    if (!$dateObj) {
        throw new Exception('Invalid date format');
    }

    if ($dateObj < new DateTime('today')) {
        throw new Exception('Cannot book appointments in the past');
    }

    // Get service details
    $stmt = $pdo->prepare("SELECT name, duration_minutes FROM services WHERE id = ? AND is_active = 1");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        throw new Exception('Selected service not found');
    }
    $treatment = $service['name'];
    $duration = (int)($service['duration_minutes'] ?? 30);

    // Check for overlap conflicts
    $timeParts = explode(':', $appointmentTime);
    $newStartMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
    $newEndMinutes = $newStartMinutes + $duration;

    $stmt = $pdo->prepare("
        SELECT 
            TIME_FORMAT(appointment_time, '%H:%i') as start_time,
            COALESCE(duration_minutes, 
                (SELECT duration_minutes FROM services WHERE id = appointments.service_id), 
                30) as dur
        FROM appointments 
        WHERE appointment_date = ?
          AND COALESCE(is_archived, 0) = 0
          AND LOWER(COALESCE(status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show')
    ");
    $stmt->execute([$appointmentDate]);
    $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($existingBookings as $existing) {
        $exParts = explode(':', $existing['start_time']);
        $exStart = (int)$exParts[0] * 60 + (int)$exParts[1];
        $exEnd = $exStart + (int)$existing['dur'];
        if ($newStartMinutes < $exEnd && $newEndMinutes > $exStart) {
            throw new Exception('This time slot overlaps with an existing booking.');
        }
    }

    $fullName = trim("$firstName $middleName $lastName");
    $fullName = preg_replace('/\s+/', ' ', $fullName);

    $pdo->beginTransaction();

    // Create a patient record so the name shows in appointment views
    $patientId = null;
    try {
        $stmt = $pdo->prepare("INSERT INTO patients (first_name, middle_name, last_name, phone, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$firstName, $middleName ?: null, $lastName, $phone, $email]);
        $patientId = (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        // If patient insert fails (e.g. missing columns), proceed without linking
        $patientId = null;
    }

    $notes = "Public booking - Name: $fullName, Email: $email, Phone: $phone, Service: $treatment";

    // Check what columns exist in the appointments table
    $cols = $pdo->query("DESCRIBE appointments");
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN);

    $colList = [];
    $params = [];

    if (in_array('patient_id', $columns)) {
        $colList[] = 'patient_id';
        $params[] = $patientId;
    }
    if (in_array('is_registered_patient', $columns)) {
        $colList[] = 'is_registered_patient';
        $params[] = 0;
    }
    if (in_array('service_id', $columns)) {
        $colList[] = 'service_id';
        $params[] = $serviceId;
    }
    if (in_array('appointment_date', $columns)) {
        $colList[] = 'appointment_date';
        $params[] = $appointmentDate;
    }
    if (in_array('appointment_time', $columns)) {
        $colList[] = 'appointment_time';
        $params[] = $appointmentTime;
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
    if (in_array('booking_type', $columns)) {
        $colList[] = 'booking_type';
        $params[] = 'website';
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

    $appointmentId = $pdo->lastInsertId();

    $pdo->commit();

    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));

    echo json_encode([
        'success' => true,
        'message' => "Appointment booked successfully for {$formattedDate} at {$formattedTime}!",
        'appointment_id' => $appointmentId
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
