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
