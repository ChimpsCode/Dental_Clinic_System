/**
 * Inactivity session timeout
 * Shows a countdown modal before logging the user out, then explains
 * the reason on the login page via logout.php?reason=inactivity.
 */
(function (global) {
    'use strict';

    if (global.__sessionTimeoutInit) {
        return;
    }

    function injectStyles() {
        if (document.getElementById('sto-styles')) return;
        var style = document.createElement('style');
        style.id = 'sto-styles';
        style.textContent = [
            '#stoOverlay{position:fixed;inset:0;z-index:2147483000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);}',
            '#stoOverlay.is-open{display:flex;}',
            '#stoOverlay .sto-card{width:100%;max-width:420px;background:#fff;border-radius:16px;padding:28px 24px 22px;box-shadow:0 24px 60px rgba(15,23,42,.28);text-align:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#0f172a;}',
            '#stoOverlay .sto-icon{width:56px;height:56px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:#2563eb;}',
            '#stoOverlay .sto-icon.is-logout{background:#fef2f2;color:#dc2626;}',
            '#stoOverlay .sto-title{margin:0 0 8px;font-size:1.25rem;font-weight:700;letter-spacing:-.02em;}',
            '#stoOverlay .sto-body{margin:0 0 18px;font-size:.95rem;line-height:1.55;color:#475569;}',
            '#stoOverlay .sto-count{font-variant-numeric:tabular-nums;font-weight:700;color:#1d4ed8;font-size:1.05em;}',
            '#stoOverlay .sto-actions{display:flex;flex-direction:column;gap:10px;}',
            '#stoOverlay .sto-btn{appearance:none;border:0;border-radius:10px;padding:12px 16px;font-size:.95rem;font-weight:600;cursor:pointer;transition:background .15s ease,transform .1s ease;}',
            '#stoOverlay .sto-btn:active{transform:scale(.98);}',
            '#stoOverlay .sto-btn-primary{background:#2563eb;color:#fff;}',
            '#stoOverlay .sto-btn-primary:hover{background:#1d4ed8;}',
            '#stoOverlay .sto-btn-ghost{background:transparent;color:#64748b;}',
            '#stoOverlay .sto-btn-ghost:hover{background:#f8fafc;color:#334155;}',
            '#stoOverlay .sto-spinner{width:28px;height:28px;margin:4px auto 12px;border:3px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:stoSpin .7s linear infinite;}',
            '@keyframes stoSpin{to{transform:rotate(360deg);}}',
            '@media (min-width:480px){#stoOverlay .sto-actions{flex-direction:row-reverse;}#stoOverlay .sto-btn{flex:1;}}'
        ].join('');
        document.head.appendChild(style);
    }

    function clockIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
    }

    function lockIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
    }

    function formatSeconds(total) {
        var s = Math.max(0, Math.ceil(total));
        return String(s) + (s === 1 ? ' second' : ' seconds');
    }

    function buildModal() {
        var overlay = document.createElement('div');
        overlay.id = 'stoOverlay';
        overlay.setAttribute('data-persist-layout', '1');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'stoTitle');
        overlay.innerHTML =
            '<div class="sto-card">' +
                '<div class="sto-icon" id="stoIcon">' + clockIcon() + '</div>' +
                '<div class="sto-spinner" id="stoSpinner" hidden></div>' +
                '<h2 class="sto-title" id="stoTitle">Are you still there?</h2>' +
                '<p class="sto-body" id="stoBody"></p>' +
                '<div class="sto-actions" id="stoActions">' +
                    '<button type="button" class="sto-btn sto-btn-primary" id="stoStay">Stay logged in</button>' +
                    '<button type="button" class="sto-btn sto-btn-ghost" id="stoLogout">Log out now</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function initSessionTimeout(options) {
        options = options || {};
        if (options.enabled === false) return;
        if (global.__sessionTimeoutInit) return;

        var logoutAfterMs = parseInt(options.logoutAfterMs, 10);
        if (!logoutAfterMs || logoutAfterMs < 5000) return;

        global.__sessionTimeoutInit = true;

        var logoutUrl = options.logoutUrl || '../../logout.php?reason=inactivity';
        var pingUrl = options.pingUrl || '../../session_ping.php';
        var warningMs = Math.min(60000, Math.max(15000, Math.floor(logoutAfterMs * 0.1)));
        if (warningMs >= logoutAfterMs) {
            warningMs = Math.max(5000, Math.floor(logoutAfterMs / 3));
        }

        injectStyles();
        var overlay = buildModal();
        var iconEl = overlay.querySelector('#stoIcon');
        var spinnerEl = overlay.querySelector('#stoSpinner');
        var titleEl = overlay.querySelector('#stoTitle');
        var bodyEl = overlay.querySelector('#stoBody');
        var actionsEl = overlay.querySelector('#stoActions');
        var stayBtn = overlay.querySelector('#stoStay');
        var logoutBtn = overlay.querySelector('#stoLogout');

        var lastActivity = Date.now();
        var idleTimer = null;
        var countdownTimer = null;
        var watchdogTimer = null;
        var warningShown = false;
        var loggingOut = false;
        var lastPingAt = 0;
        var loginUrl = options.loginUrl || '../../master/welcome-page/login.php?expired=1';

        function clearTimers() {
            if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
            if (countdownTimer) { clearTimeout(countdownTimer); countdownTimer = null; }
            if (watchdogTimer) { clearTimeout(watchdogTimer); watchdogTimer = null; }
        }

        function setWarningCopy(secondsLeft) {
            titleEl.textContent = 'Are you still there?';
            bodyEl.innerHTML = 'You have been inactive. For your security, this account will be logged out in <span class="sto-count" id="stoCount" aria-live="assertive">' + formatSeconds(secondsLeft) + '</span>.';
            iconEl.classList.remove('is-logout');
            iconEl.innerHTML = clockIcon();
            iconEl.hidden = false;
            spinnerEl.hidden = true;
            actionsEl.hidden = false;
        }

        function setLogoutCopy() {
            titleEl.textContent = 'Logging you out';
            bodyEl.textContent = 'Your session ended due to inactivity. Taking you to the login page…';
            iconEl.classList.add('is-logout');
            iconEl.innerHTML = lockIcon();
            iconEl.hidden = true;
            spinnerEl.hidden = false;
            actionsEl.hidden = true;
        }

        function openOverlay() {
            if (!overlay.isConnected) {
                document.body.appendChild(overlay);
            }
            overlay.classList.add('is-open');
            try { stayBtn.focus(); } catch (e) {}
        }

        function closeOverlay() {
            overlay.classList.remove('is-open');
        }

        function ping(force) {
            if (!pingUrl) return;
            var now = Date.now();
            if (!force && now - lastPingAt < 60000) return;
            lastPingAt = now;
            try {
                fetch(pingUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                }).then(function (res) {
                    if (res.status === 401) {
                        beginLogout(true);
                    }
                }).catch(function () { /* ignore network errors while still on the page */ });
            } catch (e) { /* ignore */ }
        }

        function remainingMs() {
            return logoutAfterMs - (Date.now() - lastActivity);
        }

        function beginLogout(alreadyExpired) {
            if (loggingOut) return;
            loggingOut = true;
            warningShown = true;
            clearTimers();
            setLogoutCopy();
            openOverlay();
            setTimeout(function () {
                global.location.href = alreadyExpired ? loginUrl : logoutUrl;
            }, 1400);
        }

        function showWarning() {
            if (loggingOut) return;
            var left = remainingMs();
            if (left <= 0) {
                beginLogout(false);
                return;
            }
            if (warningShown) {
                var countEl = overlay.querySelector('#stoCount');
                if (countEl) countEl.textContent = formatSeconds(left / 1000);
                return;
            }
            warningShown = true;
            setWarningCopy(left / 1000);
            openOverlay();

            if (countdownTimer) clearTimeout(countdownTimer);
            function tickCountdown() {
                var secondsLeft = remainingMs() / 1000;
                var liveCount = overlay.querySelector('#stoCount');
                if (liveCount) liveCount.textContent = formatSeconds(secondsLeft);
                if (secondsLeft <= 0) {
                    beginLogout(false);
                    return;
                }
                countdownTimer = setTimeout(tickCountdown, 250);
            }
            tickCountdown();
        }

        // Wall-clock check so background-tab timer throttling cannot skip the modal.
        function evaluateIdleState() {
            if (loggingOut) return;
            var left = remainingMs();
            if (left <= 0) {
                beginLogout(false);
                return;
            }
            if (left <= warningMs) {
                showWarning();
            }
        }

        function dismissWarning() {
            warningShown = false;
            if (countdownTimer) { clearTimeout(countdownTimer); countdownTimer = null; }
            closeOverlay();
        }

        function scheduleIdle() {
            if (idleTimer) clearTimeout(idleTimer);
            var delay = Math.max(0, logoutAfterMs - warningMs);
            idleTimer = setTimeout(showWarning, delay);
        }

        function markActive() {
            if (loggingOut || warningShown) return;
            lastActivity = Date.now();
            scheduleIdle();
            ping(false);
        }

        stayBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (loggingOut) return;
            lastActivity = Date.now();
            dismissWarning();
            scheduleIdle();
            ping(true);
        });

        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            beginLogout(false);
        });

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, markActive, { passive: true });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden || loggingOut) return;
            evaluateIdleState();
        });
        global.addEventListener('focus', evaluateIdleState);
        global.addEventListener('pageshow', evaluateIdleState);

        // Chained setTimeout (not setInterval) so admin PJAX timer cleanup cannot
        // silently kill the inactivity watcher after a sidebar click.
        function armWatchdog() {
            if (loggingOut) return;
            watchdogTimer = setTimeout(function () {
                evaluateIdleState();
                armWatchdog();
            }, 1000);
        }

        scheduleIdle();
        armWatchdog();
    }

    global.initSessionTimeout = initSessionTimeout;
})(window);
