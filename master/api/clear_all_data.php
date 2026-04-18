<?php
/**
 * Clear All Transactional Data
 * Simple script to reset all transactions to zero
 * This removes all example/reference data
 */

require_once 'config/database.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    if ($_POST['confirm'] === 'YES') {
        try {
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Delete all transactional data in correct order
            
            // 1. Delete payments first (references billing)
            $pdo->exec("TRUNCATE TABLE payments");
            
            // 2. Delete billing records
            $pdo->exec("TRUNCATE TABLE billing");
            
            // 3. Delete treatments
            $pdo->exec("TRUNCATE TABLE treatments");
            
            // 4. Delete medical history
            $pdo->exec("TRUNCATE TABLE medical_history");
            
            // 5. Delete dental history
            $pdo->exec("TRUNCATE TABLE dental_history");
            
            // 6. Delete appointments
            $pdo->exec("TRUNCATE TABLE appointments");
            
            // 7. Delete patients (this will cascade delete related records)
            $pdo->exec("TRUNCATE TABLE patients");
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $success = true;
            $message = "All transactional data has been cleared successfully! The system is now back to zero.";
            
        } catch (PDOException $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); // Re-enable in case of error
            $error = "Error clearing data: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Please type 'YES' to confirm.";
    }
}

// Get current counts
$counts = [];
try {
    $counts['patients'] = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $counts['appointments'] = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $counts['treatments'] = $pdo->query("SELECT COUNT(*) FROM treatments")->fetchColumn();
    $counts['billing'] = $pdo->query("SELECT COUNT(*) FROM billing")->fetchColumn();
    $counts['payments'] = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    $counts['dental_history'] = $pdo->query("SELECT COUNT(*) FROM dental_history")->fetchColumn();
    $counts['medical_history'] = $pdo->query("SELECT COUNT(*) FROM medical_history")->fetchColumn();
} catch (PDOException $e) {
    $error = "Error getting counts: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear All Data - RF Dental Clinic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-box h2 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .info-box {
            background: #e7f3ff;
            border: 2px solid #2196F3;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0d47a1;
            margin-bottom: 15px;
        }
        .data-list {
            list-style: none;
            padding: 0;
        }
        .data-list li {
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
        }
        .data-list li:last-child {
            border-bottom: none;
        }
        .count {
            font-weight: bold;
            color: #2196F3;
        }
        .form-group {
            margin: 30px 0;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .success-message {
            text-align: center;
            padding: 20px;
        }
        .success-message h2 {
            color: #28a745;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-message">
                <h2>✓ Success!</h2>
                <p><?php echo $message; ?></p>
                <p style="margin-top: 20px; color: #666;">
                    All example/reference data has been removed. The system is now clean and ready for new data.
                </p>
                <a href="dashboard.php" class="btn btn-secondary" style="margin-top: 20px;">
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <h1>🗑️ Clear All Transactional Data</h1>
            <p class="subtitle">Remove all example/reference data and reset the system to zero</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>✗ Error:</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="warning-box">
                <h2>⚠️ Warning: This will delete all transactional data!</h2>
                <p>The following will be <strong>permanently deleted</strong>:</p>
                <ul style="margin-left: 20px; margin-top: 10px; color: #856404;">
                    <li>All patient records</li>
                    <li>All appointments</li>
                    <li>All treatments</li>
                    <li>All billing records</li>
                    <li>All payment records</li>
                    <li>All dental history</li>
                    <li>All medical history</li>
                </ul>
                <p style="margin-top: 15px; font-weight: bold; color: #856404;">
                    ✓ Users and Services will be kept
                </p>
            </div>
            
            <div class="info-box">
                <h3>📊 Current Data Count</h3>
                <ul class="data-list">
                    <li>
                        <span>Patients:</span>
                        <span class="count"><?php echo number_format($counts['patients'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Appointments:</span>
                        <span class="count"><?php echo number_format($counts['appointments'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Treatments:</span>
                        <span class="count"><?php echo number_format($counts['treatments'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Billing Records:</span>
                        <span class="count"><?php echo number_format($counts['billing'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Payment Records:</span>
                        <span class="count"><?php echo number_format($counts['payments'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Dental History:</span>
                        <span class="count"><?php echo number_format($counts['dental_history'] ?? 0); ?></span>
                    </li>
                    <li>
                        <span>Medical History:</span>
                        <span class="count"><?php echo number_format($counts['medical_history'] ?? 0); ?></span>
                    </li>
                </ul>
            </div>
            
            <form method="POST" action="clear_all_data.php" onsubmit="return confirmReset()">
                <div class="form-group">
                    <label for="confirm">
                        Type <strong>YES</strong> to confirm deletion:
                    </label>
                    <input 
                        type="text" 
                        id="confirm" 
                        name="confirm" 
                        placeholder="Type YES to confirm"
                        required
                        autocomplete="off"
                    >
                </div>
                
                <button type="submit" class="btn btn-danger">
                    🗑️ Clear All Data
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    Cancel
                </a>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmReset() {
            const input = document.getElementById('confirm');
            if (input.value.toUpperCase() !== 'YES') {
                alert('Please type YES (in uppercase) to confirm.');
                return false;
            }
            return confirm('Are you sure you want to delete ALL transactional data? This cannot be undone!');
        }
        
        // Auto-uppercase the input
        document.getElementById('confirm').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>
