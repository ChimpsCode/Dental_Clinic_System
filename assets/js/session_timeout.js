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
            '#stoOverlay .sto-card{width:100%;max-width:440px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px 22px 18px;box-shadow:0 18px 48px rgba(15,23,42,.28);text-align:left;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#111827;}',
            '#stoOverlay .sto-header{display:flex;align-items:flex-start;gap:14px;}',
            '#stoOverlay .sto-icon{flex:0 0 auto;width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:#f59e0b;}',
            '#stoOverlay .sto-icon.is-logout{color:#dc2626;}',
            '#stoOverlay .sto-copy{min-width:0;flex:1;}',
            '#stoOverlay .sto-title{margin:2px 0 6px;font-size:1.05rem;font-weight:600;line-height:1.35;color:#111827;}',
            '#stoOverlay .sto-body{margin:0;font-size:.95rem;line-height:1.5;color:#4b5563;}',
            '#stoOverlay .sto-body[hidden]{display:none;}',
            '#stoOverlay .sto-count{font-variant-numeric:tabular-nums;font-weight:600;color:#111827;}',
            '#stoOverlay .sto-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;margin-top:22px;}',
            '#stoOverlay .sto-btn{appearance:none;border-radius:8px;padding:8px 16px;font-size:.9rem;font-weight:600;cursor:pointer;transition:background .15s ease,border-color .15s ease,transform .1s ease;}',
            '#stoOverlay .sto-btn:active{transform:scale(.98);}',
            '#stoOverlay .sto-btn-primary{background:#2563eb;border:1px solid #2563eb;color:#fff;}',
            '#stoOverlay .sto-btn-primary:hover{background:#1d4ed8;border-color:#1d4ed8;}',
            '#stoOverlay .sto-btn-ghost{background:#fff;border:1px solid #d1d5db;color:#111827;}',
            '#stoOverlay .sto-btn-ghost:hover{background:#f9fafb;border-color:#9ca3af;}',
            '#stoOverlay .sto-spinner{width:22px;height:22px;margin:10px 0 0;border:3px solid #e5e7eb;border-top-color:#2563eb;border-radius:50%;animation:stoSpin .7s linear infinite;}',
            '@keyframes stoSpin{to{transform:rotate(360deg);}}'
        ].join('');
        document.head.appendChild(style);
    }

    function warningIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.1c.58 0 1.1.31 1.38.82l8.16 14.4c.28.5.28 1.12 0 1.62A1.58 1.58 0 0 1 20.16 21H3.84a1.58 1.58 0 0 1-1.38-2.06l8.16-14.4c.28-.51.8-.84 1.38-.84z"/><rect x="11.1" y="8.5" width="1.8" height="6.1" rx=".9" fill="#fff"/><circle cx="12" cy="17.15" r="1.1" fill="#fff"/></svg>';
    }

    function blockedIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.2" fill="none" stroke="currentColor" stroke-width="1.9"/><rect x="6.4" y="10.9" width="11.2" height="2.2" rx="1.1" fill="currentColor"/><path d="M7.6 7.6l8.8 8.8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>';
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
                '<div class="sto-header">' +
                    '<div class="sto-icon" id="stoIcon">' + warningIcon() + '</div>' +
                    '<div class="sto-copy">' +
                        '<h2 class="sto-title" id="stoTitle">Your session is about to expire.</h2>' +
                        '<p class="sto-body" id="stoBody"></p>' +
                        '<div class="sto-spinner" id="stoSpinner" hidden></div>' +
                    '</div>' +
                '</div>' +
                '<div class="sto-actions" id="stoActions">' +
                    '<button type="button" class="sto-btn sto-btn-ghost" id="stoLogout">Log Out</button>' +
                    '<button type="button" class="sto-btn sto-btn-primary" id="stoStay">Continue Session</button>' +
                    '<button type="button" class="sto-btn sto-btn-primary" id="stoOk" hidden>OK</button>' +
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
        var okBtn = overlay.querySelector('#stoOk');

        var lastActivity = Date.now();
        var idleTimer = null;
        var countdownTimer = null;
        var watchdogTimer = null;
        var warningShown = false;
        var loggingOut = false;
        var sessionAlreadyExpired = false;
        var lastPingAt = 0;
        // Set when the server reports the account behind this session was
        // deleted/archived, so the copy and destination can say why.
        var accountRemoved = '';
        var serverRedirect = '';
        var loginUrl = options.loginUrl || '../../master/welcome-page/login.php?expired=1';

        function clearTimers() {
            if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
            if (countdownTimer) { clearTimeout(countdownTimer); countdownTimer = null; }
            if (watchdogTimer) { clearTimeout(watchdogTimer); watchdogTimer = null; }
        }

        function setWarningCopy(secondsLeft) {
            titleEl.textContent = 'Your session is about to expire.';
            bodyEl.hidden = false;
            bodyEl.innerHTML = 'You will be logged out in <span class="sto-count" id="stoCount" aria-live="assertive">' + formatSeconds(secondsLeft) + '</span>.';
            iconEl.classList.remove('is-logout');
            iconEl.innerHTML = warningIcon();
            iconEl.hidden = false;
            spinnerEl.hidden = true;
            actionsEl.hidden = false;
            stayBtn.hidden = false;
            logoutBtn.hidden = false;
            okBtn.hidden = true;
        }

        function setTimedOutCopy() {
            if (accountRemoved) {
                iconEl.classList.remove('is-logout');
                iconEl.innerHTML = blockedIcon();
                iconEl.hidden = false;
                titleEl.textContent = 'Your account is no longer active';
                bodyEl.hidden = false;
                bodyEl.textContent = accountRemoved === 'deleted'
                    ? 'The clinic has deleted your account, so you have been signed out.'
                    : 'The clinic has deactivated your account, so you have been signed out.';
                spinnerEl.hidden = true;
                actionsEl.hidden = false;
                stayBtn.hidden = true;
                logoutBtn.hidden = true;
                okBtn.hidden = false;
                return;
            }
            titleEl.textContent = 'Session time out';
            bodyEl.textContent = '';
            bodyEl.hidden = true;
            iconEl.classList.remove('is-logout');
            iconEl.innerHTML = warningIcon();
            iconEl.hidden = false;
            spinnerEl.hidden = true;
            actionsEl.hidden = false;
            stayBtn.hidden = true;
            logoutBtn.hidden = true;
            okBtn.hidden = false;
        }

        function openOverlay(focusEl) {
            if (!overlay.isConnected) {
                document.body.appendChild(overlay);
            }
            overlay.classList.add('is-open');
            try { (focusEl || stayBtn).focus(); } catch (e) {}
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
                        res.clone().json().then(function (data) {
                            if (data && data.account_removed) {
                                accountRemoved = data.reason === 'archived' ? 'archived' : 'deleted';
                            }
                            if (data && data.redirect) {
                                serverRedirect = data.redirect;
                            }
                        }).catch(function () {});
                        beginLogout(true);
                    }
                }).catch(function () { /* ignore network errors while still on the page */ });
            } catch (e) { /* ignore */ }
        }

        function remainingMs() {
            return logoutAfterMs - (Date.now() - lastActivity);
        }

        function goToLogin(alreadyExpired) {
            if (serverRedirect) {
                global.location.href = serverRedirect;
                return;
            }
            global.location.href = alreadyExpired ? loginUrl : logoutUrl;
        }

        function beginLogout(alreadyExpired) {
            if (loggingOut) return;
            loggingOut = true;
            warningShown = true;
            sessionAlreadyExpired = !!alreadyExpired;
            clearTimers();
            setTimedOutCopy();
            openOverlay(okBtn);
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
            if (loggingOut) return;
            loggingOut = true;
            warningShown = true;
            clearTimers();
            goToLogin(false);
        });

        okBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goToLogin(sessionAlreadyExpired);
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
