<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function upsertSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function handleImageUpload($pdo, $file, $section) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB.'];
    }

    $uploadDir = __DIR__ . '/../../assets/images/content/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = $section . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    $relativePath = 'assets/images/content/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }

    $stmt = $pdo->prepare("INSERT INTO content_images (file_name, original_name, file_path, file_type, file_size, section, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $fileName,
        $file['name'],
        $relativePath,
        $file['type'],
        $file['size'],
        $section,
        $_SESSION['user_id'] ?? null
    ]);

    return ['success' => true, 'file_path' => $relativePath, 'id' => $pdo->lastInsertId()];
}

// ----- UPLOAD IMAGE -----
if ($action === 'upload_image') {
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image uploaded']);
        exit;
    }
    $section = $_POST['section'] ?? 'general';
    $result = handleImageUpload($pdo, $_FILES['image'], $section);
    echo json_encode($result);
    exit;
}

// ----- SAVE HERO -----
if ($action === 'save_hero') {
    $fields = [
        'welcome_hero_title' => trim($_POST['hero_title'] ?? ''),
        'welcome_hero_subtitle' => trim($_POST['hero_subtitle'] ?? ''),
        'welcome_hero_image' => trim($_POST['hero_image'] ?? ''),
        'welcome_hero_badge_big' => trim($_POST['hero_badge_big'] ?? ''),
        'welcome_hero_badge_small' => trim($_POST['hero_badge_small'] ?? ''),
        'welcome_hero_note1' => trim($_POST['hero_note1'] ?? ''),
        'welcome_hero_note2' => trim($_POST['hero_note2'] ?? ''),
    ];
    foreach ($fields as $key => $value) {
        upsertSetting($pdo, $key, $value);
    }
    echo json_encode(['success' => true, 'message' => 'Home section saved successfully']);
    exit;
}

// ----- SAVE ABOUT -----
if ($action === 'save_about') {
    $stats = $_POST['about_stats'] ?? '[]';
    if (is_string($stats)) {
        $statsDecoded = json_decode($stats, true);
        if ($statsDecoded === null) $statsDecoded = [];
        $stats = json_encode($statsDecoded);
    }

    $fields = [
        'welcome_about_heading' => trim($_POST['about_heading'] ?? ''),
        'welcome_about_paragraph1' => trim($_POST['about_paragraph1'] ?? ''),
        'welcome_about_paragraph2' => trim($_POST['about_paragraph2'] ?? ''),
        'welcome_about_image' => trim($_POST['about_image'] ?? ''),
        'welcome_about_stats' => $stats,
    ];
    foreach ($fields as $key => $value) {
        upsertSetting($pdo, $key, $value);
    }
    echo json_encode(['success' => true, 'message' => 'About Us section saved successfully']);
    exit;
}

// ----- SAVE FAQ -----
if ($action === 'save_faq') {
    $faqs = $_POST['faqs'] ?? '[]';
    if (is_string($faqs)) {
        $decoded = json_decode($faqs, true);
        if ($decoded === null) $decoded = [];
        $faqs = json_encode($decoded);
    }
    upsertSetting($pdo, 'welcome_faqs', $faqs);
    echo json_encode(['success' => true, 'message' => 'FAQ section saved successfully']);
    exit;
}

// ----- SAVE SINGLE SERVICE IMAGE (auto-save on upload) -----
if ($action === 'save_service_image') {
    $serviceId = $_POST['service_id'] ?? '';
    $filePath = $_POST['file_path'] ?? '';
    if (!$serviceId || !$filePath) {
        echo json_encode(['success' => false, 'message' => 'Missing service_id or file_path']);
        exit;
    }
    $current = [];
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'welcome_service_images'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val) $current = json_decode($val, true) ?: [];
    $current[$serviceId] = $filePath;
    upsertSetting($pdo, 'welcome_service_images', json_encode($current));
    echo json_encode(['success' => true, 'message' => 'Service image saved']);
    exit;
}

// ----- SAVE SERVICES -----
if ($action === 'save_services') {
    $featured = $_POST['featured_services'] ?? '[]';
    if (is_string($featured)) {
        $decoded = json_decode($featured, true);
        if ($decoded === null) $decoded = [];
        if (count($decoded) > 3) {
            echo json_encode(['success' => false, 'message' => 'Maximum of 3 featured services allowed.']);
            exit;
        }
        $featured = json_encode(array_map('intval', $decoded));
    }
    upsertSetting($pdo, 'welcome_featured_services', $featured);

    $serviceImages = $_POST['service_images'] ?? '{}';
    if (is_string($serviceImages)) {
        $decoded = json_decode($serviceImages, true);
        if ($decoded === null) $decoded = [];
        $serviceImages = json_encode($decoded);
    }
    upsertSetting($pdo, 'welcome_service_images', $serviceImages);

    echo json_encode(['success' => true, 'message' => 'Services selection saved successfully']);
    exit;
}

// ----- SAVE LOGO -----
if ($action === 'save_logo') {
    $logo = trim($_POST['logo'] ?? '');
    upsertSetting($pdo, 'welcome_logo', $logo);
    echo json_encode(['success' => true, 'message' => 'Logo saved successfully']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
