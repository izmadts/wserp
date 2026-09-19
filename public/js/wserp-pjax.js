/*!
 * WSERP admin in-page navigation.
 *
 * Clicking between admin pages used to reload everything - sidebar, top bar,
 * fonts, scripts. This swaps only the page content (<main>) and the few
 * things that describe the page (title, header title, notification badges,
 * sidebar highlight), so the sidebar and top bar simply stay where they are.
 *
 * It is deliberately conservative - anything it is not sure about becomes a
 * normal full page load, exactly as before:
 *   - modified clicks (ctrl/cmd/shift, middle button), target=_blank, downloads,
 *     external links, exports/PDFs/backups, anything outside the admin URL scope;
 *   - responses that are not a complete admin page (errors, redirects to login,
 *     files, pages that opt out with @section('no-pjax'), or a different build
 *     of the layout than the one on screen);
 *   - any JavaScript error while the new page's own scripts run.
 * POST forms are never intercepted (a save is a normal request + redirect).
 * Turn it off entirely with ADMIN_PJAX=false in .env.
 *
 * Page scripts keep working unchanged: inline scripts are re-run after the new
 * content is in place, and handlers registered on DOMContentLoaded / load /
 * alpine:init - which the browser will not fire again - are called for them.
 */
(function () {
    'use strict';

    if (window.__wserpPjax) return;

    var metaContent = function (name, doc) {
        var el = (doc || document).querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : null;
    };

    var scope = metaContent('wserp-pjax-scope');
    if (!scope || !window.fetch || !window.DOMParser || !window.URL || !window.AbortController ||
        !window.history || !history.pushState) {
        return;
    }
    window.__wserpPjax = true;

    // Never navigate in-page to things that are files / print views / actions.
    var SKIP_PATH = /(export|download|pdf|print|backup|logout|\/storage\/)/i;

    var state = {
        token: 0,              // latest navigation wins
        controller: null,
        capturing: false,      // true only while a swapped-in page's scripts/handlers run
        pendingReady: [],      // DOMContentLoaded / load handlers to call for the new page
        pendingInit: [],       // alpine:init handlers
        tracked: [],           // document/window listeners the swapped-in page added
        loadedSrc: {},         // absolute URLs of external scripts already on the page
        baseSearch: null,      // DataTables global search hooks that belong to the layout
        currentKey: null       // path+query of what is on screen (to spot hash-only changes)
    };

    var absolute = function (src) {
        try { return new URL(src, location.href).href; } catch (e) { return src; }
    };
    Array.prototype.forEach.call(document.querySelectorAll('script[src]'), function (s) {
        state.loadedSrc[absolute(s.getAttribute('src'))] = true;
    });
    state.currentKey = location.pathname + location.search;

    // DataTables' global search hooks as the layout leaves them - pages add
    // theirs later, and teardown() puts the list back to exactly this.
    try {
        if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) state.baseSearch = jQuery.fn.dataTable.ext.search.slice();
    } catch (e) { /* ignore */ }

    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    // Pages rewrite the query string themselves (e.g. filters); keep our idea
    // of "what is on screen" honest so hash-only changes are recognised.
    ['pushState', 'replaceState'].forEach(function (method) {
        var original = history[method].bind(history);
        history[method] = function () {
            var result = original.apply(null, arguments);
            state.currentKey = location.pathname + location.search;
            return result;
        };
    });

    // ------------------------------------------------------------------
    // Listener bookkeeping. A swapped-in page's scripts run while
    // `state.capturing` is true; in that window DOMContentLoaded/load/
    // alpine:init listeners are held back (the events already happened) and
    // any other document/window listener is remembered so it can be removed
    // when the user leaves that page. Outside the window this is transparent.
    // ------------------------------------------------------------------
    var nativeAdd = {
        document: document.addEventListener.bind(document),
        window: window.addEventListener.bind(window)
    };
    var nativeRemove = {
        document: document.removeEventListener.bind(document),
        window: window.removeEventListener.bind(window)
    };

    function wrapAdd(name, target) {
        target.addEventListener = function (type, fn, opts) {
            if (state.capturing && fn) {
                if (type === 'DOMContentLoaded' || type === 'load' || type === 'readystatechange') {
                    state.pendingReady.push({ target: target, type: type, fn: fn });
                    return;
                }
                if (type === 'alpine:init') {
                    state.pendingInit.push({ target: target, type: type, fn: fn });
                    return;
                }
                state.tracked.push({ name: name, type: type, fn: fn, opts: opts });
            }
            return nativeAdd[name](type, fn, opts);
        };
    }
    wrapAdd('document', document);
    wrapAdd('window', window);

    // ------------------------------------------------------------------
    // Progress bar (inline styles only - no CSS build needed)
    // ------------------------------------------------------------------
    var bar = null, barTimer = null;

    function progressStart() {
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'wserp-pjax-bar';
            bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;z-index:2147483000;' +
                'background:var(--color-primary-600,#2563eb);pointer-events:none;' +
                'transition:width .25s ease,opacity .3s ease;opacity:1';
            document.body.appendChild(bar);
        }
        bar.style.opacity = '1';
        bar.style.width = '12%';
        clearInterval(barTimer);
        barTimer = setInterval(function () {
            var w = parseFloat(bar.style.width) || 0;
            if (w < 85) bar.style.width = (w + (90 - w) * 0.12) + '%';
        }, 200);
        document.documentElement.style.cursor = 'progress';
    }

    function progressDone() {
        clearInterval(barTimer);
        document.documentElement.style.cursor = '';
        if (!bar) return;
        bar.style.width = '100%';
        setTimeout(function () {
            if (!bar) return;
            bar.style.opacity = '0';
            setTimeout(function () { if (bar) bar.style.width = '0'; }, 320);
        }, 120);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    function hard(url) {
        progressDone();
        window.location.assign(url);
    }

    function inScope(url) {
        if (url.origin !== location.origin) return false;
        return url.pathname === scope || url.pathname.indexOf(scope + '/') === 0;
    }

    function keyOf(url) {
        return url.pathname + url.search;
    }

    var isExecutable = function (script) {
        var t = (script.getAttribute('type') || '').trim().toLowerCase();
        return t === '' || t === 'text/javascript' || t === 'application/javascript' || t === 'text/ecmascript';
    };

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var el = document.createElement('script');
            el.src = src;
            el.async = false;
            el.onload = function () { resolve(); };
            el.onerror = function () { reject(new Error('could not load ' + src)); };
            document.head.appendChild(el);
        });
    }

    var nextTick = function () {
        // Lets Alpine's MutationObserver initialise the freshly inserted content.
        return new Promise(function (resolve) { setTimeout(resolve, 0); });
    };

    // ------------------------------------------------------------------
    // Leaving the current page
    // ------------------------------------------------------------------
    function teardown() {
        // Listeners the previous swapped-in page put on document/window.
        state.tracked.splice(0).forEach(function (l) {
            try { nativeRemove[l.name](l.type, l.fn, l.opts); } catch (e) { /* ignore */ }
        });

        // Charts hold canvases and animation frames.
        try {
            if (window.Chart && Chart.instances) {
                Object.keys(Chart.instances).forEach(function (k) { Chart.instances[k].destroy(); });
            }
        } catch (e) { /* ignore */ }

        // DataTables keeps a global registry + search hooks; leave both as we found them.
        try {
            var jq = window.jQuery;
            if (jq && jq.fn && jq.fn.dataTable) {
                var ext = jq.fn.dataTable.ext;
                jq.fn.dataTable.tables({ api: true }).destroy();
                if (state.baseSearch) {
                    ext.search.length = 0;
                    Array.prototype.push.apply(ext.search, state.baseSearch);
                }
            }
        } catch (e) { /* ignore */ }
    }

    // Open dropdowns / palettes / the mobile sidebar must not survive a navigation.
    function closeChrome() {
        try {
            if (window.Alpine && Alpine.store) {
                var ui = Alpine.store('wserpUi');
                if (ui) { ui.searchOpen = false; ui.shortcutsOpen = false; }
            }
        } catch (e) { /* ignore */ }

        // The header dropdowns close on "click outside".
        try { document.body.dispatchEvent(new MouseEvent('click', { bubbles: true })); } catch (e) { /* ignore */ }

        document.body.style.overflow = '';

        if (window.innerWidth < 1024) {
            try {
                var root = document.querySelector('body > div[x-data]');
                var data = root && (Alpine.$data ? Alpine.$data(root) : (root._x_dataStack && root._x_dataStack[0]));
                if (data && data.closeSidebar) data.closeSidebar();
            } catch (e) { /* ignore */ }
        }
    }

    // ------------------------------------------------------------------
    // Updating the parts of the shell that describe the page
    // ------------------------------------------------------------------
    function syncChrome(doc) {
        document.title = doc.title;

        var csrf = metaContent('csrf-token', doc);
        var curCsrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf && curCsrf) curCsrf.setAttribute('content', csrf);

        // Header title.
        var newTitle = doc.querySelector('[data-pjax-region="page-title"]');
        var curTitle = document.querySelector('[data-pjax-region="page-title"]');
        if (newTitle && curTitle) curTitle.textContent = newTitle.textContent;

        // Notification badge + list (plain markup; the dropdown's own Alpine state is untouched).
        ['bell-button', 'bell-panel'].forEach(function (region) {
            var sel = '[data-pjax-region="' + region + '"]';
            var n = doc.querySelector(sel), c = document.querySelector(sel);
            if (n && c && c.innerHTML !== n.innerHTML) c.innerHTML = n.innerHTML;
        });

        // Sidebar: highlight the active link, refresh badges, open the active section.
        var newNav = doc.querySelector('nav[data-pjax-nav]');
        var curNav = document.querySelector('nav[data-pjax-nav]');
        if (!newNav || !curNav) return;

        var newLinks = newNav.querySelectorAll('a[href]');
        var curLinks = curNav.querySelectorAll('a[href]');
        if (newLinks.length === curLinks.length) {
            Array.prototype.forEach.call(curLinks, function (link, i) {
                var other = newLinks[i];
                if (link.getAttribute('href') !== other.getAttribute('href')) return;
                if (link.className !== other.className) link.className = other.className;
                if (link.innerHTML !== other.innerHTML) link.innerHTML = other.innerHTML;
            });
        }

        var newSections = newNav.querySelectorAll(':scope > div[x-data]');
        var curSections = curNav.querySelectorAll(':scope > div[x-data]');
        if (newSections.length === curSections.length) {
            Array.prototype.forEach.call(curSections, function (section, i) {
                var other = newSections[i];
                var btn = section.querySelector(':scope > button'), obtn = other.querySelector(':scope > button');
                if (btn && obtn && btn.className !== obtn.className) btn.className = obtn.className;
                if (/open:\s*true/.test(other.getAttribute('x-data') || '')) {
                    try {
                        var data = window.Alpine && Alpine.$data ? Alpine.$data(section) : (section._x_dataStack && section._x_dataStack[0]);
                        if (data) data.open = true;
                    } catch (e) { /* ignore */ }
                }
            });
        }
    }

    // ------------------------------------------------------------------
    // The swap
    // ------------------------------------------------------------------
    async function swap(doc, url, opts) {
        var curMain = document.querySelector('main');
        var newMain = doc.querySelector('main');
        var pageScripts = document.getElementById('wserp-page-scripts');
        if (!curMain || !newMain || !pageScripts) throw new Error('layout markers missing');

        // Scripts belonging to the page: inside <main>, then the layout's script stack.
        var scriptNodes = Array.prototype.slice.call(doc.querySelectorAll('main script, #wserp-page-scripts script'));
        var scripts = [];
        for (var i = 0; i < scriptNodes.length; i++) {
            var node = scriptNodes[i];
            var type = (node.getAttribute('type') || '').trim().toLowerCase();
            if (type === 'module') throw new Error('module script in page');
            if (!isExecutable(node)) continue;   // json / templates stay inert content
            scripts.push({ src: node.getAttribute('src'), text: node.textContent });
        }

        // 1. External libraries first, before anything on screen changes.
        for (var j = 0; j < scripts.length; j++) {
            if (!scripts[j].src) continue;
            var abs = absolute(scripts[j].src);
            if (state.loadedSrc[abs]) continue;
            await loadScript(abs);
            state.loadedSrc[abs] = true;
        }

        // A newer click may have superseded this one while libraries loaded.
        if (opts.token !== state.token) return false;

        // 2. Leave the old page.
        document.dispatchEvent(new CustomEvent('wserp:before-navigate'));
        teardown();
        closeChrome();

        // 3. History (before scripts run, so they see the new URL). fetch()'s
        //    final URL drops the #anchor, so put back the one that was clicked.
        var target = new URL(url, location.href);
        if (!target.hash && opts.hash) target.hash = opts.hash;
        if (opts.push) {
            try { history.replaceState(Object.assign({}, history.state, { y: window.scrollY }), ''); } catch (e) { /* ignore */ }
            history.pushState({ pjax: 1, y: 0 }, '', target.pathname + target.search + target.hash);
        } else if (target.href !== location.href) {
            history.replaceState(Object.assign({}, history.state, { pjax: 1 }), '', target.pathname + target.search + target.hash);
        }
        // 4. Shell + content. Executable scripts are stripped from the content
        //    (scripts parsed by DOMParser never run) and re-created below.
        syncChrome(doc);

        var fresh = document.importNode(newMain, true);
        Array.prototype.forEach.call(fresh.querySelectorAll('script'), function (s) {
            if (isExecutable(s)) s.parentNode.removeChild(s);
        });
        curMain.className = newMain.className;
        while (curMain.firstChild) curMain.removeChild(curMain.firstChild);
        while (fresh.firstChild) curMain.appendChild(fresh.firstChild);
        while (pageScripts.firstChild) pageScripts.removeChild(pageScripts.firstChild);

        window.scrollTo(0, opts.restoreY || 0);

        // 5. Run the page's scripts synchronously in one go - so component
        //    factories (Alpine.data / global functions used by x-data) exist
        //    before Alpine initialises the new markup a moment later.
        var errors = [];
        var onError = function (ev) {
            errors.push(ev && (ev.error || ev.message) || 'script error');
            if (ev && ev.preventDefault) ev.preventDefault();
        };
        var callHandler = function (h, type) {
            try {
                var evt = new Event(type || h.type);
                typeof h.fn === 'function' ? h.fn.call(h.target, evt) : h.fn.handleEvent(evt);
            } catch (e) { errors.push(e); }
        };
        nativeAdd.window('error', onError);

        try {
            state.pendingReady.length = 0;
            state.pendingInit.length = 0;
            state.capturing = true;
            try {
                scripts.forEach(function (s) {
                    if (s.src) return;   // libraries were loaded above
                    var el = document.createElement('script');
                    el.text = s.text;
                    pageScripts.appendChild(el);   // executes right here
                });
                state.pendingInit.splice(0).forEach(function (h) { callHandler(h, 'alpine:init'); });
            } finally {
                state.capturing = false;
            }

            // 6. Let Alpine set the new markup up, then do what DOMContentLoaded would have done.
            await nextTick();

            var ready = state.pendingReady.splice(0);
            state.capturing = true;
            try {
                ready.forEach(function (h) { callHandler(h); });
            } finally {
                state.capturing = false;
            }
        } finally {
            nativeRemove.window('error', onError);
        }

        if (target.hash) {
            var anchor = document.getElementById(decodeURIComponent(target.hash.slice(1)));
            if (anchor && anchor.scrollIntoView) anchor.scrollIntoView();
        }

        if (errors.length) {
            if (window.console && console.warn) console.warn('[wserp-pjax] page script error - reloading normally:', errors[0]);
            return { fallback: true };
        }

        document.dispatchEvent(new CustomEvent('wserp:navigated', { detail: { url: target.href } }));
        return { ok: true };
    }

    async function visit(href, opts) {
        opts = opts || {};
        var token = ++state.token;
        if (state.controller) state.controller.abort();
        var controller = state.controller = new AbortController();
        progressStart();

        try {
            var res = await fetch(href, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { 'Accept': 'text/html,application/xhtml+xml' },
                redirect: 'follow'
            });
            if (token !== state.token) return;

            var finalUrl = res.url || href;
            var type = res.headers.get('content-type') || '';
            var disposition = res.headers.get('content-disposition') || '';
            if (!res.ok || !/text\/html/i.test(type) || /attachment/i.test(disposition)) {
                controller.abort();
                return hard(finalUrl);
            }

            var html = await res.text();
            if (token !== state.token) return;

            var doc = new DOMParser().parseFromString(html, 'text/html');
            var sameShell = metaContent('wserp-layout', doc) === 'admin' &&
                metaContent('wserp-build', doc) === metaContent('wserp-build') &&
                !doc.querySelector('meta[name="wserp-no-pjax"]') &&
                doc.querySelector('main') &&
                inScope(new URL(finalUrl, location.href));
            if (!sameShell) return hard(finalUrl);

            var result = await swap(doc, finalUrl, {
                push: opts.push !== false && keyOf(new URL(finalUrl, location.href)) !== state.currentKey,
                restoreY: opts.restoreY,
                hash: new URL(href, location.href).hash,
                token: token
            });
            if (result === false) return;              // superseded
            if (result && result.fallback) return hard(finalUrl);
            progressDone();
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            if (window.console && console.warn) console.warn('[wserp-pjax] falling back to a normal page load:', err);
            hard(href);
        }
    }

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        if (!e.isTrusted || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (document.readyState !== 'complete') return;

        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-pjax') || a.closest('[data-no-pjax]')) return;

        var raw = a.getAttribute('href');
        if (!raw || raw.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(raw)) return;

        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return; }
        if (!inScope(url) || SKIP_PATH.test(url.pathname)) return;
        // Same page, only the #anchor differs: let the browser scroll.
        if (url.hash && url.pathname === location.pathname && url.search === location.search) return;

        e.preventDefault();
        visit(url.href, { push: true });
    });

    // GET forms (filters, search) navigate in-page too; POST forms stay normal.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || e.defaultPrevented) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
        if (form.target && form.target !== '_self') return;
        if (form.hasAttribute('data-no-pjax') || form.closest('[data-no-pjax]')) return;
        if (form.querySelector('input[type="file"]')) return;

        var url;
        try { url = new URL(form.getAttribute('action') || location.href, location.href); } catch (err) { return; }
        if (!inScope(url) || SKIP_PATH.test(url.pathname)) return;

        var params = new URLSearchParams();
        try {
            new FormData(form, e.submitter || undefined).forEach(function (value, key) {
                if (typeof value === 'string') params.append(key, value);
            });
        } catch (err) { return; }
        url.search = params.toString();

        e.preventDefault();
        visit(url.href, { push: true });
    });

    window.addEventListener('popstate', function () {
        var url = new URL(location.href);
        if (!inScope(url)) return;
        // Only the #anchor changed - the browser already handled it.
        if (keyOf(url) === state.currentKey && url.hash) return;
        var y = history.state && history.state.y;
        state.currentKey = keyOf(url);
        visit(url.href, { push: false, restoreY: y });
    });

    window.wserpPjax = { visit: function (href) { visit(new URL(href, location.href).href, { push: true }); } };
})();
