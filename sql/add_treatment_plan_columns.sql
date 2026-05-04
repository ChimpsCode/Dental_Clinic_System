-- Add next_session_date and next_session_time columns to treatment_plans table
ALTER TABLE `treatment_plans` 
ADD COLUMN `next_session_date` DATE DEFAULT NULL,
ADD COLUMN `next_session_time` TIME DEFAULT NULL,
ADD COLUMN `appointment_id` INT(11) DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- Add foreign key to link treatment plan to appointment
ALTER TABLE `treatment_plans` 
ADD CONSTRAINT `fk_treatment_plan_appointment` 
FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON SET NULL;