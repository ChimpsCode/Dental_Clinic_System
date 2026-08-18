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

// Allow any request for now - archive is always accessible
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
//     exit;
// }

require_once '../../config/database.php';

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
        
        if ($module === 'patients') {
            handleGetArchivedPatients($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo);
            return;
        }
        
        if ($module === 'users') {
            handleGetArchivedUsers($pdo, $page, $limit, $offset, $search);
            return;
        }
        
        // Build WHERE clause for other modules
        $where = "is_archived = 1";
        $params = [];
        
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
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Get archived users (patients, dentists, staff)
 */
function handleGetArchivedUsers($pdo, $page, $limit, $offset, $search) {
    $where = "is_archived = 1";
    $params = [];
    
    // Search by name
    if (!empty($search)) {
        $where .= " AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Role filter
    $roleFilter = isset($_POST['role']) ? trim($_POST['role']) : '';
    if (!empty($roleFilter)) {
        if ($roleFilter === 'patient') {
            $where .= " AND (role IS NULL OR role = 'Patient')";
        } else {
            $where .= " AND LOWER(role) = ?";
            $params[] = strtolower($roleFilter);
        }
    } else {
        // Default: show only employees (NOT patients) - exclude Patient role
        $where .= " AND role IS NOT NULL AND role != 'Patient'";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM users u WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Check if archived_at column exists in users table
    $hasArchivedAt = false;
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'archived_at'");
    if ($colCheck->fetch(PDO::FETCH_ASSOC)) {
        $hasArchivedAt = true;
    }
    
    $dateCol = $hasArchivedAt ? 'archived_at' : 'created_at';
    
    // Get records
    $sql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.$dateCol as archived_at,
            COALESCE(u.role, 'Patient') as role_name
            FROM users u
            WHERE $where
            ORDER BY u.$dateCol DESC
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
 * Get archived appointments with patient details
 */
function handleGetArchivedAppointments($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo) {
    // Build WHERE clause
    $where = "a.is_archived = 1";
    $params = [];
    
    // Search by patient name
    if (!empty($search)) {
        $where .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?)";
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
    $countSql = "SELECT COUNT(*) FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get records with patient info
    $sql = "SELECT a.*, 
            CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) as patient_name,
            p.phone as patient_phone
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE $where
            ORDER BY a.is_archived DESC, a.id DESC
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
 * Get archived patients with separate name fields
 * Includes both patients table records and archived user accounts with role='Patient'
 */
function handleGetArchivedPatients($pdo, $page, $limit, $offset, $search, $dateFrom, $dateTo) {
    // Search by name
    $searchClause = '';
    $searchParams = [];
    if (!empty($search)) {
        $searchClause = " AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)";
        $searchParam = "%$search%";
        $searchParams = [$searchParam, $searchParam, $searchParam];
    }
    
    // Date filter for patients (uses deleted_at or created_at)
    $dateFromClausePat = '';
    $dateToClausePat = '';
    $dateFromClauseUsr = '';
    $dateToClauseUsr = '';
    $dateParams = [];
    if (!empty($dateFrom)) {
        $dateFromClausePat = " AND DATE(COALESCE(deleted_at, created_at)) >= ?";
        $dateFromClauseUsr = " AND DATE(created_at) >= ?";
        $dateParams[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $dateToClausePat = " AND DATE(COALESCE(deleted_at, created_at)) <= ?";
        $dateToClauseUsr = " AND DATE(created_at) <= ?";
        $dateParams[] = $dateTo;
    }
    
    // Count from patients table
    $countPatSql = "SELECT COUNT(*) FROM patients WHERE is_archived = 1$searchClause$dateFromClausePat$dateToClausePat";
    $countPatStmt = $pdo->prepare($countPatSql);
    $countPatStmt->execute(array_merge($searchParams, $dateParams));
    $totalPatients = (int)$countPatStmt->fetchColumn();
    
    // Count from users table (role='Patient')
    $countUsrSql = "SELECT COUNT(*) FROM users WHERE is_archived = 1 AND role = 'Patient'$searchClause$dateFromClauseUsr$dateToClauseUsr";
    $countUsrStmt = $pdo->prepare($countUsrSql);
    $countUsrStmt->execute(array_merge($searchParams, $dateParams));
    $totalUsers = (int)$countUsrStmt->fetchColumn();
    
    $total = $totalPatients + $totalUsers;
    
    // Check if deleted_at column exists in patients table
    $hasDeletedAt = false;
    $colCheck = $pdo->query("SHOW COLUMNS FROM patients LIKE 'deleted_at'");
    if ($colCheck->fetch(PDO::FETCH_ASSOC)) {
        $hasDeletedAt = true;
    }
    $dateColPat = $hasDeletedAt ? 'deleted_at' : 'created_at';
    
    // Get patients table records
    $patSql = "SELECT 
            CONCAT('pat_', id) as id,
            first_name, middle_name, last_name, suffix,
            date_of_birth, gender, phone, email,
            COALESCE($dateColPat, created_at) as deleted_at
            FROM patients
            WHERE is_archived = 1$searchClause$dateFromClausePat$dateToClausePat
            ORDER BY deleted_at DESC";
    
    $patStmt = $pdo->prepare($patSql);
    $patStmt->execute(array_merge($searchParams, $dateParams));
    $patientRecords = $patStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users table records (archived patient accounts)
    $usrSql = "SELECT 
            CONCAT('usr_', id) as id,
            first_name, middle_name, last_name, '' as suffix,
            date_of_birth, gender, phone, email,
            created_at as deleted_at
            FROM users
            WHERE is_archived = 1 AND role = 'Patient'$searchClause$dateFromClauseUsr$dateToClauseUsr
            ORDER BY deleted_at DESC";
    
    $usrStmt = $pdo->prepare($usrSql);
    $usrStmt->execute(array_merge($searchParams, $dateParams));
    $userRecords = $usrStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort records by deleted_at DESC
    $allRecords = array_merge($patientRecords, $userRecords);
    usort($allRecords, function($a, $b) {
        $dateA = $a['deleted_at'] ?? '';
        $dateB = $b['deleted_at'] ?? '';
        return strcmp($dateB, $dateA);
    });
    
    // Apply LIMIT and OFFSET in PHP
    $pageRecords = array_slice($allRecords, $offset, $limit);
    
    echo json_encode([
        'success' => true,
        'records' => $pageRecords,
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
        $where .= " AND CONCAT(first_name, ' ', last_name) LIKE ?";
        $params[] = "%$search%";
    }
    
    // Date filter (created_at, not deleted_at)
    if (!empty($dateFrom)) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM inquiries WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get records
    $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, phone, COALESCE(concern, inquiry_message) as message, COALESCE(concern, inquiry_message) as subject, concern, notes, created_at as submitted_at, deleted_at
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
    
    try {
        // Check if archived_at column exists in users table
        $hasArchivedAt = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'archived_at'")->fetch(PDO::FETCH_ASSOC);
        $userRestoreSet = $hasArchivedAt ? "is_archived = 0, archived_at = NULL" : "is_archived = 0";
        
        // Special handling for patients module (supports prefixed IDs: pat_X, usr_X)
        if ($module === 'patients') {
            $patientIds = [];
            $userIds = [];
            foreach ($ids as $rawId) {
                $rawId = trim((string)$rawId);
                if (strpos($rawId, 'pat_') === 0) {
                    $numId = (int)substr($rawId, 4);
                    if ($numId > 0) $patientIds[] = $numId;
                } elseif (strpos($rawId, 'usr_') === 0) {
                    $numId = (int)substr($rawId, 4);
                    if ($numId > 0) $userIds[] = $numId;
                }
            }
            
            $totalRestored = 0;
            
            // Restore patient records
            if (!empty($patientIds)) {
                $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                $patStmt = $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE id IN ($placeholders) AND is_archived = 1");
                $patStmt->execute($patientIds);
                $totalRestored += $patStmt->rowCount();
                
                // Also restore matching users
                foreach ($patientIds as $pid) {
                    $patInfo = $pdo->prepare("SELECT first_name, last_name, email FROM patients WHERE id = ?");
                    $patInfo->execute([$pid]);
                    $patient = $patInfo->fetch(PDO::FETCH_ASSOC);
                    if ($patient) {
                        $email = trim($patient['email'] ?? '');
                        if ($email !== '') {
                            $usrUpd = $pdo->prepare("UPDATE users SET $userRestoreSet WHERE email = ? AND is_archived = 1");
                            $usrUpd->execute([$email]);
                        }
                        $firstName = trim($patient['first_name'] ?? '');
                        $lastName = trim($patient['last_name'] ?? '');
                        if ($firstName && $lastName) {
                            $usrUpd = $pdo->prepare("UPDATE users SET $userRestoreSet WHERE first_name = ? AND last_name = ? AND is_archived = 1");
                            $usrUpd->execute([$firstName, $lastName]);
                        }
                    }
                }
            }
            
            // Restore user records (patient accounts)
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                
                // Get user details before restoring (to cascade to patients table)
                $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id IN ($placeholders)");
                $userStmt->execute($userIds);
                $usersToRestore = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $usrStmt = $pdo->prepare("UPDATE users SET $userRestoreSet WHERE id IN ($placeholders) AND is_archived = 1");
                $usrStmt->execute($userIds);
                $totalRestored += $usrStmt->rowCount();
                
                // Cascase to patients table
                foreach ($usersToRestore as $user) {
                    $email = trim($user['email'] ?? '');
                    if ($email !== '') {
                        $patUpd = $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE email = ? AND is_archived = 1");
                        $patUpd->execute([$email]);
                    }
                    $firstName = trim($user['first_name'] ?? '');
                    $lastName = trim($user['last_name'] ?? '');
                    if ($firstName && $lastName) {
                        $patUpd = $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE first_name = ? AND last_name = ? AND is_archived = 1");
                        $patUpd->execute([$firstName, $lastName]);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => $totalRestored . ' record(s) restored successfully',
                'count' => $totalRestored
            ]);
            return;
        }
        
        // Sanitize IDs for other modules
        $ids = array_filter($ids, 'is_numeric');
        $ids = array_map('intval', $ids);
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Special handling for users table
        if ($module === 'users') {
            // Get user details before restoring
            $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id IN ($placeholders)");
            $userStmt->execute($ids);
            $usersToRestore = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Restore users
            $stmt = $pdo->prepare("UPDATE users SET $userRestoreSet WHERE id IN ($placeholders)");
            $result = $stmt->execute($ids);
            
            // Also restore matching patients in patients table
            foreach ($usersToRestore as $user) {
                $email = trim($user['email'] ?? '');
                if ($email !== '') {
                    $patientCheck = $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE email = ? AND is_archived = 1");
                    $patientCheck->execute([$email]);
                }
                $firstName = trim($user['first_name'] ?? '');
                $lastName = trim($user['last_name'] ?? '');
                if ($firstName && $lastName) {
                    $patientCheck = $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE first_name = ? AND last_name = ? AND is_archived = 1");
                    $patientCheck->execute([$firstName, $lastName]);
                }
            }
        } else {
            $stmt = $pdo->prepare("UPDATE $module SET is_archived = 0, deleted_at = NULL WHERE id IN ($placeholders)");
            $result = $stmt->execute($ids);
        }
        
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
 * Remove consultation records attached to queue entries before deleting the queue.
 * This avoids FK violations when archived patients/queue rows are permanently removed.
 */
function deleteQueueConsultations($pdo, array $queueIds = [], array $patientIds = []) {
    $queueIds = array_values(array_unique(array_filter(array_map('intval', $queueIds))));
    $patientIds = array_values(array_unique(array_filter(array_map('intval', $patientIds))));

    $clauses = [];
    $params = [];

    if (!empty($queueIds)) {
        $queuePlaceholders = implode(',', array_fill(0, count($queueIds), '?'));
        $clauses[] = "queue_id IN ($queuePlaceholders)";
        $params = array_merge($params, $queueIds);
    }

    if (!empty($patientIds)) {
        $patientPlaceholders = implode(',', array_fill(0, count($patientIds), '?'));
        $clauses[] = "patient_id IN ($patientPlaceholders)";
        $params = array_merge($params, $patientIds);
    }

    if (empty($clauses)) {
        return;
    }

    $sql = "DELETE FROM consultations WHERE " . implode(' OR ', $clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Permanently delete records
 */
function handleDeleteForever($pdo, $module, $ids) {
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No records selected']);
        return;
    }
    
    try {
        // Special handling for patients module (supports prefixed IDs: pat_X, usr_X)
        if ($module === 'patients') {
            $patientIds = [];
            $userIds = [];
            foreach ($ids as $rawId) {
                $rawId = trim((string)$rawId);
                if (strpos($rawId, 'pat_') === 0) {
                    $numId = (int)substr($rawId, 4);
                    if ($numId > 0) $patientIds[] = $numId;
                } elseif (strpos($rawId, 'usr_') === 0) {
                    $numId = (int)substr($rawId, 4);
                    if ($numId > 0) $userIds[] = $numId;
                }
            }
            
            $totalDeleted = 0;
            
            // Delete patient records
            if (!empty($patientIds)) {
                $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                $pdo->beginTransaction();
                try {
                    // Get emails of patients being deleted
                    $patStmt = $pdo->prepare("SELECT email FROM patients WHERE id IN ($placeholders) AND is_archived = 1");
                    $patStmt->execute($patientIds);
                    $patientEmails = $patStmt->fetchAll(PDO::FETCH_COLUMN);
                    $patientEmails = array_filter(array_unique($patientEmails));
                    
                    // Delete related records first
                    $pdo->prepare("DELETE FROM medical_history WHERE patient_id IN ($placeholders)")->execute($patientIds);
                    $pdo->prepare("DELETE FROM dental_history WHERE patient_id IN ($placeholders)")->execute($patientIds);

                    $queueStmt = $pdo->prepare("SELECT id FROM queue WHERE patient_id IN ($placeholders)");
                    $queueStmt->execute($patientIds);
                    $queueIds = $queueStmt->fetchAll(PDO::FETCH_COLUMN);
                    deleteQueueConsultations($pdo, $queueIds);
                    $pdo->prepare("DELETE FROM queue WHERE patient_id IN ($placeholders)")->execute($patientIds);
                    $pdo->prepare("DELETE FROM appointments WHERE patient_id IN ($placeholders)")->execute($patientIds);
                    
                    // Find and delete matching users by email
                    if (!empty($patientEmails)) {
                        $emailPlaceholders = implode(',', array_fill(0, count($patientEmails), '?'));
                        $usrDel = $pdo->prepare("DELETE FROM users WHERE email IN ($emailPlaceholders) AND is_archived = 1")->execute(array_values($patientEmails));
                    }
                    
                    // Delete the patients
                    $delStmt = $pdo->prepare("DELETE FROM patients WHERE id IN ($placeholders) AND is_archived = 1");
                    $delStmt->execute($patientIds);
                    $totalDeleted += $delStmt->rowCount();
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            
            // Delete user records (patient accounts)
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $pdo->beginTransaction();
                try {
                    // Get emails of users being deleted
                    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id IN ($placeholders) AND is_archived = 1");
                    $userStmt->execute($userIds);
                    $userEmails = $userStmt->fetchAll(PDO::FETCH_COLUMN);
                    $userEmails = array_filter(array_unique($userEmails));
                    
                    if (!empty($userEmails)) {
                        $emailPlaceholders = implode(',', array_fill(0, count($userEmails), '?'));
                        
                        // Find matching patients by email
                        $patStmt = $pdo->prepare("SELECT id FROM patients WHERE email IN ($emailPlaceholders) AND is_archived = 1");
                        $patStmt->execute(array_values($userEmails));
                        $matchingPatientIds = $patStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($matchingPatientIds)) {
                            $patPlaceholders = implode(',', array_fill(0, count($matchingPatientIds), '?'));
                            $pdo->prepare("DELETE FROM medical_history WHERE patient_id IN ($patPlaceholders)")->execute($matchingPatientIds);
                            $pdo->prepare("DELETE FROM dental_history WHERE patient_id IN ($patPlaceholders)")->execute($matchingPatientIds);

                            $queueStmt = $pdo->prepare("SELECT id FROM queue WHERE patient_id IN ($patPlaceholders)");
                            $queueStmt->execute($matchingPatientIds);
                            $queueIds = $queueStmt->fetchAll(PDO::FETCH_COLUMN);
                            deleteQueueConsultations($pdo, $queueIds);
                            $pdo->prepare("DELETE FROM queue WHERE patient_id IN ($patPlaceholders)")->execute($matchingPatientIds);
                            $pdo->prepare("DELETE FROM appointments WHERE patient_id IN ($patPlaceholders)")->execute($matchingPatientIds);
                            $pdo->prepare("DELETE FROM patients WHERE id IN ($patPlaceholders) AND is_archived = 1")->execute($matchingPatientIds);
                        }
                    }
                    
                    // Delete the users last
                    $delStmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_archived = 1");
                    $delStmt->execute($userIds);
                    $totalDeleted += $delStmt->rowCount();
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => $totalDeleted . ' record(s) permanently deleted',
                'count' => $totalDeleted
            ]);
            return;
        }
        
        // Sanitize IDs for other modules
        $ids = array_filter($ids, 'is_numeric');
        $ids = array_map('intval', $ids);
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        if ($module === 'users') {
            // ---------- USERS: cascade to patients ----------
            $pdo->beginTransaction();
            try {
                // Get emails of users being deleted
                $userStmt = $pdo->prepare("SELECT email FROM users WHERE id IN ($placeholders) AND is_archived = 1");
                $userStmt->execute($ids);
                $userEmails = $userStmt->fetchAll(PDO::FETCH_COLUMN);
                $userEmails = array_filter(array_unique($userEmails));
                
                if (!empty($userEmails)) {
                    $emailPlaceholders = implode(',', array_fill(0, count($userEmails), '?'));
                    
                    // Find matching patients by email
                    $patStmt = $pdo->prepare("SELECT id FROM patients WHERE email IN ($emailPlaceholders) AND is_archived = 1");
                    $patStmt->execute(array_values($userEmails));
                    $patientIds = $patStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($patientIds)) {
                        $patPlaceholders = implode(',', array_fill(0, count($patientIds), '?'));
                        
                        $pdo->prepare("DELETE FROM medical_history WHERE patient_id IN ($patPlaceholders)")->execute($patientIds);
                        $pdo->prepare("DELETE FROM dental_history WHERE patient_id IN ($patPlaceholders)")->execute($patientIds);

                        $queueStmt = $pdo->prepare("SELECT id FROM queue WHERE patient_id IN ($patPlaceholders)");
                        $queueStmt->execute($patientIds);
                        $queueIds = $queueStmt->fetchAll(PDO::FETCH_COLUMN);
                        deleteQueueConsultations($pdo, $queueIds);
                        $pdo->prepare("DELETE FROM queue WHERE patient_id IN ($patPlaceholders)")->execute($patientIds);
                        $pdo->prepare("DELETE FROM appointments WHERE patient_id IN ($patPlaceholders)")->execute($patientIds);
                        $pdo->prepare("DELETE FROM patients WHERE id IN ($patPlaceholders) AND is_archived = 1")->execute($patientIds);
                    }
                }
                
                // Delete the users last
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_archived = 1");
                $result = $stmt->execute($ids);
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } else {
            if ($module === 'queue') {
                deleteQueueConsultations($pdo, $ids);
            }

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
