<dialog id="auditBackupConfirmDialog" aria-labelledby="auditBackupConfirmTitle">
    <form id="auditBackupConfirmForm">
        <div class="audit-copy-heading"><h2 id="auditBackupConfirmTitle">Confirm your backup is saved</h2><button type="button" onclick="closeAuditBackupConfirmation()" aria-label="Close">&times;</button></div>
        <div class="audit-copy-body">
            <p>Your CSV download has started. Check your Downloads folder and keep the file somewhere safe before continuing.</p>
            <p id="auditBackupConfirmName"></p>
            <p>Confirmation removes the backup data from this server. Only its filename, date, log count and fingerprint remain in Saved backups. Recovery will require your downloaded CSV.</p>
            <button type="button" id="auditBackupRetry">Try saving CSV again</button>
            <label class="audit-copy-checkbox"><input type="checkbox" name="saved" value="1" required> <span>I have saved the CSV backup on my computer.</span></label>
            <label for="auditBackupConfirmAdmin">Admin account</label>
            <input id="auditBackupConfirmAdmin" type="text" autocomplete="username" value="<?php echo htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES); ?>" readonly>
            <label for="auditBackupConfirmPassword">Your admin password</label>
            <input id="auditBackupConfirmPassword" name="password" type="password" autocomplete="off" required>
            <p id="auditBackupConfirmMessage" role="status" aria-live="polite"></p>
        </div>
        <div class="audit-copy-actions"><button type="button" onclick="closeAuditBackupConfirmation()">Close</button><button type="submit" id="auditBackupConfirmSubmit">Confirm &amp; remove server copy</button></div>
    </form>
</dialog>
<style>
#auditBackupConfirmDialog { position:fixed; inset:0; margin:auto; width:min(520px,calc(100vw - 32px)); height:fit-content; max-height:calc(100dvh - 32px); overflow:auto; padding:0; border:1px solid #e2e8f0; border-radius:16px; background:#fff; color:#334155; box-shadow:0 24px 70px #0f172a40; font:14px/1.6 Inter,sans-serif; }
#auditBackupConfirmDialog::backdrop { background:#0f172a80; backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px); }
#auditBackupConfirmDialog .audit-copy-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:20px 24px; border-bottom:1px solid #e2e8f0; }
#auditBackupConfirmDialog h2 { margin:0; font-size:19px; }
#auditBackupConfirmDialog .audit-copy-heading button { font-size:24px; padding:0 10px; }
#auditBackupConfirmDialog form { margin:0; }
#auditBackupConfirmDialog .audit-copy-body { padding:18px 24px; }
#auditBackupConfirmName { overflow-wrap:anywhere; padding:10px; background:#eff6ff; border-radius:8px; font-size:12px; }
#auditBackupConfirmDialog label { display:block; margin:14px 0 5px; font-size:13px; font-weight:600; }
#auditBackupConfirmDialog input:not([type=checkbox]) { box-sizing:border-box; width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
#auditBackupConfirmDialog .audit-copy-checkbox { display:flex; align-items:flex-start; gap:8px; }
#auditBackupConfirmDialog .audit-copy-checkbox input { flex:0 0 auto; margin-top:5px; }
#auditBackupConfirmDialog button { border:1px solid #cbd5e1; border-radius:8px; padding:9px 12px; background:#fff; color:#475569; font:inherit; font-size:12px; cursor:pointer; }
#auditBackupConfirmDialog .audit-copy-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; }
#auditBackupConfirmDialog #auditBackupConfirmSubmit { color:#fff; background:#1663ca; border-color:#1663ca; }
#auditBackupConfirmDialog button:disabled { opacity:.5; cursor:not-allowed; }
#auditBackupConfirmMessage { color:#b91c1c; font-size:13px; }
</style>
<script>
(function () {
    const dialog = document.getElementById('auditBackupConfirmDialog');
    const form = document.getElementById('auditBackupConfirmForm');
    const password = document.getElementById('auditBackupConfirmPassword');
    const message = document.getElementById('auditBackupConfirmMessage');
    const submit = document.getElementById('auditBackupConfirmSubmit');
    const retry = document.getElementById('auditBackupRetry');
    const csrf = <?php echo json_encode($_SESSION['audit_cleanup_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let selected = '', pending = false, completed = false;
    function saveCsv() {
        const link = document.createElement('a');
        link.href = '../api/audit_cleanup.php?action=download&file=' + encodeURIComponent(selected);
        link.hidden = true; document.body.appendChild(link); link.click(); link.remove();
    }
    window.openAuditBackupConfirmation = function (file, label, downloadFirst = true) {
        if (pending || dialog.open) return;
        form.reset(); selected = file; completed = false; password.value = '';
        message.textContent = ''; message.style.color = '#b91c1c'; submit.disabled = false; retry.disabled = false;
        document.getElementById('auditBackupConfirmName').textContent = label;
        document.body.appendChild(dialog); dialog.showModal();
        if (downloadFirst) saveCsv();
    };
    window.closeAuditBackupConfirmation = function () { if (!pending) { dialog.close(); password.value = ''; } };
    dialog.addEventListener('cancel', event => { event.preventDefault(); window.closeAuditBackupConfirmation(); });
    retry.addEventListener('click', () => { if (!pending && !completed) saveCsv(); });
    form.addEventListener('submit', async function (event) {
        event.preventDefault(); if (pending || completed) return;
        const body = new FormData(form); body.set('action', 'confirm_saved'); body.set('file', selected); body.set('csrf', csrf);
        pending = true; submit.disabled = true; retry.disabled = true; message.textContent = '';
        try {
            const response = await fetch('../api/audit_cleanup.php', {method:'POST',body});
            if (!response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Session expired. Reload and sign in again.');
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not remove the server copy.');
            completed = true; password.value = ''; message.style.color = '#15803d';
            message.textContent = 'Server backup data removed. Saved backups now contains history only. Keep your local CSV for recovery.';
            if (window.refreshAuditBackupHistory) window.refreshAuditBackupHistory();
        } catch (e) { message.textContent = e.message; password.value = ''; }
        finally { pending = false; submit.disabled = completed; retry.disabled = completed; }
    });
})();
</script>
