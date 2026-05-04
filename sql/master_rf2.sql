-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 07:48 AM
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
-- Database: `master_rf`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `street_address` text DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `street_address`, `zip_code`, `city`, `province`) VALUES
(1, 'Nazareth, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(2, 'Kauswagan, Mambuaya, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(3, 'Mabini Street, Taglimao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(4, 'Veterans Avenue, Canitoan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(5, 'PNB Road,  Indahag, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(6, 'Sayre Highway, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(7, 'J.R. Borja Street, Consolacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(8, 'Sayre Highway, Pagatpat, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(9, 'Burgos Street, Kauswagan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(10, 'J.R. Borja Street, Bugo, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(11, 'Rizal Street, Kauswagan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(12, 'Flores Avenue, San Martin, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(13, 'Corrales Avenue, Taglimao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(14, 'Osmeña Street, Bayabas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(15, 'Luzon Avenue, San Martin, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(16, 'Gango Street, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(17, 'PNB Road, Barra, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(18, 'Alegria Street, Tagpangi, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(19, 'Mambuaya, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(20, 'Cordero Street, Kauswagan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(21, 'Patag, Cugman, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(22, 'PCGG Road, Mambuaya, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(23, 'PCGG Road, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(24, 'Patag, Taglimao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(25, 'Bayabas, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(26, 'Corrales Avenue, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(27, 'Sayre Highway, Pagalungan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(28, 'Yacapin Street, Mambuaya, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(29, 'Osmeña Street, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(30, 'Corrales Avenue, Balulang, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(31, 'Mambuaya, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(32, 'C.M. Recto Avenue, Tagpangi, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(33, 'Corrales Avenue, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(34, 'Alegria Street, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(35, 'Flores Avenue, Pagalungan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(36, 'Hayes Street, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(37, 'Burgos Street, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(38, 'Burgos Street, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(39, 'Flores Avenue, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(40, 'Bulua, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(41, 'Corrales Avenue, San Simon, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(42, 'Yacapin Street, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(43, 'Tiano Brothers Street,  Nazareth, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(44, 'Pryce Avenue, Balulang, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(45, 'Bonifacio Street, Bayabas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(46, 'Iglesia ni Cristo, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(47, 'Yacapin Street, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(48, 'J.R. Borja Street, Deboot, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(49, 'Cordero Street, Pagatpat, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(50, 'Iglesia ni Cristo, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(51, 'Mambuaya, Mambuaya, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(52, 'Gusa, Canitoan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(53, 'Tiano Brothers Street, Tignapoloan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(54, 'Gusa, Bayabas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(55, 'Cordero Street, Consolacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(56, 'Bonifacio Street, Carmen, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(57, 'Carmen, Pagalungan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(58, 'C.M. Recto Avenue, Tagpangi, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(59, 'Mabini Street,  Nazareth, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(60, 'Nazareth, Pagatpat, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(61, 'Capitol Road, Bugo, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(62, 'Mambuaya, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(63, 'Osmeña Street,  Indahag, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(64, 'J.R. Borja Street, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(65, 'Rizal Street, Upper Bubunawan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(66, 'Quezon Street, Kauswagan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(67, 'Gango Street, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(68, 'C.M. Recto Avenue, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(69, 'C.M. Recto Avenue, Deboot, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(70, 'J.R. Borja Street, San Simon, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(71, 'Yacapin Street, Lapasan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(72, 'Pryce Avenue, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(73, 'Alegria Street, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(74, 'Gusa, Taglimao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(75, 'Iglesia ni Cristo, Balulang, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(76, 'PCGG Road, Lapasan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(77, 'Gusa, Bayabas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(78, 'Quezon Street, Taglimao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(79, 'Bayabas, Tagpangi, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(80, 'Mindanao Avenue, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(81, 'Burgos Street, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(82, 'Osmeña Street, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(83, 'Gango Street, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(84, 'Nazareth, Balulang, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(85, 'Mindanao Avenue, Consolacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(86, 'Burgos Street, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(87, 'Alegria Street,  Nazareth, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(88, 'Burgos Street, Macasandig, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(89, 'Gango Street, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(90, 'J.R. Borja Street, Lapasan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(91, 'Patag, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(92, 'Sayre Highway, Consolacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(93, 'Mindanao Avenue, Lumbia, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(94, 'PCGG Road, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(95, 'Luzon Avenue,  Nazareth, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(96, 'Gango Street, Canitoan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(97, 'Burgos Street, Cugman, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(98, 'Nazareth, Poblacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(99, 'Nazareth, San Martin, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(100, 'J.R. Borja Street, Balacanas, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(101, 'Alegria Street, Deboot, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(102, 'Sayre Highway,  Nazareth, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(103, 'Hayes Street, Upper Bubunawan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(104, 'J.R. Borja Street, Upper Bubunawan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(105, 'Capitol Road, San Simon, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(106, 'C.M. Recto Avenue, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(107, 'Flores Avenue, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(108, 'Iglesia ni Cristo, Pugdao, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(109, 'Flores Avenue, Upper Bubunawan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(110, 'Gusa, Tignapoloan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(111, 'PNB Road, Lower Bubunawan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(112, 'Osmeña Street, Consolacion, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(113, 'Gusa, Mambuaya, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(114, 'Hayes Street, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(115, 'Flores Avenue, Iponan, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(116, 'Kauswagan, Camaman-an, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(117, 'Luzon Avenue, Balulang, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental'),
(118, 'Luzon Avenue, San Simon, Cagayan de Oro City', '9000', 'Cagayan de Oro City', 'Misamis Oriental');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `reference_code` varchar(10) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `is_registered_patient` tinyint(1) DEFAULT 1,
  `converted_patient_id` int(11) DEFAULT NULL,
  `booked_by_id` int(11) DEFAULT NULL,
  `relation_to_patient` varchar(50) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_reminded` tinyint(1) DEFAULT 0,
  `check_in_time` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `booking_type` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `reference_code`, `patient_id`, `is_registered_patient`, `converted_patient_id`, `booked_by_id`, `relation_to_patient`, `service_id`, `status_id`, `appointment_date`, `appointment_time`, `duration_minutes`, `notes`, `is_reminded`, `check_in_time`, `created_by`, `booking_type`, `created_at`, `updated_at`, `is_archived`, `deleted_at`, `cancelled_by`, `cancelled_at`) VALUES
(121, '', 121, 1, NULL, NULL, NULL, 1, 1, '2026-04-24', '09:00:00', NULL, '', 0, NULL, 13, NULL, '2026-04-23 05:14:36', NULL, 0, NULL, NULL, NULL),
(130, 'APT2604240', 123, 1, NULL, NULL, NULL, 3, 1, '2026-04-24', '15:30:00', 30, '', 0, NULL, 15, NULL, '2026-04-24 07:39:48', NULL, 0, NULL, NULL, NULL),
(133, 'APT2604241', 124, 1, NULL, NULL, NULL, 5, 2, '2026-04-24', '11:00:00', 120, '', 0, NULL, 16, NULL, '2026-04-24 08:00:31', NULL, 0, NULL, NULL, NULL),
(135, 'APT2604250', 133, 1, NULL, NULL, NULL, 2, 1, '2026-04-25', '13:30:00', 90, '', 0, NULL, 17, NULL, '2026-04-25 05:34:17', NULL, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_statuses`
--

CREATE TABLE `appointment_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_statuses`
--

INSERT INTO `appointment_statuses` (`id`, `status_name`) VALUES
(1, 'Scheduled'),
(2, 'In Queue'),
(3, 'Pending Payment'),
(4, 'Completed'),
(5, 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `balance` decimal(10,2) NOT NULL,
  `payment_status_id` int(11) DEFAULT NULL,
  `billing_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `treatment_id`, `appointment_id`, `total_amount`, `paid_amount`, `balance`, `payment_status_id`, `billing_date`, `due_date`, `notes`) VALUES
(1, 2, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-01-28', NULL, NULL),
(2, 3, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-01-19', NULL, NULL),
(3, 4, NULL, NULL, 800.00, 480.00, 320.00, 2, '2026-01-18', NULL, NULL),
(4, 6, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-01-19', NULL, NULL),
(5, 8, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-01-28', NULL, NULL),
(6, 9, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-01-14', NULL, NULL),
(7, 11, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-01-03', NULL, NULL),
(8, 17, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-01-25', NULL, NULL),
(9, 18, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-01-10', NULL, NULL),
(10, 20, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-01-18', NULL, NULL),
(11, 21, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-01-13', NULL, NULL),
(12, 22, NULL, NULL, 5000.00, 2750.00, 2250.00, 2, '2026-01-14', NULL, NULL),
(13, 24, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-01-21', NULL, NULL),
(14, 25, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-01-28', NULL, NULL),
(15, 26, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-02-14', NULL, NULL),
(16, 36, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-02-06', NULL, NULL),
(17, 37, NULL, NULL, 2000.00, 1100.00, 900.00, 2, '2026-02-09', NULL, NULL),
(18, 38, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-02-04', NULL, NULL),
(19, 41, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-02-10', NULL, NULL),
(20, 43, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-02-27', NULL, NULL),
(21, 44, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-02-05', NULL, NULL),
(22, 45, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-02-19', NULL, NULL),
(23, 46, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-02-13', NULL, NULL),
(24, 47, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-02-19', NULL, NULL),
(25, 48, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-02-01', NULL, NULL),
(26, 49, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-02-24', NULL, NULL),
(27, 51, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-02-03', NULL, NULL),
(28, 52, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-02-23', NULL, NULL),
(29, 53, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-02-16', NULL, NULL),
(30, 54, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-02-10', NULL, NULL),
(31, 56, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-03-16', NULL, NULL),
(32, 58, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-03-05', NULL, NULL),
(33, 59, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-05', NULL, NULL),
(34, 60, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-03-16', NULL, NULL),
(35, 61, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-04', NULL, NULL),
(36, 63, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-03-31', NULL, NULL),
(37, 65, NULL, NULL, 500.00, 250.00, 250.00, 2, '2026-03-16', NULL, NULL),
(38, 66, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-03-01', NULL, NULL),
(39, 67, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-12', NULL, NULL),
(40, 69, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-24', NULL, NULL),
(41, 70, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-03-18', NULL, NULL),
(42, 78, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-24', NULL, NULL),
(43, 79, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-03-20', NULL, NULL),
(44, 83, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-03-09', NULL, NULL),
(45, 84, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-03-14', NULL, NULL),
(46, 86, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-03-18', NULL, NULL),
(47, 87, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-03-16', NULL, NULL),
(48, 88, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-03-21', NULL, NULL),
(49, 89, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-03-30', NULL, NULL),
(50, 91, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-15', NULL, NULL),
(51, 92, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-04-15', NULL, NULL),
(52, 93, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-04-22', NULL, NULL),
(53, 95, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-26', NULL, NULL),
(54, 96, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-04-22', NULL, NULL),
(55, 97, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-24', NULL, NULL),
(56, 99, NULL, NULL, 800.00, 800.00, 0.00, 1, '2026-04-15', NULL, NULL),
(57, 100, NULL, NULL, 2000.00, 2000.00, 0.00, 1, '2026-04-12', NULL, NULL),
(58, 101, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-04-20', NULL, NULL),
(59, 102, NULL, NULL, 5000.00, 5000.00, 0.00, 1, '2026-04-05', NULL, NULL),
(60, 103, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-18', NULL, NULL),
(61, 107, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-14', NULL, NULL),
(62, 110, NULL, NULL, 500.00, 500.00, 0.00, 1, '2026-04-11', NULL, NULL),
(63, 116, NULL, NULL, 35000.00, 35000.00, 0.00, 1, '2026-04-10', NULL, NULL),
(64, 121, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Consultation'),
(65, 121, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Tooth Restoration (Pasta)'),
(66, 124, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Consultation'),
(67, 124, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Consultation'),
(68, 127, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Consultation'),
(69, 121, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Consultation'),
(70, 124, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Teeth Cleaning'),
(71, 123, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Teeth Cleaning'),
(72, 124, NULL, NULL, 5000.00, 0.00, 5000.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Fixed Bridge'),
(73, 123, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Teeth Cleaning'),
(74, 128, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Tooth Extraction (Ibot)'),
(75, 129, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Tooth Extraction (Ibot)'),
(76, 123, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Tooth Extraction (Ibot)'),
(77, 130, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Periapical Xray'),
(78, 132, NULL, NULL, 800.00, 0.00, 800.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Teeth Cleaning'),
(79, 123, NULL, NULL, 500.00, 0.00, 500.00, NULL, '2026-04-24', '2026-04-24', 'Initial billing from admission - Services: Denture Adjustment'),
(80, 121, NULL, NULL, 5000.00, 0.00, 5000.00, NULL, '2026-04-25', '2026-04-25', 'Initial billing from admission - Services: Teeth Whitening'),
(81, 124, NULL, NULL, 5000.00, 0.00, 5000.00, NULL, '2026-04-25', '2026-04-25', 'Initial billing from admission - Services: Removable Dentures');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_types`
--

CREATE TABLE `file_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `topic_service_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `inquiry_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_sources`
--

CREATE TABLE `inquiry_sources` (
  `id` int(11) NOT NULL,
  `source_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_statuses`
--

CREATE TABLE `inquiry_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_providers`
--

CREATE TABLE `insurance_providers` (
  `id` int(11) NOT NULL,
  `provider_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `allergies`, `current_medications`, `medical_conditions`, `blood_pressure`, `heart_rate`, `other_notes`, `updated_at`) VALUES
(1, 121, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 11:49:16'),
(6, 124, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 12:17:21'),
(8, 127, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 12:32:05'),
(11, 123, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 12:40:01'),
(14, 128, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 14:51:19'),
(15, 129, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 15:00:31'),
(17, 130, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 15:11:04'),
(18, 132, NULL, '', 'None', NULL, NULL, NULL, '2026-04-24 15:15:55');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `dosage_form` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `religion_id` int(11) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `registration_source_id` int(11) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `type_id`, `first_name`, `middle_name`, `last_name`, `gender`, `religion_id`, `address_id`, `registration_source_id`, `suffix`, `date_of_birth`, `phone`, `email`, `is_archived`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'Fernando', 'Diaz', 'Vargas', 'Female', 1, 1, NULL, NULL, '1973-12-07', '09730144360', NULL, 0, NULL, '2026-01-20 00:17:58', '2026-04-22 07:56:08'),
(2, 2, 'Carlos', 'Mayordomo', 'Santos', 'Male', 1, 2, NULL, NULL, '2003-09-10', '09145650488', NULL, 0, NULL, '2026-01-02 02:33:17', '2026-04-22 07:56:08'),
(3, 1, 'Angela', 'Abarquez', 'Aguilar', 'Female', 1, 3, NULL, NULL, '1990-10-11', '09931120544', NULL, 0, NULL, '2026-01-18 00:14:41', '2026-04-22 07:56:08'),
(4, 4, ' Bea', NULL, 'Luna', 'Male', 1, 4, NULL, NULL, '1961-12-03', '09997017766', NULL, 0, NULL, '2026-01-28 07:21:31', '2026-04-22 07:56:08'),
(5, 2, 'Jerome', NULL, 'Sanchez', 'Male', 1, 5, NULL, NULL, '1993-01-23', '09261739528', NULL, 0, NULL, '2026-01-07 09:05:01', '2026-04-22 07:56:08'),
(6, 3, 'Ramon', NULL, 'Lim', 'Male', 1, 6, NULL, NULL, '1974-02-18', '09339268112', NULL, 0, NULL, '2026-01-19 09:40:00', '2026-04-22 07:56:08'),
(7, 2, 'Gerald', NULL, 'Dizon', 'Female', 1, 7, NULL, NULL, '1967-12-25', '09481787545', NULL, 0, NULL, '2026-01-04 06:27:05', '2026-04-22 07:56:08'),
(8, 3, 'Lyra', NULL, 'Cruz', 'Male', 1, 8, NULL, NULL, '1946-12-12', '09414325953', NULL, 0, NULL, '2026-01-01 01:54:48', '2026-04-22 07:56:08'),
(9, 5, 'Kristine', 'Lapaz', 'Del Rosario', 'Female', 1, 9, NULL, NULL, '2012-09-15', '09706582189', NULL, 0, NULL, '2026-01-16 08:35:49', '2026-04-22 07:56:08'),
(10, 5, 'Patrick', NULL, 'Ramos', 'Male', 1, 10, NULL, NULL, '2001-03-10', '09146525322', NULL, 0, NULL, '2026-01-11 04:12:45', '2026-04-22 07:56:08'),
(11, 4, 'Mark', NULL, 'Cortez', 'Male', 1, 11, NULL, NULL, '2008-08-16', '09878734829', NULL, 0, NULL, '2026-01-19 01:41:26', '2026-04-22 07:56:08'),
(12, 5, 'Jenny', 'Bautista', 'Ramos', 'Female', 1, 12, NULL, NULL, '1946-05-19', '09510803620', NULL, 0, NULL, '2026-01-09 01:50:44', '2026-04-22 07:56:08'),
(13, 3, 'Rosa', NULL, 'Ocampo', 'Male', 1, 13, NULL, NULL, '1979-07-27', '09329098932', NULL, 0, NULL, '2026-01-22 01:26:50', '2026-04-22 07:56:08'),
(14, 4, 'Mhay', 'Mahinay', 'Ng', 'Male', 1, 14, NULL, NULL, '1981-02-09', '09984284348', NULL, 0, NULL, '2026-01-20 05:43:29', '2026-04-22 07:56:08'),
(15, 4, 'Rosa', NULL, 'Gomez', 'Male', 1, 15, NULL, NULL, '1984-12-23', '09866371196', NULL, 0, NULL, '2026-01-17 06:11:53', '2026-04-22 07:56:08'),
(16, 1, 'Warren', 'Carpio', 'Co', 'Male', 1, 16, NULL, NULL, '1983-12-04', '09395945825', NULL, 0, NULL, '2026-01-19 08:17:57', '2026-04-22 07:56:08'),
(17, 5, 'Willie', NULL, 'Gonzalez', 'Female', 1, 17, NULL, NULL, '1956-05-13', '09855365257', NULL, 0, NULL, '2026-01-05 09:47:06', '2026-04-22 07:56:08'),
(18, 4, 'Kevin', NULL, 'Mendoza', 'Female', 1, 18, NULL, NULL, '1982-03-07', '09334286488', NULL, 0, NULL, '2026-01-04 05:02:33', '2026-04-22 07:56:08'),
(19, 2, 'Joshua', NULL, 'Padilla', 'Female', 1, 19, NULL, NULL, '1959-06-06', '09346807453', NULL, 0, NULL, '2026-01-10 06:47:51', '2026-04-22 07:56:08'),
(20, 5, 'Francis', 'Pelaez', 'Padilla', 'Female', 1, 20, NULL, NULL, '2011-05-04', '09591409616', NULL, 0, NULL, '2026-01-02 07:18:02', '2026-04-22 07:56:08'),
(21, 1, 'Paula', NULL, 'Ramirez', 'Male', 1, 21, NULL, NULL, '1948-03-22', '09382496377', NULL, 0, NULL, '2026-01-08 06:32:42', '2026-04-22 07:56:08'),
(22, 1, 'Teresa', 'Villar', 'Flores', 'Female', 1, 22, NULL, NULL, '1988-03-28', '09132377423', NULL, 0, NULL, '2026-01-26 04:47:44', '2026-04-22 07:56:08'),
(23, 1, 'Pedro', NULL, 'Aguilar', 'Male', 1, 23, NULL, NULL, '1986-03-03', '09387265513', NULL, 0, NULL, '2026-01-12 05:12:11', '2026-04-22 07:56:08'),
(24, 3, 'Carmen', 'Ramirez', 'Reyes', 'Male', 1, 24, NULL, NULL, '1974-08-25', '09501504270', NULL, 0, NULL, '2026-01-28 04:31:22', '2026-04-22 07:56:08'),
(25, 4, 'Mercedes', 'Catiil', 'Perez', 'Female', 1, 25, NULL, NULL, '1962-01-06', '09159310990', NULL, 0, NULL, '2026-01-23 05:18:02', '2026-04-22 07:56:08'),
(26, 1, 'Antonio', 'Matilde', 'Garcia', 'Female', 1, 26, NULL, NULL, '2002-02-22', '09685261313', NULL, 0, NULL, '2026-01-05 02:45:11', '2026-04-22 07:56:08'),
(27, 3, 'Christian', 'Valencia', 'Garcia', 'Female', 1, 27, NULL, NULL, '2016-12-28', '09239893324', NULL, 0, NULL, '2026-01-19 09:04:40', '2026-04-22 07:56:08'),
(28, 5, 'Rogelio', NULL, 'Ramirez', 'Male', 1, 28, NULL, NULL, '1955-01-10', '09331303056', NULL, 0, NULL, '2026-01-08 08:22:06', '2026-04-22 07:56:08'),
(29, 1, 'Elena', NULL, 'Morales', 'Female', 1, 29, NULL, NULL, '1981-08-18', '09822641617', NULL, 0, NULL, '2026-01-08 07:18:23', '2026-04-22 07:56:08'),
(30, 4, 'Patrick', 'Trinidad', 'Gomez', 'Female', 1, 30, NULL, NULL, '1954-12-11', '09434946027', NULL, 0, NULL, '2026-01-01 03:54:45', '2026-04-22 07:56:08'),
(31, 5, 'Sheena', NULL, 'Luna', 'Male', 1, 31, NULL, NULL, '2015-09-09', '09582325789', NULL, 0, NULL, '2026-02-06 05:32:24', '2026-04-22 07:56:08'),
(32, 2, 'Jonathan', NULL, 'Morales', 'Female', 1, 32, NULL, NULL, '1981-09-11', '09282479701', NULL, 0, NULL, '2026-02-12 08:51:07', '2026-04-22 07:56:08'),
(33, 1, 'Rosa', 'Rosal', 'Mendoza', 'Male', 1, 33, NULL, NULL, '2005-04-02', '09830461453', NULL, 0, NULL, '2026-02-18 08:01:09', '2026-04-22 07:56:08'),
(34, 4, 'Kristine', NULL, 'Torres', 'Male', 1, 34, NULL, NULL, '1956-05-26', '09740356122', NULL, 0, NULL, '2026-02-14 07:14:08', '2026-04-22 07:56:08'),
(35, 2, 'Joyce', 'Matilde', 'Vargas', 'Female', 1, 35, NULL, NULL, '1954-05-11', '09424886684', NULL, 0, NULL, '2026-02-28 00:06:06', '2026-04-22 07:56:08'),
(36, 1, 'Darwin', 'Dela Vega', 'Vargas', 'Male', 1, 36, NULL, NULL, '2014-12-24', '09531125088', NULL, 0, NULL, '2026-02-08 03:43:13', '2026-04-22 07:56:08'),
(37, 5, 'Cyril', NULL, 'Padilla', 'Male', 1, 37, NULL, NULL, '1985-09-10', '09595871241', NULL, 0, NULL, '2026-02-20 02:10:32', '2026-04-22 07:56:08'),
(38, 4, 'Mercedes', NULL, 'Ong', 'Male', 1, 38, NULL, NULL, '1984-04-22', '09468689018', NULL, 0, NULL, '2026-02-23 02:12:14', '2026-04-22 07:56:08'),
(39, 1, 'Catherine', 'Ramirez', 'Tomas', 'Female', 1, 39, NULL, NULL, '2014-11-26', '09669733887', NULL, 0, NULL, '2026-02-17 09:43:47', '2026-04-22 07:56:08'),
(40, 1, 'Ana', NULL, 'Ng', 'Female', 1, 40, NULL, NULL, '2012-06-19', '09750171791', NULL, 0, NULL, '2026-02-13 06:08:19', '2026-04-22 07:56:08'),
(41, 5, 'Jasmine', NULL, 'Torres', 'Female', 1, 41, NULL, NULL, '1997-07-01', '09466803949', NULL, 0, NULL, '2026-02-06 02:25:54', '2026-04-22 07:56:08'),
(42, 5, 'Precious', NULL, 'Garcia', 'Male', 1, 42, NULL, NULL, '2016-08-23', '09644078066', NULL, 0, NULL, '2026-02-15 06:51:10', '2026-04-22 07:56:08'),
(43, 2, 'Nicole', NULL, 'Molina', 'Male', 1, 43, NULL, NULL, '1947-10-26', '09654588212', NULL, 0, NULL, '2026-02-21 08:11:00', '2026-04-22 07:56:08'),
(44, 2, 'Kaye', NULL, 'Dizon', 'Female', 1, 44, NULL, NULL, '1981-05-13', '09183263975', NULL, 0, NULL, '2026-02-01 01:27:14', '2026-04-22 07:56:08'),
(45, 4, 'Philip', 'Dimayuga', 'Sanchez', 'Male', 1, 45, NULL, NULL, '1966-06-27', '09981398090', NULL, 0, NULL, '2026-02-01 03:44:48', '2026-04-22 07:56:08'),
(46, 1, 'Maria', NULL, 'Ng', 'Female', 1, 46, NULL, NULL, '1987-09-08', '09876232688', NULL, 0, NULL, '2026-02-12 07:03:06', '2026-04-22 07:56:08'),
(47, 4, 'Jerome', NULL, 'Fernandez', 'Female', 1, 47, NULL, NULL, '1966-10-28', '09459224668', NULL, 0, NULL, '2026-02-14 07:11:53', '2026-04-22 07:56:08'),
(48, 1, 'Ricky', NULL, 'Go', 'Female', 1, 48, NULL, NULL, '1997-04-11', '09493405341', NULL, 0, NULL, '2026-02-24 02:52:29', '2026-04-22 07:56:08'),
(49, 3, ' Bea', 'Castro', 'Sy', 'Male', 1, 49, NULL, NULL, '1953-07-01', '09356984533', NULL, 0, NULL, '2026-02-19 09:13:02', '2026-04-22 07:56:08'),
(50, 3, 'Patrick', NULL, 'Aquino', 'Female', 1, 50, NULL, NULL, '1984-04-02', '09773939787', NULL, 0, NULL, '2026-02-24 02:09:36', '2026-04-22 07:56:08'),
(51, 5, 'Ramon', NULL, 'Molina', 'Male', 1, 51, NULL, NULL, '2015-04-21', '09308752294', NULL, 0, NULL, '2026-02-05 03:25:34', '2026-04-22 07:56:08'),
(52, 2, 'Catherine', NULL, 'Molina', 'Male', 1, 52, NULL, NULL, '1990-12-23', '09853201130', NULL, 0, NULL, '2026-02-27 04:22:50', '2026-04-22 07:56:08'),
(53, 1, 'Lester', NULL, 'Sanchez', 'Female', 1, 53, NULL, NULL, '1983-01-09', '09631430305', NULL, 0, NULL, '2026-02-05 00:48:32', '2026-04-22 07:56:08'),
(54, 1, 'Joan', NULL, 'Guevarra', 'Female', 1, 54, NULL, NULL, '2005-07-12', '09825304725', NULL, 0, NULL, '2026-02-26 04:41:37', '2026-04-22 07:56:08'),
(55, 4, 'Diana', NULL, 'Torres', 'Male', 1, 55, NULL, NULL, '1993-03-13', '09164367940', NULL, 0, NULL, '2026-02-27 09:15:00', '2026-04-22 07:56:08'),
(56, 4, 'Mhay', NULL, 'Ng', 'Female', 1, 56, NULL, NULL, '1980-02-07', '09562523151', NULL, 0, NULL, '2026-02-22 07:20:01', '2026-04-22 07:56:08'),
(57, 4, 'Jannah', NULL, 'Diaz', 'Male', 1, 57, NULL, NULL, '2015-05-23', '09274685958', NULL, 0, NULL, '2026-02-27 01:24:09', '2026-04-22 07:56:08'),
(58, 5, 'Albert', NULL, 'Perez', 'Female', 1, 58, NULL, NULL, '2003-12-11', '09823750115', NULL, 0, NULL, '2026-02-18 01:10:56', '2026-04-22 07:56:08'),
(59, 2, 'John', NULL, 'Luna', 'Male', 1, 59, NULL, NULL, '2003-03-26', '09215103631', NULL, 0, NULL, '2026-02-11 06:54:55', '2026-04-22 07:56:08'),
(60, 5, 'Albert', NULL, 'Co', 'Female', 1, 60, NULL, NULL, '1955-07-15', '09478082801', NULL, 0, NULL, '2026-02-06 03:21:20', '2026-04-22 07:56:08'),
(61, 4, 'Gerald', NULL, 'Ong', 'Female', 1, 61, NULL, NULL, '2011-08-10', '09440921042', NULL, 0, NULL, '2026-03-26 00:13:49', '2026-04-22 07:56:08'),
(62, 5, 'Lester', NULL, 'Fernandez', 'Male', 1, 62, NULL, NULL, '1968-02-22', '09268451326', NULL, 0, NULL, '2026-03-28 08:13:39', '2026-04-22 07:56:08'),
(63, 3, 'Michael', NULL, 'Vargas', 'Male', 1, 63, NULL, NULL, '2018-07-27', '09891444578', NULL, 0, NULL, '2026-03-12 06:52:44', '2026-04-22 07:56:08'),
(64, 5, 'Ricardo', NULL, 'Morales', 'Female', 1, 64, NULL, NULL, '1962-01-24', '09183721183', NULL, 0, NULL, '2026-03-25 00:39:21', '2026-04-22 07:56:08'),
(65, 5, 'Bryan', NULL, 'Cruz', 'Female', 1, 65, NULL, NULL, '1996-03-02', '09423535535', NULL, 0, NULL, '2026-03-05 04:07:55', '2026-04-22 07:56:08'),
(66, 1, 'Roberto', NULL, 'Chua', 'Male', 1, 66, NULL, NULL, '1990-04-12', '09638533297', NULL, 0, NULL, '2026-03-28 06:27:20', '2026-04-22 07:56:08'),
(67, 3, 'Jonathan', NULL, 'Ramirez', 'Male', 1, 67, NULL, NULL, '2013-10-19', '09251868625', NULL, 0, NULL, '2026-03-02 07:56:38', '2026-04-22 07:56:08'),
(68, 5, 'Patrick', NULL, 'Perez', 'Female', 1, 68, NULL, NULL, '1949-12-28', '09252379176', NULL, 0, NULL, '2026-03-02 00:18:11', '2026-04-22 07:56:08'),
(69, 2, 'Joyce', NULL, 'Aguilar', 'Female', 1, 69, NULL, NULL, '1970-01-14', '09639660640', NULL, 0, NULL, '2026-03-31 09:52:05', '2026-04-22 07:56:08'),
(70, 5, 'Kevin', NULL, 'Luna', 'Female', 1, 70, NULL, NULL, '1947-07-02', '09513925471', NULL, 0, NULL, '2026-03-29 09:44:28', '2026-04-22 07:56:08'),
(71, 4, 'Gerald', NULL, 'Tan', 'Male', 1, 71, NULL, NULL, '2021-02-18', '09263477973', NULL, 0, NULL, '2026-03-09 08:36:07', '2026-04-22 07:56:08'),
(72, 4, 'Cyril', NULL, 'Rodriguez', 'Female', 1, 72, NULL, NULL, '1966-01-28', '09941812404', NULL, 0, NULL, '2026-03-01 09:53:26', '2026-04-22 07:56:08'),
(73, 4, 'Patricia', NULL, 'Ramirez', 'Male', 1, 73, NULL, NULL, '1990-06-13', '09823216328', NULL, 0, NULL, '2026-03-02 03:57:39', '2026-04-22 07:56:08'),
(74, 4, 'Megan', NULL, 'Padilla', 'Female', 1, 74, NULL, NULL, '1993-08-07', '09978296819', NULL, 0, NULL, '2026-03-10 07:33:06', '2026-04-22 07:56:08'),
(75, 5, ' Bea', NULL, 'Cortez', 'Female', 1, 75, NULL, NULL, '1998-02-09', '09427490687', NULL, 0, NULL, '2026-03-31 05:11:12', '2026-04-22 07:56:08'),
(76, 4, 'Rachelle', NULL, 'Co', 'Female', 1, 76, NULL, NULL, '2014-12-05', '09429003986', NULL, 0, NULL, '2026-03-16 03:09:17', '2026-04-22 07:56:08'),
(77, 2, 'Willie', NULL, 'Morales', 'Female', 1, 77, NULL, NULL, '1967-06-19', '09206327393', NULL, 0, NULL, '2026-03-08 07:05:18', '2026-04-22 07:56:08'),
(78, 3, 'Jenny', NULL, 'Santos', 'Male', 1, 78, NULL, NULL, '1974-08-14', '09152423299', NULL, 0, NULL, '2026-03-07 07:01:06', '2026-04-22 07:56:08'),
(79, 3, 'Arnold', NULL, 'Yu', 'Male', 1, 79, NULL, NULL, '1980-04-09', '09433009500', NULL, 0, NULL, '2026-03-05 01:34:37', '2026-04-22 07:56:08'),
(80, 3, 'Albert', NULL, 'Perez', 'Male', 1, 80, NULL, NULL, '1946-12-06', '09764554626', NULL, 0, NULL, '2026-03-03 03:08:36', '2026-04-22 07:56:08'),
(81, 5, 'Daniel', NULL, 'Rivera', 'Female', 1, 81, NULL, NULL, '1970-08-24', '09105597018', NULL, 0, NULL, '2026-03-12 04:24:49', '2026-04-22 07:56:08'),
(82, 3, 'Christian', NULL, 'Sanchez', 'Female', 1, 82, NULL, NULL, '1948-02-17', '09421411345', NULL, 0, NULL, '2026-03-08 02:05:01', '2026-04-22 07:56:08'),
(83, 5, 'Neil', NULL, 'Rivera', 'Female', 1, 83, NULL, NULL, '2004-01-11', '09472729703', NULL, 0, NULL, '2026-03-15 04:02:06', '2026-04-22 07:56:08'),
(84, 3, 'Ellen', NULL, 'Castro', 'Female', 1, 84, NULL, NULL, '1956-06-26', '09807444960', NULL, 0, NULL, '2026-03-23 07:28:23', '2026-04-22 07:56:08'),
(85, 3, 'Eden', NULL, 'Uy', 'Female', 1, 85, NULL, NULL, '1968-08-08', '09814583428', NULL, 0, NULL, '2026-03-14 04:44:31', '2026-04-22 07:56:08'),
(86, 1, 'Jonathan', NULL, 'Ramirez', 'Female', 1, 86, NULL, NULL, '1985-07-24', '09717933717', NULL, 0, NULL, '2026-03-30 07:31:43', '2026-04-22 07:56:08'),
(87, 2, 'Kristine', NULL, 'Co', 'Female', 1, 87, NULL, NULL, '1973-04-13', '09988024981', NULL, 0, NULL, '2026-03-03 09:28:57', '2026-04-22 07:56:08'),
(88, 2, 'Rachelle', NULL, 'Lopez', 'Female', 1, 88, NULL, NULL, '2011-12-06', '09895643495', NULL, 0, NULL, '2026-03-12 00:33:47', '2026-04-22 07:56:09'),
(89, 2, 'Ron', NULL, 'Ramos', 'Male', 1, 89, NULL, NULL, '1962-07-07', '09720074652', NULL, 0, NULL, '2026-03-30 01:20:31', '2026-04-22 07:56:09'),
(90, 4, 'Ricky', NULL, 'Santos', 'Male', 1, 90, NULL, NULL, '1979-05-21', '09712890676', NULL, 0, NULL, '2026-03-05 04:51:36', '2026-04-22 07:56:09'),
(91, 3, 'Teresa', NULL, 'Co', 'Female', 1, 91, NULL, NULL, '2005-09-01', '09785971791', NULL, 0, NULL, '2026-04-05 04:06:35', '2026-04-22 07:56:09'),
(92, 3, 'Rachelle', NULL, 'Go', 'Female', 1, 92, NULL, NULL, '2000-02-22', '09182223142', NULL, 0, NULL, '2026-04-11 04:17:30', '2026-04-22 07:56:09'),
(93, 1, 'Juan', NULL, 'Dizon', 'Female', 1, 93, NULL, NULL, '1963-06-21', '09889280403', NULL, 0, NULL, '2026-04-04 08:18:39', '2026-04-22 07:56:09'),
(94, 4, 'Jerome', NULL, 'Mendoza', 'Male', 1, 94, NULL, NULL, '1972-01-04', '09300302707', NULL, 0, NULL, '2026-04-08 02:36:53', '2026-04-22 07:56:09'),
(95, 5, 'Marlon', NULL, 'Lim', 'Male', 1, 95, NULL, NULL, '1991-02-05', '09209168437', NULL, 0, NULL, '2026-04-11 06:46:02', '2026-04-22 07:56:09'),
(96, 1, 'Nora', NULL, 'Go', 'Male', 1, 96, NULL, NULL, '2012-11-16', '09315430001', NULL, 0, NULL, '2026-04-22 08:40:13', '2026-04-22 07:56:09'),
(97, 2, 'Carlos', NULL, 'Fernandez', 'Female', 1, 97, NULL, NULL, '1964-12-11', '09172017501', NULL, 0, NULL, '2026-04-15 04:33:12', '2026-04-22 07:56:09'),
(98, 3, 'Ricky', NULL, 'Vargas', 'Male', 1, 98, NULL, NULL, '1961-04-10', '09985151383', NULL, 0, NULL, '2026-04-22 06:17:54', '2026-04-22 07:56:09'),
(99, 3, 'Lloyd John', 'Abarquez', 'Aguilar', 'Female', 1, 99, NULL, NULL, '1963-10-01', '09223248214', NULL, 0, NULL, '2026-04-21 03:59:35', '2026-04-22 07:56:09'),
(100, 4, 'Kaye', NULL, 'Navarro', 'Male', 1, 100, NULL, NULL, '2009-11-19', '09768510068', NULL, 0, NULL, '2026-04-07 00:21:38', '2026-04-22 07:56:09'),
(101, 4, 'Lloyd', 'Ramirez', 'Torres', 'Male', 1, 101, NULL, NULL, '1972-04-11', '09308238988', NULL, 0, NULL, '2026-04-12 00:13:56', '2026-04-22 07:56:09'),
(102, 5, 'Rogelio', 'Castillo', 'Castro', 'Female', 1, 102, NULL, NULL, '2020-09-26', '09396110460', NULL, 0, NULL, '2026-04-07 06:05:28', '2026-04-22 07:56:09'),
(103, 2, 'Jonathan', 'Maligmat', 'Cortez', 'Male', 1, 103, NULL, NULL, '1979-09-22', '09362758416', NULL, 0, NULL, '2026-04-06 08:57:24', '2026-04-22 07:56:09'),
(104, 5, 'Lester John', 'Dela Cruz', 'Sy', 'Female', 1, 104, NULL, NULL, '1986-04-23', '09671821417', NULL, 0, NULL, '2026-04-14 02:41:46', '2026-04-22 07:56:09'),
(105, 5, 'Kathryn', 'Raagas', 'Sy', 'Female', 1, 105, NULL, NULL, '1992-05-28', '09972363645', NULL, 0, NULL, '2026-04-05 05:50:01', '2026-04-22 07:56:09'),
(106, 4, 'Shaira', 'Diaz', 'Rivera', 'Male', 1, 106, NULL, NULL, '1967-06-23', '09658333147', NULL, 0, NULL, '2026-04-20 04:42:01', '2026-04-22 07:56:09'),
(107, 2, 'Maria', NULL, 'Ocampo', 'Female', 1, 107, NULL, NULL, '1985-11-20', '09847848808', NULL, 0, NULL, '2026-04-02 09:12:39', '2026-04-22 07:56:09'),
(108, 3, 'Jasmine', 'Bulan', 'Santos', 'Female', 1, 108, NULL, NULL, '1982-10-04', '09708262052', NULL, 0, NULL, '2026-04-05 00:17:39', '2026-04-22 07:56:09'),
(109, 5, 'Catherine', 'Pagaspas', 'Go', 'Female', 1, 109, NULL, NULL, '1992-07-23', '09886170715', NULL, 0, NULL, '2026-04-04 07:40:12', '2026-04-22 07:56:09'),
(110, 3, 'Joshua', NULL, 'Go', 'Male', 1, 110, NULL, NULL, '1980-08-24', '09920360349', NULL, 0, NULL, '2026-04-11 00:09:03', '2026-04-22 07:56:09'),
(111, 4, 'Joshua', 'Quirante', 'Ocampo', 'Male', 1, 111, NULL, NULL, '1981-08-21', '09183849944', NULL, 0, NULL, '2026-04-11 02:33:18', '2026-04-22 07:56:09'),
(112, 5, 'Sharon', NULL, 'Santos', 'Male', 1, 112, NULL, NULL, '2007-10-10', '09292266809', NULL, 0, NULL, '2026-04-14 06:20:43', '2026-04-22 07:56:09'),
(113, 1, 'Ron', 'Malazarte', 'Chua', 'Female', 1, 113, NULL, NULL, '1992-02-16', '09549123152', NULL, 0, NULL, '2026-04-07 01:05:19', '2026-04-22 07:56:09'),
(114, 1, 'Kaye', 'Dimayuga', 'Ocampo', 'Male', 1, 114, NULL, NULL, '1947-03-03', '09726311680', NULL, 1, '2026-04-22 07:32:22', '2026-04-22 06:47:00', '2026-04-22 07:56:09'),
(115, 3, 'Ron', 'Malayugan', 'Basilio', 'Female', 1, 115, NULL, NULL, '2017-07-22', '09703706501', NULL, 1, '2026-04-22 07:32:28', '2026-04-18 06:11:53', '2026-04-22 07:56:09'),
(116, 4, 'Nicole', 'Napolino', 'Ocampo', 'Female', 1, 116, NULL, NULL, '1977-09-02', '09369406846', NULL, 0, NULL, '2026-04-09 07:41:50', '2026-04-22 07:56:09'),
(117, 1, 'Bobby Lee', 'Quanco', 'Tan', 'Male', 1, 117, NULL, NULL, '1950-10-10', '09633019768', NULL, 0, NULL, '2026-04-22 06:01:45', '2026-04-22 07:56:09'),
(118, 2, 'Carmen', 'Suarez', 'Guevarra', 'Female', 1, 118, NULL, NULL, '1956-04-15', '09979454128', NULL, 0, NULL, '2026-04-17 01:36:26', '2026-04-22 07:56:09'),
(119, NULL, 'Boss', 'Lorenzo', 'Zata', 'Male', NULL, NULL, NULL, NULL, '2000-02-04', '09241534656', 'bosszata20@gmail.com', 0, NULL, '2026-04-22 11:31:51', '2026-04-22 11:31:51'),
(120, NULL, 'Trisha Mael', 'Macapagal', 'Albuela', 'Female', NULL, NULL, NULL, NULL, '2002-02-04', '09271544652', 'trishaaame43@gmail.com', 0, NULL, '2026-04-23 02:08:47', '2026-04-23 02:08:47'),
(121, NULL, 'Jery', 'Mariam', 'Maglungsod', 'Male', NULL, NULL, NULL, '', '2000-02-22', '09654646466', '', 0, NULL, '2026-04-23 02:15:20', '2026-04-25 02:32:13'),
(122, NULL, 'Nathaniel', 'Villar', 'Quirante', 'Female', NULL, NULL, NULL, NULL, '2000-02-03', '09267913056', 'nathaniel@gmail.com', 0, NULL, '2026-04-23 16:35:37', '2026-04-23 16:35:37'),
(123, NULL, 'James Kyle', 'we', 'Paraiso', 'Male', NULL, NULL, NULL, '', '2000-02-22', '01215646546', '', 0, NULL, '2026-04-24 04:49:19', '2026-04-24 15:17:05'),
(124, NULL, 'Nathaniel', 'watada', 'Dgg', 'Male', NULL, NULL, NULL, '', '2000-02-21', '09254568894', '', 0, NULL, '2026-04-24 07:56:57', '2026-04-25 02:35:42'),
(127, NULL, 'Trisha Maelaa', 'Macapagal', 'Albuelaa', 'Female', NULL, NULL, NULL, '', '2002-04-21', '09271544652', '', 0, NULL, '2026-04-24 12:32:05', '2026-04-24 12:32:05'),
(128, NULL, 'Boss bi', 'Lorenzo ewwe', 'Diaz', 'Male', NULL, NULL, NULL, '', '2003-08-26', '09241534656', '', 0, NULL, '2026-04-24 14:51:19', '2026-04-24 14:51:19'),
(129, NULL, 'Trisha Maee', 'Macapagal', 'Albuelat', 'Female', NULL, NULL, NULL, '', '2001-08-01', '09271544652', '', 0, NULL, '2026-04-24 15:00:31', '2026-04-24 15:00:31'),
(130, NULL, 'Malita', 'Lorenzo', 'Zata', 'Male', NULL, NULL, NULL, '', '2000-03-04', '09241534656', '', 0, NULL, '2026-04-24 15:11:04', '2026-04-24 15:11:04'),
(131, NULL, 'Test', NULL, 'Patient1777043631', NULL, NULL, NULL, NULL, NULL, NULL, '09996691156', NULL, 0, NULL, '2026-04-24 15:13:51', '2026-04-24 15:13:51'),
(132, NULL, 'kurt jayew', 'trinidad', 'maligmate', 'Male', NULL, NULL, NULL, '', '2009-04-01', '09441534654', '', 0, NULL, '2026-04-24 15:15:55', '2026-04-24 15:15:55'),
(133, NULL, 'James Ryan', 'Kilang', 'Dela Cruz', 'Male', NULL, NULL, NULL, NULL, NULL, '09214546845', 'kristopermbl23@gmail.com', 0, NULL, '2026-04-25 05:12:37', '2026-04-25 05:12:37');

-- --------------------------------------------------------

--
-- Table structure for table `patient_files`
--

CREATE TABLE `patient_files` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_type_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE `patient_insurance` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `policy_number` varchar(100) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_types`
--

CREATE TABLE `patient_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_types`
--

INSERT INTO `patient_types` (`id`, `type_name`) VALUES
(1, 'New Patient'),
(2, 'Returning Patient'),
(3, 'Senior Citizen'),
(4, 'PWD'),
(5, 'Employee'),
(6, 'New Patient'),
(7, 'Returning Patient'),
(8, 'Senior Citizen'),
(9, 'PWD'),
(10, 'Employee'),
(11, 'New Patient'),
(12, 'Returning Patient'),
(13, 'Senior Citizen'),
(14, 'PWD'),
(15, 'Employee');

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
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `billing_id`, `patient_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `created_by`, `notes`, `created_at`) VALUES
(1, 1, 2, 500.00, 'Cash', '2026-01-28', 'REF832833', NULL, NULL, '2026-04-22 10:35:44'),
(2, 2, 3, 5000.00, 'Cash', '2026-01-19', 'REF217449', NULL, NULL, '2026-04-22 10:35:44'),
(3, 3, 4, 480.00, 'Cash', '2026-01-18', 'REF423288', NULL, NULL, '2026-04-22 10:35:44'),
(4, 4, 6, 2000.00, 'Cash', '2026-01-19', 'REF851084', NULL, NULL, '2026-04-22 10:35:44'),
(5, 5, 8, 500.00, 'Cash', '2026-01-28', 'REF299066', NULL, NULL, '2026-04-22 10:35:44'),
(6, 6, 9, 500.00, 'Cash', '2026-01-14', 'REF368057', NULL, NULL, '2026-04-22 10:35:44'),
(7, 7, 11, 5000.00, 'Cash', '2026-01-03', 'REF529950', NULL, NULL, '2026-04-22 10:35:44'),
(8, 8, 17, 35000.00, 'Cash', '2026-01-25', 'REF833673', NULL, NULL, '2026-04-22 10:35:44'),
(9, 9, 18, 800.00, 'Cash', '2026-01-10', 'REF442339', NULL, NULL, '2026-04-22 10:35:44'),
(10, 10, 20, 800.00, 'Cash', '2026-01-18', 'REF237890', NULL, NULL, '2026-04-22 10:35:44'),
(11, 11, 21, 5000.00, 'Cash', '2026-01-13', 'REF611673', NULL, NULL, '2026-04-22 10:35:44'),
(12, 12, 22, 2750.00, 'Cash', '2026-01-14', 'REF906829', NULL, NULL, '2026-04-22 10:35:44'),
(13, 13, 24, 5000.00, 'Cash', '2026-01-21', 'REF459456', NULL, NULL, '2026-04-22 10:35:44'),
(14, 14, 25, 500.00, 'Cash', '2026-01-28', 'REF886771', NULL, NULL, '2026-04-22 10:35:44'),
(15, 15, 26, 500.00, 'Cash', '2026-02-14', 'REF434659', NULL, NULL, '2026-04-22 10:35:44'),
(16, 16, 36, 800.00, 'Cash', '2026-02-06', 'REF285139', NULL, NULL, '2026-04-22 10:35:44'),
(17, 17, 37, 1100.00, 'Cash', '2026-02-09', 'REF788485', NULL, NULL, '2026-04-22 10:35:44'),
(18, 18, 38, 2000.00, 'Cash', '2026-02-04', 'REF284376', NULL, NULL, '2026-04-22 10:35:44'),
(19, 19, 41, 800.00, 'Cash', '2026-02-10', 'REF311980', NULL, NULL, '2026-04-22 10:35:44'),
(20, 20, 43, 5000.00, 'Cash', '2026-02-27', 'REF863864', NULL, NULL, '2026-04-22 10:35:44'),
(21, 21, 44, 500.00, 'Cash', '2026-02-05', 'REF174616', NULL, NULL, '2026-04-22 10:35:44'),
(22, 22, 45, 5000.00, 'Cash', '2026-02-19', 'REF747537', NULL, NULL, '2026-04-22 10:35:44'),
(23, 23, 46, 800.00, 'Cash', '2026-02-13', 'REF692035', NULL, NULL, '2026-04-22 10:35:44'),
(24, 24, 47, 5000.00, 'Cash', '2026-02-19', 'REF848618', NULL, NULL, '2026-04-22 10:35:44'),
(25, 25, 48, 500.00, 'Cash', '2026-02-01', 'REF114846', NULL, NULL, '2026-04-22 10:35:44'),
(26, 26, 49, 35000.00, 'Cash', '2026-02-24', 'REF725104', NULL, NULL, '2026-04-22 10:35:44'),
(27, 27, 51, 2000.00, 'Cash', '2026-02-03', 'REF475398', NULL, NULL, '2026-04-22 10:35:44'),
(28, 28, 52, 800.00, 'Cash', '2026-02-23', 'REF858250', NULL, NULL, '2026-04-22 10:35:44'),
(29, 29, 53, 800.00, 'Cash', '2026-02-16', 'REF438307', NULL, NULL, '2026-04-22 10:35:44'),
(30, 30, 54, 500.00, 'Cash', '2026-02-10', 'REF525451', NULL, NULL, '2026-04-22 10:35:44'),
(31, 31, 56, 800.00, 'Cash', '2026-03-16', 'REF784698', NULL, NULL, '2026-04-22 10:35:44'),
(32, 32, 58, 35000.00, 'Cash', '2026-03-05', 'REF645793', NULL, NULL, '2026-04-22 10:35:44'),
(33, 33, 59, 5000.00, 'Cash', '2026-03-05', 'REF864904', NULL, NULL, '2026-04-22 10:35:44'),
(34, 34, 60, 2000.00, 'Cash', '2026-03-16', 'REF534368', NULL, NULL, '2026-04-22 10:35:44'),
(35, 35, 61, 5000.00, 'Cash', '2026-03-04', 'REF626980', NULL, NULL, '2026-04-22 10:35:44'),
(36, 36, 63, 800.00, 'Cash', '2026-03-31', 'REF660974', NULL, NULL, '2026-04-22 10:35:44'),
(37, 37, 65, 250.00, 'Cash', '2026-03-16', 'REF977290', NULL, NULL, '2026-04-22 10:35:44'),
(38, 38, 66, 500.00, 'Cash', '2026-03-01', 'REF205457', NULL, NULL, '2026-04-22 10:35:44'),
(39, 39, 67, 5000.00, 'Cash', '2026-03-12', 'REF934119', NULL, NULL, '2026-04-22 10:35:44'),
(40, 40, 69, 5000.00, 'Cash', '2026-03-24', 'REF464898', NULL, NULL, '2026-04-22 10:35:44'),
(41, 41, 70, 2000.00, 'Cash', '2026-03-18', 'REF944220', NULL, NULL, '2026-04-22 10:35:44'),
(42, 42, 78, 5000.00, 'Cash', '2026-03-24', 'REF318524', NULL, NULL, '2026-04-22 10:35:44'),
(43, 43, 79, 800.00, 'Cash', '2026-03-20', 'REF990487', NULL, NULL, '2026-04-22 10:35:44'),
(44, 44, 83, 35000.00, 'Cash', '2026-03-09', 'REF802486', NULL, NULL, '2026-04-22 10:35:44'),
(45, 45, 84, 800.00, 'Cash', '2026-03-14', 'REF788411', NULL, NULL, '2026-04-22 10:35:44'),
(46, 46, 86, 2000.00, 'Cash', '2026-03-18', 'REF477773', NULL, NULL, '2026-04-22 10:35:44'),
(47, 47, 87, 5000.00, 'Cash', '2026-03-16', 'REF513637', NULL, NULL, '2026-04-22 10:35:44'),
(48, 48, 88, 500.00, 'Cash', '2026-03-21', 'REF713678', NULL, NULL, '2026-04-22 10:35:44'),
(49, 49, 89, 35000.00, 'Cash', '2026-03-30', 'REF269440', NULL, NULL, '2026-04-22 10:35:44'),
(50, 50, 91, 500.00, 'Cash', '2026-04-15', 'REF256422', NULL, NULL, '2026-04-22 10:35:44'),
(51, 51, 92, 800.00, 'Cash', '2026-04-15', 'REF135101', NULL, NULL, '2026-04-22 10:35:44'),
(52, 52, 93, 5000.00, 'Cash', '2026-04-22', 'REF820884', NULL, NULL, '2026-04-22 10:35:44'),
(53, 53, 95, 500.00, 'Cash', '2026-04-26', 'REF638590', NULL, NULL, '2026-04-22 10:35:44'),
(54, 54, 96, 5000.00, 'Cash', '2026-04-22', 'REF423729', NULL, NULL, '2026-04-22 10:35:44'),
(55, 55, 97, 500.00, 'Cash', '2026-04-24', 'REF146810', NULL, NULL, '2026-04-22 10:35:44'),
(56, 56, 99, 800.00, 'Cash', '2026-04-15', 'REF720852', NULL, NULL, '2026-04-22 10:35:44'),
(57, 57, 100, 2000.00, 'Cash', '2026-04-12', 'REF600834', NULL, NULL, '2026-04-22 10:35:44'),
(58, 58, 101, 5000.00, 'Cash', '2026-04-20', 'REF311673', NULL, NULL, '2026-04-22 10:35:44'),
(59, 59, 102, 5000.00, 'Cash', '2026-04-05', 'REF375423', NULL, NULL, '2026-04-22 10:35:44'),
(60, 60, 103, 500.00, 'Cash', '2026-04-18', 'REF831524', NULL, NULL, '2026-04-22 10:35:44'),
(61, 61, 107, 500.00, 'Cash', '2026-04-14', 'REF754125', NULL, NULL, '2026-04-22 10:35:44'),
(62, 62, 110, 500.00, 'Cash', '2026-04-11', 'REF431256', NULL, NULL, '2026-04-22 10:35:44'),
(63, 63, 116, 35000.00, 'Cash', '2026-04-10', 'REF640732', NULL, NULL, '2026-04-22 10:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `payment_statuses`
--

CREATE TABLE `payment_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_statuses`
--

INSERT INTO `payment_statuses` (`id`, `status_name`) VALUES
(1, 'Paid'),
(2, 'Partial'),
(3, 'Unpaid'),
(4, 'Overdue'),
(5, 'Paid'),
(6, 'Partial'),
(7, 'Unpaid'),
(8, 'Overdue');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `issue_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `status_id` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT 5,
  `treatment_type` varchar(100) DEFAULT NULL,
  `teeth_numbers` varchar(50) DEFAULT NULL,
  `procedure_notes` text DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `queue_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue`
--

INSERT INTO `queue` (`id`, `patient_id`, `status_id`, `priority`, `treatment_type`, `teeth_numbers`, `procedure_notes`, `is_processed`, `completed_at`, `queue_time`, `notes`, `is_archived`, `created_at`) VALUES
(3, 129, 1, 5, 'General Checkup', NULL, NULL, 0, NULL, '23:04:31', NULL, 0, '2026-04-24 15:04:31'),
(5, 100, 1, 5, 'General Checkup', NULL, NULL, 0, NULL, '23:34:31', NULL, 0, '2026-04-24 15:13:27'),
(7, 132, 1, 5, 'Teeth Cleaning', NULL, NULL, 0, NULL, '23:15:55', NULL, 0, '2026-04-24 15:15:55'),
(8, 123, 1, 5, 'Denture Adjustment', NULL, NULL, 0, NULL, '23:17:05', NULL, 0, '2026-04-24 15:17:05'),
(9, 121, 1, 5, 'Teeth Whitening', NULL, NULL, 0, NULL, '10:32:13', NULL, 0, '2026-04-25 02:32:13'),
(10, 124, 1, 5, 'Removable Dentures', NULL, NULL, 0, NULL, '10:35:42', NULL, 0, '2026-04-25 02:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `queue_items`
--

CREATE TABLE `queue_items` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `procedure_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_statuses`
--

CREATE TABLE `queue_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue_statuses`
--

INSERT INTO `queue_statuses` (`id`, `status_name`) VALUES
(1, 'waiting'),
(2, 'in_procedure'),
(3, 'completed'),
(4, 'cancelled')
(5, 'on_hold');

-- --------------------------------------------------------

--
-- Table structure for table `religions`
--

CREATE TABLE `religions` (
  `id` int(11) NOT NULL,
  `religion_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `religions`
--

INSERT INTO `religions` (`id`, `religion_name`) VALUES
(1, 'Roman Catholic'),
(2, 'Christian'),
(3, 'Islam'),
(4, 'Iglesia ni Cristo'),
(5, 'Buddhist'),
(6, 'Atheist'),
(7, 'Roman Catholic'),
(8, 'Christian'),
(9, 'Islam'),
(10, 'Iglesia ni Cristo'),
(11, 'Buddhist'),
(12, 'Atheist'),
(13, 'Roman Catholic'),
(14, 'Christian'),
(15, 'Islam'),
(16, 'Iglesia ni Cristo'),
(17, 'Buddhist'),
(18, 'Atheist'),
(19, 'Roman Catholic'),
(20, 'Christian'),
(21, 'Islam'),
(22, 'Iglesia ni Cristo'),
(23, 'Buddhist'),
(24, 'Atheist');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Dentist'),
(3, 'Staff'),
(4, 'Patient');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `available_day` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `mode` enum('BULK','SINGLE','NONE') DEFAULT 'SINGLE',
  `price` decimal(10,2) DEFAULT 0.00,
  `duration_minutes` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `mode`, `price`, `duration_minutes`, `description`, `is_active`, `is_archived`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Consultation', 'NONE', 500.00, 30, 'Professional oral health assessment and planning.', 1, 0, '2026-04-22 06:46:29', '2026-04-22 07:23:36', NULL),
(2, 'Crowns (Jacket)', 'SINGLE', 2000.00, 90, 'Protective cap to restore tooth shape and strength.', 1, 0, '2026-04-22 06:46:29', '2026-04-22 06:46:29', NULL),
(3, 'Denture Adjustment', 'SINGLE', 500.00, 30, 'Reshaping of dentures to improve fit and comfort.', 1, 0, '2026-04-22 06:46:29', '2026-04-22 06:46:29', NULL),
(4, 'Fixed Bridge', 'SINGLE', 5000.00, 90, 'Permanent replacement for missing teeth anchored to adjacent teeth.', 1, 0, '2026-04-22 06:46:30', '2026-04-22 06:46:30', NULL),
(5, 'Orthodontic Appliance', 'BULK', 35000.00, 120, 'Devices like braces or retainers to align teeth.', 1, 0, '2026-04-22 06:46:30', '2026-04-22 07:24:04', NULL),
(6, 'Periapical Xray', 'SINGLE', 500.00, 15, 'Single-tooth X-ray showing the root and surrounding bone', 1, 0, '2026-04-22 06:46:30', '2026-04-22 06:46:30', NULL),
(7, 'Removable Dentures', 'SINGLE', 5000.00, 30, 'Custom-made removable replacement for missing teeth.', 1, 0, '2026-04-22 06:46:30', '2026-04-22 06:46:30', NULL),
(8, 'Root Canal Treatment', 'SINGLE', 5000.00, 90, 'Saves an infected tooth by removing the pulp.', 1, 0, '2026-04-22 06:46:30', '2026-04-22 06:46:30', NULL),
(9, 'Teeth Cleaning', 'BULK', 800.00, 60, 'Case to case cleaning', 1, 0, '2026-04-22 06:46:31', '2026-04-22 07:22:55', NULL),
(10, 'Teeth Whitening', 'BULK', 5000.00, 90, 'Cosmetic procedure to lighten teeth and remove stains.', 1, 0, '2026-04-22 06:46:31', '2026-04-22 07:23:07', NULL),
(11, 'Tooth Extraction (Ibot)', 'SINGLE', 800.00, 45, 'Safe removal of a damaged or non-restorable tooth.', 1, 0, '2026-04-22 06:46:33', '2026-04-22 06:46:33', NULL),
(12, 'Tooth Restoration (Pasta)', 'SINGLE', 800.00, 45, 'Restores decayed teeth using composite filling.', 1, 0, '2026-04-22 06:46:33', '2026-04-22 06:46:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `service_prices`
--

CREATE TABLE `service_prices` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teeth`
--

CREATE TABLE `teeth` (
  `id` int(11) NOT NULL,
  `tooth_number` varchar(10) NOT NULL,
  `tooth_type` varchar(50) DEFAULT NULL,
  `jaw_type` enum('upper','lower') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatments`
--

CREATE TABLE `treatments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `treatment_date` date NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatments`
--

INSERT INTO `treatments` (`id`, `patient_id`, `doctor_id`, `service_id`, `tooth_id`, `treatment_date`, `cost`, `notes`) VALUES
(1, 2, 4, 3, NULL, '2026-01-28', 500.00, 'Completed treatment'),
(2, 3, 4, 10, NULL, '2026-01-19', 5000.00, 'Completed treatment'),
(3, 4, 4, 11, NULL, '2026-01-18', 800.00, 'Completed treatment'),
(4, 6, 4, 2, NULL, '2026-01-19', 2000.00, 'Completed treatment'),
(5, 8, 4, 3, NULL, '2026-01-28', 500.00, 'Completed treatment'),
(6, 9, 4, 6, NULL, '2026-01-14', 500.00, 'Completed treatment'),
(7, 11, 4, 7, NULL, '2026-01-03', 5000.00, 'Completed treatment'),
(8, 17, 4, 5, NULL, '2026-01-25', 35000.00, 'Completed treatment'),
(9, 18, 4, 12, NULL, '2026-01-10', 800.00, 'Completed treatment'),
(10, 20, 4, 12, NULL, '2026-01-18', 800.00, 'Completed treatment'),
(11, 21, 4, 4, NULL, '2026-01-13', 5000.00, 'Completed treatment'),
(12, 22, 4, 4, NULL, '2026-01-14', 5000.00, 'Completed treatment'),
(13, 24, 4, 8, NULL, '2026-01-21', 5000.00, 'Completed treatment'),
(14, 25, 4, 3, NULL, '2026-01-28', 500.00, 'Completed treatment'),
(15, 26, 4, 3, NULL, '2026-02-14', 500.00, 'Completed treatment'),
(16, 36, 4, 11, NULL, '2026-02-06', 800.00, 'Completed treatment'),
(17, 37, 4, 2, NULL, '2026-02-09', 2000.00, 'Completed treatment'),
(18, 38, 4, 2, NULL, '2026-02-04', 2000.00, 'Completed treatment'),
(19, 41, 4, 11, NULL, '2026-02-10', 800.00, 'Completed treatment'),
(20, 43, 4, 7, NULL, '2026-02-27', 5000.00, 'Completed treatment'),
(21, 44, 4, 3, NULL, '2026-02-05', 500.00, 'Completed treatment'),
(22, 45, 4, 7, NULL, '2026-02-19', 5000.00, 'Completed treatment'),
(23, 46, 4, 12, NULL, '2026-02-13', 800.00, 'Completed treatment'),
(24, 47, 4, 4, NULL, '2026-02-19', 5000.00, 'Completed treatment'),
(25, 48, 4, 1, NULL, '2026-02-01', 500.00, 'Completed treatment'),
(26, 49, 4, 5, NULL, '2026-02-24', 35000.00, 'Completed treatment'),
(27, 51, 4, 2, NULL, '2026-02-03', 2000.00, 'Completed treatment'),
(28, 52, 4, 12, NULL, '2026-02-23', 800.00, 'Completed treatment'),
(29, 53, 4, 11, NULL, '2026-02-16', 800.00, 'Completed treatment'),
(30, 54, 4, 6, NULL, '2026-02-10', 500.00, 'Completed treatment'),
(31, 56, 4, 11, NULL, '2026-03-16', 800.00, 'Completed treatment'),
(32, 58, 4, 5, NULL, '2026-03-05', 35000.00, 'Completed treatment'),
(33, 59, 4, 4, NULL, '2026-03-05', 5000.00, 'Completed treatment'),
(34, 60, 4, 2, NULL, '2026-03-16', 2000.00, 'Completed treatment'),
(35, 61, 4, 4, NULL, '2026-03-04', 5000.00, 'Completed treatment'),
(36, 63, 4, 12, NULL, '2026-03-31', 800.00, 'Completed treatment'),
(37, 65, 4, 3, NULL, '2026-03-16', 500.00, 'Completed treatment'),
(38, 66, 4, 6, NULL, '2026-03-01', 500.00, 'Completed treatment'),
(39, 67, 4, 8, NULL, '2026-03-12', 5000.00, 'Completed treatment'),
(40, 69, 4, 4, NULL, '2026-03-24', 5000.00, 'Completed treatment'),
(41, 70, 4, 2, NULL, '2026-03-18', 2000.00, 'Completed treatment'),
(42, 78, 4, 8, NULL, '2026-03-24', 5000.00, 'Completed treatment'),
(43, 79, 4, 9, NULL, '2026-03-20', 800.00, 'Completed treatment'),
(44, 83, 4, 5, NULL, '2026-03-09', 35000.00, 'Completed treatment'),
(45, 84, 4, 11, NULL, '2026-03-14', 800.00, 'Completed treatment'),
(46, 86, 4, 2, NULL, '2026-03-18', 2000.00, 'Completed treatment'),
(47, 87, 4, 7, NULL, '2026-03-16', 5000.00, 'Completed treatment'),
(48, 88, 4, 3, NULL, '2026-03-21', 500.00, 'Completed treatment'),
(49, 89, 4, 5, NULL, '2026-03-30', 35000.00, 'Completed treatment'),
(50, 91, 4, 3, NULL, '2026-04-15', 500.00, 'Completed treatment'),
(51, 92, 4, 9, NULL, '2026-04-15', 800.00, 'Completed treatment'),
(52, 93, 4, 8, NULL, '2026-04-22', 5000.00, 'Completed treatment'),
(53, 95, 4, 1, NULL, '2026-04-26', 500.00, 'Completed treatment'),
(54, 96, 4, 10, NULL, '2026-04-22', 5000.00, 'Completed treatment'),
(55, 97, 4, 1, NULL, '2026-04-24', 500.00, 'Completed treatment'),
(56, 99, 4, 12, NULL, '2026-04-15', 800.00, 'Completed treatment'),
(57, 100, 4, 2, NULL, '2026-04-12', 2000.00, 'Completed treatment'),
(58, 101, 4, 7, NULL, '2026-04-20', 5000.00, 'Completed treatment'),
(59, 102, 4, 4, NULL, '2026-04-05', 5000.00, 'Completed treatment'),
(60, 103, 4, 6, NULL, '2026-04-18', 500.00, 'Completed treatment'),
(61, 107, 4, 3, NULL, '2026-04-14', 500.00, 'Completed treatment'),
(62, 110, 4, 6, NULL, '2026-04-11', 500.00, 'Completed treatment'),
(63, 116, 4, 5, NULL, '2026-04-10', 35000.00, 'Completed treatment');

-- --------------------------------------------------------

--
-- Table structure for table `treatment_item_statuses`
--

CREATE TABLE `treatment_item_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plans`
--

CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `plan_name` varchar(200) DEFAULT NULL,
  `total_estimated_cost` decimal(10,2) DEFAULT NULL,
  `overall_status` enum('Active','Completed','On-Hold','Cancelled') DEFAULT NULL,
  `next_session_date` DATE DEFAULT NULL,
  `next_session_time` TIME DEFAULT NULL,
  `appointment_id` INT(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plan_details`
--

CREATE TABLE `treatment_plan_details` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `total_sessions` int(11) DEFAULT 1,
  `completed_sessions` int(11) DEFAULT 0,
  `item_status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `first_name`, `middle_name`, `last_name`, `username`, `password`, `email`, `phone`, `gender`, `date_of_birth`, `status`, `is_archived`, `created_at`, `updated_at`) VALUES
(1, 1, 'rex', NULL, 'gabz', 'admin', '$2y$10$cbQbzl/BZ4W2Fm6c2DWajubJDPI84RR7jYQIL79x/xJO5qBIbc2O.', 'rfdental@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-21 11:26:59', '2026-04-21 11:56:52'),
(3, 3, NULL, NULL, NULL, 'staff', '$2y$10$hdFzwmykuQNHabBQFfdmz.Uei1x2P8/DlDi2LQYC1mN0CJYxQOkbO', 'staff@rfdental.com', NULL, NULL, NULL, 'Active', 0, '2026-04-21 11:29:53', '2026-04-21 11:51:27'),
(4, 2, NULL, NULL, NULL, 'dentist', '$2y$10$LOvQy897K2qYpPf4a1FdD.X.SeS8ch6CQtisDWI.I8WQevY5FIWT.', 'dentist@rfdental.com', NULL, NULL, NULL, 'Active', 0, '2026-04-21 11:29:53', '2026-04-21 11:51:27'),
(11, NULL, 'Boss', 'Lorenzo', 'Zata', 'bosszata20@gmail.com', '$2y$10$gaiPj.Vs.fRpefATtDMAt.IUPgNSCE3HCL.c.r7Y5HtHnJ0pbV.OW', 'bosszata20@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-22 11:31:51', '2026-04-22 11:31:51'),
(12, NULL, 'Trisha Mael', 'Macapagal', 'Albuela', 'trishaaame43@gmail.com', '$2y$10$u9.zrGklOypo4EUyBy1OXenL0PAFkmymLPk4nV6H/27F/Tabsq4dm', 'trishaaame43@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-23 02:08:47', '2026-04-23 02:08:47'),
(13, NULL, 'Jery', 'Mariam', 'Maglungsod', 'jery23@gmail.com', '$2y$10$WeeieXOD22AgG.JK87HtUuqIhDEsDHUS8kQW/EJDhZkRH4TomLj6e', 'jery23@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-23 02:15:20', '2026-04-23 02:15:20'),
(14, NULL, 'Nathaniel', 'Villar', 'Quirante', 'nathaniel@gmail.com', '$2y$10$Mo3eE8l.L8fQLJf9zL0Ih.cykzlu5Pd/jm0jVAX.slyijoAhAhIYy', 'nathaniel@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-23 16:35:37', '2026-04-23 16:35:37'),
(15, NULL, 'James Kyle', NULL, 'Paraiso', 'jamesparaiso@gmail.com', '$2y$10$ev7h7BOjFbOzTzmsouDsduGzY17xZ5SVgGX4ipaCxhQVCK5/2OBeS', 'jamesparaiso@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-24 04:49:19', '2026-04-24 04:49:19'),
(16, NULL, 'Nathaniel', NULL, 'Dgg', 'kuyapan@gmail.com', '$2y$10$XNmf7VcRxuaaR67YzxgrZOfsymksAYhfZ2sIfAsMQ2GwPOoGwbFNm', 'kuyapan@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-24 07:56:57', '2026-04-24 07:56:57'),
(17, NULL, 'James Ryan', 'Kilang', 'Dela Cruz', 'kristopermbl23@gmail.com', '$2y$10$1BknNyFky1UPXEJGwzIKI.7ui8bAklnlhQsssQeYDpXWIRz62G8Xq', 'kristopermbl23@gmail.com', NULL, NULL, NULL, 'Active', 0, '2026-04-25 05:12:37', '2026-04-25 05:12:37');

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
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `converted_patient_id` (`converted_patient_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `treatment_id` (`treatment_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `payment_status_id` (`payment_status_id`);

--
-- Indexes for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`);

--
-- Indexes for table `file_types`
--
ALTER TABLE `file_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `topic_service_id` (`topic_service_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `inquiry_sources`
--
ALTER TABLE `inquiry_sources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `patient_id` (`patient_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `religion_id` (`religion_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `registration_source_id` (`registration_source_id`);

--
-- Indexes for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `file_type_id` (`file_type_id`),
  ADD KEY `tooth_id` (`tooth_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `patient_types`
--
ALTER TABLE `patient_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_id` (`billing_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payment_statuses`
--
ALTER TABLE `payment_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `queue_items`
--
ALTER TABLE `queue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `queue_id` (`queue_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `tooth_id` (`tooth_id`);

--
-- Indexes for table `queue_statuses`
--
ALTER TABLE `queue_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `religions`
--
ALTER TABLE `religions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_prices`
--
ALTER TABLE `service_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `teeth`
--
ALTER TABLE `teeth`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `tooth_id` (`tooth_id`);

--
-- Indexes for table `treatment_item_statuses`
--
ALTER TABLE `treatment_item_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `tooth_id` (`tooth_id`),
  ADD KEY `item_status_id` (`item_status_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `dental_history`
--
ALTER TABLE `dental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_types`
--
ALTER TABLE `file_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiry_sources`
--
ALTER TABLE `inquiry_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `patient_files`
--
ALTER TABLE `patient_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_types`
--
ALTER TABLE `patient_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `payment_statuses`
--
ALTER TABLE `payment_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `queue_items`
--
ALTER TABLE `queue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue_statuses`
--
ALTER TABLE `queue_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `religions`
--
ALTER TABLE `religions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `service_prices`
--
ALTER TABLE `service_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teeth`
--
ALTER TABLE `teeth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `treatment_item_statuses`
--
ALTER TABLE `treatment_item_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`converted_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_4` FOREIGN KEY (`payment_status_id`) REFERENCES `payment_statuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD CONSTRAINT `dental_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `inquiry_sources` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_ibfk_3` FOREIGN KEY (`topic_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inquiries_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `inquiry_statuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `patient_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_4` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_ibfk_5` FOREIGN KEY (`registration_source_id`) REFERENCES `inquiry_sources` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD CONSTRAINT `patient_files_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_files_ibfk_2` FOREIGN KEY (`file_type_id`) REFERENCES `file_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_files_ibfk_3` FOREIGN KEY (`tooth_id`) REFERENCES `teeth` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_files_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `patient_insurance_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_insurance_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `queue_statuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue_items`
--
ALTER TABLE `queue_items`
  ADD CONSTRAINT `queue_items_ibfk_1` FOREIGN KEY (`queue_id`) REFERENCES `queue` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_items_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `queue_items_ibfk_3` FOREIGN KEY (`tooth_id`) REFERENCES `teeth` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_prices`
--
ALTER TABLE `service_prices`
  ADD CONSTRAINT `service_prices_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `treatments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `treatments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `treatments_ibfk_4` FOREIGN KEY (`tooth_id`) REFERENCES `teeth` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatment_plans_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `treatment_plans_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  ADD CONSTRAINT `treatment_plan_details_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatment_plan_details_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `treatment_plan_details_ibfk_3` FOREIGN KEY (`tooth_id`) REFERENCES `teeth` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `treatment_plan_details_ibfk_4` FOREIGN KEY (`item_status_id`) REFERENCES `treatment_item_statuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
