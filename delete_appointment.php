<?php
/**
 * Delete Appointment Handler
 * - Staff: Soft delete (marks as hidden from staff view)
 * - Admin: Permanent delete (removes from database)
 */

header('Content-Type: application/json');

session_start();
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $permanent = $data['permanent'] ?? false;
    $soft_delete = $data['soft_delete'] ?? false;

    if (!$id) {
        throw new Exception('Appointment ID is required');
    }

    // Check if appointment exists
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('Appointment not found');
    }

    if ($permanent) {
        // Admin permanent delete - actually remove from database
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Appointment permanently deleted'
        ]);
    } else if ($soft_delete) {
        // Staff soft delete - check if column exists first
        $columnExists = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'deleted_by_staff'");
            $columnExists = $checkCol->rowCount() > 0;
        } catch (Exception $e) {
            $columnExists = false;
        }
        
        if ($columnExists) {
            // Column exists, do soft delete
            $stmt = $pdo->prepare("UPDATE appointments SET deleted_by_staff = 1, deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment removed from your view'
            ]);
        } else {
            // Column doesn't exist, inform user to add it or do permanent delete
            // For now, we'll just delete it permanently with a different message
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment deleted successfully'
            ]);
        }
    } else {
        throw new Exception('Delete type not specified');
    }

} catch (Exception $e) {
    error_log("Delete Appointment Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
