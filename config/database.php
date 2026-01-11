<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Function to initialize database tables and admin user
function initializeDatabase($pdo) {
    try {
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
        
        // Create patients table
        $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(100),
            address TEXT,
            date_of_birth DATE,
            gender VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create appointments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            appointment_date DATETIME NOT NULL,
            notes TEXT,
            status VARCHAR(20) DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create default admin user (username: admin, password: admin123)
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'admin@rfdental.com', 'Administrator', 'admin']);  

        // Create default dentist user (username: dentist, password: dentist123)
        $dentistPassword = password_hash('dentist123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['dentist', $dentistPassword, 'dentist@rfdental.com', 'Dentist', 'dentist']);

        // Create staff user (username: staff, password: staff123)
        $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['staff', $staffPassword, 'staff@rfdental.com', 'Staff Member', 'staff']);
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        throw $e;
    }
}

// Only connect if not already connected
if (!isset($pdo)) {
    try {
        // Try to connect to the database directly
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // If database doesn't exist, try to create it
        if ($e->getCode() == 1049) { // Error code for unknown database
            try {
                // Connect without database
                $dsn_temp = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                $pdo_temp = new PDO($dsn_temp, DB_USER, DB_PASS);
                $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database
                $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo_temp = null;
                
                // Try connecting again
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // Initialize tables and admin user for new database
                initializeDatabase($pdo);
            } catch (PDOException $e2) {
                // Log the error
                error_log("Database connection failed: " . $e2->getMessage());
                $pdo = null;
                throw $e2;
            }
        } else {
            // Log the error
            error_log("Database connection failed: " . $e->getMessage());
            $pdo = null;
            throw $e;
        }
    }
    
    // Initialize database tables and admin user if needed
    if (isset($pdo)) {
        try {
            // Check if users table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() == 0) {
                initializeDatabase($pdo);
            } else {
                // Check if dentist user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'dentist' LIMIT 1");
                $stmt->execute();
                if ($stmt->rowCount() == 0) {
                    // Create dentist user
                    $dentistPassword = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute(['admin', $dentistPassword, 'admin@rfdental.com', 'Administrator', 'admin']);
                }
                
                // Check and create patients table if it doesn't exist
                $stmt = $pdo->query("SHOW TABLES LIKE 'patients'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        full_name VARCHAR(100) NOT NULL,
                        phone VARCHAR(20),
                        email VARCHAR(100),
                        address TEXT,
                        date_of_birth DATE,
                        gender VARCHAR(10),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                }
                
                // Check and create appointments table if it doesn't exist
                $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        patient_id INT,
                        appointment_date DATETIME NOT NULL,
                        notes TEXT,
                        status VARCHAR(20) DEFAULT 'scheduled',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                }
            }
        } catch (PDOException $e) {
            // Log but don't throw - table might already exist
            error_log("Database initialization check failed: " . $e->getMessage());
        }
    }
}
?>

