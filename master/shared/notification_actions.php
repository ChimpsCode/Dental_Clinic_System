<?php
/**
 * Notification Actions
 * Handle AJAX requests for notifications
 */

// Ensure session for user identification
require_once __DIR__ . '/../../includes/session_bootstrap.php';

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'mark_panel_seen') {
    $seen = trim((string)($_POST['latest_created_at'] ?? ''));
    $_SESSION['notif_panel_seen_at'] = $seen !== '' ? $seen : date('Y-m-d H:i:s');
    session_write_close();
    echo json_encode(['success' => true]);
    exit;
}

session_write_close();

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
            $stmt = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS cnt,
                    MAX(created_at) AS latest_at
                FROM notifications
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $count = (int)($row['cnt'] ?? 0);
            echo json_encode([
                'success' => true,
                'count' => $count,
                'latest_created_at' => $row['latest_at'] ?? null,
            ]);
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
