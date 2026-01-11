<?php
// Database initialization script
// Run this once to create the database and tables

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS dental_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully!\n";

    // Use the database
    $pdo->exec("USE dental_management");

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        full_name VARCHAR(100),
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Users table created successfully!\n";

    // Create default admin user (username: dentist, password: dentist123)
    $adminPassword = password_hash('dentist123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['dentist', $adminPassword, 'dentist@rfdental.com', 'Dentist', 'dentist']);

    // Create staff user (username: staff, password: staff123)
    $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['staff', $staffPassword, 'staff@rfdental.com', 'Staff Member', 'staff']);

    echo "Users created successfully!\n";
    echo "\nDentist Account:\n";
    echo "Username: dentist\n";
    echo "Password: dentist123\n";
    echo "\nStaff Account:\n";
    echo "Username: staff\n";
    echo "Password: staff123\n";
    echo "\nSetup completed successfully!\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>

