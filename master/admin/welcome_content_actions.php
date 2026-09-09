<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

$welcomeRole = strtolower((string)($_SESSION['role'] ?? ''));
if (empty($_SESSION['user_id']) || $welcomeRole !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';
require_once __DIR__ . '/../../includes/safe_image_upload.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function upsertSetting($pdo, $key, $value) {
    auditedUpsertSetting(
        $pdo,
        (string)$key,
        $value === null ? null : (string)$value,
        'Updated welcome page content',
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'unknown',
        $_SESSION['role'] ?? 'admin'
    );
}

function handleImageUpload($pdo, $file, $section) {
    $check = validate_safe_image_upload($file, 5 * 1024 * 1024);
    if (!$check['ok']) {
        return ['success' => false, 'message' => $check['error'] === 'File size is invalid or too large.' ? 'File too large. Maximum 5MB.' : $check['error']];
    }

    $section = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$section);
    if ($section === '') {
        $section = 'general';
    }

    $uploadDir = __DIR__ . '/../../assets/images/content/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = $section . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $check['ext'];
    $filePath = $uploadDir . $fileName;
    $relativePath = 'assets/images/content/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }

    $stmt = $pdo->prepare("INSERT INTO content_images (file_name, original_name, file_path, file_type, file_size, section, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $fileName,
        basename((string)($file['name'] ?? $fileName)),
        $relativePath,
        $check['mime'],
        $file['size'],
        $section,
        $_SESSION['user_id'] ?? null
    ]);
    $contentImageId = (int)$pdo->lastInsertId();

    logAudit(
        $pdo,
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'unknown',
        $_SESSION['role'] ?? 'admin',
        'create',
        'content',
        'Uploaded welcome page image for ' . $section . ': ' . $fileName,
        $contentImageId,
        'content_images',
        null,
        json_encode(['file_path' => $relativePath, 'section' => $section], JSON_UNESCAPED_UNICODE)
    );

    return ['success' => true, 'file_path' => $relativePath, 'id' => $contentImageId];
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
    $blocks = $_POST['hero_title_blocks'] ?? '[]';
    if (is_string($blocks)) {
        $decoded = json_decode($blocks, true);
        if ($decoded === null) $decoded = [];
        $blocks = json_encode($decoded);
    }
    $fields = [
        'welcome_hero_title_blocks' => $blocks,
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

// ----- SAVE FOOTER -----
if ($action === 'save_footer') {
    $fields = [
        'welcome_footer_tagline' => trim($_POST['footer_tagline'] ?? ''),
        'welcome_footer_facebook' => trim($_POST['footer_facebook'] ?? ''),
        'welcome_footer_messenger' => trim($_POST['footer_messenger'] ?? ''),
    ];
    foreach ($fields as $key => $value) {
        upsertSetting($pdo, $key, $value);
    }
    echo json_encode(['success' => true, 'message' => 'Footer saved successfully']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
