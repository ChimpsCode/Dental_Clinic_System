-- ============================================
-- Dental Clinic Management System - Complete Database Schema
-- ============================================
-- Version: 1.0
-- Date: 2026-02-15
-- Description: Complete database schema with all tables and migrations in one file
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','dentist','staff','user') DEFAULT 'user',
  `first_login` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: patients
-- ============================================
CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `dental_insurance` varchar(100) DEFAULT NULL,
  `insurance_effective_date` date DEFAULT NULL,
  `registration_source` varchar(50) DEFAULT 'direct',
  `source_appointment_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: appointments
-- ============================================
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `appointment_time` time NOT NULL DEFAULT '09:00:00',
  `treatment` varchar(100) DEFAULT 'General Checkup',
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `is_registered_patient` tinyint(1) NOT NULL DEFAULT 0,
  `converted_patient_id` int(11) DEFAULT NULL,
  `is_converted_to_patient` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: queue
-- ============================================
CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `treatment_type` varchar(200) DEFAULT NULL,
  `teeth_numbers` varchar(500) DEFAULT NULL,
  `procedure_notes` text DEFAULT NULL,
  `status` enum('waiting','in_procedure','pending_payment','completed','cancelled','on_hold') DEFAULT 'waiting',
  `completed_at` timestamp NULL DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0,
  `priority` int(11) DEFAULT 5,
  `queue_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: treatments
-- ============================================
CREATE TABLE `treatments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `treatment_date` date NOT NULL,
  `procedure_name` varchar(200) NOT NULL,
  `tooth_number` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `doctor_id` int(11) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: treatment_plans
-- ============================================
CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `treatment_name` varchar(200) NOT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `teeth_numbers` varchar(500) DEFAULT NULL,
  `total_sessions` int(11) DEFAULT 1,
  `completed_sessions` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  `next_session_date` date DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: services
-- ============================================
CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `mode` enum('BULK','SINGLE','NONE') DEFAULT 'SINGLE',
  `price` decimal(10,2) DEFAULT 0.00,
  `duration_minutes` int(11) DEFAULT 30,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: billing
-- ============================================
CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `treatment_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `payment_status` varchar(20) DEFAULT 'pending',
  `billing_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: payments
-- ============================================
CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: medical_history
-- ============================================
CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` varchar(20) DEFAULT NULL,
  `other_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: dental_history
-- ============================================
CREATE TABLE `dental_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `previous_dentist` varchar(100) DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `reason_last_visit` text DEFAULT NULL,
  `previous_treatments` text DEFAULT NULL,
  `current_complaints` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inquiries
-- ============================================
CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `contact_info` varchar(255) DEFAULT NULL,
  `source` enum('Fb messenger','Phone call','Walk-in') NOT NULL DEFAULT 'Fb messenger',
  `inquiry_message` text DEFAULT NULL,
  `status` enum('Pending','Answered','Booked','New Admission') DEFAULT 'Pending',
  `converted_patient_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `topic` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: audit_logs
-- ============================================
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `user_role` enum('admin','dentist','staff') DEFAULT NULL,
  `action_type` enum('login','logout','create','read','update','delete','payment','status_change','failed_login') NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `record_type` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `affected_table` varchar(50) DEFAULT NULL,
  `affected_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES
-- ============================================

-- users indexes
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD KEY `idx_users_archived` (`is_archived`), ADD KEY `idx_users_deleted_at` (`deleted_at`);

-- patients indexes
ALTER TABLE `patients` ADD PRIMARY KEY (`id`), ADD KEY `idx_patients_archived` (`is_archived`), ADD KEY `idx_patients_deleted_at` (`deleted_at`), ADD KEY `idx_registration_source` (`registration_source`), ADD KEY `idx_source_appointment_id` (`source_appointment_id`);

