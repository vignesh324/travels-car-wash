const CACHE_NAME = 'tcw-pwa-v1';
const APP_BASE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const STATIC_ASSETS = [
    'offline.html',
    'manifest.json',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'favicon.ico',
].map((path) => new URL(path, self.registration.scope).toString());

// Install: Cache core static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: Clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch handling
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    const apiPath = `${APP_BASE_PATH}/api`;
    const buildPath = `${APP_BASE_PATH}/build/`;
    const iconsPath = `${APP_BASE_PATH}/icons/`;

    // 1. Never cache API endpoints or financial requests - Security & Data Integrity Rule
    if ((url.pathname === apiPath || url.pathname.startsWith(`${apiPath}/`)) || request.method !== 'GET') {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        message: 'You are currently offline. Please reconnect to access or submit transactions.',
                        offline: true,
                    }),
                    {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' },
                    }
                );
            })
        );
        return;
    }

    // 2. Navigation requests: Network-first, fallback to offline.html
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                return (await cache.match(new URL('offline.html', self.registration.scope).toString())) || Response.error();
            })
        );
        return;
    }

    // 3. Static assets (Vite build JS/CSS, images, icons): Cache-first with network fallback
    if (url.pathname.startsWith(buildPath) || url.pathname.startsWith(iconsPath) || STATIC_ASSETS.includes(url.href)) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Default: Network fetch
    event.respondWith(fetch(request));
});

// Listen for skip waiting message from app update toast
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
