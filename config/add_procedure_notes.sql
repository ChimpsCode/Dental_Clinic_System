-- ============================================
-- Add Procedure Notes Column
-- ============================================

-- Add procedure_notes column to queue table
ALTER TABLE queue ADD COLUMN IF NOT EXISTS procedure_notes TEXT AFTER teeth_numbers;

-- ============================================
-- Migration Complete
-- ============================================
-- This migration adds a column for storing dentist procedure notes
-- Allows dentist to add notes about specific procedures
-- for each patient before starting treatment