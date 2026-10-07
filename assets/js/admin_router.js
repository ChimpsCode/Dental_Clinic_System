(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;
    if (!document.getElementById('adminSidebar')) return;

    var router = {
        navToken: 0,
        executing: false,
        trackedIntervals: {},
        trackedTimeouts: {}
    };

    // Body nodes that belong to the persistent layout shell (sidebar, header,
    // main, content-area, modals). Everything else appended later is page-specific
    // (kebab portals, overlays, after-layout tags) and must be removed on nav.
    var coreBodyChildren = window.__shellBodyNodes || Array.prototype.slice.call(document.body.children);
    var scriptMemory = {};

    var nativeSetInterval = window.setInterval;
    var nativeClearInterval = window.clearInterval;
    var nativeSetTimeout = window.setTimeout;
    var nativeClearTimeout = window.clearTimeout;
    var nativeFetch = window.fetch.bind(window);

    // Track pollers so a previous page's timers can never write into a new page's DOM.
    window.setInterval = function (fn, delay) {
        var id = nativeSetInterval(fn, delay);
        router.trackedIntervals[id] = true;
        return id;
    };
    window.clearInterval = function (id) {
        delete router.trackedIntervals[id];
        return nativeClearInterval(id);
    };
    window.setTimeout = function (fn, delay) {
        var id = nativeSetTimeout(fn, delay);
        if (router.executing) router.trackedTimeouts[id] = true;
        return id;
    };
    window.clearTimeout = function (id) {
        delete router.trackedTimeouts[id];
        return nativeClearTimeout(id);
    };

    function clearTrackedTimers() {
        var ids, i;
        ids = Object.keys(router.trackedIntervals);
        for (i = 0; i < ids.length; i++) nativeClearInterval(ids[i]);
        router.trackedIntervals = {};

        ids = Object.keys(router.trackedTimeouts);
        for (i = 0; i < ids.length; i++) nativeClearTimeout(ids[i]);
        router.trackedTimeouts = {};
    }

    // Remove every asset appended by a previously-loaded pjax page.
    function removeTaggedAssets() {
        var nodes = document.querySelectorAll('[data-pjax-style], [data-pjax-link], [data-pjax-node]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].parentNode) nodes[i].parentNode.removeChild(nodes[i]);
        }
    }

    function shouldKeepBodyNode(el) {
        if (!el) return false;
        var pageModalIds = {
            adminPatientModal: true,
            odontogramModalOverlay: true,
            odXrayViewerOverlay: true,
            recordPaymentModal: true,
            paymentHistoryModal: true,
            setDueDateModal: true,
            'archive-patient-modal': true,
            patientKebabDropdownPortal: true,
            patientKebabBackdrop: true
        };
        if (el.id && pageModalIds[el.id]) return false;
        if (coreBodyChildren.indexOf(el) !== -1) return true;
        // Session-timeout overlay is appended after this snapshot and must survive nav.
        if (el.id === 'stoOverlay' || el.id === 'workspaceSplash') return true;
        if (el.getAttribute && el.getAttribute('data-persist-layout') === '1') return true;
        return false;
    }

    // Remove body children that are not part of the persistent layout shell
    // (portals appended to <body>, initial page's after-layout style/link/script tags).
    function removePageBodyNodes() {
        var children = Array.prototype.slice.call(document.body.children);
        for (var i = 0; i < children.length; i++) {
            if (!shouldKeepBodyNode(children[i])) {
                if (children[i].parentNode) children[i].parentNode.removeChild(children[i]);
            }
        }
    }

    function disposeAdminKebabState() {
        var handlerKeys = [
            '__adminQueueKebabClickHandler',
            '__adminPatientsKebabClickHandler',
            '__adminApptKebabClickHandler',
            '__adminPaymentKebabClickHandler',
            '__adminUsersKebabClickHandler',
            '__adminServicesKebabClickHandler',
            '__adminPatientLogKebabClickHandler'
        ];
        var i, fn, el, nodes, btns;
        for (i = 0; i < handlerKeys.length; i++) {
            fn = window[handlerKeys[i]];
            if (typeof fn === 'function') {
                document.removeEventListener('click', fn, true);
                document.removeEventListener('click', fn, false);
            }
            window[handlerKeys[i]] = null;
        }

        if (window.ArchiveKebab && typeof window.ArchiveKebab.close === 'function') {
            try { window.ArchiveKebab.close(); } catch (e) {}
        }

        nodes = document.querySelectorAll(
            '.queue-kebab-backdrop, .queue-kebab-dropdown-portal, ' +
            '.patient-kebab-backdrop, .patient-kebab-dropdown-portal, ' +
            '.appt-kebab-backdrop, .appt-kebab-dropdown-portal, ' +
            '.user-kebab-backdrop, .user-kebab-dropdown, ' +
            '.service-kebab-backdrop, .service-kebab-dropdown, ' +
            '.archive-kebab-backdrop, .archive-kebab-dropdown, ' +
            '.pay-kebab-backdrop, .pay-kebab-dropdown-portal'
        );
        for (i = 0; i < nodes.length; i++) {
            el = nodes[i];
            el.classList.remove('show');
            if (el.parentNode === document.body) {
                el.parentNode.removeChild(el);
            }
        }

        btns = document.querySelectorAll(
            '.queue-kebab-btn.active, .patient-kebab-btn.active, .appt-kebab-btn.active, ' +
            '.user-kebab-btn.active, .service-kebab-btn.active, .archive-kebab-btn.active, .pay-kebab-btn.active'
        );
        for (i = 0; i < btns.length; i++) {
            btns[i].classList.remove('active');
        }
    }

    function cleanupPageState() {
        clearTrackedTimers();
        document.body.classList.remove('modal-open');
        window.onclick = null;
        disposeAdminKebabState();
        removeTaggedAssets();
        removePageBodyNodes();
    }

    function installDCLShim() {
        var orig = document.addEventListener;
        document.addEventListener = function (type, fn, opts) {
            if (type === 'DOMContentLoaded' && typeof fn === 'function') {
                try { fn(); } catch (err) { console.error('pjax DOMContentLoaded error:', err); }
                return;
            }
            return orig.call(document, type, fn, opts);
        };
        return function restore() {
            document.addEventListener = orig;
        };
    }

    function runScriptCode(code, src) {
        try {
            (0, eval)(code);
        } catch (err) {
            console.error('pjax script error' + (src ? ' (' + src + ')' : '') + ':', err);
        }
    }

    function fetchExternalScript(src) {
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
                console.error('pjax failed to load script ' + src + ':', err);
            });
    }

    function executeScripts(doc, url) {
        var scripts = Array.prototype.slice.call(doc.querySelectorAll('script'));
        scripts = scripts.filter(function (s) {
            var t = (s.getAttribute('type') || '').toLowerCase();
            return t === '' || t === 'text/javascript' || t === 'application/javascript';
        });

        var chain = Promise.resolve();
        scripts.forEach(function (s) {
            var src = s.getAttribute('src');
            if (src) {
                chain = chain.then(function () {
                    return fetchExternalScript(new URL(src, url).href);
                });
            } else {
                chain = chain.then(function () { runScriptCode(s.textContent); });
            }
        });
        return chain;
    }

    function updateActiveNav(url) {
        var targetPath;
        try { targetPath = new URL(url, location.href).pathname; } catch (e) { return; }
        var targetBase = targetPath.split('/').pop();

        var items = document.querySelectorAll('#adminSidebar a.nav-item');
        var matched = false;
        for (var i = 0; i < items.length; i++) {
            var itemPath = '';
            try { itemPath = new URL(items[i].getAttribute('href') || '', location.href).pathname; } catch (e) {}
            var isActive = itemPath === targetPath;
            if (isActive) matched = true;
            items[i].classList.toggle('active', isActive);
        }
        if (!matched && targetBase) {
            for (var j = 0; j < items.length; j++) {
                var href = items[j].getAttribute('href') || '';
                items[j].classList.toggle('active', href.split('/').pop() === targetBase);
            }
        }
    }

    function render(html, url, push) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var box = doc.querySelector('.content-area');
        var current = document.querySelector('.content-area');
        if (!box || !current) {
            window.location.href = url;
            return;
        }

        var title = box.getAttribute('data-page-title') || 'Admin Dashboard';

        // Remove previous page's styles/links/body-nodes + stop its timers first.
        cleanupPageState();

        // Swap the content area (design-stable shell stays untouched).
        current.innerHTML = box.innerHTML;
        current.setAttribute('data-page-title', title);

        // Inject this page's own styles (tagged so they are removed on next nav).
        // Styles already inside the content-area travel with the innerHTML swap,
        // so only inject styles that live outside it (after-layout style blocks).
        var styles = doc.querySelectorAll('style');
        for (var i = 0; i < styles.length; i++) {
            if (box.contains(styles[i])) continue;
            var styleEl = document.createElement('style');
            styleEl.setAttribute('data-pjax-style', '1');
            styleEl.textContent = styles[i].textContent;
            document.head.appendChild(styleEl);
        }

        // Inject this page's own stylesheet links (tagged likewise).
        var links = doc.querySelectorAll('link[rel="stylesheet"]');
        for (var j = 0; j < links.length; j++) {
            if (box.contains(links[j])) continue;
            var linkEl = document.createElement('link');
            linkEl.setAttribute('data-pjax-link', '1');
            linkEl.setAttribute('rel', 'stylesheet');
            linkEl.setAttribute('href', new URL(links[j].getAttribute('href') || '', url).href);
            document.head.appendChild(linkEl);
        }

        // Titles + active nav.
        document.title = title + ' - RF Dental Clinic Admin';
        var h1 = document.querySelector('.header-title h1');
        if (h1) h1.textContent = title;
        updateActiveNav(url);

        // History entry (skip on popstate re-render).
        if (push) {
            try { history.pushState({ pjax: true }, '', url); } catch (e) {}
        }

        window.scrollTo(0, 0);

        // Execute scripts in document order; each external file is awaited before the
        // following inline script runs (keeps globals like MiniCalendar available).
        router.executing = true;
        var restoreDCL = installDCLShim();
        executeScripts(doc, url).then(function () {
            restoreDCL();
            router.executing = false;
            document.dispatchEvent(new CustomEvent('pjax:loaded'));
        });
    }

    function pageKey(url) {
        try {
            return new URL(url, location.href).pathname;
        } catch (e) {
            return String(url || '');
        }
    }

    function listKey(url) {
        try {
            var u = new URL(url, location.href);
            u.hash = '';
            u.searchParams.delete('__pjax');
            var query = u.searchParams.toString();
            return query ? (u.pathname + '?' + query) : u.pathname;
        } catch (e) {
            return pageKey(url);
        }
    }

    function pageName(url) {
        var path = pageKey(url);
        var parts = path.split('/');
        return parts[parts.length - 1] || path;
    }

    function resolvePage(page) {
        var name = String(page || '');
        if (name.indexOf('?') !== -1 || name.indexOf('/') !== -1 || name.indexOf('://') !== -1) return listKey(name);
        var links = document.querySelectorAll('#adminSidebar a.nav-item');
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            if (pageName(href) === name || href === name) return pageKey(href);
        }
        return pageKey(name);
    }

    function appendPjaxParam(url) {
        var u = new URL(url, location.href);
        u.searchParams.set('__pjax', '1');
        u.hash = '';
        return u.href;
    }

    function hideSplash() {
        var splash = document.getElementById('workspaceSplash');
        if (splash && splash.parentNode) splash.parentNode.removeChild(splash);
    }

    function fetchFragment(url) {
        return nativeFetch(appendPjaxParam(url), { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                window.appCache.set(listKey(url), html);
                return html;
            });
    }

    window.appCache = {
        data: {},
        get: function (page) {
            return this.data[resolvePage(page)] || null;
        },
        set: function (page, html) {
            this.data[resolvePage(page)] = html;
        },
        invalidate: function (pages) {
            var list = Array.isArray(pages) ? pages : [pages];
            list.forEach(function (page) {
                var key = resolvePage(page);
                if (!key) return;
                delete window.appCache.data[key];
                fetchFragment(key).catch(function (err) {
                    console.error('appCache invalidate failed:', key, err);
                });
            });
        },
        refreshCurrent: function (extraPages) {
            var url = (location.pathname + location.search).split('#')[0];
            var token = ++router.navToken;
            return fetchFragment(url).then(function (html) {
                if (token !== router.navToken) return;
                render(html, url, false);
                if (extraPages && extraPages.length) window.appCache.invalidate(extraPages);
            }).catch(function (err) {
                console.error('appCache refreshCurrent failed:', err);
                window.location.reload();
            });
        }
    };

    function loadPage(url, push) {
        var token = ++router.navToken;
        var cached = window.appCache.get(url);
        if (cached) {
            render(cached, url, push);
            return;
        }
        fetchFragment(url).then(function (html) {
            if (token !== router.navToken) return;
            render(html, url, push);
        }).catch(function (err) {
            if (token !== router.navToken) return;
            console.error('pjax failed, falling back to full load:', err);
            window.location.href = url;
        });
    }

    function closestItem(node) {
        while (node && node !== document) {
            if (node.nodeType === 1 && node.classList && node.classList.contains('nav-item') && node.closest('#adminSidebar')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    document.addEventListener('click', function (e) {
        var link = closestItem(e.target);
        if (!link) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;

        var url = new URL(href, location.href).href;
        if (url.indexOf(location.origin) !== 0) return; // external
        if (url.split('#')[0] === location.href.split('#')[0]) return; // same page

        e.preventDefault();
        loadPage(url.split('#')[0], true);
    });

    document.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        var node = e.target;
        var link = null;
        while (node && node !== document) {
            if (node.nodeType === 1 && node.tagName === 'A' && node.closest && node.closest('.content-area')) {
                link = node;
                break;
            }
            node = node.parentNode;
        }
        if (!link || link.classList.contains('disabled')) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (link.getAttribute('target') && link.getAttribute('target') !== '_self') return;
        if (link.hasAttribute('download')) return;
        var url;
        try { url = new URL(href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname !== location.pathname) return;
        if ((url.pathname + url.search) === (location.pathname + location.search)) return;
        e.preventDefault();
        loadPage(url.pathname + url.search, true);
    });

    window.addEventListener('popstate', function (e) {
        if (!e.state || !e.state.pjax) return;
        loadPage(location.href.split('#')[0], false);
    });

    function isMutating(input, init) {
        var method = 'GET';
        var url = '';
        if (typeof input === 'string') url = input;
        else if (input && input.url) url = input.url;
        if (init && init.method) method = init.method;
        else if (input && input.method) method = input.method;
        method = String(method || 'GET').toUpperCase();
        if (method === 'GET' || method === 'HEAD') return false;
        if (url.indexOf('notification_actions.php') !== -1) return false;
        if (url.indexOf('__pjax=1') !== -1) return false;
        return true;
    }

    window.fetch = function (input, init) {
        return nativeFetch(input, init).then(function (res) {
            if (res && res.ok && isMutating(input, init)) {
                window.appCache.invalidate(listKey(location.href));
            }
            return res;
        });
    };

    (function seedCurrentPage() {
        var current = document.querySelector('.content-area');
        if (!current) return;
        var h1 = document.querySelector('.header-title h1');
        if (h1 && !current.getAttribute('data-page-title')) {
            current.setAttribute('data-page-title', h1.textContent.trim());
        }
        window.appCache.set(listKey(location.href), current.outerHTML);
    })();

    (function preloadNav() {
        var links = document.querySelectorAll('#adminSidebar a.nav-item');
        var jobs = [];
        var seen = {};
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href');
            if (!href || href.charAt(0) === '#') continue;
            var url;
            try { url = new URL(href, location.href); } catch (e) { continue; }
            if (url.origin !== location.origin) continue;
            var key = url.pathname;
            if (seen[key] || key === location.pathname) continue;
            seen[key] = true;
            jobs.push(fetchFragment(url.pathname).catch(function (err) {
                console.error('workspace preload failed:', err);
            }));
        }
        Promise.allSettled(jobs).then(hideSplash);
    })();

    try { history.replaceState({ pjax: true }, '', location.href); } catch (e) {}
})();
