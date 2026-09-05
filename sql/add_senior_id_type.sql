-- Add senior_id_type for existing databases that already ran the original discount migration.
-- Safe to skip if the column already exists.

ALTER TABLE `patients`
    ADD COLUMN `senior_id_type` VARCHAR(40) DEFAULT NULL AFTER `senior_citizen_id`;
