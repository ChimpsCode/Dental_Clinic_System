-- Connect floating ERD lookup tables with foreign keys.
-- Schema-only. Does not change PHP. Leaves settings standalone.

-- 1) Auto-cancel writes appointments.status_id = 5. Lookup currently stops at id 4
--    and status_name is UNIQUE, so id 5 cannot reuse 'Cancelled'.
INSERT INTO `appointment_statuses` (`id`, `status_name`)
VALUES (5, 'Auto-Cancelled')
ON DUPLICATE KEY UPDATE `status_name` = VALUES(`status_name`);

-- 2) Live inquiries table has status names but no status_id column.
ALTER TABLE `inquiries`
  ADD COLUMN `status_id` INT(11) DEFAULT NULL AFTER `status`;

ALTER TABLE `inquiries`
  ADD INDEX `idx_inquiries_status_id` (`status_id`);

UPDATE `inquiries` i
INNER JOIN `inquiry_statuses` s ON s.status_name = i.status
SET i.status_id = s.id
WHERE i.status_id IS NULL;

-- 3) Remove session-timeout row whose user no longer exists.
DELETE s FROM `user_session_settings` s
LEFT JOIN `users` u ON u.id = s.user_id
WHERE u.id IS NULL;

-- 4) Status lookup FKs (nullable; app already writes NULL when a name is missing).
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_status`
  FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inquiry_status`
  FOREIGN KEY (`status_id`) REFERENCES `inquiry_statuses` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- 5) Per-user session FK. CASCADE so user delete is not blocked.
ALTER TABLE `user_session_settings`
  ADD CONSTRAINT `fk_user_session_user`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- 6) ICD-10 FKs on stored code strings. Indexes required by InnoDB.
ALTER TABLE `consultations`
  ADD INDEX `idx_consultations_primary_icd10` (`primary_icd10`);

ALTER TABLE `consultations`
  ADD INDEX `idx_consultations_secondary_icd10` (`secondary_icd10`);

ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_primary_icd10`
  FOREIGN KEY (`primary_icd10`) REFERENCES `icd10_codes` (`code`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_secondary_icd10`
  FOREIGN KEY (`secondary_icd10`) REFERENCES `icd10_codes` (`code`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;
