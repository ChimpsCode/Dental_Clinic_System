<?php

require_once __DIR__ . '/../../includes/session_bootstrap.php';

require_once __DIR__ . '/../../config/database.php';

function ensurePrescriptionTableColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM prescriptions');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        $alterStatements = [];
        if (!in_array('doctor_id', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN doctor_id INT(11) NULL';
        }
        if (!in_array('medications', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN medications TEXT NULL';
        }
        if (!in_array('instructions', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN instructions TEXT NULL';
        }
        if (!in_array('created_at', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        }
        if (!in_array('updated_at', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        }

        foreach ($alterStatements as $sql) {
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        error_log('Failed to ensure prescription table columns: ' . $e->getMessage());
    }
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid action'];

$current_role = $_SESSION['role'] ?? null;

if (!in_array($current_role, ['staff', 'dentist'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

ensurePrescriptionTableColumns($pdo);

try {
    switch ($action) {
        case 'get_prescriptions':
            $patient_id = $input['patient_id'] ?? null;
            $search = $input['search'] ?? '';
            $page = $input['page'] ?? 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $whereClause = "WHERE 1=1";
            $params = [];

            if ($patient_id) {
                $whereClause .= " AND pr.patient_id = ?";
                $params[] = $patient_id;
            }

            if ($search) {
                $whereClause .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR pr.medications LIKE ? OR pr.diagnosis LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.age,
                       CONCAT(u.first_name, ' ', u.last_name) as doctor_name
                FROM prescriptions pr
                LEFT JOIN patients p ON pr.patient_id = p.id
                LEFT JOIN users u ON pr.doctor_id = u.id
                $whereClause
                ORDER BY pr.issue_date DESC, pr.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        case 'get_prescription':
            $prescription_id = $input['prescription_id'] ?? 0;

            if (!$prescription_id) {
                $response['message'] = 'Prescription ID is required';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age, p.phone,
                       p.gender, p.street_address, p.city, p.province,
                       CONCAT(u.first_name, ' ', u.last_name) as doctor_name
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

        default:
            $response['message'] = 'Unknown action: ' . $action;
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Staff prescription actions error: " . $e->getMessage());
}

echo json_encode($response);
