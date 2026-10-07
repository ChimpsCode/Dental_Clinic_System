const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const page = fs.readFileSync(path.join(__dirname, '../master/dentist/dentist_appointments.php'), 'utf8');
const start = page.indexOf('<script>', page.indexOf('<!-- Kebab Menu Functions -->')) + 8;
const end = page.indexOf('    function markArrived', start);
const script = page.slice(start, end);

function setup(immediate) {
    const nodes = [], listeners = {};
    function element() {
        const classes = new Set();
        return {
            style: {}, attributes: {}, innerHTML: '',
            classList: { add(name) { classes.add(name); }, remove(name) { classes.delete(name); }, contains(name) { return classes.has(name); } },
            setAttribute(key, value) { this.attributes[key] = value; },
            getAttribute(key) { return this.attributes[key] || ''; },
            getBoundingClientRect() { return { width: 220, height: 176 }; },
            closest() { return null; }
        };
    }
    const document = {
        createElement: element,
        getElementById(id) { return nodes.find(node => node.id === id) || null; },
        body: { appendChild(node) { if (!nodes.includes(node)) nodes.push(node); } },
        addEventListener(type, callback) {
            if (type === 'DOMContentLoaded' && immediate) return callback();
            (listeners[type] ||= new Set()).add(callback);
        },
        removeEventListener(type, callback) { listeners[type]?.delete(callback); }
    };
    const window = { innerWidth: 375, innerHeight: 667 };
    const context = vm.createContext({ window, document });
    vm.runInContext(script, context);
    if (!immediate) for (const callback of listeners.DOMContentLoaded) callback();
    function button(status = 'Scheduled') {
        const node = element();
        node.attributes = { 'data-appt-id': '42', 'data-status': status, 'data-checked-in': '' };
        node.getBoundingClientRect = () => ({ left: 330, right: 364, top: 640 });
        node.closest = selector => selector === '.appt-kebab-btn' ? node : null;
        return node;
    }
    function click(target) {
        for (const callback of Array.from(listeners.click || [])) callback({ target, preventDefault() {}, stopPropagation() {} });
    }
    return { document, window, context, nodes, listeners, button, click };
}

for (const immediate of [false, true]) {
    const app = setup(immediate);
    const button = app.button();
    // A click on the SVG inside the scheduled button must open the menu.
    app.click({ closest: selector => button.closest(selector) });
    const menu = app.document.getElementById('apptKebabDropdown');
    assert.ok(menu.classList.contains('show'), 'Scheduled menu opens on ' + (immediate ? 'sidebar navigation' : 'full page load'));
    for (const action of ['viewAppointmentDetails(42)', 'editAppointment(42)', 'markArrived(42)', 'openCancelReasonModal(42)']) {
        assert.ok(menu.innerHTML.includes(action), 'Scheduled action is available: ' + action);
    }
    assert.equal(button.attributes['aria-expanded'], 'true');
    assert.ok(parseFloat(menu.style.left) >= 12 && parseFloat(menu.style.left) + 220 <= 363);
    assert.ok(parseFloat(menu.style.top) + 176 <= 655, 'All actions fit vertically');
    app.click(button);
    assert.ok(!menu.classList.contains('show'), 'Second click closes the menu');
    assert.equal(button.attributes['aria-expanded'], 'false');
    app.click(button);
    app.click({ closest: () => null });
    assert.ok(!menu.classList.contains('show'), 'Outside click closes the menu');
    app.context.initApptKebabMenu();
    assert.equal(app.listeners.click.size, 1, 'Reinitialization does not duplicate click handlers');
    assert.equal(app.nodes.length, 2, 'Reinitialization reuses menu portals');
    const refreshedButton = app.button();
    app.click(refreshedButton);
    assert.ok(menu.classList.contains('show'), 'New buttons after appointment refresh remain clickable');
}
const navigation = setup(true);
for (let revisit = 0; revisit < 3; revisit++) vm.runInContext(script, navigation.context);
assert.equal(navigation.listeners.click.size, 1, 'Revisiting the page does not toggle the menu twice');
navigation.click(navigation.button());
assert.ok(navigation.document.getElementById('apptKebabDropdown').classList.contains('show'));
console.log('PASS: full load, sidebar navigation, page revisits, refreshed buttons, scheduled actions, and mobile positioning');
