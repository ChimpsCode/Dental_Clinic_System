/**
 * In-page notice/confirm modal — same palette as Admin Edit Info.
 * Intercepts alert(); confirm() stays native unless callers use appAsk().
 */
(function (global) {
    if (global.__appNoticeModalInstalled) return;
    global.__appNoticeModalInstalled = true;

    var nativeAlert = global.alert.bind(global);
    var nativeConfirm = global.confirm.bind(global);
    var queue = [];
    var isOpen = false;
    var current = null;

    function inferKind(message) {
        var text = String(message || '').toLowerCase();
        if (/(error|fail|unable|invalid|required|please |not found|do not match|missing|cannot|can't)/.test(text)) {
            return 'error';
        }
        if (/(success|updated|saved|recorded|scheduled|done|completed)/.test(text)) {
            return 'success';
        }
        return 'info';
    }

    function ensureStyles() {
        if (document.getElementById('app-notice-modal-styles')) return;
        var style = document.createElement('style');
        style.id = 'app-notice-modal-styles';
        style.textContent = [
            '#app-notice-modal{display:none;position:fixed;inset:0;z-index:2147483000;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.45);font-family:Inter,system-ui,sans-serif;}',
            '#app-notice-modal.is-open{display:flex;}',
            '#app-notice-modal .app-notice-card{background:#fff;border-radius:16px;max-width:420px;width:100%;padding:28px 24px 24px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);animation:appNoticeIn .18s ease;}',
            '#app-notice-modal .app-notice-icon{width:56px;height:56px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;line-height:1;}',
            '#app-notice-modal .app-notice-icon.success{background:#d1fae5;color:#059669;}',
            '#app-notice-modal .app-notice-icon.error{background:#fee2e2;color:#dc2626;}',
            '#app-notice-modal .app-notice-icon.info{background:#e0f2fe;color:#0284c7;}',
            '#app-notice-modal .app-notice-title{margin:0 0 8px;font-size:18px;font-weight:700;font-family:Inter,system-ui,sans-serif;}',
            '#app-notice-modal .app-notice-title.success{color:#065f46;}',
            '#app-notice-modal .app-notice-title.error{color:#991b1b;}',
            '#app-notice-modal .app-notice-title.info{color:#0c4a6e;}',
            '#app-notice-modal .app-notice-message{margin:0 0 22px;color:#4b5563;font-size:14px;line-height:1.5;white-space:pre-wrap;word-break:break-word;}',
            '#app-notice-modal .app-notice-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}',
            '#app-notice-modal .app-notice-ok,#app-notice-modal .app-notice-cancel{min-width:120px;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;font-family:Inter,system-ui,sans-serif;display:inline-flex;align-items:center;justify-content:center;text-align:center;line-height:1;}',
            '#app-notice-modal .app-notice-ok{border:none;color:#fff;background:#0ea5e9;}',
            '#app-notice-modal .app-notice-ok.error{background:#dc2626;}',
            '#app-notice-modal .app-notice-cancel{display:none;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;}',
            '#app-notice-modal.is-confirm .app-notice-cancel{display:inline-block;}',
            '@keyframes appNoticeIn{from{opacity:0;transform:translateY(8px) scale(.98);}to{opacity:1;transform:none;}}'
        ].join('');
        (document.head || document.documentElement).appendChild(style);
    }

    function ensureDom() {
        ensureStyles();
        var overlay = document.getElementById('app-notice-modal');
        if (overlay) return overlay;
        if (!document.body) return null;

        overlay = document.createElement('div');
        overlay.id = 'app-notice-modal';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = [
            '<div class="app-notice-card">',
            '  <div class="app-notice-icon" id="appNoticeIcon"></div>',
            '  <h3 class="app-notice-title" id="appNoticeTitle"></h3>',
            '  <p class="app-notice-message" id="appNoticeMessage"></p>',
            '  <div class="app-notice-actions">',
            '    <button type="button" class="app-notice-cancel" id="appNoticeCancel">Cancel</button>',
            '    <button type="button" class="app-notice-ok" id="appNoticeOk">OK</button>',
            '  </div>',
            '</div>'
        ].join('');
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) finish(false);
        });
        document.body.appendChild(overlay);
        document.getElementById('appNoticeOk').addEventListener('click', function () { finish(true); });
        document.getElementById('appNoticeCancel').addEventListener('click', function () { finish(false); });
        return overlay;
    }

    function finish(accepted) {
        var overlay = document.getElementById('app-notice-modal');
        if (overlay) overlay.classList.remove('is-open', 'is-confirm');
        isOpen = false;
        var item = current;
        current = null;
        if (item) {
            try {
                if (accepted && typeof item.onOk === 'function') item.onOk();
                if (!accepted && typeof item.onCancel === 'function') item.onCancel();
            } catch (e) {}
        }
        if (queue.length) {
            global.setTimeout(showNext, 40);
        }
    }

    function showNext() {
        if (isOpen || !queue.length) return;
        var item = queue.shift();
        var overlay = ensureDom();
        if (!overlay) {
            if (item.mode === 'confirm') {
                if (nativeConfirm(item.message) && typeof item.onOk === 'function') item.onOk();
            } else {
                nativeAlert(item.message);
                if (typeof item.onOk === 'function') item.onOk();
            }
            showNext();
            return;
        }

        var kind = item.kind || inferKind(item.message);
        var isConfirm = item.mode === 'confirm';
        var title = item.title || (isConfirm ? 'Please Confirm' : (kind === 'success' ? 'Success' : 'Notice'));
        current = item;
        isOpen = true;

        var icon = document.getElementById('appNoticeIcon');
        var titleEl = document.getElementById('appNoticeTitle');
        var messageEl = document.getElementById('appNoticeMessage');
        var okBtn = document.getElementById('appNoticeOk');
        var cancelBtn = document.getElementById('appNoticeCancel');

        icon.className = 'app-notice-icon ' + kind;
        icon.textContent = kind === 'success' ? '✓' : kind === 'error' ? '!' : 'i';
        titleEl.className = 'app-notice-title ' + kind;
        titleEl.textContent = title;
        messageEl.textContent = item.message;
        okBtn.className = 'app-notice-ok' + (kind === 'error' ? ' error' : '');
        okBtn.textContent = item.confirmText || 'OK';
        cancelBtn.textContent = item.cancelText || 'Cancel';
        overlay.classList.toggle('is-confirm', isConfirm);
        overlay.classList.add('is-open');
        okBtn.focus();
    }

    function enqueue(message, options) {
        options = options || {};
        queue.push({
            message: String(message == null ? '' : message),
            title: options.title || '',
            kind: options.kind || '',
            mode: options.mode || 'alert',
            confirmText: options.confirmText || '',
            cancelText: options.cancelText || '',
            onOk: options.onOk || null,
            onCancel: options.onCancel || null
        });
        showNext();
    }

    global.alert = function (message) {
        try {
            if (!document.body) {
                nativeAlert(message);
                return;
            }
            enqueue(message);
        } catch (err) {
            nativeAlert(message);
        }
    };

    global.showAppNotice = function (title, message, kind, onOk) {
        enqueue(message, { title: title, kind: kind, onOk: onOk });
    };

    global.appAsk = function (message, onYes, options) {
        options = options || {};
        try {
            if (!document.body) {
                if (nativeConfirm(message) && typeof onYes === 'function') onYes();
                return;
            }
            enqueue(message, {
                title: options.title || 'Please Confirm',
                kind: options.kind || 'info',
                mode: 'confirm',
                confirmText: options.confirmText || 'OK',
                cancelText: options.cancelText || 'Cancel',
                onOk: onYes,
                onCancel: options.onCancel || null
            });
        } catch (err) {
            if (nativeConfirm(message) && typeof onYes === 'function') onYes();
        }
    };

    global.appAskNav = function (event, message, options) {
        if (event) event.preventDefault();
        var href = event && event.currentTarget ? event.currentTarget.getAttribute('href') : '';
        global.appAsk(message, function () {
            if (href) global.location.href = href;
        }, options || { title: 'Please Confirm', kind: 'error', confirmText: 'Remove' });
        return false;
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) finish(false);
    });
})(window);
