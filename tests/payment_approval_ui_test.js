const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const handlers = {}, nodes = {}, requests = [];
class Element {
    constructor(tag) {
        this.tagName = tag; this.children = []; this.dataset = {}; this.value = ''; this.textContent = '';
        this.classList = { toggle: (name, enabled) => {
            const classes = new Set((this.className || '').split(' ').filter(Boolean));
            if (enabled) classes.add(name); else classes.delete(name);
            this.className = [...classes].join(' ');
        } };
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    showModal() { this.open = true; }
    close() { this.open = false; }
    focus() { this.focused = true; }
    reset() { for (const control of this.controls || []) control.value = control.defaultValue || ''; }
    querySelector() { return this.button; }
    querySelectorAll() { return (this.controls || []).filter(control => control.type === 'password'); }
    closest(selector) { return selector === '.' + this.className || selector === '#' + this.id ? this : (this.parent?.closest(selector) || null); }
}
for (const id of ['paymentVoidForm','paymentVoidSummary','paymentVoidError','paymentVoidAdmin','paymentVoidConfirm','paymentVoidDialog','paymentVoidReason','paymentVoidPin','paymentVoidCancel','paymentApprovalPinForm','paymentApprovalPinStatus','paymentApprovalPinConfigured']) {
    nodes[id] = new Element('div'); nodes[id].id = id;
}
nodes.paymentVoidReason.name = 'reason'; nodes.paymentVoidAdmin.name = 'admin_id';
nodes.paymentVoidPin.name = 'pin'; nodes.paymentVoidPin.type = 'password';
nodes.paymentVoidForm.controls = [nodes.paymentVoidReason,nodes.paymentVoidAdmin,nodes.paymentVoidPin];
nodes.paymentVoidForm.button = nodes.paymentVoidConfirm;
const pinForm = nodes.paymentApprovalPinForm;
const pinEdit = nodes.paymentApprovalPinEdit = new Element('button');
pinEdit.id = 'paymentApprovalPinEdit'; pinEdit.dataset.editing = 'false'; pinEdit.type = 'button'; pinEdit.className = 'btn-edit';
const pinFields = ['password','pin','confirmation'].map(name => {
    const input = new Element('input'); input.name = name; input.type = 'password'; input.disabled = true; input.parent = pinForm; return input;
});
const pinToken = new Element('input'); pinToken.name = 'csrf'; pinToken.defaultValue = 'settings-token'; pinToken.value = 'settings-token';
pinForm.controls = [...pinFields,pinToken]; pinForm.button = pinEdit;
let reloads = 0, nextResult, deferredFetch;
const context = vm.createContext({
    document: { createElement: tag => new Element(tag), getElementById: id => nodes[id], addEventListener(type, handler) { handlers[type] = handler; } },
    window: { location: { reload() { reloads++; } } },
    FormData: class { constructor(form) { this.form = form; } [Symbol.iterator]() { return this.form.controls.map(control => [control.name,control.value])[Symbol.iterator](); } },
    async fetch(url, options = {}) {
        requests.push({url,options});
        if (deferredFetch) { const response = deferredFetch; deferredFetch = null; return response; }
        return { ok: true, async json() { return nextResult; } };
    },
    console
});
const script = fs.readFileSync(require('node:path').join(__dirname,'../assets/js/payment-approval.js'),'utf8');
vm.runInContext(script,context);
const click = target => handlers.click({target,preventDefault(){}});
const submit = target => handlers.submit({target,preventDefault(){}});
(async () => {
    const malicious = '<img src=x onerror=alert(1)>';
    const row = context.window.PaymentApproval.renderHistoryRow({id:125,amount:5000,payment_method:'Cash',reference_number:malicious,recorded_by:malicious});
    assert.equal(row.children[3].textContent, malicious);
    assert.equal(row.children[3].innerHTML, undefined, 'History strings must be treated as text');
    assert.equal(row.children.length,5,'Payment history must have a dedicated Action column');
    assert.equal(row.children[3].children.some(child => child.className==='payment-void-trigger'),false,'Reference must contain only payment details');
    const trigger = row.children[4].children.at(-1);
    nextResult = {success:true,csrf:'test-token',admins:[]};
    await click(trigger);
    assert.equal(nodes.paymentVoidDialog.open,true);
    assert.equal(nodes.paymentVoidConfirm.disabled,true,'Approval must be blocked without a configured admin');
    assert.equal(nodes.paymentVoidAdmin.disabled,true);
    assert.match(nodes.paymentVoidAdmin.children[0].textContent,/No admins/);
    assert.match(nodes.paymentVoidError.textContent,/configure/);
    nextResult = {success:true,csrf:'test-token',admins:[{id:7,first_name:'Admin',last_name:'Approver'}]};
    await click(trigger);
    assert.equal(nodes.paymentVoidAdmin.children[1].value,7);
    assert.equal(nodes.paymentVoidAdmin.disabled,false);
    assert.equal(nodes.paymentVoidAdmin.value,'7');
    assert.equal(nodes.paymentVoidConfirm.disabled,false);
    nodes.paymentVoidReason.value='Recorded by mistake'; nodes.paymentVoidAdmin.value='7'; nodes.paymentVoidPin.value='246810';
    nextResult = {success:false,message:'Admin approval PIN is incorrect.'};
    await submit(nodes.paymentVoidForm);
    const body = JSON.parse(requests.at(-1).options.body);
    assert.equal(body.csrf,'test-token'); assert.equal(body.admin_id,'7'); assert.equal(body.payment_id,125);
    assert.equal(body.reason,'Recorded by mistake'); assert.equal(body.action,'void_payment');
    assert.equal(nodes.paymentVoidPin.value,'','Failed attempts must clear the PIN');
    assert.equal(nodes.paymentVoidReason.value,'Recorded by mistake','Failure must retain the reason for correction');
    assert.equal(nodes.paymentVoidDialog.open,true);
    assert.equal(reloads,0);
    nodes.paymentVoidPin.value='246810'; nextResult={success:true,message:'Payment voided.'};
    await submit(nodes.paymentVoidForm);
    assert.equal(nodes.paymentVoidDialog.open,false); assert.equal(reloads,1); assert.equal(nodes.paymentVoidPin.value,'');
    nextResult = {success:true,csrf:'test-token',admins:[{id:7,username:'admin'}]};
    await click(trigger); nodes.paymentVoidPin.value='246810'; await click(nodes.paymentVoidCancel);
    assert.equal(nodes.paymentVoidPin.value,''); assert.equal(nodes.paymentVoidDialog.open,false);
    nextResult = {success:true,csrf:'test-token',admins:[{id:7,username:'admin-a'},{id:8,username:'admin-b'}]};
    await click(trigger);
    assert.equal(nodes.paymentVoidAdmin.value,'');
    assert.equal(nodes.paymentVoidConfirm.disabled,true,'Multiple admins require an explicit selection');
    nodes.paymentVoidAdmin.value='8'; handlers.change({target:nodes.paymentVoidAdmin});
    assert.equal(nodes.paymentVoidConfirm.disabled,false);
    nodes.paymentVoidAdmin.value=''; handlers.change({target:nodes.paymentVoidAdmin});
    assert.equal(nodes.paymentVoidConfirm.disabled,true,'Returning to placeholder must block confirmation');
    await click(nodes.paymentVoidCancel);
    let finishOldFetch;
    deferredFetch = new Promise(resolve => { finishOldFetch = resolve; });
    const oldClick = click(trigger);
    assert.equal(nodes.paymentVoidAdmin.disabled,true,'Dropdown must remain disabled while loading');
    await click(nodes.paymentVoidCancel);
    nextResult = {success:true,csrf:'new-token',admins:[{id:9,username:'new-admin'}]};
    await click(trigger);
    finishOldFetch({ok:true,json:async () => ({success:true,csrf:'old-token',admins:[{id:7,username:'old-admin'}]})});
    await oldClick;
    assert.equal(nodes.paymentVoidAdmin.value,'9','Late response must not overwrite a reopened dropdown');
    assert.equal(nodes.paymentVoidAdmin.children[1].textContent,'new-admin');
    await click(nodes.paymentVoidCancel);
    const voided = context.window.PaymentApproval.renderHistoryRow({id:125,amount:5000,voided_at:'2030-01-07 10:00:00',void_reason:malicious,approved_by:'Admin B',requested_by:'Staff A'});
    assert.equal(voided.children[4].children.some(child => child.className==='payment-void-trigger'),false,'Voided payments cannot be voided again');
    assert.equal(voided.children[4].children[0].textContent,'VOIDED');
    const requestsBeforeEdit = requests.length;
    await click(pinEdit);
    assert.equal(requests.length,requestsBeforeEdit,'Edit must not submit PIN settings');
    assert.equal(pinEdit.textContent,'Save changes');
    assert.equal(pinEdit.disabled,true); assert.equal(pinEdit.className,'btn-primary is-idle');
    assert.ok(pinFields.every(input => !input.disabled),'Edit must unlock credential fields');
    pinFields[0].value='account-password'; pinFields[1].value='246810'; pinFields[2].value='123456';
    handlers.input({target:pinFields[2]}); assert.equal(pinEdit.disabled,true,'Mismatched PIN must keep Save grey');
    pinFields[2].value='246810'; handlers.input({target:pinFields[2]});
    assert.equal(pinEdit.disabled,false); assert.equal(pinEdit.className,'btn-primary','Valid changes must enable blue Save');
    nextResult = {success:false,message:'Current account password is incorrect.'};
    await submit(pinForm);
    assert.equal(pinEdit.dataset.editing,'true','Failed save must remain in edit mode');
    assert.equal(pinEdit.disabled,true,'Cleared credentials must disable Save after rejection');
    assert.ok(pinFields.every(input => input.value===''),'Failed save must clear credentials');
    pinFields[0].value='account-password'; pinFields[1].value='246810'; pinFields[2].value='246810';
    handlers.input({target:pinFields[2]}); nextResult={success:true,message:'Payment Approval PIN configured.'};
    await submit(pinForm);
    const settingsBody=JSON.parse(requests.at(-1).options.body);
    assert.equal(settingsBody.action,'set_pin'); assert.equal(settingsBody.csrf,'settings-token');
    assert.equal(pinEdit.textContent,'Edit'); assert.equal(pinEdit.type,'button'); assert.equal(pinEdit.className,'btn-edit');
    assert.equal(pinEdit.disabled,false); assert.ok(pinFields.every(input => input.disabled && input.value===''));
    assert.equal(nodes.paymentApprovalPinConfigured.textContent,'PIN configured');
    assert.equal(nodes.paymentApprovalPinConfigured.className,'twofa-status on');
    const before=handlers.submit; vm.runInContext(script,context); assert.equal(handlers.submit,before,'PJAX reload must not install duplicate listeners');
    console.log('PASS: payment history, admin selection, PIN settings Edit/Save states, validation, rejection/retry, CSRF, credential clearing and duplicate listener protection.');
})().catch(error => { console.error(error); process.exitCode=1; });
