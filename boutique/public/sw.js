// Wanllan, application installée : tout passe par le réseau (les commandes doivent
// toujours être à jour). Sans réseau, une page simple dit quoi faire au lieu de
// l'écran d'erreur du navigateur.
const OFFLINE = '/offline.html';

const CACHE = 'wanllan-v1';

self.addEventListener('install', (event) => {
    // Page relue sur le réseau (pas de cache HTTP) : chaque version du service worker garde la dernière.
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE, { cache: 'reload' }))));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;
    event.respondWith(fetch(event.request).catch(async () => (await caches.match(OFFLINE)) || Response.error()));
});

// Alerte « nouvelle commande » envoyée par le serveur.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) {}
    event.waitUntil(self.registration.showNotification(data.title || 'Wanllan', {
        body: data.body || 'Nouvelle commande',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        tag: data.tag || 'wanllan',
        renotify: true,
        data: { url: data.url || '/admin' },
    }));
});

// Toucher l'alerte ouvre la commande (dans la fenêtre de Wanllan déjà ouverte si possible).
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/admin', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((w) => w.url.startsWith(self.location.origin + '/admin'));
        if (!open) return self.clients.openWindow(url);
        // navigate() refuse une fenêtre que ce service worker ne contrôle pas : on en ouvre une.
        return open.navigate(url).then((w) => (w || open).focus()).catch(() => self.clients.openWindow(url));
    }));
});
