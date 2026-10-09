const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/staff_payment_live_refresh.js'), 'utf8');
const requests = [], listeners = {}, cache = [], timers = new Map();
let timerId = 0, observer, modalOpen = false;
const nodes = Object.fromEntries(['paymentTableBody', 'staffPaymentSummary', 'staffPaymentPagination'].map(id => [id, {
    innerHTML: 'old-' + id, isConnected: true, closest: () => ({})
}]));
const document = {
    hidden: false, body: {},
    getElementById: id => nodes[id],
    querySelector: selector => nodes[selector.slice(1)],
    querySelectorAll: () => [{ style: { display: modalOpen ? 'flex' : 'none', visibility: 'visible' } }],
    addEventListener: (type, fn) => { listeners[type] = fn; },
    removeEventListener: type => { delete listeners[type]; }
};
const window = {
    location: { href: 'http://localhost/master/staff/staff_payment.php?search=Juan&status=unpaid&page=2' },
    staffPaymentData: { old: {} },
    getComputedStyle: node => node.style,
    addEventListener: (type, fn) => { listeners[type] = fn; },
    removeEventListener: type => { delete listeners[type]; },
    appCache: { set: (url, html) => cache.push({ url, html }) }
};
const context = vm.createContext({
    window, document, URL, AbortController, console,
    setInterval(fn, delay) { assert.equal(delay, 5000); timers.set(++timerId, fn); return timerId; },
    clearInterval: id => timers.delete(id),
    MutationObserver: class {
        constructor(fn) { observer = this; this.fn = fn; }
        observe() {}
        disconnect() { this.disconnected = true; }
    },
    DOMParser: class {
        parseFromString(html) {
            if (html === 'login') return { getElementById: () => null, querySelector: () => null };
            return {
                getElementById: () => ({ textContent: JSON.stringify({ 109: { billing_id: 109, patient: 'Juan', amount: 5000 } }) }),
                querySelector: selector => ({ innerHTML: html + '-' + selector.slice(1) })
            };
        }
    },
    fetch(url, options) {
        return new Promise(resolve => requests.push({ url, options,
            respond(html, ok = true) { resolve({ ok, text: async () => html }); }
        }));
    }
});
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    vm.runInContext(code, context);
    const live = window.__staffPaymentLiveRefresh;
    const initial = live.refresh();
    assert.equal(requests.length, 1, 'Opening cached Payment immediately fetches current records');
    assert.strictEqual(initial, live.refresh(), 'Concurrent polls share one fetch');
    const url = new URL(requests[0].url);
    assert.equal(url.searchParams.get('search'), 'Juan');
    assert.equal(url.searchParams.get('status'), 'unpaid');
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(url.searchParams.get('__pjax'), '1');
    assert.equal(requests[0].options.cache, 'no-store');
    requests[0].respond('new');
    assert.equal(await initial, true);
    assert.equal(nodes.paymentTableBody.innerHTML, 'new-paymentTableBody');
    assert.equal(nodes.staffPaymentSummary.innerHTML, 'new-staffPaymentSummary');
    assert.equal(nodes.staffPaymentPagination.innerHTML, 'new-staffPaymentPagination');
    assert.equal(window.staffPaymentData[109].amount, 5000, 'Print button receives fresh receipt data');
    assert.equal(cache.length, 1, 'Returning through shell cache gets updated records');

    modalOpen = true;
    assert.equal(await live.refresh(), false);
    window.payActiveButton = {};
    modalOpen = false;
    assert.equal(await live.refresh(), false);
    window.payActiveButton = null;
    document.hidden = true;
    assert.equal(await live.refresh(), false);
    document.hidden = false;
    assert.equal(requests.length, 1, 'Open dialogs, action menus and hidden tabs defer updates');

    const interrupted = live.refresh();
    modalOpen = true;
    requests[1].respond('interrupted');
    assert.equal(await interrupted, false);
    assert.equal(nodes.paymentTableBody.innerHTML, 'new-paymentTableBody', 'A dialog opened during fetch remains undisturbed');
    modalOpen = false;
    const invalid = live.refresh();
    requests[2].respond('login');
    assert.equal(await invalid, false);
    assert.equal(nodes.paymentTableBody.innerHTML, 'new-paymentTableBody', 'Expired session responses do not clear payment rows');

    listeners.focus();
    assert.equal(requests.length, 4, 'Returning to tab refreshes promptly');
    requests[3].respond('latest');
    await flush();
    assert.equal(nodes.paymentTableBody.innerHTML, 'latest-paymentTableBody');
    const oldRequest = live.refresh();
    nodes.paymentTableBody.isConnected = false;
    window.location.href = 'http://localhost/master/staff/staff_appointments.php';
    observer.fn();
    assert.equal(timers.size, 0, 'Leaving Payment stops polling');
    assert.equal(requests[4].options.signal.aborted, true, 'Leaving Payment aborts its pending request');
    requests[4].respond('stale');
    assert.equal(await oldRequest, false);
    assert.equal(nodes.paymentTableBody.innerHTML, 'latest-paymentTableBody', 'Late results cannot alter another page');

    nodes.paymentTableBody.isConnected = true;
    window.location.href = 'http://localhost/master/staff/staff_payment.php';
    vm.runInContext(code, context);
    assert.equal(timers.size, 1, 'Revisiting Payment starts exactly one poller');
    requests[5].respond('returned');
    await flush();
    window.__staffPaymentLiveRefresh.stop();
    assert.equal(timers.size, 0);
    console.log('PASS: live payment rows, totals, pagination, print data, filters, dialog protection and navigation cleanup.');
})().catch(error => { console.error(error); process.exitCode = 1; });
