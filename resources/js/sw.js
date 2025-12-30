// Custom Service Worker for React SPA in Laravel
const CACHE_NAME = 'react-spa-v1.0';
const STATIC_CACHE = 'react-static-v1.0';
const IMAGE_CACHE = 'react-images-v1.0';
const FONT_CACHE = 'react-fonts-v1.0';

// Assets to cache immediately (core app files)
const STATIC_ASSETS = [
  '/build/manifest.json',
  '/build/assets/',
];

// Routes that should never be cached
const EXCLUDED_ROUTES = [
  '/api/',
  '/sanctum/',
  '/login',
  '/logout',
  '/register',
  '/password/',
];

// Routes that can be cached with specific strategies
const CACHEABLE_GET_ROUTES = [
  '/api/knowledge/search', // Cache for 5 minutes
];

// Routes that use stale-while-revalidate strategy
const STALE_WHILE_REVALIDATE_ROUTES = [
  '/api/user', // User profile data
  '/api/knowledge', // Knowledge base listings
];

// Routes that are part of the SPA (should fallback to index.html when offline)
const SPA_ROUTES = [
  '/',
  '/dashboard',
  '/knowledge',
  '/settings',
  '/profile',
  '/onboarding',
];

// Install event - cache essential resources
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker');

  event.waitUntil(
    Promise.all([
      // Cache static assets
      caches.open(STATIC_CACHE).then((cache) => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS).catch((error) => {
          console.warn('[SW] Failed to cache some static assets:', error);
          // Continue even if some assets fail to cache
        });
      }),

      // Create offline fallback page
      caches.open(STATIC_CACHE).then((cache) => {
        const offlinePage = `
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
                margin: 0;
              }
              .container {
                max-width: 400px;
                margin: 0 auto;
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
              }
              h1 {
                color: #4F46E5;
                margin-bottom: 1rem;
                font-size: 1.5rem;
              }
              p {
                margin-bottom: 1.5rem;
                color: #64748b;
                line-height: 1.6;
              }
              button {
                background: #4F46E5;
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
                font-size: 1rem;
              }
              button:hover { background: #4338ca; }
              .status {
                color: #dc2626;
                font-weight: 500;
                margin-bottom: 1rem;
              }
            </style>
          </head>
          <body>
            <div class="container">
              <h1>🔌 You're Offline</h1>
              <p id="status">Checking connection...</p>
              <p>Please check your internet connection and try again.</p>
              <button onclick="window.location.reload()">Try Again</button>
            </div>
            <script>
              // Check online status
              function updateOnlineStatus() {
                const status = document.getElementById('status');
                if (navigator.onLine) {
                  status.textContent = 'You appear to be online. The app may be experiencing issues.';
                  status.style.color = '#d97706';
                } else {
                  status.textContent = 'No internet connection detected.';
                  status.style.color = '#dc2626';
                }
              }

              window.addEventListener('online', updateOnlineStatus);
              window.addEventListener('offline', updateOnlineStatus);
              updateOnlineStatus();
            </script>
          </body>
          </html>
        `;

        return cache.put('/offline.html', new Response(offlinePage, {
          headers: { 'Content-Type': 'text/html' }
        }));
      })
    ]).then(() => {
      console.log('[SW] Installation complete');
      return self.skipWaiting();
    })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker');

  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (![CACHE_NAME, STATIC_CACHE, IMAGE_CACHE, FONT_CACHE].includes(cacheName)) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),

      // Take control of all clients immediately
      self.clients.claim()
    ]).then(() => {
      console.log('[SW] Activation complete');
    })
  );
});

// Fetch event - intelligent caching strategy
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip external requests
  if (!url.origin.includes(self.location.origin)) {
    return;
  }

  // Skip excluded routes (API calls, auth routes)
  if (EXCLUDED_ROUTES.some(route => url.pathname.startsWith(route))) {
    return;
  }

  // Handle different types of requests
  if (isAssetRequest(request)) {
    event.respondWith(handleAssetRequest(request));
  } else if (isImageRequest(request)) {
    event.respondWith(handleImageRequest(request));
  } else if (isFontRequest(request)) {
    event.respondWith(handleFontRequest(request));
  } else if (isApiRequest(request)) {
    event.respondWith(handleApiRequest(request));
  } else if (isHtmlRequest(request)) {
    event.respondWith(handleHtmlRequest(request));
  }
});

