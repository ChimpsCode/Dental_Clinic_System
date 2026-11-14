-- RF Dental Clinic canonical database schema
-- This is the single source of truth for a fresh database installation.
-- Generated from the maintained schema and feature migrations.
-- Target: MariaDB/MySQL with utf8mb4 support.

CREATE DATABASE IF NOT EXISTS `master_rfdb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `master_rfdb`;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- --- 1. CORE TABLES (Optimized Structure) ---

CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_type` VARCHAR(50) DEFAULT 'New Patient',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `religion` VARCHAR(50) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(20) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `is_registered_patient` tinyint(1) DEFAULT 1,
  `converted_patient_id` int(11) DEFAULT NULL,
  `booked_by_id` int(11) DEFAULT NULL,
  `relation_to_patient` varchar(50) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `inquiry_id` int(11) DEFAULT NULL,
  `status` ENUM('Scheduled', 'In Queue', 'Pending Payment', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_reminded` tinyint(1) DEFAULT 0,
  `check_in_time` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `booking_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `treatment_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL,
  `payment_status` ENUM('Paid', 'Partial', 'Unpaid', 'Overdue', 'Cancelled') DEFAULT 'Unpaid',
  `billing_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `printed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `queue_label` varchar(20) DEFAULT NULL,
  `queue_date` date DEFAULT NULL,
  `status` ENUM('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') DEFAULT 'waiting',
  `dentist_id` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT 5,
  `procedure_notes` text DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `queue_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` ENUM('Admin', 'Dentist', 'Staff', 'Patient') NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `sms_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `email_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `last_active` timestamp NULL DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  `is_logged_in` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail for outbound SMS. Written by logSms() in includes/sms_functions.php.
CREATE TABLE IF NOT EXISTS `sms_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(30) DEFAULT NULL,
  `message` text,
  `category` varchar(50) DEFAULT NULL,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'failed',
  `provider_message_id` varchar(100) DEFAULT NULL,
  `error_message` text,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sms_log_phone` (`phone`),
  KEY `idx_sms_log_created` (`created_at`),
  KEY `idx_sms_log_category` (`category`),
  KEY `idx_sms_log_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- 2. SUPPORTING TABLES ---

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `action_type` enum('login','logout','create','read','update','delete','payment','status_change','failed_login') DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `affected_table` varchar(50) DEFAULT NULL,
  `affected_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `content_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dental_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `previous_dentist` varchar(100) DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `reason_last_visit` text DEFAULT NULL,
  `previous_treatments` text DEFAULT NULL,
  `current_complaints` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `concern` text DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `status` ENUM('Unread', 'Read', 'Replied', 'Closed') DEFAULT 'Unread',
  `source` varchar(50) NOT NULL DEFAULT 'Patient Portal',
  `assigned_staff_id` int(11) DEFAULT NULL,
  `inquiry_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiry_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inquiry_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('staff','guest','patient') NOT NULL DEFAULT 'guest',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `insurance_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('patient','admin','system') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `file_type` VARCHAR(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `policy_number` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `issue_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `queue_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `available_day` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clinic-wide SMS kill switch. When set to 0 no patient-facing SMS is sent.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('sms_enabled', '1');

CREATE TABLE IF NOT EXISTS `teeth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tooth_number` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `treatments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `treatment_date` date NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `treatment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `plan_name` varchar(200) DEFAULT NULL,
  `overall_status` enum('Active','Completed','On-Hold','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `treatment_plan_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `status` ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================================================
-- Supporting session table
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_login_devices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_token` (`user_id`, `token_hash`),
  KEY `idx_user_login_devices_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- Feature section: discounts
-- ============================================================================

-- Senior / PWD Discount Migration (RA 9994 & RA 10754)
-- Adds patient discount ID fields and billing discount breakdown columns

ALTER TABLE `patients`
    ADD COLUMN `senior_citizen_id` VARCHAR(30) DEFAULT NULL AFTER `date_of_birth`,
    ADD COLUMN `senior_id_type` VARCHAR(40) DEFAULT NULL AFTER `senior_citizen_id`,
    ADD COLUMN `pwd_id` VARCHAR(30) DEFAULT NULL AFTER `senior_id_type`;

-- Existing installs that already have senior_citizen_id / pwd_id:
-- ALTER TABLE `patients` ADD COLUMN `senior_id_type` VARCHAR(40) DEFAULT NULL AFTER `senior_citizen_id`;

ALTER TABLE `billing`
    ADD COLUMN `subtotal_amount` DECIMAL(10,2) DEFAULT NULL AFTER `total_amount`,
    ADD COLUMN `discount_type` ENUM('none','senior','pwd') NOT NULL DEFAULT 'none' AFTER `subtotal_amount`,
    ADD COLUMN `discount_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`,
    ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_rate`,
    ADD COLUMN `discount_id_no` VARCHAR(50) DEFAULT NULL AFTER `discount_amount`,
    ADD COLUMN `discount_holder_name` VARCHAR(150) DEFAULT NULL AFTER `discount_id_no`;

-- Manual / promotional discounts
ALTER TABLE `billing`
    MODIFY COLUMN `discount_type` ENUM('none','senior','pwd','custom') NOT NULL DEFAULT 'none',
    ADD COLUMN `discount_reason` VARCHAR(255) DEFAULT NULL AFTER `discount_holder_name`;

-- Allow 'discount' entries in audit log
ALTER TABLE `audit_logs`
    MODIFY COLUMN `action_type` ENUM('login','logout','create','read','update','delete','payment','status_change','failed_login','discount') DEFAULT NULL;


-- ============================================================================
-- Feature section: consultations
-- ============================================================================

-- Consultation Tables Migration
-- Run this SQL to add the consultations table

CREATE TABLE IF NOT EXISTS `consultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `status` enum('in_progress','completed') DEFAULT 'in_progress',

  -- Dental Examination - Periodontal
  `periodontal_screening` varchar(50) DEFAULT NULL,

  -- Dental Examination - Occlusion
  `occlusion_class_molar` tinyint(1) DEFAULT 0,
  `occlusion_molar_class` varchar(20) DEFAULT NULL,
  `occlusion_notes` text DEFAULT NULL,
  `occlusion_overjet` tinyint(1) DEFAULT 0,
  `occlusion_overbite` tinyint(1) DEFAULT 0,
  `occlusion_midline_deviation` tinyint(1) DEFAULT 0,
  `occlusion_crossbite` tinyint(1) DEFAULT 0,

  -- Dental Examination - Appliances
  `appliances_orthodontic` tinyint(1) DEFAULT 0,
  `appliances_skyplate` tinyint(1) DEFAULT 0,
  `appliances_others` tinyint(1) DEFAULT 0,
  `appliances_others_text` varchar(255) DEFAULT NULL,

  -- Dental Examination - TMD
  `tmd_clenching` tinyint(1) DEFAULT 0,
  `tmd_clicking` tinyint(1) DEFAULT 0,
  `tmd_trismus` tinyint(1) DEFAULT 0,
  `tmd_muscle_spasm` tinyint(1) DEFAULT 0,

  -- Tooth data (JSON)
  `teeth_data` json DEFAULT NULL,

  -- Notes
  `consultation_notes` text DEFAULT NULL,

  -- Dental & Medical History
  `dental_medical_history_notes` text DEFAULT NULL,

  -- Prescription
  `prescription_data` json DEFAULT NULL,

  -- Informed Consent
  `consent_provider` varchar(255) DEFAULT NULL,
  `consent_patient_address` text DEFAULT NULL,
  `consent_procedure` text DEFAULT NULL,
  `consent_by_guardian` tinyint(1) DEFAULT 0,
  `consent_guardian_name` varchar(255) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_date` date DEFAULT NULL,
  `consent_place` varchar(255) DEFAULT NULL,
  `consent_patient_signature` varchar(255) DEFAULT NULL,
  `consent_doctor_signature` varchar(255) DEFAULT NULL,
  `consent_witness_signature` varchar(255) DEFAULT NULL,
  `consent_patient_sign_name` varchar(255) DEFAULT NULL,
  `consent_witness_sign_name` varchar(255) DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `idx_consultations_queue_id` (`queue_id`),
  KEY `idx_consultations_patient_id` (`patient_id`),
  KEY `idx_consultations_dentist_id` (`dentist_id`),
  KEY `idx_consultations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- Feature section: diagnosis and ICD-10
-- ============================================================================

-- Diagnosis Migration
-- Adds ICD-10 diagnosis fields to consultations + curated Philippines/WHO ICD-10 reference table
-- Run this SQL to enable the Diagnosis tab

ALTER TABLE `consultations`
  ADD COLUMN IF NOT EXISTS `primary_icd10` varchar(10) DEFAULT NULL AFTER `dental_medical_history_notes`,
  ADD COLUMN IF NOT EXISTS `primary_icd10_desc` varchar(255) DEFAULT NULL AFTER `primary_icd10`,
  ADD COLUMN IF NOT EXISTS `secondary_icd10` varchar(10) DEFAULT NULL AFTER `primary_icd10_desc`,
  ADD COLUMN IF NOT EXISTS `secondary_icd10_desc` varchar(255) DEFAULT NULL AFTER `secondary_icd10`,
  ADD COLUMN IF NOT EXISTS `diagnosis_narrative` text DEFAULT NULL AFTER `secondary_icd10_desc`;

CREATE TABLE IF NOT EXISTS `icd10_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `description` varchar(255) NOT NULL,
  `chapter` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_icd10_code` (`code`),
  KEY `idx_icd10_description` (`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Curated Philippines/WHO ICD-10 set (dental-focused + common general codes)
INSERT IGNORE INTO `icd10_codes` (`code`, `description`, `chapter`) VALUES
-- Certain infectious and parasitic diseases
('A09', 'Gastroenteritis and colitis of infectious and unspecified origin', 'Infectious & Parasitic'),
('A15.0', 'Tuberculosis of lung, confirmed by sputum smear with or without culture', 'Infectious & Parasitic'),
('B00.1', 'Herpes labialis (cold sores)', 'Infectious & Parasitic'),
('B24', 'Human immunodeficiency virus [HIV] disease, unspecified', 'Infectious & Parasitic'),
('B35.4', 'Tinea of the body', 'Infectious & Parasitic'),
('B37.0', 'Candidal stomatitis (oral thrush)', 'Infectious & Parasitic'),
-- Neoplasms
('C00.9', 'Malignant neoplasm of lip, unspecified', 'Neoplasms'),
('C02.9', 'Malignant neoplasm of tongue, unspecified', 'Neoplasms'),
('C03.9', 'Malignant neoplasm of gum, unspecified', 'Neoplasms'),
('C04.9', 'Malignant neoplasm of floor of mouth, unspecified', 'Neoplasms'),
('C06.9', 'Malignant neoplasm of other and unspecified parts of mouth', 'Neoplasms'),
('C10.9', 'Malignant neoplasm of oropharynx, unspecified', 'Neoplasms'),
('D10.1', 'Benign neoplasm of tongue', 'Neoplasms'),
-- Diseases of the blood
('D50.9', 'Iron deficiency anaemia, unspecified', 'Blood & Immune'),
('D64.9', 'Anaemia, unspecified', 'Blood & Immune'),
('D66', 'Hereditary factor VIII deficiency (Haemophilia A)', 'Blood & Immune'),
('D67', 'Hereditary factor IX deficiency (Haemophilia B)', 'Blood & Immune'),
('D68.0', 'von Willebrand disease', 'Blood & Immune'),
('D69.3', 'Idiopathic thrombocytopenic purpura', 'Blood & Immune'),
-- Endocrine, nutritional, metabolic
('E03.9', 'Hypothyroidism, unspecified', 'Endocrine & Metabolic'),
('E04.9', 'Nontoxic goitre, unspecified', 'Endocrine & Metabolic'),
('E10.9', 'Type 1 diabetes mellitus without complications', 'Endocrine & Metabolic'),
('E11.9', 'Type 2 diabetes mellitus without complications', 'Endocrine & Metabolic'),
('E55.9', 'Vitamin D deficiency, unspecified', 'Endocrine & Metabolic'),
('E66.9', 'Obesity, unspecified', 'Endocrine & Metabolic'),
('E78.5', 'Hyperlipidaemia, unspecified', 'Endocrine & Metabolic'),
-- Mental and behavioural disorders
('F10.2', 'Alcohol dependence', 'Mental & Behavioural'),
('F17.2', 'Nicotine (tobacco) dependence', 'Mental & Behavioural'),
('F32.9', 'Depressive episode, unspecified', 'Mental & Behavioural'),
('F41.9', 'Anxiety disorder, unspecified', 'Mental & Behavioural'),
-- Diseases of the nervous system
('G40.9', 'Epilepsy, unspecified', 'Nervous System'),
('G43.9', 'Migraine, unspecified', 'Nervous System'),
('G44.2', 'Tension-type headache', 'Nervous System'),
('G47.3', 'Sleep apnoea', 'Nervous System'),
('G50.0', 'Trigeminal neuralgia', 'Nervous System'),
('G50.1', 'Atypical facial pain', 'Nervous System'),
-- Diseases of the ear
('H66.9', 'Otitis media, unspecified', 'Ear'),
-- Diseases of the circulatory system
('I10', 'Essential (primary) hypertension', 'Circulatory'),
('I15.9', 'Secondary hypertension, unspecified', 'Circulatory'),
('I20.0', 'Unstable angina', 'Circulatory'),
('I20.9', 'Angina pectoris, unspecified', 'Circulatory'),
('I21.9', 'Acute myocardial infarction, unspecified', 'Circulatory'),
('I25.1', 'Atherosclerotic heart disease', 'Circulatory'),
('I25.2', 'Old myocardial infarction', 'Circulatory'),
('I25.9', 'Chronic ischaemic heart disease, unspecified', 'Circulatory'),
('I26.9', 'Pulmonary embolism', 'Circulatory'),
('I34.1', 'Mitral (valve) prolapse', 'Circulatory'),
('I35.0', 'Aortic (valve) stenosis', 'Circulatory'),
('I38', 'Endocarditis, unspecified', 'Circulatory'),
('I48', 'Atrial fibrillation and flutter', 'Circulatory'),
('I49.9', 'Cardiac arrhythmia, unspecified', 'Circulatory'),
('I50.9', 'Heart failure, unspecified', 'Circulatory'),
('I61.9', 'Intracerebral haemorrhage', 'Circulatory'),
('I63.9', 'Cerebral infarction, unspecified', 'Circulatory'),
('I64', 'Stroke, not specified as haemorrhage or infarction', 'Circulatory'),
('I70', 'Atherosclerosis', 'Circulatory'),
('I73.9', 'Peripheral vascular disease, unspecified', 'Circulatory'),
('I95.9', 'Hypotension, unspecified', 'Circulatory'),
-- Diseases of the respiratory system
('J00', 'Acute nasopharyngitis (common cold)', 'Respiratory'),
('J01.9', 'Acute sinusitis, unspecified', 'Respiratory'),
('J02.9', 'Acute pharyngitis, unspecified', 'Respiratory'),
('J03.9', 'Acute tonsillitis, unspecified', 'Respiratory'),
('J04.0', 'Acute laryngitis', 'Respiratory'),
('J06.9', 'Acute upper respiratory infection, unspecified', 'Respiratory'),
('J11.1', 'Influenza with other respiratory manifestations, virus not identified', 'Respiratory'),
('J18.9', 'Pneumonia, unspecified', 'Respiratory'),
('J20.9', 'Acute bronchitis, unspecified', 'Respiratory'),
('J30.4', 'Allergic rhinitis, unspecified', 'Respiratory'),
('J32.0', 'Chronic maxillary sinusitis', 'Respiratory'),
('J32.9', 'Chronic sinusitis, unspecified', 'Respiratory'),
('J44.9', 'Chronic obstructive pulmonary disease, unspecified', 'Respiratory'),
('J45.9', 'Asthma, unspecified', 'Respiratory'),
-- Diseases of the digestive system (oral & dental)
('K00.1', 'Supernumerary teeth', 'Digestive (Dental/Oral)'),
('K00.2', 'Abnormalities of size and form of teeth', 'Digestive (Dental/Oral)'),
('K00.3', 'Mottled teeth (dental fluorosis)', 'Digestive (Dental/Oral)'),
('K00.6', 'Disturbances in tooth eruption', 'Digestive (Dental/Oral)'),
('K01.0', 'Embedded teeth', 'Digestive (Dental/Oral)'),
('K01.1', 'Impacted teeth', 'Digestive (Dental/Oral)'),
('K02.0', 'Dental caries limited to enamel', 'Digestive (Dental/Oral)'),
('K02.1', 'Dental caries penetrating into dentine', 'Digestive (Dental/Oral)'),
('K02.2', 'Dental caries penetrating into pulp', 'Digestive (Dental/Oral)'),
('K02.3', 'Arrested dental caries', 'Digestive (Dental/Oral)'),
('K02.9', 'Dental caries, unspecified', 'Digestive (Dental/Oral)'),
('K03.0', 'Excessive attrition of teeth', 'Digestive (Dental/Oral)'),
('K03.1', 'Abrasion of teeth', 'Digestive (Dental/Oral)'),
('K03.2', 'Dental erosion', 'Digestive (Dental/Oral)'),
('K03.7', 'Post-eruption colour changes of dental hard tissues (extrinsic stains)', 'Digestive (Dental/Oral)'),
('K04.0', 'Pulpitis (irreversible/reversible)', 'Digestive (Dental/Oral)'),
('K04.1', 'Necrosis of the pulp', 'Digestive (Dental/Oral)'),
('K04.4', 'Acute apical periodontitis of pulpal origin', 'Digestive (Dental/Oral)'),
('K04.5', 'Chronic apical periodontitis (apical granuloma)', 'Digestive (Dental/Oral)'),
('K04.6', 'Periapical abscess with sinus', 'Digestive (Dental/Oral)'),
('K04.7', 'Periapical abscess without sinus', 'Digestive (Dental/Oral)'),
('K04.8', 'Radicular cyst', 'Digestive (Dental/Oral)'),
('K05.0', 'Acute gingivitis', 'Digestive (Dental/Oral)'),
('K05.1', 'Chronic gingivitis', 'Digestive (Dental/Oral)'),
('K05.2', 'Aggressive periodontitis', 'Digestive (Dental/Oral)'),
('K05.3', 'Chronic periodontitis', 'Digestive (Dental/Oral)'),
('K05.6', 'Periodontal disease, unspecified', 'Digestive (Dental/Oral)'),
('K06.0', 'Receding gums (gingival recession)', 'Digestive (Dental/Oral)'),
('K06.1', 'Gingival enlargement (hyperplasia)', 'Digestive (Dental/Oral)'),
('K07.0', 'Major abnormalities of jaw size', 'Digestive (Dental/Oral)'),
('K07.6', 'Temporomandibular joint disorders (TMD)', 'Digestive (Dental/Oral)'),
('K07.9', 'Dentofacial anomaly, unspecified (malocclusion)', 'Digestive (Dental/Oral)'),
('K08.1', 'Loss of teeth due to accident, extraction or localized periodontal disease', 'Digestive (Dental/Oral)'),
('K08.3', 'Retained dental root', 'Digestive (Dental/Oral)'),
('K08.8', 'Other disorders of teeth and supporting structures (incl. toothache)', 'Digestive (Dental/Oral)'),
('K08.9', 'Disorder of teeth and supporting structures, unspecified', 'Digestive (Dental/Oral)'),
('K10.2', 'Inflammatory conditions of jaws (osteomyelitis)', 'Digestive (Dental/Oral)'),
('K11.5', 'Sialolithiasis (salivary gland stone)', 'Digestive (Dental/Oral)'),
('K11.6', 'Mucocele of salivary gland', 'Digestive (Dental/Oral)'),
('K11.7', 'Disturbance of salivary secretion (xerostomia/dry mouth)', 'Digestive (Dental/Oral)'),
('K12.0', 'Recurrent oral aphthae (canker sores)', 'Digestive (Dental/Oral)'),
('K12.1', 'Other forms of stomatitis', 'Digestive (Dental/Oral)'),
('K12.2', 'Cellulitis and abscess of mouth', 'Digestive (Dental/Oral)'),
('K13.0', 'Cheilitis', 'Digestive (Dental/Oral)'),
('K13.2', 'Leukoplakia and other disturbances of oral epithelium', 'Digestive (Dental/Oral)'),
('K13.5', 'Oral submucous fibrosis', 'Digestive (Dental/Oral)'),
('K13.7', 'Oral lichen planus', 'Digestive (Dental/Oral)'),
('K14.1', 'Geographic tongue', 'Digestive (Dental/Oral)'),
('K14.2', 'Median rhomboid glossitis', 'Digestive (Dental/Oral)'),
('K14.4', 'Glossitis', 'Digestive (Dental/Oral)'),
('K14.8', 'Other diseases of tongue (macroglossia)', 'Digestive (Dental/Oral)'),
('K21.9', 'Gastro-oesophageal reflux disease without oesophagitis (GERD)', 'Digestive'),
('K29.7', 'Gastritis and duodenitis, unspecified', 'Digestive'),
('K52.9', 'Noninfective gastroenteritis and colitis, unspecified', 'Digestive'),
-- Diseases of the skin
('L50.9', 'Urticaria, unspecified', 'Skin'),
-- Congenital malformations
('Q35.9', 'Cleft palate, unspecified', 'Congenital'),
('Q36.9', 'Cleft lip, unspecified', 'Congenital'),
('Q38.1', 'Ankyloglossia (tongue-tie)', 'Congenital'),
-- Symptoms and signs
('R51', 'Headache', 'Symptoms & Signs'),
-- Injury and poisoning
('S02.4', 'Fracture of malar and maxillary bones', 'Injury'),
('S02.5', 'Fracture of tooth (broken tooth)', 'Injury'),
('S02.6', 'Fracture of mandible', 'Injury'),
('S03.2', 'Dislocation, luxation and avulsion of tooth', 'Injury'),
-- Factors influencing health status (Z-codes)
('Z01.2', 'Encounter for dental examination', 'Health Status Factors'),
('Z34.9', 'Supervision of normal pregnancy, unspecified', 'Health Status Factors'),
('Z46.4', 'Encounter for fitting and adjustment of orthodontic devices', 'Health Status Factors'),
('Z79.4', 'Long term (current) use of insulin', 'Health Status Factors'),
('Z88.0', 'Personal history of allergy to penicillin', 'Health Status Factors'),
('Z88.1', 'Personal history of allergy to other antibiotic agents', 'Health Status Factors'),
('Z95.0', 'Presence of cardiac pacemaker', 'Health Status Factors'),
('Z95.2', 'Presence of prosthetic heart valve', 'Health Status Factors');


-- ============================================================================
-- Feature section: occlusion
-- ============================================================================

-- Existing checkbox values stay intact; do not interpret 1 as Class I.
-- The consultation page/API also adds missing columns automatically.
ALTER TABLE consultations
  ADD COLUMN IF NOT EXISTS occlusion_molar_class VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS occlusion_notes TEXT DEFAULT NULL;


-- ============================================================================
-- Feature section: signatures
-- ============================================================================

-- Signature Pad Migration
-- Changes consultation signature columns from varchar(255) to MEDIUMTEXT
-- Adds signature_data column to users table for persistent doctor signature

-- 1. Alter consultations signature columns to hold base64 image data
ALTER TABLE consultations
  MODIFY consent_patient_signature MEDIUMTEXT DEFAULT NULL,
  MODIFY consent_doctor_signature MEDIUMTEXT DEFAULT NULL,
  MODIFY consent_witness_signature MEDIUMTEXT DEFAULT NULL;

-- 2. Add persistent signature column to users table
ALTER TABLE users ADD COLUMN signature_data MEDIUMTEXT DEFAULT NULL AFTER is_archived;


-- ============================================================================
-- Feature section: X-ray uploads
-- ============================================================================

-- X-Ray Migration
-- Creates the patient_xrays table for consultation x-ray image uploads
-- Run this SQL to enable X-Ray uploads in the Consultation tab

CREATE TABLE IF NOT EXISTS `patient_xrays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_xray_patient` (`patient_id`),
  KEY `idx_xray_consultation` (`consultation_id`),
  CONSTRAINT `fk_xray_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_xray_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- Feature section: medical certificates
-- ============================================================================

-- Medical certificate issued during a consultation, plus the bookable service.

CREATE TABLE IF NOT EXISTS `medical_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `purpose` varchar(100) NOT NULL DEFAULT 'Employment',
  `findings` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `rest_from` date DEFAULT NULL,
  `rest_to` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `issued_at` date DEFAULT NULL,
  `dentist_name` varchar(150) DEFAULT NULL,
  `dentist_signature` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medcert_consultation` (`consultation_id`),
  KEY `idx_medcert_patient` (`patient_id`),
  KEY `idx_medcert_dentist` (`dentist_id`),
  CONSTRAINT `fk_medcert_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_medcert_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_medcert_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `services` (`name`, `mode`, `price`, `duration_minutes`, `description`, `is_active`, `is_archived`)
SELECT 'Medical Certificate', 'NONE', 0.00, 20, 'Certificate for employment, issued after an in-clinic visit.', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `services` WHERE `name` = 'Medical Certificate');


-- ============================================================================
-- Feature section: appointment editing
-- ============================================================================

-- The application also adds this column automatically when needed.
ALTER TABLE appointments ADD COLUMN patient_edited_at DATETIME NULL;


-- ============================================================================
-- Feature section: treatment plan workflow
-- ============================================================================

-- All plan sessions reserve normal appointments. Existing plan/session links
-- are recovered by ensureTreatmentPlanWorkflowSchema() when the app runs.
ALTER TABLE appointments
  ADD COLUMN IF NOT EXISTS treatment_plan_id INT NULL,
  ADD COLUMN IF NOT EXISTS treatment_plan_session INT NULL;
ALTER TABLE appointments ADD UNIQUE INDEX IF NOT EXISTS uq_treatment_plan_session (treatment_plan_id, treatment_plan_session);
ALTER TABLE treatment_plans MODIFY overall_status
  ENUM('Active','In-Progress','Completed','On-Hold','Cancelled') DEFAULT 'Active';


-- ============================================================================
-- Feature section: print logs
-- ============================================================================

-- Print Activity log: who printed what, and when
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- Feature section: SMS notifications
-- ============================================================================

-- ============================================================================
-- SMS / notification preferences + SMS audit log
-- ----------------------------------------------------------------------------
-- Why: the patient portal "Notification Preferences" screen (master/user-patient/
-- settings.php) writes to users.sms_notifications / users.email_notifications,
-- and the appointment reminder helper reads users.sms_notifications. Neither
-- column existed, so:
--   * "Save Preferences" always failed with SQLSTATE[42S22] Column not found
--   * 24h/2h appointment reminder SMS was permanently disabled
-- Adding sms_log gives every outbound SMS an audit trail so delivery problems
-- can be diagnosed without reading Apache's error log.
--
-- Idempotent: safe to run more than once.
-- ============================================================================

-- 1. Per-user notification preferences -------------------------------------
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'sms_notifications'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `users` ADD COLUMN `sms_notifications` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone`',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_notifications'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `users` ADD COLUMN `email_notifications` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sms_notifications`',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. SMS audit log -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `status` ENUM('sent','failed','skipped') NOT NULL DEFAULT 'failed',
  `provider` VARCHAR(30) NOT NULL DEFAULT 'textbee',
  `provider_message_id` VARCHAR(64) DEFAULT NULL,
  `error_message` VARCHAR(255) DEFAULT NULL,
  `patient_id` INT(11) DEFAULT NULL,
  `appointment_id` INT(11) DEFAULT NULL,
  `requested_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sms_log_phone` (`phone`),
  KEY `idx_sms_log_created` (`created_at`),
  KEY `idx_sms_log_category` (`category`),
  KEY `idx_sms_log_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Clinic-wide SMS master switch ------------------------------------------
-- Lets staff pause all outbound SMS from one place without a code change.
INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('sms_enabled', '1')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;


-- ============================================================================
-- Feature section: typing status cleanup
-- ============================================================================

-- Typing is ephemeral presence, not stored data. Drop leftover status tables.
DROP TABLE IF EXISTS `chat_typing_status`;
DROP TABLE IF EXISTS `inquiry_typing_status`;


-- ============================================================================
-- Feature section: audit/session foreign keys
-- ============================================================================

-- ============================================================================
-- Connect the audit/session tables to the rest of the schema
-- ----------------------------------------------------------------------------
-- Why: print_logs, sms_log and user_login_devices were created on demand by
-- the application (includes/print_log_helper.php, includes/sms_functions.php,
-- includes/login_device_alert.php) or by separate migration files, so they
-- ended up in the database with indexes but no FOREIGN KEY constraints.
-- A designer (phpMyAdmin / MySQL Workbench / DBeaver) therefore draws them as
-- disconnected boxes even though the columns clearly reference other tables:
--
--   print_logs.user_id            -> users.id
--   print_logs.reference_id       -> billing.id (polymorphic, see below)
--   sms_log.patient_id            -> patients.id
--   sms_log.appointment_id        -> appointments.id
--   sms_log.requested_by          -> users.id
--   user_login_devices.user_id    -> users.id
--
-- print_logs.reference_table / reference_id is intentionally NOT constrained:
-- it is a polymorphic pointer ('billing', 'appointment', ...) and a real FK
-- would need one constraint per target table. That is why the report reads it
-- with a LEFT JOIN in master/admin/admin_reports.php.
--
-- All of the targets are audit/history rows, so every FK uses ON DELETE
-- SET NULL: deleting a user must not erase the record that they existed, and
-- it must never be blocked by history. The single exception is
-- user_login_devices, which is live session state and is worthless without its
-- owner, so it cascades.
--
-- Idempotent: safe to run more than once. Orphaned ids are normalised to NULL
-- first so the constraints can be added on a database that already has data.
-- ============================================================================

SET @db := DATABASE();

-- ---------------------------------------------------------------------------
-- 0. Normalise orphans so the ALTERs below cannot fail on existing data
-- ---------------------------------------------------------------------------
UPDATE `print_logs` pl
  LEFT JOIN `users` u ON u.id = pl.user_id
  SET pl.user_id = NULL
  WHERE pl.user_id IS NOT NULL AND u.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `patients` p ON p.id = sl.patient_id
  SET sl.patient_id = NULL
  WHERE sl.patient_id IS NOT NULL AND p.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `appointments` a ON a.id = sl.appointment_id
  SET sl.appointment_id = NULL
  WHERE sl.appointment_id IS NOT NULL AND a.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `users` u ON u.id = sl.requested_by
  SET sl.requested_by = NULL
  WHERE sl.requested_by IS NOT NULL AND u.id IS NULL;

-- user_login_devices.user_id is NOT NULL, so orphans cannot be nulled. These
-- rows are cached session tokens for an account that no longer exists, so
-- dropping them is the correct normalisation.
DELETE d FROM `user_login_devices` d
  LEFT JOIN `users` u ON u.id = d.user_id
  WHERE u.id IS NULL;

-- ---------------------------------------------------------------------------
-- 1. user_login_devices.user_id -> users.id   (ON DELETE CASCADE)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'user_login_devices'
    AND CONSTRAINT_NAME = 'user_login_devices_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `user_login_devices`
     ADD CONSTRAINT `user_login_devices_ibfk_1`
     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 2. print_logs.user_id -> users.id           (ON DELETE SET NULL)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'print_logs'
    AND CONSTRAINT_NAME = 'print_logs_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `print_logs`
     ADD CONSTRAINT `print_logs_ibfk_1`
     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 3. sms_log -> patients / appointments / users (ON DELETE SET NULL)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_1`
     FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_2'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_2`
     FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_3'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_3`
     FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 4. Indexes the new FKs rely on
--    MySQL creates the child index automatically when it is missing, but doing
--    it explicitly keeps the key names predictable across installs.
-- ---------------------------------------------------------------------------
SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'user_login_devices'
    AND COLUMN_NAME = 'user_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `user_login_devices` ADD KEY `idx_user_login_devices_user` (`user_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'print_logs'
    AND COLUMN_NAME = 'user_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `print_logs` ADD KEY `idx_print_logs_user` (`user_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND COLUMN_NAME = 'patient_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `sms_log` ADD KEY `idx_sms_log_patient` (`patient_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND COLUMN_NAME = 'requested_by'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `sms_log` ADD KEY `idx_sms_log_requested_by` (`requested_by`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================================
-- Supporting lookup/session tables used by the ERD relationships
-- ============================================================================
CREATE TABLE IF NOT EXISTS `appointment_statuses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `status_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `appointment_statuses` (`id`, `status_name`) VALUES
  (1, 'Pending'), (2, 'Confirmed'), (3, 'Cancelled'), (4, 'Completed'), (5, 'Auto-Cancelled');

CREATE TABLE IF NOT EXISTS `inquiry_statuses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `status_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inquiry_status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `inquiry_statuses` (`id`, `status_name`) VALUES
  (1, 'Unread'), (2, 'Read'), (3, 'Replied'), (4, 'Converted'), (5, 'Archived');

CREATE TABLE IF NOT EXISTS `user_session_settings` (
  `user_id` INT(11) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `timeout_minutes` INT(11) NOT NULL DEFAULT 60,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `status_id` INT(11) DEFAULT NULL;
ALTER TABLE `inquiries` ADD COLUMN IF NOT EXISTS `status_id` INT(11) DEFAULT NULL;
-- ============================================================================
-- Feature section: ERD relationships
-- ============================================================================

-- Connect floating ERD lookup tables with foreign keys.
-- Schema-only. Does not change PHP. Leaves settings standalone.

-- 1) Auto-cancel writes appointments.status_id = 5. Lookup currently stops at id 4
--    and status_name is UNIQUE, so id 5 cannot reuse 'Cancelled'.
INSERT INTO `appointment_statuses` (`id`, `status_name`)
VALUES (5, 'Auto-Cancelled')
ON DUPLICATE KEY UPDATE `status_name` = VALUES(`status_name`);

-- 2) Live inquiries table has status names but no status_id column.
ALTER TABLE `inquiries`
  ADD COLUMN `status_id` INT(11) DEFAULT NULL AFTER `status`;

ALTER TABLE `inquiries`
  ADD INDEX `idx_inquiries_status_id` (`status_id`);

UPDATE `inquiries` i
INNER JOIN `inquiry_statuses` s ON s.status_name = i.status
SET i.status_id = s.id
WHERE i.status_id IS NULL;

-- 3) Remove session-timeout row whose user no longer exists.
DELETE s FROM `user_session_settings` s
LEFT JOIN `users` u ON u.id = s.user_id
WHERE u.id IS NULL;

-- 4) Status lookup FKs (nullable; app already writes NULL when a name is missing).
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_status`
  FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiry_status`
  FOREIGN KEY (`status_id`) REFERENCES `inquiry_statuses` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- 5) Per-user session FK. CASCADE so user delete is not blocked.
ALTER TABLE `user_session_settings`
  ADD CONSTRAINT `fk_user_session_user`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- 6) ICD-10 FKs on stored code strings. Indexes required by InnoDB.
ALTER TABLE `consultations`
  ADD INDEX `idx_consultations_primary_icd10` (`primary_icd10`);

ALTER TABLE `consultations`
  ADD INDEX `idx_consultations_secondary_icd10` (`secondary_icd10`);

ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_primary_icd10`
  FOREIGN KEY (`primary_icd10`) REFERENCES `icd10_codes` (`code`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_secondary_icd10`
  FOREIGN KEY (`secondary_icd10`) REFERENCES `icd10_codes` (`code`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;


-- The senior_id_type column is included in the discounts section above.

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;