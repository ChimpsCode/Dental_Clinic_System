<?php
// Database configuration
// Source of truth: sql/master_rf.sql

define('DB_HOST', 'localhost');
define('DB_NAME', 'master_rf');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connect only - do not create tables automatically
// Use sql/master_rf.sql for schema reference

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
        error_log("Database connection failed: " . $e->getMessage());
        $pdo = null;
        throw $e;
    }
}
?>