// Check if request is for static assets
function isAssetRequest(request) {
  const url = new URL(request.url);
  return url.pathname.match(/\.(js|css|map)$/);
}

// Check if request is for images
function isImageRequest(request) {
  const url = new URL(request.url);
  return url.pathname.match(/\.(png|jpg|jpeg|gif|svg|webp|ico)$/);
}

// Check if request is for fonts
function isFontRequest(request) {
  const url = new URL(request.url);
  return url.pathname.match(/\.(woff|woff2|ttf|eot)$/);
}

// Check if request is for API endpoints
function isApiRequest(request) {
  const url = new URL(request.url);
  return url.pathname.startsWith('/api/');
}

// Check if request is for HTML pages
function isHtmlRequest(request) {
  return request.headers.get('accept')?.includes('text/html') ||
         request.destination === 'document';
}

// Handle static assets with cache-first strategy
async function handleAssetRequest(request) {
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
    console.warn('[SW] Failed to fetch asset:', request.url);
    return new Response('Asset not available', { status: 503 });
  }
}

// Handle images with cache-first strategy and expiration
async function handleImageRequest(request) {
  const cache = await caches.open(IMAGE_CACHE);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    // Check if image is still fresh (7 days)
    const cacheTime = cachedResponse.headers.get('sw-cache-time');
    const age = Date.now() - parseInt(cacheTime || '0');

    if (age < 7 * 24 * 60 * 60 * 1000) { // 7 days
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
    // Return cached version if available
    if (cachedResponse) {
      return cachedResponse;
    }

    // Return a placeholder for failed images
    return new Response('', {
      status: 404,
      headers: { 'Content-Type': 'image/svg+xml' }
    });
  }
}

// Handle fonts with cache-first strategy
async function handleFontRequest(request) {
  const cache = await caches.open(FONT_CACHE);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    return cachedResponse;
  }

  try {
    const networkResponse = await fetch(request);

    // Cache successful font responses
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.warn('[SW] Failed to fetch font:', request.url);
    return new Response('Font not available', { status: 503 });
  }
}

// Handle API requests with enhanced caching strategies
async function handleApiRequest(request) {
  const url = new URL(request.url);
  const cacheKey = request.url;

  // Never cache POST requests or requests with auth cookies
  if (request.method === 'POST' || request.headers.get('cookie')?.includes('session')) {
    return fetch(request);
  }

  // Check if this is a cacheable GET route
  const isCacheableGet = CACHEABLE_GET_ROUTES.some(route => url.pathname.startsWith(route));
  const isStaleWhileRevalidate = STALE_WHILE_REVALIDATE_ROUTES.some(route => url.pathname.startsWith(route));

  if (isCacheableGet) {
    // Cache for 5 minutes
    return handleCacheableApiRequest(request, 5 * 60 * 1000); // 5 minutes
  } else if (isStaleWhileRevalidate) {
    // Use stale-while-revalidate strategy
    return handleStaleWhileRevalidateApiRequest(request);
  } else {
    // Default: Network-first for other API calls
    return handleNetworkFirstApiRequest(request);
  }
}

