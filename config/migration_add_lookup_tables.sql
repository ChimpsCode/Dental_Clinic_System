-- ==============================================
-- RF Dental Clinic - Database Migration
-- Adds lookup tables and missing columns
-- Date: 2026-04-15
-- ==============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ==============================================
-- 1. CREATE LOOKUP TABLES
-- ==============================================

-- Patient Types
CREATE TABLE IF NOT EXISTS `patient_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `patient_types` (`type_name`, `description`, `discount_percentage`) VALUES
('Regular', 'Regular paying patient', 0.00),
('Senior Citizen', 'Senior citizen with discount', 20.00),
('PWD', 'Person with disability with discount', 20.00),
('Student', 'Student with school ID discount', 15.00),
('VIP', 'Very important patient', 0.00);

-- Patient Statuses
CREATE TABLE IF NOT EXISTS `patient_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `patient_statuses` (`status_name`, `color`, `description`, `sort_order`) VALUES
('Active', '#28a745', 'Patient can book appointments', 1),
('Inactive', '#6c757d', 'Account temporarily disabled', 2),
('Archived', '#ffc107', 'Soft-deleted patient records', 3),
('Deceased', '#dc3545', 'Patient is deceased', 4);

-- Genders
CREATE TABLE IF NOT EXISTS `genders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gender_name` varchar(20) NOT NULL,
  `abbreviation` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `genders` (`gender_name`, `abbreviation`) VALUES
('Male', 'M'),
('Female', 'F'),
('Other', 'O'),
('Prefer not to say', 'N/A');

-- Religions
CREATE TABLE IF NOT EXISTS `religions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `religion_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `religions` (`religion_name`) VALUES
('Roman Catholic'),
('Christianity (Non-Catholic)'),
('Islam'),
('Iglesia ni Cristo'),
('Buddhism'),
('Hinduism'),
('Judaism'),
('Other'),
('None');

-- Appointment Statuses
CREATE TABLE IF NOT EXISTS `appointment_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `appointment_statuses` (`status_name`, `color`, `description`, `sort_order`) VALUES
('Scheduled', '#007bff', 'Appointment is booked', 1),
('Confirmed', '#28a745', 'Patient confirmed attendance', 2),
('In Progress', '#ffc107', 'Patient is being seen', 3),
('Completed', '#17a2b8', 'Appointment finished', 4),
('Cancelled', '#dc3545', 'Appointment cancelled', 5),
('No Show', '#6c757d', 'Patient did not show up', 6),
('Rescheduled', '#6610f2', 'Appointment moved to different date', 7);

-- Queue Statuses
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `queue_statuses` (`status_name`, `color`, `icon`, `description`, `sort_order`) VALUES
('Waiting', '#007bff', 'fa-clock', 'Patient is waiting', 1),
('Called', '#ffc107', 'fa-bell', 'Patient has been called', 2),
('In Procedure', '#17a2b8', 'fa-user-md', 'Currently in treatment', 3),
('Pending Payment', '#fd7e14', 'fa-money-bill', 'Awaiting payment', 4),
('Completed', '#28a745', 'fa-check', 'All done, paid', 5),
('On Hold', '#6c757d', 'fa-pause', 'Temporarily paused', 6),
('Cancelled', '#dc3545', 'fa-times', 'Cancelled', 7);

-- Inquiry Sources
CREATE TABLE IF NOT EXISTS `inquiry_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inquiry_sources` (`source_name`, `color`) VALUES
('Facebook Messenger', '#1877F2'),
('Phone Call', '#28a745'),
('Walk-in', '#ffc107'),
('Website', '#007bff'),
('Referral', '#17a2b8'),
('Email', '#6c757d');

-- Inquiry Statuses
CREATE TABLE IF NOT EXISTS `inquiry_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inquiry_statuses` (`status_name`, `color`, `description`, `sort_order`) VALUES
('Pending', '#ffc107', 'Inquiry received, awaiting response', 1),
('Answered', '#17a2b8', 'Response sent to patient', 2),
('Booked', '#28a745', 'Appointment has been booked', 3),
('Not Interested', '#6c757d', 'Patient not interested', 4),
('Spam', '#dc3545', 'Invalid/spam inquiry', 5);

-- Service Categories
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `service_categories` (`category_name`, `description`, `color`, `sort_order`) VALUES
('Preventive Care', 'Routine checkups and cleaning', '#28a745', 1),
('Restorative', 'Fillings, crowns, bridges', '#007bff', 2),
('Extraction', 'Tooth removal procedures', '#dc3545', 3),
('Endodontics', 'Root canal treatments', '#17a2b8', 4),
('Prosthodontics', 'Dentures and bridges', '#6c757d', 5),
('Orthodontics', 'Braces and aligners', '#6610f2', 6),
('Cosmetic', 'Whitening, veneers', '#ffc107', 7),
('Diagnostic', 'X-rays and scans', '#fd7e14', 8);

-- Payment Statuses
CREATE TABLE IF NOT EXISTS `payment_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_statuses` (`status_name`, `color`, `description`, `sort_order`) VALUES
('Pending', '#ffc107', 'Awaiting payment', 1),
('Partial', '#17a2b8', 'Partially paid', 2),
('Paid', '#28a745', 'Fully paid', 3),
('Overdue', '#dc3545', 'Payment overdue', 4),
('Cancelled', '#6c757d', 'Payment cancelled', 5),
('Refunded', '#6610f2', 'Payment refunded', 6);

-- Treatment Item Statuses
CREATE TABLE IF NOT EXISTS `treatment_item_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `treatment_item_statuses` (`status_name`, `color`, `description`, `sort_order`) VALUES
('Pending', '#ffc107', 'Treatment item not yet started', 1),
('In Progress', '#17a2b8', 'Treatment currently being performed', 2),
('Done', '#28a745', 'Treatment session completed', 3),
('Skipped', '#6c757d', 'Treatment skipped', 4),
('Cancelled', '#dc3545', 'Treatment cancelled', 5);

