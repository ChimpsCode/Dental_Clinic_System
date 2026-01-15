-- Add queue table to existing database
-- Run this in phpMyAdmin

CREATE TABLE IF NOT EXISTS queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    treatment_type VARCHAR(200),
    teeth_numbers VARCHAR(100),
    status ENUM('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') DEFAULT 'waiting',
    priority INT DEFAULT 5,
    queue_time TIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_queue_time (queue_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
