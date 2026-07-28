-- Signature Pad Migration
-- Changes consultation signature columns from varchar(255) to MEDIUMTEXT
-- Adds signature_data column to users table for persistent doctor signature

-- 1. Alter consultations signature columns to hold base64 image data
ALTER TABLE consultations
  MODIFY consent_patient_signature MEDIUMTEXT DEFAULT NULL,
  MODIFY consent_doctor_signature MEDIUMTEXT DEFAULT NULL,
  MODIFY consent_witness_signature MEDIUMTEXT DEFAULT NULL;

-- 2. Add persistent signature column to users table
ALTER TABLE users ADD COLUMN signature_data MEDIUMTEXT DEFAULT NULL AFTER is_archived;
