<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$user_role = $role;

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}
$action = $data['action'] ?? '';
$queueId = $data['queue_id'] ?? ($data['id'] ?? null);
$patientId = $data['patient_id'] ?? null;
$doctorId = $data['doctor_id'] ?? null;

if ($action !== 'get_doctors' && !$queueId && !$patientId) {
    echo json_encode(['success' => false, 'message' => 'Queue ID or Patient ID is required']);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/audit_helper.php';
    require_once '../../includes/billing_helper.php';
    require_once '../../includes/notification_functions.php';
    ensureNotificationSchema($pdo);
    
    // Helper function to build full name
    function buildFullName($item) {
        return trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? ''));
    }

    switch ($action) {
        case 'call':
            $stmt = $pdo->prepare("SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);

            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);

            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Called patient: ' . $patientName, $queueId, 'queue', 'waiting', 'in_procedure');

            if (!empty($queueItem['patient_id'])) {
                $queueNum = $queueItem['queue_number'] ?? $queueId;
                notifyPatientQueueUpdate($pdo, (int)$queueItem['patient_id'], (string)$queueNum, 'in_procedure');
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Calling patient: ' . $patientName,
                'patient_name' => $patientName,
                'phone' => $queueItem['phone']
            ]);
            break;
            
        case 'start_procedure':
            // Check if there's already a procedure in progress
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix 
                                   FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) 
                                   WHERE q.status = 'in_procedure'
                                   LIMIT 1");
            $stmt->execute();
            $existingProcedure = $stmt->fetch();
            
            if ($existingProcedure) {
                $existingPatientName = buildFullName($existingProcedure);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot start procedure: ' . $existingPatientName . ' is currently in procedure. Please complete their procedure first.'
                ]);
                break;
            }
            
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn() ?: 'waiting';
            
            // Update to In Procedure and record the exact start timestamp.
            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure', updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to start procedure']);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Started procedure for patient: ' . $patientName, $queueId, 'queue', $oldStatus, 'in_procedure');

            if (!empty($queueItem['patient_id'])) {
                $queueNum = $queueItem['queue_number'] ?? $queueId;
                notifyPatientQueueUpdate($pdo, (int)$queueItem['patient_id'], (string)$queueNum, 'in_procedure');
            }

            echo json_encode(['success' => true, 'message' => 'Patient moved to procedure', 'patient_name' => $patientName]);
            break;
            
        case 'complete':
            // First check if queue item exists
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            $patientId = $queueItem['patient_id'];
            $patientName = buildFullName($queueItem);
            $treatmentType = $queueItem['treatment_type'] ?? 'consultation';
            $estimatedAmount = 0;
            // Try exact match first, then flexible match
            $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1");
            $stmt->execute([$treatmentType]);
            $service = $stmt->fetch();
            if ($service) {
                $estimatedAmount = floatval($service['price']);
            } else {
                // Fallback: try LIKE match on individual names
                $treatmentNames = array_map('trim', explode(',', $treatmentType));
                $stmt = $pdo->prepare("SELECT price FROM services WHERE name LIKE ? AND is_active = 1 LIMIT 1");
                foreach ($treatmentNames as $name) {
                    if (empty($name)) continue;
                    $stmt->execute(['%' . $name . '%']);
                    $service = $stmt->fetch();
                    if ($service) {
                        $estimatedAmount += floatval($service['price']);
                    }
                }
            }
            
            $pdo->beginTransaction();
            
            // Update to Pending Payment (billing continues next)
            $stmt = $pdo->prepare("UPDATE queue SET status = 'pending_payment', completed_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to complete treatment']);
                break;
            }
            
            // Update appointment status to completed
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE patient_id = ? AND (status IN ('Scheduled', 'In Queue') OR status = '' OR status IS NULL)");
            $stmt->execute([$patientId]);

            // Find the patient's completed appointment id for billing linkage
            $appointmentId = null;
            $stmt = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND status = 'Completed' ORDER BY appointment_date DESC, id DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $appointmentId = $stmt->fetchColumn() ?: null;

            // Find-or-create billing record for this visit
            $visitDate = date('Y-m-d', strtotime($queueItem['created_at']));
            $billingInfo = findOrCreateBillingForVisit($pdo, (int)$patientId, $visitDate, $estimatedAmount, $treatmentType, $appointmentId);

            $pdo->commit();
            
            // Fetch ALL billing records for this patient to get actual totals
            $billingData = null;
            $stmt = $pdo->prepare("
                SELECT b.id as billing_id, b.total_amount, b.paid_amount, b.balance, b.payment_status
                FROM billing b
                WHERE b.patient_id = ?
                ORDER BY b.created_at ASC
            ");
            $stmt->execute([$patientId]);
            $allBillings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $billingData = !empty($allBillings) ? end($allBillings) : null;
            
            // Calculate summary totals
            $summaryTotal = 0;
            $summaryPaid = 0;
            $summaryBalance = 0;
            foreach ($allBillings as $bl) {
                $summaryTotal += floatval($bl['total_amount'] ?? 0);
                $summaryPaid += floatval($bl['paid_amount'] ?? 0);
                $summaryBalance += floatval($bl['balance'] ?? 0);
            }
            
            if ($billingData) {
                $totalAmount = $summaryTotal;
                $paidAmount = $summaryPaid;
                $balance = $summaryBalance;
                $paymentStatus = $billingData['payment_status'];
            } else {
                $totalAmount = $estimatedAmount;
                $paidAmount = 0;
                $balance = $estimatedAmount;
                $paymentStatus = 'unpaid';
            }
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Completed procedure for: ' . $patientName, $queueId, 'queue', 'in_procedure', 'pending_payment');

            if (!empty($patientId)) {
                require_once __DIR__ . '/../../includes/patient_dashboard_status_helper.php';
                $outstanding = getPatientOutstandingBalance($pdo, (int)$patientId);
                notifyPatientConsultationComplete($pdo, (int)$patientId, $outstanding);
                notifyStaffConsultationComplete($pdo, $patientName, $outstanding, (string)$treatmentType);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Procedure completed. Record moved to billing.',
                'patient_id' => $patientId,
                'patient_name' => $patientName,
                'billing_id' => isset($billingInfo['billing_id']) ? $billingInfo['billing_id'] : ($billingData['billing_id'] ?? null),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'payment_status' => $paymentStatus
            ]);
            break;
            
        case 'on_hold':
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT q.status FROM queue q WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'waiting';
            
            // Update to On Hold
            $stmt = $pdo->prepare("UPDATE queue SET status = 'on_hold' WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient put on hold: ' . $patientName, $queueId, 'queue', $oldStatusName, 'on_hold');

            if (!empty($queueItem['patient_id'])) {
                $queueNum = $queueItem['queue_number'] ?? $queueId;
                notifyPatientQueueUpdate($pdo, (int)$queueItem['patient_id'], (string)$queueNum, 'on_hold');
            }

            echo json_encode(['success' => true, 'message' => 'Patient put on hold']);
            break;
            
        case 'cancel':
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT q.status FROM queue q WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'waiting';
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient cancelled: ' . $patientName, $queueId, 'queue', $oldStatusName, 'cancelled');
            
            echo json_encode(['success' => true, 'message' => 'Patient cancelled']);
            break;
            
        case 'requeue':
            // First check if queue item exists
            $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            // Get old status name for audit
            $oldStatusName = $queueItem['status'] ?? 'on_hold';
            
            // Set back to waiting
            $stmt = $pdo->prepare("UPDATE queue SET status = 'waiting' WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to re-queue patient']);
                break;
            }
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient re-queued: ' . $patientName, $queueId, 'queue', $oldStatusName, 'waiting');

            if (!empty($queueItem['patient_id'])) {
                $queueNum = $queueItem['queue_number'] ?? $queueId;
                notifyPatientQueueUpdate($pdo, (int)$queueItem['patient_id'], (string)$queueNum, 'waiting');
            }

            echo json_encode(['success' => true, 'message' => 'Patient re-queued']);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue record not found']);
                break;
            }

            $patientName = buildFullName($queueItem);
            $archiveSql = 'UPDATE queue SET is_archived = 1';
            $archiveParams = [];
            $queueColumns = array_flip($pdo->query('SHOW COLUMNS FROM queue')->fetchAll(PDO::FETCH_COLUMN));
            if (isset($queueColumns['deleted_at'])) {
                $archiveSql .= ', deleted_at = NOW()';
            }
            $archiveSql .= ' WHERE id = ?';
            $archiveParams[] = $queueId;
            $archiveStmt = $pdo->prepare($archiveSql);
            $archiveStmt->execute($archiveParams);

            logAudit($pdo, $user_id, $username, $user_role, 'delete', 'queue', 'Archived queue record: ' . $patientName, $queueId, 'queue', 'active', 'archived');
            echo json_encode(['success' => true, 'message' => 'Record removed from active queues']);
            break;
            
        case 'get_patient_record':
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();
            
            echo json_encode(['success' => true, 'patient' => $patient]);
            break;
            
        case 'get_doctors':
            $stmt = $pdo->query("
                SELECT u.id, u.first_name, u.middle_name, u.last_name
                FROM users u
                WHERE LOWER(COALESCE(u.role, '')) = 'dentist'
                  AND COALESCE(u.status, 'Active') = 'Active'
                  AND COALESCE(u.is_archived, 0) = 0
                ORDER BY u.first_name, u.last_name
            ");
            $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'doctors' => $doctors]);
            break;

        case 'assign_doctor':
            if (!$queueId) {
                echo json_encode(['success' => false, 'message' => 'Queue ID is required']);
                break;
            }

            if ($doctorId !== null && $doctorId !== '' && $doctorId !== 0) {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM users u
                    WHERE u.id = ?
                      AND LOWER(COALESCE(u.role, '')) = 'dentist'
                      AND COALESCE(u.status, 'Active') = 'Active'
                    LIMIT 1
                ");
                $stmt->execute([$doctorId]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Invalid doctor. Doctor not found or inactive.']);
                    break;
                }
            } else {
                $doctorId = null;
            }

            $stmt = $pdo->prepare("UPDATE queue SET doctor_id = ? WHERE id = ?");
            $result = $stmt->execute([$doctorId, $queueId]);

            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to assign doctor']);
                break;
            }

            $doctorName = 'Unassigned';
            if ($doctorId) {
                $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
                $stmt->execute([$doctorId]);
                $doctorName = $stmt->fetchColumn() ?: 'Unknown';
            }

            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);

            logAudit($pdo, $user_id, $username, $user_role, 'assignment', 'queue', 'Assigned doctor to patient: ' . $patientName . ' -> ' . $doctorName, $queueId, 'queue', null, $doctorName);

            echo json_encode(['success' => true, 'message' => 'Doctor assigned successfully', 'doctor_name' => $doctorName]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Queue Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>