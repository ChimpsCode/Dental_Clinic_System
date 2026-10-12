<dialog id="auditCleanupDialog" aria-labelledby="auditCleanupTitle" aria-describedby="auditCleanupHelp">
    <form id="auditCleanupForm">
        <div class="audit-cleanup-heading"><h2 id="auditCleanupTitle">Back up &amp; delete old logs</h2><button type="button" class="audit-cleanup-close" aria-label="Close" onclick="closeAuditCleanup()">&times;</button></div>
        <div class="audit-cleanup-body">
            <p id="auditCleanupHelp">Create a CSV backup, then remove the backed-up logs from the database. The latest 30 days are kept.</p>
            <p id="auditCleanupOldest" class="audit-cleanup-oldest" role="status">Checking the oldest log date...</p>
            <label for="auditCleanupBefore">Delete logs before this date</label>
            <input type="date" id="auditCleanupBefore" name="before" required max="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" value="<?php echo date('Y-m-d', strtotime('-1 year')); ?>">
            <p class="audit-cleanup-hint">Applies to all logs before this date, regardless of table filters. Logs on this date are kept.</p>
            <div id="auditCleanupPreview" role="status">Checking older logs...</div>
            <label for="auditCleanupPassword">Your admin password</label>
            <input type="password" id="auditCleanupPassword" name="password" autocomplete="current-password" required placeholder="Re-enter your login password">
            <p class="audit-cleanup-hint">A server copy is retained. The CSV download starts after cleanup, and can be downloaded again below. No restore feature is included.</p>
            <p id="auditCleanupError" role="alert"></p>
            <p id="auditCleanupSuccess" role="status"></p>
            <a id="auditCleanupDownload" hidden>Download backup CSV again</a>
            <details id="auditBackupHistory"><summary>Saved backups</summary><div id="auditBackupList">Loading...</div></details>
        </div>
        <div class="audit-cleanup-actions"><button type="button" onclick="closeAuditCleanup()">Cancel</button><button type="submit" id="auditCleanupConfirm" disabled>Back up &amp; delete</button></div>
    </form>
