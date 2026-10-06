-- All plan sessions reserve normal appointments. Existing plan/session links
-- are recovered by ensureTreatmentPlanWorkflowSchema() when the app runs.
ALTER TABLE appointments
  ADD COLUMN IF NOT EXISTS treatment_plan_id INT NULL,
  ADD COLUMN IF NOT EXISTS treatment_plan_session INT NULL;
ALTER TABLE appointments ADD UNIQUE INDEX IF NOT EXISTS uq_treatment_plan_session (treatment_plan_id, treatment_plan_session);
ALTER TABLE treatment_plans MODIFY overall_status
  ENUM('Active','In-Progress','Completed','On-Hold','Cancelled') DEFAULT 'Active';
