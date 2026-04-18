<?php
/**
 * Delete Appointment Handler
 * - All roles (Admin, Dentist, Staff): Archive appointment (soft delete)
 * - Admin only: Permanent delete from Archive page
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
    $action = $data['action'] ?? 'archive'; // 'archive' or 'permanent'

    if (!$id) {
        throw new Exception('Appointment ID is required');
    }

    // Check if appointment exists
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('Appointment not found');
    }

    // Check if is_archived column exists
    $columnExists = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
        $columnExists = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $columnExists = false;
    }

    if ($action === 'permanent' || $permanent) {
        // Permanent delete - only admin can do this from Archive page
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            throw new Exception('Only admin can permanently delete appointments');
        }
        
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ? AND is_archived = 1");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Appointment permanently deleted'
        ]);
    } else {
        // Archive appointment (soft delete) - all roles can do this
        if ($columnExists) {
            $stmt = $pdo->prepare("UPDATE appointments SET is_archived = 1, deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment archived successfully. You can restore it from the Archive page.'
            ]);
        } else {
            // Column doesn't exist - inform user to run migration
            echo json_encode([
                'success' => false,
                'message' => 'Archive system not configured. Please run database migration: config/add_archive_columns.sql'
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Delete Appointment Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
