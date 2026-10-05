const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const field = (value = '') => ({ value, type: 'text', required: true, disabled: false, style: {}, events: {}, dataset: {},
    addEventListener(name, fn) { this.events[name] = fn; },
    dispatchEvent(event) { this.events[event.type]?.(event); },
    checkValidity() { return !this.required || this.value.trim() !== ''; },
    reportValidity() { return this.checkValidity(); },
    focus() { this.focused = true; }
});
const group = field();
const method = field('email');
const email = field('sample@example.com');
const phone = field('09123456789');
email.closest = () => group;
const hint = field();
const status = field();
status.textContent = '';
const mobileInput = field();
const mobileGroup = field();
const summary = field();
summary.required = false;
const summaryLabel = field();
const password = field('StrongPassword123!');
const error = field();
const next = field();
const submit = field();
const back = field();
back.dataset.registrationBack = 'contact';
const panels = ['details', 'contact', 'password'].map(stage => {
    const panel = field();
    panel.dataset.registrationStage = stage;
    panel.querySelectorAll = () => stage === 'contact' ? [email, mobileInput] : stage === 'password' ? [password] : [phone];
    panel.querySelector = () => panel.querySelectorAll()[0];
    return panel;
});
const fields = {
    '[name="contact_method"]': method, '[name="email"]': email, '[name="phone"]': phone,
    '[data-contact-hint]': hint, '#reg-contact-mobile': mobileInput,
    '[data-contact-mobile-group]': mobileGroup, '#reg-contact-summary': summary,
    '[data-contact-summary-label]': summaryLabel, '[data-contact-error]': error,
    '[data-registration-contact-next]': next, 'button[type="submit"]': submit
};
const form = {
    events: {},
    querySelector: selector => fields[selector],
    querySelectorAll: selector => selector === '[data-registration-stage]' ? panels : [back],
    closest: () => null,
    addEventListener(name, fn) { this.events[name] = fn; }
};
const context = { window: {}, document: { querySelector: () => form, getElementById: () => status }, Event: class { constructor(type) { this.type = type; } } };
vm.runInNewContext(fs.readFileSync(path.join(root, 'assets/js/patient_registration_contact.js'), 'utf8'), context);
assert.equal(email.required, true);
assert.equal(email.disabled, false);
assert.equal(context.window.PatientRegistration.identifier(), 'sample@example.com');
assert.deepEqual(panels.map(panel => panel.style.display), ['block', 'none', 'none']);
context.window.PatientRegistration.showStage('contact');
assert.equal(submit.style.display, 'none');
next.events.click();
assert.deepEqual(panels.map(panel => panel.style.display), ['none', 'none', 'block']);
assert.equal(summary.value, 'sample@example.com');
back.events.click();
assert.deepEqual(panels.map(panel => panel.style.display), ['none', 'block', 'none']);
assert.equal(password.value, 'StrongPassword123!');
email.value = '';
next.events.click();
assert.equal(panels[1].style.display, 'block', 'Blank email cannot advance to password');
context.window.PatientRegistration.select('phone');
assert.equal(email.required, false);
assert.equal(email.disabled, true);
assert.equal(group.style.display, 'none');
assert.equal(context.window.PatientRegistration.identifier(), '09123456789');
assert.match(hint.textContent, /text a verification code/);
assert.equal(mobileGroup.style.display, '');
mobileInput.value = '123';
next.events.click();
assert.equal(panels[1].style.display, 'block', 'Invalid mobile cannot advance');
assert.match(error.textContent, /11-digit/);
mobileInput.value = '09987654321';
mobileInput.events.input();
next.events.click();
assert.equal(summary.value, '09987654321');
assert.equal(summaryLabel.textContent, 'mobile number');
assert.equal(phone.value, '09987654321', 'Chosen mobile must be saved in the original registration field');
back.events.click();
let prevented = false;
let stopped = false;
form.events.submit({ preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } });
assert.equal(prevented && stopped, true, 'Enter on contact must advance without creating the account');
assert.equal(panels[2].style.display, 'block');
context.window.PatientRegistration.select('email');
assert.equal(email.required, true);
assert.equal(email.disabled, false);
assert.equal(group.style.display, '');
assert.match(hint.textContent, /email a verification code/);

// Compile page-specific JavaScript, including inline modal/booking handlers.
let scripts = 0;
for (const file of ['login.php', 'home.php', 'service-detail.php', 'reset_password.php']) {
    const page = fs.readFileSync(path.join(root, 'master/welcome-page', file), 'utf8');
    const stripped = page.replace(/<\?(?:php|=)[\s\S]*?\?>/g, 'null');
    for (const [, attributes, source] of stripped.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        if (/\bsrc=|application\/ld\+json/i.test(attributes)) continue;
        new vm.Script(source, { filename: file + ':inline' });
        scripts++;
    }
}
console.log(`Registration contact controls passed; ${scripts} inline scripts compiled.`);
