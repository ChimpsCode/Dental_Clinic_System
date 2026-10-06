-- Consultation Tables Migration
-- Run this SQL to add the consultations table

CREATE TABLE IF NOT EXISTS `consultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `status` enum('in_progress','completed') DEFAULT 'in_progress',

  -- Dental Examination - Periodontal
  `periodontal_screening` varchar(50) DEFAULT NULL,

  -- Dental Examination - Occlusion
  `occlusion_class_molar` tinyint(1) DEFAULT 0,
  `occlusion_molar_class` varchar(20) DEFAULT NULL,
  `occlusion_notes` text DEFAULT NULL,
  `occlusion_overjet` tinyint(1) DEFAULT 0,
  `occlusion_overbite` tinyint(1) DEFAULT 0,
  `occlusion_midline_deviation` tinyint(1) DEFAULT 0,
  `occlusion_crossbite` tinyint(1) DEFAULT 0,

  -- Dental Examination - Appliances
  `appliances_orthodontic` tinyint(1) DEFAULT 0,
  `appliances_skyplate` tinyint(1) DEFAULT 0,
  `appliances_others` tinyint(1) DEFAULT 0,
  `appliances_others_text` varchar(255) DEFAULT NULL,

  -- Dental Examination - TMD
  `tmd_clenching` tinyint(1) DEFAULT 0,
  `tmd_clicking` tinyint(1) DEFAULT 0,
  `tmd_trismus` tinyint(1) DEFAULT 0,
  `tmd_muscle_spasm` tinyint(1) DEFAULT 0,

  -- Tooth data (JSON)
  `teeth_data` json DEFAULT NULL,

  -- Notes
  `consultation_notes` text DEFAULT NULL,

  -- Dental & Medical History
  `dental_medical_history_notes` text DEFAULT NULL,

  -- Prescription
  `prescription_data` json DEFAULT NULL,

  -- Informed Consent
  `consent_provider` varchar(255) DEFAULT NULL,
  `consent_patient_address` text DEFAULT NULL,
  `consent_procedure` text DEFAULT NULL,
  `consent_by_guardian` tinyint(1) DEFAULT 0,
  `consent_guardian_name` varchar(255) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_date` date DEFAULT NULL,
  `consent_place` varchar(255) DEFAULT NULL,
  `consent_patient_signature` varchar(255) DEFAULT NULL,
  `consent_doctor_signature` varchar(255) DEFAULT NULL,
  `consent_witness_signature` varchar(255) DEFAULT NULL,
  `consent_patient_sign_name` varchar(255) DEFAULT NULL,
  `consent_witness_sign_name` varchar(255) DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `idx_consultations_queue_id` (`queue_id`),
  KEY `idx_consultations_patient_id` (`patient_id`),
  KEY `idx_consultations_dentist_id` (`dentist_id`),
  KEY `idx_consultations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
