-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2026 at 01:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `master_rfdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `reference_code` varchar(20) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `is_registered_patient` tinyint(1) DEFAULT 1,
  `converted_patient_id` int(11) DEFAULT NULL,
  `booked_by_id` int(11) DEFAULT NULL,
  `relation_to_patient` varchar(50) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `dentist_id` int(11) DEFAULT NULL,
  `inquiry_id` int(11) DEFAULT NULL,
  `status` enum('Scheduled','In Queue','Pending Payment','Completed','Cancelled','Pending Approval') DEFAULT 'Scheduled',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
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
  `status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `reference_code`, `patient_id`, `is_registered_patient`, `converted_patient_id`, `booked_by_id`, `relation_to_patient`, `service_id`, `dentist_id`, `inquiry_id`, `status`, `approved_by`, `approved_at`, `appointment_date`, `appointment_time`, `duration_minutes`, `notes`, `is_reminded`, `check_in_time`, `created_by`, `created_at`, `is_archived`, `cancel_reason`, `cancelled_by`, `cancelled_at`, `deleted_at`, `booking_type`, `status_id`) VALUES
(2, 'APT26071517141628', NULL, 1, NULL, 9, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-16', '08:15:00', 30, 'thanks', 0, NULL, 9, '2026-07-15 15:14:16', 1, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, '2026-07-16 22:59:34', 'self-booking', 5),
(3, 'APT26071918171355', 20, 1, NULL, 20, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-21', '08:00:00', 30, '', 0, NULL, 10, '2026-07-19 16:17:13', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(4, 'APT627C15', 16, 1, NULL, 4, NULL, 3, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-22', '08:00:00', 30, NULL, 0, NULL, 4, '2026-07-20 04:42:23', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, NULL, 5),
(6, 'APT26072803125041', 22, 1, NULL, 22, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-29', '09:45:00', 30, '', 0, NULL, 12, '2026-07-28 01:12:50', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(8, 'APT3663F6', 29, 1, NULL, 4, NULL, 7, NULL, NULL, 'Completed', NULL, NULL, '2026-07-29', '10:15:00', 30, NULL, 0, NULL, 4, '2026-07-28 05:24:57', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'APT26072810100554', NULL, 1, NULL, 24, NULL, 7, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-03', '09:00:00', 30, '', 0, '2026-07-28 08:10:35', 13, '2026-07-28 08:10:05', 0, 'Junk test row (blank status) — cleaned up', NULL, '2026-08-02 16:14:04', NULL, 'self-booking', NULL),
(13, 'APT26072810112132', NULL, 1, NULL, 32, NULL, 6, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-01', '10:00:00', 15, '', 0, '2026-07-28 08:15:58', 18, '2026-07-28 08:11:21', 0, 'Junk test row (blank status) — cleaned up', NULL, '2026-08-02 16:14:04', NULL, 'self-booking', NULL),
(14, 'APT26072810124177', 26, 1, NULL, 26, NULL, 7, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '13:30:00', 30, '', 0, NULL, 14, '2026-07-28 08:12:41', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(15, 'APT26072810124422', 26, 1, NULL, 26, NULL, 7, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '00:00:00', 30, '', 0, NULL, 14, '2026-07-28 08:12:44', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(16, 'APT26072810125031', 26, 1, NULL, 26, NULL, 7, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '09:45:00', 30, '', 0, '2026-07-28 08:14:24', 14, '2026-07-28 08:12:50', 0, 'Junk test row (blank status) — cleaned up', NULL, '2026-08-02 16:14:04', NULL, 'self-booking', NULL),
(17, 'APT26072810132793', NULL, 1, NULL, 33, NULL, 8, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-05', '13:30:00', 90, '', 0, NULL, 19, '2026-07-28 08:13:27', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(18, 'APT26072810334833', NULL, 1, NULL, 34, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '15:00:00', 30, '', 0, NULL, 20, '2026-07-28 08:33:48', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(21, 'APT26072917550131', 41, 1, NULL, 2, NULL, 1, 4, NULL, '', NULL, NULL, '2026-08-07', '08:00:00', NULL, 'adsa', 0, '2026-08-05 12:52:24', 2, '2026-07-29 15:55:01', 0, NULL, NULL, NULL, NULL, 'staff', NULL),
(22, 'APT26073001402169', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '08:30:00', 30, '', 0, NULL, 21, '2026-07-29 23:40:21', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(23, 'APT26073005334966', 45, 1, NULL, 45, NULL, 9, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '10:15:00', 60, '', 0, '2026-07-30 03:35:36', 25, '2026-07-30 03:33:49', 0, 'Junk test row (blank status) — cleaned up', NULL, '2026-08-02 16:14:04', NULL, 'self-booking', NULL),
(24, 'APT26073006202273', 45, 1, NULL, 45, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-07-31', '08:00:00', 30, '', 0, '2026-07-30 04:22:32', 25, '2026-07-30 04:20:22', 0, 'Junk test row (blank status) — cleaned up', NULL, '2026-08-02 16:14:04', NULL, 'self-booking', NULL),
(35, 'APT26080210433138', 48, 1, NULL, 48, NULL, 1, NULL, NULL, 'Cancelled', 2, '2026-08-02 17:00:40', '2026-08-03', '08:00:00', 30, '', 0, NULL, 26, '2026-08-02 08:43:31', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(36, 'APT26080211241743', 48, 1, NULL, 48, NULL, 1, NULL, NULL, 'Cancelled', 2, '2026-08-02 17:25:17', '2026-08-04', '08:00:00', 30, '', 0, NULL, 26, '2026-08-02 09:24:17', 0, 'Auto-cancelled due to no-show (patient did not arrive within 15 minutes of appointment time)', NULL, NULL, NULL, 'self-booking', 5),
(37, 'APT26080212260377', 40, 1, NULL, 40, NULL, 1, NULL, NULL, '', 2, '2026-08-02 18:26:34', '2026-08-06', '09:00:00', 30, '', 0, '2026-08-04 14:05:46', 21, '2026-08-02 10:26:03', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(38, 'APT26080212311152', 49, 1, NULL, 49, NULL, 1, NULL, NULL, '', 2, '2026-08-02 18:31:55', '2026-08-06', '10:00:00', 30, 'okkk', 0, '2026-08-05 11:50:16', 27, '2026-08-02 10:31:11', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(39, 'APT26080212524855', 49, 1, NULL, 49, NULL, 1, NULL, NULL, 'Scheduled', 2, '2026-08-02 18:55:45', '2026-08-12', '09:00:00', 30, '', 0, NULL, 27, '2026-08-02 10:52:48', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(40, 'APT26080213025525', 49, 1, NULL, 49, NULL, 1, NULL, NULL, '', 2, '2026-08-02 19:03:23', '2026-08-10', '08:00:00', 30, '', 0, '2026-08-05 15:05:08', 27, '2026-08-02 11:02:55', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(41, 'APT26080306115420', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Completed', 2, '2026-08-03 12:13:32', '2026-08-12', '08:00:00', 30, '', 0, NULL, 21, '2026-08-03 04:11:54', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(42, 'APT26080306142122', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Completed', 2, '2026-08-03 12:14:37', '2026-08-14', '08:00:00', 30, '', 0, NULL, 21, '2026-08-03 04:14:21', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(43, 'APT26080306154821', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Completed', 2, '2026-08-03 12:15:53', '2026-08-15', '09:00:00', 30, '', 0, NULL, 21, '2026-08-03 04:15:48', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(44, 'APT26080306252623', 40, 1, NULL, 40, NULL, 1, NULL, NULL, '', 2, '2026-08-03 12:25:44', '2026-08-20', '08:00:00', 30, '', 0, '2026-08-03 05:01:21', 21, '2026-08-03 04:25:26', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(45, 'APT26080306385061', 50, 1, NULL, 50, NULL, 1, NULL, NULL, '', 2, '2026-08-03 12:39:05', '2026-08-17', '08:00:00', 30, '', 0, '2026-08-04 16:09:35', 28, '2026-08-03 04:38:50', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(46, 'APT26080307200879', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-18', '08:00:00', 30, '', 0, NULL, 21, '2026-08-03 05:20:08', 0, 'theres no doctor on that time maam sorry', 2, '2026-08-03 18:09:30', NULL, 'self-booking', 3),
(47, 'APT26080412483437', 40, 1, NULL, 40, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-04', '13:00:00', 30, '', 0, NULL, 21, '2026-08-04 10:48:34', 0, 'no show', 2, '2026-08-03 21:19:40', NULL, 'self-booking', 3),
(48, 'APT26080422172542', 51, 1, NULL, 51, NULL, 1, NULL, NULL, '', 2, '2026-08-04 22:19:39', '2026-08-05', '08:00:00', 30, '', 0, '2026-08-04 15:01:11', 29, '2026-08-04 14:17:25', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(49, 'APT26080422423820', 52, 1, NULL, 52, NULL, 1, NULL, NULL, 'Cancelled', NULL, NULL, '2026-08-05', '08:30:00', 30, '', 0, NULL, 30, '2026-08-04 14:42:38', 0, 'Auto-declined: appointment date has already passed', NULL, '2026-08-06 00:12:32', NULL, 'self-booking', NULL),
(50, 'APT26080422511453', 53, 1, NULL, 53, NULL, 1, NULL, NULL, '', 2, '2026-08-04 22:52:41', '2026-08-05', '08:30:00', 30, 'pa book matsalams', 0, '2026-08-04 15:02:00', 31, '2026-08-04 14:51:14', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(51, 'APT26080500324030', 50, 1, NULL, 50, NULL, 1, NULL, NULL, '', 2, '2026-08-05 00:32:59', '2026-08-11', '08:00:00', 30, '', 0, '2026-08-04 16:33:49', 28, '2026-08-04 16:32:40', 0, NULL, NULL, NULL, NULL, 'self-booking', 2),
(52, 'APT26080523125395', 61, 1, NULL, 2, NULL, 1, 22, NULL, 'Pending Approval', NULL, NULL, '2026-08-07', '08:00:00', NULL, '', 0, NULL, 2, '2026-08-05 15:12:53', 0, NULL, NULL, NULL, NULL, 'staff', NULL),
(53, 'APT26080600252883', 16, 1, NULL, 2, NULL, 4, 22, NULL, 'Scheduled', NULL, NULL, '2026-08-07', '08:30:00', NULL, '', 0, NULL, 2, '2026-08-05 16:25:28', 0, NULL, NULL, NULL, NULL, 'staff', NULL);

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
(3, 'Cancelled'),
(4, 'Completed'),
(2, 'Confirmed'),
(1, 'Pending');

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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `module`, `action_type`, `status`, `affected_table`, `affected_id`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 09:54:30'),
(2, NULL, 'malazarteshane24@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: malazarteshane24@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 10:07:32'),
(3, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 11:41:49'),
(4, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 11:42:25'),
(5, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:07:27'),
(6, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:08:03'),
(7, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:08:55'),
(8, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:10:06'),
(9, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:21:26'),
(10, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:22:04'),
(11, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:45:17'),
(12, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:45:43'),
(13, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:48:05'),
(14, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:51:37'),
(15, 2, 'staff', 'staff', 'queue', 'status_change', 'success', 'queue', 1, 'Patient put on hold: James  Dela cruz', 'waiting', 'on_hold', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:53:44'),
(16, 2, 'staff', 'staff', 'queue', 'status_change', 'success', 'queue', 1, 'Patient re-queued: James  Dela cruz', 'on_hold', 'waiting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 12:54:07'),
(17, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 13:03:39'),
(18, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 13:04:09'),
(19, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 13:27:39'),
(20, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 13:40:01'),
(21, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 13:58:22'),
(22, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:10:26'),
(23, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:19:56'),
(24, NULL, 'babyjeanjumawid05@gmail.com', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:20:07'),
(25, NULL, 'babyjeanjumawid05@gmail.com', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:20:35'),
(26, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:20:43'),
(27, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:27:40'),
(28, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:31:57'),
(29, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 14:48:55'),
(30, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:01:12'),
(31, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:01:43'),
(32, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:09:55'),
(33, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:21:59'),
(34, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:33:21'),
(35, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:48:32'),
(36, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:49:14'),
(37, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:49:33'),
(38, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:49:51'),
(39, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:49:55'),
(40, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:50:06'),
(41, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:50:27'),
(42, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:50:32'),
(43, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:50:48'),
(44, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:51:02'),
(45, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:51:20'),
(46, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:52:06'),
(47, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:52:49'),
(48, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:53:05'),
(49, NULL, 'chimp.artfiles@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: chimp.artfiles@gmail.com', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:53:14'),
(50, NULL, 'chimp.artfiles@gmail.com', '', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:53:25'),
(51, NULL, 'chimp.artfiles@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:56:06'),
(52, NULL, 'bosszata20@gmail.com', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:56:30'),
(53, NULL, 'bosszata20@gmail.com', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 15:56:36'),
(54, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:02:27'),
(55, NULL, 'chimp.artfiles@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:08:51'),
(56, NULL, 'chimp.artfiles@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:08:59'),
(57, NULL, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:09:18'),
(58, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:28:27'),
(59, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:30:23'),
(60, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:36:18'),
(61, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:41:55'),
(62, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:44:06'),
(63, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:47:56'),
(64, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:48:17'),
(65, 4, 'dentist', 'dentist', 'queue', 'status_change', 'success', 'queue', 1, 'Started procedure for patient: James  Dela cruz', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:48:38'),
(66, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 2, 'Payment recorded: ₱800.00 for patient: James Dela cruz (INV-002)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:49:07'),
(67, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 1, 'Created payment record: ₱800.00 for patient: James Dela cruz', NULL, '₱800.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:49:07'),
(68, 4, 'dentist', 'dentist', 'queue', 'status_change', 'success', 'queue', 1, 'Completed procedure for: James  Dela cruz', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 10:55:31'),
(69, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:02:44'),
(70, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:06:02'),
(71, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:48:43'),
(72, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:49:12'),
(73, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:50:21'),
(74, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:50:41'),
(75, 4, 'dentist', 'dentist', 'queue', 'status_change', 'success', 'queue', 2, 'Started procedure for patient: Jesper Maglangit Diaz', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:51:12'),
(76, 2, 'staff', 'staff', 'queue', 'status_change', 'success', 'queue', 2, 'Completed procedure for: Jesper  Diaz', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:59:31'),
(77, 4, 'dentist', 'dentist', 'queue', 'status_change', 'success', 'queue', 3, 'Started procedure for patient: Baby Jean Jumawid', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 11:59:50'),
(78, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:01:35'),
(79, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:13:40'),
(80, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:14:26'),
(81, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:16:05'),
(82, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:16:10'),
(83, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:16:23'),
(84, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 4, 'Partial payment recorded: ₱2,500.00 via installment for patient: Baby Jumawid', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:17:12'),
(85, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 2, 'Created payment record: ₱2,500.00 for patient: Baby Jumawid', NULL, '₱2,500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:17:12'),
(86, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:18:34'),
(87, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:18:37'),
(88, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 4, 'Partial payment recorded: ₱1,000.00 via cash for patient: Baby Jumawid', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:19:05'),
(89, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 3, 'Created payment record: ₱1,000.00 for patient: Baby Jumawid', NULL, '₱1,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:19:05'),
(90, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:19:08'),
(91, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:24:29'),
(92, 1, 'admin', 'admin', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:35:53'),
(93, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:54:13'),
(94, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:55:08'),
(95, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 12:57:58'),
(96, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:06:35'),
(97, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 2, 'Viewed payment details for patient ID: 2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:06:41'),
(98, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:08:12'),
(99, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:08:14'),
(100, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:08:40'),
(101, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:09:25'),
(102, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 2, 'Viewed payment details for patient ID: 2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:14:48'),
(103, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 3, 'Viewed payment details for patient ID: 3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:15:00'),
(104, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:15:14'),
(105, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:18:13'),
(106, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:18:29'),
(107, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 2, 'Viewed payment details for patient ID: 2', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:18:57'),
(108, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:20:00'),
(109, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:29:53'),
(110, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:30:03'),
(111, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 4, 'Viewed payment details for patient ID: 4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:33:45'),
(112, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 13:38:54'),
(113, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 14:08:38'),
(114, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 14:25:35'),
(115, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-14 14:29:47'),
(116, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 12:45:55'),
(117, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 13:02:11'),
(118, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:02:59'),
(119, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:10:00'),
(120, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:23:25'),
(121, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:28:08'),
(122, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:32:54'),
(123, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:33:58'),
(124, 4, 'dentist', 'dentist', 'queue', 'status_change', 'success', 'queue', 3, 'Completed procedure for: Baby  Jumawid', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:34:18'),
(125, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'billing', 4, 'Viewed billing record: INV-004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:42:26'),
(126, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:43:27'),
(127, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:52:21'),
(128, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 14:52:48'),
(129, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:00:05'),
(130, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:00:36'),
(131, 4, 'dentist', 'dentist', 'billing', 'update', 'success', 'billing', 5, 'Updated billing amount: ₱800.00 → ₱900.00 for patient: Walkin Dela cruz (INV-005) - Reason: Dentist adjustment', '₱800.00', '₱900.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:01:14'),
(132, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:13:07'),
(133, 9, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:25:10'),
(134, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:25:15'),
(135, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 8, 'Viewed payment details for patient ID: 8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:41:55'),
(136, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:52:30'),
(137, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:55:02'),
(138, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 8, 'Viewed payment details for patient ID: 8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 15:59:10'),
(139, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 8, 'Viewed payment details for patient ID: 8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:00:45'),
(140, NULL, NULL, NULL, 'queue', '', 'success', 'queue', 4, 'Assigned doctor to patient: Walkin  Dela cruz -> Floralyn Gabz', NULL, 'Floralyn Gabz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:03:45'),
(141, NULL, NULL, NULL, 'queue', '', 'success', 'queue', 4, 'Assigned doctor to patient: Walkin  Dela cruz -> Unassigned', NULL, 'Unassigned', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:03:52'),
(142, 9, 'lenghuk23@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:06:35'),
(143, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:08:48'),
(144, NULL, NULL, NULL, 'queue', '', 'success', 'queue', 4, 'Assigned doctor to patient: Walkin  Dela cruz -> Floralyn Gabz', NULL, 'Floralyn Gabz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:13:11'),
(145, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:13:27'),
(146, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:17:22'),
(147, 9, 'lenghuk23@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:25:27'),
(148, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:25:55'),
(149, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:28:46'),
(150, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:34:17'),
(151, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:35:07'),
(152, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 8, 'Viewed payment details for patient ID: 8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:35:53'),
(153, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 4, 'Patient put on hold: Walkin  Dela cruz', 'waiting', 'on_hold', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:36:00'),
(154, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 4, 'Patient re-queued: Walkin  Dela cruz', 'on_hold', 'waiting', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:36:04'),
(155, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 8, 'Viewed payment details for patient ID: 8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:38:03'),
(156, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:40:19'),
(157, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 4, 'Started procedure for patient: Walkin  Dela cruz', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 16:44:27'),
(158, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 4, 'Completed procedure for: Walkin  Dela cruz', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:08:15'),
(159, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:10:43'),
(160, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 5, 'Started procedure for patient: Walkin  Dela cruz', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:12:51'),
(161, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 5, 'Completed procedure for: Walkin  Dela cruz', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:13:09'),
(162, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:14:59'),
(163, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:15:43'),
(164, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:16:25'),
(165, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:24:45'),
(166, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:25:11'),
(167, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:25:24'),
(168, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:25:35'),
(169, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:26:20'),
(170, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:26:47'),
(171, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:27:03'),
(172, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:27:21'),
(173, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:31:33'),
(174, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 5, 'Viewed billing record: INV-005', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 17:38:07'),
(175, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:34:58'),
(176, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:38:59'),
(177, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:43:16'),
(178, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:50:23'),
(179, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:52:07'),
(180, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 10, 'Viewed payment details for patient ID: 10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:56:03'),
(181, NULL, NULL, NULL, 'queue', '', 'success', 'queue', 6, 'Assigned doctor to patient: Walkin  Patient -> Floralyn Gabz', NULL, 'Floralyn Gabz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:56:12'),
(182, 4, 'dentist', 'dentist', 'billing', 'update', 'success', 'billing', 6, 'Updated billing amount: ₱800.00 → ₱850.00 for patient: Walkin Patient (INV-006) - Reason: Dentist adjustment', '₱800.00', '₱850.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:57:12'),
(183, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 10, 'Viewed payment details for patient ID: 10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:57:39'),
(184, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 10, 'Viewed payment details for patient ID: 10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:57:44'),
(185, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 10, 'Viewed payment details for patient ID: 10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:57:51');
INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `module`, `action_type`, `status`, `affected_table`, `affected_id`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(186, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 10, 'Viewed payment details for patient ID: 10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:58:07'),
(187, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 6, 'Viewed billing record: INV-006', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:58:20'),
(188, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 6, 'Viewed billing record: INV-006', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:08:09'),
(189, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:09:02'),
(190, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:20:00'),
(191, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:26:18'),
(192, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:28:12'),
(193, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:29:07'),
(194, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:32:30'),
(195, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:32:38'),
(196, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 6, 'Viewed billing record: INV-006', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:32:49'),
(197, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:33:49'),
(198, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 6, 'Started procedure for patient: Walkin  Patient', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:35:30'),
(199, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 6, 'Completed procedure for: Walkin  Patient', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:35:32'),
(200, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 6, 'Viewed billing record: INV-006', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:35:44'),
(201, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 8, 'Started procedure for patient: walk in  patient', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:42:35'),
(202, 4, 'dentist', 'dentist', 'billing', 'update', 'success', 'billing', 7, 'Updated billing amount: ₱800.00 → ₱850.00 for patient: walk in patient (INV-007) - Reason: Dentist adjustment', '₱800.00', '₱850.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:42:45'),
(203, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 7, 'Viewed billing record: INV-007', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:42:55'),
(204, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 7, 'Viewed billing record: INV-007', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:46:16'),
(205, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 8, 'Completed procedure for: walk in  patient', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:46:22'),
(206, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:56:13'),
(207, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 11:59:57'),
(208, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:02:12'),
(209, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:12:04'),
(210, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:12:37'),
(211, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:22:32'),
(212, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:24:57'),
(213, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 7, 'Viewed billing record: INV-007', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:25:12'),
(214, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:25:54'),
(215, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:36:06'),
(216, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:38:31'),
(217, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:40:27'),
(218, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 9, 'Started procedure for patient: walk in  patient', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:40:58'),
(219, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 9, 'Completed procedure for: walk in  patient', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:41:01'),
(220, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:44:51'),
(221, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 7, 'Viewed billing record: INV-007', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:45:24'),
(222, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 7, 'Viewed billing record: INV-007', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:45:38'),
(223, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 8, 'Viewed billing record: INV-008', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 12:50:33'),
(224, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:01:19'),
(225, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:04:07'),
(226, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:17:34'),
(227, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:22:31'),
(228, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:24:11'),
(229, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 10, 'Started procedure for patient: sample  sample', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:30:06'),
(230, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 10, 'Completed procedure for: sample  sample', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:30:10'),
(231, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:30:29'),
(232, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 11, 'Started procedure for patient: sample  sample', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:35:38'),
(233, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 11, 'Completed procedure for: sample  sample', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:35:46'),
(234, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 12, 'Viewed payment details for patient ID: 12', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:42:15'),
(235, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 12, 'Viewed payment details for patient ID: 12', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:42:20'),
(236, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 13:42:57'),
(237, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 12, 'Started procedure for patient: sample  sample', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:03:40'),
(238, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 12, 'Completed procedure for: sample  sample', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:03:42'),
(239, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 13, 'Started procedure for patient: sample2  again', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:03:56'),
(240, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 13, 'Completed procedure for: sample2  again', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:03:58'),
(241, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:13:24'),
(242, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 14, 'Started procedure for patient: walk in  patient', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:16:31'),
(243, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 14, 'Completed procedure for: walk in  patient', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:16:34'),
(244, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:17:06'),
(245, 4, 'dentist', 'dentist', 'billing', 'payment', 'success', 'billing', 12, 'Payment recorded: ₱500.00 for patient: walk_in2 thesecond (INV-012)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:18:27'),
(246, 4, 'dentist', 'dentist', 'payments', 'create', 'success', 'payments', 4, 'Created payment record: ₱500.00 for patient: walk_in2 thesecond', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:18:27'),
(247, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:18:43'),
(248, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:23:14'),
(249, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:23:42'),
(250, 4, 'dentist', 'dentist', 'billing', 'payment', 'success', 'billing', 9, 'Partial payment recorded: ₱800.00 via cash for patient: sample sample', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:42:20'),
(251, 4, 'dentist', 'dentist', 'payments', 'create', 'success', 'payments', 5, 'Created payment record: ₱800.00 for patient: sample sample', NULL, '₱800.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:42:20'),
(252, 4, 'dentist', 'dentist', 'billing', 'payment', 'success', 'billing', 11, 'Partial payment recorded: ₱10.00 via cash for patient: walk in patient', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:43:43'),
(253, 4, 'dentist', 'dentist', 'payments', 'create', 'success', 'payments', 6, 'Created payment record: ₱10.00 for patient: walk in patient', NULL, '₱10.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:43:43'),
(254, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:43:50'),
(255, 4, 'dentist', 'dentist', 'billing', 'payment', 'success', 'billing', 11, 'Partial payment recorded: ₱20.00 via cash for patient: walk in patient', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:44:13'),
(256, 4, 'dentist', 'dentist', 'payments', 'create', 'success', 'payments', 7, 'Created payment record: ₱20.00 for patient: walk in patient', NULL, '₱20.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:44:13'),
(257, 4, 'dentist', 'dentist', 'billing', 'payment', 'success', 'billing', 11, 'Partial payment recorded: ₱10.00 via cash for patient: walk in patient', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:44:46'),
(258, 4, 'dentist', 'dentist', 'payments', 'create', 'success', 'payments', 8, 'Created payment record: ₱10.00 for patient: walk in patient', NULL, '₱10.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:44:46'),
(259, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:45:35'),
(260, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:56:22'),
(261, NULL, 'chimp.artfiles@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:00:13'),
(262, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:08:58'),
(263, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:10:00'),
(264, NULL, 'chimp.artfiles@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:10:21'),
(265, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:19:11'),
(266, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:24:55'),
(267, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 16, 'Started procedure for patient: Juan  Miguel', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:28:43'),
(268, 4, 'dentist', 'dentist', 'billing', 'update', 'success', 'billing', 13, 'Updated billing amount: ₱800.00 → ₱900.00 for patient: Juan Miguel (INV-013) - Reason: Dentist adjustment', '₱800.00', '₱900.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:28:58'),
(269, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 16, 'Completed procedure for: Juan  Miguel', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:29:28'),
(270, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 13, 'Payment recorded: ₱900.00 for patient: Juan Miguel (INV-013)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:29:48'),
(271, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 9, 'Created payment record: ₱900.00 for patient: Juan Miguel', NULL, '₱900.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:29:48'),
(272, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:31:21'),
(273, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:40:30'),
(274, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:40:41'),
(275, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:40:53'),
(276, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:43:24'),
(277, NULL, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:48:57'),
(278, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:49:37'),
(279, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:52:52'),
(280, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:53:01'),
(281, NULL, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:59:11'),
(282, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 16:05:11'),
(283, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 08:12:22'),
(284, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 08:12:45'),
(285, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 06:35:11'),
(286, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 06:41:28'),
(287, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 06:44:51'),
(288, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 06:58:30'),
(289, NULL, 'bosszata20@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: bosszata20@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:14:53'),
(290, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:15:07'),
(291, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:15:57'),
(292, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:18:27'),
(293, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:32:45'),
(294, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:36:59'),
(295, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:37:51'),
(296, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:42:26'),
(297, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:43:04'),
(298, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:43:50'),
(299, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:52:58'),
(300, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 09:58:59'),
(301, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 10:12:26'),
(302, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 10:18:18'),
(303, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 10:18:46'),
(304, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:11:45'),
(305, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:11:57'),
(306, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:37:31'),
(307, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:48:57'),
(308, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:49:03'),
(309, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:50:03'),
(310, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 13:38:32'),
(311, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 14:55:25'),
(312, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 15:37:10'),
(313, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:04:16'),
(314, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:06:03'),
(315, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:16:05'),
(316, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:16:43'),
(317, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:25:59'),
(318, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:27:18'),
(319, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 00:40:05'),
(320, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 00:40:13'),
(321, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:33:20'),
(322, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:34:17'),
(323, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:34:33'),
(324, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:44:27'),
(325, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:50:22'),
(326, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 01:50:48'),
(327, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:01:27'),
(328, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:01:41'),
(329, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:04:31'),
(330, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:14:05'),
(331, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:16:10'),
(332, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:36:54'),
(333, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:38:18'),
(334, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:40:31'),
(335, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:55:39'),
(336, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:56:51'),
(337, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 02:58:24'),
(338, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:27:01'),
(339, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:27:15'),
(340, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:46:45'),
(341, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:47:16'),
(342, NULL, 'mara.sabanal.coc@phinmaed.com	P', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: mara.sabanal.coc@phinmaed.com	P', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:54:40'),
(343, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:54:50'),
(344, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:55:26'),
(345, NULL, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 03:57:03'),
(346, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:09:28'),
(347, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:11:01'),
(348, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:17:49'),
(349, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:19:40'),
(350, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:20:54'),
(351, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:21:04'),
(352, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:22:40'),
(353, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 13, 'Viewed billing record: INV-013', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:22:54'),
(354, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:22:58'),
(355, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:25:47'),
(356, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:25:50'),
(357, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:25:53'),
(358, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:26:10'),
(359, 1, 'admin', 'admin', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:26:19'),
(360, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:28:10'),
(361, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:31:34'),
(362, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:31:41'),
(363, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:34:14'),
(364, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:34:44'),
(365, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:34:51'),
(366, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:34:56'),
(367, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:35:42'),
(368, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:49:09'),
(369, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:50:44'),
(370, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 16, 'Viewed payment details for patient ID: 16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:52:34'),
(371, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:53:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `module`, `action_type`, `status`, `affected_table`, `affected_id`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(372, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 04:54:30'),
(373, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 05:00:14'),
(374, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 05:06:23'),
(375, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:29:17'),
(376, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:29:44'),
(377, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:30:20'),
(378, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:30:31'),
(379, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:30:45'),
(380, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:32:41'),
(381, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:34:12'),
(382, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:34:42'),
(383, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:36:27'),
(384, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:36:39'),
(385, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:37:11'),
(386, NULL, 'staff', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: staff', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:37:22'),
(387, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:37:33'),
(388, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 06:38:22'),
(389, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:15:43'),
(390, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:15:46'),
(391, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:15:46'),
(392, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:16:38'),
(393, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:17:34'),
(394, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:20:29'),
(395, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:23:53'),
(396, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:24:12'),
(397, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:25:14'),
(398, NULL, 'mara.sabanal.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:29:40'),
(399, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 07:30:20'),
(400, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 12:04:31'),
(401, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 12:30:48'),
(402, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 13:05:29'),
(403, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 13:21:48'),
(404, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:02:56'),
(405, NULL, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:03:17'),
(406, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:13:01'),
(407, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:13:01'),
(408, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:13:13'),
(409, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:14:05'),
(410, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 19, 'Started procedure for patient: sample dad dadwad', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:15:43'),
(411, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:22:39'),
(412, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 01:26:00'),
(413, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 02:30:28'),
(414, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 02:47:39'),
(415, NULL, 'davidcasinillo6@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 02:49:18'),
(416, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 02:50:07'),
(417, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:04:24'),
(418, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:06:11'),
(419, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:21:32'),
(420, NULL, 'staff', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: staff', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 03:22:21'),
(421, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 03:22:36'),
(422, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 20, 'Started procedure for patient: James Malik Deskaya', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:25:22'),
(423, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:29:20'),
(424, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:29:41'),
(425, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:30:19'),
(426, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:30:33'),
(427, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:31:06'),
(428, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:31:46'),
(429, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:33:47'),
(430, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:36:57'),
(431, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 03:40:47'),
(432, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 03:41:33'),
(433, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 17, 'Viewed billing record: INV-017', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:43:23'),
(434, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 21, 'Started procedure for patient: David  Cas', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:46:03'),
(435, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:51:28'),
(436, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 03:54:03'),
(437, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:31:32'),
(438, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:35:02'),
(439, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:42:29'),
(440, 4, 'dentist', 'dentist', 'billing', 'update', 'success', 'billing', 18, 'Updated billing amount: ₱800.00 → ₱800.00 for patient: David Cas (INV-018) - Reason: Dentist adjustment', '₱800.00', '₱800.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:44:47'),
(441, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 18, 'Partial payment recorded: ₱400.00 via cash for patient: David Cas', 'pending', 'partial', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:47:42'),
(442, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 10, 'Created payment record: ₱400.00 for patient: David Cas', NULL, '₱400.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:47:42'),
(443, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 18, 'Viewed billing record: INV-018', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:47:49'),
(444, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 18, 'Partial payment recorded: ₱400.00 via gcash for patient: David Cas', 'pending', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:50:46'),
(445, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 11, 'Created payment record: ₱400.00 for patient: David Cas', NULL, '₱400.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 04:50:46'),
(446, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:13:07'),
(447, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:15:45'),
(448, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:17:24'),
(449, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:20:16'),
(450, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 22, 'Started procedure for patient: sample sample d. patient', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:20:31'),
(451, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:39:22'),
(452, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:46:24'),
(453, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 05:51:10'),
(454, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.203', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-28 06:05:18'),
(455, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:19:39'),
(456, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:23:11'),
(457, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 06:23:50'),
(458, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:25:34'),
(459, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:25:54'),
(460, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.202', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:32:18'),
(461, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:34:02'),
(462, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:52:18'),
(463, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 06:55:00'),
(464, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '192.168.100.203', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-28 06:55:13'),
(465, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:05:08'),
(466, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:05:33'),
(467, 1, 'admin', 'admin', 'appointments', '', 'success', 'appointments', 9, 'Appointment cancelled: APT-0009 - Reason: no show', NULL, 'cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:05:57'),
(468, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '192.168.100.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:11:34'),
(469, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:15:03'),
(470, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:16:05'),
(471, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 19, 'Payment recorded: ₱500.00 for patient: sample sample patient (INV-019)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:17:21'),
(472, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 12, 'Created payment record: ₱500.00 for patient: sample sample patient', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:17:21'),
(473, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 24, 'Started procedure for patient: Baby Jean Galendez Jumawid', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:17:29'),
(474, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:21:21'),
(475, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:21:26'),
(476, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:28:59'),
(477, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:30:04'),
(478, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:30:12'),
(479, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:30:20'),
(480, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:55:57'),
(481, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:55:58'),
(482, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:55:58'),
(483, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:55:58'),
(484, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:57:04'),
(485, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 07:59:08'),
(486, NULL, 'staff', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: staff', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:02:25'),
(487, NULL, 'staff', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: staff', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:02:33'),
(488, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:02:50'),
(489, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.220', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:03:04'),
(490, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 25, 'Started procedure for patient: Baby Jean Galendez Jumawid', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:05:08'),
(491, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:09:12'),
(492, NULL, 'davidcasinillo6@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:09:33'),
(493, NULL, 'davidcasinillo6@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:10:58'),
(494, NULL, 'baga.jumawid.coc@phinmaed.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.220', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:11:00'),
(495, NULL, 'davidcasinillo13@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: davidcasinillo13@gmail.com', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:11:20'),
(496, NULL, 'baga.jumawid.coc@phinmaed.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '10.25.155.220', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:11:29'),
(497, NULL, 'theunbreakable13@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:11:44'),
(498, NULL, 'pelotchej@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.220', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:13:06'),
(499, NULL, 'theunbreakable13@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:17:05'),
(500, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 08:17:25'),
(501, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:24:48'),
(502, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:36:59'),
(503, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:40:26'),
(504, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:44:26'),
(505, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:47:49'),
(506, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:48:11'),
(507, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 26, 'Started procedure for patient: David Nams Casinillo', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:49:40'),
(508, NULL, 'babyjeanjumawid05@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:52:25'),
(509, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 16, 'Viewed billing record: INV-016', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:58:16'),
(510, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 16, 'Viewed billing record: INV-016', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:58:26'),
(511, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 16, 'Partial payment recorded: ₱500.00 via cash for patient: sample dadwad', 'pending', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:58:42'),
(512, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 13, 'Created payment record: ₱500.00 for patient: sample dadwad', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:58:42'),
(513, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 17, 'Payment recorded: ₱500.00 for patient: James Deskaya (INV-017)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:59:14'),
(514, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 14, 'Created payment record: ₱500.00 for patient: James Deskaya', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 08:59:14'),
(515, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 10:22:31'),
(516, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 10:22:31'),
(517, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:03:31'),
(518, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:06:13'),
(519, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:06:54'),
(520, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:16:35'),
(521, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:17:37'),
(522, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:26:02'),
(523, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:27:03'),
(524, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 14:32:16'),
(525, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:24:11'),
(526, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:24:46'),
(527, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.225.168.101', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-28 15:26:27'),
(528, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:34:28'),
(529, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:39:08'),
(530, NULL, 'staff', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: staff', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:51:59'),
(531, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:52:08'),
(532, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:55:03'),
(533, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 31, 'Patient cancelled: Sandro  Marcos', 'waiting', 'cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 15:59:44'),
(534, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 16:03:32'),
(535, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 16:15:25'),
(536, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 16:17:41'),
(537, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 16:22:24'),
(538, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 16:41:47'),
(539, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:03:56'),
(540, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:06:06'),
(541, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:06:22'),
(542, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:53:45'),
(543, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:53:45'),
(544, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:54:50'),
(545, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 05:55:23'),
(546, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 06:05:59'),
(547, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 06:20:28'),
(548, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 10:49:01'),
(549, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 10:50:43'),
(550, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 11:18:13'),
(551, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 11:21:36'),
(552, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 11:52:16'),
(553, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 11:54:06'),
(554, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:20:50'),
(555, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:21:50'),
(556, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:55:42'),
(557, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 12:56:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `module`, `action_type`, `status`, `affected_table`, `affected_id`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(558, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:05:19'),
(559, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:13:21'),
(560, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:17:57'),
(561, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:20:40'),
(562, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:25:37'),
(563, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:41:05'),
(564, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:50:04'),
(565, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:50:29'),
(566, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:51:27'),
(567, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:51:40'),
(568, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:51:50'),
(569, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:51:55'),
(570, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:52:04'),
(571, NULL, 'babyjeanjumawid05@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: babyjeanjumawid05@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:52:12'),
(572, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 13:52:42'),
(573, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:00:13'),
(574, 21, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:12:40'),
(575, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:12:44'),
(576, NULL, 'bosszata20@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: bosszata20@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:25:41'),
(577, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:25:58'),
(578, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:26:07'),
(579, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:26:21'),
(580, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:40:52'),
(581, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:47:16'),
(582, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:48:17'),
(583, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 14:58:43'),
(584, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 15:04:05'),
(585, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 15:04:35'),
(586, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 15:12:03'),
(587, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 15:22:22'),
(588, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 15:52:34'),
(589, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:05:52'),
(590, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:12:24'),
(591, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:18:55'),
(592, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:25:38'),
(593, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:30:57'),
(594, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:45:14'),
(595, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:45:37'),
(596, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:59:26'),
(597, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 16:59:42'),
(598, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:00:46'),
(599, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:23:24'),
(600, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:23:49'),
(601, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:26:02'),
(602, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:39:38'),
(603, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 33, 'Started procedure for patient: Krist  Lampembert', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 17:40:03'),
(604, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:38:59'),
(605, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:39:38'),
(606, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:42:56'),
(607, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:52:03'),
(608, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:57:59'),
(609, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-29 23:58:57'),
(610, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:09:59'),
(611, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:14:25'),
(612, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:19:44'),
(613, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:20:24'),
(614, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:21:19'),
(615, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:34:30'),
(616, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:41:08'),
(617, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:41:41'),
(618, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:42:22'),
(619, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 00:42:34'),
(620, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 02:41:41'),
(621, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 02:42:20'),
(622, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 02:43:54'),
(623, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 02:52:40'),
(624, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 02:53:59'),
(625, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:01:28'),
(626, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:07:52'),
(627, 23, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:14:09'),
(628, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:18:08'),
(629, NULL, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:26:30'),
(630, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:27:26'),
(631, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:28:26'),
(632, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:28:38'),
(633, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:29:51'),
(634, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:30:09'),
(635, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:30:24'),
(636, 25, 'davidcasinillo728@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.25.155.48', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-30 03:33:23'),
(637, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:34:11'),
(638, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:35:59'),
(639, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:36:13'),
(640, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 36, 'Started procedure for patient: david nams casinillo', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:36:18'),
(641, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:39:41'),
(642, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:41:47'),
(643, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:48:44'),
(644, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:49:00'),
(645, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:49:07'),
(646, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:49:22'),
(647, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:49:43'),
(648, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:50:50'),
(649, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 03:51:24'),
(650, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:04:21'),
(651, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:04:34'),
(652, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:07:24'),
(653, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:07:37'),
(654, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 36, 'Completed procedure for: david  casinillo', 'in_procedure', 'completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:07:46'),
(655, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 37, 'Started procedure for patient: Chimp  Art', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:07:54'),
(656, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 38, 'Started procedure for patient: fritzz  turtosa jr.', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:10:00'),
(657, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:11:09'),
(658, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:11:19'),
(659, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 27, 'Partial payment recorded: ₱500.00 via cash for patient: fritzz turtosa', 'pending', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:12:11'),
(660, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 15, 'Created payment record: ₱500.00 for patient: fritzz turtosa', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:12:11'),
(661, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:13:07'),
(662, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:13:20'),
(663, 25, 'davidcasinillo728@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:18:20'),
(664, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:21:04'),
(665, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:27:34'),
(666, 25, 'davidcasinillo728@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 04:30:39'),
(667, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 06:27:45'),
(668, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-30 06:40:33'),
(669, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-01 13:42:15'),
(670, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-01 14:27:00'),
(671, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:40:34'),
(672, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:41:09'),
(673, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:43:13'),
(674, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:43:18'),
(675, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:43:30'),
(676, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:43:38'),
(677, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:43:52'),
(678, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 01:52:11'),
(679, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:19:41'),
(680, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:35:53'),
(681, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:35:57'),
(682, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:39:33'),
(683, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:45:17'),
(684, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 02:46:35'),
(685, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '10.177.39.185', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 02:51:42'),
(686, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '10.177.39.185', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 03:07:54'),
(687, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:17:36'),
(688, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:32:40'),
(689, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:33:21'),
(690, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:39:52'),
(691, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:40:07'),
(692, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:43:23'),
(693, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:51:14'),
(694, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:53:15'),
(695, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 03:53:35'),
(696, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 04:02:15'),
(697, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 04:26:56'),
(698, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 04:39:08'),
(699, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:03:37'),
(700, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:06:21'),
(701, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:26:28'),
(702, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:36:33'),
(703, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:37:45'),
(704, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:39:35'),
(705, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:40:00'),
(706, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:54:22'),
(707, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 05:58:48'),
(708, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:07:44'),
(709, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:09:05'),
(710, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:33:35'),
(711, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:33:39'),
(712, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:33:48'),
(713, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 06:42:29'),
(714, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:02:36'),
(715, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:08:24'),
(716, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:15:17'),
(717, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:28:20'),
(718, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:47:33'),
(719, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 07:58:05'),
(720, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:18:21'),
(721, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:23:21'),
(722, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:33:53'),
(723, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:39:26'),
(724, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:44:40'),
(725, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:56:59'),
(726, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 08:59:12'),
(727, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:19:06'),
(728, 26, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:20:26'),
(729, NULL, 'kimpang2k24@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: kimpang2k24@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:20:45'),
(730, 26, 'kimpang2k25@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:21:21'),
(731, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:23:56'),
(732, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:24:01'),
(733, 26, 'kimpang2k25@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:34:35'),
(734, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 09:44:17'),
(735, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:25:34'),
(736, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:25:48'),
(737, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:28:17'),
(738, 27, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:41:01'),
(739, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:45:31'),
(740, 27, 'lenghuk2k25@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:48:11'),
(741, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 10:52:59'),
(742, 27, 'lenghuk2k25@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 11:06:04'),
(743, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-02 11:19:20'),
(744, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:11:23'),
(745, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:12:07'),
(746, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:24:17'),
(747, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:25:11');
INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `user_role`, `module`, `action_type`, `status`, `affected_table`, `affected_id`, `description`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(748, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:32:51'),
(749, 28, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 04:48:40'),
(750, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 05:17:47'),
(751, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 05:19:58'),
(752, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 05:30:04'),
(753, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 06:25:06'),
(754, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 09:06:52'),
(755, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 10:08:37'),
(756, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 10:15:11'),
(757, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 10:30:33'),
(758, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 10:46:54'),
(759, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 10:49:02'),
(760, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:00:48'),
(761, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:12:03'),
(762, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:12:43'),
(763, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:15:59'),
(764, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:23:55'),
(765, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 11:39:19'),
(766, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 13:16:21'),
(767, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 40, 'Started procedure for patient: Kurt Jay Trinidad Maligmat', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 13:18:45'),
(768, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 13:47:11'),
(769, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 13:57:27'),
(770, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 13:58:32'),
(771, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 02:59:03'),
(772, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 03:01:51'),
(773, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 03:25:01'),
(774, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:08:23'),
(775, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:17:03'),
(776, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:28:35'),
(777, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:29:56'),
(778, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:30:36'),
(779, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:46:38'),
(780, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 09:58:31'),
(781, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 10:28:55'),
(782, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 10:41:31'),
(783, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 10:55:32'),
(784, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 10:59:49'),
(785, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:03:14'),
(786, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:15:57'),
(787, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:16:05'),
(788, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:19:40'),
(789, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:21:58'),
(790, NULL, 'admin', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:24:12'),
(791, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:24:19'),
(792, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:31:42'),
(793, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 11:42:52'),
(794, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:01:39'),
(795, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:09:17'),
(796, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:34:31'),
(797, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 12:49:17'),
(798, 1, 'admin', 'admin', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 13:51:49'),
(799, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:04:19'),
(800, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:04:35'),
(801, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 41, 'Started procedure for patient: Kurt Jay Trinidad Maligmat', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:07:15'),
(802, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:09:37'),
(803, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:11:14'),
(804, NULL, 'bosszata20@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: bosszata20@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:11:24'),
(805, NULL, 'bosszata20@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: bosszata20@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:11:35'),
(806, NULL, 'bosszata20@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - Invalid password for user: bosszata20@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:11:42'),
(807, 21, 'bosszata20@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:11:52'),
(808, 21, 'bosszata20@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:12:01'),
(809, 1, 'admin', 'admin', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:15:12'),
(810, 29, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:27:46'),
(811, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:28:07'),
(812, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:32:57'),
(813, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:48:31'),
(814, 30, 'unknown', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 14:52:32'),
(815, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 53, 'Viewed payment details for patient ID: 53', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:05:11'),
(816, 31, 'chimp.artfiles@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:05:54'),
(817, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:13:30'),
(818, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 42, 'Started procedure for patient: Karina Jasie Villanueva', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:13:37'),
(819, 31, 'chimp.artfiles@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:25:54'),
(820, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:07:57'),
(821, 28, 'malazarteshane24@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:09:05'),
(822, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:16:45'),
(823, 28, 'malazarteshane24@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:19:14'),
(824, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:19:43'),
(825, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:20:42'),
(826, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 50, 'Viewed payment details for patient ID: 50', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:27:42'),
(827, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 50, 'Viewed payment details for patient ID: 50', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:27:54'),
(828, NULL, 'mariaalonasabanal@gmail.com', NULL, 'users', 'failed_login', 'failed', 'users', NULL, 'Failed login attempt - User not found: mariaalonasabanal@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:29:39'),
(829, 28, 'malazarteshane24@gmail.com', 'user', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:30:06'),
(830, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 46, 'Started procedure for patient: Alona Mari  Sabanal', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:34:07'),
(831, 28, 'malazarteshane24@gmail.com', 'user', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:43:57'),
(832, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:44:03'),
(833, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:44:14'),
(834, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 47, 'Started procedure for patient: buaaya  john', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 16:45:03'),
(835, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:05:29'),
(836, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:06:24'),
(837, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:10:16'),
(838, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:10:29'),
(839, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 51, 'Started procedure for patient: asdasd asd asd', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:22:12'),
(840, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 52, 'Patient cancelled: asdasd asd asd', 'waiting', 'cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:23:33'),
(841, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 53, 'Patient cancelled: asdasd asd asd', 'waiting', 'cancelled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 17:23:35'),
(842, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:09:40'),
(843, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:15:08'),
(844, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:20:58'),
(845, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:33:53'),
(846, NULL, NULL, NULL, 'queue', 'status_change', 'success', 'queue', 54, 'Started procedure for patient: lamp  Art', 'waiting', 'in_procedure', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:34:39'),
(847, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 03:44:55'),
(848, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 11:11:19'),
(849, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 11:43:57'),
(850, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 11:45:21'),
(851, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 11:58:46'),
(852, 4, 'dentist', 'dentist', 'billing', 'read', 'success', 'patients', 59, 'Viewed payment details for patient ID: 59', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 12:18:45'),
(853, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 12:22:23'),
(854, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 12:41:04'),
(855, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 12:51:56'),
(856, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 12:54:18'),
(857, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 60, 'Viewed payment details for patient ID: 60', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:13:44'),
(858, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:16:24'),
(859, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:25:58'),
(860, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:33:37'),
(861, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:35:59'),
(862, 2, 'staff', 'staff', 'billing', 'read', 'success', 'patients', 59, 'Viewed payment details for patient ID: 59', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 13:39:12'),
(863, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 14:12:25'),
(864, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 14:41:05'),
(865, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 14:42:18'),
(866, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 14:52:07'),
(867, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:00:17'),
(868, 2, 'staff', 'staff', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:24:28'),
(869, 2, 'staff', 'staff', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:26:59'),
(870, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:38:54'),
(871, 4, 'dentist', 'dentist', 'users', 'login', 'success', NULL, NULL, 'Successful login from web browser', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:52:13'),
(872, 2, 'staff', 'staff', 'billing', 'read', 'success', 'billing', 39, 'Viewed billing record: INV-039', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:54:00'),
(873, 2, 'staff', 'staff', 'billing', 'payment', 'success', 'billing', 39, 'Payment recorded: ₱500.00 for patient: Chimp Art (INV-039)', 'unpaid', 'paid', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:54:05'),
(874, 2, 'staff', 'staff', 'payments', 'create', 'success', 'payments', 16, 'Created payment record: ₱500.00 for patient: Chimp Art', NULL, '₱500.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 15:54:05'),
(875, 4, 'dentist', 'dentist', 'users', 'logout', 'success', NULL, NULL, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-05 16:06:31');

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
  `payment_status` enum('Paid','Partial','Unpaid','Overdue') DEFAULT 'Unpaid',
  `billing_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `printed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `treatment_id`, `appointment_id`, `total_amount`, `paid_amount`, `balance`, `payment_status`, `billing_date`, `due_date`, `printed_at`, `notes`, `created_at`, `updated_at`) VALUES
(13, 16, NULL, NULL, 900.00, 900.00, 0.00, 'Paid', '2026-02-16', '2026-02-16', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-02-16 15:07:18', '2026-02-16 15:41:29'),
(16, 23, NULL, NULL, 500.00, 500.00, 0.00, 'Paid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Denture Adjustment', '2026-07-28 01:15:11', '2026-07-28 08:58:42'),
(17, 25, NULL, NULL, 500.00, 500.00, 0.00, 'Paid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Periapical Xray', '2026-07-28 03:24:54', '2026-07-28 08:59:14'),
(18, 28, NULL, NULL, 800.00, 800.00, 0.00, 'Paid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-07-28 03:43:23', '2026-07-28 04:50:46'),
(19, 29, NULL, NULL, 500.00, 500.00, 0.00, 'Paid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Consultation', '2026-07-28 05:19:22', '2026-07-28 07:17:21'),
(21, 36, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Consultation', '2026-07-28 15:28:28', '2026-07-28 15:28:28'),
(22, 37, NULL, NULL, 800.00, 0.00, 800.00, 'Unpaid', '2026-07-28', '2026-07-28', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-07-28 15:36:17', '2026-07-28 15:36:17'),
(23, 38, NULL, NULL, 2000.00, 0.00, 2000.00, 'Unpaid', '2026-07-29', '2026-07-29', NULL, 'Initial billing from admission - Services: Crowns (Jacket)', '2026-07-29 12:00:04', '2026-07-29 12:00:04'),
(24, 39, NULL, NULL, 800.00, 0.00, 800.00, 'Unpaid', '2026-07-29', '2026-07-29', NULL, 'Initial billing from admission - Services: Tooth Extraction (Ibot)', '2026-07-29 12:37:49', '2026-07-29 12:37:49'),
(25, 42, NULL, NULL, 5000.00, 0.00, 5000.00, 'Unpaid', '2026-07-30', '2026-07-30', NULL, 'Initial billing from admission - Services: Fixed Bridge', '2026-07-29 17:04:46', '2026-07-29 17:04:46'),
(26, 46, NULL, NULL, 5800.00, 0.00, 5800.00, 'Unpaid', '2026-07-30', '2026-07-30', NULL, 'Initial billing from admission - Services: Removable Dentures, Tooth Extraction (Ibot)', '2026-07-30 03:48:13', '2026-07-30 03:48:13'),
(27, 47, NULL, NULL, 500.00, 500.00, 0.00, 'Paid', '2026-07-30', '2026-07-30', NULL, 'Initial billing from admission - Services: Consultation', '2026-07-30 04:07:10', '2026-07-30 04:12:11'),
(28, 54, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-04', '2026-08-04', NULL, 'Initial billing from admission - Services: Consultation', '2026-08-04 15:15:44', '2026-08-04 15:15:44'),
(29, 55, NULL, NULL, 800.00, 0.00, 800.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-08-04 16:44:52', '2026-08-04 16:44:52'),
(30, 56, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Periapical Xray', '2026-08-04 16:53:29', '2026-08-04 16:53:29'),
(31, 57, NULL, NULL, 800.00, 0.00, 800.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-08-04 17:11:05', '2026-08-04 17:11:05'),
(32, 58, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Periapical Xray', '2026-08-04 17:15:59', '2026-08-04 17:15:59'),
(33, 58, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Billing from quick session - Consultation', '2026-08-04 17:21:29', '2026-08-04 17:21:29'),
(34, 58, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Billing from quick session - Consultation', '2026-08-04 17:21:42', '2026-08-04 17:21:42'),
(35, 58, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Billing from quick session - Consultation', '2026-08-04 17:21:51', '2026-08-04 17:21:51'),
(36, 59, NULL, NULL, 800.00, 0.00, 800.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Teeth Cleaning', '2026-08-05 03:33:11', '2026-08-05 03:33:11'),
(37, 60, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Consultation', '2026-08-05 13:12:49', '2026-08-05 13:12:49'),
(38, 46, NULL, NULL, 500.00, 0.00, 500.00, 'Unpaid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Consultation', '2026-08-05 15:27:42', '2026-08-05 15:27:42'),
(39, 39, NULL, NULL, 500.00, 500.00, 0.00, 'Paid', '2026-08-05', '2026-08-05', NULL, 'Initial billing from admission - Services: Consultation', '2026-08-05 15:51:54', '2026-08-05 15:54:05');

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing_status`
--

CREATE TABLE `chat_typing_status` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `sender_type` enum('patient','staff') NOT NULL,
  `is_typing` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `status` enum('in_progress','completed') DEFAULT 'in_progress',
  `periodontal_screening` varchar(50) DEFAULT NULL,
  `occlusion_class_molar` tinyint(1) DEFAULT 0,
  `occlusion_overjet` tinyint(1) DEFAULT 0,
  `occlusion_overbite` tinyint(1) DEFAULT 0,
  `occlusion_midline_deviation` tinyint(1) DEFAULT 0,
  `occlusion_crossbite` tinyint(1) DEFAULT 0,
  `appliances_orthodontic` tinyint(1) DEFAULT 0,
  `appliances_skyplate` tinyint(1) DEFAULT 0,
  `appliances_others` tinyint(1) DEFAULT 0,
  `appliances_others_text` varchar(255) DEFAULT NULL,
  `tmd_clenching` tinyint(1) DEFAULT 0,
  `tmd_clicking` tinyint(1) DEFAULT 0,
  `tmd_trismus` tinyint(1) DEFAULT 0,
  `tmd_muscle_spasm` tinyint(1) DEFAULT 0,
  `teeth_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`teeth_data`)),
  `consultation_notes` text DEFAULT NULL,
  `dental_medical_history_notes` text DEFAULT NULL,
  `prescription_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`prescription_data`)),
  `consent_provider` varchar(255) DEFAULT NULL,
  `consent_patient_address` text DEFAULT NULL,
  `consent_procedure` text DEFAULT NULL,
  `consent_by_guardian` tinyint(1) DEFAULT 0,
  `consent_guardian_name` varchar(255) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_date` date DEFAULT NULL,
  `consent_place` varchar(255) DEFAULT NULL,
  `consent_patient_signature` mediumtext DEFAULT NULL,
  `consent_doctor_signature` mediumtext DEFAULT NULL,
  `consent_witness_signature` mediumtext DEFAULT NULL,
  `consent_patient_sign_name` varchar(255) DEFAULT NULL,
  `consent_witness_sign_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `queue_id`, `patient_id`, `dentist_id`, `status`, `periodontal_screening`, `occlusion_class_molar`, `occlusion_overjet`, `occlusion_overbite`, `occlusion_midline_deviation`, `occlusion_crossbite`, `appliances_orthodontic`, `appliances_skyplate`, `appliances_others`, `appliances_others_text`, `tmd_clenching`, `tmd_clicking`, `tmd_trismus`, `tmd_muscle_spasm`, `teeth_data`, `consultation_notes`, `dental_medical_history_notes`, `prescription_data`, `consent_provider`, `consent_patient_address`, `consent_procedure`, `consent_by_guardian`, `consent_guardian_name`, `consent_given`, `consent_date`, `consent_place`, `consent_patient_signature`, `consent_doctor_signature`, `consent_witness_signature`, `consent_patient_sign_name`, `consent_witness_sign_name`, `created_at`, `updated_at`) VALUES
(1, 19, 23, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"},{\"tooth\":18,\"surface\":\"M\",\"type\":\"treatment\",\"code\":\"Am\",\"label\":\"Amalgam Filling\"},{\"tooth\":18,\"surface\":\"D\",\"type\":\"treatment\",\"code\":\"Am\",\"label\":\"Amalgam Filling\"},{\"tooth\":18,\"surface\":\"B\",\"type\":\"treatment\",\"code\":\"P\",\"label\":\"Pontic\"},{\"tooth\":18,\"surface\":\"L\",\"type\":\"treatment\",\"code\":\"S\",\"label\":\"Sealants\"}]', 'sample text', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:18:10', '2026-07-28 03:20:47'),
(2, 20, 25, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":17,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', 'naay daot\nneed xray also', NULL, '[{\"name\":\"tambal\",\"dosage\":\"wala\",\"frequency\":\"wala pud\",\"duration\":\"hmm\",\"instructions\":\"search google\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:25:22', '2026-07-28 03:42:38'),
(3, 21, 28, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"},{\"tooth\":17,\"surface\":null,\"type\":\"condition\",\"code\":\"M\",\"label\":\"Missing due to caries\"}]', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:45:05', '2026-07-28 04:46:16'),
(4, 22, 29, 4, 'completed', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, '[{\"name\":\"med\",\"dosage\":\"dos\",\"frequency\":\"idk\",\"duration\":\"23\",\"instructions\":\"nothing hehe\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 05:20:31', '2026-07-28 07:17:07'),
(5, 24, 31, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":null,\"type\":\"condition\",\"code\":\"Rf\",\"label\":\"Root Fragment\"},{\"tooth\":17,\"surface\":\"D\",\"type\":\"treatment\",\"code\":\"S\",\"label\":\"Sealants\"}]', 'notes ni sya', 'sampple textdr', '[{\"name\":\"tambal\",\"dosage\":\"23\",\"frequency\":\"23\",\"duration\":\"hehe\",\"instructions\":\"hehehe \"}]', '', '', 'Teeth Cleaning', 0, '', 1, '2026-07-28', 'RF Dental Clinic', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmcAAAD6CAYAAADk1eClAAAQAElEQVR4AeydC5wbVfXHz5nslrYUFKQ8ymZSoNAkrRUoUjZJcRFB0L+IYFFEQBEFeQmI/hWUh6L8wRcKKiAoD0VUEFQEAYGFJtkiFBDaZAul3U2W8ihvKH3sZs7/3GyyTdvtazebzEx+87k3986dmXvP+d508+u9M3cswgYCIAACIAACIAACIOAaA', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmcAAAD6CAYAAADk1eClAAAQAElEQVR4AeydCZwcRdn/n6dnNxdE7ntndsOR7ExC5BBCZiYYBFFREA+8QV88UVTwwgMP1Pevolyir3jfJyqCIKKAIZmZDWAEQzI9CSHs9GwSwn2FbHZ3uv6/mt0Nm4vsbubomfn153m6qrurq576Vu/0s1Xd1Y5wIQESIAESIAESIAESCAwBOmeBa', NULL, '', '', '2026-07-28 07:17:29', '2026-07-28 07:29:13'),
(6, 25, 31, 4, 'completed', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 08:05:08', '2026-07-28 08:15:18'),
(7, 26, 24, 4, 'completed', '', 0, 0, 1, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"},{\"tooth\":17,\"surface\":null,\"type\":\"condition\",\"code\":\"M\",\"label\":\"Missing due to caries\"}]', 'notes', NULL, '[{\"name\":\"biogesic\",\"dosage\":\"20mg\",\"frequency\":\"3x a day\",\"duration\":\"7 days\",\"instructions\":\"churva\"}]', '', '', 'Removable Dentures', 0, '', 1, '2026-07-28', 'RF Dental Clinic', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmcAAAD6CAYAAADk1eClAAAQAElEQVR4AeydC3wcVfXHz5lNaEvLUwGh2d2UR7u7KQURKNndQkAsoCioyEvwgQh/QEH5A4ooig8UEPgDggqovEFBwQfyEilkd8PDyjO7WyglmYS3llfpK8mc/7mb3TRNkzaP3WRm9jefe+beuTNz55zvTbK/3Ds7YxEWEAABEAABEAABEAAB1xCAO', NULL, NULL, '', '', '2026-07-28 08:10:52', '2026-07-28 08:57:08'),
(8, 28, 32, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 08:16:07', '2026-07-28 08:16:07'),
(9, 27, 26, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 08:57:17', '2026-07-28 08:57:17'),
(10, 29, 31, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 08:57:24', '2026-07-28 08:57:24'),
(11, 33, 38, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, 'asdasdasd', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 12:00:20', '2026-07-29 17:42:27'),
(12, 34, 39, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 12:38:00', '2026-07-29 12:38:00'),
(13, 35, 42, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-29 17:04:59', '2026-07-29 17:04:59'),
(14, 36, 45, 4, 'in_progress', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":17,\"surface\":\"D\",\"type\":\"treatment\",\"code\":\"S\",\"label\":\"Sealants\"}]', 'ayooo sa ulo', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 03:36:18', '2026-07-30 03:38:55'),
(15, 37, 46, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"M\",\"type\":\"treatment\",\"code\":\"S\",\"label\":\"Sealants\"},{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 04:07:54', '2026-07-30 04:09:27'),
(16, 38, 47, 4, 'completed', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 04:07:59', '2026-07-30 04:11:05'),
(17, 40, 40, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-03 13:18:46', '2026-08-03 13:18:46'),
(18, 41, 40, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', 'sample rani nga note', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 14:07:15', '2026-08-04 14:11:03'),
(19, 42, 51, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, 'asdadasda', NULL, NULL, '', '', 'Consultation', 0, '', 1, '2026-08-04', 'RF Dental Clinic', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASwAAACWCAYAAABkW7XSAAAQAElEQVR4AeydBYBV1fPH5/ETsH4KKCXd3bm0CNLd3d2wwNKd0kh3d3d3NwgSEoKKKKKof38qoP/7GbjPt+tSy8aLs3DuPbdOzL1vzpyZ78yJ9Lf5MxQwFDAU8BAKRBLzZyhgKGAo4CEUMAzLQ16UaaahgKGAiGFYPvQVmK4aCng6BQzD8vQ3aNpvK', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASwAAACWCAYAAABkW7XSAAAQAElEQVR4AexdB1gVxxb+URMxiYkxeYlJjF2xdxR7iYUoYu8gVkSwoKKCoohd7B079q7YNfaOXWONKT5bxBJLfMYaffuP2RtAUJDLbRw+Znfulpkz/+z+O3PmzJkUL+VPEBAEBAErQSAF5E8QEAQEAStBQAjLSipKxBQEBAFACCsZPQVSVEHA2hEQw', NULL, '', '', '2026-08-04 15:13:38', '2026-08-04 15:56:23'),
(20, 44, 54, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 15:16:04', '2026-08-04 15:24:19'),
(21, 43, 53, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 15:16:06', '2026-08-04 15:16:06'),
(22, 45, 50, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', 'daot ', 'nope', '[{\"name\":\"biogesic\",\"dosage\":\"23\",\"frequency\":\"2x a day\",\"duration\":\"7 days\",\"instructions\":\"after meal\"}]', '', '', 'Consultation', 0, '', 1, '2026-08-04', 'RF Dental Clinic', 'data:image/png;base64,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', 'data:image/png;base64,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', NULL, '', '', '2026-08-04 16:09:46', '2026-08-04 16:18:44'),
(23, 46, 50, 4, 'completed', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, '[{\"name\":\"dasd\",\"dosage\":\"asd2323\",\"frequency\":\"asd\",\"duration\":\"asd\",\"instructions\":\"asd\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 16:34:07', '2026-08-04 16:38:10'),
(24, 47, 55, 4, 'completed', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, '[{\"name\":\"biotgesic\",\"dosage\":\"23\",\"frequency\":\"23231\",\"duration\":\"23\",\"instructions\":\"12321 ok\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 16:45:04', '2026-08-04 16:45:59');
INSERT INTO `consultations` (`id`, `queue_id`, `patient_id`, `dentist_id`, `status`, `periodontal_screening`, `occlusion_class_molar`, `occlusion_overjet`, `occlusion_overbite`, `occlusion_midline_deviation`, `occlusion_crossbite`, `appliances_orthodontic`, `appliances_skyplate`, `appliances_others`, `appliances_others_text`, `tmd_clenching`, `tmd_clicking`, `tmd_trismus`, `tmd_muscle_spasm`, `teeth_data`, `consultation_notes`, `dental_medical_history_notes`, `prescription_data`, `consent_provider`, `consent_patient_address`, `consent_procedure`, `consent_by_guardian`, `consent_guardian_name`, `consent_given`, `consent_date`, `consent_place`, `consent_patient_signature`, `consent_doctor_signature`, `consent_witness_signature`, `consent_patient_sign_name`, `consent_witness_sign_name`, `created_at`, `updated_at`) VALUES
(25, 48, 56, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', 'sdasd', NULL, '[{\"name\":\"asda\",\"dosage\":\"asd\",\"frequency\":\"23\",\"duration\":\"2323\",\"instructions\":\"23\"}]', '', '', 'Periapical Xray', 0, '', 1, '2026-08-04', 'RF Dental Clinic', 'data:image/png;base64,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', 'data:image/png;base64,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', NULL, '', '', '2026-08-04 16:53:48', '2026-08-04 16:55:11'),
(26, 49, 57, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":null,\"type\":\"treatment\",\"code\":\"Rm\",\"label\":\"Removable Denture\"},{\"tooth\":17,\"surface\":null,\"type\":\"treatment\",\"code\":\"Rm\",\"label\":\"Removable Denture\"},{\"tooth\":16,\"surface\":null,\"type\":\"treatment\",\"code\":\"Rm\",\"label\":\"Removable Denture\"},{\"tooth\":15,\"surface\":null,\"type\":\"treatment\",\"code\":\"Rm\",\"label\":\"Removable Denture\"}]', 'dadwqdq', 'ad', '[{\"name\":\"asdasd hehe\",\"dosage\":\"ad23\",\"frequency\":\"23\",\"duration\":\"23\",\"instructions\":\"233\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 17:11:15', '2026-08-04 17:12:47'),
(27, 50, 58, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"treatment\",\"code\":\"In\",\"label\":\"Inlay\"}]', NULL, NULL, '[]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 17:16:03', '2026-08-04 17:16:32'),
(28, 51, 58, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"M\",\"type\":\"treatment\",\"code\":\"S\",\"label\":\"Sealants\"}]', 'HEHE', NULL, '[{\"name\":\"HAHAH\",\"dosage\":\"HA\",\"frequency\":\"HA\",\"duration\":\"HA\",\"instructions\":\"HA\"}]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-04 17:22:12', '2026-08-04 17:22:56'),
(29, 54, 59, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 'asda', 'asdasdasd', 'Teeth Cleaning', 0, '', 1, '2026-08-05', 'RF Dental Clinic', 'data:image/png;base64,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', 'signed', NULL, '', '', '2026-08-05 03:34:39', '2026-08-05 14:21:30'),
(30, 56, 41, 4, 'in_progress', 'Gingivitis', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":46,\"surface\":null,\"type\":\"surgery\",\"code\":\"Xc\",\"label\":\"Extraction due to caries\"}]', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 12:55:10', '2026-08-05 13:51:46'),
(31, 55, 49, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, '[{\"name\":\"asd\",\"dosage\":\"asd\",\"frequency\":\"asd\",\"duration\":\"asd\",\"instructions\":\"asd\"}]', '', '', 'Consultation', 0, '', 1, '2026-08-05', 'RF Dental Clinic', 'signed', 'signed', NULL, '', '', '2026-08-05 13:41:12', '2026-08-05 14:24:10'),
(32, 57, 60, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 13:41:13', '2026-08-05 13:41:13'),
(33, 59, 46, 4, 'in_progress', NULL, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 15:28:12', '2026-08-05 15:28:12'),
(34, 60, 39, 4, 'completed', '', 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, '[{\"tooth\":18,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"},{\"tooth\":11,\"surface\":\"O\",\"type\":\"pathology\",\"code\":\"D\",\"label\":\"Decayed\"}]', 'HAHAHAAHH', 'MEOW', '[]', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 15:52:21', '2026-08-05 15:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `content_images`
--

CREATE TABLE `content_images` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `content_images`
--

INSERT INTO `content_images` (`id`, `file_name`, `original_name`, `file_path`, `file_type`, `file_size`, `section`, `uploaded_by`, `created_at`) VALUES
(1, 'logo_20260514_101320_89245b67.png', 'profile.png', 'assets/images/content/logo_20260514_101320_89245b67.png', 'image/png', 595177, 'logo', 1, '2026-05-14 00:13:20'),
(2, 'logo_20260514_101434_4bce1816.png', 'Logo.png', 'assets/images/content/logo_20260514_101434_4bce1816.png', 'image/png', 49982, 'logo', 1, '2026-05-14 00:14:34'),
(3, 'logo_20260514_101506_27cbec71.png', 'profile.png', 'assets/images/content/logo_20260514_101506_27cbec71.png', 'image/png', 595177, 'logo', 1, '2026-05-14 00:15:06'),
(4, 'logo_20260514_102953_0d4e46e9.png', 'Logo.png', 'assets/images/content/logo_20260514_102953_0d4e46e9.png', 'image/png', 49982, 'logo', 1, '2026-05-14 00:29:53'),
(5, 'services_20260515_111034_c50fea38.jpg', 'pic4.jpg', 'assets/images/content/services_20260515_111034_c50fea38.jpg', 'image/jpeg', 36889, 'services', 1, '2026-05-15 01:10:34'),
(6, 'services_20260515_111046_b445dc6f.jpg', 'pic5.jpg', 'assets/images/content/services_20260515_111046_b445dc6f.jpg', 'image/jpeg', 51802, 'services', 1, '2026-05-15 01:10:46'),
(7, 'services_20260515_111058_a786afee.jpg', 'pic6.jpg', 'assets/images/content/services_20260515_111058_a786afee.jpg', 'image/jpeg', 37804, 'services', 1, '2026-05-15 01:10:58'),
(8, 'services_20260515_111603_b54e2378.jpg', 'pic4.jpg', 'assets/images/content/services_20260515_111603_b54e2378.jpg', 'image/jpeg', 36889, 'services', 1, '2026-05-15 01:16:03'),
(9, 'services_20260515_111623_e5c02eec.jpg', 'pic5.jpg', 'assets/images/content/services_20260515_111623_e5c02eec.jpg', 'image/jpeg', 51802, 'services', 1, '2026-05-15 01:16:23'),
(10, 'services_20260515_111707_3838b040.jpg', 'pic6.jpg', 'assets/images/content/services_20260515_111707_3838b040.jpg', 'image/jpeg', 37804, 'services', 1, '2026-05-15 01:17:07'),
(11, 'services_20260515_111714_3fc30d14.jpg', 'pic7.jpg', 'assets/images/content/services_20260515_111714_3fc30d14.jpg', 'image/jpeg', 41072, 'services', 1, '2026-05-15 01:17:14'),
(12, 'services_20260515_111720_d4bf4c12.jpg', 'pic8.jpg', 'assets/images/content/services_20260515_111720_d4bf4c12.jpg', 'image/jpeg', 78170, 'services', 1, '2026-05-15 01:17:20'),
(13, 'services_20260515_111727_d30f52c7.jpg', 'pic9.jpg', 'assets/images/content/services_20260515_111727_d30f52c7.jpg', 'image/jpeg', 388244, 'services', 1, '2026-05-15 01:17:27'),
(14, 'services_20260515_111745_a4cfe745.jpg', 'pic10.jpg', 'assets/images/content/services_20260515_111745_a4cfe745.jpg', 'image/jpeg', 186602, 'services', 1, '2026-05-15 01:17:45'),
(15, 'services_20260515_111751_26ead3db.jpg', 'pic11.jpg', 'assets/images/content/services_20260515_111751_26ead3db.jpg', 'image/jpeg', 62435, 'services', 1, '2026-05-15 01:17:51'),
(16, 'services_20260515_111758_1c0e2e0a.jpg', 'pic3.jpg', 'assets/images/content/services_20260515_111758_1c0e2e0a.jpg', 'image/jpeg', 60782, 'services', 1, '2026-05-15 01:17:58'),
(17, 'services_20260515_111811_2519a038.png', 'pic12.png', 'assets/images/content/services_20260515_111811_2519a038.png', 'image/png', 322497, 'services', 1, '2026-05-15 01:18:11'),
(18, 'services_20260515_111819_316499a9.jpg', 'pic1.jpg', 'assets/images/content/services_20260515_111819_316499a9.jpg', 'image/jpeg', 57390, 'services', 1, '2026-05-15 01:18:19'),
(19, 'services_20260515_111839_2add5061.jpg', 'pic2.jpg', 'assets/images/content/services_20260515_111839_2add5061.jpg', 'image/jpeg', 99985, 'services', 1, '2026-05-15 01:18:39'),
(20, 'services_20260515_112536_c96be267.jpg', 'pic4.jpg', 'assets/images/content/services_20260515_112536_c96be267.jpg', 'image/jpeg', 36889, 'services', 1, '2026-05-15 01:25:36'),
(21, 'services_20260515_112633_ee77c6f5.jpg', 'pic4.jpg', 'assets/images/content/services_20260515_112633_ee77c6f5.jpg', 'image/jpeg', 36889, 'services', 1, '2026-05-15 01:26:33'),
(22, 'logo_20260515_112641_ebe9f706.png', 'profile.png', 'assets/images/content/logo_20260515_112641_ebe9f706.png', 'image/png', 595177, 'logo', 1, '2026-05-15 01:26:41'),
(23, 'logo_20260515_112725_72864ec4.png', 'Logo.png', 'assets/images/content/logo_20260515_112725_72864ec4.png', 'image/png', 49982, 'logo', 1, '2026-05-15 01:27:25'),
(24, 'hero_20260516_053157_2a0d56d8.png', 'Logo.png', 'assets/images/content/hero_20260516_053157_2a0d56d8.png', 'image/png', 49982, 'hero', 1, '2026-05-15 19:31:57'),
(25, 'hero_20260516_053250_6dde5856.png', 'Dentists.png', 'assets/images/content/hero_20260516_053250_6dde5856.png', 'image/png', 1318130, 'hero', 1, '2026-05-15 19:32:50'),
(26, 'services_20260713_123031_d10ececf.jpg', 'pic4.jpg', 'assets/images/content/services_20260713_123031_d10ececf.jpg', 'image/jpeg', 36889, 'services', 1, '2026-07-13 10:30:31'),
(27, 'services_20260713_123055_bd94bcec.jpg', 'pic5.jpg', 'assets/images/content/services_20260713_123055_bd94bcec.jpg', 'image/jpeg', 51802, 'services', 1, '2026-07-13 10:30:55'),
(28, 'services_20260713_123117_4ee79a67.jpg', 'pic6.jpg', 'assets/images/content/services_20260713_123117_4ee79a67.jpg', 'image/jpeg', 37804, 'services', 1, '2026-07-13 10:31:17'),
(29, 'services_20260713_123124_3b6bbb0c.jpg', 'pic7.jpg', 'assets/images/content/services_20260713_123124_3b6bbb0c.jpg', 'image/jpeg', 41072, 'services', 1, '2026-07-13 10:31:24'),
(30, 'services_20260713_123134_813999e4.jpg', 'pic10.jpg', 'assets/images/content/services_20260713_123134_813999e4.jpg', 'image/jpeg', 186602, 'services', 1, '2026-07-13 10:31:34'),
(31, 'services_20260713_123143_33f12a7c.jpg', 'pic9.jpg', 'assets/images/content/services_20260713_123143_33f12a7c.jpg', 'image/jpeg', 388244, 'services', 1, '2026-07-13 10:31:43'),
(32, 'services_20260713_123204_a64017ed.jpg', 'pic10.jpg', 'assets/images/content/services_20260713_123204_a64017ed.jpg', 'image/jpeg', 186602, 'services', 1, '2026-07-13 10:32:04'),
(33, 'services_20260713_123208_e62dfecc.jpg', 'pic8.jpg', 'assets/images/content/services_20260713_123208_e62dfecc.jpg', 'image/jpeg', 78170, 'services', 1, '2026-07-13 10:32:08'),
(34, 'services_20260713_123215_d5163843.jpg', 'pic11.jpg', 'assets/images/content/services_20260713_123215_d5163843.jpg', 'image/jpeg', 62435, 'services', 1, '2026-07-13 10:32:15'),
(35, 'services_20260713_123228_72c5dcb2.jpg', 'pic3.jpg', 'assets/images/content/services_20260713_123228_72c5dcb2.jpg', 'image/jpeg', 60782, 'services', 1, '2026-07-13 10:32:28'),
(36, 'services_20260713_123244_8e162ea5.png', 'pic12.png', 'assets/images/content/services_20260713_123244_8e162ea5.png', 'image/png', 322497, 'services', 1, '2026-07-13 10:32:44'),
(37, 'services_20260713_123250_b03a4ddc.jpg', 'pic1.jpg', 'assets/images/content/services_20260713_123250_b03a4ddc.jpg', 'image/jpeg', 57390, 'services', 1, '2026-07-13 10:32:50'),
(38, 'services_20260713_123304_ed9abecb.jpg', 'pic2.jpg', 'assets/images/content/services_20260713_123304_ed9abecb.jpg', 'image/jpeg', 99985, 'services', 1, '2026-07-13 10:33:04');

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

--
-- Dumping data for table `dental_history`
--

INSERT INTO `dental_history` (`id`, `patient_id`, `previous_dentist`, `last_visit_date`, `reason_last_visit`, `previous_treatments`, `current_complaints`, `updated_at`) VALUES
(1, 59, 'Dr. Floralyn Gabz', '2026-02-02', 'cleaning', '', NULL, '2026-08-05 03:33:11'),
(2, 60, 'Dr. dentist2 dent', '0000-00-00', '', '', NULL, '2026-08-05 13:12:49');

-- --------------------------------------------------------

--
-- Table structure for table `dentist_unavailable`
--

CREATE TABLE `dentist_unavailable` (
  `id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `unavailable_date` date NOT NULL,
  `is_full_day` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `concern` text DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `pain_level` varchar(30) DEFAULT NULL,
  `urgency` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
  `confirmed_date` date DEFAULT NULL,
  `confirmed_time` time DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Unread',
  `source` varchar(50) NOT NULL DEFAULT 'Patient Portal',
  `fb_sender_id` varchar(100) DEFAULT NULL,
  `reply_token` varchar(64) DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `assigned_dentist` varchar(150) DEFAULT NULL,
  `inquiry_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `patient_id`, `first_name`, `middle_name`, `last_name`, `email`, `concern`, `service_id`, `preferred_date`, `preferred_time`, `pain_level`, `urgency`, `confirmed_date`, `confirmed_time`, `status`, `source`, `fb_sender_id`, `reply_token`, `assigned_staff_id`, `assigned_dentist`, `inquiry_message`, `created_at`, `phone`, `source_id`, `status_id`, `notes`, `is_archived`, `deleted_at`) VALUES
(1, NULL, 'Simene', NULL, 'Angelo', 'angelosimene14@gmail.com', 'Hi, i want to know if may discount ba ang senior ? thanks', NULL, NULL, NULL, NULL, 'Low', NULL, NULL, 'Read', 'Website', NULL, 'a654f917a2435412fa838678a1ac1bdeeb3fbf766b4aa3f7bc36a840bbbf11d3', 2, NULL, NULL, '2026-07-14 14:16:26', '0921455532', NULL, NULL, '\n[Staff replied on 2026-07-14 16:18:30]\nwe are looking forward po for the senior discount this time. but for now we are offering a case to case prices for the services . thank you', 0, NULL),
(2, 20, 'Maria', 'Alona A', 'Sabanal', 'mara.sabanal.coc@phinmaed.com', 'dasd', NULL, NULL, NULL, NULL, 'Low', NULL, NULL, 'Read', 'Patient Portal', NULL, NULL, 2, NULL, 'dasd', '2026-07-20 04:44:09', '09214546665', NULL, 1, '\n[Staff replied on 2026-07-20 06:44:25]\nhi\n[Staff replied on 2026-07-20 09:18:47]\nweeeeeeeeeee\n[Staff replied on 2026-07-20 09:19:22]\n6265\\', 0, NULL),
(3, 16, 'Juan', NULL, 'Miguel', '', 'Test inquiry from CLI', NULL, NULL, NULL, NULL, 'Low', NULL, NULL, 'Replied', 'Patient Portal', NULL, NULL, 2, NULL, 'Test inquiry from CLI', '2026-07-20 04:44:34', '09214168241', NULL, 1, '\n[Staff replied on 2026-07-20 08:34:03]\nxzxz', 0, NULL),
(4, NULL, 'Baby', 'Jean Galendez', 'Jumawid', 'babyjeanjumawid05@gmail.com', 'hello', NULL, NULL, NULL, NULL, 'Low', NULL, NULL, 'Read', 'Patient Portal', NULL, NULL, 2, NULL, 'hello', '2026-07-28 06:26:00', '09675288657', NULL, 1, '\n[Staff replied on 2026-07-28 08:26:23]\nAyoo', 0, NULL),
(5, NULL, 'Boss', 'Lorenzo Asucion Villar', 'Zata', 'bovi.zata.coc@phinmaed.com', 'hehe', NULL, NULL, NULL, NULL, 'Low', NULL, NULL, 'Unread', 'Website', NULL, 'df4b4b9cb51333b308fcc0181299d4897fc46454c092bbd5a437a319b68fc079', NULL, NULL, NULL, '2026-08-05 03:28:49', '09145465464', NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_replies`
--

CREATE TABLE `inquiry_replies` (
  `id` int(11) NOT NULL,
  `inquiry_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('staff','guest','patient') NOT NULL DEFAULT 'guest',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiry_replies`
--

INSERT INTO `inquiry_replies` (`id`, `inquiry_id`, `message`, `sender_type`, `created_at`) VALUES
(1, 1, 'we are looking forward po for the senior discount this time. but for now we are offering a case to case prices for the services . thank you', 'staff', '2026-07-14 14:18:30'),
(2, 1, 'okay po, thanks !', 'guest', '2026-07-14 14:19:17'),
(3, 2, 'hi', 'staff', '2026-07-20 04:44:25'),
(4, 3, 'xzxz', 'staff', '2026-07-20 06:34:03'),
(5, 2, 'ngayu ko gtabang', 'patient', '2026-07-20 06:37:07'),
(6, 2, 'hoy! ngayu klo tabang ba', 'patient', '2026-07-20 07:17:47'),
(7, 2, 'weeeeeeeeeee', 'staff', '2026-07-20 07:18:47'),
(8, 2, '6265\\', 'staff', '2026-07-20 07:19:22'),
(9, 2, 'hey', 'patient', '2026-07-20 07:19:37'),
(10, 4, 'Ayoo', 'staff', '2026-07-28 06:26:23'),
(11, 4, 'yes', 'patient', '2026-07-28 06:26:42');

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_statuses`
--

CREATE TABLE `inquiry_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiry_statuses`
--

INSERT INTO `inquiry_statuses` (`id`, `status_name`) VALUES
(2, 'Read'),
(3, 'Replied'),
(1, 'Unread');

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
  `medical_conditions` text DEFAULT NULL,
  `past_surgeries` text DEFAULT NULL,
  `heart_condition` tinyint(1) DEFAULT 0,
  `diabetes` tinyint(1) DEFAULT 0,
  `bleeding_disorder` tinyint(1) DEFAULT 0,
  `pregnancy` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_medications` text DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `hiv_aids` tinyint(1) DEFAULT 0,
  `hepatitis` tinyint(1) DEFAULT 0,
  `tuberculosis` tinyint(1) DEFAULT 0,
  `asthma` tinyint(1) DEFAULT 0,
  `epilepsy` tinyint(1) DEFAULT 0,
  `std` tinyint(1) DEFAULT 0,
  `stomach_ulcer` tinyint(1) DEFAULT 0,
  `fainting_seizure` tinyint(1) DEFAULT 0,
  `rapid_weight_loss` tinyint(1) DEFAULT 0,
  `rheumatic_fever` tinyint(1) DEFAULT 0,
  `hay_fever` tinyint(1) DEFAULT 0,
  `respiratory_problems` tinyint(1) DEFAULT 0,
  `swollen_ankles` tinyint(1) DEFAULT 0,
  `kidney_disease` tinyint(1) DEFAULT 0,
  `chest_pain` tinyint(1) DEFAULT 0,
  `stroke` tinyint(1) DEFAULT 0,
  `others` text DEFAULT NULL,
  `heart_rate` varchar(20) DEFAULT NULL,
  `other_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `allergies`, `medical_conditions`, `past_surgeries`, `heart_condition`, `diabetes`, `bleeding_disorder`, `pregnancy`, `updated_at`, `current_medications`, `blood_pressure`, `hiv_aids`, `hepatitis`, `tuberculosis`, `asthma`, `epilepsy`, `std`, `stomach_ulcer`, `fainting_seizure`, `rapid_weight_loss`, `rheumatic_fever`, `hay_fever`, `respiratory_problems`, `swollen_ankles`, `kidney_disease`, `chest_pain`, `stroke`, `others`, `heart_rate`, `other_notes`) VALUES
(11, 16, NULL, 'allergies', NULL, 0, 0, 0, 0, '2026-07-16 15:07:17', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(14, 23, '', 'None', '', 1, 0, 0, 0, '2026-07-28 03:19:05', '', '0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', NULL, NULL),
(15, 25, '', 'None', '', 0, 0, 0, 0, '2026-07-28 03:42:05', '', '0', 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', NULL, NULL),
(16, 28, NULL, 'None', NULL, 0, 0, 0, 0, '2026-07-28 03:43:23', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(17, 29, NULL, 'None', NULL, 0, 0, 0, 0, '2026-07-28 05:19:22', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(21, 36, NULL, 'None', NULL, 0, 0, 0, 0, '2026-07-28 15:28:28', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(22, 37, NULL, 'None', NULL, 0, 0, 0, 0, '2026-07-28 15:36:17', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(23, 38, '', 'diabetes', '', 0, 1, 0, 0, '2026-07-29 12:25:06', '', '0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', NULL, NULL),
(24, 39, NULL, 'stroke', NULL, 0, 0, 0, 0, '2026-07-29 12:37:49', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NULL, NULL, NULL),
(25, 42, NULL, 'heart_disease', NULL, 1, 0, 0, 0, '2026-07-29 17:04:46', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(26, 46, NULL, 'rapid_weight_loss, respiratory_problems', NULL, 0, 0, 0, 0, '2026-07-30 03:48:13', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0, 0, NULL, NULL, NULL),
(27, 47, NULL, 'asthma', NULL, 0, 0, 0, 0, '2026-07-30 04:07:10', '', NULL, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(28, 54, NULL, 'None', NULL, 0, 0, 0, 0, '2026-08-04 15:15:44', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(29, 51, '', '', '', 0, 0, 0, 0, '2026-08-04 15:24:03', '', '0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, '', NULL, NULL),
(30, 50, 'mani', '', '', 0, 0, 0, 0, '2026-08-04 16:10:50', '', '0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, '', NULL, NULL),
(31, 55, NULL, 'hay_fever', NULL, 0, 0, 0, 0, '2026-08-04 16:44:52', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(32, 56, NULL, 'diabetes', NULL, 0, 1, 0, 0, '2026-08-04 16:53:29', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(33, 57, NULL, 'chest_pain', NULL, 0, 0, 0, 0, '2026-08-04 17:11:05', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, NULL, NULL, NULL),
(34, 58, NULL, 'None', NULL, 0, 0, 0, 0, '2026-08-04 17:15:59', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(35, 59, NULL, 'pregnancy', NULL, 0, 0, 0, 1, '2026-08-05 03:33:11', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(36, 60, NULL, 'None', NULL, 0, 0, 0, 0, '2026-08-05 13:12:49', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(37, 46, NULL, 'None', NULL, 0, 0, 0, 0, '2026-08-05 15:27:42', '', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL),
(38, 39, '', 'None', '', 0, 0, 0, 0, '2026-08-05 15:52:56', '', '0', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `medicine_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('patient','admin','system') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `auto_reply_for_message_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_by` enum('patient','admin') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `patient_id`, `message`, `sender_type`, `created_at`, `sender_id`, `receiver_id`, `subject`, `auto_reply_for_message_id`, `is_read`, `sent_by`) VALUES
(1, 20, 'hi', 'admin', '2026-07-20 04:44:25', 2, NULL, NULL, NULL, 0, 'admin'),
(2, 16, 'xzxz', 'admin', '2026-07-20 06:34:03', 2, NULL, NULL, NULL, 0, 'admin'),
(3, 20, 'weeeeeeeeeee', 'admin', '2026-07-20 07:18:47', 2, NULL, NULL, NULL, 0, 'admin'),
(4, 20, '6265\\', 'admin', '2026-07-20 07:19:22', 2, NULL, NULL, NULL, 0, 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`, `type`, `action_url`) VALUES
(1, 1, 'New Patient Registration', 'James Ryan Dela cruz has been registered as a new patient', 0, '2026-07-13 11:41:21', 'patient', 'admin_patients.php'),
(2, 2, 'New Patient Registration', 'James Ryan Dela cruz has been registered as a new patient', 0, '2026-07-13 11:41:21', 'patient', 'admin_patients.php'),
(3, 4, 'New Patient Registration', 'James Ryan Dela cruz has been registered as a new patient', 0, '2026-07-13 11:41:21', 'patient', 'dentist_patients.php'),
(4, 1, 'New Patient Registration', 'James Dela cruz has been registered as a new patient', 0, '2026-07-13 11:44:05', 'patient', 'admin_patients.php'),
(5, 2, 'New Patient Registration', 'James Dela cruz has been registered as a new patient', 0, '2026-07-13 11:44:05', 'patient', 'admin_patients.php'),
(6, 4, 'New Patient Registration', 'James Dela cruz has been registered as a new patient', 0, '2026-07-13 11:44:05', 'patient', 'dentist_patients.php'),
(7, 1, 'New Patient Registration', 'Jesper Diaz has been registered as a new patient', 0, '2026-07-13 13:57:30', 'patient', 'admin_patients.php'),
(8, 2, 'New Patient Registration', 'Jesper Diaz has been registered as a new patient', 0, '2026-07-13 13:57:30', 'patient', 'admin_patients.php'),
(9, 4, 'New Patient Registration', 'Jesper Diaz has been registered as a new patient', 0, '2026-07-13 13:57:30', 'patient', 'dentist_patients.php'),
(10, 1, 'New Patient Registration', 'Baby Jumawid has been registered as a new patient', 0, '2026-07-13 14:47:30', 'patient', 'admin_patients.php'),
(11, 2, 'New Patient Registration', 'Baby Jumawid has been registered as a new patient', 0, '2026-07-13 14:47:30', 'patient', 'admin_patients.php'),
(12, 4, 'New Patient Registration', 'Baby Jumawid has been registered as a new patient', 0, '2026-07-13 14:47:30', 'patient', 'dentist_patients.php'),
(13, 1, 'New Patient Registration', 'Walkin Dela cruz has been registered as a new patient', 0, '2026-07-15 14:59:33', 'patient', 'admin_patients.php'),
(14, 2, 'New Patient Registration', 'Walkin Dela cruz has been registered as a new patient', 0, '2026-07-15 14:59:33', 'patient', 'admin_patients.php'),
(15, 4, 'New Patient Registration', 'Walkin Dela cruz has been registered as a new patient', 0, '2026-07-15 14:59:33', 'patient', 'dentist_patients.php'),
(16, 1, 'New Patient Registration', 'Walkin Patient has been registered as a new patient', 0, '2026-07-16 10:55:53', 'patient', 'admin_patients.php'),
(17, 2, 'New Patient Registration', 'Walkin Patient has been registered as a new patient', 0, '2026-07-16 10:55:53', 'patient', 'admin_patients.php'),
(18, 4, 'New Patient Registration', 'Walkin Patient has been registered as a new patient', 0, '2026-07-16 10:55:53', 'patient', 'dentist_patients.php'),
(19, 1, 'New Patient Registration', 'walk in patient has been registered as a new patient', 0, '2026-07-16 11:42:16', 'patient', 'admin_patients.php'),
(20, 2, 'New Patient Registration', 'walk in patient has been registered as a new patient', 0, '2026-07-16 11:42:16', 'patient', 'admin_patients.php'),
(21, 4, 'New Patient Registration', 'walk in patient has been registered as a new patient', 0, '2026-07-16 11:42:16', 'patient', 'dentist_patients.php'),
(22, 1, 'New Patient Registration', 'sample sample has been registered as a new patient', 0, '2026-07-16 12:50:21', 'patient', 'admin_patients.php'),
(23, 2, 'New Patient Registration', 'sample sample has been registered as a new patient', 0, '2026-07-16 12:50:21', 'patient', 'admin_patients.php'),
(24, 4, 'New Patient Registration', 'sample sample has been registered as a new patient', 0, '2026-07-16 12:50:21', 'patient', 'dentist_patients.php'),
(25, 1, 'New Patient Registration', 'sample2 again has been registered as a new patient', 0, '2026-07-16 13:52:49', 'patient', 'admin_patients.php'),
(26, 2, 'New Patient Registration', 'sample2 again has been registered as a new patient', 0, '2026-07-16 13:52:49', 'patient', 'admin_patients.php'),
(27, 4, 'New Patient Registration', 'sample2 again has been registered as a new patient', 0, '2026-07-16 13:52:49', 'patient', 'dentist_patients.php'),
(28, 1, 'New Patient Registration', 'walk_in2 thesecond has been registered as a new patient', 0, '2026-07-16 14:18:06', 'patient', 'admin_patients.php'),
(29, 2, 'New Patient Registration', 'walk_in2 thesecond has been registered as a new patient', 0, '2026-07-16 14:18:06', 'patient', 'admin_patients.php'),
(30, 4, 'New Patient Registration', 'walk_in2 thesecond has been registered as a new patient', 0, '2026-07-16 14:18:06', 'patient', 'dentist_patients.php'),
(31, 1, 'New Patient Registration', 'Juan Miguel has been registered as a new patient', 0, '2026-07-16 15:07:18', 'patient', 'admin_patients.php'),
(32, 2, 'New Patient Registration', 'Juan Miguel has been registered as a new patient', 0, '2026-07-16 15:07:18', 'patient', 'admin_patients.php'),
(33, 4, 'New Patient Registration', 'Juan Miguel has been registered as a new patient', 0, '2026-07-16 15:07:19', 'patient', 'dentist_patients.php'),
(34, 1, 'New Patient Registration', 'jodsa Art has been registered as a new patient', 0, '2026-07-16 15:23:53', 'patient', 'admin_patients.php'),
(35, 2, 'New Patient Registration', 'jodsa Art has been registered as a new patient', 0, '2026-07-16 15:23:53', 'patient', 'admin_patients.php'),
(36, 4, 'New Patient Registration', 'jodsa Art has been registered as a new patient', 0, '2026-07-16 15:23:53', 'patient', 'dentist_patients.php'),
(37, 1, 'New Patient Registration', 'Chimp adasdaw has been registered as a new patient', 0, '2026-07-16 15:30:47', 'patient', 'admin_patients.php'),
(38, 2, 'New Patient Registration', 'Chimp adasdaw has been registered as a new patient', 0, '2026-07-16 15:30:47', 'patient', 'admin_patients.php'),
(39, 4, 'New Patient Registration', 'Chimp adasdaw has been registered as a new patient', 0, '2026-07-16 15:30:47', 'patient', 'dentist_patients.php'),
(40, 1, 'New Patient Registration', 'sample dadwad has been registered as a new patient', 0, '2026-07-28 01:15:11', 'patient', 'admin_patients.php'),
(41, 2, 'New Patient Registration', 'sample dadwad has been registered as a new patient', 0, '2026-07-28 01:15:11', 'patient', 'admin_patients.php'),
(42, 4, 'New Patient Registration', 'sample dadwad has been registered as a new patient', 0, '2026-07-28 01:15:11', 'patient', 'dentist_patients.php'),
(43, 1, 'New Patient Registration', 'James Deskaya has been registered as a new patient', 0, '2026-07-28 03:24:54', 'patient', 'admin_patients.php'),
(44, 2, 'New Patient Registration', 'James Deskaya has been registered as a new patient', 0, '2026-07-28 03:24:54', 'patient', 'admin_patients.php'),
(45, 4, 'New Patient Registration', 'James Deskaya has been registered as a new patient', 0, '2026-07-28 03:24:54', 'patient', 'dentist_patients.php'),
(46, 1, 'New Patient Registration', 'David Cas has been registered as a new patient', 0, '2026-07-28 03:43:23', 'patient', 'admin_patients.php'),
(47, 2, 'New Patient Registration', 'David Cas has been registered as a new patient', 0, '2026-07-28 03:43:23', 'patient', 'admin_patients.php'),
(48, 4, 'New Patient Registration', 'David Cas has been registered as a new patient', 0, '2026-07-28 03:43:23', 'patient', 'dentist_patients.php'),
(49, 1, 'New Patient Registration', 'sample sample patient has been registered as a new patient', 0, '2026-07-28 05:19:22', 'patient', 'admin_patients.php'),
(50, 2, 'New Patient Registration', 'sample sample patient has been registered as a new patient', 0, '2026-07-28 05:19:22', 'patient', 'admin_patients.php'),
(51, 4, 'New Patient Registration', 'sample sample patient has been registered as a new patient', 0, '2026-07-28 05:19:22', 'patient', 'dentist_patients.php'),
(52, 1, 'New Patient Registration', 'Jery Maglungsod has been registered as a new patient', 0, '2026-07-28 09:01:20', 'patient', 'admin_patients.php'),
(53, 2, 'New Patient Registration', 'Jery Maglungsod has been registered as a new patient', 0, '2026-07-28 09:01:20', 'patient', 'admin_patients.php'),
(54, 4, 'New Patient Registration', 'Jery Maglungsod has been registered as a new patient', 0, '2026-07-28 09:01:20', 'patient', 'dentist_patients.php'),
(55, 1, 'New Patient Registration', 'Sandro Marcos has been registered as a new patient', 0, '2026-07-28 15:28:29', 'patient', 'admin_patients.php'),
(56, 2, 'New Patient Registration', 'Sandro Marcos has been registered as a new patient', 0, '2026-07-28 15:28:29', 'patient', 'admin_patients.php'),
(57, 4, 'New Patient Registration', 'Sandro Marcos has been registered as a new patient', 0, '2026-07-28 15:28:29', 'patient', 'dentist_patients.php'),
(58, 1, 'New Patient Registration', 'Kim Lim has been registered as a new patient', 0, '2026-07-28 15:36:17', 'patient', 'admin_patients.php'),
(59, 2, 'New Patient Registration', 'Kim Lim has been registered as a new patient', 0, '2026-07-28 15:36:17', 'patient', 'admin_patients.php'),
(60, 4, 'New Patient Registration', 'Kim Lim has been registered as a new patient', 0, '2026-07-28 15:36:17', 'patient', 'dentist_patients.php'),
(61, 1, 'New Patient Registration', 'Krist Lampembert has been registered as a new patient', 0, '2026-07-29 12:00:04', 'patient', 'admin_patients.php'),
(62, 2, 'New Patient Registration', 'Krist Lampembert has been registered as a new patient', 0, '2026-07-29 12:00:04', 'patient', 'admin_patients.php'),
(63, 4, 'New Patient Registration', 'Krist Lampembert has been registered as a new patient', 0, '2026-07-29 12:00:04', 'patient', 'dentist_patients.php'),
(64, 1, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-29 12:37:49', 'patient', 'admin_patients.php'),
(65, 2, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-29 12:37:49', 'patient', 'admin_patients.php'),
(66, 4, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-29 12:37:49', 'patient', 'dentist_patients.php'),
(67, 1, 'New Appointment', 'New appointment scheduled for kwala sdasdasd malate on August 7, 2026', 0, '2026-07-29 15:55:01', 'appointment', 'admin_appointments.php'),
(68, 4, 'New Appointment', 'New appointment scheduled for kwala sdasdasd malate on August 7, 2026', 0, '2026-07-29 15:55:01', 'appointment', 'dentist_appointments.php'),
(69, 2, 'New Appointment', 'New appointment scheduled for kwala sdasdasd malate on August 7, 2026', 0, '2026-07-29 15:55:01', 'appointment', 'admin_appointments.php'),
(70, 1, 'New Patient Registration', 'asdasd sadasd has been registered as a new patient', 0, '2026-07-29 17:04:46', 'patient', 'admin_patients.php'),
(71, 2, 'New Patient Registration', 'asdasd sadasd has been registered as a new patient', 0, '2026-07-29 17:04:46', 'patient', 'admin_patients.php'),
(72, 4, 'New Patient Registration', 'asdasd sadasd has been registered as a new patient', 0, '2026-07-29 17:04:46', 'patient', 'dentist_patients.php'),
(73, 1, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-30 03:48:13', 'patient', 'admin_patients.php'),
(74, 2, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-30 03:48:13', 'patient', 'admin_patients.php'),
(75, 4, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-30 03:48:13', 'patient', 'dentist_patients.php'),
(76, 22, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-07-30 03:48:13', 'patient', 'dentist_patients.php'),
(77, 1, 'New Patient Registration', 'fritzz turtosa has been registered as a new patient', 0, '2026-07-30 04:07:10', 'patient', 'admin_patients.php'),
(78, 2, 'New Patient Registration', 'fritzz turtosa has been registered as a new patient', 0, '2026-07-30 04:07:10', 'patient', 'admin_patients.php'),
(79, 4, 'New Patient Registration', 'fritzz turtosa has been registered as a new patient', 0, '2026-07-30 04:07:10', 'patient', 'dentist_patients.php'),
(80, 22, 'New Patient Registration', 'fritzz turtosa has been registered as a new patient', 0, '2026-07-30 04:07:10', 'patient', 'dentist_patients.php'),
(81, 1, 'New Appointment', 'New appointment scheduled for Kurt Jay Trinidad Maligmat on August 12, 2026', 0, '2026-08-03 04:12:46', 'appointment', 'admin_appointments.php'),
(82, 4, 'New Appointment', 'New appointment scheduled for Kurt Jay Trinidad Maligmat on August 12, 2026', 0, '2026-08-03 04:12:46', 'appointment', 'dentist_appointments.php'),
(83, 22, 'New Appointment', 'New appointment scheduled for Kurt Jay Trinidad Maligmat on August 12, 2026', 0, '2026-08-03 04:12:46', 'appointment', 'dentist_appointments.php'),
(84, 2, 'New Appointment', 'New appointment scheduled for Kurt Jay Trinidad Maligmat on August 12, 2026', 0, '2026-08-03 04:12:46', 'appointment', 'admin_appointments.php'),
(85, 1, 'New Patient Registration', 'salvador maligmat has been registered as a new patient', 0, '2026-08-04 15:15:44', 'patient', 'admin_patients.php'),
(86, 2, 'New Patient Registration', 'salvador maligmat has been registered as a new patient', 0, '2026-08-04 15:15:44', 'patient', 'admin_patients.php'),
(87, 4, 'New Patient Registration', 'salvador maligmat has been registered as a new patient', 0, '2026-08-04 15:15:44', 'patient', 'dentist_patients.php'),
(88, 22, 'New Patient Registration', 'salvador maligmat has been registered as a new patient', 0, '2026-08-04 15:15:44', 'patient', 'dentist_patients.php'),
(89, 1, 'New Patient Registration', 'buaaya john has been registered as a new patient', 0, '2026-08-04 16:44:52', 'patient', 'admin_patients.php'),
(90, 2, 'New Patient Registration', 'buaaya john has been registered as a new patient', 0, '2026-08-04 16:44:52', 'patient', 'admin_patients.php'),
(91, 4, 'New Patient Registration', 'buaaya john has been registered as a new patient', 0, '2026-08-04 16:44:52', 'patient', 'dentist_patients.php'),
(92, 22, 'New Patient Registration', 'buaaya john has been registered as a new patient', 0, '2026-08-04 16:44:52', 'patient', 'dentist_patients.php'),
(93, 1, 'New Patient Registration', 'sample patient123 has been registered as a new patient', 0, '2026-08-04 16:53:29', 'patient', 'admin_patients.php'),
(94, 2, 'New Patient Registration', 'sample patient123 has been registered as a new patient', 0, '2026-08-04 16:53:29', 'patient', 'admin_patients.php'),
(95, 4, 'New Patient Registration', 'sample patient123 has been registered as a new patient', 0, '2026-08-04 16:53:29', 'patient', 'dentist_patients.php'),
(96, 22, 'New Patient Registration', 'sample patient123 has been registered as a new patient', 0, '2026-08-04 16:53:29', 'patient', 'dentist_patients.php'),
(97, 1, 'New Patient Registration', 'haynaks sample has been registered as a new patient', 0, '2026-08-04 17:11:05', 'patient', 'admin_patients.php'),
(98, 2, 'New Patient Registration', 'haynaks sample has been registered as a new patient', 0, '2026-08-04 17:11:05', 'patient', 'admin_patients.php'),
(99, 4, 'New Patient Registration', 'haynaks sample has been registered as a new patient', 0, '2026-08-04 17:11:05', 'patient', 'dentist_patients.php'),
(100, 22, 'New Patient Registration', 'haynaks sample has been registered as a new patient', 0, '2026-08-04 17:11:05', 'patient', 'dentist_patients.php'),
(101, 1, 'New Patient Registration', 'asdasd asd has been registered as a new patient', 0, '2026-08-04 17:15:59', 'patient', 'admin_patients.php'),
(102, 2, 'New Patient Registration', 'asdasd asd has been registered as a new patient', 0, '2026-08-04 17:15:59', 'patient', 'admin_patients.php'),
(103, 4, 'New Patient Registration', 'asdasd asd has been registered as a new patient', 0, '2026-08-04 17:15:59', 'patient', 'dentist_patients.php'),
(104, 22, 'New Patient Registration', 'asdasd asd has been registered as a new patient', 0, '2026-08-04 17:15:59', 'patient', 'dentist_patients.php'),
(105, 1, 'New Patient Registration', 'lamp Art has been registered as a new patient', 0, '2026-08-05 03:33:11', 'patient', 'admin_patients.php'),
(106, 2, 'New Patient Registration', 'lamp Art has been registered as a new patient', 0, '2026-08-05 03:33:11', 'patient', 'admin_patients.php'),
(107, 4, 'New Patient Registration', 'lamp Art has been registered as a new patient', 0, '2026-08-05 03:33:11', 'patient', 'dentist_patients.php'),
(108, 22, 'New Patient Registration', 'lamp Art has been registered as a new patient', 0, '2026-08-05 03:33:12', 'patient', 'dentist_patients.php'),
(109, 1, 'New Patient Registration', 'admission gikan has been registered as a new patient', 0, '2026-08-05 13:12:49', 'patient', 'admin_patients.php'),
(110, 2, 'New Patient Registration', 'admission gikan has been registered as a new patient', 0, '2026-08-05 13:12:49', 'patient', 'admin_patients.php'),
(111, 4, 'New Patient Registration', 'admission gikan has been registered as a new patient', 0, '2026-08-05 13:12:49', 'patient', 'dentist_patients.php'),
(112, 22, 'New Patient Registration', 'admission gikan has been registered as a new patient', 0, '2026-08-05 13:12:49', 'patient', 'dentist_patients.php'),
(113, 1, 'New Appointment', 'New appointment scheduled for walkin sample heh hehehe on August 7, 2026', 0, '2026-08-05 15:12:53', 'appointment', 'admin_appointments.php'),
(114, 4, 'New Appointment', 'New appointment scheduled for walkin sample heh hehehe on August 7, 2026', 0, '2026-08-05 15:12:53', 'appointment', 'dentist_appointments.php'),
(115, 22, 'New Appointment', 'New appointment scheduled for walkin sample heh hehehe on August 7, 2026', 0, '2026-08-05 15:12:53', 'appointment', 'dentist_appointments.php'),
(116, 2, 'New Appointment', 'New appointment scheduled for walkin sample heh hehehe on August 7, 2026', 0, '2026-08-05 15:12:53', 'appointment', 'admin_appointments.php'),
(117, 1, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:27:42', 'patient', 'admin_patients.php'),
(118, 2, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:27:42', 'patient', 'admin_patients.php'),
(119, 4, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:27:42', 'patient', 'dentist_patients.php'),
(120, 22, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:27:42', 'patient', 'dentist_patients.php'),
(121, 1, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:51:54', 'patient', 'admin_patients.php'),
(122, 2, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:51:54', 'patient', 'admin_patients.php'),
(123, 4, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:51:54', 'patient', 'dentist_patients.php'),
(124, 22, 'New Patient Registration', 'Chimp Art has been registered as a new patient', 0, '2026-08-05 15:51:54', 'patient', 'dentist_patients.php'),
(125, 1, 'New Appointment', 'New appointment scheduled for Chimpdsad  Art on August 7, 2026', 0, '2026-08-05 16:25:28', 'appointment', 'admin_appointments.php'),
(126, 4, 'New Appointment', 'New appointment scheduled for Chimpdsad  Art on August 7, 2026', 0, '2026-08-05 16:25:28', 'appointment', 'dentist_appointments.php'),
(127, 22, 'New Appointment', 'New appointment scheduled for Chimpdsad  Art on August 7, 2026', 0, '2026-08-05 16:25:28', 'appointment', 'dentist_appointments.php'),
(128, 2, 'New Appointment', 'New appointment scheduled for Chimpdsad  Art on August 7, 2026', 0, '2026-08-05 16:25:28', 'appointment', 'admin_appointments.php');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `patient_type` varchar(50) DEFAULT 'New Patient',
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `first_name`, `middle_name`, `last_name`, `gender`, `religion`, `street_address`, `zip_code`, `city`, `province`, `suffix`, `date_of_birth`, `phone`, `email`, `patient_type`, `is_archived`, `deleted_at`, `created_at`, `updated_at`) VALUES
(16, 'Chimpdsad', '', 'Art', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-03-12', '09214168241', 'chimp.artfiles@gmail.com', 'Returning Patient', 0, NULL, '2026-02-16 15:07:17', '2026-08-05 16:25:28'),
(20, 'Maria', 'Alona', 'A Sabanal', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-21', '09214546665', 'mara.sabanal.coc@phinmaed.com', 'New Patient', 0, NULL, '2026-07-19 16:15:18', '2026-07-19 16:15:18'),
(22, 'Adaadwawd', 'Wadawd', 'Wadadw', 'Male', NULL, NULL, NULL, NULL, NULL, NULL, '2000-02-02', '26235', 'bovi.zata.coc@phinmaed.com', 'New Patient', 0, NULL, '2026-07-28 01:12:30', '2026-07-28 01:12:30'),
(23, 'sample', 'dad', 'dadwad', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '21321312231', '', 'New Patient', 0, NULL, '2026-07-28 01:15:11', '2026-07-28 01:15:11'),
(25, 'James', 'Malik', 'Deskaya', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2003-07-29', '09123456146', '', 'New Patient', 0, NULL, '2026-07-28 03:24:54', '2026-07-28 03:24:54'),
(26, 'David', 'Nam', 'Casinillo', 'Male', NULL, NULL, NULL, NULL, NULL, NULL, '1998-07-21', '09358565116', 'theunbreakable13@gmail.com', 'Returning Patient', 0, NULL, '2026-07-28 03:36:26', '2026-07-28 08:12:44'),
(28, 'David', '', 'Cas', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2005-01-24', '09350336101', '', 'New Patient', 0, NULL, '2026-07-28 03:43:23', '2026-07-28 03:43:23'),
(29, 'sample sample', 'd.', 'patient', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214568888', '', 'New Patient', 0, NULL, '2026-07-28 05:19:22', '2026-07-28 05:19:22'),
(36, 'Sandro', '', 'Marcos', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-07-29', '09864646434', '', 'New Patient', 0, NULL, '2026-07-28 15:28:28', '2026-07-28 15:28:28'),
(37, 'Kim', '', 'Lim', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2001-08-02', '09864646455', '', 'New Patient', 0, NULL, '2026-07-28 15:36:17', '2026-07-28 15:36:17'),
(38, 'Krist', '', 'Lampembert', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09134564564', '', 'New Patient', 0, NULL, '2026-07-29 12:00:04', '2026-07-29 12:00:04'),
(39, 'Chimp', '', 'Art', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-07-29 12:37:49', '2026-08-05 15:51:54'),
(40, 'Kurt Jay', 'Trinidad', 'Maligmat', 'Male', NULL, NULL, NULL, NULL, NULL, NULL, '2000-02-02', '09924465990', 'bosszata20@gmail.com', 'Returning Patient', 0, NULL, '2026-07-29 14:02:29', '2026-08-03 04:12:45'),
(41, 'kwala', 'sdasdasd', 'malate', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'New Patient', 0, NULL, '2026-07-29 15:55:01', '2026-07-29 15:55:01'),
(42, 'asdasd', 'asdas', 'sadasd', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '12321123213', '', 'New Patient', 0, NULL, '2026-07-29 17:04:46', '2026-07-29 17:04:46'),
(43, 'Phili', NULL, 'Philip', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12313133123', 'babyjeanjumawid05@gmail.com', 'New Patient', 0, NULL, '2026-07-30 03:13:58', '2026-07-30 03:13:58'),
(45, 'david', 'nams', 'casinillo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '09350336101', 'davidcasinillo728@gmail.com', 'Returning Patient', 0, NULL, '2026-07-30 03:31:58', '2026-07-30 04:20:22'),
(46, 'Chimp', '', 'Art', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-07-30 03:48:13', '2026-08-05 15:27:42'),
(47, 'fritzz', '', 'turtosa', 'Male', NULL, NULL, NULL, NULL, NULL, 'jr.', '2004-05-20', '09214168241', '', 'New Patient', 0, NULL, '2026-07-30 04:07:10', '2026-07-30 04:07:10'),
(48, 'Denver', NULL, 'Jane', 'Male', NULL, NULL, NULL, NULL, NULL, NULL, '2000-02-22', '12312312312', 'kimpang2k25@gmail.com', 'Returning Patient', 0, NULL, '2026-08-02 08:42:01', '2026-08-02 09:24:17'),
(49, 'Sample Patient', 'Ni', 'Sya', 'Male', NULL, NULL, NULL, NULL, NULL, NULL, '2000-08-25', '09493472725', 'lenghuk2k25@gmail.com', 'Returning Patient', 0, NULL, '2026-08-02 10:30:52', '2026-08-02 10:52:48'),
(50, 'Alona Mari', NULL, 'Sabanal', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2002-07-20', '09757605916', 'malazarteshane24@gmail.com', 'Returning Patient', 0, NULL, '2026-08-03 04:38:24', '2026-08-04 16:32:40'),
(51, 'Karina', 'Jasie', 'Villanueva', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2000-02-02', '09924465990', 'gabrielmike24@gmail.com', 'New Patient', 0, NULL, '2026-08-04 14:16:20', '2026-08-04 14:16:20'),
(52, 'Gabby', NULL, 'Meldado', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2001-02-02', '09924465990', 'shemamercado@gmail.com', 'New Patient', 0, NULL, '2026-08-04 14:42:22', '2026-08-04 14:42:22'),
(53, 'Maligaya', NULL, 'Linda', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2003-03-22', '09924465990', 'chimp.artfiles@gmail.com', 'New Patient', 0, NULL, '2026-08-04 14:50:21', '2026-08-04 14:50:21'),
(54, 'salvador', '', 'maligmat', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2001-02-02', '09924465990', '', 'New Patient', 0, NULL, '2026-08-04 15:15:44', '2026-08-04 15:15:44'),
(55, 'buaaya', '', 'john', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09234168241', '', 'New Patient', 0, NULL, '2026-08-04 16:44:52', '2026-08-04 16:44:52'),
(56, 'sample', '', 'patient123', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-08-04 16:53:29', '2026-08-04 16:53:29'),
(57, 'haynaks', '', 'sample', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-08-04 17:11:05', '2026-08-04 17:11:05'),
(58, 'asdasd', 'asd', 'asd', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2000-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-08-04 17:15:59', '2026-08-04 17:15:59'),
(59, 'lamp', '', 'Art', 'Female', NULL, NULL, NULL, NULL, NULL, '', '2001-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-08-05 03:33:11', '2026-08-05 03:33:11'),
(60, 'admission', 'ni', 'gikan', 'Male', NULL, NULL, NULL, NULL, NULL, '', '2004-02-02', '09214168241', '', 'New Patient', 0, NULL, '2026-08-05 13:12:49', '2026-08-05 13:12:49'),
(61, 'walkin sample', 'heh', 'hehehe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '123123123', 'adwadr@gmail.com', 'New Patient', 0, NULL, '2026-08-05 15:12:53', '2026-08-05 15:12:53');

-- --------------------------------------------------------

--
-- Table structure for table `patient_files`
--

CREATE TABLE `patient_files` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tooth_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
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
  `provider_id` int(11) DEFAULT NULL,
  `policy_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `patient_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `billing_id`, `amount`, `payment_date`, `created_at`, `patient_id`, `payment_method`, `reference_number`, `created_by`, `notes`) VALUES
(9, 13, 900.00, '2026-02-16', '2026-02-16 15:29:48', 16, 'Cash', NULL, 2, NULL),
(10, 18, 400.00, '2026-07-28', '2026-07-28 04:47:42', 28, 'cash', NULL, 2, NULL),
(11, 18, 400.00, '2026-07-28', '2026-07-28 04:50:46', 28, 'gcash', NULL, 2, NULL),
(12, 19, 500.00, '2026-07-28', '2026-07-28 07:17:21', 29, 'Cash', NULL, 2, NULL),
(13, 16, 500.00, '2026-07-28', '2026-07-28 08:58:42', 23, 'cash', NULL, 2, NULL),
(14, 17, 500.00, '2026-07-28', '2026-07-28 08:59:14', 25, 'Cash', NULL, 2, NULL),
(15, 27, 500.00, '2026-07-30', '2026-07-30 04:12:11', 47, 'cash', NULL, 2, NULL),
(16, 39, 500.00, '2026-08-05', '2026-08-05 15:54:05', 39, 'Cash', NULL, 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `issue_date` date NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `medications` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `patient_id`, `diagnosis`, `issue_date`, `doctor_id`, `consultation_id`, `created_at`, `medications`, `instructions`, `updated_at`) VALUES
(9, 16, 'sadasd213123', '2026-07-20', 4, NULL, '2026-07-20 04:19:52', 'asdadasd', 'asdasd', '2026-07-30 00:44:44'),
(10, 57, 'Prescription from consultation', '2026-08-05', 4, 26, '2026-08-04 17:12:22', 'asdasd hehe (ad23, 23, 23)', '233', '2026-08-04 17:12:22'),
(11, 58, 'Prescription from consultation', '2026-08-05', 4, 28, '2026-08-04 17:22:56', 'HAHAH (HA, HA, HA)', 'HA', '2026-08-04 17:22:56'),
(12, 49, 'Prescription from consultation', '2026-08-05', 4, 31, '2026-08-05 14:24:10', 'asd (asd, asd, asd)', 'asd', '2026-08-05 14:24:10');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `medicine_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `queue_label` varchar(20) DEFAULT NULL,
  `queue_date` date DEFAULT NULL,
  `status` enum('waiting','in_procedure','completed','cancelled','on_hold') DEFAULT 'waiting',
  `status_id` int(11) DEFAULT 1,
  `dentist_id` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT 5,
  `procedure_notes` text DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` varchar(20) DEFAULT NULL,
  `temperature` varchar(20) DEFAULT NULL,
  `oxygen_saturation` varchar(20) DEFAULT NULL,
  `height` varchar(20) DEFAULT NULL,
  `weight` varchar(20) DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `queue_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `teeth_numbers` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue`
--

INSERT INTO `queue` (`id`, `patient_id`, `appointment_id`, `queue_number`, `queue_label`, `queue_date`, `status`, `status_id`, `dentist_id`, `priority`, `procedure_notes`, `blood_pressure`, `heart_rate`, `temperature`, `oxygen_saturation`, `height`, `weight`, `is_processed`, `completed_at`, `queue_time`, `notes`, `is_archived`, `created_at`, `updated_at`, `treatment_type`, `doctor_id`, `service_id`, `teeth_numbers`) VALUES
(16, 16, NULL, 1, '#1', '2026-07-16', 'completed', 1, NULL, 5, 'done', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-16 15:29:28', '23:07:17', NULL, 0, '2026-07-16 15:07:17', '2026-07-16 23:29:24', 'Teeth Cleaning', NULL, 9, '18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28, 48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38'),
(19, 23, NULL, 1, '#1', '2026-07-28', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-28 03:20:47', '03:15:11', NULL, 0, '2026-07-28 01:15:11', '2026-07-28 09:15:11', 'Denture Adjustment', NULL, NULL, NULL),
(20, 25, NULL, 2, '#2', '2026-07-28', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-28 03:42:38', '05:24:54', NULL, 0, '2026-07-28 03:24:54', '2026-07-28 11:24:54', 'Periapical Xray', NULL, NULL, NULL),
(21, 28, NULL, 3, '#3', '2026-07-28', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-28 04:46:16', '05:43:23', NULL, 0, '2026-07-28 03:43:23', '2026-07-28 11:43:23', 'Teeth Cleaning', NULL, NULL, NULL),
(22, 29, NULL, 4, '#4', '2026-07-28', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-28 07:17:07', '07:19:22', NULL, 0, '2026-07-28 05:19:22', '2026-07-28 13:19:22', 'Consultation', NULL, NULL, NULL),
(27, 26, 16, 8, '#8', '2026-07-28', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '10:14:24', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-28 08:14:24', '2026-07-29 18:50:17', 'Removable Dentures', NULL, NULL, NULL),
(31, 36, NULL, 12, '#12', '2026-07-28', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '17:28:28', NULL, 0, '2026-07-28 15:28:28', '2026-07-28 23:28:28', 'Consultation', NULL, NULL, NULL),
(32, 37, NULL, 13, '#13', '2026-07-28', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '17:36:17', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-28 15:36:17', '2026-07-29 18:50:17', 'Teeth Cleaning', NULL, NULL, NULL),
(33, 38, NULL, 1, '#1', '2026-07-29', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '14:00:04', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-29 12:00:04', '2026-07-30 08:19:54', 'Crowns (Jacket)', NULL, NULL, NULL),
(34, 39, NULL, 2, '#2', '2026-07-29', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '14:37:49', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-29 12:37:49', '2026-07-30 08:19:54', 'Tooth Extraction (Ibot)', NULL, NULL, NULL),
(35, 42, NULL, 3, '#3', '2026-07-29', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '19:04:46', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-29 17:04:46', '2026-07-30 08:19:54', 'Fixed Bridge', NULL, NULL, NULL),
(36, 45, 23, 1, '#1', '2026-07-30', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-30 04:07:46', '05:35:36', NULL, 0, '2026-07-30 03:35:36', '2026-07-30 11:35:36', 'Teeth Cleaning', NULL, NULL, NULL),
(37, 46, NULL, 2, '#2', '2026-07-30', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-30 04:09:27', '05:48:13', NULL, 0, '2026-07-30 03:48:13', '2026-07-30 11:48:13', 'Removable Dentures, Tooth Extraction (Ibot)', NULL, NULL, NULL),
(38, 47, NULL, 3, '#3', '2026-07-30', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-07-30 04:11:05', '06:07:10', NULL, 0, '2026-07-30 04:07:10', '2026-07-30 12:07:10', 'Consultation', NULL, NULL, NULL),
(39, 45, 24, 4, '#4', '2026-07-30', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '06:22:32', ' [Auto-archived: stale entry from previous day]', 0, '2026-07-30 04:22:32', '2026-08-02 10:01:12', 'Consultation', NULL, NULL, NULL),
(40, 40, 44, 1, '#1', '2026-08-03', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '07:01:21', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-03 05:01:21', '2026-08-04 17:56:49', 'Consultation', NULL, NULL, NULL),
(41, 40, 37, 1, '#1', '2026-08-04', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 14:11:03', '22:05:46', NULL, 0, '2026-08-04 14:05:46', '2026-08-04 22:05:46', 'Consultation', NULL, NULL, NULL),
(42, 51, 48, 2, '#2', '2026-08-04', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '23:01:11', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-04 15:01:11', '2026-08-05 00:03:59', 'Consultation', NULL, NULL, NULL),
(43, 53, 50, 3, '#3', '2026-08-04', 'cancelled', 1, NULL, 5, 'pa book matsalams', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '23:02:00', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-04 15:02:00', '2026-08-05 00:03:59', 'Consultation', NULL, NULL, NULL),
(44, 54, NULL, 4, '#4', '2026-08-04', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '23:15:44', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-04 15:15:44', '2026-08-05 00:03:59', 'Consultation', NULL, NULL, NULL),
(45, 50, 45, 1, '#1', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 16:18:44', '00:09:35', NULL, 0, '2026-08-04 16:09:35', '2026-08-05 00:09:35', 'Consultation', NULL, NULL, NULL),
(46, 50, 51, 2, '#2', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 16:38:10', '00:33:49', NULL, 0, '2026-08-04 16:33:49', '2026-08-05 00:33:49', 'Consultation', NULL, NULL, NULL),
(47, 55, NULL, 3, '#3', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 16:45:59', '00:44:52', NULL, 0, '2026-08-04 16:44:52', '2026-08-05 00:44:52', 'Teeth Cleaning', NULL, NULL, NULL),
(48, 56, NULL, 4, '#4', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 16:55:11', '00:53:29', NULL, 0, '2026-08-04 16:53:29', '2026-08-05 00:53:29', 'Periapical Xray', NULL, NULL, NULL),
(49, 57, NULL, 5, '#5', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 17:12:47', '01:11:05', NULL, 0, '2026-08-04 17:11:05', '2026-08-05 01:11:05', 'Teeth Cleaning', NULL, NULL, NULL),
(50, 58, NULL, 6, '#6', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 17:17:10', '01:15:59', NULL, 0, '2026-08-04 17:15:59', '2026-08-05 01:15:59', 'Periapical Xray', NULL, NULL, NULL),
(51, 58, NULL, 7, '#7', '2026-08-05', 'completed', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-08-04 17:22:56', '01:21:29', NULL, 0, '2026-08-04 17:21:29', '2026-08-05 01:21:29', 'Consultation', NULL, NULL, '18'),
(52, 58, NULL, 8, '#8', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '01:21:42', NULL, 0, '2026-08-04 17:21:42', '2026-08-05 01:21:42', 'Consultation', NULL, NULL, '18'),
(53, 58, NULL, 9, '#9', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '01:21:51', 'ds\n\nAdditional Notes: asd', 0, '2026-08-04 17:21:51', '2026-08-05 01:21:51', 'Consultation', NULL, NULL, '18'),
(54, 59, NULL, 10, '#10', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '11:33:11', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 03:33:11', '2026-08-06 00:12:46', 'Teeth Cleaning', NULL, NULL, NULL),
(55, 49, 38, 11, '#11', '2026-08-05', 'cancelled', 1, NULL, 5, 'okkk', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '19:50:16', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 11:50:16', '2026-08-06 00:12:46', 'Consultation', NULL, NULL, NULL),
(56, 41, 21, 12, '#12', '2026-08-05', 'cancelled', 1, NULL, 5, 'adsa', '120/80', '72', '36', '99', '166', '55', 0, NULL, '20:52:24', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 12:52:24', '2026-08-06 00:12:46', 'Consultation', 4, NULL, NULL),
(57, 60, NULL, 13, '#13', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '21:12:49', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 13:12:49', '2026-08-06 00:12:46', 'Consultation', NULL, NULL, NULL),
(58, 49, 40, 14, '#14', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '23:05:08', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 15:05:08', '2026-08-06 00:12:46', 'Consultation', NULL, NULL, NULL),
(59, 46, NULL, 15, '#15', '2026-08-05', 'cancelled', 1, NULL, 5, NULL, '130', NULL, NULL, NULL, NULL, NULL, 0, NULL, '23:27:42', ' [Auto-archived: stale entry from previous day]', 0, '2026-08-05 15:27:42', '2026-08-06 00:12:46', 'Consultation', NULL, NULL, NULL),
(60, 39, NULL, 16, '#16', '2026-08-05', 'completed', 1, NULL, 5, NULL, '130/20', NULL, NULL, NULL, NULL, NULL, 0, '2026-08-05 15:53:09', '23:51:54', NULL, 0, '2026-08-05 15:51:54', '2026-08-05 23:51:54', 'Consultation', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `queue_items`
--

CREATE TABLE `queue_items` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `available_day` varchar(20) NOT NULL,
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '17:00:00',
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `doctor_id`, `available_day`, `start_time`, `end_time`, `is_available`) VALUES
(1, 4, 'Monday', '08:00:00', '17:00:00', 1),
(2, 4, 'Tuesday', '08:00:00', '17:00:00', 1),
(3, 4, 'Wednesday', '08:00:00', '17:00:00', 1),
(4, 4, 'Thursday', '08:00:00', '17:00:00', 1),
(5, 4, 'Friday', '08:00:00', '17:00:00', 1),
(6, 4, 'Saturday', '08:00:00', '17:00:00', 1),
(7, 4, 'Sunday', '08:00:00', '17:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `mode` enum('BULK','SINGLE','NONE') DEFAULT 'SINGLE',
  `duration_minutes` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `price`, `is_active`, `mode`, `duration_minutes`, `description`, `is_archived`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Consultation', 500.00, 1, 'NONE', 30, 'Professional oral health assessment and planning.', 0, '2026-04-21 22:46:29', '2026-04-21 23:23:36', NULL),
(2, 'Crowns (Jacket)', 2000.00, 1, 'SINGLE', 90, 'Protective cap to restore tooth shape and strength.', 0, '2026-04-21 22:46:29', '2026-04-21 22:46:29', NULL),
(3, 'Denture Adjustment', 500.00, 1, 'SINGLE', 30, 'Reshaping of dentures to improve fit and comfort.', 0, '2026-04-21 22:46:29', '2026-04-21 22:46:29', NULL),
(4, 'Fixed Bridge', 5000.00, 1, 'SINGLE', 90, 'Permanent replacement for missing teeth anchored to adjacent teeth.', 0, '2026-04-21 22:46:30', '2026-04-21 22:46:30', NULL),
(5, 'Orthodontic Appliance', 35000.00, 1, 'BULK', 120, 'Devices like braces or retainers to align teeth.', 0, '2026-04-21 22:46:30', '2026-04-21 23:24:04', NULL),
(6, 'Periapical Xray', 500.00, 1, 'SINGLE', 15, 'Single-tooth X-ray showing the root and surrounding bone', 0, '2026-04-21 22:46:30', '2026-04-21 22:46:30', NULL),
(7, 'Removable Dentures', 5000.00, 1, 'SINGLE', 30, 'Custom-made removable replacement for missing teeth.', 0, '2026-04-21 22:46:30', '2026-04-21 22:46:30', NULL),
(8, 'Root Canal Treatment', 5000.00, 1, 'SINGLE', 90, 'Saves an infected tooth by removing the pulp.', 0, '2026-04-21 22:46:30', '2026-04-21 22:46:30', NULL),
(9, 'Teeth Cleaning', 800.00, 1, 'BULK', 60, 'Case to case cleaning', 0, '2026-04-21 22:46:31', '2026-04-21 23:22:55', NULL),
(10, 'Teeth Whitening', 5000.00, 1, 'BULK', 90, 'Cosmetic procedure to lighten teeth and remove stains.', 0, '2026-04-21 22:46:31', '2026-04-21 23:23:07', NULL),
(11, 'Tooth Extraction (Ibot)', 800.00, 1, 'SINGLE', 45, 'Safe removal of a damaged or non-restorable tooth.', 0, '2026-04-21 22:46:33', '2026-04-21 22:46:33', NULL),
(12, 'Tooth Restoration (Pasta)', 800.00, 1, 'SINGLE', 45, 'Restores decayed teeth using composite filling.', 0, '2026-04-21 22:46:33', '2026-04-21 22:46:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(50, 'welcome_hero_title_blocks', '[{\"text\":\"Your\",\"color\":\"\",\"fontSize\":64,\"fontWeight\":700,\"lineHeight\":1.05,\"newLine\":true},{\"text\":\"Best Dental\",\"color\":\"#0069d1\",\"fontSize\":64,\"fontWeight\":700,\"lineHeight\":1.05,\"newLine\":false},{\"text\":\"Care\",\"color\":\"#000000\",\"fontSize\":64,\"fontWeight\":700,\"lineHeight\":1.05,\"newLine\":true},{\"text\":\"In Town Awaits\",\"color\":\"#000000\",\"fontSize\":64,\"fontWeight\":700,\"lineHeight\":1.05,\"newLine\":true}]'),
(51, 'welcome_hero_subtitle', 'Experience expert, family-friendly dental care. Our dedicated team is here to give you a brighter, healthier smile'),
(52, 'welcome_hero_image', ''),
(53, 'welcome_hero_badge_big', '7+'),
(54, 'welcome_hero_badge_small', 'Years Experience'),
(55, 'welcome_hero_note1', 'Hi I\'m Dr. Rex'),
(56, 'welcome_hero_note2', 'and I\'m Dr. Floralyn'),
(72, 'welcome_about_heading', 'About RF Dental'),
(73, 'welcome_about_paragraph1', 'At RF Dental Clinic, we\'re committed to providing exceptional dental care in a comfortable and welcoming environment. Our experienced team uses the latest technology to ensure you receive the best treatment possible.'),
(74, 'welcome_about_paragraph2', 'We believe everyone deserves a healthy, beautiful smile. That\'s why we offer comprehensive services ranging from routine checkups to advanced cosmetic procedures, all delivered with personalized care and attention.'),
(75, 'welcome_about_image', ''),
(76, 'welcome_about_stats', '[{\"value\":\"5,000+\",\"label\":\"Happy Patients\"},{\"value\":\"98%\",\"label\":\"Satisfaction Rate\"},{\"value\":\"1,500\",\"label\":\"Fb followers\"}]'),
(92, 'welcome_featured_services', '[1,9,11]'),
(93, 'welcome_service_images', '{\"1\":\"assets\\/images\\/content\\/services_20260713_123031_d10ececf.jpg\",\"2\":\"assets\\/images\\/content\\/services_20260713_123055_bd94bcec.jpg\",\"3\":\"assets\\/images\\/content\\/services_20260713_123117_4ee79a67.jpg\",\"4\":\"assets\\/images\\/content\\/services_20260713_123124_3b6bbb0c.jpg\",\"5\":\"assets\\/images\\/content\\/services_20260713_123208_e62dfecc.jpg\",\"6\":\"assets\\/images\\/content\\/services_20260713_123143_33f12a7c.jpg\",\"7\":\"assets\\/images\\/content\\/services_20260713_123204_a64017ed.jpg\",\"8\":\"assets\\/images\\/content\\/services_20260713_123215_d5163843.jpg\",\"9\":\"assets\\/images\\/content\\/services_20260713_123228_72c5dcb2.jpg\",\"10\":\"assets\\/images\\/content\\/services_20260713_123244_8e162ea5.png\",\"11\":\"assets\\/images\\/content\\/services_20260713_123250_b03a4ddc.jpg\",\"12\":\"assets\\/images\\/content\\/services_20260713_123304_ed9abecb.jpg\"}'),
(97, 'welcome_faqs', '[{\"q\":\"What should I expect during my first visit?\",\"a\":\"During your first visit, you\'ll meet our friendly team, discuss your dental history, and receive a thorough examination. We recommend bringing any previous dental records and a list of medications you\'re currently taking.\"},{\"q\":\"How often should I visit the dentist?\",\"a\":\"We recommend visiting the dentist every 6 months for regular check-ups and cleanings. However, if you have specific dental concerns or conditions, we may suggest more frequent visits to ensure optimal oral health.\"},{\"q\":\"Do you accept dental insurance?\",\"a\":\"Unfortunately, we do not accept dental insurance at this time. However, we offer competitive pricing and flexible payment options to make our services accessible to all patients.\"},{\"q\":\"What payment options do you offer?\",\"a\":\"We offer flexible payment options including cash, gcash e-wallet and bank transfers.\"}]'),
(98, 'clinic_name', 'RF Dental Clinic'),
(99, 'clinic_address', '17 Max Y. Suniel St, Carmen, Cagayan De Oro City, 9000 Misamis Oriental, Philippines'),
(100, 'clinic_phone', '0949 347 2725'),
(101, 'clinic_email', 'rfdc2023@gmail.com'),
(102, 'auto_cancel_enabled', '1'),
(103, 'auto_cancel_minutes', '15'),
(107, 'two_factor_auth', '0'),
(111, 'session_timeout', '60'),
(123, 'session_notification_threshold', '2'),
(124, 'unavailable_dates', '[{\"date\":\"2026-07-29\",\"status\":\"custom\",\"note\":\"close\",\"custom_open\":\"10:00\",\"custom_close\":\"12:00\"}]');

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
  `treatment_date` date NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plans`
--

CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `plan_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sessions_data` text DEFAULT NULL,
  `teeth_numbers` varchar(100) DEFAULT NULL,
  `completed_sessions` int(11) DEFAULT 0,
  `total_sessions` int(11) DEFAULT 1,
  `overall_status` enum('Active','Completed','On-Hold','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doctor_id` int(11) DEFAULT NULL,
  `total_estimated_cost` decimal(10,2) DEFAULT NULL,
  `next_session_date` date DEFAULT NULL,
  `next_session_time` time DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_plans`
--

INSERT INTO `treatment_plans` (`id`, `patient_id`, `plan_name`, `notes`, `sessions_data`, `teeth_numbers`, `completed_sessions`, `total_sessions`, `overall_status`, `created_at`, `doctor_id`, `total_estimated_cost`, `next_session_date`, `next_session_time`, `appointment_id`, `updated_at`) VALUES
(3, 16, 'Denture Adjustment', '', '[{\"date\":\"2026-07-22\",\"time\":\"08:00:00\",\"treatment\":\"Denture Adjustment\"},{\"date\":\"2026-07-27\",\"time\":\"08:00:00\",\"treatment\":\"Tooth Extraction (Ibot)\"}]', '', 0, 2, 'Active', '2026-07-20 04:42:23', 4, NULL, '2026-07-22', '08:00:00', 4, '2026-07-20 04:42:23'),
(4, 29, 'Removable Dentures', '', '[{\"date\":\"2026-07-29\",\"time\":\"10:15:00\",\"treatment\":\"Removable Dentures\"}]', '', 0, 1, 'Active', '2026-07-28 05:24:57', 4, NULL, '2026-07-29', '10:15:00', 8, '2026-07-28 05:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plan_details`
--

CREATE TABLE `treatment_plan_details` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `service_id` int(11) DEFAULT NULL,
  `tooth_id` int(11) DEFAULT NULL,
  `total_sessions` int(11) DEFAULT NULL,
  `completed_sessions` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('Admin','Dentist','Staff','Patient') NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `last_active` timestamp NULL DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  `is_logged_in` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `patient_id`, `staff_id`, `username`, `password`, `status`, `last_active`, `last_logout`, `is_logged_in`, `created_at`, `updated_at`, `two_factor_enabled`, `two_factor_secret`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `gender`, `date_of_birth`, `is_archived`, `profile_picture`) VALUES
(1, 'Admin', NULL, NULL, 'admin', '$2y$12$DjNJqA0j0ftaQYTsK6NoYuFViHhU9n/m5kfwibHL4E/PfUQeW3Gly', 'Active', '2026-08-04 05:51:49', '2026-08-04 14:15:12', 0, '2026-07-13 09:43:44', '2026-08-04 14:15:12', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(2, 'Staff', NULL, NULL, 'staff', '$2y$12$kiIQRXHpo5PdQ0IN1Nuti.mj.yMf8V4KSqi/EFF/JnkxYYODPwlXC', 'Active', '2026-08-05 07:26:59', NULL, 1, '2026-07-13 10:50:26', '2026-08-05 15:26:59', 0, NULL, 'Angelle', '', 'Hope', 'rfdental_staff@gmail.com', '09264564564', NULL, NULL, 0, 'assets/images/profiles/2_1784119787.jpg'),
(4, 'Dentist', NULL, NULL, 'dentist', '$2y$12$VSfDxGxRMRorb6QTJ3TppeocbMcQBN2neJpspZ7VoRPn0mDs7/kRm', 'Active', '2026-08-05 07:52:13', '2026-08-05 16:06:31', 0, '2026-07-13 10:58:44', '2026-08-05 16:06:31', 0, NULL, 'Floralyn', '', 'Gabz', 'rfdental_dentist@gmail.com', '09264546544', NULL, NULL, 0, 'assets/images/profiles/4_1784452604.jpg'),
(9, 'Patient', NULL, NULL, 'lenghuk23@gmail.com', '$2y$12$o2k5hmFAM36bt.bT/bLJIOw/rb3lLnc6j9fSs3VexXOFisbYXZl8C', 'Active', '2026-07-15 08:06:35', '2026-07-15 16:25:27', 0, '2026-07-15 15:13:32', '2026-07-16 16:43:18', 0, NULL, 'Booking', 'Appointment', 'Today', 'lenghuk23@gmail.com', '09214566546', 'Female', '2000-02-03', 0, NULL),
(21, 'Patient', NULL, NULL, 'bosszata20@gmail.com', '$2y$12$sPTpIrWUc4thFIQmZ72W..1kroBGBzHxNjiLOsf1KKhzT0Zv4kLf2', 'Active', '2026-08-04 06:11:52', '2026-08-04 14:12:01', 0, '2026-07-29 14:02:29', '2026-08-04 14:12:01', 0, NULL, 'Kurt Jay', 'Trinidad', 'Maligmat', 'bosszata20@gmail.com', '12312313', 'Male', '2000-02-02', 0, NULL),
(22, 'Dentist', NULL, NULL, 'dentist2', '$2y$12$9B3LpHHpUDQiSsXXHzMLzuNioU.GcUx4w2vAMuZYLlM9SatNyPFS2', 'Active', NULL, NULL, NULL, '2026-07-30 00:22:50', '2026-07-30 00:22:50', 0, NULL, 'dentist2', 'Kate', 'dent', 'ivoryk@gmail.com', NULL, 'Male', NULL, 0, NULL),
(23, 'Patient', NULL, NULL, 'babyjeanjumawid05@gmail.com', '$2y$12$tnz.KsqPqQNcGr/wsDfiJOTcuh5WYTGl.w53xtahsh/rWhJClmeS.', 'Active', NULL, '2026-07-30 03:14:09', 0, '2026-07-30 03:13:58', '2026-07-30 03:14:09', 0, NULL, 'Phili', NULL, 'Philip', 'babyjeanjumawid05@gmail.com', '12313133123', 'Female', NULL, 0, NULL),
(25, 'Patient', NULL, NULL, 'davidcasinillo728@gmail.com', '$2y$12$DCBl3gBXXf9vFOPINLn7HeKVESEbI8T2gRnJkxjwFWoF/4BmL8x7a', 'Active', '2026-07-29 20:18:20', '2026-07-30 04:30:39', 0, '2026-07-30 03:31:57', '2026-07-30 04:30:39', 0, NULL, 'david', 'nams', 'casinillo', 'davidcasinillo728@gmail.com', '09350336101', NULL, NULL, 0, NULL),
(26, 'Patient', NULL, NULL, 'kimpang2k25@gmail.com', '$2y$12$6E4HvR0nF95CEpCYagxpauoSHDhMn1xmC2Dp13bq0Wh/rYMM02Jum', 'Active', '2026-08-02 01:21:21', '2026-08-02 09:34:35', 0, '2026-08-02 08:42:01', '2026-08-02 09:34:35', 0, NULL, 'Denver', NULL, 'Jane', 'kimpang2k25@gmail.com', '12312312312', 'Male', '2000-02-22', 0, NULL),
(27, 'Patient', NULL, NULL, 'lenghuk2k25@gmail.com', '$2y$12$EvGX5RpvhOp3wtYQYFdaOOL8.eCpx06ZuQMXKrEzH4KdRiuXqi61y', 'Active', '2026-08-02 02:48:11', '2026-08-02 11:06:04', 0, '2026-08-02 10:30:52', '2026-08-02 11:06:04', 0, NULL, 'Sample Patient', 'Ni', 'Sya', 'lenghuk2k25@gmail.com', '09493472725', 'Male', '2000-08-25', 0, NULL),
(28, 'Patient', NULL, NULL, 'malazarteshane24@gmail.com', '$2y$12$hqIU9T750CzcfrRtqOTsDeUxlNs.c2.lvfN1/he1sC17dPXgFQqWu', 'Active', '2026-08-04 08:30:06', '2026-08-04 16:43:57', 0, '2026-08-03 04:38:24', '2026-08-04 16:43:57', 0, NULL, 'Alona Mari', NULL, 'Sabanal', 'malazarteshane24@gmail.com', '09757605916', 'Female', '2002-07-20', 0, NULL),
(29, 'Patient', NULL, NULL, 'gabrielmike24@gmail.com', '$2y$12$hnExZGKGSBsWkcrxsml9U.jygBi65yMnW7vLNL8/nmbJr/3lWxxeu', 'Active', NULL, '2026-08-04 14:27:46', 0, '2026-08-04 14:16:20', '2026-08-04 14:27:46', 0, NULL, 'Karina', 'Jasie', 'Villanueva', 'gabrielmike24@gmail.com', '09924465990', 'Female', '2000-02-02', 0, NULL),
(30, 'Patient', NULL, NULL, 'shemamercado@gmail.com', '$2y$12$mB/EdhqxOje8.Woo3CQ9mO//qwnmzSJsiJAjzDYY.q1kLN.bc99xa', 'Active', NULL, '2026-08-04 14:52:32', 0, '2026-08-04 14:42:22', '2026-08-04 14:52:32', 0, NULL, 'Gabby', NULL, 'Meldado', 'shemamercado@gmail.com', '09924465990', 'Female', '2001-02-02', 0, NULL),
(31, 'Patient', NULL, NULL, 'chimp.artfiles@gmail.com', '$2y$12$lmwnCDha8i2kK6fA86rWA.UDtIGDKi0Ng11lss2SLEb6zA4W4QdZ6', 'Active', '2026-08-04 07:05:54', '2026-08-04 15:25:54', 0, '2026-08-04 14:50:21', '2026-08-04 15:25:54', 0, NULL, 'Maligaya', NULL, 'Linda', 'chimp.artfiles@gmail.com', '09924465990', 'Female', '2003-03-22', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appt_patient` (`patient_id`),
  ADD KEY `fk_appt_service` (`service_id`),
  ADD KEY `idx_dentist_id` (`dentist_id`);

--
-- Indexes for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_appointment_status_name` (`status_name`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_billing_patient` (`patient_id`),
  ADD KEY `fk_billing_appt` (`appointment_id`);

--
-- Indexes for table `chat_typing_status`
--
ALTER TABLE `chat_typing_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consultations_queue_id` (`queue_id`),
  ADD KEY `idx_consultations_patient_id` (`patient_id`),
  ADD KEY `idx_consultations_dentist_id` (`dentist_id`),
  ADD KEY `idx_consultations_status` (`status`);

--
-- Indexes for table `content_images`
--
ALTER TABLE `content_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_images_user` (`uploaded_by`);

--
-- Indexes for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dental_patient` (`patient_id`);

--
-- Indexes for table `dentist_unavailable`
--
ALTER TABLE `dentist_unavailable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dentist_date` (`dentist_id`,`unavailable_date`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_reply_token` (`reply_token`),
  ADD KEY `fk_inquiry_patient` (`patient_id`),
  ADD KEY `fk_inquiry_service` (`service_id`);

--
-- Indexes for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reply_inquiry` (`inquiry_id`);

--
-- Indexes for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_inquiry_status_name` (`status_name`);

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
  ADD KEY `fk_medical_patient` (`patient_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_messages_auto_reply_for_message_id` (`auto_reply_for_message_id`),
  ADD KEY `fk_msg_patient` (`patient_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_files_patient` (`patient_id`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ins_patient` (`patient_id`),
  ADD KEY `fk_ins_provider` (`provider_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_billing` (`billing_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_prescriptions_consultation_id` (`consultation_id`),
  ADD KEY `fk_presc_patient` (`patient_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_presc_items_presc` (`prescription_id`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_queue_patient` (`patient_id`),
  ADD KEY `idx_queue_date` (`queue_date`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_queue_appointment_id` (`appointment_id`),
  ADD KEY `idx_queue_doctor_id` (`doctor_id`);

--
-- Indexes for table `queue_items`
--
ALTER TABLE `queue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_queue_items_queue` (`queue_id`),
  ADD KEY `fk_queue_service` (`service_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sched_doctor` (`doctor_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_setting_key` (`setting_key`);

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
  ADD KEY `fk_treatment_patient` (`patient_id`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_plan_patient` (`patient_id`);

--
-- Indexes for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_details_plan` (`plan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_users_patient` (`patient_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=876;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `chat_typing_status`
--
ALTER TABLE `chat_typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `content_images`
--
ALTER TABLE `content_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `dental_history`
--
ALTER TABLE `dental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dentist_unavailable`
--
ALTER TABLE `dentist_unavailable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inquiry_statuses`
--
ALTER TABLE `inquiry_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

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
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `queue_items`
--
ALTER TABLE `queue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `teeth`
--
ALTER TABLE `teeth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `fk_billing_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `content_images`
--
ALTER TABLE `content_images`
  ADD CONSTRAINT `fk_images_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `dental_history`
--
ALTER TABLE `dental_history`
  ADD CONSTRAINT `fk_dental_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_unavailable`
--
ALTER TABLE `dentist_unavailable`
  ADD CONSTRAINT `dentist_unavailable_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiry_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inquiry_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD CONSTRAINT `fk_reply_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `fk_medical_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_files`
--
ALTER TABLE `patient_files`
  ADD CONSTRAINT `fk_files_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `fk_ins_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ins_provider` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_billing` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_presc_items_presc` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `fk_queue_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `queue_items`
--
ALTER TABLE `queue_items`
  ADD CONSTRAINT `fk_queue_items_queue` FOREIGN KEY (`queue_id`) REFERENCES `queue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_sched_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `fk_treatment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD CONSTRAINT `fk_plan_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `treatment_plan_details`
--
ALTER TABLE `treatment_plan_details`
  ADD CONSTRAINT `fk_details_plan` FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
