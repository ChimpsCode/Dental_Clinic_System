-- The application also adds this column automatically when needed.
ALTER TABLE appointments ADD COLUMN patient_edited_at DATETIME NULL;
