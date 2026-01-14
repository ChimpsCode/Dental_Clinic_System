<?php
/**
 * Setup Treatment Plans Table
 * Run this file once to create the treatment_plans table
 */

require_once 'config/database.php';

try {
    // Create treatment_plans table
    $sql = "
        CREATE TABLE IF NOT EXISTS treatment_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            treatment_name VARCHAR(200) NOT NULL,
            treatment_type VARCHAR(100),
            teeth_numbers VARCHAR(100),
            total_sessions INT DEFAULT 1,
            completed_sessions INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            next_session_date DATE,
            estimated_cost DECIMAL(10, 2),
            notes TEXT,
            doctor_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_patient_id (patient_id),
            INDEX idx_status (status),
            INDEX idx_next_session (next_session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    
    echo "Treatment plans table created successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
