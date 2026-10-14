const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../includes/audit_cleanup_dialog.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g,'"fixture"');
function node(tag = 'div') {
    return {tag, children:[], listeners:{}, textContent:'',
        append(...items){this.children.push(...items);}, appendChild(item){this.children.push(item);},
        replaceChildren(){this.children=[];}, setAttribute(){}, addEventListener(type,fn){this.listeners[type]=fn;}};
}
const elements = {};
const backup = {file:'fixture.csv.php',label:'fixture.csv',count:3,created_at:'2026-10-14 10:00:00',has_server_copy:false};
const last = {at:'2026-10-14 20:30:00',status:'restored',restored:2,duplicates:1,conflicts:0};
const backups = [{...backup,last_restore:null},{...backup,last_restore:last},{...backup,last_restore:{...last,status:'partial',conflicts:1}}];
let selected = null;
const context = {
    document:{getElementById:id=>elements[id] ||= node(),createElement:node},
    window:{openAuditSavedRestore(file,label){selected={file,label};}},
    fetch:async()=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,backups})}),
};
vm.runInNewContext(script,context);
(async () => {
    await context.window.refreshAuditBackupHistory();
    const rows = elements.auditBackupList.children;
    const texts = row => row.children.map(child=>child.textContent);
    assert.ok(texts(rows[0]).includes('Not yet restored'));
    assert.ok(texts(rows[0]).includes('Restore'));
    assert.ok(texts(rows[1]).includes('Restored'));
    assert.ok(texts(rows[1]).includes('Check / Restore again'));
    assert.ok(texts(rows[1]).some(text=>text.startsWith('Last restored:')));
    assert.ok(texts(rows[1]).includes('Restored: 2 · Already present: 1 · Conflicts skipped: 0'));
    assert.ok(texts(rows[2]).includes('Partially restored'));
    assert.ok(texts(rows[1]).includes('Server copy removed · Local CSV required to restore'));
    rows[1].children.find(child=>child.tag==='button').listeners.click();
    assert.deepEqual(selected,{file:backup.file,label:backup.label});
    console.log('Backup status UI passed: initial/restored/partial badges, latest counts/date, truthful storage message, and repeat-restore action.');
})().catch(error=>{console.error(error);process.exitCode=1;});
