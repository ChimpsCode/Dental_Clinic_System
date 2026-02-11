<?php
/**
 * Prescription Actions API - Handles prescription-related AJAX requests for Dentists
 */

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action'];

// Get current user info from session
$current_user_id = $_SESSION['user_id'] ?? null;
$current_username = $_SESSION['username'] ?? null;
$current_role = $_SESSION['role'] ?? null;

// Allow only dentists for prescription operations
if ($current_role !== 'dentist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get action from POST
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get_patients':
            // Get all patients for dropdown
            $stmt = $pdo->query("
                SELECT id, CONCAT(first_name, ' ', last_name) as patient_name, age
                FROM patients 
                ORDER BY first_name, last_name
            ");
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['patients'] = $patients;
            break;
            
        case 'get_prescriptions':
            // Get all prescriptions with optional filtering
            $patient_id = $input['patient_id'] ?? null;
            $search = $input['search'] ?? '';
            $page = $input['page'] ?? 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($patient_id) {
                $whereClause .= " AND p.id = ?";
                $params[] = $patient_id;
            }
            
            if ($search) {
                $whereClause .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR pr.medications LIKE ? OR pr.diagnosis LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get prescriptions with patient and doctor info
            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.age,
                       u.full_name as doctor_name
                FROM prescriptions pr
                LEFT JOIN patients p ON pr.patient_id = p.id
                LEFT JOIN users u ON pr.doctor_id = u.id
                $whereClause
                ORDER BY pr.issue_date DESC, pr.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM prescriptions pr
                LEFT JOIN patients p ON pr.patient_id = p.id
                $whereClause
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();
            
            $response['success'] = true;
            $response['prescriptions'] = $prescriptions;
            $response['pagination'] = [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ];
            break;
            
        case 'get_patient_info':
            // Get patient medical information for safety display
            $patient_id = $input['patient_id'] ?? 0;
            
            if (!$patient_id) {
                $response['message'] = 'Patient ID is required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT p.id, CONCAT(p.first_name, ' ', p.last_name) as patient_name, 
                       p.age, p.phone,
                       mh.allergies, mh.current_medications, mh.medical_conditions
                FROM patients p
                LEFT JOIN medical_history mh ON p.id = mh.patient_id
                WHERE p.id = ?
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient) {
                $response['success'] = true;
                $response['patient'] = $patient;
            } else {
                $response['message'] = 'Patient not found';
            }
            break;
            
        case 'create_prescription':
            // Create new prescription
            $patient_id = $input['patient_id'] ?? 0;
            $medications = $input['medications'] ?? '';
            $diagnosis = $input['diagnosis'] ?? '';
            $instructions = $input['instructions'] ?? '';
            $issue_date = $input['issue_date'] ?? date('Y-m-d');
            
            if (!$patient_id || !$medications || !$diagnosis) {
                $response['message'] = 'Patient ID, medications, and diagnosis are required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO prescriptions (patient_id, doctor_id, medications, diagnosis, instructions, issue_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $patient_id,
                $current_user_id,
                $medications,
                $diagnosis,
                $instructions,
                $issue_date
            ]);
            
            if ($result) {
                $prescription_id = $pdo->lastInsertId();
                
                // Get patient name for response
                $patientStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as patient_name FROM patients WHERE id = ?");
                $patientStmt->execute([$patient_id]);
                $patient_name = $patientStmt->fetchColumn();
                
                $response['success'] = true;
                $response['message'] = "Prescription created successfully for $patient_name";
                $response['prescription_id'] = $prescription_id;
            } else {
                $response['message'] = 'Failed to create prescription';
            }
            break;
            
        case 'get_prescription':
            // Get single prescription details
            $prescription_id = $input['prescription_id'] ?? 0;
            
            if (!$prescription_id) {
                $response['message'] = 'Prescription ID is required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.age, p.phone,
                       u.full_name as doctor_name
                FROM prescriptions pr
                LEFT JOIN patients p ON pr.patient_id = p.id
                LEFT JOIN users u ON pr.doctor_id = u.id
                WHERE pr.id = ?
            ");
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prescription) {
                $response['success'] = true;
                $response['prescription'] = $prescription;
            } else {
                $response['message'] = 'Prescription not found';
            }
            break;
            
        case 'update_prescription':
            // Update existing prescription
            $prescription_id = $input['prescription_id'] ?? 0;
            $medications = $input['medications'] ?? '';
            $diagnosis = $input['diagnosis'] ?? '';
            $instructions = $input['instructions'] ?? '';
            $issue_date = $input['issue_date'] ?? '';
            
            if (!$prescription_id || !$medications || !$diagnosis) {
                $response['message'] = 'Prescription ID, medications, and diagnosis are required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE prescriptions 
                SET medications = ?, diagnosis = ?, instructions = ?, issue_date = ?
                WHERE id = ? AND doctor_id = ?
            ");
            $result = $stmt->execute([$medications, $diagnosis, $instructions, $issue_date, $prescription_id, $current_user_id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Prescription updated successfully';
            } else {
                $response['message'] = 'Failed to update prescription';
            }
            break;
            
        case 'delete_prescription':
            // Delete prescription
            $prescription_id = $input['prescription_id'] ?? 0;
            
            if (!$prescription_id) {
                $response['message'] = 'Prescription ID is required';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ? AND doctor_id = ?");
            $result = $stmt->execute([$prescription_id, $current_user_id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Prescription deleted successfully';
            } else {
                $response['message'] = 'Failed to delete prescription';
            }
            break;
            
        default:
            $response['message'] = 'Unknown action: ' . $action;
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Prescription actions error: " . $e->getMessage());
}

echo json_encode($response);
?>