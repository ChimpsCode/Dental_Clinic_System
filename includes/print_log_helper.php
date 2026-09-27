<?php
/**
 * Print Log Helper - records every print (who printed what, and when) for the Print Activity report.
 */

function ensurePrintLogsTable($pdo) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'print_logs'")->rowCount() > 0;
        if ($exists) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS print_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(50) NULL,
                user_role VARCHAR(50) NULL,
                document_type VARCHAR(50) NOT NULL,
                details VARCHAR(255) NULL,
                reference_table VARCHAR(50) NULL,
                reference_id INT NULL,
                page VARCHAR(100) NULL,
                ip_address VARCHAR(45) NULL,
                printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_print_logs_printed_at (printed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // One-time backfill of invoice prints that were only recorded in audit_logs
        $pdo->exec("
            INSERT INTO print_logs (user_id, username, user_role, document_type, details, reference_table, reference_id, page, ip_address, printed_at)
            SELECT a.user_id, a.username, a.user_role, 'Invoice',
                   CONCAT('INV-', LPAD(a.affected_id, 4, '0'),
                          IFNULL(CONCAT(' - ', p.first_name, ' ', p.last_name), '')),
                   'billing', a.affected_id, NULL, a.ip_address, a.created_at
            FROM audit_logs a
            LEFT JOIN billing b ON b.id = a.affected_id
            LEFT JOIN patients p ON p.id = b.patient_id
            WHERE a.description LIKE 'Printed invoice%'
            ORDER BY a.created_at
        ");
    } catch (Exception $e) {
        error_log('ensurePrintLogsTable error: ' . $e->getMessage());
    }
}

function logPrint($pdo, $documentType, $details, $referenceTable = null, $referenceId = null, $page = null) {
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        ensurePrintLogsTable($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO print_logs (user_id, username, user_role, document_type, details, reference_table, reference_id, page, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null,
            strtolower((string)($_SESSION['role'] ?? '')) ?: null,
            mb_substr((string)$documentType, 0, 50),
            $details !== null ? mb_substr((string)$details, 0, 255) : null,
            $referenceTable !== null ? mb_substr((string)$referenceTable, 0, 50) : null,
            $referenceId !== null && $referenceId !== '' ? (int)$referenceId : null,
            $page !== null ? mb_substr((string)$page, 0, 100) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log('logPrint error: ' . $e->getMessage());
        return false;
    }
}
