<?php
/**
 * Billing Actions API - Handles billing-related AJAX requests for both Admin and Staff
 */

// Start session first - must be done before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action'];

// Get current user info from session with fallback
$current_user_id = $_SESSION['user_id'] ?? null;
$current_username = $_SESSION['username'] ?? null;
$current_role = $_SESSION['role'] ?? null;

// Skip authentication check for get_details - it's just viewing
$skipAuth = true;

// Get action from GET or POST
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Allow viewing details without full login
if ($action === 'get_details') {
    $skipAuth = false;
}

// Allow both admin and staff - also check for dentist
$allowed_roles = ['admin', 'staff', 'dentist'];
if ($skipAuth && !in_array($current_role, $allowed_roles)) {
    // Try to get user_id from POST if session is not available
    $input = json_decode(file_get_contents('php://input'), true);
    $posted_user_id = $input['user_id'] ?? null;
    if (!$current_user_id && !$posted_user_id) {
        // Check for admin bypass in POST
        if (!empty($input['username']) && !empty($input['password'])) {
            // Try direct database login for admin
            require_once __DIR__ . '/../../config/database.php';
            $directUser = $input['username'];
            $directPass = $input['password'];
            
            try {
                $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE username = ? AND password = ?");
                $stmt->execute([$directUser, $directPass]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Set session variables for this request
                    $current_user_id = $user['id'];
                    $current_username = $user['username'];
                    $current_role = $user['role'];
                }
            } catch (Exception $e) {
                error_log("Direct login attempt failed: " . $e->getMessage());
            }
        }
        
        if (!$current_user_id && !$posted_user_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }
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

// Include audit helper
require_once __DIR__ . '/../../includes/audit_helper.php';

// Ensure printed_at column exists for logging prints
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM billing LIKE 'printed_at'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE billing ADD COLUMN printed_at DATETIME NULL AFTER due_date");
    }
} catch (Exception $e) {
    // non-fatal; continue without blocking
}

