<?php
if (!empty($GLOBALS['NEW_DEVICE_LOGIN_MODAL'])) {
    return;
}
$GLOBALS['NEW_DEVICE_LOGIN_MODAL'] = true;

$ndRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user'));
if ($ndRole === 'patient') {
    $ndRole = 'user';
}
if ($ndRole === 'administrator') {
    $ndRole = 'admin';
}
$ndCanChangeInModal = in_array($ndRole, ['admin', 'staff', 'dentist'], true);
?>
<div id="newDeviceLoginModal" class="nd-login-modal" hidden>
    <div class="nd-login-card" role="dialog" aria-modal="true" aria-labelledby="newDeviceLoginTitle">
        <h3 id="newDeviceLoginTitle">New device sign-in</h3>
        <p id="newDeviceLoginMessage" class="nd-login-message"></p>
        <p id="newDeviceLoginStatus" class="nd-login-status" hidden></p>
        <form id="newDevicePasswordForm" class="nd-login-form" hidden>
            <label class="nd-login-label" for="newDeviceCurrentPassword">Current password</label>
            <input id="newDeviceCurrentPassword" name="current_password" type="password" autocomplete="current-password" required>
            <label class="nd-login-label" for="newDeviceNewPassword">New password</label>
            <input id="newDeviceNewPassword" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
            <label class="nd-login-label" for="newDeviceConfirmPassword">Confirm new password</label>
            <input id="newDeviceConfirmPassword" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
            <button type="submit" class="nd-login-btn nd-login-btn-primary" id="newDevicePasswordSubmit">Save and sign out other devices</button>
        </form>
        <div id="newDeviceLoginActions" class="nd-login-actions">
            <button type="button" class="nd-login-btn nd-login-btn-muted" id="newDeviceWasMe">This was me</button>
            <button type="button" class="nd-login-btn nd-login-btn-primary" id="newDeviceChangePassword">Change password</button>
        </div>
    </div>
</div>
<style>
    #newDeviceLoginModal.nd-login-modal {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, .45);
        font-family: Inter, system-ui, sans-serif;
    }
    #newDeviceLoginModal.nd-login-modal[hidden] { display: none; }
    #newDeviceLoginModal .nd-login-card {
        background: #fff;
        border-radius: 16px;
        max-width: 440px;
        width: 100%;
        padding: 28px 24px 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35);
        text-align: left;
    }
    #newDeviceLoginModal h3 {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 700;
        color: #0c4a6e;
        text-align: center;
    }
    #newDeviceLoginModal .nd-login-message {
        margin: 0 0 18px;
        color: #4b5563;
        font-size: 14px;
        line-height: 1.5;
    }
    #newDeviceLoginModal .nd-login-status {
        margin: 0 0 12px;
        font-size: 13px;
        line-height: 1.4;
    }
    #newDeviceLoginModal .nd-login-status.is-error { color: #991b1b; }
    #newDeviceLoginModal .nd-login-status.is-success { color: #065f46; }
    #newDeviceLoginModal .nd-login-form { display: flex; flex-direction: column; gap: 6px; }
    #newDeviceLoginModal .nd-login-form[hidden] { display: none; }
    #newDeviceLoginModal .nd-login-label { font-size: 13px; font-weight: 600; color: #374151; margin-top: 6px; }
    #newDeviceLoginModal input {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 14px;
        font-family: inherit;
    }
    #newDeviceLoginModal .nd-login-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }
    #newDeviceLoginModal .nd-login-actions[hidden] { display: none; }
    #newDeviceLoginModal .nd-login-btn {
        box-sizing: border-box;
        min-width: 120px;
        height: 42px;
        margin: 0;
        padding: 0 16px;
        border-radius: 8px;
        border: 1px solid transparent;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #newDeviceLoginModal .nd-login-btn-primary {
        border-color: #0ea5e9;
        color: #fff;
        background: #0ea5e9;
    }
    #newDeviceLoginModal .nd-login-btn-muted {
        background: #f3f4f6;
        color: #374151;
        border-color: #d1d5db;
    }
    #newDeviceLoginModal #newDevicePasswordSubmit {
        margin-top: 14px;
        width: 100%;
        height: auto;
        min-height: 42px;
        padding: 10px 16px;
        white-space: normal;
    }
