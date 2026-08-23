<?php
/**
 * Dentist Billing Actions API - Handle billing operations for dentists
 * Allows dentists to view and edit billing amounts
 */

// Start session only if not already started
require_once __DIR__ . '/../../includes/session_bootstrap.php';

// Check if user is logged in and is dentist
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Turn off error display to prevent HTML breaking JSON, log them instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Handle GET requests (view billing details)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'get_billing') {
        $patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
        
        if ($patientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
            exit();
        }
        
        try {
            // Get patient info first
            $patientStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, phone, email FROM patients WHERE id = ?");
            $patientStmt->execute([$patientId]);
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                echo json_encode(['success' => false, 'message' => 'Patient not found (ID: ' . $patientId . ')']);
                exit();
            }
            
            $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            
            // Get the latest appointment for this patient
            $apptStmt = $pdo->prepare("
                SELECT a.*, s.name as service_name, s.price as service_price
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC, a.created_at DESC
                LIMIT 1
            ");
            $apptStmt->execute([$patientId]);
            $appointment = $apptStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get the latest queue item if exists
            $queueStmt = $pdo->prepare("
                SELECT q.* FROM queue q
                WHERE q.patient_id = ?
                ORDER BY q.created_at DESC
                LIMIT 1
            ");
            $queueStmt->execute([$patientId]);
            $queueItem = $queueStmt->fetch(PDO::FETCH_ASSOC);
            
            // SAFELY extract queue data
            $queueId = is_array($queueItem) ? ($queueItem['id'] ?? null) : null;
            $queueStatus = is_array($queueItem) ? ($queueItem['status'] ?? null) : null;
            $queueDate = (is_array($queueItem) && !empty($queueItem['created_at'])) ? date('F d, Y', strtotime($queueItem['created_at'])) : null;
            $teethNumbers = is_array($queueItem) ? ($queueItem['teeth_numbers'] ?? null) : null;
            
            // Determine treatment and service from appointment
            $serviceId = null;
            $serviceName = null;
            $estimatedAmount = 0;
            
            if (is_array($appointment) && !empty($appointment['service_id'])) {
                $serviceId = $appointment['service_id'];
                $serviceName = $appointment['service_name'];
                $estimatedAmount = floatval($appointment['service_price'] ?? 0);
            }
            
            // Fallback: if no appointment service, try queue
            if (empty($serviceId) && is_array($queueItem) && !empty($queueItem['service_id'])) {
                $svcStmt = $pdo->prepare("SELECT id, name, price FROM services WHERE id = ? AND is_active = 1");
                $svcStmt->execute([$queueItem['service_id']]);
                $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);
                if ($svc) {
                    $serviceId = $svc['id'];
                    $serviceName = $svc['name'];
                    $estimatedAmount = floatval($svc['price']);
                }
            }
            
            // If still no service, try to find by treatment name from queue
            if (empty($serviceId) && is_array($queueItem) && !empty($queueItem['treatment_type'])) {
                $treatmentNames = array_map('trim', explode(',', $queueItem['treatment_type']));
                $estimatedAmount = 0;
                $matchedNames = [];
                foreach ($treatmentNames as $singleTreatment) {
                    if (empty($singleTreatment)) continue;
                    $svcStmt = $pdo->prepare("SELECT id, name, price FROM services WHERE name LIKE ? LIMIT 1");
                    $svcStmt->execute(['%' . $singleTreatment . '%']);
                    $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);
                    if ($svc) {
                        if (empty($serviceId)) $serviceId = $svc['id'];
                        $estimatedAmount += floatval($svc['price']);
                        $matchedNames[] = $svc['name'];
                    }
                }
                if (!empty($matchedNames)) {
                    $serviceName = implode(', ', $matchedNames);
                }
            }
            
            // Safe treatment type determination
            $treatmentType = $serviceName;
            if (!$treatmentType) {
                if (is_array($appointment) && isset($appointment['treatment'])) {
                    $treatmentType = $appointment['treatment'];
                } elseif (is_array($queueItem) && isset($queueItem['treatment_type'])) {
                    $treatmentType = $queueItem['treatment_type'];
                } else {
                    $treatmentType = 'General Consultation';
                }
            }
            
            error_log("DEBUG billing - patient_id: $patientId, serviceName: $serviceName, estimatedAmount: $estimatedAmount, serviceId: $serviceId");
            
            // Get ALL billing records for this patient
            $billingStmt = $pdo->prepare("
                SELECT b.*, 
                       COALESCE(svc.name, q.treatment_type, 'Service') as service_name
                FROM billing b
                LEFT JOIN appointments a ON b.appointment_id = a.id
                LEFT JOIN services svc ON a.service_id = svc.id
                LEFT JOIN queue q ON DATE(b.billing_date) = DATE(q.created_at) AND q.patient_id = b.patient_id
                WHERE b.patient_id = ?
                ORDER BY b.created_at ASC
            ");
            $billingStmt->execute([$patientId]);
            $allBillings = $billingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary totals
            $totalAllAmount = 0;
            $totalPaidAmount = 0;
            $totalBalance = 0;
            foreach ($allBillings as $bl) {
                $totalAllAmount += floatval($bl['total_amount'] ?? 0);
                $totalPaidAmount += floatval($bl['paid_amount'] ?? 0);
                $totalBalance += floatval($bl['balance'] ?? 0);
            }
            
            // Get the latest billing for single-record display fields
            $billing = !empty($allBillings) ? end($allBillings) : null;
            
            // If no billing record exists, create default values
            $totalAmount = 0;
            $paidAmount = 0;
            $balance = 0;
            $paymentStatus = 'unpaid';
            $billingId = null;
            $billingNotes = '';
            
            if (is_array($billing)) {
                $totalAmount = floatval($billing['total_amount'] ?? 0);
                $paidAmount = floatval($billing['paid_amount'] ?? 0);
                $balance = floatval($billing['balance'] ?? 0);
                $paymentStatus = strtolower($billing['payment_status'] ?? 'unpaid');
                $billingId = $billing['id'];
                $billingNotes = $billing['notes'] ?? '';
            } 
            
            // If totalAmount is 0 but estimatedAmount is > 0, use estimated as default
            if ($totalAmount <= 0 && $estimatedAmount > 0) {
                $totalAmount = $estimatedAmount;
                $balance = $estimatedAmount;
            }
            
            // Ensure estimatedAmount reflects reality (if it was somehow 0)
            if ($estimatedAmount <= 0 && $totalAmount > 0) {
                $estimatedAmount = $totalAmount;
            }
            
            echo json_encode([
                'success' => true,
                'billing' => [
                    'billing_id' => $billingId,
                    'queue_id' => $queueId,
                    'patient_id' => $patientId,
                    'patient_name' => $patientName ?: 'Unknown',
                    'treatment_type' => $treatmentType,
                    'service_id' => $serviceId,
                    'service_name' => $serviceName ?: $treatmentType,
                    'teeth_numbers' => $teethNumbers,
                    'queue_status' => ucfirst($queueStatus ?? ''),
                    'queue_date' => $queueDate,
                    'phone' => is_array($patient) ? ($patient['phone'] ?? '') : '',
                    'email' => is_array($patient) ? ($patient['email'] ?? '') : '',
                    'estimated_amount' => $estimatedAmount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'payment_status' => $paymentStatus,
                    'billing_notes' => $billingNotes,
                    'all_billings' => array_map(function($bl) {
                        return [
                            'id' => $bl['id'],
                            'service_name' => $bl['service_name'] ?? 'Service',
                            'total_amount' => floatval($bl['total_amount'] ?? 0),
                            'paid_amount' => floatval($bl['paid_amount'] ?? 0),
                            'balance' => floatval($bl['balance'] ?? 0),
                            'payment_status' => strtolower($bl['payment_status'] ?? 'unpaid'),
                            'billing_date' => $bl['billing_date'] ?? '',
                            'notes' => $bl['notes'] ?? ''
                        ];
                    }, $allBillings),
                    'summary_total' => $totalAllAmount,
                    'summary_paid' => $totalPaidAmount,
                    'summary_balance' => $totalBalance
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle POST requests (update billing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request body']);
        exit();
    }
    
    $action = isset($input['action']) ? $input['action'] : '';
    
    if ($action === 'update_amount') {
        $patientId = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
        $newAmount = isset($input['total_amount']) ? floatval($input['total_amount']) : 0;
        $notes = isset($input['notes']) ? trim($input['notes']) : '';
        
        if ($patientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit();
        }
        
        if ($newAmount < 0) {
            echo json_encode(['success' => false, 'message' => 'Amount cannot be negative']);
            exit();
        }
        
        try {
            require_once __DIR__ . '/../../includes/discount_helper.php';
            
            $pdo->beginTransaction();
            
            // Get the latest queue for this patient
            $queueStmt = $pdo->prepare("SELECT id, DATE(created_at) as queue_date FROM queue WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $queueStmt->execute([$patientId]);
            $queueData = $queueStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$queueData) {
                // Modified: If no queue, allow setting a bill anyway using current date
                $queueId = null;
                $queueDate = date('Y-m-d');
            } else {
                $queueId = $queueData['id'];
                $queueDate = $queueData['queue_date'];
            }
            
            // Check if billing record already exists for this patient
            $checkStmt = $pdo->prepare("SELECT id, paid_amount, payment_status, discount_type FROM billing WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $checkStmt->execute([$patientId]);
            $existingBilling = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Senior/PWD discount: the entered amount is the subtotal; total becomes discounted
            $disc = getPatientDiscount($pdo, $patientId);
            $totals = computeDiscountedTotals($newAmount, $disc);
            $payable = $totals['total'];
            
            if (is_array($existingBilling)) {
                // Calculate new balance
                $paidAmount = floatval($existingBilling['paid_amount']);
                $newBalance = $payable - $paidAmount;
                if ($newBalance < 0) $newBalance = 0;
                
                // Determine new status
                $newStatus = 'unpaid';
                if ($paidAmount >= $payable && $payable > 0) {
                    $newStatus = 'paid';
                } elseif ($paidAmount > 0) {
                    $newStatus = 'partial';
                }
                
                // Update existing billing record
                $updateStmt = $pdo->prepare("
                    UPDATE billing 
                    SET total_amount = ?, 
                        subtotal_amount = ?,
                        discount_type = ?,
                        discount_rate = ?,
                        discount_amount = ?,
                        discount_id_no = ?,
                        discount_holder_name = ?,
                        balance = ?,
                        payment_status = ?,
                        notes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $payable,
                    $totals['subtotal'],
                    $disc['eligible'] ? $disc['type'] : 'none',
                    $disc['rate'],
                    $totals['discount_amount'],
                    $disc['id_no'],
                    $disc['holder_name'],
                    $newBalance,
                    $newStatus,
                    $notes,
                    $existingBilling['id']
                ]);

                if ($disc['eligible'] && ($existingBilling['discount_type'] ?? 'none') === 'none') {
                    logDiscountAudit($pdo, 'discount', (int)$existingBilling['id'], 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') when dentist set bill amount');
                }
                
                $billingId = $existingBilling['id'];
            } else {
                // Create new billing record
                $insertStmt = $pdo->prepare("
                    INSERT INTO billing (
                        patient_id, 
                        total_amount, 
                        subtotal_amount,
                        discount_type,
                        discount_rate,
                        discount_amount,
                        discount_id_no,
                        discount_holder_name,
                        paid_amount, 
                        balance, 
                        payment_status, 
                        billing_date, 
                        due_date,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'unpaid', ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $patientId,
                    $payable,
                    $totals['subtotal'],
                    $disc['eligible'] ? $disc['type'] : 'none',
                    $disc['rate'],
                    $totals['discount_amount'],
                    $disc['id_no'],
                    $disc['holder_name'],
                    $payable,
                    $queueDate,
                    $queueDate,
                    $notes ?: 'Billing amount set by dentist'
                ]);
                
                $billingId = $pdo->lastInsertId();

                if ($disc['eligible']) {
                    logDiscountAudit($pdo, 'discount', (int)$billingId, 'Auto-applied ' . $disc['label'] . ' ' . $disc['rate'] . '% discount (' . number_format($totals['discount_amount'], 2) . ') on new dentist-set bill');
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Billing amount updated successfully',
                'billing_id' => $billingId
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle other methods
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>