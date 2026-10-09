const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../master/dentist/dentist_patients.php'), 'utf8');
const menuCode = source.slice(source.indexOf('// Patient Kebab Menu Functions'), source.indexOf('// Open prescription modal for patient'));
const bindings = source.slice(source.indexOf('if (window.__dentistPatientKebabClickHandler)'), source.indexOf('// Add Appointment Modal Functions'));
const handlers = new Map(), nodes = [], actions = [];
function listen(type, fn) { if (!handlers.has(type)) handlers.set(type, new Set()); handlers.get(type).add(fn); }
function unlisten(type, fn) { handlers.get(type)?.delete(fn); }
function element() {
    const classes = new Set();
    const listeners = new Map();
    return {
        isConnected: false, style: {}, innerHTML: '', dataset: {},
        classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
        addEventListener(type, fn) { if (!listeners.has(type)) listeners.set(type, new Set()); listeners.get(type).add(fn); },
        removeEventListener(type, fn) { listeners.get(type)?.delete(fn); },
        remove() { this.isConnected = false; },
        emit(type, target) { for (const fn of [...(listeners.get(type) || [])]) fn({ target, preventDefault() {}, stopPropagation() {} }); },
        closest: () => null,
        getBoundingClientRect: () => ({ left: 500, right: 530, top: 100, bottom: 130 })
    };
}
const document = {
    createElement: element,
    getElementById: id => nodes.find(node => node.isConnected && node.id === id) || null,
    body: { appendChild(node) { node.isConnected = true; nodes.push(node); } },
    addEventListener: listen, removeEventListener: unlisten
};
const window = { innerWidth: 1280, innerHeight: 900, location: {}, addEventListener: listen, removeEventListener: unlisten };
const context = vm.createContext({ document, window,
    viewPatientDetails: id => actions.push(['view', id]),
    openPatientPrescriptionModal() {}, openBillingModal() {}, openAddAppointmentModal() {}, openTreatmentPlanModal() {}
});
const button = () => {
    const node = element(); node.dataset.patientId = '42'; node.isConnected = true;
    node.closest = selector => selector === '.patient-kebab-btn' ? node : selector === '#patientsTableBody' ? {} : null;
    return node;
};
function click(target) {
    const event = { target, preventDefault() { this.defaultPrevented = true; }, stopImmediatePropagation() { this.stopped = true; } };
    for (const handler of [...handlers.get('click')]) { handler(event); if (event.stopped) break; }
    return event;
}
assert(!menuCode.includes("className = 'patient-kebab-backdrop'"), 'Opening the dropdown creates no full-page overlay over navigation');
for (let visit = 0; visit < 3; visit++) {
    vm.runInContext(menuCode + bindings, context);
    assert.equal(handlers.get('click').size, 1, 'Returning to the tab replaces the previous click handler');
    assert.equal(handlers.get('keydown').size, 1);
    assert.equal(handlers.get('resize').size, 1);
    const trigger = button();
    click({ closest: selector => trigger.closest(selector) });
    let portal = document.getElementById('patientKebabDropdownPortal');
    assert(portal.classList.contains('show'), 'SVG clicks open the menu after tab navigation');
    for (const action of ['view', 'prescriptions', 'billing', 'session', 'appointment', 'treatment_plan']) {
        assert(portal.innerHTML.includes(`data-action="${action}"`), 'Existing menu action retained: ' + action);
    }
    click(trigger);
    assert(!portal.classList.contains('show'), 'A second click closes the menu');
    click(trigger);
    const navigationClick = click({ closest: () => null });
    assert(!portal.classList.contains('show'), 'Outside clicks close the menu');
    assert(!navigationClick.defaultPrevented && !navigationClick.stopped, 'Navigation clicks close the menu and continue on the first click');
    // The shell removes body portals when switching tabs.
    nodes.forEach(node => node.remove());
    click(button());
    portal = document.getElementById('patientKebabDropdownPortal');
    assert(portal?.classList.contains('show'), 'A disconnected portal is recreated');
    const link = { dataset: { action: 'view', id: '42' } };
    portal.emit('click', { closest: () => link });
    assert.equal(actions.length, visit + 1, 'Menu action fires exactly once per click');
    assert.deepEqual(actions.at(-1), ['view', 42]);
    assert.equal(nodes.filter(node => node.isConnected).length, 1, 'Only the dropdown survives, without an overlay blocking the sidebar');
}
// Both page scripts must be valid and their global page state must permit reruns.
for (const match of source.matchAll(/<script>([\s\S]*?)<\/script>/g)) {
    const code = match[1].replace(/<\?php[\s\S]*?\?>/g, '[]');
    new vm.Script(code);
    assert(!/^(?:const|let) /m.test(code), 'Page-global state can be initialized again by the shell');
}
console.log('PASS: repeat tab navigation, single event handlers, disconnected portal recovery and preserved patient actions.');