</style>
<script>
(function () {
    if (window.__newDeviceLoginModal) return;
    window.__newDeviceLoginModal = true;

    var canChangeInModal = <?php echo $ndCanChangeInModal ? 'true' : 'false'; ?>;
    var overlay = document.getElementById('newDeviceLoginModal');
    var messageEl = document.getElementById('newDeviceLoginMessage');
    var statusEl = document.getElementById('newDeviceLoginStatus');
    var actionsEl = document.getElementById('newDeviceLoginActions');
    var formEl = document.getElementById('newDevicePasswordForm');
    var wasMeBtn = document.getElementById('newDeviceWasMe');
    var changeBtn = document.getElementById('newDeviceChangePassword');
    var currentNotifId = '';

    function sharedUrl(fileName) {
        var parts = window.location.pathname.split('/');
        var masterIdx = parts.lastIndexOf('master');
        if (masterIdx >= 0) {
            parts.splice(masterIdx + 1);
            return parts.join('/') + '/shared/' + fileName;
        }
        return '../shared/' + fileName;
    }

    function markRead(id) {
        if (!id) return;
        fetch(sharedUrl('notification_actions.php'), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_read&id=' + encodeURIComponent(id)
        }).catch(function () {});
    }

    function setStatus(text, kind) {
        if (!text) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.className = 'nd-login-status';
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = text;
        statusEl.className = 'nd-login-status is-' + kind;
    }

    function showChoices() {
        formEl.hidden = true;
        actionsEl.hidden = false;
        changeBtn.hidden = false;
        wasMeBtn.textContent = 'This was me';
        formEl.reset();
        setStatus('', '');
    }

    function closeModal() {
        overlay.hidden = true;
        currentNotifId = '';
        showChoices();
    }

    function openModal(item) {
        var titleEl = item.querySelector('.notif-title');
        var msgEl = item.querySelector('.notif-msg');
        if (!titleEl || titleEl.textContent.trim() !== 'New device sign-in') return false;
        if (overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }
        currentNotifId = item.getAttribute('data-notif-id') || '';
        messageEl.textContent = msgEl ? msgEl.textContent.trim() : '';
        showChoices();
        overlay.hidden = false;
        item.classList.remove('unread');
        markRead(currentNotifId);
        document.querySelectorAll('.notif-dropdown.open').forEach(function (dropdown) {
            dropdown.classList.remove('open');
        });
        return true;
    }

    document.addEventListener('click', function (e) {
        var item = e.target.closest('.notif-item');
        if (!item) return;
        var titleEl = item.querySelector('.notif-title');
        if (!titleEl || titleEl.textContent.trim() !== 'New device sign-in') return;
        e.preventDefault();
        e.stopPropagation();
        openModal(item);
    }, true);

    wasMeBtn.addEventListener('click', function () {
        markRead(currentNotifId);
        closeModal();
    });

    changeBtn.addEventListener('click', function () {
        if (!canChangeInModal) {
            window.location.href = 'settings.php';
            return;
        }
        actionsEl.hidden = true;
        formEl.hidden = false;
        setStatus('', '');
        var first = document.getElementById('newDeviceCurrentPassword');
        if (first) first.focus();
    });

    formEl.addEventListener('submit', function (e) {
        e.preventDefault();
        var currentPassword = document.getElementById('newDeviceCurrentPassword').value;
        var newPassword = document.getElementById('newDeviceNewPassword').value;
        var confirmPassword = document.getElementById('newDeviceConfirmPassword').value;
        if (newPassword.length < 8) {
            setStatus('Password must be at least 8 characters.', 'error');
            return;
        }
        if (newPassword !== confirmPassword) {
            setStatus('New passwords do not match.', 'error');
            return;
        }
        var submitBtn = document.getElementById('newDevicePasswordSubmit');
        submitBtn.disabled = true;
        var body = 'current_password=' + encodeURIComponent(currentPassword)
            + '&new_password=' + encodeURIComponent(newPassword)
            + '&confirm_password=' + encodeURIComponent(confirmPassword);
        fetch(sharedUrl('new_device_password_actions.php'), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        }).then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                formEl.hidden = true;
                actionsEl.hidden = false;
                changeBtn.hidden = true;
                wasMeBtn.textContent = 'Close';
                setStatus(data.message || 'Password saved. Other devices were signed out.', 'success');
                formEl.reset();
                return;
            }
            setStatus((data && data.message) || 'Unable to change the password.', 'error');
        }).catch(function () {
            setStatus('Unable to change the password.', 'error');
        }).finally(function () {
            submitBtn.disabled = false;
        });
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) closeModal();
    });
})();
</script>
