<?php
session_start();
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $date = $_GET['date'] ?? '';
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    $exclude_inquiry_id = isset($_GET['exclude_inquiry_id']) ? (int)$_GET['exclude_inquiry_id'] : 0;
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        exit;
    }
    
    // Parse date manually
    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    
    if ($year < 2020 || $year > 2030 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
        echo json_encode(['success' => false, 'message' => 'Invalid date values']);
        exit;
    }
    
    // Calculate day of week
    $dayOfWeek = (int)date('w', mktime(0, 0, 0, $month, $day, $year));
    
    // Sunday = 0, closed
    if ($dayOfWeek === 0) {
        echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Clinic is closed on Sundays']);
        exit;
    }
    
    require_once '../../config/database.php';
    
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Past date check
    $today = date('Y-m-d');
    if ($date < $today) {
        echo json_encode(['success' => true, 'date' => $date, 'is_open' => true, 'is_past' => true, 'message' => 'Cannot book past dates']);
        exit;
    }
    
    // Get service duration for the new booking
    $newServiceDuration = 30;
    if ($service_id) {
        $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ? AND is_active = 1");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            $newServiceDuration = (int)$service['duration_minutes'];
        }
    }
    
    // Get all existing bookings for this date with their durations
    $stmt = $pdo->prepare("
        SELECT 
            TIME_FORMAT(a.appointment_time, '%H:%i') as start_time,
            COALESCE(a.duration_minutes, s.duration_minutes, 30) as duration_minutes
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date = ?
          AND COALESCE(a.is_archived, 0) = 0
          AND LOWER(COALESCE(a.status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show')
    ");
    $stmt->execute([$date]);
    $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inqStmt = $pdo->prepare("
        SELECT
            TIME_FORMAT(i.confirmed_time, '%H:%i') as start_time,
            COALESCE(s.duration_minutes, 30) as duration_minutes
        FROM inquiries i
        LEFT JOIN services s ON s.id = i.service_id
        WHERE i.confirmed_date = ?
          AND COALESCE(i.is_archived, 0) = 0
          AND i.confirmed_time IS NOT NULL
          AND i.confirmed_time <> ''
          AND i.id <> ?
          AND LOWER(COALESCE(i.status, 'pending')) IN ('scheduled', 'awaiting confirmation', 'contacted')
    ");
    $inqStmt->execute([$date, $exclude_inquiry_id]);
    $existingBookings = array_merge($existingBookings, $inqStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Calculate end time for each existing booking
    $bookedSlots = [];
    foreach ($existingBookings as $booking) {
        $startParts = explode(':', $booking['start_time']);
        $startHour = (int)$startParts[0];
        $startMin = (int)$startParts[1];
        $duration = (int)$booking['duration_minutes'];
        
        $endMin = $startMin + $duration;
        $endHour = $startHour + floor($endMin / 60);
        $endMin = $endMin % 60;
        
        $bookedSlots[] = [
            'start' => $booking['start_time'],
            'end' => sprintf('%02d:%02d', $endHour, $endMin),
            'start_minutes' => $startHour * 60 + $startMin,
            'end_minutes' => $endHour * 60 + $endMin
        ];
    }
    
    // Generate potential time slots (9 AM to 5 PM) - fixed 15-minute intervals
    $allSlots = [];
    $hour = 9;
    $minute = 0;
    $clinicEnd = 17 * 60; // 5 PM in minutes
    
    while (($hour * 60 + $minute) < $clinicEnd) {
        $timeStr = sprintf('%02d:%02d', $hour, $minute);
        $slotEndMinutes = $hour * 60 + $minute + $newServiceDuration;
        
        // Check if this slot would extend past clinic hours
        if ($slotEndMinutes <= $clinicEnd) {
            $allSlots[] = $timeStr;
        }
        
        $minute += 15; // 15-minute intervals
        if ($minute >= 60) {
            $hour += floor($minute / 60);
            $minute = $minute % 60;
        }
    }
    
    // Check overlap for each slot
    $availableSlots = [];
    $blockedSlots = [];
    
    foreach ($allSlots as $slot) {
        $slotParts = explode(':', $slot);
        $slotStartMinutes = (int)$slotParts[0] * 60 + (int)$slotParts[1];
        $slotEndMinutes = $slotStartMinutes + $newServiceDuration;
        
        $isBlocked = false;
        
        foreach ($bookedSlots as $booked) {
            // Check overlap: slot overlaps if it starts before booked ends AND ends after booked starts
            if ($slotStartMinutes < $booked['end_minutes'] && $slotEndMinutes > $booked['start_minutes']) {
                $isBlocked = true;
                break;
            }
        }
        
        if ($isBlocked) {
            $blockedSlots[] = $slot;
        } else {
            $availableSlots[] = $slot;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'is_open' => true,
        'service_duration' => $newServiceDuration,
        'available_slots' => $availableSlots,
        'booked_slots' => $blockedSlots
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
