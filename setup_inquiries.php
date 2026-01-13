<?php
/**
 * Database Setup for Inquiries Module
 * Run this file once to create the inquiries table
 */

require_once 'config/database.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact_info VARCHAR(255),
        source ENUM('Facebook', 'Phone Call', 'Walk-in', 'Referral', 'Instagram', 'Messenger') NOT NULL DEFAULT 'Facebook',
        inquiry_message TEXT,
        topic VARCHAR(100) DEFAULT 'General',
        status ENUM('Pending', 'Answered', 'Closed', 'Booked') DEFAULT 'Pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql);
    echo "Inquiries table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
