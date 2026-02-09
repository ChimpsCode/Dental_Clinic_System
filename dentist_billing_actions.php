<?php
/**
 * Dentist Billing Actions API - Handle billing operations for dentists
 * Allows dentists to view and edit billing amounts
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is dentist
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config/database.php';

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
            // Get the latest queue item for this patient
            $queueStmt = $pdo->prepare("
                SELECT q.* 
                FROM queue q
                WHERE q.patient_id = ?
                ORDER BY q.created_at DESC
                LIMIT 1
            ");
            $queueStmt->execute([$patientId]);
            $queueItem = $queueStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'No queue record found for patient']);
                exit();
            }
            
            $queueId = $queueItem['id'];
            $treatmentType = $queueItem['treatment_type'];
            $teethNumbers = $queueItem['teeth_numbers'];
            $queueStatus = $queueItem['status'];
            $queueDate = date('F d, Y', strtotime($queueItem['created_at']));
            
            // Get patient info
            $patientStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, phone, email FROM patients WHERE id = ?");
            $patientStmt->execute([$patientId]);
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            
            $patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            
            // Get billing record - use patient_id only (no date restriction)
            $billingStmt = $pdo->prepare("
                SELECT b.* 
                FROM billing b
                WHERE b.patient_id = ?
                ORDER BY b.created_at DESC
                LIMIT 1
            ");
            $billingStmt->execute([$patientId]);
            $billing = $billingStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate estimated amount from services
            $estimatedAmount = 0;
            if (!empty($treatmentType)) {
                $servicesStmt = $pdo->query("SELECT name, price FROM services WHERE is_active = 1");
                $services = $servicesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $treatments = explode(',', $treatmentType);
                foreach ($treatments as $treatment) {
                    $treatment = trim($treatment);
                    foreach ($services as $name => $price) {
                        if (strcasecmp($name, $treatment) === 0) {
                            $estimatedAmount += floatval($price);
                            break;
                        }
                    }
                }
            }
            
            // If no billing record exists, create default values
            $totalAmount = 0;
            $paidAmount = 0;
            $balance = 0;
            $paymentStatus = 'unpaid';
            $billingId = null;
            
            if ($billing) {
                $totalAmount = floatval($billing['total_amount'] ?? 0);
                $paidAmount = floatval($billing['paid_amount'] ?? 0);
                $balance = floatval($billing['balance'] ?? 0);
                $paymentStatus = strtolower($billing['payment_status'] ?? 'unpaid');
                $billingId = $billing['id'];
            } elseif ($estimatedAmount > 0) {
                // Use estimated amount as default
                $totalAmount = $estimatedAmount;
                $balance = $estimatedAmount;
            }
            
            echo json_encode([
                'success' => true,
                'billing' => [
                    'billing_id' => $billingId,
                    'queue_id' => $queueId,
                    'patient_id' => $patientId,
                    'patient_name' => $patientName ?: 'Unknown',
                    'treatment_type' => $treatmentType ?: 'Consultation',
                    'teeth_numbers' => $teethNumbers,
                    'queue_status' => ucfirst($queueStatus ?? ''),
                    'queue_date' => $queueDate,
                    'phone' => $patient['phone'] ?? '',
                    'email' => $patient['email'] ?? '',
                    'estimated_amount' => $estimatedAmount > 0 ? $estimatedAmount : null,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'payment_status' => $paymentStatus,
                    'billing_notes' => $billing['notes'] ?? ''
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
            $pdo->beginTransaction();
            
            // Get the latest queue for this patient
            $queueStmt = $pdo->prepare("SELECT id, DATE(created_at) as queue_date FROM queue WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $queueStmt->execute([$patientId]);
            $queueData = $queueStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$queueData) {
                throw new Exception('Queue record not found');
            }
            
            $queueId = $queueData['id'];
            $queueDate = $queueData['queue_date'];
            
            // Check if billing record already exists for this patient
            $checkStmt = $pdo->prepare("SELECT id, paid_amount, payment_status FROM billing WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $checkStmt->execute([$patientId]);
            $existingBilling = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingBilling) {
                // Calculate new balance
                $paidAmount = floatval($existingBilling['paid_amount']);
                $newBalance = $newAmount - $paidAmount;
                if ($newBalance < 0) $newBalance = 0;
                
                // Determine new status
                $newStatus = 'unpaid';
                if ($paidAmount >= $newAmount && $newAmount > 0) {
                    $newStatus = 'paid';
                } elseif ($paidAmount > 0) {
                    $newStatus = 'partial';
                }
                
                // Update existing billing record
                $updateStmt = $pdo->prepare("
                    UPDATE billing 
                    SET total_amount = ?, 
                        balance = ?,
                        payment_status = ?,
                        notes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newAmount, $newBalance, $newStatus, $notes, $existingBilling['id']]);
                
                $billingId = $existingBilling['id'];
            } else {
                // Create new billing record
                $insertStmt = $pdo->prepare("
                    INSERT INTO billing (
                        patient_id, 
                        total_amount, 
                        paid_amount, 
                        balance, 
                        payment_status, 
                        billing_date, 
                        due_date,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, 0, ?, 'unpaid', ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $patientId,
                    $newAmount,
                    $newAmount,
                    $queueDate,
                    $queueDate,
                    $notes ?: 'Billing amount set by dentist'
                ]);
                
                $billingId = $pdo->lastInsertId();
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
