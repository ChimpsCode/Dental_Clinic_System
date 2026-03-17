-- Add user_id column to patients table to link with users table
ALTER TABLE `patients` 
ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `id`,
ADD INDEX `idx_user_id` (`user_id`),
ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;