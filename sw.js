// ============================================================
// sw.js — Service Worker  |  Imperio Comercial PWA
// Estrategia: Cache-First para assets, Network-First para datos
// ============================================================

const CACHE_NAME    = 'imperio-v1.0.2';
const DATA_CACHE    = 'imperio-data-v1.0.2';
const OFFLINE_URL   = '/sgo/offline.php';

// Assets estáticos a cachear en la instalación
const STATIC_ASSETS = [
    '/sgo/offline.php',
    '/sgo/assets/css/app.css',
    '/sgo/assets/js/app.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];

// ── INSTALL: Pre-cache assets estáticos ──────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ── ACTIVATE: Limpiar caches viejas ──────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME && k !== DATA_CACHE)
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── FETCH: Estrategia híbrida ─────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo interceptar peticiones al mismo origen + CDNs conocidos
    const isSameOrigin = url.origin === self.location.origin;
    const isCDN = url.hostname.includes('jsdelivr.net');

    if (!isSameOrigin && !isCDN) return;

    // API / datos dinámicos → Network First
    if (url.pathname.includes('/api/') || url.pathname.includes('/reportes/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Assets CSS/JS/Fonts → Cache First
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Páginas PHP → Network First con fallback offline
    if (request.mode === 'navigate') {
        event.respondWith(navigateWithFallback(request));
        return;
    }
});

// ── Estrategia: Cache First ───────────────────────────────────
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Asset no disponible offline', { status: 503 });
    }
}

// ── Estrategia: Network First ─────────────────────────────────
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(DATA_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || new Response(
            JSON.stringify({ error: 'Sin conexión. Reintente más tarde.' }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
    }
}

// ── Navegación con fallback offline ──────────────────────────
async function navigateWithFallback(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        const offline = await caches.match(OFFLINE_URL);
        return offline || new Response('<h1>Sin conexión</h1>', {
            headers: { 'Content-Type': 'text/html' }
        });
    }
}

// ── Helper ────────────────────────────────────────────────────
function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|png|jpg|ico|svg)$/i.test(pathname);
}

// ── Background Sync: reintentar ventas offline ────────────────
self.addEventListener('sync', event => {
    if (event.tag === 'sync-ventas') {
        event.waitUntil(syncVentasPendientes());
    }
});

async function syncVentasPendientes() {
    // Obtener ventas pendientes del IndexedDB e intentar enviarlas
    // Implementación con idb-keyval o similar en app.js
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({ type: 'SYNC_COMPLETE', tag: 'sync-ventas' });
    });
}
