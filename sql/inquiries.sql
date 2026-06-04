-- phpMyAdmin SQL Dump
-- Table structure for table `inquiries`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `concern` text NOT NULL,
  `service` varchar(100) NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `pain_level` tinyint(2) DEFAULT NULL,
  `urgency` enum('Low','Medium','High') DEFAULT NULL,
  `status` enum('Pending','Contacted','Scheduled','Completed','Cancelled') DEFAULT 'Pending',
  `assigned_staff_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiries_patient` (`patient_id`),
  ADD KEY `idx_inquiries_status` (`status`),
  ADD KEY `idx_inquiries_assigned_staff` (`assigned_staff_id`);

ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiries_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inquiries_assigned_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT;