-- File Types
CREATE TABLE IF NOT EXISTS `file_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `file_types` (`type_name`, `icon`, `color`, `description`, `sort_order`) VALUES
('X-ray', 'fa-x-ray', '#fd7e14', 'Dental X-ray images', 1),
('Photo', 'fa-camera', '#007bff', 'Patient photos', 2),
('Consent Form', 'fa-file-signature', '#28a745', 'Signed consent forms', 3),
('ID', 'fa-id-card', '#17a2b8', 'Valid ID documents', 4),
('Medical Certificate', 'fa-file-medical', '#6c757d', 'Medical certificates', 5),
('Receipt', 'fa-receipt', '#ffc107', 'Payment receipts', 6),
('Treatment Plan', 'fa-clipboard-list', '#6610f2', 'Treatment plan documents', 7),
('Other', 'fa-file', '#6c757d', 'Other documents', 8);

-- Roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6c757d',
  `permissions` json DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`role_name`, `display_name`, `description`, `color`, `is_system`) VALUES
('admin', 'Administrator', 'Full system access', '#dc3545', 1),
('dentist', 'Dentist', 'Clinical staff with treatment access', '#007bff', 1),
('staff', 'Staff', 'Front desk and administrative', '#28a745', 1),
('user', 'Patient', 'Patient portal access', '#6c757d', 1);

-- ==============================================
-- 2. ADD MISSING COLUMNS TO EXISTING TABLES
-- ==============================================

-- Add service_id and status_id to appointments table
ALTER TABLE `appointments`
  ADD COLUMN `service_id` int(11) DEFAULT NULL AFTER `patient_id`,
  ADD COLUMN `status_id` int(11) DEFAULT 1 AFTER `service_id`;

-- Add foreign key for service_id
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

-- Add foreign key for status_id
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`) ON DELETE SET NULL;

-- Add user_id and role_id to users table
ALTER TABLE `users`
  ADD COLUMN `role_id` int(11) DEFAULT 4 AFTER `id`;

-- Set role_id based on existing role values
UPDATE `users` SET `role_id` = 1 WHERE `role` = 'admin';
UPDATE `users` SET `role_id` = 2 WHERE `role` = 'dentist';
UPDATE `users` SET `role_id` = 3 WHERE `role` = 'staff';
UPDATE `users` SET `role_id` = 4 WHERE `role` = 'user';

-- Update existing appointments with default status
UPDATE `appointments` SET `status_id` = 1 WHERE `status` = 'scheduled';
UPDATE `appointments` SET `status_id` = 4 WHERE `status` = 'completed';
UPDATE `appointments` SET `status_id` = 5 WHERE `status` = 'cancelled';

-- Add gender_id to patients
ALTER TABLE `patients`
  ADD COLUMN `gender_id` int(11) DEFAULT NULL AFTER `gender`;

-- Update gender_id based on existing gender values
UPDATE `patients` SET `gender_id` = 1 WHERE `gender` = 'Male' OR `gender` = 'male';
UPDATE `patients` SET `gender_id` = 2 WHERE `gender` = 'Female' OR `gender` = 'female';
UPDATE `patients` SET `gender_id` = 3 WHERE `gender` = 'Other' OR `gender` = 'other';

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
