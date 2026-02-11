<?php
$pageTitle = 'Settings';
require_once 'config/database.php';
require_once 'includes/staff_layout_start.php';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $avg_service_time = intval($_POST['avg_service_time'] ?? 30);
        $max_patients_per_day = intval($_POST['max_patients_per_day'] ?? 40);
        $auto_waiting_to_serving = isset($_POST['auto_waiting_to_serving']) ? 1 : 0;
        $auto_serving_to_completed = isset($_POST['auto_serving_to_completed']) ? 1 : 0;
        $enable_walkin = isset($_POST['enable_walkin']) ? 1 : 0;
        $priority_senior = isset($_POST['priority_senior']) ? 1 : 0;
        $priority_pwd = isset($_POST['priority_pwd']) ? 1 : 0;
        $priority_emergency = isset($_POST['priority_emergency']) ? 1 : 0;
        $queue_format = $_POST['queue_format'] ?? 'Q-###';

        if ($avg_service_time < 1) {
            throw new Exception('Average service time must be at least 1 minute');
        }
        if ($max_patients_per_day < 1) {
            throw new Exception('Maximum patients per day must be at least 1');
        }

        $_SESSION['queue_settings'] = [
            'avg_service_time' => $avg_service_time,
            'max_patients_per_day' => $max_patients_per_day,
            'auto_waiting_to_serving' => $auto_waiting_to_serving,
            'auto_serving_to_completed' => $auto_serving_to_completed,
            'enable_walkin' => $enable_walkin,
            'priority_senior' => $priority_senior,
            'priority_pwd' => $priority_pwd,
            'priority_emergency' => $priority_emergency,
            'queue_format' => $queue_format
        ];

        $success_message = 'Queue settings saved successfully!';
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

$settings = $_SESSION['queue_settings'] ?? [
    'avg_service_time' => 30,
    'max_patients_per_day' => 40,
    'auto_waiting_to_serving' => 1,
    'auto_serving_to_completed' => 1,
    'enable_walkin' => 0,
    'priority_senior' => 1,
    'priority_pwd' => 1,
    'priority_emergency' => 1,
    'queue_format' => 'Q-###'
];
?>

<style>
    .settings-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .settings-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
    }

    .settings-section {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .settings-section h2 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #111827;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 16px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }

    .form-group input[type="number"],
    .form-group input[type="text"],
    .form-group textarea {
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        font-size: 14px;
    }

    .example-text {
        font-size: 12px;
        color: #6b7280;
    }

    .settings-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }

    .toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 10px;
    }

    .toggle-row span {
        font-size: 13px;
        color: #374151;
        font-weight: 600;
    }

    .btn-primary {
        background: #2563eb;
        color: #ffffff;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }

    .alert {
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 13px;
    }

    .alert-success {
        background: #ecfdf3;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .alert-danger {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
</style>

<?php if ($success_message): ?>
    <div class="alert alert-success">? <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger">? <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="settings-container">
    <div class="settings-header">
        <h1>Queue Settings</h1>
    </div>

    <form method="POST" action="">
        <div class="settings-section">
            <h2>Service Time Configuration</h2>
            <div class="settings-row">
                <div class="form-group">
                    <label for="avg_service_time">Average Service Time per Patient</label>
                    <input type="number" id="avg_service_time" name="avg_service_time" value="<?php echo $settings['avg_service_time']; ?>" min="1" required>
                    <div class="example-text">Example: 30 minutes</div>
                </div>
                <div class="form-group">
                    <label for="max_patients_per_day">Maximum Patients per Day</label>
                    <input type="number" id="max_patients_per_day" name="max_patients_per_day" value="<?php echo $settings['max_patients_per_day']; ?>" min="1" required>
                    <div class="example-text">Example: 40</div>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h2>Auto Queue Status Update</h2>
            <div class="toggle-row">
                <span>Auto waiting ? serving</span>
                <input type="checkbox" name="auto_waiting_to_serving" <?php echo $settings['auto_waiting_to_serving'] ? 'checked' : ''; ?>>
            </div>
            <div class="toggle-row">
                <span>Auto serving ? completed</span>
                <input type="checkbox" name="auto_serving_to_completed" <?php echo $settings['auto_serving_to_completed'] ? 'checked' : ''; ?>>
            </div>
        </div>

        <div class="settings-section">
            <h2>Walk-in & Priority</h2>
            <div class="toggle-row">
                <span>Enable walk-in</span>
                <input type="checkbox" name="enable_walkin" <?php echo $settings['enable_walkin'] ? 'checked' : ''; ?>>
            </div>
            <div class="toggle-row">
                <span>Priority: Senior</span>
                <input type="checkbox" name="priority_senior" <?php echo $settings['priority_senior'] ? 'checked' : ''; ?>>
            </div>
            <div class="toggle-row">
                <span>Priority: PWD</span>
                <input type="checkbox" name="priority_pwd" <?php echo $settings['priority_pwd'] ? 'checked' : ''; ?>>
            </div>
            <div class="toggle-row">
                <span>Priority: Emergency</span>
                <input type="checkbox" name="priority_emergency" <?php echo $settings['priority_emergency'] ? 'checked' : ''; ?>>
            </div>
        </div>

        <div class="settings-section">
            <h2>Queue Format</h2>
            <div class="form-group">
                <label for="queue_format">Queue Number Format</label>
                <input type="text" id="queue_format" name="queue_format" value="<?php echo htmlspecialchars($settings['queue_format']); ?>" required>
                <div class="example-text">Example: Q-###</div>
            </div>
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<?php require_once 'includes/staff_layout_end.php'; ?>
