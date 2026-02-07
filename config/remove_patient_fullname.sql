-- Migration: Remove full_name column from patients table
-- Date: 2026-02-06
-- Purpose: Replace full_name with separate first_name, middle_name, last_name, suffix fields

-- First, ensure all existing records have the separate name fields populated
-- (This assumes full_name was properly split when records were created)

-- Remove the full_name column
ALTER TABLE patients DROP COLUMN full_name;

-- Note: Make sure first_name, middle_name, last_name, suffix columns already exist
-- If they don't exist, run this first:
-- ALTER TABLE patients ADD COLUMN first_name VARCHAR(100) AFTER id;
-- ALTER TABLE patients ADD COLUMN middle_name VARCHAR(100) AFTER first_name;
-- ALTER TABLE patients ADD COLUMN last_name VARCHAR(100) AFTER middle_name;
-- ALTER TABLE patients ADD COLUMN suffix VARCHAR(50) AFTER last_name;
