<?php
session_start();
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $date = $_GET['date'] ?? '';
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    $exclude_inquiry_id = isset($_GET['exclude_inquiry_id']) ? (int)$_GET['exclude_inquiry_id'] : 0;
    $dentist_id = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : 0;
    
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
    $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $dayName = $dayNames[$dayOfWeek];
    
    require_once '../../config/database.php';

    // Ensure dentist_unavailable table exists (inline migration)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS dentist_unavailable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dentist_id INT NOT NULL,
            unavailable_date DATE NOT NULL,
            is_full_day TINYINT(1) DEFAULT 1,
            start_time TIME DEFAULT NULL,
            end_time TIME DEFAULT NULL,
            reason VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (dentist_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_dentist_date (dentist_id, unavailable_date)
        )");
    } catch (Exception $e) {}

    // Ensure schedules table has required columns
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM schedules LIKE 'start_time'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE schedules ADD COLUMN start_time TIME DEFAULT '08:00:00'");
            $pdo->exec("ALTER TABLE schedules ADD COLUMN end_time TIME DEFAULT '17:00:00'");
            $pdo->exec("ALTER TABLE schedules ADD COLUMN is_available TINYINT(1) DEFAULT 1");
        }
    } catch (Exception $e) {}

    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Load settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_hours', 'unavailable_dates')");
    $settingsRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $businessHours = isset($settingsRows['business_hours']) ? json_decode($settingsRows['business_hours'], true) : [];
    $unavailableDates = isset($settingsRows['unavailable_dates']) ? json_decode($settingsRows['unavailable_dates'], true) : [];
    
    // Default business hours
    if (!$businessHours) {
        $businessHours = [
            'monday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
            'tuesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
            'wednesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
            'thursday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
            'friday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'is_open' => true],
            'sunday' => ['open' => '', 'close' => '', 'is_open' => false]
        ];
    }
    if (!$unavailableDates) $unavailableDates = [];
    
    // Check if date is in unavailable dates
    $unavailableEntry = null;
    foreach ($unavailableDates as $entry) {
        if ($entry['date'] === $date) {
            $unavailableEntry = $entry;
            break;
        }
    }
    
    // If date is explicitly set to closed or holiday
    if ($unavailableEntry && in_array($unavailableEntry['status'], ['closed', 'holiday'])) {
        $note = $unavailableEntry['note'] ?? '';
        $message = 'Clinic is closed';
        if ($note) $message .= ' - ' . $note;
        echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => $message]);
        exit;
    }
    
    // Get day hours from business_hours
    $dayHours = $businessHours[$dayName] ?? null;
    
    // If date has custom hours, use those
    if ($unavailableEntry && $unavailableEntry['status'] === 'custom') {
        $dayHours = [
            'open' => $unavailableEntry['custom_open'] ?? '08:00',
            'close' => $unavailableEntry['custom_close'] ?? '17:00',
            'is_open' => true
        ];
    }
    
    // If date is half day AM
    if ($unavailableEntry && $unavailableEntry['status'] === 'half_am') {
        $dayHours = [
            'open' => $dayHours['open'] ?? '08:00',
            'close' => '12:00',
            'is_open' => true,
            'is_half_day' => true
        ];
    }
    
    // If date is half day PM
    if ($unavailableEntry && $unavailableEntry['status'] === 'half_pm') {
        $dayHours = [
            'open' => '13:00',
            'close' => $dayHours['close'] ?? '17:00',
            'is_open' => true,
            'is_half_day' => true
        ];
    }
    
    // If day is closed
    if (!$dayHours || !$dayHours['is_open']) {
        echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Clinic is closed on ' . ucfirst($dayName) . 's']);
        exit;
    }
    
    // Parse clinic open/close times (needed before dentist check)
    $openParts = explode(':', $dayHours['open']);
    $closeParts = explode(':', $dayHours['close']);
    $clinicOpen = (int)$openParts[0] * 60 + (int)$openParts[1];
    $clinicClose = (int)$closeParts[0] * 60 + (int)$closeParts[1];
    
    // If no specific dentist selected, check if ANY dentist is available on this day
    if (!$dentist_id) {
        try {
            $dentistStmt = $pdo->query("SELECT id FROM users WHERE role = 'Dentist' AND (is_archived = 0 OR is_archived IS NULL)");
            $allDentists = $dentistStmt->fetchAll(PDO::FETCH_COLUMN);
            $availableDentistFound = false;

            foreach ($allDentists as $did) {
                // Check weekly schedule
                $schedStmt = $pdo->prepare("SELECT start_time, end_time, is_available FROM schedules WHERE doctor_id = ? AND available_day = ?");
                $dayFull = ucfirst($dayName);
                $schedStmt->execute([$did, $dayFull]);
                $sched = $schedStmt->fetch(PDO::FETCH_ASSOC);

                $dentistAvailable = true;
                $dOpen = $clinicOpen;
                $dClose = $clinicClose;

                if ($sched) {
                    if (!(int)$sched['is_available']) {
                        $dentistAvailable = false;
                    } else {
                        $dOpenParts = explode(':', $sched['start_time']);
                        $dCloseParts = explode(':', $sched['end_time']);
                        $dOpen = (int)$dOpenParts[0] * 60 + (int)$dOpenParts[1];
                        $dClose = (int)$dCloseParts[0] * 60 + (int)$dCloseParts[1];
                    }
                }

                // Check date-specific override (always runs, takes precedence over weekly schedule)
                $ovStmt = $pdo->prepare("SELECT is_full_day, start_time, end_time FROM dentist_unavailable WHERE dentist_id = ? AND unavailable_date = ?");
                $ovStmt->execute([$did, $date]);
                $ov = $ovStmt->fetch(PDO::FETCH_ASSOC);
                if ($ov) {
                    if ((int)$ov['is_full_day']) {
                        $dentistAvailable = false;
                    } else {
                        $dentistAvailable = true;
                        if ($ov['start_time']) {
                            $ovOpenParts = explode(':', $ov['start_time']);
                            $dOpen = (int)$ovOpenParts[0] * 60 + (int)$ovOpenParts[1];
                        }
                        if ($ov['end_time']) {
                            $ovCloseParts = explode(':', $ov['end_time']);
                            $dClose = (int)$ovCloseParts[0] * 60 + (int)$ovCloseParts[1];
                        }
                    }
                }

                if ($dentistAvailable && $dOpen < $dClose) {
                    $availableDentistFound = true;
                    break;
                }
            }

            if (!empty($allDentists) && !$availableDentistFound) {
                echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'No dentists available on this day']);
                exit;
            }
        } catch (Exception $e) {
            // Fallback: allow booking (ignore dentist check)
        }
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
    $apptSql = "
        SELECT 
            TIME_FORMAT(a.appointment_time, '%H:%i') as start_time,
            COALESCE(a.duration_minutes, s.duration_minutes, 30) as duration_minutes
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date = ?
          AND COALESCE(a.is_archived, 0) = 0
          AND LOWER(COALESCE(a.status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show', 'pending approval')
    ";
    $apptParams = [$date];
    if ($dentist_id) {
        $apptSql .= " AND (a.dentist_id IS NULL OR a.dentist_id = ?)";
        $apptParams[] = $dentist_id;
    }
    $stmt = $pdo->prepare($apptSql);
    $stmt->execute($apptParams);
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
    
    // If a specific dentist is selected, intersect clinic hours with dentist's schedule
    if ($dentist_id) {
        $stmt = $pdo->prepare("SELECT start_time, end_time, is_available FROM schedules WHERE doctor_id = ? AND available_day = ?");
        $dayFull = ucfirst($dayName);
        $stmt->execute([$dentist_id, $dayFull]);
        $dentistSched = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dentistSched) {
            if (!(int)$dentistSched['is_available']) {
                echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Dentist is not available on this day']);
                exit;
            }
            $dOpenParts = explode(':', $dentistSched['start_time']);
            $dCloseParts = explode(':', $dentistSched['end_time']);
            $dentistOpen = (int)$dOpenParts[0] * 60 + (int)$dOpenParts[1];
            $dentistClose = (int)$dCloseParts[0] * 60 + (int)$dCloseParts[1];
            
            // Intersect: later of the two opens, earlier of the two closes
            if ($clinicOpen < $dentistOpen) $clinicOpen = $dentistOpen;
            if ($clinicClose > $dentistClose) $clinicClose = $dentistClose;
            
            if ($clinicOpen >= $clinicClose) {
                echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Dentist is not available during clinic hours on this day']);
                exit;
            }
        }

        // Check date-specific override for this dentist
        $ovStmt = $pdo->prepare("SELECT is_full_day, start_time, end_time FROM dentist_unavailable WHERE dentist_id = ? AND unavailable_date = ?");
        $ovStmt->execute([$dentist_id, $date]);
        $ov = $ovStmt->fetch(PDO::FETCH_ASSOC);
        if ($ov) {
            if ((int)$ov['is_full_day']) {
                echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Dentist is not available on this date']);
                exit;
            }
            // Override with custom hours — intersect with current window
            if ($ov['start_time']) {
                $ovOpenParts = explode(':', $ov['start_time']);
                $ovOpen = (int)$ovOpenParts[0] * 60 + (int)$ovOpenParts[1];
                if ($clinicOpen < $ovOpen) $clinicOpen = $ovOpen;
            }
            if ($ov['end_time']) {
                $ovCloseParts = explode(':', $ov['end_time']);
                $ovClose = (int)$ovCloseParts[0] * 60 + (int)$ovCloseParts[1];
                if ($clinicClose > $ovClose) $clinicClose = $ovClose;
            }
            if ($clinicOpen >= $clinicClose) {
                echo json_encode(['success' => true, 'date' => $date, 'is_open' => false, 'message' => 'Dentist has limited hours on this date']);
                exit;
            }
        }
    }
    
    // Generate potential time slots based on business hours
    $allSlots = [];
    $currentMinutes = $clinicOpen;
    
    while ($currentMinutes < $clinicClose) {
        $hour = floor($currentMinutes / 60);
        $minute = $currentMinutes % 60;
        $timeStr = sprintf('%02d:%02d', $hour, $minute);
        $slotEndMinutes = $currentMinutes + $newServiceDuration;
        
        // Check if this slot would extend past clinic hours
        if ($slotEndMinutes <= $clinicClose) {
            $allSlots[] = $timeStr;
        }
        
        $currentMinutes += 15; // 15-minute intervals
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
    
    // Filter out past time slots if the date is today
    if ($date === date('Y-m-d')) {
        $nowMinutes = (int)date('G') * 60 + (int)date('i');
        $availableSlots = array_values(array_filter($availableSlots, function($s) use ($nowMinutes) {
            $parts = explode(':', $s);
            return (int)$parts[0] * 60 + (int)$parts[1] > $nowMinutes;
        }));
        $blockedSlots = array_values(array_filter($blockedSlots, function($s) use ($nowMinutes) {
            $parts = explode(':', $s);
            return (int)$parts[0] * 60 + (int)$parts[1] > $nowMinutes;
        }));
    }
    
    $response = [
        'success' => true,
        'date' => $date,
        'is_open' => true,
        'service_duration' => $newServiceDuration,
        'available_slots' => $availableSlots,
        'booked_slots' => $blockedSlots,
        'dentist_id' => $dentist_id,
        'business_hours' => [
            'open' => $dayHours['open'],
            'close' => $dayHours['close']
        ]
    ];
    
    if (!empty($unavailableEntry)) {
        $response['date_status'] = $unavailableEntry['status'];
        $response['date_note'] = $unavailableEntry['note'] ?? '';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
