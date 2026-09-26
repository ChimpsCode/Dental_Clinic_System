<?php
/**
 * Appointment SMS reminders.
 *
 * Sends one reminder 24 hours before the visit and another 2 hours before.
 * Run every 5 to 15 minutes. Appointment and dashboard pages also trigger this
 * when someone is using the clinic system.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment_reminder_helper.php';

sendDueAppointmentReminders($pdo, true);
