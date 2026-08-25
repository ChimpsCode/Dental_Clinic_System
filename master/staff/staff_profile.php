<?php
$pageTitle = 'Profile & Settings';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/staff_layout_start.php';

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

$message = '';
$messageType = '';

$userId = (int)$_SESSION['user_id'];
$colCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_picture'");
$colCheck->execute();
if (!$colCheck->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER updated_at");
}

// Ensure 2FA columns exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? '';

    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ? WHERE id = ?");
    $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $date_of_birth ?: null, $gender ?: null, $userId]);

    $message = 'Profile updated successfully!';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_security') {
    $twoFactorEnabled = ($_POST['two_factor_enabled'] ?? '0') === '1' ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    $stmt->execute([$twoFactorEnabled, $userId]);

    $sessionTimeout = (int)($_POST['session_timeout'] ?? 30);
    if (in_array($sessionTimeout, [15, 30, 60, 120])) {
        $stmt2 = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt2->execute(['session_timeout', (string)$sessionTimeout]);
    }

    $message = 'Security settings saved successfully!';
    $messageType = 'success';
}

// Load session timeout setting
$sessionTimeoutSetting = '30';
try {
    $toStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout' LIMIT 1");
    $toStmt->execute();
    $toVal = $toStmt->fetchColumn();
    if ($toVal !== false && $toVal !== null) {
        $sessionTimeoutSetting = $toVal;
    }
} catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, phone, profile_picture, gender, date_of_birth, created_at, two_factor_enabled FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<style>
.section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
.section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
.profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
.profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 42px; flex-shrink: 0; overflow: hidden; }
.profile-avatar img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; }
.profile-info h2 { font-size: 24px; color: #1a1a2e; margin-bottom: 4px; }
.profile-info p { color: #6b7280; font-size: 14px; }
.avatar-upload-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.upload-btn-wrapper { position: relative; overflow: hidden; display: inline-block; }
.upload-btn-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 160px; height: 100%; cursor: pointer; }
.btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: #005daa; color: white; }
.btn-primary:hover { background: #004080; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-success { background: #059669; color: #fff; }
.btn-success:hover { background: #047857; }
.btn-danger { background: #fee2e2; color: #dc2626; }
.btn-danger:hover { background: #fecaca; }
.upload-hint { font-size: 13px; color: #6b7280; margin: 0; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.form-group { margin-bottom: 16px; }
.form-group.full-width { grid-column: span 2; }
.form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
.form-input:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
.form-select { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white; }
.btn-container { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-switch .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e5e7eb; transition: .3s; border-radius: 24px; }
.toggle-switch .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.toggle-switch input:checked + .toggle-slider { background-color: #005daa !important; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); }

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-group.full-width { grid-column: span 1; }
    .profile-header { flex-direction: column; text-align: center; }
    .avatar-upload-actions { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="content-main">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($uploadError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($uploadError); ?></div>
    <?php endif; ?>

    <?php
    $profPic = $user['profile_picture'] ?? $_SESSION['profile_picture'] ?? null;
    $profPicFull = $profPic ? ('../../' . $profPic) : null;
    $first = $user['first_name'] ?? $_SESSION['display_name'] ?? 'S';
    $initial = strtoupper(substr($first, 0, 1));
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $email = $user['email'] ?? $_SESSION['email'] ?? '';
    $phone = $user['phone'] ?? '';
    $createdAt = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'N/A';
    $gender = $user['gender'] ?? '';
    $date_of_birth = $user['date_of_birth'] ?? '';
    ?>

    <div class="section">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if ($profPic && file_exists(dirname(__DIR__, 2) . '/' . $profPic)): ?>
                    <img src="<?php echo htmlspecialchars($profPicFull); ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo htmlspecialchars($initial); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($fullName ?: 'Staff'); ?></h2>
                <p>Staff since <?php echo htmlspecialchars($createdAt); ?></p>
            </div>
        </div>

        <div style="padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <div class="section-title">Profile Photo</div>
            <form action="../shared/profile_picture_actions.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <div class="avatar-upload-actions">
                    <div class="upload-btn-wrapper">
                        <button class="btn btn-primary" id="chooseBtn" type="button">Choose Photo</button>
                        <input type="file" name="profile_picture" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>
                    <button class="btn btn-success" id="saveBtn" style="display:none;" type="button">Save Photo</button>
                    <?php if ($profPic): ?>
                        <a href="../shared/profile_picture_actions.php?action=delete" class="btn btn-danger" id="deleteBtn" onclick="return confirm('Remove your profile picture?')">Remove Photo</a>
                    <?php endif; ?>
                    <span class="upload-hint">JPG, PNG, GIF, WebP. Max 2MB.</span>
                </div>
            </form>
        </div>
    </div>

    <div class="section">
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_profile">

            <div class="section-title">Personal Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-input" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-input" value="<?php echo htmlspecialchars($date_of_birth); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="section-title" style="margin-top: 24px;">Contact Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($phone); ?>">
                </div>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <div class="section" style="border-left: 4px solid #d97706;">
        <div class="section-title">🔒 Security</div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_security">

            <div class="toggle-setting" style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f3f4f6; margin-bottom:16px;">
                <div>
                    <span style="font-weight:600; color:#374151;">Two-Factor Authentication</span>
                    <p style="font-size:13px; color:#6b7280; margin:4px 0 0;">Add extra security to your account</p>
                </div>
                <label class="toggle-switch" style="position:relative; display:inline-block; width:46px; height:24px; flex-shrink:0;">
                    <input type="checkbox" name="two_factor_enabled" value="1" <?php echo ($user['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f3f4f6; margin-bottom:16px;">
                <div>
                    <span style="font-weight:600; color:#374151;">Session Timeout</span>
                    <p style="font-size:13px; color:#6b7280; margin:4px 0 0;">Auto logout after inactivity (global setting)</p>
                </div>
                <select name="session_timeout" class="form-input" style="width:auto; padding:8px 12px;">
                    <option value="15" <?php echo $sessionTimeoutSetting == '15' ? 'selected' : ''; ?>>15 minutes</option>
                    <option value="30" <?php echo $sessionTimeoutSetting == '30' ? 'selected' : ''; ?>>30 minutes</option>
                    <option value="60" <?php echo $sessionTimeoutSetting == '60' ? 'selected' : ''; ?>>1 hour</option>
                    <option value="120" <?php echo $sessionTimeoutSetting == '120' ? 'selected' : ''; ?>>2 hours</option>
                </select>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Security Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/staff_layout_end.php'; ?>
<script>
(function() {
    const fileInput = document.getElementById('fileInput');
    const chooseBtn = document.getElementById('chooseBtn');
    const saveBtn = document.getElementById('saveBtn');
    const form = document.getElementById('uploadForm');
    const preview = document.querySelector('.profile-avatar');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, GIF, and WebP images are allowed.');
            this.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be under 2MB.');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = preview.querySelector('img');
            if (img) {
                img.src = e.target.result;
            } else {
                preview.innerHTML = '';
                const newImg = document.createElement('img');
                newImg.src = e.target.result;
                newImg.alt = 'Profile';
                preview.appendChild(newImg);
            }
        };
        reader.readAsDataURL(file);

        saveBtn.style.display = 'inline-flex';
        chooseBtn.textContent = 'Change Photo';
    });

    saveBtn.addEventListener('click', function() {
        saveBtn.disabled = true;
        chooseBtn.disabled = true;
        form.submit();
    });

    var alertEl = document.querySelector('.alert-success');
    if (alertEl) {
        setTimeout(function() {
            alertEl.style.transition = 'opacity 0.3s';
            alertEl.style.opacity = '0';
            setTimeout(function() { alertEl.remove(); }, 300);
        }, 2000);
    }
})();
</script>
