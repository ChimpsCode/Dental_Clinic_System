<?php
/**
 * Notification Actions
 * Handle AJAX requests for notifications
 */

// Ensure session for user identification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'mark_read':
        $notificationId = (int)($_POST['id'] ?? 0);
        if ($notificationId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notificationId, $userId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_notifications':
        try {
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'notifications' => $notifications]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_unread_count':
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'delete':
        $notificationId = (int)($_POST['id'] ?? 0);
        if ($notificationId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notificationId, $userId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
