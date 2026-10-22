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
