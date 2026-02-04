-- Active: 1759842436534@@127.0.0.1@3306@dental_management
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 02:39 AM
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
  `first_name` varchar(100) NOT NULL,   -- Required
  `middle_name` varchar(100) DEFAULT NULL,  -- Required
  `last_name` varchar(100) NOT NULL,    -- Required
  `patient_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `appointment_time` time NOT NULL DEFAULT '09:00:00',
  `treatment` varchar(100) DEFAULT 'General Checkup',
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `first_name`, `middle_name`, `last_name`, `patient_id`, `appointment_date`, `appointment_time`, `treatment`, `notes`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(1, NULL, NULL, NULL, 4, '2025-12-31 00:00:00', '19:04:00', 'General Checkup', 'sadsad', 'scheduled', '2026-01-14 11:02:30', '2026-01-14 11:02:30', 4),
(2, NULL, NULL, NULL, 5, '2026-01-15 00:00:00', '19:08:00', 'Teeth Cleaning', 'he needs cleaning later', 'scheduled', '2026-01-14 11:04:47', '2026-01-14 11:04:47', 4),
(3, NULL, NULL, NULL, 6, '2026-01-07 00:00:00', '12:06:00', 'Tooth Extraction', 'adwadwadags aweqawdwad', 'scheduled', '2026-01-14 13:06:49', '2026-01-14 13:06:49', 4),
(4, NULL, NULL, NULL, NULL, '2026-02-03 00:00:00', '09:30:00', 'General Checkup', 'Follow-up', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(5, 'David', NULL, 'Lim', 5, '2026-02-05 00:00:00', '10:00:00', 'Teeth Cleaning', 'Regular cleaning', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(6, 'watawaw', NULL, 'mali', NULL, '2026-02-07 00:00:00', '14:00:00', 'Tooth Extraction', 'Extraction needed', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(7, 'Joy', NULL, 'Mamski', 7, '2026-02-04 00:00:00', '11:00:00', 'Root Canal', 'Session 1', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(8, 'Juan', NULL, 'Dela Cruz', 8, '2026-02-06 00:00:00', '15:00:00', 'Filling', 'Cavity filling', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(9, 'Michael', 'James', 'Lopez', 11, '2026-02-08 00:00:00', '09:30:00', 'Denture Adjustment', 'Adjustment needed', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(10, 'Sarah', NULL, 'Miller', 12, '2026-02-09 00:00:00', '13:00:00', 'Teeth Whitening', 'Cosmetic procedure', 'scheduled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(11, 'Daniel', NULL, 'Santos', 13, '2026-02-10 00:00:00', '10:30:00', 'Consultation', 'Initial consultation', 'completed', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(12, 'Emily', 'Grace', 'Nguyen', 16, '2026-02-01 00:00:00', '11:00:00', 'Cleaning', 'Post-treatment', 'completed', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(13, 'John', NULL, 'Reyes', 19, '2026-02-02 00:00:00', '14:00:00', 'Orthodontic Checkup', 'Braces checkup', 'completed', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(14, 'Maria', 'Isabel', 'Dela Cruz', 20, '2026-01-31 00:00:00', '09:00:00', 'Filling Repair', 'Repair needed', 'cancelled', '2026-02-02 22:10:26', '2026-02-02 22:10:26', 4),
(15, 'Patrick', NULL, 'Lim', 5, '2026-02-05 00:00:00', '10:00:00', 'Teeth Cleaning', 'Regular cleaning', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(16, NULL, NULL, 'Mamski', 7, '2026-02-04 00:00:00', '11:00:00', 'Root Canal', 'Session 1', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(17, 'Juan', NULL, 'Dela Cruz', 8, '2026-02-06 00:00:00', '15:00:00', 'Filling', 'Cavity filling', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(18, NULL, NULL, 'Mamski', 7, '2026-02-04 00:00:00', '11:00:00', 'Root Canal', 'Session 1', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(19, 'Juan', NULL, 'Dela Cruz', 8, '2026-02-06 00:00:00', '15:00:00', 'Filling', 'Cavity filling', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(20, 'Michael', 'James', 'Lopez', 11, '2026-02-08 00:00:00', '09:30:00', 'Denture Adjustment', 'Adjustment needed', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(21, 'Sandra', NULL, 'Miller', 12, '2026-02-09 00:00:00', '13:00:00', 'Teeth Whitening', 'Cosmetic procedure', 'scheduled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(22, 'Daniel', NULL, 'Santos', 13, '2026-02-10 00:00:00', '10:30:00', 'Consultation', 'Initial consultation', 'completed', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(23, 'Grace', NULL, 'Nguyen', 16, '2026-02-01 00:00:00', '11:00:00', 'Cleaning', 'Post-treatment', 'completed', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(24, 'John', NULL, 'Reyes', 19, '2026-02-02 00:00:00', '14:00:00', 'Orthodontic Checkup', 'Braces checkup', 'completed', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4),
(25, 'Maria', 'Isabel', 'Dela Cruz', 20, '2026-01-31 00:00:00', '09:00:00', 'Filling Repair', 'Repair needed', 'cancelled', '2026-02-02 22:12:39', '2026-02-02 22:12:39', 4);

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

-- --------------------------------------------------------

--
-- Table structure for table `dental_history`
--

CREATE TABLE `dental_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `previous_dentist` varchar(100) DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `reason_last_visit` text DEFAULT NULL,
  `previous_treatments` text DEFAULT NULL,
  `current_complaints` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `source` enum('Fb messenger','Phone call','Walk-in') NOT NULL DEFAULT 'Fb messenger',
  `inquiry_message` text DEFAULT NULL,
  `topic` varchar(255) DEFAULT 'General',
  `status` enum('Pending','Answered','Booked','New Admission') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_source` (`source`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_first_name` (`first_name`),
  KEY `idx_last_name` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` varchar(20) DEFAULT NULL,
  `other_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `last_name` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `dental_insurance` varchar(255) DEFAULT NULL,
  `insurance_effective_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `last_name`, `first_name`, `full_name`, `phone`, `email`, `address`, `date_of_birth`, `gender`, `created_at`, `updated_at`, `created_by`) VALUES
(4, '', 'sdasd', 'sdasd', '09525231006', NULL, NULL, NULL, NULL, '2026-01-14 11:02:30', '2026-01-14 11:02:30', NULL),
(5, 'Lim', 'David', 'David Lim', '09525231006', NULL, NULL, NULL, NULL, '2026-01-14 11:04:47', '2026-01-14 11:04:47', NULL),
(6, 'mali', 'watawaw', 'watawaw mali', '09525231001', NULL, NULL, NULL, NULL, '2026-01-14 13:06:49', '2026-01-14 13:06:49', NULL);

INSERT INTO `patients` (`id`, `last_name`, `first_name`, `full_name`, `phone`, `email`, `address`, `date_of_birth`, `gender`, `created_at`, `updated_at`, `created_by`) VALUES
(7, 'Mamski', 'Joy', 'Joy Mamski Jr.', '09687889421', 'joy@gmail.com', 'st.francis123, Cagayan De Oro City, Misamis Oriental 9000', '1989-04-23', 'Male', '2026-02-02 12:00:00', '2026-02-02 12:00:00', NULL),
(8, 'Dela Cruz', 'Juan', 'Juan Dela Cruz Jr.', NULL, NULL, '123 Main St, Barangay', NULL, NULL, '2026-02-02 12:10:00', '2026-02-02 12:10:00', NULL);


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

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `mode` varchar(50) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `mode`, `duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(59, 'Case Cleaning', 'Case to case cleaning', 800.00, 'BULK', 60, 1, '2026-01-21 22:48:42', '2026-01-21 22:48:42'),
(60, 'Tooth Restoration (Pasta)', 'Restores decayed teeth using composite filling.', 800.00, 'SINGLE', 45, 1, '2026-01-21 22:55:10', '2026-01-21 22:55:10'),
(61, 'Tooth Extraction (Ibot)', 'Safe removal of a damaged or non-restorable tooth.', 800.00, 'SINGLE', 45, 1, '2026-01-21 22:55:46', '2026-01-21 23:10:32'),
(62, 'Root Canal Treatment', 'Saves an infected tooth by removing the pulp.', 5000.00, 'SINGLE', 90, 1, '2026-01-21 22:57:32', '2026-01-21 22:57:32'),
(63, 'Consultation', 'Professional oral health assessment and planning.', 500.00, 'NONE', 30, 1, '2026-01-21 22:58:21', '2026-01-21 22:58:21'),
(64, 'Periapical Xray', 'Single-tooth X-ray showing the root and surrounding...', 500.00, 'SINGLE', 15, 1, '2026-01-21 23:00:34', '2026-01-21 23:00:34'),
(65, 'Denture Adjustment', 'Reshaping of dentures to improve fit and comfort.', 500.00, 'NONE', 30, 1, '2026-01-21 23:02:13', '2026-01-21 23:02:13'),
(66, 'Removable Dentures', 'Custom-made removable replacement for missing teeth', 5000.00, 'NONE', 30, 1, '2026-01-21 23:03:22', '2026-01-21 23:03:22'),
(67, 'Crowns (Jacket)', 'Protective cap to restore tooth shape and strength...', 2000.00, 'SINGLE', 90, 1, '2026-01-21 23:04:22', '2026-01-21 23:04:22'),
(68, 'Fixed Bridge', 'Permanent replacement for missing teeth anchored t...', 5000.00, 'BULK', 90, 1, '2026-01-21 23:05:07', '2026-01-21 23:05:07'),
(69, 'Teeth Whitening', 'Cosmetic procedure to lighten teeth and remove sta...', 5000.00, 'NONE', 90, 1, '2026-01-21 23:05:58', '2026-01-21 23:05:58'),
(70, 'Orthodontic Appliance', 'Devices like braces or retainers to align teeth.', 35000.00, 'BULK', 120, 1, '2026-01-21 23:08:06', '2026-01-21 23:08:06');

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
  `teeth_numbers` varchar(100) DEFAULT NULL,
  `total_sessions` int(11) DEFAULT 1,
  `completed_sessions` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'active',
  `next_session_date` date DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table ``
