<?php
/**
 * Run migration to add service_id column to queue table
 * Run this ONCE, then delete this file
 */

require_once __DIR__ . '/../config/database.php';

echo "Running migration: Add service_id to queue table\n";

try {
    // Check if column already exists
    $check = $pdo->query("DESCRIBE queue");
    $columns = $check->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('service_id', $columns)) {
        echo "Column 'service_id' already exists in queue table.\n";
    } else {
        // Add service_id column
        $pdo->exec("ALTER TABLE queue ADD COLUMN service_id INT(11) DEFAULT NULL AFTER treatment_type");
        echo "Added 'service_id' column to queue table.\n";
    }
    
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
?>