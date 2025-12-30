// Service Worker for PWA functionality with Workbox injection
const CACHE_NAME = 'ai-chat-v2.0';
const STATIC_CACHE = 'ai-chat-static-v2.0';
const API_CACHE = 'ai-chat-api-v2.0';
const IMAGE_CACHE = 'ai-chat-images-v2.0';

// Import workbox from CDN for injectManifest strategy
importScripts('https://storage.googleapis.com/workbox-cdn/releases/6.5.4/workbox-sw.js');

// Workbox injection point - DO NOT REMOVE OR MODIFY
workbox.precaching.precacheAndRoute(self.__WB_MANIFEST);

// Resources to cache immediately
const STATIC_ASSETS = [
  '/',
  '/signup',
  '/login',
  '/manifest.json',
  '/favicon.ico',
  '/offline.html'
];

// API endpoints to cache (safe, non-sensitive data)
const API_ENDPOINTS = [
  '/api/user', // User profile data
];

// Install event - cache essential resources
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');

  event.waitUntil(
    Promise.all([
      // Cache static assets
      caches.open(STATIC_CACHE).then((cache) => {
        console.log('Service Worker: Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      }),

      // Create offline fallback page
      caches.open(STATIC_CACHE).then((cache) => {
        const offlinePage = new Response(`
          <!DOCTYPE html>
          <html lang="en">
          <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Offline - AI Chat Support</title>
            <style>
              body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                text-align: center;
                padding: 2rem;
                background: #f8fafc;
                color: #334155;
              }
              .container {
                max-width: 400px;
                margin: 0 auto;
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
              }
              h1 { color: #4F46E5; margin-bottom: 1rem; }
              p { margin-bottom: 1.5rem; color: #64748b; }
              button {
                background: #4F46E5;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
              }
              button:hover { background: #4338ca; }
            </style>
          </head>
          <body>
            <div class="container">
              <h1>🤖 You're Offline</h1>
              <p>AI Chat Support is currently unavailable. Please check your internet connection and try again.</p>
              <button onclick="window.location.reload()">Try Again</button>
            </div>
          </body>
          </html>
        `, {
          headers: { 'Content-Type': 'text/html' }
        });

        return cache.put('/offline.html', offlinePage);
      })
    ]).then(() => {
      console.log('Service Worker: Installation complete');
      return self.skipWaiting();
    })
  );
});

// Activate event - clean up old caches and take control
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Activating...');

  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (![CACHE_NAME, STATIC_CACHE, API_CACHE, IMAGE_CACHE].includes(cacheName)) {
              console.log('Service Worker: Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),

      // Take control of all clients
      self.clients.claim()
    ]).then(() => {
      console.log('Service Worker: Activation complete');
    })
  );
});

// Fetch event - intelligent caching strategy
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Handle different types of requests
  if (request.method !== 'GET') {
    return; // Don't cache non-GET requests
  }

  // API requests - cache safely
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(handleApiRequest(request));
    return;
  }

  // Image requests - cache with expiration
  if (request.destination === 'image' || url.pathname.match(/\.(png|jpg|jpeg|gif|svg|webp)$/)) {
    event.respondWith(handleImageRequest(request));
    return;
  }

  // Static assets and pages - cache first, then network
  event.respondWith(handleStaticRequest(request));
});

