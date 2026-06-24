<?php
/**
 * Run Database Migration
 * This script adds lookup tables and missing columns
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>RF Dental Clinic - Database Migration</h2>";
echo "<pre>";

try {
    $pdo->beginTransaction();
    
    // ==============================================
    // 1. CREATE LOOKUP TABLES
    // ==============================================
    
    // Patient Types
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `patient_types` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `type_name` varchar(50) NOT NULL,
          `description` text DEFAULT NULL,
          `discount_percentage` decimal(5,2) DEFAULT 0.00,
          `is_active` tinyint(1) DEFAULT 1,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ patient_types table created\n";
    
    // Insert seed data for patient_types
    $pdo->exec("DELETE FROM patient_types");
    $stmt = $pdo->prepare("INSERT INTO patient_types (type_name, description, discount_percentage) VALUES (?, ?, ?)");
    $stmt->execute(['Regular', 'Regular paying patient', 0.00]);
    $stmt->execute(['Senior Citizen', 'Senior citizen with discount', 20.00]);
    $stmt->execute(['PWD', 'Person with disability with discount', 20.00]);
    $stmt->execute(['Student', 'Student with school ID discount', 15.00]);
    $stmt->execute(['VIP', 'Very important patient', 0.00]);
    echo "✓ patient_types data inserted\n";
    
    // Appointment Statuses
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `appointment_statuses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `status_name` varchar(50) NOT NULL,
          `color` varchar(7) DEFAULT '#6c757d',
          `description` text DEFAULT NULL,
          `sort_order` int(11) DEFAULT 0,
          `is_active` tinyint(1) DEFAULT 1,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ appointment_statuses table created\n";
    
    $pdo->exec("DELETE FROM appointment_statuses");
    $stmt = $pdo->prepare("INSERT INTO appointment_statuses (status_name, color, description, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Scheduled', '#007bff', 'Appointment is booked', 1]);
    $stmt->execute(['Confirmed', '#28a745', 'Patient confirmed attendance', 2]);
    $stmt->execute(['In Progress', '#ffc107', 'Patient is being seen', 3]);
    $stmt->execute(['Completed', '#17a2b8', 'Appointment finished', 4]);
    $stmt->execute(['Cancelled', '#dc3545', 'Appointment cancelled', 5]);
    $stmt->execute(['No Show', '#6c757d', 'Patient did not show up', 6]);
    $stmt->execute(['Rescheduled', '#6610f2', 'Appointment moved to different date', 7]);
    echo "✓ appointment_statuses data inserted\n";
    
    // Queue Statuses
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `queue_statuses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `status_name` varchar(50) NOT NULL,
          `color` varchar(7) DEFAULT '#6c757d',
          `icon` varchar(50) DEFAULT NULL,
          `description` text DEFAULT NULL,
          `sort_order` int(11) DEFAULT 0,
          `is_active` tinyint(1) DEFAULT 1,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ queue_statuses table created\n";
    
    $pdo->exec("DELETE FROM queue_statuses");
    $stmt = $pdo->prepare("INSERT INTO queue_statuses (status_name, color, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Waiting', '#007bff', 'fa-clock', 'Patient is waiting', 1]);
    $stmt->execute(['Called', '#ffc107', 'fa-bell', 'Patient has been called', 2]);
    $stmt->execute(['In Procedure', '#17a2b8', 'fa-user-md', 'Currently in treatment', 3]);
    $stmt->execute(['Pending Payment', '#fd7e14', 'fa-money-bill', 'Awaiting payment', 4]);
    $stmt->execute(['Completed', '#28a745', 'fa-check', 'All done, paid', 5]);
    $stmt->execute(['On Hold', '#6c757d', 'fa-pause', 'Temporarily paused', 6]);
    $stmt->execute(['Cancelled', '#dc3545', 'fa-times', 'Cancelled', 7]);
    echo "✓ queue_statuses data inserted\n";
    
    // ==============================================
    // 2. ADD MISSING COLUMNS TO EXISTING TABLES
    // ==============================================
    
    // Check if service_id column exists in appointments
    $result = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'service_id'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `service_id` int(11) DEFAULT NULL AFTER `patient_id`");
        echo "✓ Added service_id to appointments\n";
    }
    
    // Check if status_id column exists in appointments
    $result = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'status_id'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `status_id` int(11) DEFAULT 1 AFTER `service_id`");
        echo "✓ Added status_id to appointments\n";
    }
    
    // Update existing appointments with service_id from treatment match
    $pdo->exec("
        UPDATE appointments a
        LEFT JOIN services s ON a.treatment = s.name
        SET a.service_id = s.id
        WHERE a.service_id IS NULL AND a.treatment IS NOT NULL
    ");
    echo "✓ Updated existing appointments with service_id\n";
    
    // Update existing appointments with status_id
    $pdo->exec("UPDATE appointments SET `status_id` = 1 WHERE `status` = 'scheduled' OR `status` = 'pending'");
    $pdo->exec("UPDATE appointments SET `status_id` = 4 WHERE `status` = 'completed'");
    $pdo->exec("UPDATE appointments SET `status_id` = 5 WHERE `status` = 'cancelled'");
    $pdo->exec("UPDATE appointments SET `status_id` = 2 WHERE `status` = 'confirmed'");
    echo "✓ Updated existing appointments with status_id\n";
    
    $pdo->commit();
    
    echo "\n========================================\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n";
    echo "\nSummary:\n";
    echo "- Created 3 new lookup tables\n";
    echo "- Added 2 columns to appointments table\n";
    echo "- All seed data inserted\n";
    echo "\nYou can now book appointments and they will display correctly!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
