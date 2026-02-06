-- PATIENT REGISTRATION WORKFLOW MIGRATION
-- Run this in phpMyAdmin to enable the appointment-to-patient workflow
-- Date: 2026-02-05

-- ============================================
-- STEP 1: Add columns to patients table
-- This tracks where patients came from
-- ============================================
ALTER TABLE patients 
ADD COLUMN registration_source VARCHAR(50) DEFAULT 'direct' NULL,
ADD COLUMN source_appointment_id INT NULL;

-- ============================================
-- STEP 2: Add columns to appointments table  
-- This tracks if appointment was converted to patient
-- ============================================
ALTER TABLE appointments 
ADD COLUMN is_converted_to_patient TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN converted_patient_id INT NULL;

-- ============================================
-- STEP 3: Mark existing patients as direct registrations
-- ============================================
UPDATE patients SET registration_source = 'direct' WHERE registration_source IS NULL;

-- ============================================
-- DONE! The workflow is now enabled.
-- ============================================