// Handle API requests with safe caching
async function handleApiRequest(request) {
  const url = new URL(request.url);
  const cacheKey = request.url;

  try {
    // Try network first for fresh data
    const networkResponse = await fetch(request.clone());

    // Cache successful GET responses (excluding sensitive data)
    if (networkResponse.ok && request.method === 'GET') {
      const cache = await caches.open(API_CACHE);

      // Only cache safe endpoints
      if (API_ENDPOINTS.some(endpoint => url.pathname.startsWith(endpoint))) {
        // Add cache headers for client-side cache management
        const responseToCache = new Response(networkResponse.clone().body, {
          status: networkResponse.status,
          statusText: networkResponse.statusText,
          headers: {
            ...Object.fromEntries(networkResponse.headers.entries()),
            'sw-cache-time': Date.now().toString(),
            'sw-cache-strategy': 'network-first'
          }
        });

        cache.put(cacheKey, responseToCache);
      }
    }

    return networkResponse;
  } catch (error) {
    // Network failed, try cache
    console.log('Service Worker: Network failed for API request, trying cache');

    const cachedResponse = await caches.match(cacheKey);
    if (cachedResponse) {
      // Add header to indicate this came from cache
      const responseWithCacheHeader = new Response(cachedResponse.body, {
        status: cachedResponse.status,
        statusText: cachedResponse.statusText,
        headers: {
          ...Object.fromEntries(cachedResponse.headers.entries()),
          'sw-cache-hit': 'true'
        }
      });

      return responseWithCacheHeader;
    }

    // No cache available, return offline response
    return new Response(JSON.stringify({
      error: 'Offline',
      message: 'This feature requires an internet connection'
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Handle image requests with expiration
async function handleImageRequest(request) {
  const cache = await caches.open(IMAGE_CACHE);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    // Check if image is still fresh (24 hours)
    const cacheTime = cachedResponse.headers.get('sw-cache-time');
    const age = Date.now() - parseInt(cacheTime || '0');

    if (age < 24 * 60 * 60 * 1000) { // 24 hours
      return cachedResponse;
    }
  }

  try {
    const networkResponse = await fetch(request);

    if (networkResponse.ok) {
      // Cache the image with timestamp
      const responseToCache = new Response(networkResponse.clone().body, {
        status: networkResponse.status,
        statusText: networkResponse.statusText,
        headers: {
          ...Object.fromEntries(networkResponse.headers.entries()),
          'sw-cache-time': Date.now().toString()
        }
      });

      cache.put(request, responseToCache);
    }

    return networkResponse;
  } catch (error) {
    // Return cached version if available, otherwise placeholder
    if (cachedResponse) {
      return cachedResponse;
    }

    // Return a placeholder image
    return new Response('', {
      status: 404,
      headers: { 'Content-Type': 'image/svg+xml' }
    });
  }
}

// Handle static requests with cache-first strategy
async function handleStaticRequest(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    // Serve from cache immediately
    return cachedResponse;
  }

  try {
    const networkResponse = await fetch(request);

    // Cache successful responses
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    // Network failed, show offline page for navigation requests
    if (request.mode === 'navigate') {
      const offlineResponse = await cache.match('/offline.html');
      return offlineResponse || new Response('Offline', { status: 503 });
    }

    // For other requests, return network error
    return new Response('Network Error', { status: 503 });
  }
}

// Handle background sync for offline actions
self.addEventListener('sync', (event) => {
  console.log('Service Worker: Background sync triggered:', event.tag);

  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

// Background sync implementation
async function doBackgroundSync() {
  // Implement any offline action queuing here
  console.log('Service Worker: Performing background sync');
}

// Handle push notifications (future enhancement)
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();

    const options = {
      body: data.body,
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      vibrate: [100, 50, 100],
      data: data.data || {},
      actions: data.actions || []
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'AI Chat Support', options)
    );
  }
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const urlToOpen = event.action || '/dashboard';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      // Check if there's already a window/tab open
      for (let client of windowClients) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          return client.focus();
        }
      }

      // If no suitable window is found, open a new one
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// Periodic cleanup of old cache entries
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAN_CACHE') {
    cleanOldCacheEntries();
  }
});

async function cleanOldCacheEntries() {
  const cache = await caches.open(IMAGE_CACHE);
  const keys = await cache.keys();

  // Remove images older than 7 days
  const maxAge = 7 * 24 * 60 * 60 * 1000;

  await Promise.all(
    keys.map(async (request) => {
      const response = await cache.match(request);
      if (response) {
        const cacheTime = response.headers.get('sw-cache-time');
        const age = Date.now() - parseInt(cacheTime || '0');

        if (age > maxAge) {
          await cache.delete(request);
        }
      }
    })
  );

  console.log('Service Worker: Cache cleanup completed');
}
