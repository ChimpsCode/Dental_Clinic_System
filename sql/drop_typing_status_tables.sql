-- Typing is ephemeral presence, not stored data. Drop leftover status tables.
DROP TABLE IF EXISTS `chat_typing_status`;
DROP TABLE IF EXISTS `inquiry_typing_status`;
