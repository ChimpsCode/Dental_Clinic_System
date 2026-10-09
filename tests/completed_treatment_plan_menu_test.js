const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../master/dentist/dentist_treatments.php'), 'utf8');
const plans = [{ id: 1, overall_status: 'Completed' }, { id: 2, overall_status: 'Active' }, { id: 3, overall_status: ' In-Progress ' }];
const nodes = {};
function button(id) {
    return { getAttribute: () => String(id), classList: { add() {}, remove() {} },
        getBoundingClientRect: () => ({ right: 100, left: 60, top: 60 }) };
}
const context = vm.createContext({
    treatmentPlans: plans,
    window: { innerWidth: 1280, innerHeight: 900 },
    document: {
        createElement: () => ({ style: {}, innerHTML: '' }),
        body: { appendChild(node) { nodes[node.id] = node; } },
        getElementById(id) {
            if (id !== 'tpPlansList') throw new Error('Completed plan attempted to open an editable form');
            return nodes.tpPlansList;
        }
    },
    renderSessionsHtml: () => ''
});
const start = source.indexOf('    function isTreatmentPlanCompleted(');
vm.runInContext(source.slice(start, source.indexOf('    // Close kebab when clicking elsewhere', start)), context);
for (const plan of plans) {
    context.toggleTpKebab(button(plan.id));
    const menu = nodes.tpKebabDropdown.innerHTML;
    assert(menu.includes(`viewTreatmentPlan(${plan.id})`), 'View is available for every plan');
    assert.equal(menu.includes(`editTreatmentPlan(${plan.id})`), plan.overall_status !== 'Completed', 'Only unfinished plans offer Edit');
}
const renderStart = source.indexOf('    function renderTreatmentPlans(');
vm.runInContext(source.slice(renderStart, source.indexOf('    function renderTreatmentHistory(', renderStart)), context);
nodes.tpPlansList = { innerHTML: '' };
context.renderTreatmentPlans([{ id: 1, status: 'completed', total_sessions: 1, completed_sessions: 1 }]);
assert(!nodes.tpPlansList.innerHTML.includes('editTreatmentPlan('), 'Completed plan cards are read-only');
context.renderTreatmentPlans([{ id: 2, status: 'active', total_sessions: 1, completed_sessions: 0 }]);
assert(nodes.tpPlansList.innerHTML.includes('editTreatmentPlan(2)'), 'Active plan cards retain Edit');
const editStart = source.indexOf('    function editTreatmentPlan(');
vm.runInContext(source.slice(editStart, source.indexOf('    function saveTreatmentPlan(', editStart)), context);
assert.doesNotThrow(() => context.editTreatmentPlan(1), 'Direct edit calls for completed plans leave the form untouched');
console.log('PASS: completed plans offer View only, unfinished plans retain Edit, and completed plan cards cannot open the edit form.');
