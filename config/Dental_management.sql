-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 29, 2026 at 04:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dental_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL DEFAULT '',
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

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `action_type`, `module`, `record_id`, `record_type`, `description`, `old_value`, `new_value`, `affected_table`, `affected_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, NULL, 'dentist', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: dentist', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:04'),
(2, NULL, 'dentist', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: dentist', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:04'),
(3, NULL, 'dentist', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: dentist', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:21'),
(4, NULL, 'dentist', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: dentist', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:21'),
(5, NULL, 'staff', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: staff', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:30'),
(6, NULL, 'staff', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: staff', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:42'),
(7, NULL, 'staff', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: staff', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:09:42'),
(8, 3, 'staff', 'staff', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success', '2026-03-28 07:09:50'),
(9, NULL, 'staff', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: staff', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:10:02'),
(10, NULL, 'staff', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: staff', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:10:03'),
(11, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:10:22'),
(12, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:10:32'),
(13, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:10:32'),
(14, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:11:07'),
(15, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:11:09'),
(16, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:11:10'),
(17, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:11:13'),
(18, NULL, 'dentist', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: dentist', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:11:36'),
(19, NULL, 'admin', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: admin', NULL, NULL, 'users', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'failed', '2026-03-28 07:12:01');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

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

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `treatment_id`, `appointment_id`, `total_amount`, `paid_amount`, `balance`, `payment_status`, `billing_date`, `due_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, NULL, 2400.00, 2400.00, 0.00, 'paid', '2026-02-05', '2026-02-12', 'General Service', '2026-03-28 06:52:29', '2026-03-28 06:52:29'),
(2, 5, NULL, NULL, 500.00, 0.00, 500.00, 'unpaid', '2026-03-28', '2026-03-28', 'Initial billing from admission - Services: Periapical Xray', '2026-03-28 06:53:56', '2026-03-28 06:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `dental_history`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `insurance_providers`
--

CREATE TABLE `insurance_providers` (
  `id` int(11) NOT NULL,
  `provider_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

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

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `allergies`, `current_medications`, `medical_conditions`, `blood_pressure`, `heart_rate`, `other_notes`, `created_at`, `updated_at`) VALUES
(1, 5, NULL, '', 'None', NULL, NULL, NULL, '2026-03-28 06:53:56', '2026-03-28 06:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `action_url`, `created_at`) VALUES
(1, 1, 'patient', 'New Patient Registration', 'boss ads has been registered as a new patient', 0, 'admin_patients.php', '2026-03-28 06:53:56'),
(2, 2, 'patient', 'New Patient Registration', 'boss ads has been registered as a new patient', 0, 'admin_patients.php', '2026-03-28 06:53:56'),
(3, 3, 'patient', 'New Patient Registration', 'boss ads has been registered as a new patient', 0, 'dentist_patients.php', '2026-03-28 06:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
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

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `age`, `gender`, `date_of_birth`, `phone`, `email`, `address`, `city`, `province`, `zip_code`, `religion`, `dental_insurance`, `insurance_effective_date`, `registration_source`, `source_appointment_id`, `status`, `is_archived`, `created_at`, `updated_at`, `created_by`, `deleted_at`) VALUES
(1, NULL, 'Mark', NULL, 'Tan', NULL, NULL, 'Male', NULL, '09170000001', 'mark.tan@example.com', 'Cebu City', NULL, NULL, NULL, NULL, NULL, NULL, 'direct', NULL, 'active', 0, '2026-03-28 06:52:29', '2026-03-28 06:52:29', NULL, NULL),
(2, NULL, 'Juan', NULL, 'Flores', NULL, NULL, 'Male', NULL, '09170000002', 'juan.flores@example.com', 'Mandaue City', NULL, NULL, NULL, NULL, NULL, NULL, 'direct', NULL, 'active', 0, '2026-03-28 06:52:29', '2026-03-28 06:52:29', NULL, NULL),
(3, NULL, 'Kevin', 'Miguel', 'Lim', NULL, NULL, 'Male', NULL, '09170000003', 'kevin.lim@example.com', 'Lapu-Lapu City', NULL, NULL, NULL, NULL, NULL, NULL, 'direct', NULL, 'active', 0, '2026-03-28 06:52:29', '2026-03-28 06:52:29', NULL, NULL),
(4, NULL, 'Jona', NULL, 'Casinillo', NULL, NULL, 'Female', NULL, '09170000004', 'jona.casinillo@example.com', 'Cebu City', NULL, NULL, NULL, NULL, NULL, NULL, 'direct', NULL, 'active', 0, '2026-03-28 06:52:29', '2026-03-28 06:52:29', NULL, NULL),
(5, NULL, 'boss', 'dasd', 'ads', 'asd', 24, 'male', '2002-01-31', '09951513213', '', 'daads', 'dasd', 'adsad', '646', '', '', '0000-00-00', 'walk-in', NULL, 'active', 0, '2026-03-28 06:53:56', '2026-03-28 06:53:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `billing_id`, `patient_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 2, 2400.00, 'cash', '2026-02-05', 'INV-002', 'Sample payment', 1, '2026-03-28 06:52:29');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

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

--
-- Dumping data for table `queue`
--

INSERT INTO `queue` (`id`, `patient_id`, `treatment_type`, `teeth_numbers`, `procedure_notes`, `status`, `completed_at`, `is_processed`, `priority`, `queue_time`, `notes`, `created_at`, `updated_at`, `is_archived`, `deleted_at`) VALUES
(1, 5, 'Periapical Xray', '25', NULL, 'waiting', NULL, 0, 5, '14:53:56', NULL, '2026-03-28 06:53:56', '2026-03-28 06:53:56', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

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

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `mode`, `price`, `duration_minutes`, `description`, `is_active`, `created_at`, `updated_at`, `is_archived`, `deleted_at`) VALUES
(1, 'Teeth Cleaning', 'BULK', 800.00, 60, 'Case to case cleaning', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(2, 'Tooth Restoration (Pasta)', 'SINGLE', 800.00, 45, 'Restores decayed teeth using composite filling.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(3, 'Tooth Extraction (Ibot)', 'SINGLE', 800.00, 45, 'Safe removal of a damaged or non-restorable tooth.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(4, 'Root Canal Treatment', 'SINGLE', 5000.00, 90, 'Saves an infected tooth by removing the pulp.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(5, 'Consultation', 'NONE', 500.00, 30, 'Professional oral health assessment and planning.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(6, 'Periapical Xray', 'SINGLE', 500.00, 15, 'Single-tooth X-ray showing the root and surrounding bone', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(7, 'Denture Adjustment', 'NONE', 500.00, 30, 'Reshaping of dentures to improve fit and comfort.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(8, 'Removable Dentures', 'NONE', 5000.00, 30, 'Custom-made removable replacement for missing teeth.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(9, 'Crowns (Jacket)', 'SINGLE', 2000.00, 90, 'Protective cap to restore tooth shape and strength.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(10, 'Fixed Bridge', 'BULK', 5000.00, 90, 'Permanent replacement for missing teeth anchored to adjacent teeth.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(11, 'Teeth Whitening', 'NONE', 5000.00, 90, 'Cosmetic procedure to lighten teeth and remove stains.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL),
(12, 'Orthodontic Appliance', 'BULK', 35000.00, 120, 'Devices like braces or retainers to align teeth.', 1, '2026-03-28 06:52:29', '2026-03-28 06:52:29', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `treatments`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plans`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL DEFAULT '',
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

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `username`, `password`, `email`, `role`, `first_login`, `status`, `created_at`, `updated_at`, `is_archived`, `deleted_at`) VALUES
(1, '', NULL, '', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@rfdental.com', 'admin', 0, 'active', '2026-03-28 06:52:28', '2026-03-28 06:52:28', 0, NULL),
(2, '', NULL, '', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff@rfdental.com', 'staff', 0, 'active', '2026-03-28 06:52:28', '2026-03-28 06:52:28', 0, NULL),
(3, '', NULL, '', 'dentist', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dentist@rfdental.com', 'dentist', 0, 'active', '2026-03-28 06:52:28', '2026-03-28 06:52:28', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointments_archived` (`is_archived`),
  ADD KEY `idx_is_registered_patient` (`is_registered_patient`),
  ADD KEY `idx_converted_patient_id` (`converted_patient_id`),
  ADD KEY `appointments_ibfk_1` (`patient_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_billing_date` (`billing_date`),
  ADD KEY `billing_ibfk_1` (`patient_id`),
  ADD KEY `billing_ibfk_2` (`treatment_id`),
  ADD KEY `billing_ibfk_3` (`appointment_id`);

--
-- Indexes for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dental_history_ibfk_1` (`patient_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiries_archived` (`is_archived`),
  ADD KEY `inquiries_ibfk_1` (`topic`),
  ADD KEY `fk_inquiry_converted_patient` (`converted_patient_id`);

--
-- Indexes for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_history_ibfk_1` (`patient_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patients_archived` (`is_archived`),
  ADD KEY `idx_patients_deleted_at` (`deleted_at`),
  ADD KEY `idx_registration_source` (`registration_source`),
  ADD KEY `fk_patient_user` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `payments_ibfk_1` (`billing_id`),
  ADD KEY `payments_ibfk_2` (`patient_id`),
  ADD KEY `payments_ibfk_3` (`created_by`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescriptions_ibfk_1` (`patient_id`),
  ADD KEY `prescriptions_ibfk_2` (`doctor_id`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_archived` (`is_archived`),
  ADD KEY `queue_ibfk_1` (`patient_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_services_archived` (`is_archived`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_treatment_date` (`treatment_date`),
  ADD KEY `treatments_ibfk_1` (`patient_id`),
  ADD KEY `treatments_ibfk_2` (`doctor_id`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_treatment_plans_archived` (`is_archived`),
  ADD KEY `treatment_plans_ibfk_1` (`patient_id`),
  ADD KEY `treatment_plans_ibfk_2` (`doctor_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_archived` (`is_archived`),
  ADD KEY `idx_users_deleted_at` (`deleted_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dental_history`
--
ALTER TABLE `dental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD CONSTRAINT `dental_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiry_converted_patient` FOREIGN KEY (`converted_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`topic`) REFERENCES `services` (`id`);

--
-- Constraints for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `treatments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatment_plans_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
