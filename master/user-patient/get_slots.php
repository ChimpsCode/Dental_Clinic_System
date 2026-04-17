<?php
header('Content-Type: application/json');

session_start();
require_once '../../config/database.php';

try {
    $date = $_GET['date'] ?? '';
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    
    if (empty($date)) {
        throw new Exception('Date is required');
    }
    
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        throw new Exception('Invalid date format');
    }
    
    $dayOfWeek = (int)$dateObj->format('w');
    if ($dayOfWeek === 0) {
        echo json_encode([
            'success' => true,
            'date' => $date,
            'is_open' => false,
            'message' => 'Clinic is closed on Sundays',
            'available_slots' => [],
            'booked_slots' => []
        ]);
        exit();
    }
    
    if ($dateObj < new DateTime('today')) {
        echo json_encode([
            'success' => true,
            'date' => $date,
            'is_open' => true,
            'is_past' => true,
            'message' => 'Cannot book past dates',
            'available_slots' => [],
            'booked_slots' => []
        ]);
        exit();
    }
    
    $duration = 30;
    if ($service_id) {
        $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ? AND is_active = 1");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            $duration = $service['duration_minutes'] ?? 30;
        }
    }
    
    $startHour = 9;
    $endHour = 17;
    $allSlots = [];
    
    $slotStart = new DateTime($date . ' 09:00:00');
    $dayEnd = new DateTime($date . ' 17:00:00');
    
    while ($slotStart < $dayEnd) {
        $slotEnd = clone $slotStart;
        $slotEnd->modify("+{$duration} minutes");
        
        if ($slotEnd <= $dayEnd) {
            $allSlots[] = $slotStart->format('H:i');
        }
        
        $slotStart->modify("+{$duration} minutes");
    }
    
    // Get ALL booked appointment times for this date (regardless of service)
    $stmt = $pdo->prepare("
        SELECT appointment_time, COUNT(*) as count 
        FROM appointments 
        WHERE appointment_date = ? 
        AND status IN ('scheduled', 'pending', 'confirmed')
        GROUP BY appointment_time
    ");
    $stmt->execute([$date]);
    $bookedTimes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $bookedSlots = [];
    $availableSlots = [];
    
    // For this service's duration, determine which slots are available
    foreach ($allSlots as $slot) {
        // A slot is booked if there's ANY appointment at this exact time
        if (isset($bookedTimes[$slot]) && $bookedTimes[$slot] >= 1) {
            $bookedSlots[] = $slot;
        } else {
            $availableSlots[] = $slot;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'is_open' => true,
        'is_past' => false,
        'duration' => $duration,
        'message' => 'Slots retrieved successfully',
        'available_slots' => $availableSlots,
        'booked_slots' => $bookedSlots,
        'all_slots' => $allSlots
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'available_slots' => [],
        'booked_slots' => [],
        'all_slots' => []
    ]);
}
