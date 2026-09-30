(function () {
    'use strict';

    if (window.__patientShell) return;
    if (!document.getElementById('patientSidebar')) return;
    window.__patientShell = true;

    var PAGES = {
        dashboard: 'patient-dashboard.php',
        inquiry: 'inquiry_consultation.php',
        appointments: 'appointments.php',
        records: 'medical_records.php',
        profile: 'profile.php',
        billing: 'billing.php',
        settings: 'settings.php',
        messages: 'messages.php'
    };
    var PAGE_FILES = Object.keys(PAGES).map(function (key) { return PAGES[key]; });
    var SKIP_SCRIPTS = ['patient_router.js', 'patient_sidebar.js', 'logout_confirm.js', 'app_notice_modal.js'];

    var scriptMemory = {};
    var views = {};
    var intervalsByPage = {};
    var activeKey = '';
    var router = { executing: false, pageKey: '' };

    var nativeSetInterval = window.setInterval;
    var nativeClearInterval = window.clearInterval;
    var nativeFetch = window.fetch.bind(window);

    window.setInterval = function (fn, delay) {
        var id = nativeSetInterval(fn, delay);
        var key = router.pageKey || activeKey;
        if (key) {
            if (!intervalsByPage[key]) intervalsByPage[key] = [];
            intervalsByPage[key].push(id);
        }
        return id;
    };
    window.clearInterval = function (id) {
        var keys = Object.keys(intervalsByPage);
        for (var i = 0; i < keys.length; i++) {
            var list = intervalsByPage[keys[i]];
            var idx = list.indexOf(id);
            if (idx !== -1) list.splice(idx, 1);
        }
        return nativeClearInterval(id);
    };

    function pageName(url) {
        try {
            var parts = new URL(url, location.href).pathname.split('/');
            return parts[parts.length - 1] || '';
        } catch (e) {
            return '';
        }
    }

    function isShellPage(name) {
        return PAGE_FILES.indexOf(name) !== -1;
    }

    function cacheKey(url) {
        return new URL(url, location.href).pathname;
    }

    function resolvePage(page) {
        var name = String(page || '');
        if (!name) return activeKey || location.pathname;
        if (PAGES[name]) return cacheKey(PAGES[name]);
        return cacheKey(name);
    }

    function showSplash() {
        if (document.getElementById('workspaceSplash')) return;
        var splash = document.createElement('div');
        splash.id = 'workspaceSplash';
        splash.setAttribute('style', 'position:fixed;inset:0;z-index:100000;background:none;background-color:transparent;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);display:flex;align-items:center;justify-content:center;font-family:Inter,system-ui,sans-serif;');
        splash.innerHTML = '<div style="text-align:center;color:#0f172a;">' +
            '<div style="width:36px;height:36px;margin:0 auto 14px;border:3px solid #dbe3ee;border-top-color:#005daa;border-radius:50%;animation:workspaceSpin .8s linear infinite;"></div>' +
            '<p style="margin:0;font-size:15px;font-weight:600;">Initializing workspace...</p></div>';
        document.body.appendChild(splash);
        if (!document.getElementById('workspaceSpinStyle')) {
            var style = document.createElement('style');
            style.id = 'workspaceSpinStyle';
            style.textContent = '@keyframes workspaceSpin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
    }

    function hideSplash() {
        var splash = document.getElementById('workspaceSplash');
        if (splash && splash.parentNode) splash.parentNode.removeChild(splash);
    }

    function clearIntervalsFor(key) {
        var list = intervalsByPage[key] || [];
        for (var i = 0; i < list.length; i++) nativeClearInterval(list[i]);
        delete intervalsByPage[key];
    }

    function isShellNode(el) {
        if (!el || el.nodeType !== 1) return true;
        if (el.id === 'patientSidebar' || el.id === 'sidebarOverlay' || el.id === 'workspaceSplash' || el.id === 'app-notice-modal') return true;
        if (el.tagName === 'SCRIPT') return true;
        return false;
    }

    function pageCssFrom(styles) {
        var css = '';
        for (var i = 0; i < styles.length; i++) {
            if (styles[i].id === 'workspaceSpinStyle') continue;
            css += styles[i].textContent || '';
        }
        return css;
    }

    function stylesheetHrefs(root, base) {
        var links = root.querySelectorAll('link[rel="stylesheet"]');
        var hrefs = [];
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            if (!href) continue;
            try { hrefs.push(new URL(href, base).href); } catch (e) {}
        }
        return hrefs;
    }

    function installPageStyle(css) {
        var holder = document.getElementById('patientPageStyle');
        if (!holder) {
            holder = document.createElement('style');
            holder.id = 'patientPageStyle';
            holder.setAttribute('data-patient-inline', '1');
            var first = document.querySelector('head style[data-patient-inline]');
            if (first && first.parentNode) first.parentNode.insertBefore(holder, first);
            else document.head.appendChild(holder);
        }
        holder.textContent = css;
        var old = document.querySelectorAll('head style[data-patient-inline]');
        for (var i = 0; i < old.length; i++) {
            if (old[i] === holder) continue;
            if (old[i].parentNode) old[i].parentNode.removeChild(old[i]);
        }
    }

    function syncStylesheets(hrefs) {
        var wanted = {};
        for (var i = 0; i < hrefs.length; i++) wanted[hrefs[i]] = true;
        var existing = document.querySelectorAll('link[rel="stylesheet"]');
        for (var j = 0; j < existing.length; j++) {
            var abs = existing[j].href;
            if (!wanted[abs] && existing[j].parentNode) existing[j].parentNode.removeChild(existing[j]);
        }
        var have = {};
        existing = document.querySelectorAll('link[rel="stylesheet"]');
        for (var k = 0; k < existing.length; k++) have[existing[k].href] = true;
        for (var n = 0; n < hrefs.length; n++) {
            if (have[hrefs[n]]) continue;
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = hrefs[n];
            document.head.appendChild(link);
        }
    }

    function markLiveStyles() {
        var styles = document.querySelectorAll('head style');
        for (var i = 0; i < styles.length; i++) {
            if (styles[i].id === 'workspaceSpinStyle') continue;
            styles[i].setAttribute('data-patient-inline', '1');
            if (!document.getElementById('patientPageStyle')) styles[i].id = 'patientPageStyle';
        }
    }

    function skipScriptSrc(src) {
        for (var i = 0; i < SKIP_SCRIPTS.length; i++) {
            if (src.indexOf(SKIP_SCRIPTS[i]) !== -1) return true;
        }
        return false;
    }

    function runScriptCode(code, src) {
        try { (0, eval)(code); } catch (err) {
            console.error('patient shell script error' + (src ? ' (' + src + ')' : '') + ':', err);
        }
    }

    function fetchExternalScript(src) {
        if (skipScriptSrc(src)) return Promise.resolve();
        if (Object.prototype.hasOwnProperty.call(scriptMemory, src)) {
            if (scriptMemory[src]) runScriptCode(scriptMemory[src], src);
            return Promise.resolve();
        }
        return nativeFetch(src, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.text() : ''; })
            .then(function (code) {
                scriptMemory[src] = code;
                if (code) runScriptCode(code, src);
            })
            .catch(function (err) {
                console.error('patient shell failed to load script ' + src + ':', err);
            });
    }

    function executeScripts(doc, url, pageKey) {
        var orig = document.addEventListener;
        document.addEventListener = function (type, fn, opts) {
            if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                try { fn(); } catch (err) { console.error(err); }
                return;
            }
            return orig.call(document, type, fn, opts);
        };
        router.executing = true;
        router.pageKey = pageKey;
        var scripts = Array.prototype.slice.call(doc.querySelectorAll('script'));
        var chain = Promise.resolve();
        scripts.forEach(function (s) {
            var type = (s.getAttribute('type') || '').toLowerCase();
            if (type && type !== 'text/javascript' && type !== 'application/javascript') return;
            var src = s.getAttribute('src');
            if (src) {
                var abs = new URL(src, url).href;
                if (skipScriptSrc(abs)) return;
                chain = chain.then(function () { return fetchExternalScript(abs); });
            } else if (s.textContent) {
                chain = chain.then(function () { runScriptCode(s.textContent); });
            }
        });
        return chain.then(function () {
            document.addEventListener = orig;
            router.executing = false;
            router.pageKey = '';
        }, function (err) {
            document.addEventListener = orig;
            router.executing = false;
            router.pageKey = '';
            throw err;
        });
    }

    function linkTarget(link) {
        if (!link) return null;
        var page = link.getAttribute('data-page');
        if (page && PAGES[page]) return new URL(PAGES[page], location.href);
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return null;
        try { return new URL(href, location.href); } catch (e) { return null; }
    }

    function markActive(url) {
        var path = cacheKey(url);
        var items = document.querySelectorAll('#patientSidebar .menu-item');
        for (var i = 0; i < items.length; i++) {
            var target = linkTarget(items[i]);
            items[i].classList.toggle('active', !!(target && target.pathname === path));
        }
    }

    function collectPageNodes(doc) {
        var nodes = [];
        var children = doc.body ? doc.body.children : [];
        for (var i = 0; i < children.length; i++) {
            var el = children[i];
            if (el.id === 'patientSidebar' || el.id === 'sidebarOverlay' || el.id === 'workspaceSplash' || el.id === 'app-notice-modal') continue;
            if (el.tagName === 'SCRIPT') continue;
            nodes.push(document.importNode(el, true));
        }
        return nodes;
    }

    function parsePage(html, url) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var css = pageCssFrom(doc.querySelectorAll('head style'));
        if (!doc.querySelector('.main-wrapper') || !css.trim()) return null;
        return {
            doc: doc,
            css: css,
            links: stylesheetHrefs(doc, url),
            nodes: collectPageNodes(doc)
        };
    }

    function parkActive() {
        if (!activeKey || !views[activeKey]) return;
        var view = views[activeKey];
        var children = Array.prototype.slice.call(document.body.children);
        var parked = [];
        for (var i = 0; i < children.length; i++) {
            if (isShellNode(children[i])) continue;
            parked.push(children[i]);
            children[i].parentNode.removeChild(children[i]);
        }
        view.parked = parked;
        view.attached = false;
    }

    function attachNodes(nodes) {
        for (var i = 0; i < nodes.length; i++) document.body.appendChild(nodes[i]);
    }

    function dropView(key) {
        clearIntervalsFor(key);
        delete views[key];
        delete window.appCache.data[key];
    }

    function showPrepared(key, prepared, url, push) {
        if (activeKey) parkActive();
        var view = {
            html: window.appCache.data[key] || '',
            css: prepared.css,
            links: prepared.links,
            doc: prepared.doc,
            prepared: prepared,
            parked: prepared.nodes,
            scriptsRan: false,
            attached: true
        };
        views[key] = view;
        installPageStyle(view.css);
        syncStylesheets(view.links);
        attachNodes(view.parked);
        activeKey = key;
        document.title = (prepared.doc && prepared.doc.title) || document.title;
        if (push) {
            try { history.pushState({ patientShell: true }, '', url); } catch (e) {}
        }
        markActive(url);
        window.scrollTo(0, 0);
        executeScripts(prepared.doc, url, key).then(function () {
            view.scriptsRan = true;
        });
    }

    function showCached(key, url, push) {
        var view = views[key];
        if (view && view.scriptsRan && view.parked && view.parked.length && !view.attached) {
            if (activeKey) parkActive();
            installPageStyle(view.css || '');
            syncStylesheets(view.links || []);
            attachNodes(view.parked);
            view.attached = true;
            activeKey = key;
            if (push) {
                try { history.pushState({ patientShell: true }, '', url); } catch (e) {}
            }
            markActive(url);
            window.scrollTo(0, 0);
            return;
        }
        if (view && view.prepared && !view.scriptsRan) {
            showPrepared(key, view.prepared, url, push);
            return;
        }
        var html = window.appCache.data[key];
        if (!html) {
            window.location.href = url;
            return;
        }
        var prepared = parsePage(html, url);
        if (!prepared) {
            window.location.href = url;
            return;
        }
        showPrepared(key, prepared, url, push);
    }

    function remember(url, html) {
        var key = cacheKey(url);
        var prepared = parsePage(html, url);
        if (!prepared) return false;
        window.appCache.data[key] = html;
        var prev = views[key];
        if (prev && prev.attached && activeKey === key) {
            prev.html = html;
            prev.pending = prepared;
            return true;
        }
        if (prev && prev.scriptsRan) clearIntervalsFor(key);
        views[key] = {
            html: html,
            css: prepared.css,
            links: prepared.links,
            doc: prepared.doc,
            parked: null,
            prepared: prepared,
            scriptsRan: false,
            attached: false
        };
        return true;
    }

    function fetchPage(url) {
        var key = cacheKey(url);
        return nativeFetch(key, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                if (!remember(url, html)) throw new Error('unusable page');
                return html;
            });
    }

    function refreshPage(page) {
        var key = resolvePage(page);
        return fetchPage(key).then(function () {
            var view = views[key];
            var prepared = view && (view.pending || view.prepared);
            if (!prepared) return;
            if (activeKey === key) {
                clearIntervalsFor(key);
                showPrepared(key, prepared, key, false);
            }
        });
    }

    function dropOthers(keep) {
        var keys = Object.keys(window.appCache.data);
        for (var i = 0; i < keys.length; i++) {
            if (keys[i] === keep) continue;
            if (views[keys[i]] && views[keys[i]].attached) continue;
            dropView(keys[i]);
        }
    }

    window.appCache = {
        data: {},
        get: function (page) {
            return this.data[resolvePage(page)] || null;
        },
        set: function (page, html) {
            var key = resolvePage(page);
            this.data[key] = html;
            remember(key, html);
        },
        refresh: function (page) {
            return refreshPage(page || activeKey);
        },
        invalidate: function (pages) {
            var list = Array.isArray(pages) ? pages : [pages];
            var jobs = [];
            for (var i = 0; i < list.length; i++) {
                var key = resolvePage(list[i]);
                if (key === activeKey) jobs.push(refreshPage(key));
                else dropView(key);
            }
            return Promise.all(jobs);
        }
    };

    function isProfileLink(link) {
        if (!link) return false;
        if (link.getAttribute('data-page') === 'profile') return true;
        return pageName(link.getAttribute('href') || '') === 'profile.php';
    }

    function isPatientMutation(input, init) {
        var method = 'GET';
        var url = '';
        if (typeof input === 'string') url = input;
        else if (input && input.url) url = input.url;
        if (init && init.method) method = init.method;
        else if (input && input.method) method = input.method;
        method = String(method || 'GET').toUpperCase();
        if (method === 'GET' || method === 'HEAD') return false;
        if (url.indexOf('notification_actions.php') !== -1) return false;
        if (url.indexOf('book_appointment.php') !== -1) return true;
        if (url.indexOf('inquiry_actions.php') !== -1) return true;
        if (url.indexOf('process_patient_appointment.php') !== -1) return true;
        if (url.indexOf('phone_change_actions.php') !== -1) return true;
        if (url.indexOf('profile.php') !== -1) return true;
        if (url.indexOf('settings.php') !== -1) return true;
        if (url.indexOf('messages.php') !== -1) return true;
        return false;
    }

    window.fetch = function (input, init) {
        return nativeFetch(input, init).then(function (res) {
            if (res && res.ok && isPatientMutation(input, init)) {
                dropOthers(activeKey);
                refreshPage(activeKey).catch(function (err) {
                    console.error('patient cache refresh failed:', err);
                });
            }
            // The account behind this session was deleted/archived by an admin,
            // or the session timed out. Stop here and send the patient to the
            // login screen, which explains why.
            if (res && typeof res.json === 'function') {
                var pingUrl = typeof input === 'string' ? input : (input && input.url) || '';
                // session_ping is polled by session_timeout.js, which shows the
                // blocking modal itself; navigating here would skip it.
                var isPing = String(pingUrl).indexOf('session_ping.php') !== -1;
                if (!isPing) {
                    res.clone().json().then(function (data) {
                        if (data && (data.account_removed || data.session_expired) && data.redirect) {
                            window.location.href = data.redirect;
                        }
                    }).catch(function () {});
                }
            }
            return res;
        });
    };

    document.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        var node = e.target;
        var link = null;
        while (node && node !== document) {
            if (node.nodeType === 1 && node.tagName === 'A') {
                link = node;
                break;
            }
            node = node.parentNode;
        }
        if (!link) return;
        var inSidebar = link.classList.contains('menu-item') && link.closest && link.closest('#patientSidebar');
        if (!inSidebar && !isProfileLink(link)) return;
        var url = linkTarget(link);
        if (!url || url.origin !== location.origin) return;
        if (!isShellPage(pageName(url.href))) return;
        if (url.pathname === location.pathname) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        if (!window.appCache.data[url.pathname] && !(views[url.pathname] && views[url.pathname].prepared)) {
            window.location.href = url.pathname + url.search;
            return;
        }
        showCached(url.pathname, url.pathname + url.search, true);
    }, true);

    window.addEventListener('popstate', function (e) {
        if (!e.state || !e.state.patientShell) return;
        if (!isShellPage(pageName(location.href))) return;
        if (location.pathname === activeKey) return;
        showCached(location.pathname, location.pathname + location.search, false);
    });

    try { history.replaceState({ patientShell: true }, '', location.href); } catch (e) {}

    markLiveStyles();
    activeKey = location.pathname;
    views[activeKey] = {
        html: '',
        css: pageCssFrom(document.querySelectorAll('head style[data-patient-inline]')),
        links: stylesheetHrefs(document, location.href),
        parked: null,
        prepared: null,
        scriptsRan: true,
        attached: true
    };
    window.appCache.data[activeKey] = document.documentElement.outerHTML;

    showSplash();

    var jobs = [];
    var seen = {};
    PAGE_FILES.forEach(function (file) {
        var path = new URL(file, location.href).pathname;
        if (seen[path] || path === location.pathname) return;
        seen[path] = true;
        jobs.push(fetchPage(path).catch(function (err) {
            console.error('patient preload failed:', file, err);
        }));
    });
    Promise.all(jobs).then(hideSplash, hideSplash);
})();
