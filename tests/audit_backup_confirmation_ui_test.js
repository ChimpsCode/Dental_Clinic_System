const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../includes/audit_backup_confirm_dialog.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g, '"csrf-fixture"');
const ids = ['auditBackupConfirmDialog','auditBackupConfirmForm','auditBackupConfirmPassword','auditBackupConfirmMessage','auditBackupConfirmSubmit','auditBackupRetry','auditBackupConfirmName'];
const elements = Object.fromEntries(ids.map(id => [id, {
    value:'', style:{}, disabled:false, open:false, listeners:{},
    addEventListener(type, handler) { this.listeners[type] = handler; },
    showModal() { this.open = true; }, close() { this.open = false; }, reset() {},
}]));
let downloads = 0, refreshes = 0, succeeds = false;
const requests = [];
class FakeFormData {
    constructor() { this.data = new Map([['saved','1'], ['password',elements.auditBackupConfirmPassword.value]]); }
    set(k,v) { this.data.set(k,v); } get(k) { return this.data.get(k); }
}
const context = {
    document:{getElementById:id=>elements[id],body:{appendChild(){}},createElement:()=>({click(){downloads++;},remove(){}})},
    FormData:FakeFormData,
    fetch:async (url, options) => { requests.push(options.body); return {ok:succeeds,headers:{get:()=> 'application/json'},json:async()=>({success:succeeds,message:'Password incorrect'})}; },
    window:{refreshAuditBackupHistory(){refreshes++;}},
};
vm.runInNewContext(script,context);
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const file = 'audit-backup-2020-01-01-000000-0123456789abcdef.csv.php';
    context.window.openAuditBackupConfirmation(file,'fixture.csv',false);
    assert.equal(downloads,0,'Automatic cleanup download must not be duplicated.');
    context.window.closeAuditBackupConfirmation();
    assert.equal(requests.length,0,'Closing confirmation must not remove the server backup.');
    context.window.openAuditBackupConfirmation(file,'fixture.csv');
    assert.equal(downloads,1);
    elements.auditBackupRetry.listeners.click();
    assert.equal(downloads,2);
    elements.auditBackupConfirmPassword.value = 'bad-password';
    elements.auditBackupConfirmForm.listeners.submit({preventDefault(){}});
    await flush();
    assert.equal(requests[0].get('action'),'confirm_saved');
    assert.equal(requests[0].get('file'),file);
    assert.equal(requests[0].get('saved'),'1');
    assert.equal(elements.auditBackupConfirmPassword.value,'');
    assert.equal(elements.auditBackupRetry.disabled,false);
    assert.equal(refreshes,0);
    succeeds = true;
    elements.auditBackupConfirmPassword.value = 'correct-password';
    elements.auditBackupConfirmForm.listeners.submit({preventDefault(){}});
    await flush();
    assert.equal(refreshes,1);
    assert.equal(elements.auditBackupConfirmSubmit.disabled,true);
    assert.equal(elements.auditBackupRetry.disabled,true);
    assert.match(elements.auditBackupConfirmMessage.textContent,/history only/);
    elements.auditBackupConfirmForm.listeners.submit({preventDefault(){}});
    await flush();
    assert.equal(requests.length,2,'Completed confirmation must not submit again.');
    console.log('Backup confirmation UI passed: cancel retains data, download retry, password failure, explicit confirmation, and history refresh.');
})().catch(error => { console.error(error); process.exitCode = 1; });
