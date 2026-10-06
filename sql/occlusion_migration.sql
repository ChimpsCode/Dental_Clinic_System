-- Existing checkbox values stay intact; do not interpret 1 as Class I.
-- The consultation page/API also adds missing columns automatically.
ALTER TABLE consultations
  ADD COLUMN IF NOT EXISTS occlusion_molar_class VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS occlusion_notes TEXT DEFAULT NULL;
