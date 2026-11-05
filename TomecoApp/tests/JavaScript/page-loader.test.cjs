const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/js/page-loader.js'), 'utf8');

class Events {
    listeners = new Map();
    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }
    dispatch(type, event = {}) {
        this.listeners.get(type)?.forEach(listener => listener(event));
    }
}

function setup({ reducedMotion = true, storage = new Map() } = {}) {
    const window = new Events();
    window.navigation = new Events();
    window.matchMedia = () => ({ matches: reducedMotion });
    window.sessionStorage = {
        setItem(key, value) { storage.set(key, value); },
        getItem(key) { return storage.get(key) ?? null; },
        removeItem(key) { storage.delete(key); },
    };
    const timers = new Map();
    let timerId = 0;
    const classes = new Set();
    const document = Object.assign(new Events(), { readyState: 'loading' });
    const originalFocus = { isConnected: true, focus() { document.activeElement = this; } };
    document.activeElement = originalFocus;
    const loader = Object.assign(new Events(), {
        hidden: true, open: false,
        classList: {
            add(...names) { names.forEach(name => classes.add(name)); },
            remove(...names) { names.forEach(name => classes.delete(name)); },
            contains(name) { return classes.has(name); },
            toggle(name, active) { if (active) classes.add(name); else classes.delete(name); },
        },
        showModal() { assert.equal(this.open, false); this.open = true; },
        close() { this.open = false; },
        focus() { document.activeElement = this; },
    });
    document.getElementById = () => loader;
    vm.runInNewContext(source, {
        window, document, URL, location: new URL('https://tomeco.test/dashboard'),
        getComputedStyle: () => ({ getPropertyValue: () => '180ms' }),
        setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
        clearTimeout(id) { timers.delete(id); },
    });
    return { window, document, loader, originalFocus, storage, timers };
}

function element(attributes = {}, properties = {}) {
    return {
        getAttribute(name) { return attributes[name] ?? null; },
        hasAttribute(name) { return Object.hasOwn(attributes, name); },
        closest() { return this; },
        ...properties,
    };
}

function click(window, href, options = {}, attributes = {}, properties = {}) {
    window.dispatch('click', {
        button: 0, defaultPrevented: false,
        target: element({ href, ...attributes }, { target: '', ...properties }), ...options,
    });
}

test('internal navigation shows a modal loader and history restoration releases it', () => {
    const { window, document, loader, originalFocus } = setup();
    click(window, '/dashboard/analytics');
    assert.equal(loader.hidden, false);
    assert.equal(loader.open, true);
    assert.equal(document.activeElement, loader);
    window.dispatch('pageshow', { persisted: true });
    assert.equal(loader.hidden, true);
    assert.equal(loader.open, false);
    assert.equal(document.activeElement, originalFocus);
});

test('menus, intercepted clicks, hashes, downloads and other browsing contexts stay usable', () => {
    const cases = [
        ['/dashboard/analytics', { defaultPrevented: true }],
        ['/dashboard/analytics', { ctrlKey: true }],
        ['/dashboard/analytics', { metaKey: true }],
        ['/dashboard/analytics', { shiftKey: true }],
        ['/dashboard/analytics', { altKey: true }],
        ['/dashboard/analytics', { button: 1 }],
        ['#filters'], ['/dashboard#filters'], ['mailto:office@example.com'],
        ['https://other.test/dashboard'],
        ['/report.csv', {}, { download: '' }],
        ['/dashboard/analytics', {}, { 'data-no-page-loader': '' }],
        ['/dashboard/analytics', {}, {}, { target: '_blank' }],
        ['http://[invalid'],
    ];
    for (const args of cases) {
        const { window, loader } = setup();
        click(window, ...args);
        assert.equal(loader.hidden, true, JSON.stringify(args));
    }
});

test('accepted form submissions show loading, while validation and AJAX cancellations do not', () => {
    for (const prevented of [true, false]) {
        const { window, loader } = setup();
        window.dispatch('submit', {
            defaultPrevented: prevented,
            target: element({}, { action: 'https://tomeco.test/profile', method: 'post', target: '' }),
        });
        assert.equal(loader.hidden, prevented);
    }
    for (const properties of [{ target: '_blank' }, { method: 'dialog' }, { action: 'https://other.test/submit' }]) {
        const { window, loader } = setup();
        window.dispatch('submit', {
            target: element({}, { action: 'https://tomeco.test/profile', method: 'post', target: '', ...properties }),
        });
        assert.equal(loader.hidden, true);
    }
});

test('scripted navigation shows loading and a cancelled navigation restores the current page', () => {
    const { window, loader } = setup();
    const signal = new Events();
    window.navigation.dispatch('navigate', {
        destination: { sameDocument: false, url: 'https://tomeco.test/dashboard/analytics' },
        downloadRequest: null, signal,
    });
    assert.equal(loader.open, true);
    signal.dispatch('abort');
    assert.equal(loader.open, false);
    assert.equal(loader.hidden, true);
});