// Handle cacheable API requests (like knowledge search)
async function handleCacheableApiRequest(request, maxAge) {
  const cache = await caches.open('api-cache');
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    // Check if cache is still fresh
    const cacheTime = cachedResponse.headers.get('sw-cache-time');
    const age = Date.now() - parseInt(cacheTime || '0');

    if (age < maxAge) {
      console.log('[SW] Serving API response from cache:', request.url);
      return cachedResponse;
    }
  }

  try {
    const networkResponse = await fetch(request);

    if (networkResponse.ok) {
      // Cache the response with timestamp
      const responseToCache = new Response(networkResponse.clone().body, {
        status: networkResponse.status,
        statusText: networkResponse.statusText,
        headers: {
          ...Object.fromEntries(networkResponse.headers.entries()),
          'sw-cache-time': Date.now().toString(),
          'sw-cache-strategy': 'time-based'
        }
      });

      cache.put(request, responseToCache);
    }

    return networkResponse;
  } catch (error) {
    // Return cached version if available
    if (cachedResponse) {
      console.log('[SW] Network failed, serving stale API response:', request.url);
      return cachedResponse;
    }

    return new Response(JSON.stringify({
      error: 'Network Error',
      message: 'Unable to fetch data. Please check your connection.'
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Handle stale-while-revalidate API requests
async function handleStaleWhileRevalidateApiRequest(request) {
  const cache = await caches.open('api-cache');
  const cachedResponse = await cache.match(request);

  // Always try to fetch fresh data in background
  const networkFetch = fetch(request).then(networkResponse => {
    if (networkResponse.ok) {
      // Update cache with fresh data
      const responseToCache = new Response(networkResponse.clone().body, {
        status: networkResponse.status,
        statusText: networkResponse.statusText,
        headers: {
          ...Object.fromEntries(networkResponse.headers.entries()),
          'sw-cache-time': Date.now().toString(),
          'sw-cache-strategy': 'stale-while-revalidate'
        }
      });

      cache.put(request, responseToCache);
    }
    return networkResponse;
  }).catch(error => {
    console.warn('[SW] Background refresh failed for:', request.url);
    return null;
  });

  // Return cached version immediately if available
  if (cachedResponse) {
    console.log('[SW] Serving stale API response, refreshing in background:', request.url);
    return cachedResponse;
  }

  // No cache available, wait for network
  try {
    const networkResponse = await networkFetch;
    return networkResponse || new Response(JSON.stringify({
      error: 'Network Error',
      message: 'Unable to fetch data. Please check your connection.'
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  } catch (error) {
    return new Response(JSON.stringify({
      error: 'Network Error',
      message: 'Unable to fetch data. Please check your connection.'
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Handle network-first API requests
async function handleNetworkFirstApiRequest(request) {
  try {
    const networkResponse = await fetch(request);

    // Cache successful GET responses for potential future use
    if (networkResponse.ok && request.method === 'GET') {
      const cache = await caches.open('api-cache');
      const responseToCache = new Response(networkResponse.clone().body, {
        status: networkResponse.status,
        statusText: networkResponse.statusText,
        headers: {
          ...Object.fromEntries(networkResponse.headers.entries()),
          'sw-cache-time': Date.now().toString(),
          'sw-cache-strategy': 'network-first'
        }
      });

      cache.put(request, responseToCache);
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Network failed for API request, trying cache:', request.url);

    // Network failed, try cache
    const cache = await caches.open('api-cache');
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      console.log('[SW] Serving cached API response:', request.url);
      return cachedResponse;
    }

    return new Response(JSON.stringify({
      error: 'Offline',
      message: 'This feature requires an internet connection'
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Handle HTML requests with network-first strategy
async function handleHtmlRequest(request) {
  const url = new URL(request.url);

  try {
    // Try network first for fresh content
    const networkResponse = await fetch(request);

    // Cache successful HTML responses for SPA routes
    if (networkResponse.ok && SPA_ROUTES.some(route => url.pathname.startsWith(route))) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.log('[SW] Network failed for HTML request, trying cache');

    // Network failed, try cache
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
      return cachedResponse;
    }

    // For SPA routes, serve the index.html as fallback
    if (SPA_ROUTES.some(route => url.pathname.startsWith(route))) {
      const indexResponse = await cache.match('/');
      if (indexResponse) {
        return indexResponse;
      }
    }

    // Show offline page for navigation requests
    if (request.mode === 'navigate') {
      const offlineResponse = await cache.match('/offline.html');
      return offlineResponse || new Response('Offline', { status: 503 });
    }

    return new Response('Network Error', { status: 503 });
  }
}

// Handle messages from the main thread
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'CLEAN_CACHE') {
    cleanOldCacheEntries();
  }
});

// Periodic cleanup of old cache entries
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

  console.log('[SW] Cache cleanup completed');
}

// Handle push notifications (for future use)
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();

    const options = {
      body: data.body || 'New notification',
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      vibrate: [100, 50, 100],
      data: data.data || {},
      tag: data.tag || 'general',
      requireInteraction: data.requireInteraction || false
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
