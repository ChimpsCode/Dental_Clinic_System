<?php
/**
 * Create a scheduled clinic backup when Auto Backup is on and the chosen time has passed.
 * Run from Task Scheduler, or leave it to admin page loads, which call the same check.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup_helper.php';

runAutoBackupIfDue($pdo);
