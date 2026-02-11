-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 12:24 PM
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
-- Table structure for table `appointments`
--

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

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `first_name`, `middle_name`, `last_name`, `patient_id`, `appointment_date`, `appointment_time`, `treatment`, `notes`, `status`, `created_at`, `updated_at`, `created_by`, `is_archived`, `deleted_at`, `is_registered_patient`, `converted_patient_id`, `is_converted_to_patient`) VALUES
(36, 'Kirk', 'Seno', 'Palangan', NULL, '2026-02-09 00:00:00', '14:00:00', 'Teeth Cleaning', 'case to case cleaning', 'scheduled', '2026-02-08 05:13:44', '2026-02-08 05:13:44', 4, 0, NULL, 0, NULL, 0),
(37, 'Marrie', 'Dee', 'Lastimosa', NULL, '2026-02-17 00:00:00', '13:58:00', 'General Checkup', 'lower teeth daw kay ga sakit', 'scheduled', '2026-02-08 05:14:51', '2026-02-08 05:14:51', 4, 0, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for failed logins',
  `username` varchar(100) DEFAULT NULL COMMENT 'NULL if user does not exist',
  `user_role` enum('admin','dentist','staff') DEFAULT NULL,
  `action_type` enum('login','logout','create','read','update','delete','payment','status_change','failed_login') NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'Which section: users, patients, appointments, queue, treatments, billing, payments, inquiries, treatment_plans',
  `record_id` int(11) DEFAULT NULL COMMENT 'ID of the affected record',
  `record_type` varchar(50) DEFAULT NULL COMMENT 'Table name that was affected',
  `description` text NOT NULL COMMENT 'Human-readable description of the action performed',
  `old_value` text DEFAULT NULL COMMENT 'Previous value (for update actions)',
  `new_value` text DEFAULT NULL COMMENT 'New value (for update actions)',
  `affected_table` varchar(50) DEFAULT NULL COMMENT 'Table name that was affected',
  `affected_id` int(11) DEFAULT NULL COMMENT 'Primary key of the affected record',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success' COMMENT 'For login attempts',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `action_type`, `module`, `record_id`, `record_type`, `description`, `old_value`, `new_value`, `affected_table`, `affected_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, NULL, 'invalid_user', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt', NULL, NULL, 'users', NULL, 'unknown', 'unknown', 'failed', '2026-02-10 17:42:07'),
