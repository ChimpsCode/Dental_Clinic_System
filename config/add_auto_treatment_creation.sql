-- ============================================
-- Add Auto-Treatment Creation Columns to Queue
-- ============================================

-- Add completed_at timestamp to queue table
ALTER TABLE queue ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL DEFAULT NULL AFTER status;

-- Add is_processed flag to prevent duplicate treatment creation
ALTER TABLE queue ADD COLUMN IF NOT EXISTS is_processed TINYINT(1) DEFAULT 0 AFTER completed_at;

-- ============================================
-- Migration Complete
-- ============================================
-- This enables automatic creation of treatment records
-- when queue items are marked as 'completed'

-- Test: SELECT id, status, is_processed FROM queue LIMIT 5;