</dialog>
<style>
.audit-cleanup-trigger { color: #b91c1c !important; border-color: #fecaca !important; }
#auditCleanupDialog { position: fixed; inset: 0; margin: auto; height: fit-content; transform: none; width: min(520px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; color: #334155; box-sizing: border-box; box-shadow: 0 24px 70px #0f172a40; font: 14px/1.6 Inter, sans-serif; }
#auditCleanupDialog::backdrop { background: #0f172a80; backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
#auditCleanupDialog[open] { animation: auditCleanupSlide .25s ease; }
@keyframes auditCleanupSlide { from { opacity: 0; transform: translateY(-16px); } to { opacity: 1; transform: translateY(0); } }
#auditCleanupDialog form { margin: 0; }
#auditCleanupDialog .audit-cleanup-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
#auditCleanupDialog h2 { margin: 0; font-size: 19px; color: #1e293b; }
#auditCleanupDialog .audit-cleanup-close { border: 0; background: #f1f5f9; color: #64748b; font-size: 24px; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; }
#auditCleanupDialog .audit-cleanup-body { padding: 20px 24px; }
#auditCleanupDialog #auditCleanupHelp { margin: 0 0 18px; }
#auditCleanupDialog label { display: block; margin: 16px 0 6px; font-size: 13px; font-weight: 600; }
#auditCleanupDialog input { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; color: #334155; }
#auditCleanupDialog .audit-cleanup-hint { font-size: 12px; color: #64748b; margin: 8px 0; }
#auditCleanupPreview { padding: 12px; border-radius: 8px; background: #fff7ed; color: #9a3412; font-size: 13px; margin-top: 16px; }
#auditCleanupDialog .audit-cleanup-oldest { padding: 12px; border: 1px solid #dce7f6; border-radius: 8px; background: #eff6ff; color: #254974; font-size: 13px; }
#auditCleanupError { color: #b91c1c; font-size: 13px; }
#auditCleanupSuccess { color: #15803d; font-size: 13px; }
#auditCleanupDialog .audit-cleanup-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
#auditCleanupDialog .audit-cleanup-actions button { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 16px; font: inherit; font-size: 13px; cursor: pointer; background: #fff; color: #475569; }
#auditCleanupDialog #auditCleanupConfirm { background: #b91c1c; border-color: #b91c1c; color: #fff; }
#auditCleanupDialog button:disabled { opacity: .5; cursor: not-allowed; }
#auditCleanupDialog :is(input,button,a,summary):focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
#auditCleanupDialog details { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
#auditCleanupDialog summary { cursor: pointer; font-size: 13px; font-weight: 600; }
#auditBackupList a { display: block; margin-top: 10px; overflow-wrap: anywhere; font-size: 12px; }
@media (prefers-reduced-motion: reduce) { #auditCleanupDialog[open] { animation: none; } }
</style>
<script>
(function () {
    const dialog = document.getElementById('auditCleanupDialog');
    const form = document.getElementById('auditCleanupForm');
    const before = document.getElementById('auditCleanupBefore');
    const password = document.getElementById('auditCleanupPassword');
    const confirm = document.getElementById('auditCleanupConfirm');
    const error = document.getElementById('auditCleanupError');
    const success = document.getElementById('auditCleanupSuccess');
    const preview = document.getElementById('auditCleanupPreview');
    const oldest = document.getElementById('auditCleanupOldest');
    const download = document.getElementById('auditCleanupDownload');
    const csrf = <?php echo json_encode($_SESSION['audit_cleanup_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let pending = false, changed = false, previewVersion = 0;
    const endpoint = '../api/audit_cleanup.php';
    const url = file => endpoint + '?action=download&file=' + encodeURIComponent(file);
    async function responseData(response) {
        if (!response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Your session may have expired. Reload the page and sign in again.');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'The request could not complete.');
        return data;
    }
    async function request(action) {
        const data = new FormData(form);
        data.set('action', action); data.set('csrf', csrf);
        if (action === 'preview') data.delete('password');
        return responseData(await fetch(endpoint, {method: 'POST', body: data}));
    }
    async function loadBackups() {
        const list = document.getElementById('auditBackupList');
        list.textContent = 'Loading...';
        try {
            const data = await responseData(await fetch(endpoint + '?action=list', {cache: 'no-store'}));
            list.replaceChildren();
            if (!data.backups.length) list.textContent = 'No saved backups yet.';
            data.backups.forEach(backup => {
                const link = document.createElement('a'); link.href = url(backup.file); link.textContent = backup.label;
                list.appendChild(link);
            });
        } catch (e) { list.textContent = e.message; }
    }
    async function updatePreview() {
        const version = ++previewVersion;
        confirm.disabled = true; error.textContent = ''; preview.textContent = 'Checking older logs...';
        if (!before.checkValidity()) { preview.textContent = 'Choose a valid date at least 30 days ago.'; return; }
        try {
            const data = await request('preview');
            if (version !== previewVersion) return;
            oldest.textContent = data.oldest_log
                ? 'Oldest available log: ' + new Date(data.oldest_log.slice(0, 10) + 'T00:00:00').toLocaleDateString('en-PH', {year: 'numeric', month: 'long', day: 'numeric'})
                : 'No audit logs are currently available.';
            preview.textContent = data.count.toLocaleString() + ' logs before ' + before.value + ' will be backed up and deleted. Recent logs will remain.';
            confirm.disabled = pending || changed || data.count === 0;
        } catch (e) { if (version === previewVersion) { error.textContent = e.message; preview.textContent = 'Unable to count older logs.'; oldest.textContent = 'Unable to load the oldest log date.'; } }
    }
    window.openAuditCleanup = function () {
        form.reset(); password.value = ''; error.textContent = ''; success.textContent = ''; download.hidden = true; oldest.textContent = 'Checking the oldest log date...';
        document.body.appendChild(dialog); dialog.showModal(); updatePreview(); loadBackups();
    };
    window.closeAuditCleanup = function () {
        if (pending) return;
        dialog.close(); password.value = '';
        if (changed) window.location.reload();
    };
    before.addEventListener('change', updatePreview);
    dialog.addEventListener('cancel', function (e) { e.preventDefault(); window.closeAuditCleanup(); });
    form.addEventListener('submit', async function (e) {
        e.preventDefault(); if (pending || confirm.disabled) return;
        pending = true; confirm.disabled = true; before.readOnly = true; confirm.textContent = 'Backing up...'; error.textContent = '';
        try {
            const result = await request('cleanup');
            changed = true; password.value = ''; download.href = url(result.file); download.hidden = false;
            success.textContent = result.count.toLocaleString() + ' logs backed up and deleted. Your CSV download is starting. Close this window to refresh the audit trail.';
            preview.textContent = 'Backup saved securely. Recent logs were kept.';
            download.click(); loadBackups(); updatePreview();
        } catch (e) { error.textContent = e.message; password.value = ''; }
        finally { pending = false; before.readOnly = false; confirm.textContent = 'Back up & delete'; if (!changed) confirm.disabled = false; }
    });
})();
</script>