(2, 1, 'admin', 'admin', 'login', 'users', 0, 'Successful login attempt', '1', NULL, NULL, 'Successful login attempt', 0, 'unknown', 'unknown', 'success', '2026-02-10 17:42:33'),
(3, NULL, 'invalid_user', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt', NULL, NULL, 'users', NULL, 'unknown', 'unknown', 'failed', '2026-02-10 17:42:33'),
(4, 4, 'staff', 'staff', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:42:36'),
(5, 6, 'dentist', 'dentist', 'login', 'users', NULL, NULL, 'Successful login from web browser', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:43:17'),
(6, 6, 'dentist', 'dentist', 'status_change', 'queue', 58, 'queue', 'Started procedure for patient: Trisha Mae Macapagal Albuela (Q-0058)', 'waiting', 'in_procedure', 'queue', 58, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:43:24'),
(7, 1, 'admin', 'admin', 'login', 'users', 1, 'users', 'Test user login', NULL, NULL, 'users', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(8, 1, 'admin', 'admin', 'logout', 'users', 1, 'users', 'Test user logout', NULL, NULL, 'users', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(9, 1, 'admin', 'admin', 'create', 'patients', 1, 'patients', 'Test patient creation', NULL, NULL, 'patients', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(10, 1, 'admin', 'admin', 'read', 'patients', 1, 'patients', 'Test patient view', NULL, NULL, 'patients', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(11, 1, 'admin', 'admin', 'update', 'patients', 1, 'patients', 'Test patient update', NULL, NULL, 'patients', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(12, 1, 'admin', 'admin', 'delete', 'patients', 1, 'patients', 'Test patient deletion', NULL, NULL, 'patients', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(13, 1, 'admin', 'admin', 'payment', 'billing', 1, 'billing', 'Test payment processing', NULL, NULL, 'billing', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(14, 1, 'admin', 'admin', 'status_change', 'queue', 1, 'queue', 'Test queue status change', NULL, NULL, 'queue', 1, 'unknown', 'unknown', 'success', '2026-02-10 17:43:25'),
(15, NULL, 'test_user', NULL, 'failed_login', 'users', NULL, 'users', 'Failed login attempt - Invalid password for user: test_user', NULL, NULL, 'users', NULL, 'unknown', 'unknown', 'failed', '2026-02-10 17:43:25'),
(16, 6, 'dentist', 'dentist', 'create', 'treatments', 12, 'treatments', 'Created treatment record for patient: Trisha Mae Macapagal Albuela - Orthodontic Appliance', NULL, 'Orthodontic Appliance', 'treatments', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:44:02'),
(17, 6, 'dentist', 'dentist', 'status_change', 'queue', 58, 'queue', 'Completed treatment for patient: Trisha Mae Macapagal Albuela (Q-0058)', 'in_procedure', 'completed', 'queue', 58, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:44:02'),
(18, 6, 'dentist', 'dentist', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:44:22'),
(19, 4, 'staff', 'staff', 'login', 'users', NULL, NULL, 'Successful login from web browser', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:44:30'),
(20, 4, 'staff', 'staff', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:45:51'),
(21, 6, 'dentist', 'dentist', 'login', 'users', NULL, NULL, 'Successful login from web browser', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:45:56'),
(22, 6, 'dentist', 'dentist', 'status_change', 'queue', 59, 'queue', 'Started procedure for patient: Kyle Shee Libertad Micabani (Q-0059)', 'waiting', 'in_procedure', 'queue', 59, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:46:03'),
(23, 6, 'dentist', 'dentist', 'create', 'treatments', 13, 'treatments', 'Created treatment record for patient: Kyle Shee Libertad Micabani - Consultation', NULL, 'Consultation', 'treatments', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:46:41'),
(24, 6, 'dentist', 'dentist', 'status_change', 'queue', 59, 'queue', 'Completed treatment for patient: Kyle Shee Libertad Micabani (Q-0059)', 'in_procedure', 'completed', 'queue', 59, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:46:41'),
(25, 6, 'dentist', 'dentist', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:46:58'),
(26, 4, 'staff', 'staff', 'login', 'users', NULL, NULL, 'Successful login from web browser', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 17:47:03'),
(27, 4, 'staff', 'staff', 'logout', 'users', NULL, NULL, 'User logged out', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:08:41'),
(28, 4, 'staff', 'staff', 'login', 'users', NULL, NULL, 'Successful login from web browser', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:08:51'),
(29, 4, 'staff', 'staff', 'payment', 'billing', 18, 'billing', 'Payment recorded: ₱550.00 for patient: Kyle Shee Micabani (INV-018)', 'unpaid', 'paid', 'billing', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:11:42'),
(30, 4, 'staff', 'staff', 'create', 'payments', 13, 'payments', 'Created payment record: ₱550.00 for patient: Kyle Shee Micabani', NULL, '₱550.00', 'payments', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:11:42'),
(31, 4, 'staff', 'staff', 'payment', 'billing', 15, 'billing', 'Payment recorded: ₱35,000.00 for patient: Trisha Mae Albuela (INV-015)', 'unpaid', 'paid', 'billing', 15, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:16'),
(32, 4, 'staff', 'staff', 'create', 'payments', 14, 'payments', 'Created payment record: ₱35,000.00 for patient: Trisha Mae Albuela', NULL, '₱35,000.00', 'payments', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:16'),
(33, 4, 'staff', 'staff', 'payment', 'billing', 16, 'billing', 'Payment recorded: ₱820.00 for patient: Trisha Mael Albuela (INV-016)', 'paid', 'paid', 'billing', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:35'),
(34, 4, 'staff', 'staff', 'create', 'payments', 15, 'payments', 'Created payment record: ₱820.00 for patient: Trisha Mael Albuela', NULL, '₱820.00', 'payments', 15, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:35'),
(35, 4, 'staff', 'staff', 'payment', 'billing', 16, 'billing', 'Payment recorded: ₱820.00 for patient: Trisha Mael Albuela (INV-016)', 'paid', 'paid', 'billing', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:48'),
(36, 4, 'staff', 'staff', 'create', 'payments', 16, 'payments', 'Created payment record: ₱820.00 for patient: Trisha Mael Albuela', NULL, '₱820.00', 'payments', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:48'),
(37, 4, 'staff', 'staff', 'payment', 'billing', 16, 'billing', 'Payment recorded: ₱820.00 for patient: Trisha Mael Albuela (INV-016)', 'paid', 'paid', 'billing', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:57'),
(38, 4, 'staff', 'staff', 'create', 'payments', 17, 'payments', 'Created payment record: ₱820.00 for patient: Trisha Mael Albuela', NULL, '₱820.00', 'payments', 17, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'success', '2026-02-10 18:12:57');

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
(13, 77, NULL, NULL, 840.00, 0.00, 840.00, 'unpaid', '2026-02-10', '2026-02-10', '', '2026-02-10 07:37:40', '2026-02-10 07:37:51'),
(14, 76, NULL, NULL, 820.00, 0.00, 820.00, 'unpaid', '2026-02-10', '2026-02-10', '', '2026-02-10 08:02:36', '2026-02-10 17:19:05'),
(15, 78, NULL, NULL, 35000.00, 35000.00, 0.00, 'paid', '2026-02-11', '2026-02-11', 'Initial billing from admission - Services: Orthodontic Appliance', '2026-02-10 17:17:53', '2026-02-10 18:12:16'),
(16, 76, NULL, NULL, 820.00, 7380.00, 0.00, 'paid', '2026-02-11', '2026-02-18', NULL, '2026-02-10 17:19:40', '2026-02-10 18:12:57'),
(17, 77, NULL, NULL, 840.00, 840.00, 0.00, 'paid', '2026-02-11', '2026-02-18', NULL, '2026-02-10 17:26:47', '2026-02-10 17:26:47'),
(18, 79, NULL, NULL, 550.00, 550.00, 0.00, 'paid', '2026-02-11', '2026-02-11', '', '2026-02-10 17:45:20', '2026-02-10 18:11:42');

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

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `first_name`, `middle_name`, `last_name`, `contact_info`, `source`, `inquiry_message`, `status`, `converted_patient_id`, `created_at`, `updated_at`, `is_archived`, `deleted_at`, `topic`) VALUES
(45, 'Kyle', 'Libertad', 'Micabani', '09241534656', 'Phone call', 'nag ask lang daw sya po :)\r\nhihi nag kaon naka doc? yabag', 'New Admission', NULL, '2026-02-07 09:04:05', '2026-02-07 13:48:07', 0, NULL, 66),
(46, 'Krystel Ann', 'Medina', 'Malazarte', '09271544652', 'Walk-in', '', 'New Admission', NULL, '2026-02-07 09:04:41', '2026-02-07 13:45:45', 0, NULL, 69),
(47, 'Jery', 'Mariam', 'Maglungsod', '09441534654', 'Fb messenger', '', 'New Admission', NULL, '2026-02-09 15:14:59', '2026-02-09 15:16:14', 0, NULL, 70);

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

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

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

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `first_name`, `middle_name`, `last_name`, `suffix`, `age`, `gender`, `date_of_birth`, `phone`, `email`, `address`, `city`, `province`, `zip_code`, `religion`, `dental_insurance`, `insurance_effective_date`, `registration_source`, `source_appointment_id`, `status`, `is_archived`, `created_at`, `updated_at`, `created_by`, `deleted_at`) VALUES
(76, 'Trisha Mael', 'Macapagal', 'Albuela', '', 14, 'female', '2012-01-31', '09271544652', 'trishaaame43@gmail.com', 'Zayas', 'Cagayan de oro city', 'Misamis Oriental', '9000', '', '', '0000-00-00', 'direct', NULL, 'active', 0, '2026-02-10 07:31:59', '2026-02-10 08:02:36', NULL, NULL),
(77, 'Krystel Ann', 'Medina', 'Malazarte', '', 22, 'female', '2003-11-29', '09271544652', 'Krsstl24@gmail.com', 'Zayas', 'Cagayan de oro city', 'Misamis Oriental', '9000', '', '', '0000-00-00', 'direct', NULL, 'active', 0, '2026-02-10 07:37:40', '2026-02-10 07:37:40', NULL, NULL),
(78, 'Trisha Mae', 'Macapagal', 'Albuela', '', 22, 'female', '2003-12-31', '09271544652', 'trishaaame43@gmail.com', 'Zayas', 'Cagayan de oro city', 'Misamis Oriental', '9000', '', '', '0000-00-00', 'direct', NULL, 'active', 0, '2026-02-10 17:17:53', '2026-02-10 17:17:53', NULL, NULL),
(79, 'Kyle Shee', 'Libertad', 'Micabani', '', 19, 'male', '2006-12-30', '09241534656', 'james_delacruz@gmail.com', 'Igpit', 'Opol', 'Misamis Oriental', '9000', '', '', '0000-00-00', 'direct', NULL, 'active', 0, '2026-02-10 17:45:20', '2026-02-10 17:45:20', NULL, NULL);

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
(6, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:19:40'),
(7, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:20:02'),
(8, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:20:15'),
(9, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:20:21'),
(10, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:23:14'),
(11, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:26:39'),
(12, 17, 77, 840.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 17:26:47'),
(13, 18, 79, 550.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 18:11:42'),
(14, 15, 78, 35000.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 18:12:16'),
(15, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 18:12:35'),
(16, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 18:12:48'),
(17, 16, 76, 820.00, 'Cash', '2026-02-11', NULL, NULL, 4, '2026-02-10 18:12:57');

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
(55, 76, 'Tooth Extraction (Ibot)', '28', NULL, 'completed', '2026-02-10 07:36:01', 1, 5, '15:31:59', NULL, '2026-02-10 07:31:59', '2026-02-10 07:36:01', 0, NULL),
(56, 77, 'Tooth Extraction (Ibot)', '48', NULL, 'completed', '2026-02-10 07:38:38', 1, 5, '15:37:40', NULL, '2026-02-10 07:37:40', '2026-02-10 07:38:38', 0, NULL),
(57, 76, 'Teeth Cleaning', '18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28, 48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38', NULL, 'completed', '2026-02-10 17:19:11', 1, 5, '16:02:36', NULL, '2026-02-10 08:02:36', '2026-02-10 17:19:11', 0, NULL),
(58, 78, 'Orthodontic Appliance', '18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28, 48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38', NULL, 'completed', '2026-02-10 17:44:02', 1, 5, '01:17:53', NULL, '2026-02-10 17:17:53', '2026-02-10 17:44:02', 0, NULL),
(59, 79, 'Consultation', '', NULL, 'completed', '2026-02-10 17:46:41', 1, 5, '01:45:20', NULL, '2026-02-10 17:45:20', '2026-02-10 17:46:41', 0, NULL);

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
(59, 'Teeth Cleaning', 'BULK', 800.00, 60, 'Case to case cleaning', 1, '2026-01-21 14:48:42', '2026-01-21 14:48:42', 0, NULL),
(60, 'Tooth Restoration (Pasta)', 'SINGLE', 800.00, 45, 'Restores decayed teeth using composite filling.', 1, '2026-01-21 14:55:10', '2026-01-21 14:55:10', 0, NULL),
(61, 'Tooth Extraction (Ibot)', 'SINGLE', 800.00, 45, 'Safe removal of a damaged or non-restorable tooth.', 1, '2026-01-21 14:55:46', '2026-01-21 15:10:32', 0, NULL),
(62, 'Root Canal Treatment', 'SINGLE', 5000.00, 90, 'Saves an infected tooth by removing the pulp.', 1, '2026-01-21 14:57:32', '2026-01-21 14:57:32', 0, NULL),
(63, 'Consultation', 'NONE', 500.00, 30, 'Professional oral health assessment and planning.', 1, '2026-01-21 14:58:21', '2026-01-21 14:58:21', 0, NULL),
(64, 'Periapical Xray', 'SINGLE', 500.00, 15, 'Single-tooth X-ray showing the root and surrounding bone', 1, '2026-01-21 15:00:34', '2026-01-21 15:00:34', 0, NULL),
(65, 'Denture Adjustment', 'NONE', 500.00, 30, 'Reshaping of dentures to improve fit and comfort.', 1, '2026-01-21 15:02:13', '2026-01-21 15:02:13', 0, NULL),
(66, 'Removable Dentures', 'NONE', 5000.00, 30, 'Custom-made removable replacement for missing teeth.', 1, '2026-01-21 15:03:22', '2026-01-21 15:03:22', 0, NULL),
(67, 'Crowns (Jacket)', 'SINGLE', 2000.00, 90, 'Protective cap to restore tooth shape and strength.', 1, '2026-01-21 15:04:22', '2026-01-21 15:04:22', 0, NULL),
(68, 'Fixed Bridge', 'BULK', 5000.00, 90, 'Permanent replacement for missing teeth anchored to adjacent teeth.', 1, '2026-01-21 15:05:07', '2026-01-21 15:05:07', 0, NULL),
(69, 'Teeth Whitening', 'NONE', 5000.00, 90, 'Cosmetic procedure to lighten teeth and remove stains.', 1, '2026-01-21 15:05:58', '2026-01-21 15:05:58', 0, NULL),
(70, 'Orthodontic Appliance', 'BULK', 35000.00, 120, 'Devices like braces or retainers to align teeth.', 1, '2026-01-21 15:08:06', '2026-01-21 15:08:06', 0, NULL);

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

--
-- Dumping data for table `treatments`
--

INSERT INTO `treatments` (`id`, `patient_id`, `treatment_date`, `procedure_name`, `tooth_number`, `description`, `status`, `doctor_id`, `cost`, `notes`, `created_at`, `updated_at`) VALUES
(9, 76, '2026-02-10', 'Tooth Extraction (Ibot)', '28', 'Treatment completed from queue', 'completed', 6, NULL, '', '2026-02-10 07:36:01', '2026-02-10 07:36:01'),
(10, 77, '2026-02-10', 'Tooth Extraction (Ibot)', '48', 'Treatment completed from queue', 'completed', 6, NULL, '', '2026-02-10 07:38:38', '2026-02-10 07:38:38'),
(11, 76, '2026-02-11', 'Teeth Cleaning', '18, 17, 16, 15, 14, ', 'Treatment completed from queue', 'completed', 6, NULL, '', '2026-02-10 17:19:11', '2026-02-10 17:19:11'),
(12, 78, '2026-02-11', 'Orthodontic Appliance', '18, 17, 16, 15, 14, ', 'Treatment completed from queue', 'completed', 6, NULL, '', '2026-02-10 17:44:02', '2026-02-10 17:44:02'),
(13, 79, '2026-02-11', 'Consultation', '', 'Treatment completed from queue', 'completed', 6, NULL, '', '2026-02-10 17:46:41', '2026-02-10 17:46:41');

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
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','dentist','staff','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `username`, `password`, `email`, `full_name`, `role`, `created_at`, `updated_at`, `is_archived`, `deleted_at`) VALUES
(1, '', NULL, '', 'adminn', '$2y$10$fBqiR.vEHMxmVTuyCB6GrODnHfiaF9fjOORHRlm9KN.iU46V46kJC', 'admin@rfdental.com', NULL, 'admin', '2026-01-02 06:32:17', '2026-02-10 17:39:28', 0, NULL),
(4, '', NULL, '', 'staff', '$2y$10$jRGwuu0MuLLPiNaDV5uz8.SSueIZkw7AwOeyouvIPCGnftm/OcBdK', 'staff@rfdental.com', NULL, 'staff', '2026-01-09 14:40:00', '2026-02-10 17:39:28', 0, NULL),
(6, '', NULL, '', 'dentist', '$2y$10$J.tCZfkE.TDCaYClTem.GertJO7FDz2dwXDkukVluf0nWfJg1qzW2', 'dentist@rfdental.com', NULL, 'dentist', '2026-01-12 02:44:24', '2026-02-10 17:39:28', 0, NULL),
(213, '', NULL, '', 'chimp', '$2y$10$XBxSm7AfCTPKNxYdUpnk9eMxalhzr.oKybRQTlj8dVz38g1KfEdta', 'chimp.artfiles@gmail.com', NULL, 'staff', '2026-01-16 06:46:40', '2026-02-10 17:39:28', 0, NULL),
(214, '', NULL, '', 'james', '$2y$10$sEEhpydSTyeVrLigBNLJfe/PvVObMCy7ZmyRBjV64y56AbaZ6S6v6', 'james33@gmail.com', NULL, 'staff', '2026-01-26 16:09:49', '2026-02-10 17:39:28', 0, NULL),
(215, 'Boss', '', 'Zata', 'boss', '$2y$10$bJmmt17nhalOd5TsSsdC1O9cvkmtVoYbhTHG71qKLCk.kdtMlj8OO', 'bosszata20@gmail.com', NULL, 'dentist', '2026-01-26 18:06:23', '2026-02-10 17:39:28', 0, NULL),
(216, 'david', '', 'casinillo', 'david', '$2y$10$tln3pNuAFFgKkPwhEDpbJ.vKkwrzKl3AzuGnOuqBAJHWudcSIq/YK', 'davidcasinillo@gmail.com', NULL, 'dentist', '2026-01-27 01:40:32', '2026-02-10 17:39:28', 0, NULL),
(217, 'jayson', 'rrr', 'belmes', 'jayson', '$2y$10$qBmRuLaPlARta.oDG4rn6O5wxUuP3d.fDh.0MAvWVvQx/M2ctIdYS', 'jayson@gmail.com', NULL, 'staff', '2026-01-27 06:54:15', '2026-02-10 17:39:28', 0, NULL),
(218, 'asdasd', 'asdsa', 'asdasd', 'sample', '$2y$10$qWuuGa0v/tG61SvtiDPWbuSAXx0HDxpAmXxtQPxNkL16vzx.JB08S', 'sample@gmail.com', NULL, 'dentist', '2026-02-02 13:45:54', '2026-02-10 17:39:28', 0, NULL),
(219, '', NULL, '', 'admin', '$2y$10$FXDdqW3kOxHDvH7nH9i/4.Mrrze218ezCvGOmk6jjcfuRXciQMuma', 'admin@rfdental.com', 'Administrator', 'admin', '2026-02-10 17:39:28', '2026-02-10 17:39:28', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `idx_appointments_archived` (`is_archived`),
  ADD KEY `idx_appointments_deleted_at` (`deleted_at`),
  ADD KEY `idx_is_registered_patient` (`is_registered_patient`),
  ADD KEY `idx_converted_patient_id` (`converted_patient_id`),
  ADD KEY `idx_is_converted` (`is_converted_to_patient`),
  ADD KEY `idx_converted_patient` (`converted_patient_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `treatment_id` (`treatment_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_billing_date` (`billing_date`);

--
-- Indexes for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_first_name` (`first_name`),
  ADD KEY `idx_last_name` (`last_name`),
  ADD KEY `idx_inquiries_archived` (`is_archived`),
  ADD KEY `idx_inquiries_deleted_at` (`deleted_at`),
  ADD KEY `topic` (`topic`),
  ADD KEY `idx_converted_patient` (`converted_patient_id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patients_archived` (`is_archived`),
  ADD KEY `idx_patients_deleted_at` (`deleted_at`),
  ADD KEY `idx_registration_source` (`registration_source`),
  ADD KEY `idx_source_appointment_id` (`source_appointment_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_id` (`billing_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_queue_time` (`queue_time`),
  ADD KEY `idx_queue_archived` (`is_archived`),
  ADD KEY `idx_queue_deleted_at` (`deleted_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_mode` (`mode`),
  ADD KEY `idx_services_archived` (`is_archived`),
  ADD KEY `idx_services_deleted_at` (`deleted_at`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_treatment_date` (`treatment_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_next_session` (`next_session_date`),
  ADD KEY `idx_treatment_plans_archived` (`is_archived`),
  ADD KEY `idx_treatment_plans_deleted_at` (`deleted_at`);

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
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `dental_history`
--
ALTER TABLE `dental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

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
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
