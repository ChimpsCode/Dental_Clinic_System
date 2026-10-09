(function () {
    'use strict';

    // The shell can run this script again when returning to Payment.
    if (window.__staffPaymentLiveRefresh) window.__staffPaymentLiveRefresh.stop();
    var table = document.getElementById('paymentTableBody');
    if (!table) return;
    var pageUrl = window.location.href;
    var stopped = false;
    var pending = null;
    var controller = null;
    var selectors = ['#paymentTableBody', '#staffPaymentSummary', '#staffPaymentPagination'];

    function isCurrentPage() {
        return !stopped && table.isConnected && window.location.href === pageUrl;
    }

    function isBusy() {
        if (window.payActiveButton || window.recordPaymentInFlight) return true;
        return Array.prototype.some.call(document.querySelectorAll('.modal-overlay'), function (modal) {
            var style = window.getComputedStyle(modal);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });
    }

    function refresh() {
        if (!isCurrentPage()) { stop(); return Promise.resolve(false); }
        if (pending) return pending;
        if (document.hidden || isBusy()) return Promise.resolve(false);
        var url = new URL(pageUrl);
        url.searchParams.set('__pjax', '1');
        controller = new AbortController();
        pending = fetch(url.href, {
            cache: 'no-store', credentials: 'same-origin', signal: controller.signal
        }).then(function (response) {
            if (!response.ok) throw new Error('Payment refresh failed');
            return response.text();
        }).then(function (html) {
            // A dialog may have opened or the user may have navigated while fetching.
            if (!isCurrentPage() || document.hidden || isBusy()) return false;
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var dataNode = doc.getElementById('staffPaymentLiveData');
            var targets = selectors.map(function (selector) { return document.querySelector(selector); });
            var sources = selectors.map(function (selector) { return doc.querySelector(selector); });
            if (!dataNode || targets.some(function (node) { return !node; })
                || sources.some(function (node) { return !node; })) return false;
            var data = JSON.parse(dataNode.textContent);
            if (!data || typeof data !== 'object' || Array.isArray(data)) return false;
            targets.forEach(function (target, index) {
                if (target.innerHTML !== sources[index].innerHTML) target.innerHTML = sources[index].innerHTML;
            });
            // Existing delegated action handlers stay in place; printing reads fresh data.
            window.staffPaymentData = data;
            if (window.appCache && typeof window.appCache.set === 'function') window.appCache.set(pageUrl, html);
            return true;
        }).catch(function (error) {
            if (error.name !== 'AbortError') console.warn('Unable to refresh payment list:', error);
            return false;
        }).finally(function () { pending = null; controller = null; });
        return pending;
    }

    function stop() {
        if (stopped) return;
        stopped = true;
        clearInterval(timer);
        if (controller) controller.abort();
        observer.disconnect();
        window.removeEventListener('focus', refresh);
        document.removeEventListener('visibilitychange', refresh);
    }

    var observer = new MutationObserver(function () { if (!isCurrentPage()) stop(); });
    observer.observe(table.closest('.content-area') || document.body, { childList: true, subtree: true });
    var timer = setInterval(refresh, 5000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    window.__staffPaymentLiveRefresh = { refresh: refresh, stop: stop };
    refresh();
}());
