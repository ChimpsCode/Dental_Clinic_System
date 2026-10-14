<dialog id="auditImportDialog" aria-labelledby="auditImportTitle" aria-describedby="auditImportHelp">
    <form id="auditImportForm">
        <div class="audit-import-heading"><h2 id="auditImportTitle">Restore audit backup</h2><button type="button" aria-label="Close" onclick="closeAuditImport()">&times;</button></div>
        <div class="audit-import-body">
            <p id="auditImportHelp">Upload a CSV downloaded from “Back up &amp; delete old logs” to return its records to the audit trail. Original dates and log IDs are kept.</p>
            <div id="auditImportUploadFields">
                <label for="auditImportFile">Backup CSV</label>
                <input type="file" id="auditImportFile" name="backup" accept=".csv,text/csv" required>
                <p class="audit-import-hint">Maximum 5 MB and 10,000 logs per file. Regular report exports cannot be restored.</p>
            </div>
            <p id="auditImportSavedFile" class="audit-import-hint" hidden></p>
            <button type="button" id="auditImportPreviewButton">Preview backup</button>
            <section id="auditImportPreview" aria-label="Backup preview" hidden>
                <div id="auditImportResult" class="audit-preview-result" role="status">
                    <strong id="auditImportResultTitle"></strong>
                    <p id="auditImportResultMessage"></p>
                </div>
                <div class="audit-preview-details">
                    <h3>Backup summary</h3>
                    <dl class="audit-preview-counts">
                        <div><dt>Total logs in backup</dt><dd id="auditImportTotal"></dd></div>
                        <div class="audit-preview-ready"><dt>Ready to restore</dt><dd id="auditImportReady"></dd></div>
                        <div><dt>Already in audit trail <small>Skipped as duplicates</small></dt><dd id="auditImportDuplicates"></dd></div>
                        <div><dt>Conflicting records <small>Skipped; existing records stay unchanged</small></dt><dd id="auditImportConflicts"></dd></div>
                        <div id="auditImportMissingUsersRow" hidden><dt>Logs with deleted accounts</dt><dd id="auditImportMissingUsers"></dd></div>
                    </dl>
                    <h3 class="audit-preview-period-heading">Log dates</h3>
                    <dl class="audit-preview-dates">
                        <div><dt>Oldest log</dt><dd id="auditImportOldest"></dd></div>
                        <div><dt>Newest log</dt><dd id="auditImportNewest"></dd></div>
                    </dl>
                </div>
            </section>
            <label for="auditImportAdmin">Admin account</label>
            <input type="text" id="auditImportAdmin" autocomplete="username" value="<?php echo htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES); ?>" readonly>
            <label for="auditImportPassword">Your admin password</label>
            <input type="password" id="auditImportPassword" name="password" autocomplete="off" required disabled placeholder="Preview your backup first">
            <p class="audit-import-hint">Matching records are skipped. A conflicting ID never replaces an existing log. If a user account no longer exists, the saved username and role are still preserved.</p>
            <p id="auditImportError" role="alert"></p>
            <p id="auditImportSuccess" role="status"></p>
        </div>
        <div class="audit-import-actions"><button type="button" onclick="closeAuditImport()">Close</button><button type="submit" id="auditImportConfirm" disabled>Restore to audit trail</button></div>
    </form>
