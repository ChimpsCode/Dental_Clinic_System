<?php
/**
 * Audit Helper - Functions for logging system activities
 * Include this file in any script that needs audit logging
 */

require_once __DIR__ . '/../config/database.php';

// Helper function to get user info safely
function getUserInfo($pdo, $user_id, $username) {
    $user_info = null;
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_info) {
                $user_info['full_name'] = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
            }
        } catch (Exception $e) {
            error_log("Error getting user info: " . $e->getMessage());
        }
    } elseif ($username) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_info) {
                $user_info['full_name'] = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
            }
        } catch (Exception $e) {
            error_log("Error getting user info: " . $e->getMessage());
        }
    }
    
    return $user_info ?: [
        'id' => 0,
        'username' => $username ?? 'unknown',
        'full_name' => $username ?? 'Unknown User',
        'role' => null
    ];
}

/**
 * Log an audit event
 * 
 * @param PDO $pdo Database connection
 * @param int|null $user_id User ID (NULL for failed logins)
 * @param string|null $username Username (NULL if user doesn't exist)
 * @param string|null $user_role User role (admin, dentist, staff)
 * @param string $action_type Action type (login, logout, create, read, update, delete, payment, status_change, failed_login)
 * @param string $module Module/section (users, patients, appointments, queue, treatments, billing, payments, inquiries, treatment_plans)
 * @param string $description Human-readable description
 * @param int|null $record_id ID of affected record
 * @param string|null $record_type Table name affected
 * @param string|null $old_value Previous value (for updates)
 * @param string|null $new_value New value (for updates)
 * @return bool Success status
 */
function logAudit($pdo, $user_id, $username, $user_role, $action_type, $module, $description, $record_id = null, $record_type = null, $old_value = null, $new_value = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, username, user_role, action_type, module, record_id, record_type,
                description, old_value, new_value, affected_table, affected_id,
                ip_address, user_agent, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'success'
            )
        ");
        
        $stmt->execute([
            $user_id,
            $username,
            $user_role,
            $action_type,
            $module,
            $record_id,
            $record_type,
            $description,
            $old_value,
            $new_value,
            $record_type,
            $record_id,
            $ip_address,
            $user_agent
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Audit logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a failed login attempt
 * 
 * @param PDO $pdo Database connection
 * @param string $username Attempted username
 * @param string $reason Failure reason (invalid_password, user_not_found, account_disabled)
 */
function logFailedLogin($pdo, $username, $reason = 'invalid_password') {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $description = "Failed login attempt";
        if ($reason === 'invalid_password') {
            $description .= " - Invalid password for user: $username";
        } elseif ($reason === 'user_not_found') {
            $description .= " - User not found: $username";
        } elseif ($reason === 'account_disabled') {
            $description .= " - Account disabled for user: $username";
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, username, user_role, action_type, module, record_id, record_type,
                description, old_value, new_value, affected_table, affected_id,
                ip_address, user_agent, status
            ) VALUES (
                NULL, ?, NULL, 'failed_login', 'users', NULL, 'users',
                ?, NULL, NULL, 'users', NULL,
                ?, ?, 'failed'
            )
        ");
        
        $stmt->execute([
            $username,
            $description,
            $ip_address,
            $user_agent
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed login logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with filters
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Filter options (user_id, action_type, module, status, date_from, date_to, search)
 * @param int $limit Number of records
 * @param int $offset Offset for pagination
 * @return array Audit logs
 */
function getAuditLogs($pdo, $filters = [], $limit = 50, $offset = 0) {
    $where = "1=1";
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $where .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['action_type'])) {
        $where .= " AND action_type = ?";
        $params[] = $filters['action_type'];
    }
    
    if (!empty($filters['module'])) {
        $where .= " AND module = ?";
        $params[] = $filters['module'];
    }
    
    if (!empty($filters['status'])) {
        $where .= " AND status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['user_role'])) {
        $where .= " AND user_role = ?";
        $params[] = $filters['user_role'];
    }
    
    if (!empty($filters['date_from'])) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $where .= " AND description LIKE ?";
        $params[] = '%' . $filters['search'] . '%';
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM audit_logs WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get records
    $sql = "SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    return [
        'logs' => $logs,
        'total' => $totalRecords
    ];
}

/**
 * Get audit statistics
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date to get stats for (default: today)
 * @return array Statistics
 */
function getAuditStats($pdo, $date = null) {
    $date = $date ?? date('Y-m-d');
    
    $stats = [];
    
    // Total logins today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'login' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['logins_today'] = $stmt->fetchColumn();
    
    // Failed logins today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'failed_login' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['failed_logins_today'] = $stmt->fetchColumn();
    
    // Payments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'payment' AND DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['payments_today'] = $stmt->fetchColumn();
    
    // Total records
    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    $stats['total_logs'] = $stmt->fetchColumn();
    
    return $stats;
}
?>
