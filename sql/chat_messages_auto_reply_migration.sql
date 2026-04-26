-- Chat auto-reply migration
-- Run this once on your database before testing AJAX auto-reply.

ALTER TABLE `messages`
    ADD COLUMN `sender_id` INT NULL AFTER `id`,
    ADD COLUMN `receiver_id` INT NULL AFTER `sender_id`,
    ADD COLUMN `sender_type` ENUM('patient','admin','system') NOT NULL DEFAULT 'patient' AFTER `message`,
    ADD COLUMN `auto_reply_for_message_id` INT NULL AFTER `sender_type`;

-- Backfill sender_type from old sent_by values (safe for mixed old data)
UPDATE `messages`
SET `sender_type` = CASE
    WHEN `sent_by` = 'patient' THEN 'patient'
    WHEN `sent_by` = 'admin' THEN 'admin'
    ELSE 'system'
END
WHERE `sender_type` IS NULL OR `sender_type` = '';

-- Optional but recommended: enforce one auto-reply per patient message
CREATE UNIQUE INDEX `uq_messages_auto_reply_for_message_id`
ON `messages` (`auto_reply_for_message_id`);

