<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Only connect if not already connected
if (!isset($pdo)) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Better error handling - don't expose database details in production
        error_log("Database connection failed: " . $e->getMessage());
        if (defined('DEBUG') && DEBUG) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            die("Database connection failed. Please contact administrator.");
        }
    }
}
?>

