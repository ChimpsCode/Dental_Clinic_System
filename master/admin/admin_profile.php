<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_layout_start.php';

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

$userId = (int)$_SESSION['user_id'];
$colCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_picture'");
$colCheck->execute();
if (!$colCheck->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER updated_at");
}
$stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, phone, profile_picture, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<style>
.profile-section { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
.profile-section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
.avatar-upload-wrap { display: flex; align-items: center; gap: 32px; flex-wrap: wrap; }
.avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.avatar-preview-initials { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; flex-shrink: 0; }
.avatar-upload-actions { display: flex; flex-direction: column; gap: 12px; }
.upload-btn-wrapper { position: relative; overflow: hidden; display: inline-block; }
.upload-btn-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: #005daa; color: #fff; }
.btn-primary:hover { background: #004080; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-success { background: #059669; color: #fff; }
.btn-success:hover { background: #047857; }
.btn-danger { background: #fee2e2; color: #dc2626; }
.btn-danger:hover { background: #fecaca; }
.upload-hint { font-size: 13px; color: #6b7280; margin: 0; }
.profile-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.profile-info-label { display: block; font-size: 13px; font-weight: 500; color: #6b7280; margin-bottom: 4px; }
.profile-info-value { font-size: 15px; color: #1a1a2e; font-weight: 500; }
.profile-dates { grid-column: span 2; display: flex; gap: 32px; padding-top: 12px; border-top: 1px solid #f3f4f6; }
@media (max-width: 768px) {
    .profile-info-grid { grid-template-columns: 1fr; }
    .profile-dates { grid-column: span 1; flex-direction: column; gap: 12px; }
    .avatar-upload-wrap { flex-direction: column; align-items: center; text-align: center; }
}
</style>

<div class="content-main">
    <div class="page-header">
        <h2>My Profile</h2>
        <p class="page-subtitle">Manage your profile picture and personal information</p>
    </div>

    <?php if ($uploadError): ?>
        <div style="padding:12px 16px;border-radius:8px;margin-bottom:20px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:14px;"><?php echo htmlspecialchars($uploadError); ?></div>
    <?php endif; ?>

    <?php
    $profPic = $user['profile_picture'] ?? $_SESSION['profile_picture'] ?? null;
    $profPicFull = $profPic ? ('../../' . $profPic) : null;
    $first = $user['first_name'] ?? $_SESSION['display_name'] ?? 'A';
    $initial = strtoupper(substr($first, 0, 1));
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $email = $user['email'] ?? $_SESSION['email'] ?? '';
    $phone = $user['phone'] ?? '';
    $createdAt = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'N/A';
    ?>

    <div class="profile-section">
        <div class="profile-section-title">Profile Picture</div>
        <form action="../shared/profile_picture_actions.php" method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="action" value="upload">
            <div class="avatar-upload-wrap">
                <div>
                    <?php if ($profPic && file_exists(dirname(__DIR__, 2) . '/' . $profPic)): ?>
                        <img src="<?php echo htmlspecialchars($profPicFull); ?>?t=<?php echo time(); ?>" alt="Profile" class="avatar-preview" id="avatarPreview">
                    <?php else: ?>
                        <div class="avatar-preview-initials" id="avatarPreview"><?php echo htmlspecialchars($initial); ?></div>
                    <?php endif; ?>
                </div>
                <div class="avatar-upload-actions">
                    <div class="upload-btn-wrapper">
                        <button class="btn btn-primary" id="chooseBtn" type="button">Choose Photo</button>
                        <input type="file" name="profile_picture" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>
                    <button class="btn btn-success" id="saveBtn" style="display:none;" type="button">Save</button>
                    <?php if ($profPic): ?>
                        <a href="../shared/profile_picture_actions.php?action=delete" class="btn btn-danger" id="deleteBtn" onclick="return confirm('Remove your profile picture?')">Remove Photo</a>
                    <?php endif; ?>
                    <p class="upload-hint">JPG, PNG, GIF, or WebP. Max 2MB.</p>
                </div>
            </div>
        </form>
    </div>

    <div class="profile-section">
        <div class="profile-section-title">Account Information</div>
        <div class="profile-info-grid">
            <div class="profile-info-item">
                <span class="profile-info-label">Full Name</span>
                <span class="profile-info-value"><?php echo htmlspecialchars($fullName ?: 'N/A'); ?></span>
            </div>
            <div class="profile-info-item">
                <span class="profile-info-label">Role</span>
                <span class="profile-info-value">Admin</span>
            </div>
            <div class="profile-info-item">
                <span class="profile-info-label">Email</span>
                <span class="profile-info-value"><?php echo htmlspecialchars($email ?: 'N/A'); ?></span>
            </div>
            <div class="profile-info-item">
                <span class="profile-info-label">Phone</span>
                <span class="profile-info-value"><?php echo htmlspecialchars($phone ?: 'N/A'); ?></span>
            </div>
            <div class="profile-dates">
                <div class="profile-info-item">
                    <span class="profile-info-label">Member Since</span>
                    <span class="profile-info-value"><?php echo htmlspecialchars($createdAt); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>
<script>
(function() {
    const fileInput = document.getElementById('fileInput');
    const chooseBtn = document.getElementById('chooseBtn');
    const saveBtn = document.getElementById('saveBtn');
    const form = document.getElementById('uploadForm');
    const preview = document.getElementById('avatarPreview');

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
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Profile';
                img.className = 'avatar-preview';
                img.id = 'avatarPreview';
                preview.parentNode.replaceChild(img, preview);
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
})();
</script>
