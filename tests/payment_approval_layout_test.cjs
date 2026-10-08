const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { spawnSync } = require('node:child_process');
const browser = [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
].find(file => fs.existsSync(file));
assert.ok(browser, 'Chrome or Edge is required for the layout check');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'payment-approval-layout-'));
const dialog = fs.readFileSync(path.join(__dirname, '../includes/payment_approval_dialog.php'), 'utf8')
    .replace(/<script src="[^"]+"><\/script>/, '');
const script = fs.readFileSync(path.join(__dirname, '../assets/js/payment-approval.js'), 'utf8');
const fixture = path.join(temporary, 'dialog.html');
fs.writeFileSync(fixture, `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>* { margin:0;padding:0;box-sizing:border-box; } body { font:16px Arial;background:#eef2f7; } h2 { margin-bottom:12px; } p { margin:10px 0; }</style></head><body>
${dialog}
<script>window.fetch = async () => ({ok:true,json:async () => ({success:true,csrf:'test-token',admins:[{id:7,username:'admin-a',first_name:'Admin',last_name:'A'},{id:8,username:'admin-b',first_name:'Admin',last_name:'B'}]})});</script>
<script>${script}</script>
<script>
const button = document.createElement('button'); button.className='payment-void-trigger'; button.dataset.paymentId='125'; button.dataset.amount='5000';document.body.append(button);button.click();
setTimeout(() => {
    const modal=document.getElementById('paymentVoidDialog'), select=document.getElementById('paymentVoidAdmin');
    select.value='8'; select.dispatchEvent(new Event('change',{bubbles:true}));
    const rect=modal.getBoundingClientRect();
    const result={width:innerWidth,height:innerHeight,x:rect.x,y:rect.y,dialogWidth:rect.width,dialogHeight:rect.height,open:modal.open,admins:select.options.length,selected:select.value,selectEnabled:!select.disabled,confirmEnabled:!document.getElementById('paymentVoidConfirm').disabled};
    const report=document.createElement('pre'); report.id='layoutResult'; report.textContent=JSON.stringify(result);document.body.append(report);
},100);
</script></body></html>`);
// Headless Chrome clamps its outer window to a minimum width on Windows.
// Assertions use the actual reported viewport, including the narrow case.
for (const [name, width, height] of [['desktop',1280,900],['narrow',390,844]]) {
    const result = spawnSync(browser, [
        '--headless', '--no-first-run', '--no-default-browser-check',
        '--disable-background-networking', '--disable-extensions',
        '--user-data-dir=' + path.join(temporary, name),
        '--window-size=' + width + ',' + height, '--force-device-scale-factor=1',
        '--virtual-time-budget=1000', '--dump-dom', pathToFileURL(fixture).href
    ], { encoding:'utf8', timeout:20000, maxBuffer:4*1024*1024 });
    assert.equal(result.status,0,result.error?.message || result.stderr);
    const match=result.stdout.match(/<pre id="layoutResult">([^<]+)<\/pre>/);
    assert.ok(match,'Browser must report dialog geometry');
    const layout=JSON.parse(match[1]);
    assert.ok(layout.open);
    assert.ok(Math.abs(layout.x + layout.dialogWidth/2 - layout.width/2) < 2,'Dialog must be horizontally centered');
    assert.ok(Math.abs(layout.y + layout.dialogHeight/2 - layout.height/2) < 2,'Dialog must be vertically centered');
    assert.ok(layout.dialogWidth <= layout.width - 30,'Dialog must fit the viewport width');
    assert.ok(layout.dialogHeight <= layout.height - 30,'Dialog must fit the viewport height');
    assert.equal(layout.admins,3);assert.equal(layout.selected,'8');
    assert.ok(layout.selectEnabled && layout.confirmEnabled,'Native dropdown must allow choosing an admin');
    console.log('PASS: '+name+' centered dialog and working native admin dropdown ('+layout.width+'×'+layout.height+').');
}