-- appointments indexes
ALTER TABLE `appointments` ADD PRIMARY KEY (`id`), ADD KEY `patient_id` (`patient_id`), ADD KEY `idx_appointments_archived` (`is_archived`), ADD KEY `idx_appointments_deleted_at` (`deleted_at`), ADD KEY `idx_is_registered_patient` (`is_registered_patient`), ADD KEY `idx_converted_patient_id` (`converted_patient_id`), ADD KEY `idx_is_converted` (`is_converted_to_patient`);

-- queue indexes
ALTER TABLE `queue` ADD PRIMARY KEY (`id`), ADD KEY `idx_patient_id` (`patient_id`), ADD KEY `idx_status` (`status`), ADD KEY `idx_priority` (`priority`), ADD KEY `idx_queue_time` (`queue_time`), ADD KEY `idx_queue_archived` (`is_archived`), ADD KEY `idx_queue_deleted_at` (`deleted_at`);

-- treatments indexes
ALTER TABLE `treatments` ADD PRIMARY KEY (`id`), ADD KEY `doctor_id` (`doctor_id`), ADD KEY `idx_patient_id` (`patient_id`), ADD KEY `idx_treatment_date` (`treatment_date`), ADD KEY `idx_status` (`status`);

-- treatment_plans indexes
ALTER TABLE `treatment_plans` ADD PRIMARY KEY (`id`), ADD KEY `doctor_id` (`doctor_id`), ADD KEY `idx_patient_id` (`patient_id`), ADD KEY `idx_status` (`status`), ADD KEY `idx_next_session` (`next_session_date`), ADD KEY `idx_treatment_plans_archived` (`is_archived`), ADD KEY `idx_treatment_plans_deleted_at` (`deleted_at`);

-- services indexes
ALTER TABLE `services` ADD PRIMARY KEY (`id`), ADD KEY `idx_is_active` (`is_active`), ADD KEY `idx_mode` (`mode`), ADD KEY `idx_services_archived` (`is_archived`), ADD KEY `idx_services_deleted_at` (`deleted_at`);

-- billing indexes
ALTER TABLE `billing` ADD PRIMARY KEY (`id`), ADD KEY `treatment_id` (`treatment_id`), ADD KEY `appointment_id` (`appointment_id`), ADD KEY `idx_patient_id` (`patient_id`), ADD KEY `idx_payment_status` (`payment_status`), ADD KEY `idx_billing_date` (`billing_date`);

-- payments indexes
ALTER TABLE `payments` ADD PRIMARY KEY (`id`), ADD KEY `billing_id` (`billing_id`), ADD KEY `created_by` (`created_by`), ADD KEY `idx_patient_id` (`patient_id`), ADD KEY `idx_payment_date` (`payment_date`);

-- medical_history indexes
ALTER TABLE `medical_history` ADD PRIMARY KEY (`id`), ADD KEY `idx_patient_id` (`patient_id`);

-- dental_history indexes
ALTER TABLE `dental_history` ADD PRIMARY KEY (`id`), ADD KEY `idx_patient_id` (`patient_id`);

-- inquiries indexes
ALTER TABLE `inquiries` ADD PRIMARY KEY (`id`), ADD KEY `idx_status` (`status`), ADD KEY `idx_source` (`source`), ADD KEY `idx_created_at` (`created_at`), ADD KEY `idx_first_name` (`first_name`), ADD KEY `idx_last_name` (`last_name`), ADD KEY `idx_inquiries_archived` (`is_archived`), ADD KEY `idx_inquiries_deleted_at` (`deleted_at`), ADD KEY `topic` (`topic`), ADD KEY `idx_converted_patient` (`converted_patient_id`);

-- audit_logs indexes
ALTER TABLE `audit_logs` ADD PRIMARY KEY (`id`), ADD KEY `idx_user_id` (`user_id`), ADD KEY `idx_action_type` (`action_type`), ADD KEY `idx_module` (`module`), ADD KEY `idx_status` (`status`), ADD KEY `idx_created_at` (`created_at`), ADD KEY `idx_ip` (`ip_address`);

