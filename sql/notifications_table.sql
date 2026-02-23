-- Notifications Table for Dental Clinic System
-- Run this SQL to create the notifications table

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample data (optional - for testing)
-- INSERT INTO notifications (user_id, type, title, message, action_url) VALUES
-- (1, 'appointment', 'New Appointment', 'A new appointment has been scheduled', 'admin_appointments.php'),
-- (2, 'payment', 'Pending Payment', 'A payment is pending review', 'admin_payment.php'),
-- (3, 'inquiry', 'New Inquiry', 'A new inquiry has been received', 'admin_inquiries.php');
