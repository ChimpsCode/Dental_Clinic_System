const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../master/dentist/dentist_consultation.php'), 'utf8');
const code = source.slice(source.indexOf('function tpMoney('), source.indexOf('// ===================== HELPERS =====================', source.indexOf('function tpMoney(')));
function element() {
    return { style: {}, children: [], value: '', listeners: {},
        appendChild(child) { this.children.push(child); },
        addEventListener(name, fn) { this.listeners[name] = fn; },
        set innerHTML(value) { this.html = value; this.children = []; },
        get innerHTML() { return this.html || ''; }
    };
}
const elements = { tpCostBreakdown: element(), tp_estimated_cost: element(), tpCostAdjustment: element(), tpExistingPlansContainer: element() };
function row(name, cost, number) {
    const select = element(); select.value = name;
    const custom = element();
    return { dataset: { session: String(number), estimatedCost: String(cost) }, querySelector(selector) { return selector === '.session-treatment' ? select : custom; } };
}
let rows = [row('Cleaning', 800, 1), row('Extraction', 800, 2)];
const context = {
    isPastView: false, window: { _cachedServices: [{ name: 'Cleaning', price: 800 }, { name: 'Extraction', price: 500 }] },
    document: { getElementById(id) { return elements[id]; }, querySelectorAll() { return rows; }, createElement() { return element(); } },
    escapeHtml(value) { return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
};
vm.createContext(context); vm.runInContext(code, context);
context.tpRenderCostBreakdown(); assert.equal(elements.tp_estimated_cost.value, '1600.00');
const costInput = elements.tpCostBreakdown.children[1].children[1]; costInput.value = '500'; costInput.listeners.input.call(costInput);
assert.equal(elements.tp_estimated_cost.value, '1300.00');
const plans = [{ id: 1, status: 'in_progress', total_sessions: 2, total_estimated_cost: 1300, sessions_data: JSON.stringify([
    { date: '2030-01-07', time: '09:00:00', treatment: 'Cleaning', estimated_cost: 800, appointment_id: 1, status: 'Completed' },
    { date: '2030-01-08', time: '10:00:00', treatment: '<custom treatment>', estimated_cost: 500, appointment_id: 2, status: 'Scheduled' }
]) }];
context.renderConsultPlans(plans);
let html = elements.tpExistingPlansContainer.children[0].innerHTML;
assert(html.includes('In Progress'));
assert(html.includes('Session 1 complete'));
assert(html.includes('Session 2 scheduled'));
assert(html.includes('tpOpenReschedule(1,2)'));
assert(!html.includes('tpOpenReschedule(1,1)'));
assert(html.includes('&lt;custom treatment&gt;'));
context.isPastView = true; context.renderConsultPlans(plans);
html = elements.tpExistingPlansContainer.children[0].innerHTML;
assert(!html.includes('tpOpenReschedule(')); assert(!html.includes('tpCancelScheduled('));
console.log('PASS: editable cost totals, automatic plan/session labels, rescheduling only scheduled sessions, escaping and read-only history.');