-- ============================================
-- AUTO_INCREMENT
-- ============================================
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `patients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `appointments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `queue` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `treatments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `treatment_plans` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `services` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `billing` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `payments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `medical_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dental_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `inquiries` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `audit_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- ============================================
-- FOREIGN KEYS
-- ============================================
ALTER TABLE `appointments` ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;
ALTER TABLE `billing` ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `billing` ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL;
ALTER TABLE `billing` ADD CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;
ALTER TABLE `dental_history` ADD CONSTRAINT `dental_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `inquiries` ADD CONSTRAINT `fk_inquiry_converted_patient` FOREIGN KEY (`converted_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;
ALTER TABLE `inquiries` ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`topic`) REFERENCES `services` (`id`);
ALTER TABLE `medical_history` ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `queue` ADD CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `treatments` ADD CONSTRAINT `treatments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `treatments` ADD CONSTRAINT `treatments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `treatment_plans` ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
ALTER TABLE `treatment_plans` ADD CONSTRAINT `treatment_plans_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ============================================
-- DEFAULT DATA - USERS
-- ============================================
INSERT INTO `users` (`first_name`, `middle_name`, `last_name`, `username`, `password`, `email`, `role`, `first_login`) VALUES
('', NULL, '', 'admin', '$2y$10$FXDdqW3kOxHDvH7nH9i/4.Mrrze218ezCvGOmk6jjcfuRXciQMuma', 'admin@rfdental.com', 'admin', 0),
('', NULL, '', 'staff', '$2y$10$jRGwuu0MuLLPiNaDV5uz8.SSueIZkw7AwOeyouvIPCGnftm/OcBdK', 'staff@rfdental.com', 'staff', 0),
('', NULL, '', 'dentist', '$2y$10$J.tCZfkE.TDCaYClTem.GertJO7FDz2dwXDkukVluf0nWfJg1qzW2', 'dentist@rfdental.com', 'dentist', 0);

-- ============================================
-- DEFAULT DATA - SERVICES
-- ============================================
INSERT INTO `services` (`name`, `mode`, `price`, `duration_minutes`, `description`, `is_active`) VALUES
('Teeth Cleaning', 'BULK', 800.00, 60, 'Case to case cleaning', 1),
('Tooth Restoration (Pasta)', 'SINGLE', 800.00, 45, 'Restores decayed teeth using composite filling.', 1),
('Tooth Extraction (Ibot)', 'SINGLE', 800.00, 45, 'Safe removal of a damaged or non-restorable tooth.', 1),
('Root Canal Treatment', 'SINGLE', 5000.00, 90, 'Saves an infected tooth by removing the pulp.', 1),
('Consultation', 'NONE', 500.00, 30, 'Professional oral health assessment and planning.', 1),
('Periapical Xray', 'SINGLE', 500.00, 15, 'Single-tooth X-ray showing the root and surrounding bone', 1),
('Denture Adjustment', 'NONE', 500.00, 30, 'Reshaping of dentures to improve fit and comfort.', 1),
('Removable Dentures', 'NONE', 5000.00, 30, 'Custom-made removable replacement for missing teeth.', 1),
('Crowns (Jacket)', 'SINGLE', 2000.00, 90, 'Protective cap to restore tooth shape and strength.', 1),
('Fixed Bridge', 'BULK', 5000.00, 90, 'Permanent replacement for missing teeth anchored to adjacent teeth.', 1),
('Teeth Whitening', 'NONE', 5000.00, 90, 'Cosmetic procedure to lighten teeth and remove stains.', 1),
('Orthodontic Appliance', 'BULK', 35000.00, 120, 'Devices like braces or retainers to align teeth.', 1);

-- ============================================
-- DEFAULT DATA - PATIENTS (sample)
-- ============================================
INSERT INTO `patients` (`id`, `first_name`, `middle_name`, `last_name`, `gender`, `phone`, `email`, `address`, `status`) VALUES
(1, 'Mark', NULL, 'Tan', 'Male', '09170000001', 'mark.tan@example.com', 'Cebu City', 'active'),
(2, 'Juan', NULL, 'Flores', 'Male', '09170000002', 'juan.flores@example.com', 'Mandaue City', 'active'),
(3, 'Kevin', 'Miguel', 'Lim', 'Male', '09170000003', 'kevin.lim@example.com', 'Lapu-Lapu City', 'active'),
(4, 'Jona', NULL, 'Casinillo', 'Female', '09170000004', 'jona.casinillo@example.com', 'Cebu City', 'active');

-- ============================================
-- DEFAULT DATA - BILLING (sample for revenue chart)
-- ============================================
INSERT INTO `billing` (`id`, `patient_id`, `total_amount`, `paid_amount`, `balance`, `payment_status`, `billing_date`, `due_date`, `notes`) VALUES
(1, 1, 800.00, 800.00, 0.00, 'paid', '2026-01-10', '2026-01-17', 'General Service'),
(2, 2, 2400.00, 2400.00, 0.00, 'paid', '2026-02-05', '2026-02-12', 'General Service'),
(3, 3, 800.00, 800.00, 0.00, 'paid', '2026-03-08', '2026-03-15', 'General Service'),
(4, 4, 800.00, 800.00, 0.00, 'paid', '2026-04-12', '2026-04-19', 'General Service'),
(5, 1, 800.00, 800.00, 0.00, 'paid', '2026-05-09', '2026-05-16', 'General Service'),
(6, 2, 800.00, 800.00, 0.00, 'paid', '2026-06-14', '2026-06-21', 'General Service'),
(7, 3, 800.00, 800.00, 0.00, 'paid', '2026-07-11', '2026-07-18', 'General Service'),
(8, 4, 800.00, 800.00, 0.00, 'paid', '2026-08-16', '2026-08-23', 'General Service'),
(9, 1, 800.00, 800.00, 0.00, 'paid', '2026-09-13', '2026-09-20', 'General Service'),
(10, 2, 800.00, 800.00, 0.00, 'paid', '2026-10-10', '2026-10-17', 'General Service'),
(11, 3, 800.00, 800.00, 0.00, 'paid', '2026-11-14', '2026-11-21', 'General Service'),
(12, 4, 800.00, 800.00, 0.00, 'paid', '2026-12-12', '2026-12-19', 'General Service');

-- ============================================
-- DEFAULT DATA - PAYMENTS (sample)
-- ============================================
INSERT INTO `payments` (`id`, `billing_id`, `patient_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `notes`, `created_by`) VALUES
(1, 1, 1, 800.00, 'cash', '2026-01-10', 'INV-001', 'Sample payment', 1),
(2, 2, 2, 2400.00, 'cash', '2026-02-05', 'INV-002', 'Sample payment', 1),
(3, 3, 3, 800.00, 'cash', '2026-03-08', 'INV-003', 'Sample payment', 1),
(4, 4, 4, 800.00, 'cash', '2026-04-12', 'INV-004', 'Sample payment', 1),
(5, 5, 1, 800.00, 'cash', '2026-05-09', 'INV-005', 'Sample payment', 1),
(6, 6, 2, 800.00, 'cash', '2026-06-14', 'INV-006', 'Sample payment', 1),
(7, 7, 3, 800.00, 'cash', '2026-07-11', 'INV-007', 'Sample payment', 1),
(8, 8, 4, 800.00, 'cash', '2026-08-16', 'INV-008', 'Sample payment', 1),
(9, 9, 1, 800.00, 'cash', '2026-09-13', 'INV-009', 'Sample payment', 1),
(10, 10, 2, 800.00, 'cash', '2026-10-10', 'INV-010', 'Sample payment', 1),
(11, 11, 3, 800.00, 'cash', '2026-11-14', 'INV-011', 'Sample payment', 1),
(12, 12, 4, 800.00, 'cash', '2026-12-12', 'INV-012', 'Sample payment', 1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