</dialog>
<style>
#auditImportDialog { position:fixed; inset:0; margin:auto; height:fit-content; transform:none; width:min(540px,calc(100vw - 32px)); max-height:calc(100dvh - 32px); overflow-y:auto; padding:0; border:1px solid #e2e8f0; border-radius:16px; background:#fff; color:#334155; box-sizing:border-box; box-shadow:0 24px 70px #0f172a40; font:14px/1.6 Inter,sans-serif; }
#auditImportDialog::backdrop { background:#0f172a80; backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px); }
#auditImportDialog[open] { animation:auditCleanupSlide .25s ease; }
#auditImportDialog form { margin:0; }
#auditImportDialog .audit-import-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:20px 24px; border-bottom:1px solid #e2e8f0; }
#auditImportDialog h2 { margin:0; font-size:19px; color:#1e293b; }
#auditImportDialog .audit-import-heading button { border:0; background:#f1f5f9; color:#64748b; font-size:24px; border-radius:50%; width:32px; height:32px; cursor:pointer; }
#auditImportDialog .audit-import-body { padding:20px 24px; }
#auditImportHelp { margin:0 0 18px; }
#auditImportDialog label { display:block; margin:16px 0 6px; font-size:13px; font-weight:600; }
#auditImportDialog input { width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; color:#334155; }
#auditImportDialog input:disabled,#auditImportDialog input[readonly] { background:#f8fafc; }
#auditImportDialog .audit-import-hint { font-size:12px; color:#64748b; margin:8px 0; }
#auditImportPreview { border:1px solid #e2e8f0; border-radius:12px; background:#fff; color:#334155; font-size:13px; margin-top:16px; overflow:hidden; }
#auditImportPreview .audit-preview-result { padding:14px 16px; background:#f0fdf4; border-bottom:1px solid #dcfce7; color:#166534; }
#auditImportPreview .audit-preview-result.ready { background:#eff6ff; border-color:#dbeafe; color:#1e40af; }
#auditImportPreview .audit-preview-result.warning { background:#fffbeb; border-color:#fde68a; color:#92400e; }
#auditImportPreview .audit-preview-result strong { display:block; font-size:14px; font-weight:700; line-height:1.4; color:inherit; }
#auditImportPreview .audit-preview-result p { margin:5px 0 0; font-size:12px; line-height:1.6; color:inherit; }
#auditImportPreview .audit-preview-details { padding:14px 16px; }
#auditImportPreview h3 { margin:0 0 6px; font-size:12px; font-weight:700; color:#475569; }
#auditImportPreview dl { margin:0; }
#auditImportPreview dl > div { display:flex; align-items:baseline; justify-content:space-between; gap:16px; padding:10px 0; border-bottom:1px solid #f1f5f9; }
#auditImportPreview dl > div[hidden] { display:none; }
#auditImportPreview dt { color:#475569; line-height:1.5; }
#auditImportPreview dt small { display:block; margin-top:2px; font-size:11px; font-weight:400; color:#64748b; }
#auditImportPreview dd { margin:0; color:#0f172a; font-weight:600; text-align:right; font-variant-numeric:tabular-nums; }
#auditImportPreview .audit-preview-counts dd { flex-shrink:0; }
#auditImportPreview .audit-preview-ready dt,#auditImportPreview .audit-preview-ready dd { font-weight:700; }
#auditImportPreview h3.audit-preview-period-heading { margin-top:16px; }
#auditImportPreview .audit-preview-dates dt { flex-shrink:0; font-size:12px; color:#64748b; }
#auditImportPreview .audit-preview-dates dd { font-size:12px; font-weight:500; }
#auditImportPreview dl > div:last-child { border-bottom:0; }
#auditImportError { color:#b91c1c; font-size:13px; }
#auditImportSuccess { color:#15803d; font-size:13px; }
#auditImportDialog .audit-import-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; background:#f8fafc; border-top:1px solid #e2e8f0; }
#auditImportDialog .audit-import-actions button,#auditImportPreviewButton { border:1px solid #cbd5e1; border-radius:8px; padding:10px 16px; font:inherit; font-size:13px; cursor:pointer; background:#fff; color:#475569; }
#auditImportDialog #auditImportConfirm { background:#1663ca; border-color:#1663ca; color:#fff; }
#auditImportDialog button:disabled { opacity:.5; cursor:not-allowed; }
#auditImportDialog :is(input,button):focus-visible { outline:3px solid #93c5fd; outline-offset:2px; }
@media (prefers-reduced-motion:reduce) { #auditImportDialog[open] { animation:none; } }
</style>
<script>
(function () {
    const dialog = document.getElementById('auditImportDialog');
    const form = document.getElementById('auditImportForm');
    const file = document.getElementById('auditImportFile');
    const password = document.getElementById('auditImportPassword');
    const previewButton = document.getElementById('auditImportPreviewButton');
    const confirmButton = document.getElementById('auditImportConfirm');
    const preview = document.getElementById('auditImportPreview');
    const error = document.getElementById('auditImportError');
    const success = document.getElementById('auditImportSuccess');
    const uploadFields = document.getElementById('auditImportUploadFields');
    const savedLabel = document.getElementById('auditImportSavedFile');
    const title = document.getElementById('auditImportTitle');
    const help = document.getElementById('auditImportHelp');
    const resultBox = document.getElementById('auditImportResult');
    const resultTitle = document.getElementById('auditImportResultTitle');
    const resultMessage = document.getElementById('auditImportResultMessage');
    function renderPreview(result) {
        const number = value => Number(value || 0).toLocaleString();
        resultBox.className = 'audit-preview-result' + (ready ? ' ready' : result.conflicts > 0 ? ' warning' : '');
        resultTitle.textContent = ready ? number(result.restore) + ' logs ready to restore' : result.conflicts > 0 ? 'No logs can be restored' : 'Nothing to restore';
        resultMessage.textContent = ready
            ? 'Enter your admin password below to restore the missing logs.'
            : result.conflicts > 0 ? 'The remaining records have conflicting IDs. Existing audit records will stay unchanged.'
            : 'All logs in this backup are already in the audit trail.';
        for (const [id, value] of Object.entries({auditImportTotal:result.total, auditImportReady:result.restore,
            auditImportDuplicates:result.duplicates, auditImportConflicts:result.conflicts, auditImportMissingUsers:result.missing_users})) {
            document.getElementById(id).textContent = number(value);
        }
        document.getElementById('auditImportMissingUsersRow').hidden = !result.missing_users;
        for (const [id, value] of [['auditImportOldest',result.oldest],['auditImportNewest',result.newest]]) {
            const date = new Date(String(value).replace(' ', 'T'));
            document.getElementById(id).textContent = Number.isNaN(date.getTime()) ? String(value)
                : date.toLocaleString('en-PH', {month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
        }
    }
    const csrf = <?php echo json_encode($_SESSION['audit_cleanup_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let pending = false, ready = false, changed = false, savedFile = '';
    function resetPreview() {
        ready = false; password.disabled = true; password.value = ''; password.placeholder = 'Preview your backup first';
        preview.hidden = true; confirmButton.disabled = true; error.textContent = ''; success.textContent = '';
    }
    function openDialog(name = '', label = '') {
        if (pending || dialog.open) return;
        form.reset(); resetPreview(); changed = false;
        savedFile = name; file.disabled = false; file.required = true;
        uploadFields.hidden = false; savedLabel.hidden = !name;
        savedLabel.textContent = name ? 'Selected backup: ' + label : '';
        title.textContent = name ? 'Restore saved backup' : 'Upload audit backup';
        help.textContent = name
            ? 'Select the original CSV saved on your computer for this history entry. Preview it, then enter your admin password to restore missing logs.'
            : 'Upload a CSV downloaded from Back up & delete old logs to return its records to the audit trail. Original dates and log IDs are kept.';
        previewButton.disabled = false;
        document.body.appendChild(dialog); dialog.showModal();
    }
    window.openAuditImport = function () { openDialog(); };
    window.openAuditSavedRestore = function (name, label) {
        openDialog(name, label);
    };
    window.closeAuditImport = function () {
        if (pending) return;
        dialog.close(); password.value = '';
        if (changed) window.location.reload();
    };
    file.addEventListener('change', resetPreview);
    dialog.addEventListener('cancel', function (event) { event.preventDefault(); window.closeAuditImport(); });
    async function request(action) {
        const body = new FormData(form); body.set('action', action); body.set('csrf', csrf);
        body.set('source', savedFile ? 'saved' : 'upload');
        if (savedFile) body.set('saved_file', savedFile);
        if (action === 'preview') body.delete('password');
        const response = await fetch('../api/import_audit.php', {method:'POST', body});
        if (!response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Upload failed or your session expired. Reload the page and try again.');
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Could not restore the backup.');
        return result;
    }
    async function run(action) {
        if (pending || changed) return;
        if (!file.files.length) { error.textContent = 'Choose the downloaded backup CSV first.'; return; }
        if (file.files[0].size > 5 * 1024 * 1024) { error.textContent = 'Choose a backup no larger than 5 MB.'; return; }
        pending = true; error.textContent = ''; previewButton.disabled = true; confirmButton.disabled = true;
        confirmButton.textContent = action === 'restore' ? 'Restoring...' : 'Restore to audit trail';
        previewButton.textContent = action === 'preview' ? 'Checking backup...' : 'Preview backup';
        try {
            const response = request(action); file.disabled = true;
            const result = await response;
            if (action === 'preview') {
                ready = result.restore > 0;
                renderPreview(result);
                preview.hidden = false; password.disabled = !ready;
                password.placeholder = ready ? 'Re-enter your admin password' : 'No missing logs to restore';
                if (ready) password.focus();
            } else {
                changed = true; ready = false; password.value = ''; password.disabled = true;
                success.textContent = `${result.restore.toLocaleString()} logs restored. ${result.duplicates.toLocaleString()} duplicates and ${result.conflicts.toLocaleString()} conflicts skipped. Close this window to refresh the audit trail. Use the original log dates to find the restored records.`;
                if (result.history_warning) success.textContent += ' The restore completed, but its history status could not be updated.';
                if (window.refreshAuditBackupHistory) window.refreshAuditBackupHistory();
            }
        } catch (e) {
            error.textContent = e.message;
            if (action === 'preview') resetPreview();
            error.textContent = e.message;
            password.value = '';
        } finally {
            pending = false; file.disabled = changed; previewButton.disabled = changed; confirmButton.disabled = !ready || changed;
            confirmButton.textContent = 'Restore to audit trail'; previewButton.textContent = 'Preview backup';
        }
    }
    previewButton.addEventListener('click', () => run('preview'));
    form.addEventListener('submit', function (event) { event.preventDefault(); if (ready && password.value) run('restore'); });
})();
</script>
