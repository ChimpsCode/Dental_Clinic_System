<?php
/**
 * Billing Actions API - Handles billing-related AJAX requests for both Admin and Staff
 */

// Start session first - must be done before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action'];

// Get current user info from session
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role = $_SESSION['role'] ?? '';

// Allow both admin and staff
$allowed_roles = ['admin', 'staff'];
if (!in_array($current_role, $allowed_roles)) {
    // Try to get user_id from POST if session is not available
    $input = json_decode(file_get_contents('php://input'), true);
    $posted_user_id = $input['user_id'] ?? 0;
    if (!$current_user_id && !$posted_user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    if ($posted_user_id) {
        $current_user_id = $posted_user_id;
    }
}

// Get action from GET or POST
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'get_billing':
            // Used by admin_payment.php
            $billing_id = $_GET['billing_id'] ?? 0;
            
            if (!$billing_id) {
                $response['message'] = 'Billing ID is required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone, p.address
                FROM billing b
                LEFT JOIN patients p ON b.patient_id = p.id
                WHERE b.id = ?
            ");
            $stmt->execute([$billing_id]);
            $billing = $stmt->fetch();
            
            if ($billing) {
                $response['success'] = true;
                $response['billing'] = $billing;
            } else {
                $response['message'] = 'Billing record not found';
            }
            break;
            
        case 'get_details':
            // Used by staff_payment.php and staff_billing.php
            $queue_id = $_GET['queue_id'] ?? 0;
            $patient_id = $_GET['patient_id'] ?? 0;
            
            if (!$queue_id && !$patient_id) {
                $response['message'] = 'Queue ID or Patient ID is required';
                echo json_encode($response);
                exit;
            }
            
            // Get queue details
            if ($queue_id) {
                $stmt = $pdo->prepare("
                    SELECT q.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.phone
                    FROM queue q
                    LEFT JOIN patients p ON q.patient_id = p.id
                    WHERE q.id = ?
                ");
                $stmt->execute([$queue_id]);
                $queue = $stmt->fetch();
                
                if ($queue) {
                    // Get or create billing record
                    $stmt = $pdo->prepare("
                        SELECT b.*, COALESCE(b.payment_status, 'unpaid') as payment_status
                        FROM billing b
                        WHERE b.patient_id = ? AND DATE(b.billing_date) = DATE(?)
                    ");
                    $stmt->execute([$queue['patient_id'], $queue['created_at']]);
                    $billing = $stmt->fetch();
                    
                    // Calculate amount based on treatment
                    $amount = 0;
                    if ($billing && $billing['total_amount'] > 0) {
                        $amount = $billing['total_amount'];
                    } else {
                        // Calculate from treatment type
                        $treatment = strtolower(trim($queue['treatment_type'] ?? 'consultation'));
                        $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = ? AND is_active = 1 LIMIT 1");
                        $stmt->execute([$treatment]);
                        $service = $stmt->fetch();
                        $amount = $service['price'] ?? 500; // Default 500 if not found
                    }
                    
                    $response['success'] = true;
                    $response['queue_id'] = $queue['id'];
                    $response['patient_id'] = $queue['patient_id'];
                    $response['patient_name'] = $queue['patient_name'];
                    $response['phone'] = $queue['phone'];
                    $response['treatment_type'] = $queue['treatment_type'];
                    $response['teeth_numbers'] = $queue['teeth_numbers'];
                    $response['queue_status'] = $queue['status'];
                    $response['queue_date'] = date('Y-m-d', strtotime($queue['created_at']));
                    $response['amount'] = $amount;
                    $response['billing_id'] = $billing['id'] ?? 0;
                    $response['payment_status'] = $billing['payment_status'] ?? 'unpaid';
                } else {
                    $response['message'] = 'Queue record not found';
                }
            } else {
                // Only patient_id provided
                $stmt = $pdo->prepare("
                    SELECT CONCAT(first_name, ' ', last_name) as patient_name, phone
                    FROM patients WHERE id = ?
                ");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch();
                
                if ($patient) {
                    $response['success'] = true;
                    $response['patient_id'] = $patient_id;
                    $response['patient_name'] = $patient['patient_name'];
                    $response['phone'] = $patient['phone'];
                    $response['treatment_type'] = 'Consultation';
                    $response['teeth_numbers'] = '';
                    $response['queue_status'] = 'unknown';
                    $response['queue_date'] = date('Y-m-d');
                    $response['amount'] = 500;
                    $response['payment_status'] = 'unpaid';
                } else {
                    $response['message'] = 'Patient not found';
                }
            }
            break;
            
        case 'mark_paid':
            // Used by admin_payment.php, staff_payment.php, staff_billing.php
            $input = json_decode(file_get_contents('php://input'), true);
            
            $billing_id = $input['billing_id'] ?? 0;
            $queue_id = $input['queue_id'] ?? 0;
            $patient_id = $input['patient_id'] ?? 0;
            $amount = $input['amount'] ?? 0;
            $treatment = $input['treatment'] ?? '';
            
            // Get patient_id from queue if not provided
            if (!$patient_id && $queue_id) {
                $stmt = $pdo->prepare("SELECT patient_id FROM queue WHERE id = ?");
                $stmt->execute([$queue_id]);
                $queue = $stmt->fetch();
                $patient_id = $queue['patient_id'] ?? 0;
            }
            
            if (!$patient_id) {
                $response['message'] = 'Patient ID is required';
                echo json_encode($response);
                exit;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Find or create billing record
                if ($billing_id) {
                    // Update existing billing
                    $stmt = $pdo->prepare("
                        UPDATE billing 
                        SET payment_status = 'paid', 
                            paid_amount = COALESCE(paid_amount, 0) + ?, 
                            balance = 0,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$amount, $billing_id]);
                } else {
                    // Check if billing exists for today
                    $stmt = $pdo->prepare("
                        SELECT id FROM billing 
                        WHERE patient_id = ? AND DATE(billing_date) = CURDATE()
                    ");
                    $stmt->execute([$patient_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $billing_id = $existing['id'];
                        $stmt = $pdo->prepare("
                            UPDATE billing 
                            SET payment_status = 'paid', 
                                paid_amount = COALESCE(paid_amount, 0) + ?, 
                                balance = 0,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$amount, $billing_id]);
                    } else {
                        // Create new billing
                        $stmt = $pdo->prepare("
                            INSERT INTO billing (patient_id, total_amount, paid_amount, balance, payment_status, billing_date, due_date, created_at, updated_at)
                            VALUES (?, ?, ?, 0, 'paid', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), NOW(), NOW())
                        ");
                        $stmt->execute([$patient_id, $amount, $amount]);
                        $billing_id = $pdo->lastInsertId();
                    }
                }
                
                // Create payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (billing_id, patient_id, amount, payment_method, payment_date, created_by, created_at)
                    VALUES (?, ?, ?, 'Cash', CURDATE(), ?, NOW())
                ");
                $stmt->execute([$billing_id, $patient_id, $amount, $current_user_id]);
                
                $pdo->commit();
                
                $response['success'] = true;
                $response['message'] = 'Payment marked as paid successfully';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'update_amount':
            // Used by admin_payment.php
            $input = json_decode(file_get_contents('php://input'), true);
            
            $billing_id = $input['billing_id'] ?? 0;
            $new_amount = $input['new_amount'] ?? 0;
            $reason = $input['reason'] ?? '';
            
            if (!$billing_id) {
                $response['message'] = 'Billing ID is required';
                echo json_encode($response);
                exit;
            }
            
            // Get current billing record
            $stmt = $pdo->prepare("SELECT total_amount, paid_amount FROM billing WHERE id = ?");
            $stmt->execute([$billing_id]);
            $billing = $stmt->fetch();
            
            if (!$billing) {
                $response['message'] = 'Billing record not found';
                echo json_encode($response);
                exit;
            }
            
            $paid_amount = $billing['paid_amount'];
            $new_balance = max(0, $new_amount - $paid_amount);
            
            // Update billing amount
            $stmt = $pdo->prepare("
                UPDATE billing 
                SET total_amount = ?, balance = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_amount, $new_balance, $billing_id]);
            
            $response['success'] = true;
            $response['message'] = 'Payment amount updated successfully';
            break;
            
        default:
            $response['message'] = 'Unknown action: ' . $action;
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Billing actions error: " . $e->getMessage());
}

echo json_encode($response);
?>
