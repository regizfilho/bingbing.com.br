const CACHE_NAME = 'controle-financeiro-v1';
const URLS_TO_CACHE = [
  '/',
  '/manifest.json',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(URLS_TO_CACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') {
    return;
  }

  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    return;
  }

  if (url.pathname.includes('@vite')) {
    return;
  }

  event.respondWith(
    fetch(request)
      .then(response => {
        if (!response || response.status !== 200) {
          return response;
        }

        const responseToCache = response.clone();

        caches.open(CACHE_NAME).then(cache => {
          cache.put(request, responseToCache);
        });

        return response;
      })
      .catch(() => {
        return caches.match(request)
          .then(cachedResponse => {
            return cachedResponse || new Response('Offline - O recurso não foi encontrado no cache', { status: 404 });
          });
      })
  );
});

self.addEventListener('push', event => {
  if (!event.data) {
    return;
  }

  const data = event.data.json();
  
  const options = {
    body: data.body || '',
    icon: data.icon || '/imgs/ico.png',
    badge: data.badge || '/imgs/ico.png',
    vibrate: [200, 100, 200],
    data: data.data || {},
    actions: data.actions || [],
    requireInteraction: false,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Notificação', options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();

  const urlToOpen = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(windowClients => {
        for (let client of windowClients) {
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});