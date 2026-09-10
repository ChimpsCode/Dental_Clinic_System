/**
 * Confirm Logout clicks and intercept browser Back on main dashboards.
 * Pads many same-document history entries so spam Back cannot leave the site.
 */
(function (global) {
    'use strict';

    if (global.__logoutConfirmInstalled) {
        return;
    }
    global.__logoutConfirmInstalled = true;

    var GUARD_STATE = { appLogoutGuard: true };
    var GUARD_PAD = 50;
    var asking = false;

    function logoutUrlFrom(anchor) {
        if (anchor && anchor.getAttribute) {
            var href = anchor.getAttribute('href');
            if (href) {
                return href;
            }
        }
        return '../../logout.php';
    }

    function isDashboardPage() {
        var path = (global.location.pathname || '').toLowerCase();
        return /staff-dashboard\.php$/.test(path)
            || /dentist_dashboard\.php$/.test(path)
            || /patient-dashboard\.php$/.test(path)
            || /admin_dashboard\.php$/.test(path);
    }

    function isAdminPjaxState(state) {
        return !!(state && state.pjax);
    }

    function fillGuard() {
        if (!global.history || !global.history.pushState) {
            return;
        }
        var i;
        for (i = 0; i < GUARD_PAD; i += 1) {
            try {
                global.history.pushState(GUARD_STATE, '', global.location.href);
            } catch (e) {
                break;
            }
        }
    }

    function installGuard() {
        if (!global.history || !global.history.replaceState || !global.history.pushState) {
            return;
        }
        try {
            global.history.replaceState(GUARD_STATE, '', global.location.href);
        } catch (e) {}
        fillGuard();
    }

    function askLogout(onYes, onNo) {
        if (asking) {
            return;
        }
        asking = true;

        var goYes = function () {
            asking = false;
            if (typeof onYes === 'function') {
                onYes();
            }
        };
        var goNo = function () {
            asking = false;
            if (typeof onNo === 'function') {
                onNo();
            }
        };

        var options = {
            title: 'Logout',
            kind: 'info',
            confirmText: 'Yes',
            cancelText: 'No',
            onCancel: goNo
        };

        if (typeof global.appAsk === 'function') {
            global.appAsk('Would you like to logout?', goYes, options);
            return;
        }

        if (global.confirm('Would you like to logout?')) {
            goYes();
        } else {
            goNo();
        }
    }

    function goLogout(url) {
        global.location.href = url || '../../logout.php';
    }

    document.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a[href*="logout.php"]') : null;
        if (!link) {
            return;
        }
        e.preventDefault();
        askLogout(function () {
            goLogout(logoutUrlFrom(link));
        });
    }, true);

    if (isDashboardPage() && global.history && global.history.pushState) {
        installGuard();
        global.addEventListener('popstate', function (e) {
            if (isAdminPjaxState(e.state)) {
                return;
            }
            fillGuard();
            askLogout(function () {
                goLogout('../../logout.php');
            }, function () {
                fillGuard();
            });
        });
    }
})(window);
