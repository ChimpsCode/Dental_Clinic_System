<?php
/**
 * Settings Actions - Handle saving and loading system settings
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

require_once '../../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_settings') {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($action === 'save_settings') {
    $clinicName = trim($_POST['clinic_name'] ?? '');
    $clinicAddress = trim($_POST['clinic_address'] ?? '');
    $clinicPhone = trim($_POST['clinic_phone'] ?? '');
    $clinicEmail = trim($_POST['clinic_email'] ?? '');
    
    $settingsToSave = [
        'clinic_name' => $clinicName,
        'clinic_address' => $clinicAddress,
        'clinic_phone' => $clinicPhone,
        'clinic_email' => $clinicEmail
    ];
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    foreach ($settingsToSave as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    exit;
}

if ($action === 'save_business_hours') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $businessHours = $input['business_hours'] ?? null;
    
    if (!$businessHours) {
        echo json_encode(['success' => false, 'message' => 'No business hours data provided']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['business_hours', json_encode($businessHours)]);
    
    echo json_encode(['success' => true, 'message' => 'Business hours saved successfully']);
    exit;
}

if ($action === 'get_business_hours') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'business_hours'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $hours = json_decode($result['setting_value'], true);
        echo json_encode(['success' => true, 'business_hours' => $hours]);
    } else {
        echo json_encode(['success' => true, 'business_hours' => []]);
    }
    exit;
}

if ($action === 'save_unavailable_date') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $date = $input['date'] ?? '';
    $status = $input['status'] ?? 'closed';
    $note = $input['note'] ?? '';
    $customOpen = $input['custom_open'] ?? null;
    $customClose = $input['custom_close'] ?? null;
    
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    
    $existingIndex = array_search($date, array_column($unavailableDates, 'date'));
    
    $newEntry = [
        'date' => $date,
        'status' => $status,
        'note' => $note
    ];
    
    if ($status === 'custom') {
        $newEntry['custom_open'] = $customOpen;
        $newEntry['custom_close'] = $customClose;
    }
    
    if ($existingIndex !== false) {
        $unavailableDates[$existingIndex] = $newEntry;
    } else {
        $unavailableDates[] = $newEntry;
    }
    
    usort($unavailableDates, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['unavailable_dates', json_encode($unavailableDates)]);
    
    echo json_encode(['success' => true, 'message' => 'Date saved successfully']);
    exit;
}

if ($action === 'delete_unavailable_date') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $date = $input['date'] ?? '';
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'No date specified']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    
    $unavailableDates = array_values(array_filter($unavailableDates, function($entry) use ($date) {
        return $entry['date'] !== $date;
    }));
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['unavailable_dates', json_encode($unavailableDates)]);
    
    echo json_encode(['success' => true, 'message' => 'Date removed successfully']);
    exit;
}

if ($action === 'bulk_add_unavailable_dates') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $status = $input['status'] ?? 'closed';
    $note = $input['note'] ?? '';
    $customOpen = $input['custom_open'] ?? null;
    $customClose = $input['custom_close'] ?? null;
    
    if (empty($startDate) || empty($endDate)) {
        echo json_encode(['success' => false, 'message' => 'Start and end dates are required']);
        exit;
    }
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    if ($start > $end) {
        echo json_encode(['success' => false, 'message' => 'Start date must be before end date']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    $existingDates = array_column($unavailableDates, 'date');
    
    $added = 0;
    $current = clone $start;
    
    while ($current <= $end) {
        $dateStr = $current->format('Y-m-d');
        
        if (!in_array($dateStr, $existingDates)) {
            $newEntry = [
                'date' => $dateStr,
                'status' => $status,
                'note' => $note
            ];
            
            if ($status === 'custom') {
                $newEntry['custom_open'] = $customOpen;
                $newEntry['custom_close'] = $customClose;
            }
            
            $unavailableDates[] = $newEntry;
            $added++;
        }
        
        $current->modify('+1 day');
    }
    
    usort($unavailableDates, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['unavailable_dates', json_encode($unavailableDates)]);
    
    echo json_encode(['success' => true, 'message' => "Added $added date(s) successfully"]);
    exit;
}

if ($action === 'get_unavailable_dates') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $unavailableDates = json_decode($row['setting_value'], true);
        echo json_encode(['success' => true, 'unavailable_dates' => $unavailableDates]);
    } else {
        echo json_encode(['success' => true, 'unavailable_dates' => []]);
    }
    exit;
}

if ($action === 'save_auto_cancel_settings') {
    $enabled = ($_POST['auto_cancel_enabled'] ?? '0') === '1' ? '1' : '0';
    $minutes = (int)($_POST['auto_cancel_minutes'] ?? 15);
    if (!in_array($minutes, [10, 15, 20, 30])) {
        $minutes = 15;
    }

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['auto_cancel_enabled', $enabled]);
    $stmt->execute(['auto_cancel_minutes', (string)$minutes]);

    echo json_encode(['success' => true, 'message' => 'Auto-cancel settings saved successfully']);
    exit;
}

if ($action === 'save_security_settings') {
    $sessionTimeout = (int)($_POST['session_timeout'] ?? 30);
    if (!in_array($sessionTimeout, [15, 30, 60, 120])) {
        $sessionTimeout = 30;
    }
    $twoFactorAuth = ($_POST['two_factor_auth'] ?? '0') === '1' ? '1' : '0';

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['session_timeout', (string)$sessionTimeout]);
    $stmt->execute(['two_factor_auth', $twoFactorAuth]);

    echo json_encode(['success' => true, 'message' => 'Security settings saved successfully']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
