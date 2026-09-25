-- Medical certificate issued during a consultation, plus the bookable service.

CREATE TABLE IF NOT EXISTS `medical_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `purpose` varchar(100) NOT NULL DEFAULT 'Employment',
  `findings` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `rest_from` date DEFAULT NULL,
  `rest_to` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `issued_at` date DEFAULT NULL,
  `dentist_name` varchar(150) DEFAULT NULL,
  `dentist_signature` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medcert_consultation` (`consultation_id`),
  KEY `idx_medcert_patient` (`patient_id`),
  KEY `idx_medcert_dentist` (`dentist_id`),
  CONSTRAINT `fk_medcert_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_medcert_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_medcert_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `services` (`name`, `mode`, `price`, `duration_minutes`, `description`, `is_active`, `is_archived`)
SELECT 'Medical Certificate', 'NONE', 0.00, 20, 'Certificate for employment, issued after an in-clinic visit.', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `services` WHERE `name` = 'Medical Certificate');