--

CREATE TABLE IF NOT EXISTS queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    treatment_type VARCHAR(200),
    teeth_numbers VARCHAR(100),
    status ENUM('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') DEFAULT 'waiting',
    priority INT DEFAULT 5,
    queue_time TIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_queue_time (queue_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$fBqiR.vEHMxmVTuyCB6GrODnHfiaF9fjOORHRlm9KN.iU46V46kJC', 'admin@rfdental.com', 'Administrator', 'admin', '2026-01-02 14:32:17'),
(4, 'staff', '$2y$10$S2yS105JRGwuu0MuLLPiNaDV5uz8.SSueIZkw7AwOeyouvIPCGnftm/OcBdK', 'staff@rfdental.com', 'Staff', 'staff', '2026-01-09 22:40:00'),
(6, 'dentist', '$2y$10$S2yS105J.iCZIKE.TDCaYClTem.GertJO7FDz2dwXDkukVluf0nWfJg1qzW2', 'dentist@rfdental.com', 'Rex Gabz', 'dentist', '2026-01-12 10:44:24'),
(9, 'Fiors', '$2y$10$S2yS105S3y7eEV5UFbDTFSlvUD2v0RPKD.RRIP.fhMxDpBsV9..', 'floralyn@gmail.com', 'Floralyn Gabz', 'dentist', '2026-02-02 23:22:11');

--
-- Indexes for dumped tables
--



--
-- Indexes for table `appointments`
--
-- PRIMARY KEY is already defined in CREATE TABLE statement above
ALTER TABLE `appointments`
  ADD KEY `patient_id` (`patient_id`);

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
-- PRIMARY KEY is already defined in CREATE TABLE statement above

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
-- PRIMARY KEY is already defined in CREATE TABLE statement above

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
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_active` (`is_active`);

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
  ADD KEY `idx_next_session` (`next_session_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dental_history`
--
ALTER TABLE `dental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
