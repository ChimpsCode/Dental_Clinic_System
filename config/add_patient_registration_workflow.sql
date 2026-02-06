-- Workflow Update: Appointment to Patient Registration
-- This ensures appointment patients only appear in patient records after new admission

-- 1. Add source tracking to patients table
ALTER TABLE patients 
ADD COLUMN IF NOT EXISTS registration_source VARCHAR(50) DEFAULT 'direct' NULL,
ADD COLUMN IF NOT EXISTS source_appointment_id INT NULL,
ADD INDEX idx_registration_source (registration_source),
ADD INDEX idx_source_appointment_id (source_appointment_id);

-- 2. Add processed flag to appointments
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS is_converted_to_patient TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS converted_patient_id INT NULL,
ADD INDEX idx_is_converted (is_converted_to_patient),
ADD INDEX idx_converted_patient (converted_patient_id);

-- Update existing patients to mark them as direct registrations
-- (assuming existing patients were properly registered)
UPDATE patients SET registration_source = 'direct' WHERE registration_source IS NULL;
