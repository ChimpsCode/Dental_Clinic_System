<?php
/**
 * Archive Actions Handler
 * 
 * Handles all archive-related actions:
 * - Get archived records
 * - Restore records
 * - Permanent delete records
 * 
 * @package Dental_Clinic_System
 * @version 1.0
 * @date 2026-02-04
 */

session_start();
header('Content-Type: application/json');

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once 'config/database.php';

$action = $_POST['action'] ?? '';
$module = $_POST['module'] ?? '';
$ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];

// Validate module
$validModules = ['patients', 'appointments', 'queue', 'treatment_plans', 'services', 'inquiries', 'users'];
if (!in_array($module, $validModules)) {
    echo json_encode(['success' => false, 'message' => 'Invalid module']);
    exit;
}

// Check if archive column exists
$checkColumn = $pdo->query("SHOW COLUMNS FROM $module LIKE 'is_archived'");
if ($checkColumn->rowCount() == 0) {
    echo json_encode(['success' => false, 'message' => 'Archive system not configured. Please run database migration.']);
    exit;
}

switch ($action) {
    case 'get_archived':
        handleGetArchived($pdo, $module);
        break;
        
    case 'restore':
        handleRestore($pdo, $module, $ids);
        break;
        
    case 'delete_forever':
        handleDeleteForever($pdo, $module, $ids);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get archived records for a module
 */
function handleGetArchived($pdo, $module) {
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $limit = 7; // Same as patient records
    $offset = ($page - 1) * $limit;
    
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $dateFrom = isset($_POST['dateFrom']) ? trim($_POST['dateFrom']) : '';
    $dateTo = isset($_POST['dateTo']) ? trim($_POST['dateTo']) : '';
    
    try {
        // Handle different modules
        if ($module === 'appointments') {
            handleGetArchivedAppointments($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo);
            return;
        }
        
        if ($module === 'inquiries') {
            handleGetArchivedInquiries($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo);
            return;
        }
        
        // Build WHERE clause for other modules
        $where = "is_archived = 1";
        $params = [];
        
        // Search by patient name (for patients module)
        if (!empty($search) && $module === 'patients') {
            $where .= " AND full_name LIKE ?";
            $params[] = "%$search%";
        }
        
        // Date filters
        if (!empty($dateFrom)) {
            $where .= " AND DATE(deleted_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where .= " AND DATE(deleted_at) <= ?";
            $params[] = $dateTo;
        }
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $module WHERE $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get records
        $sql = "SELECT * FROM $module WHERE $where ORDER BY deleted_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'records' => $records,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit),
            'current_page' => $page
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to fetch records: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get archived appointments with patient details
 */
function handleGetArchivedAppointments($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo) {
    // Build WHERE clause
    $where = "a.is_archived = 1";
    $params = [];
    
    // Search by patient name
    if (!empty($search)) {
        $where .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR CONCAT(a.first_name, ' ', a.last_name) LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Date filter (appointment date, not deleted_at)
    if (!empty($dateFrom)) {
        $where .= " AND a.appointment_date >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $where .= " AND a.appointment_date <= ?";
        $params[] = $dateTo;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM appointments a WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get records with patient info
    $sql = "SELECT a.*, 
            CONCAT(a.first_name, ' ', IFNULL(a.middle_name, ''), ' ', a.last_name) as patient_name,
            a.appointment_date,
            a.appointment_time,
            a.treatment,
            a.status,
            a.deleted_at,
            p.phone as patient_phone
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE $where
            ORDER BY a.deleted_at DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'total' => (int)$total,
        'pages' => (int)ceil($total / $limit),
        'current_page' => $page
    ]);
}

/**
 * Get archived inquiries
 */
function handleGetArchivedInquiries($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo) {
    // Build WHERE clause
    $where = "is_archived = 1";
    $params = [];
    
    // Search by name
    if (!empty($search)) {
        $where .= " AND full_name LIKE ?";
        $params[] = "%$search%";
    }
    
    // Date filter (submitted_at, not deleted_at)
    if (!empty($dateFrom)) {
        $where .= " AND DATE(submitted_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $where .= " AND DATE(submitted_at) <= ?";
        $params[] = $dateTo;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM inquiries WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get records
    $sql = "SELECT id, full_name, email, phone, subject, message, submitted_at, deleted_at
            FROM inquiries
            WHERE $where
            ORDER BY deleted_at DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'total' => (int)$total,
        'pages' => (int)ceil($total / $limit),
        'current_page' => $page
    ]);
}

/**
 * Restore archived records
 */
function handleRestore($pdo, $module, $ids) {
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No records selected']);
        return;
    }
    
    // Sanitize IDs
    $ids = array_filter($ids, 'is_numeric');
    $ids = array_map('intval', $ids);
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $stmt = $pdo->prepare("UPDATE $module SET is_archived = 0, deleted_at = NULL WHERE id IN ($placeholders)");
        $result = $stmt->execute($ids);
        
        if ($result) {
            $count = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => $count . ' record(s) restored successfully',
                'count' => $count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to restore records']);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Permanently delete records
 */
function handleDeleteForever($pdo, $module, $ids) {
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No records selected']);
        return;
    }
    
    // Sanitize IDs
    $ids = array_filter($ids, 'is_numeric');
    $ids = array_map('intval', $ids);
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
        return;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        // For patients, also delete related records
        if ($module === 'patients') {
            $pdo->beginTransaction();
            
            try {
                // Delete related records first
                $pdo->prepare("DELETE FROM medical_history WHERE patient_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM dental_history WHERE patient_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM queue WHERE patient_id IN ($placeholders)")->execute($ids);
                
                // Delete the patients
                $stmt = $pdo->prepare("DELETE FROM patients WHERE id IN ($placeholders) AND is_archived = 1");
                $result = $stmt->execute($ids);
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // For other modules, just delete
            $stmt = $pdo->prepare("DELETE FROM $module WHERE id IN ($placeholders) AND is_archived = 1");
            $result = $stmt->execute($ids);
        }
        
        if ($result) {
            $count = $stmt->rowCount();
            echo json_encode([
                'success' => true,
                'message' => $count . ' record(s) permanently deleted',
                'count' => $count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete records']);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>
