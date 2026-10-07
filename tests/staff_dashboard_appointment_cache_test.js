const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const router = fs.readFileSync(path.join(__dirname, '../assets/js/shell_router.js'), 'utf8');
const dashboard = fs.readFileSync(path.join(__dirname, '../master/staff/staff-dashboard.php'), 'utf8');
const cacheStart = router.indexOf('    window.appCache = {');
const cacheEnd = router.indexOf('    function seedCurrentPage()', cacheStart);
const fetched = [];
const pending = [];
const context = vm.createContext({
    window: {}, console,
    resolvePage: page => page,
    fetchFragment(key) {
        fetched.push(key);
        return new Promise(resolve => pending.push(() => {
            context.window.appCache.set(key, 'fresh Scheduled list');
            resolve('fresh Scheduled list');
        }));
    },
    openDashboardConfirmModal(message, confirm) { confirm(); },
    openDashboardDeclineModal(message, confirm) { confirm('Requested cancellation'); },
    fetch: async () => ({ json: async () => ({ success: true }) }),
    showDashboardMessage() {}, loadDashboardData() {}
});
vm.runInContext(router.slice(cacheStart, cacheEnd), context);
const approvalStart = dashboard.indexOf('function approveAppointment(id) {');
const approvalEnd = dashboard.indexOf('// Cancelled Modal Functions', approvalStart);
vm.runInContext(dashboard.slice(approvalStart, approvalEnd), context);

(async () => {
    for (const action of ['approveAppointment', 'declineAppointment']) {
        context.window.appCache.set('staff_appointments.php', 'stale Pending Approval list');
        vm.runInContext(action + '(12);', context);
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(context.window.appCache.get('staff_appointments.php'), null,
            action + ': navigation cannot reuse stale appointments while refresh is pending');
        assert.equal(fetched.at(-1), 'staff_appointments.php');
        pending.shift()();
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(context.window.appCache.get('staff_appointments.php'), 'fresh Scheduled list');
    }
    console.log('PASS: dashboard approval/decline invalidate appointments immediately and cache the fresh list.');
})().catch(error => { console.error(error); process.exitCode = 1; });
