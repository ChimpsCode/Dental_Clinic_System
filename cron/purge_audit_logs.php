<?php
/**
 * Purge Audit Logs older than 1 year
 *
 * Run as a cron job or trigger from admin page visits.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit_helper.php';

purgeOldAuditLogs($pdo);
