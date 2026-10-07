const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const requests = [];
let current;
let search;
let tabs;
function makeContent(status) {
    return {
        status,
        setAttribute() {},
        replaceWith(next) {
            current = next;
            search = { value: '', dataset: {}, addEventListener(type, handler) { this[type] = handler; } };
            tabs = ['all', 'week'].map(filter => ({ dataset: { filter }, classList: { toggle(name, active) { this.active = active; } }, addEventListener() {} }));
        }
    };
}
current = makeContent('Pending Approval');
search = { value: 'Maria', dataset: {}, addEventListener() {} };
tabs = [];
const document = {
    dispatchEvent() {},
    addEventListener() {},
    getElementById(id) { return id === 'searchAppointment' ? search : null; },
    querySelector(selector) {
        if (selector === '.content-main') return current;
        if (selector === '.filter-tabs span.active') return { dataset: { filter: 'week' } };
        return null;
    },
    querySelectorAll(selector) { return selector === '.filter-tabs span' ? tabs : []; }
};
const context = vm.createContext({
    CustomEvent: class {},
    document, console,
    window: { location: { href: 'http://localhost/master/staff/staff_appointments.php' } },
    fetch(url, options) {
        return new Promise(resolve => requests.push({ url, options, respond(status) { resolve({ ok: true, text: async () => status }); } }));
    },
    DOMParser: class {
        parseFromString(status) {
            return {
                querySelector: () => makeContent(status),
                scripts: [{ textContent: 'var appointments = ' + JSON.stringify([{ id: 12, status }]) + ';' }]
            };
        }
    }
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/js/appointment_ajax_refresh.js'), 'utf8'), context);

(async () => {
    const refresh = context.window.refreshAppointmentsAjax;
    const background = refresh();
    assert.strictEqual(refresh(), background, 'Ordinary polls reuse an active request');
    const approvalRefresh = refresh({ afterCurrent: true });
    assert.equal(requests.length, 1, 'Post-approval refresh waits for the older request');
    requests[0].respond('Pending Approval');
    await background;
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests.length, 2, 'Approval triggers a fresh fetch after the older request');
    requests[1].respond('Scheduled');
    assert.equal(await approvalRefresh, true);
    assert.equal(current.status, 'Scheduled', 'Scheduled replaces stale pending content without a page reload');
    assert.equal(context.window.appointments[0].status, 'Scheduled', 'Calendar and action data are updated');
    assert.equal(search.value, 'Maria', 'Staff search survives content replacement');
    assert.equal(tabs.find(tab => tab.dataset.filter === 'week').classList.active, true);
    assert.equal(typeof search.input, 'function', 'Search remains functional after replacement');
    assert.equal(requests[1].options.cache, 'no-store');

    const page = fs.readFileSync(path.join(__dirname, '../master/staff/staff_appointments.php'), 'utf8');
    const start = page.indexOf('    function approveAppointment(id) {');
    const end = page.indexOf('    function declineAppointment(id)', start);
    let refreshed = false;
    let noticed = false;
    const approvalContext = vm.createContext({
        console,
        openAppointmentDecisionModal(action, confirm) { assert.equal(action, 'approve'); confirm(); },
        fetch: async () => ({ json: async () => ({ success: true }) }),
        refreshAppointmentsAjax(options) {
            assert.equal(options.afterCurrent, true);
            refreshed = true;
            return Promise.resolve(true);
        },
        showAppointmentNoticeModal() { assert(refreshed); noticed = true; }
    });
    vm.runInContext(page.slice(start, end) + '\napproveAppointment(12);', approvalContext);
    await new Promise(resolve => setImmediate(resolve));
    assert(noticed, 'Approval refreshes the list before showing success');
    console.log('PASS: staff approval refresh, background request race, updated appointment data, and preserved search/filter.');
})().catch(error => { console.error(error); process.exitCode = 1; });
