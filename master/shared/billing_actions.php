<?php
/**
 * Billing Actions API - Handles billing-related AJAX requests for both Admin and Staff
 */

// Start session first - must be done before any output
require_once __DIR__ . '/../../includes/session_bootstrap.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/notification_functions.php';
ensureNotificationSchema($pdo);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action'];

// Get current user info from session with fallback
$current_user_id = $_SESSION['user_id'] ?? null;
$current_username = $_SESSION['username'] ?? null;
$current_role = strtolower((string)($_SESSION['role'] ?? ''));

$allowed_roles = ['admin', 'staff', 'dentist'];
if (empty($current_user_id) || !in_array($current_role, $allowed_roles, true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
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

// Include Senior/PWD discount helper
require_once __DIR__ . '/../../includes/discount_helper.php';

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
                SELECT 
                    b.*, 
                    p.first_name, 
                    p.middle_name, 
                    p.last_name, 
                    p.suffix, 
                    p.phone,
                    p.street_address,
                    p.city,
                    p.province,
                    p.zip_code
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

        case 'get_payments':
            // Get payment records for a patient (all invoices) or a single billing record
            $billing_id = (int)($_GET['billing_id'] ?? 0);
            $patient_id = (int)($_GET['patient_id'] ?? 0);

            if (!$patient_id && !$billing_id) {
                $response['message'] = 'Patient ID or Billing ID is required';
                echo json_encode($response);
                exit;
            }

            if ($patient_id) {
                $stmt = $pdo->prepare("
                    SELECT pay.id, pay.billing_id, pay.patient_id, pay.amount, pay.payment_method,
                           pay.payment_date, pay.reference_number, pay.created_by, pay.notes, pay.created_at
                    FROM payments pay
                    LEFT JOIN billing b ON b.id = pay.billing_id
                    WHERE pay.patient_id = ? OR b.patient_id = ?
                    ORDER BY pay.payment_date DESC, pay.created_at DESC
                ");
                $stmt->execute([$patient_id, $patient_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, billing_id, patient_id, amount, payment_method, payment_date, reference_number, created_by, notes, created_at
                    FROM payments
                    WHERE billing_id = ?
                    ORDER BY payment_date DESC, created_at DESC
                ");
                $stmt->execute([$billing_id]);
            }
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = true;
            $response['payments'] = $payments;
            $response['count'] = count($payments);
            break;

        case 'set_due_date':
            // Set or update the due date on a billing record
            $input = json_decode(file_get_contents('php://input'), true);
            $billing_id = $input['billing_id'] ?? 0;
            $due_date = $input['due_date'] ?? '';

            if (!$billing_id || !$due_date) {
                $response['message'] = 'Billing ID and due date are required';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->prepare("SELECT patient_id, due_date, balance FROM billing WHERE id = ?");
            $stmt->execute([$billing_id]);
            $billing = $stmt->fetch();

            if (!$billing) {
                $response['message'] = 'Billing record not found';
                echo json_encode($response);
                exit;
            }

            $patient_name = getPatientName($pdo, $billing['patient_id']);
            $previousDue = !empty($billing['due_date']) ? date('Y-m-d', strtotime($billing['due_date'])) : '';
            $newDue = date('Y-m-d', strtotime($due_date));

            $stmt = $pdo->prepare("UPDATE billing SET due_date = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$due_date, $billing_id]);

            logAudit($pdo, $current_user_id, $current_username, $current_role, 'update', 'billing', 'Set due date to ' . $due_date . ' for patient: ' . $patient_name . ' (INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT) . ')', $billing_id, 'billing', null, $due_date);

            if ($previousDue !== $newDue) {
                notifyPatientDueDateSet(
                    $pdo,
                    (int)$billing['patient_id'],
                    (int)$billing_id,
                    $newDue,
                    (float)($billing['balance'] ?? 0)
                );
            }

            $response['success'] = true;
            $response['message'] = 'Due date set successfully';
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

            // Must run before logAudit: the first-time print_logs backfill copies existing audit rows
            require_once __DIR__ . '/../../includes/print_log_helper.php';
            $printPatientStmt = $pdo->prepare("SELECT patient_id FROM billing WHERE id = ?");
            $printPatientStmt->execute([$billing_id]);
            $printPatientId = $printPatientStmt->fetchColumn();
            logPrint(
                $pdo,
                'Invoice',
                'INV-' . str_pad($billing_id, 4, '0', STR_PAD_LEFT) . ($printPatientId ? ' - ' . getPatientName($pdo, $printPatientId) : ''),
                'billing',
                $billing_id,
                'billing_actions.php'
            );

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
                    $queue_id = $appointment_id;
                    
                    // Get billing for this patient on appointment date
                    $stmt = $pdo->prepare("
                        SELECT b.*, COALESCE(b.payment_status, 'unpaid') as payment_status
                        FROM billing b
                        WHERE b.patient_id = ? AND DATE(b.billing_date) = DATE(?)
                    ");
                    $stmt->execute([$patient_id, $appointment['appointment_date']]);
                    $billing = $stmt->fetch();
                    
                    // ALWAYS fetch price from services table first - admin can change prices anytime
                    $treatment = trim($appointment['treatment'] ?? 'Consultation');
                    $treatmentLower = strtolower($treatment);
                    
                    // Get price from services table - flexible match
                    $stmt = $pdo->query("SELECT name, price FROM services WHERE is_active = 1 ORDER BY LENGTH(name)");
                    $servicePrice = 500;
                    while ($svc = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $svcName = strtolower($svc['name']);
                        // Match if treatment contains service name OR service name contains treatment
                        if (strpos($treatmentLower, $svcName) !== false || 
                            strpos($svcName, $treatmentLower) !== false ||
                            ($treatmentLower === 'crown' && strpos($svcName, 'crown') !== false) ||
                            ($treatmentLower === 'consultation' && strpos($svcName, 'consultation') !== false)) {
                            $servicePrice = (int)$svc['price'];
                            break;
                        }
                    }
                    
                    // Use fresh service price (always from services table - reflects admin changes)
                    $amount = $servicePrice;
                    
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
                    $response['due_date'] = $billing['due_date'] ?? null;
                    
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
                    $response['due_date'] = $billing['due_date'] ?? null;
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
                    $response['due_date'] = null;
                } else {
                    $response['message'] = 'Patient not found';
                }
            }
            break;
            
        case 'mark_paid':
            // Used by admin_payment.php, staff_payment.php, staff_billing.php, dentist_appointments.php
            $input = json_decode(file_get_contents('php://input'), true);
            
            $billing_id = $input['billing_id'] ?? 0;
            $queue_id = $input['queue_id'] ?? 0;
            $appointment_id = $input['appointment_id'] ?? 0;
            $patient_id = $input['patient_id'] ?? 0;
            $amount = $input['amount'] ?? 0;
            $treatment = $input['treatment'] ?? '';
            $payment_method = $input['payment_method'] ?? 'Cash';
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
            
            // Handle appointment_id
            if (!$patient_id && $appointment_id) {
                $stmt = $pdo->prepare("SELECT patient_id, DATE(appointment_date) as apt_date FROM appointments WHERE id = ?");
                $stmt->execute([$appointment_id]);
                $aptRow = $stmt->fetch();
                if ($aptRow) {
                    $queueDate = $aptRow['apt_date'] ?? date('Y-m-d');
                    if (!$patient_id) {
                        $patient_id = $aptRow['patient_id'];
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
            
            // Amounts entered are pre-discount subtotals; discount auto-applies when eligible
            $paymentAmount = $amount;
            
            $pdo->beginTransaction();
            
            try {
                // Find or create billing record
                if ($billing_id) {
                    // Get old status before update
                    $stmt = $pdo->prepare("SELECT payment_status, total_amount FROM billing WHERE id = ?");
                    $stmt->execute([$billing_id]);
                    $existingBilling = $stmt->fetch(PDO::FETCH_ASSOC);
                    $old_status = strtolower(($existingBilling['payment_status'] ?? '') ?: 'unpaid');
                    $totalAmt = floatval($existingBilling['total_amount'] ?? $amount);
                    
                    // Mark as fully paid: set paid_amount = total_amount, balance = 0
                    $stmt = $pdo->query("DESCRIBE billing");
                    $bcols = array_column($stmt->fetchAll(), 'Field');
                    $bupdates = [];
                    $bparams = [];
                    if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = 1"; }
                    if (in_array('payment_status', $bcols)) { $bupdates[] = "payment_status = 'Paid'"; }
                    if (in_array('paid_amount', $bcols)) { $bupdates[] = "paid_amount = ?"; $bparams[] = $totalAmt; }
                    if (in_array('balance', $bcols)) { $bupdates[] = "balance = 0"; }
                    if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
                    if (!empty($bupdates)) {
                        $bparams[] = $billing_id;
                        $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                        $stmt->execute($bparams);
                    }
                } else {
                    // Check if billing exists for today
                    $stmt = $pdo->prepare("
                        SELECT id, payment_status FROM billing 
                        WHERE patient_id = ? AND DATE(billing_date) = ?
                    ");
                    $stmt->execute([$patient_id, $queueDate]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing - mark as fully paid
                        $billing_id = $existing['id'];
                        $old_status = strtolower($existing['payment_status'] ?: 'unpaid');
                        $stmt = $pdo->prepare("SELECT total_amount FROM billing WHERE id = ?");
                        $stmt->execute([$billing_id]);
                        $totalAmt = floatval($stmt->fetchColumn() ?: $amount);
                        $stmt = $pdo->query("DESCRIBE billing");
                        $bcols = array_column($stmt->fetchAll(), 'Field');
                        $bupdates = [];
                        $bparamsUpd = [];
                        if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = 1"; }
                        if (in_array('payment_status', $bcols)) { $bupdates[] = "payment_status = 'Paid'"; }
                        if (in_array('paid_amount', $bcols)) { $bupdates[] = "paid_amount = ?"; $bparamsUpd[] = $totalAmt; }
                        if (in_array('balance', $bcols)) { $bupdates[] = "balance = 0"; }
                        if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
                        if (!empty($bupdates)) {
                            $bparamsUpd[] = $billing_id;
                            $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                            $stmt->execute($bparamsUpd);
                        }
                    } else {
                        // Create new billing - column check (with Senior/PWD discount)
                        $disc = getPatientDiscount($pdo, (int)$patient_id);
                        $totals = computeDiscountedTotals(floatval($amount), $disc);
                        if ($disc['eligible']) {
                            $paymentAmount = $totals['total'];
                        }
                        $stmt = $pdo->query("DESCRIBE billing");
                        $bcols = array_column($stmt->fetchAll(), 'Field');
                        $bcolsList = [];
                        $bvals = [];
                        if (in_array('patient_id', $bcols)) { $bcolsList[] = "patient_id"; $bvals[] = $patient_id; }
                        if (in_array('total_amount', $bcols)) { $bcolsList[] = "total_amount"; $bvals[] = $totals['total']; }
                        if ($disc['eligible'] && in_array('subtotal_amount', $bcols)) { $bcolsList[] = "subtotal_amount"; $bvals[] = $totals['subtotal']; }
                        if ($disc['eligible'] && in_array('discount_type', $bcols)) { $bcolsList[] = "discount_type"; $bvals[] = $disc['type']; }
                        if ($disc['eligible'] && in_array('discount_rate', $bcols)) { $bcolsList[] = "discount_rate"; $bvals[] = $disc['rate']; }
                        if ($disc['eligible'] && in_array('discount_amount', $bcols)) { $bcolsList[] = "discount_amount"; $bvals[] = $totals['discount_amount']; }
                        if ($disc['eligible'] && in_array('discount_id_no', $bcols)) { $bcolsList[] = "discount_id_no"; $bvals[] = $disc['id_no']; }
                        if ($disc['eligible'] && in_array('discount_holder_name', $bcols)) { $bcolsList[] = "discount_holder_name"; $bvals[] = $disc['holder_name']; }
                        if (in_array('paid_amount', $bcols)) { $bcolsList[] = "paid_amount"; $bvals[] = $paymentAmount; }
                        if (in_array('balance', $bcols)) { $bcolsList[] = "balance"; $bvals[] = 0; }
                        if (in_array('payment_status_id', $bcols)) { $bcolsList[] = "payment_status_id"; $bvals[] = 1; }
                        if (in_array('payment_status', $bcols)) { $bcolsList[] = "payment_status"; $bvals[] = 'paid'; }
                        if (in_array('billing_date', $bcols)) { $bcolsList[] = "billing_date"; $bvals[] = $queueDate; }
                        if (in_array('due_date', $bcols)) { $bcolsList[] = "due_date"; $bvals[] = date('Y-m-d', strtotime($queueDate . ' +7 days')); }
                        if (in_array('created_at', $bcols)) { $bcolsList[] = "created_at"; $bvals[] = 'NOW()'; }
                        if (in_array('updated_at', $bcols)) { $bcolsList[] = "updated_at"; $bvals[] = 'NOW()'; }
                        if (!empty($bcolsList)) {
                            $sql = "INSERT INTO billing (" . implode(', ', $bcolsList) . ") VALUES (" . implode(', ', array_fill(0, count($bvals), '?')) . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($bvals);
                            $billing_id = $pdo->lastInsertId();
                            if ($disc['eligible']) {
                                logDiscountAudit($pdo, 'discount', (int)$billing_id, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on mark-paid bill');
                            }
                        }
                    }
                }
                
                // Create payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (billing_id, patient_id, amount, payment_method, payment_date, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$billing_id, $patient_id, $paymentAmount, $payment_method, $paymentDate, $current_user_id]);
                $payment_id = $pdo->lastInsertId();
                
                // Update queue status to completed if queue_id exists
                if ($queue_id) {
                    $stmt = $pdo->query("DESCRIBE queue");
                    $qcols = array_column($stmt->fetchAll(), 'Field');
                    $qupdates = [];
                    if (in_array('status', $qcols)) { $qupdates[] = "status = 'completed'"; }
                    if (in_array('status_id', $qcols)) { $qupdates[] = "status_id = 3"; } // 3 = completed
                    if (in_array('updated_at', $qcols)) { $qupdates[] = "updated_at = NOW()"; }
                    if (!empty($qupdates)) {
                        $whereClauses = ["id = ?"];
                        if (in_array('status', $qcols)) {
                            // Accept common variants used in DB or code: 'Pending Payment' or 'pending_payment'
                            $whereClauses[] = "(LOWER(status) = 'pending payment' OR status = 'pending_payment')";
                        } elseif (in_array('status_id', $qcols)) {
                            $whereClauses[] = "status_id IN (1, 2)"; // only waiting or in_procedure
                        }
                        $stmt = $pdo->prepare("UPDATE queue SET " . implode(', ', $qupdates) . " WHERE " . implode(' AND ', $whereClauses));
                        $stmt->execute([$queue_id]);
                    }
                }
                
                $pdo->commit();
                
                // Log audit - payment recorded
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'payment', 'billing', 'Payment recorded: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name . ' (INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT) . ')', $billing_id, 'billing', $old_status, 'paid');
                
                // Log audit - payment record created
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'create', 'payments', 'Created payment record: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name, $payment_id, 'payments', null, '₱' . number_format($amount, 2));
                
                if (!empty($patient_id)) {
                    $notifResult = notifyPatientPaymentRecorded($pdo, (int)$patient_id, floatval($paymentAmount), $payment_method, $treatment ?: 'consultation', 0);
                    if ($notifResult === false) {
                        notifLog("billing_actions mark_paid: notifyPatientPaymentRecorded FAILED for patient_id={$patient_id}");
                    } else {
                        notifLog("billing_actions mark_paid: notifyPatientPaymentRecorded OK for patient_id={$patient_id} amount={$paymentAmount} method={$payment_method}");
                    }
                }

                $response['success'] = true;
                $response['message'] = 'Payment marked as paid successfully';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'record_payment':
            // Used by dentist_dashboard.php, staff_payment.php, staff_billing.php for partial/down payments
            $input = json_decode(file_get_contents('php://input'), true);
            
            $patient_id = $input['patient_id'] ?? 0;
            $queue_id = $input['queue_id'] ?? 0;
            $billing_id = $input['billing_id'] ?? 0;
            $amount = floatval($input['amount'] ?? 0);
            $payment_method = $input['payment_method'] ?? 'Cash';
            $settle_outstanding = !empty($input['settle_outstanding']);
            $reference_number = trim((string)($input['reference_number'] ?? ''));
            if ($reference_number === '' || !in_array(strtolower((string)$payment_method), ['gcash', 'bank_transfer'], true)) {
                $reference_number = null;
            }
            
            if (!$patient_id || $amount <= 0) {
                $response['message'] = 'Patient ID and valid amount are required';
                echo json_encode($response);
                exit;
            }
            
            $patient_name = getPatientName($pdo, $patient_id);
            $paymentDate = date('Y-m-d');
            
            $pdo->beginTransaction();
            
            try {
                if ($settle_outstanding) {
                    $stmt = $pdo->prepare("
                        SELECT id, total_amount, paid_amount, balance, payment_status
                        FROM billing
                        WHERE patient_id = ?
                          AND COALESCE(balance, 0) > 0
                          AND LOWER(COALESCE(payment_status, '')) NOT IN ('paid', 'cancelled', 'canceled', 'voided')
                        ORDER BY created_at ASC, id ASC
                    ");
                    $stmt->execute([$patient_id]);
                    $openBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $totalDue = 0.0;
                    foreach ($openBills as $openBill) {
                        $totalDue += floatval($openBill['balance']);
                    }
                    $totalDue = round($totalDue, 2);
                    $amount = round($amount, 2);

                    if ($totalDue <= 0) {
                        $pdo->rollBack();
                        $response['message'] = 'All services are paid.';
                        break;
                    }
                    if ($amount - $totalDue > 0.009) {
                        $pdo->rollBack();
                        $response['message'] = 'Payment is higher than the remaining balance of P' . number_format($totalDue, 2) . '.';
                        break;
                    }

                    $stmt = $pdo->query("DESCRIBE billing");
                    $bcols = array_column($stmt->fetchAll(), 'Field');
                    $remaining = $amount;
                    $payment_id = null;
                    $billing_id = 0;
                    $new_status = 'partial';
                    $recordedBalance = $totalDue;

                    foreach ($openBills as $openBill) {
                        if ($remaining <= 0.009) {
                            break;
                        }
                        $billBalance = round(floatval($openBill['balance']), 2);
                        if ($billBalance <= 0) {
                            continue;
                        }
                        $apply = round(min($remaining, $billBalance), 2);
                        $new_paid = round(floatval($openBill['paid_amount']) + $apply, 2);
                        $new_balance = round(max(0, floatval($openBill['total_amount']) - $new_paid), 2);
                        if ($new_balance <= 0.009) {
                            $new_balance = 0;
                            $new_status = 'paid';
                            $new_status_id = 1;
                        } else {
                            $new_status = 'partial';
                            $new_status_id = 2;
                        }

                        $targetId = (int)$openBill['id'];
                        $bupdates = [];
                        $bparams = [];
                        $bupdates[] = "paid_amount = ?"; $bparams[] = $new_paid;
                        $bupdates[] = "balance = ?"; $bparams[] = $new_balance;
                        $bupdates[] = "payment_status = ?"; $bparams[] = $new_status;
                        if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = ?"; $bparams[] = $new_status_id; }
                        $bupdates[] = "updated_at = NOW()";
                        $bparams[] = $targetId;
                        $upd = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                        $upd->execute($bparams);

                        $payStmt = $pdo->prepare("
                            INSERT INTO payments (billing_id, patient_id, amount, payment_method, payment_date, reference_number, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $payStmt->execute([$targetId, $patient_id, $apply, $payment_method, $paymentDate, $reference_number, $current_user_id]);
                        $payment_id = $pdo->lastInsertId();
                        $billing_id = $targetId;
                        $remaining = round($remaining - $apply, 2);
                        $recordedBalance = round($recordedBalance - $apply, 2);
                    }

                    $pdo->commit();

                    logAudit($pdo, $current_user_id, $current_username, $current_role, 'payment', 'billing', 'Outstanding balance payment recorded: ₱' . number_format($amount, 2) . ' via ' . $payment_method . ' for patient: ' . $patient_name, $billing_id, 'billing', 'partial', $new_status ?? 'partial');
                    if ($payment_id) {
                        logAudit($pdo, $current_user_id, $current_username, $current_role, 'create', 'payments', 'Created payment record: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name, $payment_id, 'payments', null, '₱' . number_format($amount, 2));
                    }

                    if (!empty($patient_id)) {
                        $treatmentType = 'consultation';
                        if (!empty($queue_id)) {
                            $tStmt = $pdo->prepare("SELECT treatment_type FROM queue WHERE id = ?");
                            $tStmt->execute([(int)$queue_id]);
                            $treatmentType = $tStmt->fetchColumn() ?: 'consultation';
                        }
                        $notifResult = notifyPatientPaymentRecorded($pdo, (int)$patient_id, floatval($amount), $payment_method, $treatmentType, max(0, $recordedBalance));
                        if ($notifResult === false) {
                            notifLog("billing_actions: notifyPatientPaymentRecorded FAILED for patient_id={$patient_id} user_id check needed");
                        } else {
                            notifLog("billing_actions: notifyPatientPaymentRecorded OK for patient_id={$patient_id} amount={$amount} method={$payment_method}");
                        }
                    }

                    $response['success'] = true;
                    $response['message'] = 'Payment recorded successfully';
                    break;
                }

                // Find or create billing record
                if ($billing_id) {
                    $stmt = $pdo->prepare("SELECT id, total_amount, paid_amount, balance, payment_status FROM billing WHERE id = ?");
                    $stmt->execute([$billing_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, total_amount, paid_amount, balance, payment_status FROM billing WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$patient_id]);
                }
                $billing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($billing) {
                    $billing_id = $billing['id'];
                    $total_amount = floatval($billing['total_amount']);
                    $old_paid = floatval($billing['paid_amount']);
                    $new_paid = $old_paid + $amount;
                    $new_balance = max(0, $total_amount - $new_paid);
                    
                    // Determine new payment status
                    $new_status = 'partial';
                    $new_status_id = 2; // Partial
                    if ($new_balance <= 0) { $new_status = 'paid'; $new_status_id = 1; } // Paid
                    
                    $stmt = $pdo->query("DESCRIBE billing");
                    $bcols = array_column($stmt->fetchAll(), 'Field');
                    $bupdates = [];
                    $bparams = [];
                    $bupdates[] = "paid_amount = ?"; $bparams[] = $new_paid;
                    $bupdates[] = "balance = ?"; $bparams[] = $new_balance;
                    $bupdates[] = "payment_status = ?"; $bparams[] = $new_status;
                    if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = ?"; $bparams[] = $new_status_id; }
                    $bupdates[] = "updated_at = NOW()";
                    $bparams[] = $billing_id;
                    $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                    $stmt->execute($bparams);
                } else {
                    // Create new billing if none exists (with Senior/PWD discount)
                    $disc = getPatientDiscount($pdo, (int)$patient_id);
                    $totals = computeDiscountedTotals(floatval($amount), $disc);
                    $paymentAmount = $totals['total'];
                    
                    $stmt = $pdo->query("DESCRIBE billing");
                    $bcols = array_column($stmt->fetchAll(), 'Field');
                    $bcolsList = [];
                    $bvals = [];
                    if (in_array('patient_id', $bcols)) { $bcolsList[] = "patient_id"; $bvals[] = $patient_id; }
                    if (in_array('total_amount', $bcols)) { $bcolsList[] = "total_amount"; $bvals[] = $totals['total']; }
                    if ($disc['eligible'] && in_array('subtotal_amount', $bcols)) { $bcolsList[] = "subtotal_amount"; $bvals[] = $totals['subtotal']; }
                    if ($disc['eligible'] && in_array('discount_type', $bcols)) { $bcolsList[] = "discount_type"; $bvals[] = $disc['type']; }
                    if ($disc['eligible'] && in_array('discount_rate', $bcols)) { $bcolsList[] = "discount_rate"; $bvals[] = $disc['rate']; }
                    if ($disc['eligible'] && in_array('discount_amount', $bcols)) { $bcolsList[] = "discount_amount"; $bvals[] = $totals['discount_amount']; }
                    if ($disc['eligible'] && in_array('discount_id_no', $bcols)) { $bcolsList[] = "discount_id_no"; $bvals[] = $disc['id_no']; }
                    if ($disc['eligible'] && in_array('discount_holder_name', $bcols)) { $bcolsList[] = "discount_holder_name"; $bvals[] = $disc['holder_name']; }
                    if (in_array('paid_amount', $bcols)) { $bcolsList[] = "paid_amount"; $bvals[] = $paymentAmount; }
                    if (in_array('balance', $bcols)) { $bcolsList[] = "balance"; $bvals[] = 0; }
                    if (in_array('payment_status', $bcols)) { $bcolsList[] = "payment_status"; $bvals[] = 'paid'; }
                    if (in_array('billing_date', $bcols)) { $bcolsList[] = "billing_date"; $bvals[] = $paymentDate; }
                    if (in_array('due_date', $bcols)) { $bcolsList[] = "due_date"; $bvals[] = date('Y-m-d', strtotime($paymentDate . ' +30 days')); }
                    if (in_array('created_at', $bcols)) { $bcolsList[] = "created_at"; $bvals[] = 'NOW()'; }
                    if (in_array('updated_at', $bcols)) { $bcolsList[] = "updated_at"; $bvals[] = 'NOW()'; }
                    if (!empty($bcolsList)) {
                        $sql = "INSERT INTO billing (" . implode(', ', $bcolsList) . ") VALUES (" . implode(', ', array_fill(0, count($bvals), '?')) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($bvals);
                        $billing_id = $pdo->lastInsertId();
                        if ($disc['eligible']) {
                            logDiscountAudit($pdo, 'discount', (int)$billing_id, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on first-payment bill');
                        }
                    }
                }
                
                // Create payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (billing_id, patient_id, amount, payment_method, payment_date, reference_number, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$billing_id, $patient_id, $paymentAmount ?? $amount, $payment_method, $paymentDate, $reference_number, $current_user_id]);
                $payment_id = $pdo->lastInsertId();
                
                $pdo->commit();
                
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'payment', 'billing', 'Partial payment recorded: ₱' . number_format($amount, 2) . ' via ' . $payment_method . ' for patient: ' . $patient_name, $billing_id, 'billing', 'pending', $new_status ?? 'partial');
                logAudit($pdo, $current_user_id, $current_username, $current_role, 'create', 'payments', 'Created payment record: ₱' . number_format($amount, 2) . ' for patient: ' . $patient_name, $payment_id, 'payments', null, '₱' . number_format($amount, 2));

                if (!empty($patient_id)) {
                    $treatmentType = 'consultation';
                    if (!empty($queue_id)) {
                        $tStmt = $pdo->prepare("SELECT treatment_type FROM queue WHERE id = ?");
                        $tStmt->execute([(int)$queue_id]);
                        $treatmentType = $tStmt->fetchColumn() ?: 'consultation';
                    }
                    $pTotal = floatval($total_amount ?? 0);
                    $pPaid = floatval($new_paid ?? 0);
                    $recordedBalance = $pTotal > 0 ? max(0, $pTotal - $pPaid) : 0;
                    $notifResult = notifyPatientPaymentRecorded($pdo, (int)$patient_id, floatval($amount), $payment_method, $treatmentType, $recordedBalance);
                    if ($notifResult === false) {
                        notifLog("billing_actions: notifyPatientPaymentRecorded FAILED for patient_id={$patient_id} user_id check needed");
                    } else {
                        notifLog("billing_actions: notifyPatientPaymentRecorded OK for patient_id={$patient_id} amount={$amount} method={$payment_method}");
                    }
                }

                $response['success'] = true;
                $response['message'] = 'Payment recorded successfully';
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("billing_actions record_payment error: " . $e->getMessage());
                throw $e;
            }
            break;
            
        case 'update_amount':
            // Used by admin_payment.php, dentist_dashboard.php, dentist_appointments.php
            $input = json_decode(file_get_contents('php://input'), true);
            
            $billing_id = $input['billing_id'] ?? 0;
            $patient_id = $input['patient_id'] ?? 0;
            $queue_id = $input['queue_id'] ?? 0;
            $appointment_id = $input['appointment_id'] ?? 0;
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
                
                // Handle appointment_id
                if (!$patient_id && $appointment_id) {
                    $stmt = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    $patient_id = $stmt->fetchColumn();
                    if ($patient_id) {
                        $queue_date = date('Y-m-d');
                        $stmt = $pdo->prepare("SELECT id FROM billing WHERE patient_id = ? AND DATE(billing_date) = ? LIMIT 1");
                        $stmt->execute([$patient_id, $queue_date]);
                        $billing_id = $stmt->fetchColumn();
                        
                        if (!$billing_id) {
                            $stmt = $pdo->prepare("INSERT INTO billing (patient_id, total_amount, payment_status, billing_date, created_at) VALUES (?, ?, 'pending', ?, NOW())");
                            $stmt->execute([$patient_id, $new_amount, $queue_date]);
                            $billing_id = $pdo->lastInsertId();
                        }
                    }
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
            
            // Entered amount is a pre-discount subtotal; discount auto-applies when eligible
            $disc = getPatientDiscount($pdo, (int)$billing['patient_id']);
            $totals = computeDiscountedTotals(floatval($new_amount), $disc);
            $payable = $totals['total'];
            $new_balance = max(0, $payable - $paid_amount);
            
            // Update billing amount
            $stmt = $pdo->query("DESCRIBE billing");
            $bcols = array_column($stmt->fetchAll(), 'Field');
            $bupdates = [];
            $bparams = [];
            if (in_array('total_amount', $bcols)) { $bupdates[] = "total_amount = ?"; $bparams[] = $payable; }
            if ($disc['eligible'] && in_array('subtotal_amount', $bcols)) { $bupdates[] = "subtotal_amount = ?"; $bparams[] = $totals['subtotal']; }
            if ($disc['eligible'] && in_array('discount_type', $bcols)) { $bupdates[] = "discount_type = ?"; $bparams[] = $disc['type']; }
            if ($disc['eligible'] && in_array('discount_rate', $bcols)) { $bupdates[] = "discount_rate = ?"; $bparams[] = $disc['rate']; }
            if ($disc['eligible'] && in_array('discount_amount', $bcols)) { $bupdates[] = "discount_amount = ?"; $bparams[] = $totals['discount_amount']; }
            if ($disc['eligible'] && in_array('discount_id_no', $bcols)) { $bupdates[] = "discount_id_no = ?"; $bparams[] = $disc['id_no']; }
            if ($disc['eligible'] && in_array('discount_holder_name', $bcols)) { $bupdates[] = "discount_holder_name = ?"; $bparams[] = $disc['holder_name']; }
            if (in_array('balance', $bcols)) { $bupdates[] = "balance = ?"; $bparams[] = $new_balance; }
            if (in_array('updated_at', $bcols)) { $bupdates[] = "updated_at = NOW()"; }
            // If new amount exceeds what was already paid, reset payment status to partial
            if ($payable > $paid_amount && $paid_amount > 0) {
                if (in_array('payment_status_id', $bcols)) { $bupdates[] = "payment_status_id = 2"; }
                if (in_array('payment_status', $bcols)) { $bupdates[] = "payment_status = 'partial'"; }
            }
            if (!empty($bupdates)) {
                $stmt = $pdo->prepare("UPDATE billing SET " . implode(', ', $bupdates) . " WHERE id = ?");
                $bparams[] = $billing_id;
                $stmt->execute($bparams);
            }
            
            if ($disc['eligible']) {
                logDiscountAudit($pdo, 'discount', (int)$billing_id, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on amount update');
            }
            
            // Log audit - billing updated
            logAudit($pdo, $current_user_id, $current_username, $current_role, 'update', 'billing', 'Updated billing amount: ₱' . number_format($old_amount, 2) . ' → ₱' . number_format($payable, 2) . ' for patient: ' . $patient_name . ' (INV-' . str_pad($billing_id, 3, '0', STR_PAD_LEFT) . ')' . ($reason ? ' - Reason: ' . $reason : ''), $billing_id, 'billing', '₱' . number_format($old_amount, 2), '₱' . number_format($payable, 2));
            
            $response['success'] = true;
            $response['message'] = 'Payment amount updated successfully';
            break;
            
        case 'toggle_discount':
            // Retroactively apply/remove Senior/PWD discount on an unpaid/partial bill
            $input = json_decode(file_get_contents('php://input'), true);
            $billing_id = intval($input['billing_id'] ?? 0);
            $apply = !empty($input['apply']);
            
            if (!$billing_id) {
                $response['message'] = 'Billing ID is required';
                break;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM billing WHERE id = ?");
                $stmt->execute([$billing_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) {
                    $response['message'] = 'Billing record not found';
                    break;
                }
                
                $status = strtolower($bill['payment_status'] ?? 'unpaid');
                if ($status === 'paid') {
                    $response['message'] = 'Cannot modify a fully paid bill';
                    break;
                }
                
                $patientId = (int)$bill['patient_id'];
                $paid = floatval($bill['paid_amount'] ?? 0);
                $disc = getPatientDiscount($pdo, $patientId);
                $patient_name = getPatientName($pdo, $patientId);
                
                if ($apply) {
                    if (!$disc['eligible']) {
                        $response['message'] = $disc['ineligible_reason'] ?: 'Patient is not eligible for Senior/PWD discount';
                        break;
                    }
                    if (($bill['discount_type'] ?? 'none') !== 'none') {
                        $response['success'] = true;
                        $response['message'] = 'Discount already applied';
                        break;
                    }
                    // Subtotal is the current undiscounted total (or stored subtotal if present)
                    $subtotal = floatval($bill['subtotal_amount'] ?: $bill['total_amount']);
                    $totals = computeDiscountedTotals($subtotal, $disc);
                    
                    $stmt = $pdo->prepare("UPDATE billing SET total_amount = ?, subtotal_amount = ?, discount_type = ?, discount_rate = ?, discount_amount = ?, discount_id_no = ?, discount_holder_name = ?, balance = GREATEST(0, ? - ?), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$totals['total'], $totals['subtotal'], $disc['type'], $disc['rate'], $totals['discount_amount'], $disc['id_no'], $disc['holder_name'], $totals['total'], $paid, $billing_id]);
                    
                    logDiscountAudit($pdo, 'discount', $billing_id, 'Applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') to bill of ' . $patient_name);
                    
                    $response['success'] = true;
                    $response['message'] = $disc['label'] . ' 20% discount applied. New total: ₱' . number_format($totals['total'], 2);
                } else {
                    if (($bill['discount_type'] ?? 'none') === 'none') {
                        $response['success'] = true;
                        $response['message'] = 'No discount to remove';
                        break;
                    }
                    // Restore the pre-discount amount
                    $restored = floatval($bill['subtotal_amount'] ?: ($bill['total_amount'] + $bill['discount_amount']));
                    
                    $stmt = $pdo->prepare("UPDATE billing SET total_amount = ?, subtotal_amount = NULL, discount_type = 'none', discount_rate = 0, discount_amount = 0, discount_id_no = NULL, discount_holder_name = NULL, balance = GREATEST(0, ? - ?), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$restored, $restored, $paid, $billing_id]);
                    
                    logDiscountAudit($pdo, 'discount', $billing_id, 'Removed discount from bill of ' . $patient_name . ' (restored ₱' . number_format($restored, 2) . ')');
                    
                    $response['success'] = true;
                    $response['message'] = 'Discount removed. New total: ₱' . number_format($restored, 2);
                }
            } catch (Exception $e) {
                throw $e;
            }
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
