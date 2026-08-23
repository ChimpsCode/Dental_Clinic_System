-- Senior / PWD Discount Migration (RA 9994 & RA 10754)
-- Adds patient discount ID fields and billing discount breakdown columns

ALTER TABLE `patients`
    ADD COLUMN `senior_citizen_id` VARCHAR(30) DEFAULT NULL AFTER `date_of_birth`,
    ADD COLUMN `pwd_id` VARCHAR(30) DEFAULT NULL AFTER `senior_citizen_id`;

ALTER TABLE `billing`
    ADD COLUMN `subtotal_amount` DECIMAL(10,2) DEFAULT NULL AFTER `total_amount`,
    ADD COLUMN `discount_type` ENUM('none','senior','pwd') NOT NULL DEFAULT 'none' AFTER `subtotal_amount`,
    ADD COLUMN `discount_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`,
    ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_rate`,
    ADD COLUMN `discount_id_no` VARCHAR(50) DEFAULT NULL AFTER `discount_amount`,
    ADD COLUMN `discount_holder_name` VARCHAR(150) DEFAULT NULL AFTER `discount_id_no`;

-- Allow 'discount' entries in audit log
ALTER TABLE `audit_logs`
    MODIFY COLUMN `action_type` ENUM('login','logout','create','read','update','delete','payment','status_change','failed_login','discount') DEFAULT NULL;
