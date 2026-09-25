(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    var sidebar = document.getElementById('staffSidebar') || document.getElementById('dentistSidebar');
    if (!sidebar) return;

    var roleLabel = document.getElementById('staffSidebar') ? 'Staff' : 'Dentist';
    var scriptMemory = {};
    var router = {
        navToken: 0,
        executing: false,
        trackedIntervals: {},
        trackedTimeouts: {}
    };

    var coreBodyChildren = window.__shellBodyNodes || Array.prototype.slice.call(document.body.children);

    var nativeSetInterval = window.setInterval;
    var nativeClearInterval = window.clearInterval;
    var nativeSetTimeout = window.setTimeout;
    var nativeClearTimeout = window.clearTimeout;
    var nativeFetch = window.fetch.bind(window);

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
        var links = sidebar.querySelectorAll('a.nav-item');
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

    function clearTrackedTimers() {
        var ids, i;
        ids = Object.keys(router.trackedIntervals);
        for (i = 0; i < ids.length; i++) nativeClearInterval(ids[i]);
        router.trackedIntervals = {};
        ids = Object.keys(router.trackedTimeouts);
        for (i = 0; i < ids.length; i++) nativeClearTimeout(ids[i]);
        router.trackedTimeouts = {};
    }

    function removeTaggedAssets() {
        var nodes = document.querySelectorAll('[data-pjax-style], [data-pjax-link], [data-pjax-node]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].parentNode) nodes[i].parentNode.removeChild(nodes[i]);
        }
    }

    function shouldKeepBodyNode(el) {
        if (!el) return false;
        if (coreBodyChildren.indexOf(el) !== -1) return true;
        if (el.id === 'stoOverlay' || el.id === 'workspaceSplash') return true;
        if (el.getAttribute && el.getAttribute('data-persist-layout') === '1') return true;
        return false;
    }

    function removePageBodyNodes() {
        var children = Array.prototype.slice.call(document.body.children);
        for (var i = 0; i < children.length; i++) {
            if (!shouldKeepBodyNode(children[i]) && children[i].parentNode) {
                children[i].parentNode.removeChild(children[i]);
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
                try { fn(); } catch (err) { console.error('shell DOMContentLoaded error:', err); }
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
            console.error('shell script error' + (src ? ' (' + src + ')' : '') + ':', err);
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
                console.error('shell failed to load script ' + src + ':', err);
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
            } else if (s.textContent && s.textContent.indexOf('__shellBodyNodes') === -1) {
                chain = chain.then(function () { runScriptCode(s.textContent); });
            }
        });
        return chain;
    }

    function updateActiveNav(url) {
        var targetPath = pageKey(url);
        var targetBase = pageName(url);
        var items = sidebar.querySelectorAll('a.nav-item');
        var matched = false;
        for (var i = 0; i < items.length; i++) {
            var isActive = pageKey(items[i].getAttribute('href') || '') === targetPath;
            if (isActive) matched = true;
            items[i].classList.toggle('active', isActive);
        }
        if (!matched && targetBase) {
            for (var j = 0; j < items.length; j++) {
                items[j].classList.toggle('active', pageName(items[j].getAttribute('href') || '') === targetBase);
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

        var title = box.getAttribute('data-page-title') || roleLabel + ' Dashboard';
        cleanupPageState();
        current.innerHTML = box.innerHTML;
        current.setAttribute('data-page-title', title);

        var styles = doc.querySelectorAll('style');
        for (var i = 0; i < styles.length; i++) {
            if (box.contains(styles[i])) continue;
            var styleEl = document.createElement('style');
            styleEl.setAttribute('data-pjax-style', '1');
            styleEl.textContent = styles[i].textContent;
            document.head.appendChild(styleEl);
        }

        var links = doc.querySelectorAll('link[rel="stylesheet"]');
        for (var j = 0; j < links.length; j++) {
            if (box.contains(links[j])) continue;
            var linkEl = document.createElement('link');
            linkEl.setAttribute('data-pjax-link', '1');
            linkEl.setAttribute('rel', 'stylesheet');
            linkEl.setAttribute('href', new URL(links[j].getAttribute('href') || '', url).href);
            document.head.appendChild(linkEl);
        }

        document.title = title + ' - RF Dental Clinic ' + roleLabel;
        var h1 = document.querySelector('.header-title h1');
        if (h1) h1.textContent = title;
        updateActiveNav(url);

        if (push) {
            try { history.pushState({ shell: true }, '', url); } catch (e) {}
        }
        window.scrollTo(0, 0);

        router.executing = true;
        var restoreDCL = installDCLShim();
        executeScripts(doc, url).then(function () {
            restoreDCL();
            router.executing = false;
            document.dispatchEvent(new CustomEvent('pjax:loaded'));
        });
    }

    function storeFragment(url, html) {
        window.appCache.set(listKey(url), html);
    }

    function fetchFragment(url) {
        return nativeFetch(appendPjaxParam(url), { cache: 'no-store', credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                storeFragment(url, html);
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

    function seedCurrentPage() {
        var current = document.querySelector('.content-area');
        if (!current) return;
        var h1 = document.querySelector('.header-title h1');
        if (h1 && !current.getAttribute('data-page-title')) {
            current.setAttribute('data-page-title', h1.textContent.trim());
        }
        window.appCache.set(listKey(location.href), current.outerHTML);
    }

    function preloadNav() {
        var links = sidebar.querySelectorAll('a.nav-item');
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
        return Promise.allSettled(jobs).then(hideSplash);
    }

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
            console.error('shell navigation failed, falling back to full load:', err);
            window.location.href = url;
        });
    }

    function closestItem(node) {
        while (node && node !== document) {
            if (node.nodeType === 1 && node.classList && node.classList.contains('nav-item') && node.closest('#' + sidebar.id)) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

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

    document.addEventListener('click', function (e) {
        var link = closestItem(e.target);
        if (!link) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        var url = new URL(href, location.href).href;
        if (url.indexOf(location.origin) !== 0) return;
        if (url.split('#')[0] === location.href.split('#')[0]) return;
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
        if (!e.state || !e.state.shell) return;
        loadPage(location.href.split('#')[0], false);
    });

    try { history.replaceState({ shell: true }, '', location.href); } catch (e) {}

    seedCurrentPage();
    preloadNav();
})();
