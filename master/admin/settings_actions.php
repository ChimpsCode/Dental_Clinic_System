<?php
/**
 * Settings Actions - Handle saving and loading system settings
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$action = $_POST['action'] ?? '';

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

echo json_encode(['success' => false, 'message' => 'Invalid action']);