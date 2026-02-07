<?php
require_once 'config/database.php';

try {
    // Check if procedure_notes column exists in queue table
    $stmt = $pdo->query("SHOW COLUMNS FROM queue LIKE 'procedure_notes'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ procedure_notes column exists in queue table\n";
        echo "Column info: " . json_encode($result) . "\n";
    } else {
        echo "❌ procedure_notes column NOT FOUND in queue table\n";
        echo "You need to run the SQL migration to add this column\n";
        
        echo "\n📝 SQL to run in phpMyAdmin:\n";
        echo "ALTER TABLE queue ADD COLUMN IF NOT EXISTS procedure_notes TEXT AFTER teeth_numbers;\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking database: " . $e->getMessage() . "\n";
}
?>