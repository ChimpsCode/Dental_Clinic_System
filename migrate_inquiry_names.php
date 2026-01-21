<?php
// Migration script to update inquiries table with separate name fields

try {
    require_once 'config/database.php';

    // Start transaction
    $pdo->beginTransaction();

    // Add new columns first (to avoid errors if data exists)
    $pdo->exec("ALTER TABLE inquiries ADD COLUMN first_name VARCHAR(100) AFTER id");
    $pdo->exec("ALTER TABLE inquiries ADD COLUMN middle_name VARCHAR(100) AFTER first_name");
    $pdo->exec("ALTER TABLE inquiries ADD COLUMN last_name VARCHAR(100) AFTER middle_name");

    // Copy existing name data to the new columns
    // We'll split by spaces - first word as first_name, last word as last_name, middle as middle_name
    $stmt = $pdo->query("SELECT id, name FROM inquiries");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parts = explode(' ', trim($row['name']), 3);
        $firstName = $parts[0] ?? '';
        $middleName = $parts[1] ?? '';
        $lastName = $parts[2] ?? '';

        $updateStmt = $pdo->prepare("UPDATE inquiries SET first_name = ?, middle_name = ?, last_name = ? WHERE id = ?");
        $updateStmt->execute([$firstName, $middleName, $lastName, $row['id']]);
    }

    // Update source enum to include new values
    $pdo->exec("ALTER TABLE inquiries MODIFY source ENUM('Fb messenger', 'Phone call', 'Walk-in', 'Facebook', 'Phone Call', 'Walk-in', 'Referral', 'Instagram', 'Messenger') NOT NULL DEFAULT 'Fb messenger'");

    // Update status enum to include New Admission and remove Closed
    $pdo->exec("ALTER TABLE inquiries MODIFY status ENUM('Pending', 'Answered', 'Booked', 'New Admission') NOT NULL DEFAULT 'Pending'");

    // Now drop the old name column
    $pdo->exec("ALTER TABLE inquiries DROP COLUMN name");

    // Commit transaction
    $pdo->commit();

    echo "Migration completed successfully!";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage();
}
?>
