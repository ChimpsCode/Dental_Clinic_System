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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create patients table
        $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100),
            middle_name VARCHAR(100),
            last_name VARCHAR(100),
            suffix VARCHAR(10),
            full_name VARCHAR(200) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(100),
            address TEXT,
            city VARCHAR(100),
            province VARCHAR(100),
            zip_code VARCHAR(20),
            date_of_birth DATE,
            age INT,
            gender VARCHAR(10),
            religion VARCHAR(50),
            dental_insurance VARCHAR(100),
            insurance_effective_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_full_name (full_name),
            INDEX idx_phone (phone),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create appointments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            treatment VARCHAR(100) DEFAULT 'General Checkup',
            notes TEXT,
            status VARCHAR(20) DEFAULT 'scheduled',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_appointment_date (appointment_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create dental_history table
        $pdo->exec("CREATE TABLE IF NOT EXISTS dental_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            previous_dentist VARCHAR(100),
            last_visit_date DATE,
            reason_last_visit TEXT,
            previous_treatments TEXT,
            current_complaints TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_patient_id (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create medical_history table
        $pdo->exec("CREATE TABLE IF NOT EXISTS medical_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            allergies TEXT,
            current_medications TEXT,
            medical_conditions TEXT,
            blood_pressure VARCHAR(20),
            heart_rate VARCHAR(20),
            other_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_patient_id (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create treatments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS treatments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            treatment_date DATE NOT NULL,
            procedure_name VARCHAR(200) NOT NULL,
            tooth_number VARCHAR(20),
            description TEXT,
            status VARCHAR(20) DEFAULT 'completed',
            doctor_id INT,
            cost DECIMAL(10, 2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_patient_id (patient_id),
            INDEX idx_treatment_date (treatment_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create services table (with mode for tooth selection handling)
        $pdo->exec("CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            mode ENUM('BULK', 'SINGLE', 'NONE') DEFAULT 'SINGLE',
            price DECIMAL(10,2) DEFAULT 0.00,
            duration_minutes INT DEFAULT 30,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mode (mode),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create billing table
        $pdo->exec("CREATE TABLE IF NOT EXISTS billing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            treatment_id INT,
            appointment_id INT,
            total_amount DECIMAL(10, 2) NOT NULL,
            paid_amount DECIMAL(10, 2) DEFAULT 0.00,
            balance DECIMAL(10, 2) DEFAULT 0.00,
            payment_status VARCHAR(20) DEFAULT 'pending',
            billing_date DATE NOT NULL,
            due_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE SET NULL,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
            INDEX idx_patient_id (patient_id),
            INDEX idx_payment_status (payment_status),
            INDEX idx_billing_date (billing_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create payments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            billing_id INT NOT NULL,
            patient_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(50),
            payment_date DATE NOT NULL,
            reference_number VARCHAR(100),
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (billing_id) REFERENCES billing(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_patient_id (patient_id),
            INDEX idx_payment_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create treatment_plans table
        $pdo->exec("CREATE TABLE IF NOT EXISTS treatment_plans (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create inquiries table
        $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact_info VARCHAR(255),
            source ENUM('Facebook', 'Phone Call', 'Walk-in', 'Referral', 'Instagram', 'Messenger') NOT NULL DEFAULT 'Facebook',
            inquiry_message TEXT,
            topic VARCHAR(100) DEFAULT 'General',
            status ENUM('Pending', 'Answered', 'Closed', 'Booked') DEFAULT 'Pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_source (source),
            INDEX idx_created_at (created_at)
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
        
        // Insert default services with proper mode
        $defaultServices = [
            ['Tooth Extraction', 'SINGLE', 2000.00, 30, 'Simple tooth extraction procedure'],
            ['Root Canal Treatment', 'SINGLE', 8000.00, 60, 'Root canal therapy for infected teeth'],
            ['Oral Prophylaxis (Teeth Cleaning)', 'BULK', 1500.00, 45, 'Professional teeth cleaning and polishing'],
            ['Denture Adjustment', 'BULK', 1000.00, 30, 'Adjustment and repair of dentures'],
            ['Dental X-Ray', 'SINGLE', 800.00, 15, 'Periapical or bitewing X-ray'],
            ['Braces Consultation', 'BULK', 500.00, 30, 'Orthodontic consultation and assessment'],
            ['Tooth Restoration', 'SINGLE', 2500.00, 30, 'Dental filling for cavities'],
            ['Crowns', 'SINGLE', 8500.00, 60, 'Dental crown placement'],
            ['Fixed Bridge', 'SINGLE', 25000.00, 90, 'Fixed dental bridge installation'],
            ['Teeth Whitening', 'BULK', 5000.00, 60, 'Professional teeth whitening treatment'],
            ['Consultation', 'NONE', 500.00, 15, 'General dental consultation'],
            ['Periapical Xray', 'SINGLE', 800.00, 15, 'Periapical radiograph']
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO services (name, mode, price, duration_minutes, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultServices as $service) {
            $stmt->execute($service);
        }
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
            // Check if users table exists - if not, initialize all tables
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() == 0) {
                initializeDatabase($pdo);
            } else {
                // Check if all required tables exist, create missing ones
                $requiredTables = ['users', 'patients', 'appointments', 'dental_history', 'medical_history', 'treatments', 'services', 'billing', 'payments', 'treatment_plans', 'inquiries', 'queue'];
                $stmt = $pdo->query("SHOW TABLES");
                $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $missingTables = array_diff($requiredTables, $existingTables);
                
                // If any tables are missing, reinitialize to ensure all tables exist
                if (!empty($missingTables)) {
                    // Just create the missing tables by calling initializeDatabase
                    // It uses CREATE TABLE IF NOT EXISTS so it won't break existing tables
                    initializeDatabase($pdo);
                }
                
                // Ensure default users exist
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
                $stmt->execute();
                if ($stmt->rowCount() == 0) {
                    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute(['admin', $adminPassword, 'admin@rfdental.com', 'Administrator', 'admin']);
                }
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'dentist' LIMIT 1");
                $stmt->execute();
                if ($stmt->rowCount() == 0) {
                    $dentistPassword = password_hash('dentist123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute(['dentist', $dentistPassword, 'dentist@rfdental.com', 'Dentist', 'dentist']);
                }
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'staff' LIMIT 1");
                $stmt->execute();
                if ($stmt->rowCount() == 0) {
                    $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute(['staff', $staffPassword, 'staff@rfdental.com', 'Staff Member', 'staff']);
                }
            }
        } catch (PDOException $e) {
            // Log but don't throw - table might already exist
            error_log("Database initialization check failed: " . $e->getMessage());
        }
    }
}
?>

