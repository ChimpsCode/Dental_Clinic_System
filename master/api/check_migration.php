<?php
/**
 * Database Migration Check
 * Run this to verify archive columns exist
 */

require_once 'config/database.php';

echo "<h2>Database Migration Check</h2>";
echo "<pre>";

$tables = ['patients', 'appointments', 'queue', 'treatment_plans', 'services', 'inquiries', 'users'];

foreach ($tables as $table) {
    echo "\n=== TABLE: $table ===\n";
    
    // Check for is_archived column
    $checkArchived = $pdo->query("SHOW COLUMNS FROM $table LIKE 'is_archived'");
    $hasArchived = $checkArchived->rowCount() > 0;
    
    // Check for deleted_at column
    $checkDeleted = $pdo->query("SHOW COLUMNS FROM $table LIKE 'deleted_at'");
    $hasDeleted = $checkDeleted->rowCount() > 0;
    
    // Count archived records
    $archivedCount = 0;
    if ($hasArchived) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE is_archived = 1");
        $archivedCount = $stmt->fetchColumn();
    }
    
    echo "is_archived column: " . ($hasArchived ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "deleted_at column: " . ($hasDeleted ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "Archived records: $archivedCount\n";
}

echo "\n=== TOTAL ARCHIVED RECORDS ===\n";
foreach ($tables as $table) {
    $checkArchived = $pdo->query("SHOW COLUMNS FROM $table LIKE 'is_archived'");
    if ($checkArchived->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE is_archived = 1");
        $count = $stmt->fetchColumn();
        echo "$table: $count\n";
    }
}

echo "</pre>";

echo "<h3>Instructions:</h3>";
echo "<p>If you see ❌ MISSING above, you need to run the migration:</p>";
echo "<ol>";
echo "<li>Open phpMyAdmin</li>";
echo "<li>Select 'dental_management' database</li>";
echo "<li>Go to SQL tab</li>";
echo "<li>Paste contents of: config/add_archive_columns.sql</li>";
echo "<li>Click 'Go'</li>";
echo "</ol>";
?>