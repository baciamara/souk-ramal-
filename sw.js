// Service Worker - سوق الرمال الذهبية
const CACHE_NAME = 'souk-ramal-v1';

// الملفات التي سيتم تخزينها للعمل بدون إنترنت
const urlsToCache = [
    '/',
    '/index.php',
    '/login.php',
    '/register.php',
    '/uploads/icon-512.png'
];

// تثبيت Service Worker
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            console.log('تم فتح الذاكرة المؤقتة');
            return cache.addAll(urlsToCache);
        })
    );
});

// استراتيجية: الشبكة أولاً، ثم الذاكرة المؤقتة
self.addEventListener('fetch', function(event) {
    event.respondWith(
        fetch(event.request)
            .then(function(response) {
                // تخزين النسخة في الذاكرة المؤقتة
                var responseClone = response.clone();
                caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(event.request, responseClone);
                });
                return response;
            })
            .catch(function() {
                // إذا فشل الاتصال، استخدم الذاكرة المؤقتة
                return caches.match(event.request);
            })
    );
});

// تحديث الذاكرة المؤقتة عند وجود نسخة جديدة
self.addEventListener('activate', function(event) {
    var cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});