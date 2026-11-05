(() => {
    const loader = document.getElementById('pageLoader');
    if (!loader) return;

    let previousFocus;
    let hideTimer;
    let navigatingAway = false;
    const pendingKey = 'tomeco.page-loading';

    function rememberNavigation(pending) {
        try {
            if (pending) window.sessionStorage.setItem(pendingKey, '1');
            else window.sessionStorage.removeItem(pendingKey);
        } catch { /* Navigation still works when browser storage is unavailable. */ }
    }

    function hasPendingNavigation() {
        try { return window.sessionStorage.getItem(pendingKey) === '1'; }
        catch { return false; }
    }

    function show(instant = false) {
        if (!instant) navigatingAway = true;
        rememberNavigation(true);
        clearTimeout(hideTimer);
        loader.classList.remove('is-leaving');
        loader.classList.toggle('is-carried', instant);
        if (!loader.hidden) return;
        previousFocus = document.activeElement;
        loader.hidden = false;
        loader.showModal();
        loader.focus({ preventScroll: true });
    }

    function closeImmediately() {
        clearTimeout(hideTimer);
        const restoreFocus = document.activeElement === loader;
        if (loader.open) loader.close();
        loader.hidden = true;
        if (restoreFocus && previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
        previousFocus = null;
        loader.classList.remove('is-leaving', 'is-carried');
    }

    function hide() {
        navigatingAway = false;
        rememberNavigation(false);
        if (loader.hidden || loader.classList.contains('is-leaving')) return;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = reducedMotion ? 0 : parseFloat(getComputedStyle(loader).getPropertyValue('--page-loader-fade-duration')) || 0;
        if (!duration) { closeImmediately(); return; }
        loader.classList.add('is-leaving');
        hideTimer = setTimeout(closeImmediately, duration + 50);
    }

    function isPageUrl(href) {
        if (!href || href.startsWith('#')) return false;
        try {
            const url = new URL(href, location.href);
            return ['http:', 'https:'].includes(url.protocol) && url.origin === location.origin &&
                !(url.pathname === location.pathname && url.search === location.search && url.hash);
        } catch {
            return false;
        }
    }

    // Observe the completed event so existing validation and AJAX handlers win.
    window.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-page-loader')) return;
        if (link.target && link.target.toLowerCase() !== '_self') return;
        if (isPageUrl(link.getAttribute('href'))) show();
    });

    window.addEventListener('submit', event => {
        const form = event.target;
        const button = event.submitter;
        if (event.defaultPrevented || form.hasAttribute('data-no-page-loader')) return;
        const target = button?.getAttribute('formtarget') ?? form.target;
        const method = button?.getAttribute('formmethod') ?? form.method;
        const action = button?.getAttribute('formaction') ?? form.action;
        if ((target && target.toLowerCase() !== '_self') || method.toLowerCase() === 'dialog') return;
        if (isPageUrl(action)) show();
    });

    // Also covers scripted redirects, native form.submit(), and history traversal.
    if (window.navigation) {
        window.navigation.addEventListener('navigate', event => {
            if (event.defaultPrevented || event.destination.sameDocument || event.hashChange || event.downloadRequest !== null) return;
            if (event.sourceElement?.hasAttribute('data-no-page-loader')) return;
            if (!isPageUrl(event.destination.url)) return;
            show();
            event.signal.addEventListener('abort', hide, { once: true });
        });
        window.navigation.addEventListener('navigateerror', hide);
    }

    // Release the page once its DOM and deferred scripts are ready. Maps, images,
    // and other remote assets keep their own loading states without blocking it.
    document.addEventListener('DOMContentLoaded', () => {
        if (!navigatingAway) hide();
    }, { once: true });

    // A restored history entry must never retain a blocking loading screen.
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        if (hasPendingNavigation() && loader.hidden) show(true);
        hide();
    });
    window.addEventListener('pagehide', closeImmediately);
    window.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !loader.hidden) {
            event.preventDefault();
            hide();
        }
    });
    loader.addEventListener('cancel', event => {
        event.preventDefault();
        hide();
    });
    loader.addEventListener('animationend', event => {
        if (event.target === loader && event.animationName === 'page-loader-out' && loader.classList.contains('is-leaving')) closeImmediately();
    });

    // Carry the outgoing screen across the document swap, then fade it out when ready.
    if (hasPendingNavigation()) show(true);
    if (document.readyState === 'complete') hide();
})();
