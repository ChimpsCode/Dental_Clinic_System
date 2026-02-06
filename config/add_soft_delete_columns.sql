-- Add soft delete columns for staff delete functionality
-- Run this SQL in phpMyAdmin or MySQL client

ALTER TABLE `appointments` 
ADD COLUMN `deleted_by_staff` TINYINT(1) DEFAULT 0 AFTER `created_by`,
ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `deleted_by_staff`;

-- Add index for better query performance
ALTER TABLE `appointments` ADD INDEX `idx_deleted_by_staff` (`deleted_by_staff`);



-- can I delete this file ??????????? 