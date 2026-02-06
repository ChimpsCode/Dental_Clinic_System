-- Active: 1759842436534@@127.0.0.1@3306@dental_management
-- Add is_registered_patient column to appointments table
-- This tracks if an appointment patient has been through new admission

ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS is_registered_patient TINYINT(1) DEFAULT 0 NOT NULL,
ADD INDEX idx_is_registered_patient (is_registered_patient);

-- Add converted_patient_id column to track relationship
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS converted_patient_id INT NULL,
ADD INDEX idx_converted_patient_id (converted_patient_id);
