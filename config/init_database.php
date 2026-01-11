<?php
// Database initialization script
// Run this once to create the database and tables
// Usage: php config/init_database.php

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS dental_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'dental_management' created successfully!\n\n";

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Users table created successfully!\n";

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
    echo "✓ Patients table created successfully!\n";

    // Create appointments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT,
        appointment_date DATETIME NOT NULL,
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
    echo "✓ Appointments table created successfully!\n";

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
    echo "✓ Dental history table created successfully!\n";

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
    echo "✓ Medical history table created successfully!\n";

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
    echo "✓ Treatments table created successfully!\n";

    // Create services table
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(200) NOT NULL,
        description TEXT,
        default_cost DECIMAL(10, 2),
        category VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✓ Services table created successfully!\n";

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
    echo "✓ Billing table created successfully!\n";

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
    echo "✓ Payments table created successfully!\n";

    // Insert default users
    echo "\n--- Creating Default Users ---\n";
    
    // Create admin user (username: admin, password: admin123)
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'admin@rfdental.com', 'Administrator', 'admin']);
    echo "✓ Admin user created (username: admin, password: admin123)\n";

    // Create dentist user (username: dentist, password: dentist123)
    $dentistPassword = password_hash('dentist123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['dentist', $dentistPassword, 'dentist@rfdental.com', 'Dentist', 'dentist']);
    echo "✓ Dentist user created (username: dentist, password: dentist123)\n";

    // Create staff user (username: staff, password: staff123)
    $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['staff', $staffPassword, 'staff@rfdental.com', 'Staff Member', 'staff']);
    echo "✓ Staff user created (username: staff, password: staff123)\n";

    // Insert default services
    echo "\n--- Creating Default Services ---\n";
    $defaultServices = [
        ['Dental Cleaning', 'Professional teeth cleaning and polishing', 1500.00, 'Preventive'],
        ['Tooth Extraction', 'Simple tooth extraction procedure', 2000.00, 'Surgery'],
        ['Root Canal', 'Root canal treatment', 8000.00, 'Endodontics'],
        ['Tooth Filling', 'Dental filling for cavities', 2500.00, 'Restorative'],
        ['Denture Adjustment', 'Adjustment of dentures', 1000.00, 'Prosthodontics'],
        ['Follow-up Checkup', 'Routine follow-up examination', 500.00, 'Preventive'],
        ['Teeth Whitening', 'Professional teeth whitening', 5000.00, 'Cosmetic'],
        ['Dental X-Ray', 'Dental radiography', 800.00, 'Diagnostic']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO services (service_name, description, default_cost, category) VALUES (?, ?, ?, ?)");
    foreach ($defaultServices as $service) {
        $stmt->execute($service);
        echo "✓ Service '{$service[0]}' added\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ Database setup completed successfully!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "Default Login Credentials:\n";
    echo "---------------------------\n";
    echo "Admin Account:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    echo "Dentist Account:\n";
    echo "  Username: dentist\n";
    echo "  Password: dentist123\n\n";
    echo "Staff Account:\n";
    echo "  Username: staff\n";
    echo "  Password: staff123\n\n";

} catch (PDOException $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}
?>
