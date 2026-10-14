const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../includes/audit_import_dialog.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g, '"test-csrf"');
const ids = ['auditImportDialog','auditImportForm','auditImportFile','auditImportPassword','auditImportPreviewButton','auditImportConfirm','auditImportPreview','auditImportError','auditImportSuccess','auditImportUploadFields','auditImportSavedFile','auditImportTitle','auditImportHelp',
    'auditImportResult','auditImportResultTitle','auditImportResultMessage','auditImportTotal','auditImportReady','auditImportDuplicates','auditImportConflicts','auditImportMissingUsers','auditImportMissingUsersRow','auditImportOldest','auditImportNewest'];
const elements = Object.fromEntries(ids.map(id => [id, {
    id, value: '', files: [], open: false, disabled: false, hidden: false, required: false,
    listeners: {}, addEventListener(type, fn) { this.listeners[type] = fn; },
    focus() {}, showModal() { this.open = true; }, close() { this.open = false; },
    reset() { elements.auditImportFile.files = []; elements.auditImportPassword.value = ''; },
}]));
const requests = [];
let reloads = 0;
let result = {success:true,total:3,restore:2,duplicates:1,conflicts:0,missing_users:0,oldest:'2020-01-01 00:00:00',newest:'2020-02-01 00:00:00'};
class FormDataStub {
    constructor() { this.fields = new Map([['password', elements.auditImportPassword.value]]); }
    set(k,v) { this.fields.set(k,v); } delete(k) { this.fields.delete(k); } get(k) { return this.fields.get(k); }
}
const context = {
    document:{getElementById:id=>elements[id],body:{appendChild(){}}},
    FormData:FormDataStub,
    fetch:async (url, options) => { requests.push(options.body); return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({...result})}; },
    window:{location:{reload(){reloads++;}}},
};
vm.runInNewContext(script, context);
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const filename = 'audit-backup-2020-01-01-080000-0123456789abcdef.csv.php';
    context.window.openAuditSavedRestore(filename, 'Saved fixture');
    await flush();
    assert.equal(requests.length, 0, 'History restoration must wait for the local CSV.');
    assert.equal(elements.auditImportFile.required, true);
    assert.equal(elements.auditImportFile.disabled, false);
    assert.equal(elements.auditImportConfirm.disabled, true);
    elements.auditImportFile.files = [{size:100}];
    elements.auditImportPreviewButton.listeners.click();
    await flush();
    assert.equal(requests[0].get('source'), 'saved');
    assert.equal(requests[0].get('saved_file'), filename);
    assert.equal(requests[0].get('action'), 'preview');
    assert.equal(elements.auditImportFile.required, true);
    assert.equal(elements.auditImportFile.disabled, false);
    assert.equal(elements.auditImportPassword.disabled, false);
    assert.equal(elements.auditImportConfirm.disabled, false);
    elements.auditImportPassword.value = 'fixture-password';
    elements.auditImportForm.listeners.submit({preventDefault(){}});
    await flush();
    assert.equal(requests[1].get('action'), 'restore');
    assert.equal(requests[1].get('password'), 'fixture-password');
    assert.equal(elements.auditImportPassword.value, '');
    context.window.closeAuditImport();
    assert.equal(reloads, 1);
    result = {...result,restore:0,duplicates:3};
    context.window.openAuditSavedRestore(filename, 'Saved fixture');
    elements.auditImportFile.files = [{size:100}];
    elements.auditImportPreviewButton.listeners.click();
    await flush();
    assert.equal(elements.auditImportConfirm.disabled, true);
    assert.match(elements.auditImportResultMessage.textContent, /already in the audit trail/);
    context.window.closeAuditImport();
    context.window.openAuditImport();
    assert.equal(elements.auditImportFile.disabled, false);
    assert.equal(elements.auditImportFile.required, true);
    assert.equal(elements.auditImportUploadFields.hidden, false);
    assert.equal(elements.auditImportConfirm.disabled, true);
    assert.equal(elements.auditImportPassword.disabled, true);
    console.log('Audit restore UI passed: local CSV required for history, preview, password submission, duplicate notice, refresh, and upload-mode reset.');
})().catch(error => { console.error(error); process.exitCode = 1; });
