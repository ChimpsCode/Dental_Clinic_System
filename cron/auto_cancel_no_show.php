<?php
/**
 * Auto-Cancel No-Show Appointments
 * 
 * Run as a cron job or trigger from appointments page visits.
 * Cancels appointments that are past the no-show threshold
 * and have not been marked as arrived (check_in_time IS NULL).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auto_cancel_helper.php';

autoCancelNoShows($pdo);