// Helper function to get patient name
function getPatientName($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $result = $stmt->fetch();
    return $result['full_name'] ?? 'Unknown Patient';
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
                // Log audit - read billing record
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'read', 'billing', 'Viewed billing record: INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT), $billing_id, 'billing', null, null);
                
                $response['success'] = true;
                $response['billing'] = $billing;
            } else {
                $response['message'] = 'Billing record not found';
            }
            break;

        case 'mark_printed':
            // Log that an invoice was printed
            $billing_id = (int)($input['billing_id'] ?? 0);
            if ($billing_id <= 0) {
                $response['message'] = 'Billing ID is required';
                break;
            }

            // Update printed timestamp
            $stmt = $pdo->prepare("UPDATE billing SET printed_at = NOW() WHERE id = ?");
            $stmt->execute([$billing_id]);

            logAudit(
                $pdo,
                $current_user_id,
                $current_username,
                $current_role,
                'print',
                'billing',
                'Printed invoice INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT),
                $billing_id,
                'billing',
                null,
                null
            );

            $response = ['success' => true, 'message' => 'Print logged'];
            break;
            
        case 'get_details':
            // Used by staff_payment.php, staff_billing.php, dentist_appointments.php
            $queue_id = $_GET['queue_id'] ?? 0;
            $patient_id = $_GET['patient_id'] ?? 0;
            $appointment_id = $_GET['appointment_id'] ?? 0;
            
            if (!$queue_id && !$patient_id && !$appointment_id) {
                $response['message'] = 'Queue ID, Patient ID, or Appointment ID is required';
                echo json_encode($response);
                exit;
            }
            
            // Handle appointment_id
            if ($appointment_id && !$queue_id) {
                $stmt = $pdo->prepare("
                    SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.phone
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch();
                
                if ($appointment) {
                    $patient_id = $appointment['patient_id'];
                    $queue_id = $appointment_id; // Use appointment_id as fallback
                    
                    // Get billing for this patient on appointment date
                    $stmt = $pdo->prepare("
                        SELECT b.*, COALESCE(b.payment_status, 'unpaid') as payment_status
                        FROM billing b
                        WHERE b.patient_id = ? AND DATE(b.billing_date) = DATE(?)
                    ");
                    $stmt->execute([$patient_id, $appointment['appointment_date']]);
                    $billing = $stmt->fetch();
                    
                    $treatment = strtolower(trim($appointment['treatment'] ?? 'consultation'));
                    $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$treatment]);
                    $service = $stmt->fetch();
                    $amount = $billing ? ($billing['total_amount'] ?? 0) : ($service['price'] ?? 500);
                    
                    $response['success'] = true;
                    $response['appointment_id'] = $appointment_id;
                    $response['patient_id'] = $patient_id;
                    $response['patient_name'] = $appointment['patient_name'];
                    $response['phone'] = $appointment['phone'];
                    $response['treatment_type'] = $appointment['treatment'];
                    $response['queue_date'] = date('Y-m-d', strtotime($appointment['appointment_date']));
                    $response['amount'] = $amount;
                    $response['billing_id'] = $billing['id'] ?? 0;
                    $response['payment_status'] = $billing['payment_status'] ?? 'unpaid';
                    
                    echo json_encode($response);
                    exit;
                }
            }
            
            // Get queue details (existing logic)
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
                    // Log audit - read billing details
                    logAudit($pdo, $current_user_id, $current_username, $current_role, 'read', 'billing', 'Viewed payment details for queue: Q-' . str_pad($queue_id, 4, '0', STR_PAD_LEFT), $queue_id, 'queue', null, null);
                    
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
                    // Log audit - read billing details
                    logAudit($pdo, $current_user_id, $current_username, $current_role, 'read', 'billing', 'Viewed payment details for patient ID: ' . $patient_id, $patient_id, 'patients', null, null);
                    
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
            $queueDate = date('Y-m-d'); // default to today
            $paymentDate = date('Y-m-d'); // actual payment date (today)
            
            // If queue provided, grab its date so billing_date aligns with queue_date for joins
            if ($queue_id) {
                $stmt = $pdo->prepare("SELECT patient_id, DATE(created_at) as created_date FROM queue WHERE id = ?");
                $stmt->execute([$queue_id]);
                $queueRow = $stmt->fetch();
                if ($queueRow) {
                    $queueDate = $queueRow['created_date'];
                    // prefer patient_id from queue to avoid mismatch
                    if (!$patient_id) {
                        $patient_id = $queueRow['patient_id'];
                    }
                }
            }
            
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
            
            $patient_name = getPatientName($pdo, $patient_id);
            $old_status = 'unknown';
            $new_status = 'paid';
            
            $pdo->beginTransaction();
            
            try {
                // Find or create billing record
                if ($billing_id) {
                    // Get old status before update - use payment_status_id
                    $stmt = $pdo->prepare("SELECT payment_status_id FROM billing WHERE id = ?");
                    $stmt->execute([$billing_id]);
                    $old_status_id = $stmt->fetchColumn();
                    $old_status = $old_status_id == 1 ? 'paid' : 'unpaid';
                    
                    // Check billing columns
                        $stmt = $pdo->query("DESCRIBE billing");
                        $bcols = array_column($stmt->fetchAll(), 'Field');
                        $bupdates = [];
                        if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = 1"; }
                        if (in_array('paid_amount', $bcols)) { $bupdates[] = "paid_amount = COALESCE(paid_amount, 0) + " . $amount; }
                        if (in_array('balance', $bcols)) { $bupdates[] = "balance = 0"; }
                        if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
                        if (!empty($bupdates)) {
                            $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                            $stmt->execute([$billing_id]);
                        }
                } else {
                    // Check if billing exists for today
                    $stmt = $pdo->prepare("
                        SELECT id, payment_status_id FROM billing 
                        WHERE patient_id = ? AND DATE(billing_date) = ?
                    ");
                    $stmt->execute([$patient_id, $queueDate]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing - use column checks
                        $billing_id = $existing['id'];
                        $old_status = $existing['payment_status_id'] == 1 ? 'paid' : 'unpaid';
                        $stmt = $pdo->query("DESCRIBE billing");
                        $bcols = array_column($stmt->fetchAll(), 'Field');
                        $bupdates = [];
                        if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = 1"; }
                        if (in_array('paid_amount', $bcols)) { $bupdates[] = "paid_amount = COALESCE(paid_amount, 0) + " . $amount; }
                        if (in_array('balance', $bcols)) { $bupdates[] = "balance = 0"; }
                        if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
                        if (!empty($bupdates)) {
                            $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                            $stmt->execute([$billing_id]);
                        }
                    } else {
                        // Create new billing - column check
                        $stmt = $pdo->query("DESCRIBE billing");
                        $bcols = array_column($stmt->fetchAll(), 'Field');
                        $bcolsList = [];
                        $bvals = [];
                        if (in_array('patient_id', $bcols)) { $bcolsList[] = "patient_id"; $bvals[] = $patient_id; }
                        if (in_array('total_amount', $bcols)) { $bcolsList[] = "total_amount"; $bvals[] = $amount; }
                        if (in_array('paid_amount', $bcols)) { $bcolsList[] = "paid_amount"; $bvals[] = $amount; }
                        if (in_array('balance', $bcols)) { $bcolsList[] = "balance"; $bvals[] = 0; }
                        if (in_array('payment_status_id', $bcols)) { $bcolsList[] = "payment_status_id"; $bvals[] = 1; }
                        if (in_array('billing_date', $bcols)) { $bcolsList[] = "billing_date"; $bvals[] = $queueDate; }
                        if (in_array('due_date', $bcols)) { $bcolsList[] = "due_date"; $bvals[] = date('Y-m-d', strtotime($queueDate . ' +7 days')); }
                        if (in_array('created_at', $bcols)) { $bcolsList[] = "created_at"; $bvals[] = 'NOW()'; }
                        if (in_array('updated_at', $bcols)) { $bcolsList[] = "updated_at"; $bvals[] = 'NOW()'; }
                        if (!empty($bcolsList)) {
                            $sql = "INSERT INTO billing (" . implode(', ', $bcolsList) . ") VALUES (" . implode(', ', array_fill(0, count($bvals), '?')) . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($bvals);
                            $billing_id = $pdo->lastInsertId();
                        }
                    }
                }
                
                // Create payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (billing_id, patient_id, amount, payment_method, payment_date, created_by, created_at)
                    VALUES (?, ?, ?, 'Cash', ?, ?, NOW())
                ");
                $stmt->execute([$billing_id, $patient_id, $amount, $paymentDate, $current_user_id]);
                $payment_id = $pdo->lastInsertId();
                
                // Update queue status to completed if queue_id exists
                if ($queue_id) {
                    $stmt = $pdo->query("DESCRIBE queue");
                    $qcols = array_column($stmt->fetchAll(), 'Field');
                    $qupdates = [];
                    if (in_array('status', $qcols)) { $qupdates[] = "status = 'completed'"; }
                    if (in_array('updated_at', $qcols)) { $qupdates[] = "updated_at = NOW()"; }
                    if (!empty($qupdates)) {
                        $stmt = $pdo->prepare("UPDATE queue SET " . implode(', ', $qupdates) . " WHERE id = ? AND status = 'pending_payment'");
                        $stmt->execute([$queue_id]);
                    }
                }
                
                $pdo->commit();
                
                // Log audit - payment recorded
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'payment', 'billing', 'Payment recorded: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name . ' (INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT) . ')', $billing_id, 'billing', $old_status, 'paid');
                
                // Log audit - payment record created
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'create', 'payments', 'Created payment record: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name, $payment_id, 'payments', null, '₱' . number_format($amount, 2));
                
                $response['success'] = true;
                $response['message'] = 'Payment marked as paid successfully';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'update_amount':
            // Used by admin_payment.php, dentist_dashboard.php
            $input = json_decode(file_get_contents('php://input'), true);
            
            $billing_id = $input['billing_id'] ?? 0;
            $patient_id = $input['patient_id'] ?? 0;
            $queue_id = $input['queue_id'] ?? 0;
            $new_amount = $input['new_amount'] ?? 0;
            $reason = $input['reason'] ?? '';
            
            // If no billing_id, try to find or create one
            if (!$billing_id) {
                if (!$patient_id && $queue_id) {
                    // Get patient_id from queue
                    $stmt = $pdo->prepare("SELECT patient_id FROM queue WHERE id = ?");
                    $stmt->execute([$queue_id]);
                    $patient_id = $stmt->fetchColumn();
                }
                
                if ($patient_id) {
                    // Check if billing exists for today
                    $stmt = $pdo->prepare("SELECT id FROM billing WHERE patient_id = ? AND DATE(billing_date) = CURDATE() LIMIT 1");
                    $stmt->execute([$patient_id]);
                    $billing_id = $stmt->fetchColumn();
                    
                    if (!$billing_id) {
                        // Create new billing record
                        $stmt = $pdo->prepare("INSERT INTO billing (patient_id, total_amount, payment_status, billing_date, created_at) VALUES (?, ?, 'pending', NOW(), NOW())");
                        $stmt->execute([$patient_id, $new_amount]);
                        $billing_id = $pdo->lastInsertId();
                    }
                }
            }
            
            if (!$billing_id) {
                // Last attempt - create billing with just patient_id
                if ($patient_id) {
                    $stmt = $pdo->prepare("INSERT INTO billing (patient_id, total_amount, payment_status, billing_date, created_at) VALUES (?, ?, 'pending', NOW(), NOW())");
                    $stmt->execute([$patient_id, $new_amount]);
                    $billing_id = $pdo->lastInsertId();
                }
            }
            
            if (!$billing_id) {
                $response['message'] = 'Could not create billing. Patient ID: ' . $patient_id . ', Queue ID: ' . $queue_id;
                echo json_encode($response);
                exit;
            }
            
            // Get current billing record
            $stmt = $pdo->prepare("SELECT total_amount, paid_amount, patient_id FROM billing WHERE id = ?");
            $stmt->execute([$billing_id]);
            $billing = $stmt->fetch();
            
            if (!$billing) {
                $response['message'] = 'Billing record not found';
                echo json_encode($response);
                exit;
            }
            
            $patient_name = getPatientName($pdo, $billing['patient_id']);
            $old_amount = $billing['total_amount'];
            $paid_amount = $billing['paid_amount'];
            $new_balance = max(0, $new_amount - $paid_amount);
            
            // Update billing amount
            $stmt = $pdo->query("DESCRIBE billing");
            $bcols = array_column($stmt->fetchAll(), 'Field');
            $bupdates = [];
            $bparams = [];
            if (in_array('total_amount', $bcols)) { $bupdates[] = "total_amount = ?"; $bparams[] = $new_amount; }
            if (in_array('balance', $bcols)) { $bupdates[] = "balance = ?"; $bparams[] = max(0, $new_amount - $paid_amount); }
            if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
            if (!empty($bupdates)) {
                $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                $bparams[] = $billing_id;
                $stmt->execute($bparams);
            }
            
            // Log audit - billing updated
            logAudit($pdo, $current_user_id, $current_username, $current_role, 'update', 'billing', 'Updated billing amount: ₱' . number_format($old_amount, 2) . ' → ₱' . number_format($new_amount, 2) . ' for patient: ' . $patient_name . ' (INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT) . ')' . ($reason ? ' - Reason: ' . $reason : ''), $billing_id, 'billing', '₱' . number_format($old_amount, 2), '₱' . number_format($new_amount, 2));
            
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
