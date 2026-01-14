<?php
/**
 * Update Treatment Plan Progress
 */

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';

try {
    $plan_id = $_POST['progress_plan_id'] ?? null;
    $completed_sessions = $_POST['update_completed'] ?? 0;
    $next_session_date = $_POST['update_next_date'] ?? null;
    $session_notes = $_POST['session_notes'] ?? '';
    
    if (!$plan_id) {
        echo json_encode(['success' => false, 'message' => 'Plan ID is required']);
        exit();
    }
    
    // Get current plan to check total sessions
    $stmt = $pdo->prepare("SELECT total_sessions, notes FROM treatment_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Treatment plan not found']);
        exit();
    }
    
    // Determine status based on completed sessions
    $status = 'active';
    if ($completed_sessions >= $plan['total_sessions']) {
        $status = 'completed';
    } elseif ($completed_sessions > 0) {
        $status = 'in_progress';
    }
    
    // Append session notes to existing notes
    $notes = $plan['notes'] ?? '';
    if (!empty($session_notes)) {
        $notes .= "\n\n--- " . date('M d, Y H:i') . " ---\n" . $session_notes;
    }
    
    // Update the plan
    $stmt = $pdo->prepare("
        UPDATE treatment_plans SET 
            completed_sessions = ?,
            status = ?,
            next_session_date = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $result = $stmt->execute([
        $completed_sessions,
        $status,
        $next_session_date ?: null,
        $notes,
        $plan_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Progress updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
