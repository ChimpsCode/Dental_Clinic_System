<?php
/**
 * Debug script to check if staff user exists and has correct role
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'dental_management';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if staff user exists
    $stmt = $pdo->prepare("SELECT id, username, role, full_name FROM users WHERE username = 'staff'");
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff) {
        echo "<h2>✓ Staff user found:</h2>";
        echo "<pre>";
        print_r($staff);
        echo "</pre>";
    } else {
        echo "<h2>✗ Staff user NOT found!</h2>";
        echo "<p>Adding staff user now...</p>";
        
        // Add staff user
        $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['staff', $staffPassword, 'staff@rfdental.com', 'Staff', 'staff']);
        
        echo "<h3>✓ Staff user created successfully!</h3>";
        echo "<p><strong>Username:</strong> staff</p>";
        echo "<p><strong>Password:</strong> staff123</p>";
    }

    // Also show all users
    echo "<hr>";
    echo "<h2>All users in database:</h2>";
    $stmt = $pdo->prepare("SELECT id, username, role, full_name FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Full Name</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['role']}</td><td>{$u['full_name']}</td></tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
