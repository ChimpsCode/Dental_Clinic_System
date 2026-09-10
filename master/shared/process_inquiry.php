<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

    try {
    require_once '../../config/database.php';
    require_once __DIR__ . '/../../includes/input_sanitize.php';

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (!empty($name) && (empty($first_name) || empty($last_name))) {
        $parts = explode(' ', trim($name), 3);
        $first_name = $parts[0] ?? '';
        $middle_name = $parts[1] ?? '';
        $last_name = $parts[2] ?? '';
    }

    $firstSan = sanitize_name($first_name, true, 80, 'First name');
    $middleSan = sanitize_name($middle_name, false, 80, 'Middle name');
    $lastSan = sanitize_name($last_name, true, 80, 'Last name');
    $messageSan = sanitize_message($_POST['inquiry_message'] ?? '', false, 2000, 'Message');
    foreach ([$firstSan, $middleSan, $lastSan, $messageSan] as $field) {
        if (!$field['ok']) {
            echo json_encode(['success' => false, 'message' => $field['error']]);
            exit();
        }
    }

    $first_name = $firstSan['value'];
    $middle_name = $middleSan['value'];
    $last_name = $lastSan['value'];
    $inquiry_message = $messageSan['value'];
    $contact_info = trim($_POST['contact_info'] ?? '');
    $source = trim((string)($_POST['source'] ?? ''));
    $topic = trim($_POST['topic'] ?? '');
    $status = 'Unread';

    if ($contact_info !== '' && contains_html($contact_info)) {
        echo json_encode(['success' => false, 'message' => 'This field contains invalid characters.']);
        exit();
    }
    if ($source === '' || contains_html($source)) {
        echo json_encode(['success' => false, 'message' => $source === '' ? 'First name, last name, and source are required' : 'This field contains invalid characters.']);
        exit();
    }
    if (contains_html($topic)) {
        echo json_encode(['success' => false, 'message' => 'This field contains invalid characters.']);
        exit();
    }

    if (empty($first_name) || empty($last_name) || empty($source)) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and source are required']);
        exit();
    }
    
    // Convert topic to service_id if it's text (topic is now a foreign key to services.id)
    $service_id = null;
    if (!empty($topic)) {
        if (is_numeric($topic)) {
            // Already an ID
            $service_id = (int)$topic;
        } else {
            // It's a service name, look up the ID
            $stmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
            $stmt->execute([$topic]);
            $result = $stmt->fetch();
            if ($result) {
                $service_id = $result['id'];
            } else {
                // Service not found, try to find partial match
                $stmt = $pdo->prepare("SELECT id FROM services WHERE name LIKE ? LIMIT 1");
                $stmt->execute(['%' . $topic . '%']);
                $result = $stmt->fetch();
                if ($result) {
                    $service_id = $result['id'];
                }
            }
        }
    }
    
    // Insert inquiry with service_id as topic
    $stmt = $pdo->prepare("INSERT INTO inquiries (first_name, middle_name, last_name, contact_info, source, topic, inquiry_message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$first_name, $middle_name, $last_name, $contact_info, $source, $service_id, $inquiry_message, $status]);
    
    echo json_encode(['success' => true, 'message' => 'Inquiry added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>