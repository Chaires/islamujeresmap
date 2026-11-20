/* Panel · modo sin conexión: guarda el escáner y los estilos para poder abrirlo sin internet.
   Las validaciones hechas sin internet las guarda escanear.js y las manda al reconectar. */
const VERSION = "impanel-v1";
const BASE = ["escanear.php", "admin.css?v=6", "escanear.js?v=3", "../assets/vendor/jsQR.js", "../assets/img/icono.png", "manifest.webmanifest"];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => Promise.all(BASE.map((u) => c.add(u).catch(() => {})))).then(() => self.skipWaiting()));
});
self.addEventListener("activate", (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k.startsWith("impanel-") && k !== VERSION).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});
self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET" || new URL(req.url).origin !== location.origin) return;
  const url = new URL(req.url);
  if (url.pathname.endsWith("/api.php") || url.pathname.endsWith("/salir.php") || url.search.includes("csv")) return;
  // Primero la red; si no hay internet, la última copia guardada
  e.respondWith((async () => {
    const cache = await caches.open(VERSION);
    try {
      const r = await fetch(req);
      if (r.ok && !r.redirected && (req.mode !== "navigate" || url.pathname.endsWith("/escanear.php"))) cache.put(req, r.clone());
      return r;
    } catch (err) {
      const g = await cache.match(req, { ignoreSearch: true })
        || (req.mode === "navigate" ? await cache.match("escanear.php") : null);
      if (g) return g;
      throw err;
    }
  })());
});
