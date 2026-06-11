const CACHE_NAME = "kopisop-cache-v3";
const OFFLINE_URL = "/offline.html";
const PRECACHE_URLS = [
    OFFLINE_URL,
    "/manifest.json",
    "/icon-192.png",
    "/logo.png"
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

function isSameOrigin(url) {
    return url.origin === self.location.origin;
}

function isKasirPath(pathname) {
    return pathname === "/kasir"
        || pathname.startsWith("/kasir/");
}

function isCacheableResponse(response) {
    if (!response || response.status !== 200 || response.type !== "basic") {
        return false;
    }

    const cacheControl = (response.headers.get("Cache-Control") || "").toLowerCase();
    return !cacheControl.includes("no-store") && !cacheControl.includes("private");
}

self.addEventListener("fetch", (event) => {
    const { request } = event;

    if (request.method !== "GET") {
        return;
    }

    const requestUrl = new URL(request.url);
    if (!isSameOrigin(requestUrl)) {
        return;
    }

    if (request.mode === "navigate") {
        if (!isKasirPath(requestUrl.pathname)) {
            event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
            return;
        }

        event.respondWith((async () => {
            try {
                const networkResponse = await fetch(request);
                if (isCacheableResponse(networkResponse)) {
                    const cache = await caches.open(CACHE_NAME);
                    await cache.put(request, networkResponse.clone());
                }
                return networkResponse;
            } catch {
                const cachedPage = await caches.match(request);
                if (cachedPage) {
                    return cachedPage;
                }
                return caches.match(OFFLINE_URL);
            }
        })());
        return;
    }

    const isStaticAsset = ["style", "script", "image", "font"].includes(request.destination);

    if (isStaticAsset) {
        event.respondWith((async () => {
            const cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);
            if (cached) {
                fetch(request)
                    .then((response) => {
                        if (isCacheableResponse(response)) {
                            cache.put(request, response.clone());
                        }
                    })
                    .catch(() => {});
                return cached;
            }

            const networkResponse = await fetch(request);
            if (isCacheableResponse(networkResponse)) {
                await cache.put(request, networkResponse.clone());
            }
            return networkResponse;
        })());
    }
});