test('same-document history and downloads do not start a page loader', () => {
    for (const properties of [{ sameDocument: true }, { downloadRequest: 'report.csv' }]) {
        const { window, loader } = setup();
        window.navigation.dispatch('navigate', {
            destination: { sameDocument: Boolean(properties.sameDocument), url: 'https://tomeco.test/dashboard?page=2' },
            downloadRequest: properties.downloadRequest ?? null, signal: new Events(),
        });
        assert.equal(loader.hidden, true);
    }
});

test('duplicate signals are safe and cancellation paths never leave a blocking screen', () => {
    for (const event of ['pagehide', 'navigateerror', 'Escape']) {
        const { window, loader } = setup();
        click(window, '/dashboard/analytics');
        click(window, '/dashboard/analytics');
        if (event === 'navigateerror') window.navigation.dispatch(event);
        else if (event === 'Escape') {
            let prevented = false;
            window.dispatch('keydown', { key: 'Escape', preventDefault() { prevented = true; } });
            assert.equal(prevented, true);
        }
        else window.dispatch(event);
        assert.equal(loader.hidden, true);
        assert.equal(loader.open, false);
    }
});

test('fade-out keeps the screen open until its own animation finishes, then restores focus', () => {
    const { window, document, loader, originalFocus } = setup({ reducedMotion: false });
    click(window, '/dashboard/analytics');
    window.navigation.dispatch('navigateerror');
    assert.equal(loader.open, true);
    assert.equal(loader.classList.contains('is-leaving'), true);
    loader.dispatch('animationend', { target: {}, animationName: 'page-loader-spin' });
    assert.equal(loader.open, true);
    loader.dispatch('animationend', { target: loader, animationName: 'page-loader-out' });
    assert.equal(loader.open, false);
    assert.equal(loader.hidden, true);
    assert.equal(document.activeElement, originalFocus);
});

test('the destination carries the loading screen until ready and then fades it out', () => {
    const sourcePage = setup({ reducedMotion: false });
    click(sourcePage.window, '/dashboard/analytics');
    sourcePage.window.dispatch('pagehide');
    assert.equal(sourcePage.loader.hidden, true);
    const destination = setup({ reducedMotion: false, storage: sourcePage.storage });
    assert.equal(destination.loader.open, true);
    assert.equal(destination.loader.classList.contains('is-carried'), true);
    destination.document.dispatch('DOMContentLoaded');
    assert.equal(destination.loader.classList.contains('is-leaving'), true);
    assert.equal(destination.storage.size, 0);
    destination.loader.dispatch('animationend', { target: destination.loader, animationName: 'page-loader-out' });
    assert.equal(destination.loader.hidden, true);
});

test('slow map or image requests do not keep the destination covered after DOM readiness', () => {
    const storage = new Map([['tomeco.page-loading', '1']]);
    const { document, loader, storage: pending } = setup({ reducedMotion: true, storage });
    assert.equal(loader.open, true);
    document.dispatch('DOMContentLoaded');
    assert.equal(loader.open, false);
    assert.equal(loader.hidden, true);
    assert.equal(pending.size, 0);
});

test('a late initial pageshow cannot dismiss a newer navigation while map assets finish', () => {
    const { document, window, loader, storage } = setup();
    document.dispatch('DOMContentLoaded');
    click(window, '/dashboard/analytics');
    window.dispatch('pageshow', { persisted: false });
    assert.equal(loader.open, true);
    assert.equal(storage.get('tomeco.page-loading'), '1');
});

test('late DOM readiness cannot dismiss navigation already leaving the current page', () => {
    const { document, window, loader, storage } = setup();
    click(window, '/dashboard/analytics');
    document.dispatch('DOMContentLoaded');
    assert.equal(loader.open, true);
    assert.equal(storage.get('tomeco.page-loading'), '1');
});

test('a new navigation cancels an unfinished fade and its cleanup timer', () => {
    const { window, loader, timers } = setup({ reducedMotion: false });
    click(window, '/dashboard/analytics');
    window.navigation.dispatch('navigateerror');
    assert.equal(timers.size, 1);
    click(window, '/profile');
    assert.equal(timers.size, 0);
    assert.equal(loader.classList.contains('is-leaving'), false);
    assert.equal(loader.open, true);
});

test('cleanup still finishes when the browser does not deliver animationend', () => {
    const { window, loader, timers } = setup({ reducedMotion: false });
    click(window, '/dashboard/analytics');
    window.navigation.dispatch('navigateerror');
    [...timers.values()].forEach(callback => callback());
    assert.equal(loader.hidden, true);
    assert.equal(loader.open, false);
});
