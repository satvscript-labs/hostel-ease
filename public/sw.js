// HSMS PWA service worker — network-first with offline fallback.
const CACHE = 'hsms-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/manifest.webmanifest']))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    event.respondWith(
        fetch(request)
            .then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));
                return response;
            })
            .catch(() =>
                caches.match(request).then((cached) =>
                    cached || (request.mode === 'navigate' ? caches.match(OFFLINE_URL) : undefined)
                )
            )
    );
});
