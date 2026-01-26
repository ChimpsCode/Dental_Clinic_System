<?php
/**
 * Billing Actions API - Handle billing operations
 * Used by staff_billing.php for viewing details and marking as paid
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

// Handle GET requests (view details)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'get_details') {
        $queueId = isset($_GET['queue_id']) ? intval($_GET['queue_id']) : 0;
        $patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
        
        if ($queueId <= 0 || $patientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit();
        }
        
        try {
            // Get queue and patient details
            $stmt = $pdo->prepare("
                SELECT 
                    q.id as queue_id,
                    q.patient_id,
                    q.treatment_type,
                    q.teeth_numbers,
                    q.status as queue_status,
                    q.notes as queue_notes,
                    DATE_FORMAT(q.created_at, '%M %d, %Y %h:%i %p') as queue_date,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.phone,
                    p.email,
                    b.id as billing_id,
                    COALESCE(b.total_amount, 0) as amount,
                    COALESCE(b.payment_status, 'unpaid') as payment_status,
                    b.paid_amount
                FROM queue q
                LEFT JOIN patients p ON q.patient_id = p.id
                LEFT JOIN billing b ON b.patient_id = q.patient_id AND DATE(b.billing_date) = DATE(q.created_at)
                WHERE q.id = ? AND q.patient_id = ?
            ");
            $stmt->execute([$queueId, $patientId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                // Calculate amount from services if not set
                if ($record['amount'] <= 0 && !empty($record['treatment_type'])) {
                    $servicesStmt = $pdo->query("SELECT name, price FROM services WHERE is_active = 1");
                    $services = $servicesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $total = 0;
                    $treatments = explode(',', $record['treatment_type']);
                    foreach ($treatments as $treatment) {
                        $treatment = trim($treatment);
                        foreach ($services as $name => $price) {
                            if (strcasecmp($name, $treatment) === 0) {
                                $total += $price;
                                break;
                            }
                        }
                    }
                    $record['amount'] = $total > 0 ? $total : 500;
                }
                
                echo json_encode([
                    'success' => true,
                    'patient_name' => $record['patient_name'],
                    'treatment_type' => $record['treatment_type'],
                    'teeth_numbers' => $record['teeth_numbers'],
                    'queue_status' => ucfirst($record['queue_status']),
                    'amount' => $record['amount'],
                    'payment_status' => ucfirst($record['payment_status']),
                    'queue_date' => $record['queue_date'],
                    'phone' => $record['phone'],
                    'email' => $record['email']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle POST requests (mark as paid)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request body']);
        exit();
    }
    
    $action = isset($input['action']) ? $input['action'] : '';
    
    if ($action === 'mark_paid') {
        $queueId = isset($input['queue_id']) ? intval($input['queue_id']) : 0;
        $patientId = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
        $amount = isset($input['amount']) ? floatval($input['amount']) : 0;
        $treatment = isset($input['treatment']) ? trim($input['treatment']) : '';
        
        if ($queueId <= 0 || $patientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get queue date for matching
            $queueStmt = $pdo->prepare("SELECT DATE(created_at) as queue_date FROM queue WHERE id = ?");
            $queueStmt->execute([$queueId]);
            $queueData = $queueStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$queueData) {
                throw new Exception('Queue record not found');
            }
            
            $queueDate = $queueData['queue_date'];
            
            // Check if billing record already exists for this patient on this date
            $checkStmt = $pdo->prepare("
                SELECT id FROM billing 
                WHERE patient_id = ? AND DATE(billing_date) = ?
            ");
            $checkStmt->execute([$patientId, $queueDate]);
            $existingBilling = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingBilling) {
                // Update existing billing record
                $updateStmt = $pdo->prepare("
                    UPDATE billing 
                    SET payment_status = 'paid', 
                        paid_amount = total_amount,
                        balance = 0,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$existingBilling['id']]);
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
                    ) VALUES (?, ?, ?, 0, 'paid', ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $patientId,
                    $amount,
                    $amount,
                    $queueDate,
                    $queueDate, // Due date same as billing date since it's paid
                    "Payment for: " . $treatment
                ]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment marked as paid successfully'
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
