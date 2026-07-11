const CACHE_NAME = 'tomeco-pwa-v4';

const cacheAssets = [
    '/favicon.ico',
    '/manifest.json',
    '/css/mobile.css',
    '/pwa/icons/android/launchericon-192x192.png',
    '/pwa/icons/android/launchericon-512x512.png',
];

self.addEventListener('install', event => {
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            cache.addAll(cacheAssets);
        })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keyList => {
            return Promise.all(keyList.map(key => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
            }));
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);
    const acceptsHtml = event.request.headers.get('accept')?.includes('text/html');

    if (
        event.request.mode === 'navigate' ||
        acceptsHtml ||
        requestUrl.pathname === '/' ||
        requestUrl.pathname === '/mobile-login' ||
        requestUrl.pathname === '/mobile-home' ||
        requestUrl.pathname === '/login'
    ) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(cache => {
            return cache.match(event.request).then(response => {
                return response || fetch(event.request).then(fetchResponse => {
                    cache.put(event.request, fetchResponse.clone());
                    return fetchResponse;
                });
            });
        })
    );
});
