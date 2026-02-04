-- Active: 1759842436534@@127.0.0.1@3306@dental_management
-- Active: 1759842436534@@127.0.0.1@3306@dental_management
-- Archive System Database Migration
-- Add is_archived and deleted_at columns to all 7 tables
-- Date: 2026-02-04

-- ============================================
-- PATIENTS TABLE
-- ============================================
ALTER TABLE patients 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_patients_archived (is_archived),
ADD INDEX idx_patients_deleted_at (deleted_at);

-- ============================================
-- APPOINTMENTS TABLE
-- ============================================
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_appointments_archived (is_archived),
ADD INDEX idx_appointments_deleted_at (deleted_at);

-- ============================================
-- QUEUE TABLE
-- ============================================
ALTER TABLE queue 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_queue_archived (is_archived),
ADD INDEX idx_queue_deleted_at (deleted_at);

-- ============================================
-- TREATMENT_PLANS TABLE
-- ============================================
ALTER TABLE treatment_plans 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_treatment_plans_archived (is_archived),
ADD INDEX idx_treatment_plans_deleted_at (deleted_at);

-- ============================================
-- SERVICES TABLE
-- ============================================
ALTER TABLE services 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_services_archived (is_archived),
ADD INDEX idx_services_deleted_at (deleted_at);

-- ============================================
-- INQUIRIES TABLE
-- ============================================
ALTER TABLE inquiries 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_inquiries_archived (is_archived),
ADD INDEX idx_inquiries_deleted_at (deleted_at);

-- ============================================
-- USERS TABLE (Dentist/Doctor Records)
-- ============================================
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
ADD INDEX idx_users_archived (is_archived),
ADD INDEX idx_users_deleted_at (deleted_at);

-- ============================================
-- MIGRATION COMPLETE
-- ============================================
-- All tables now have soft delete capability
