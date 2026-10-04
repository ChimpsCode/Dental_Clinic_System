-- ============================================================================
-- Connect the audit/session tables to the rest of the schema
-- ----------------------------------------------------------------------------
-- Why: print_logs, sms_log and user_login_devices were created on demand by
-- the application (includes/print_log_helper.php, includes/sms_functions.php,
-- includes/login_device_alert.php) or by separate migration files, so they
-- ended up in the database with indexes but no FOREIGN KEY constraints.
-- A designer (phpMyAdmin / MySQL Workbench / DBeaver) therefore draws them as
-- disconnected boxes even though the columns clearly reference other tables:
--
--   print_logs.user_id            -> users.id
--   print_logs.reference_id       -> billing.id (polymorphic, see below)
--   sms_log.patient_id            -> patients.id
--   sms_log.appointment_id        -> appointments.id
--   sms_log.requested_by          -> users.id
--   user_login_devices.user_id    -> users.id
--
-- print_logs.reference_table / reference_id is intentionally NOT constrained:
-- it is a polymorphic pointer ('billing', 'appointment', ...) and a real FK
-- would need one constraint per target table. That is why the report reads it
-- with a LEFT JOIN in master/admin/admin_reports.php.
--
-- All of the targets are audit/history rows, so every FK uses ON DELETE
-- SET NULL: deleting a user must not erase the record that they existed, and
-- it must never be blocked by history. The single exception is
-- user_login_devices, which is live session state and is worthless without its
-- owner, so it cascades.
--
-- Idempotent: safe to run more than once. Orphaned ids are normalised to NULL
-- first so the constraints can be added on a database that already has data.
-- ============================================================================

SET @db := DATABASE();

-- ---------------------------------------------------------------------------
-- 0. Normalise orphans so the ALTERs below cannot fail on existing data
-- ---------------------------------------------------------------------------
UPDATE `print_logs` pl
  LEFT JOIN `users` u ON u.id = pl.user_id
  SET pl.user_id = NULL
  WHERE pl.user_id IS NOT NULL AND u.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `patients` p ON p.id = sl.patient_id
  SET sl.patient_id = NULL
  WHERE sl.patient_id IS NOT NULL AND p.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `appointments` a ON a.id = sl.appointment_id
  SET sl.appointment_id = NULL
  WHERE sl.appointment_id IS NOT NULL AND a.id IS NULL;

UPDATE `sms_log` sl
  LEFT JOIN `users` u ON u.id = sl.requested_by
  SET sl.requested_by = NULL
  WHERE sl.requested_by IS NOT NULL AND u.id IS NULL;

-- user_login_devices.user_id is NOT NULL, so orphans cannot be nulled. These
-- rows are cached session tokens for an account that no longer exists, so
-- dropping them is the correct normalisation.
DELETE d FROM `user_login_devices` d
  LEFT JOIN `users` u ON u.id = d.user_id
  WHERE u.id IS NULL;

-- ---------------------------------------------------------------------------
-- 1. user_login_devices.user_id -> users.id   (ON DELETE CASCADE)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'user_login_devices'
    AND CONSTRAINT_NAME = 'user_login_devices_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `user_login_devices`
     ADD CONSTRAINT `user_login_devices_ibfk_1`
     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 2. print_logs.user_id -> users.id           (ON DELETE SET NULL)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'print_logs'
    AND CONSTRAINT_NAME = 'print_logs_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `print_logs`
     ADD CONSTRAINT `print_logs_ibfk_1`
     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 3. sms_log -> patients / appointments / users (ON DELETE SET NULL)
-- ---------------------------------------------------------------------------
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_1'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_1`
     FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_2'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_2`
     FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND CONSTRAINT_NAME = 'sms_log_ibfk_3'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE `sms_log`
     ADD CONSTRAINT `sms_log_ibfk_3`
     FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 4. Indexes the new FKs rely on
--    MySQL creates the child index automatically when it is missing, but doing
--    it explicitly keeps the key names predictable across installs.
-- ---------------------------------------------------------------------------
SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'user_login_devices'
    AND COLUMN_NAME = 'user_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `user_login_devices` ADD KEY `idx_user_login_devices_user` (`user_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'print_logs'
    AND COLUMN_NAME = 'user_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `print_logs` ADD KEY `idx_print_logs_user` (`user_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND COLUMN_NAME = 'patient_id'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `sms_log` ADD KEY `idx_sms_log_patient` (`patient_id`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sms_log'
    AND COLUMN_NAME = 'requested_by'
);
SET @sql := IF(@ix = 0,
  'ALTER TABLE `sms_log` ADD KEY `idx_sms_log_requested_by` (`requested_by`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;