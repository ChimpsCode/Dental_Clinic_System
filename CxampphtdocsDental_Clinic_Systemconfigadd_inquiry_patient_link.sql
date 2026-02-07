-- Add converted_patient_id to inquiries table
-- This links an inquiry to the patient record it became after New Admission

ALTER TABLE inquiries 
ADD COLUMN IF NOT EXISTS converted_patient_id INT NULL AFTER status,
ADD INDEX idx_converted_patient (converted_patient_id),
ADD CONSTRAINT fk_inquiry_converted_patient 
    FOREIGN KEY (converted_patient_id) 
    REFERENCES patients(id) 
    ON DELETE SET NULL;

-- ============================================
-- MIGRATION COMPLETE
-- ============================================
-- Now inquiries can be linked to patients:
-- inquiries.converted_patient_id → patients.id
