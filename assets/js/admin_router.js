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
    var coreBodyChildren = Array.prototype.slice.call(document.body.children);

    var nativeSetInterval = window.setInterval;
    var nativeClearInterval = window.clearInterval;
    var nativeSetTimeout = window.setTimeout;
    var nativeClearTimeout = window.clearTimeout;

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

    // Remove body children that are not part of the persistent layout shell
    // (portals appended to <body>, initial page's after-layout style/link/script tags).
    function removePageBodyNodes() {
        var children = Array.prototype.slice.call(document.body.children);
        for (var i = 0; i < children.length; i++) {
            if (coreBodyChildren.indexOf(children[i]) === -1) {
                if (children[i].parentNode) children[i].parentNode.removeChild(children[i]);
            }
        }
    }

    function cleanupPageState() {
        clearTrackedTimers();
        document.body.classList.remove('modal-open');
        window.onclick = null;
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
        return fetch(src, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.text() : ''; })
            .then(function (code) {
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

    function appendPjaxParam(url) {
        var u = new URL(url, location.href);
        u.searchParams.set('__pjax', '1');
        return u.href;
    }

    function loadPage(url, push) {
        var token = ++router.navToken;
        fetch(appendPjaxParam(url), { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                if (token !== router.navToken) return; // stale response, ignore
                render(html, url, push);
            })
            .catch(function (err) {
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
        loadPage(url, true);
    });

    window.addEventListener('popstate', function (e) {
        if (!e.state || !e.state.pjax) return;
        loadPage(location.href, false);
    });
})();
