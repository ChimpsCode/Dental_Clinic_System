const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const role of ['admin', 'staff', 'dentist']) {
    const source = fs.readFileSync(path.join(__dirname, `../master/${role}/${role}_appointments.php`), 'utf8');
    const dentist = role === 'dentist';
    const start = source.indexOf(dentist ? 'function openApptKebabDropdown(button)' : 'function getApptMenuItems(apptId)');
    const end = source.indexOf(dentist ? 'function closeApptKebabDropdown()' : 'function positionApptKebabDropdown(button)', start);
    assert.ok(start >= 0 && end > start);
    const classes = { add() {}, remove() {} };
    const rect = () => ({ left: 100, right: 120, top: 100, width: 200, height: 200 });
    const button = {
        dataset: { cancellationRequest: '7' }, classList: classes,
        getAttribute(name) { return ({ 'data-status': 'Scheduled', 'data-checked-in': '', 'data-appt-id': '200' })[name]; },
        getBoundingClientRect: rect, setAttribute() {}
    };
    const dropdown = { innerHTML: '', classList: classes, style: {}, getBoundingClientRect: rect };
    const context = vm.createContext({
        document: { querySelector: () => button }, window: { innerWidth: 1000, innerHeight: 800 },
        apptKebabDropdown: dropdown, apptKebabBackdrop: { classList: classes }
    });
    vm.runInContext(source.slice(start, end), context);
    function menu() {
        if (dentist) { context.openApptKebabDropdown(button); return dropdown.innerHTML; }
        return context.getApptMenuItems(200);
    }
    const pending = menu();
    assert.ok(pending.includes('View Request'));
    assert.ok(pending.includes('data-cancellation-request="7"'));
    assert.ok(pending.indexOf('View Request') < pending.indexOf('Reschedule'), 'View Request follows View');
    assert.ok(!pending.includes('Mark as Arrived'), 'Pending cancellation hides arrival');
    button.dataset.cancellationRequest = '0';
    const declined = menu();
    assert.ok(!declined.includes('View Request'));
    assert.ok(declined.includes('Mark as Arrived'), 'Declining restores arrival');
    console.log(`PASS: ${role} cancellation menu and arrival restoration.`);
}

// A staff card used to omit data-id. The refresh must still use its menu ID
// rather than erase a pending cancellation badge rendered by PHP.
const reviewSource = fs.readFileSync(path.join(__dirname, '../assets/js/cancellation-requests.js'), 'utf8');
const renderStart = reviewSource.indexOf('    function render()');
const renderEnd = reviewSource.indexOf('    function mount()', renderStart);
const badgeClasses = new Set(['status-requesting-cancellation']);
const badge = {
    textContent: 'Requesting for cancellation', dataset: { originalStatus: 'Scheduled' },
    classList: { contains: name => badgeClasses.has(name), add: name => badgeClasses.add(name), remove: name => badgeClasses.delete(name) }
};
const menu = { dataset: { apptId: '200', cancellationRequest: '1' } };
const card = { dataset: { status: 'scheduled' }, querySelector: selector => selector === '.appt-kebab-btn' ? menu : badge };
const renderContext = vm.createContext({
    requests: [{ id: 1, appointment_id: 200 }],
    document: { querySelectorAll: selector => selector === '.appointment-mini-card' ? [card] : [] }
});
vm.runInContext(reviewSource.slice(renderStart, renderEnd), renderContext);
renderContext.render();
assert.equal(badge.textContent, 'Requesting for cancellation');
assert.equal(menu.dataset.cancellationRequest, 1);
card.dataset.id = '200';
renderContext.render();
assert.equal(badge.textContent, 'Requesting for cancellation');
renderContext.requests = [];
renderContext.render();
assert.equal(badge.textContent, 'Scheduled');
assert.equal(menu.dataset.cancellationRequest, '0');
assert.ok(!badgeClasses.has('status-requesting-cancellation'));
console.log('PASS: pending cancellation badge survives refresh with either card or menu ID and clears after review.');
