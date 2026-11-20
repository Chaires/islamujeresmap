/* Isla Mujeres Pass · modo sin conexión (página pública).
   - La página, estilos, scripts y fotos quedan guardados en el teléfono.
   - El catálogo y el tipo de cambio se piden en línea y, si no hay internet, se usa la última copia.
   - Los canjes hechos sin internet los guarda app.js y los manda al reconectar. */
const VERSION = "impass-v6";
const BASE = [
  "./", "index.html", "manifest.webmanifest",
  "assets/css/estilos.css?v=5", "assets/js/datos.js", "assets/js/idiomas.js?v=6", "assets/js/app.js?v=7",
  "assets/vendor/leaflet/leaflet.css", "assets/vendor/leaflet/leaflet.js", "assets/vendor/qrcode.js",
  "assets/img/icono.png", "assets/img/wallet-logo.png",
];
const MAX_MAPA = 400;

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(BASE)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== VERSION && k !== "impass-mapa").map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

async function primeroRed(req, llave) {
  const cache = await caches.open(VERSION);
  try {
    const r = await fetch(req);
    if (r.ok) cache.put(llave || req, r.clone());
    return r;
  } catch (e) {
    const g = await cache.match(llave || req, { ignoreSearch: !llave });
    if (g) return g;
    throw e;
  }
}

async function primeroCache(req, nombre, limite) {
  const cache = await caches.open(nombre);
  const g = await cache.match(req);
  if (g) return g;
  const r = await fetch(req);
  if (r.ok || r.type === "opaque") {
    cache.put(req, r.clone());
    if (limite) cache.keys().then((ks) => { if (ks.length > limite) ks.slice(0, ks.length - limite).forEach((k) => cache.delete(k)); });
  }
  return r;
}

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  const scope = new URL(self.registration.scope);

  // Mosaicos del mapa (Esri) y tipografías
  if (url.hostname.endsWith("arcgisonline.com")) return e.respondWith(primeroCache(req, "impass-mapa", MAX_MAPA));
  if (url.hostname.endsWith("fonts.googleapis.com") || url.hostname.endsWith("fonts.gstatic.com")) return e.respondWith(primeroCache(req, VERSION));
  if (url.origin !== scope.origin || !url.pathname.startsWith(scope.pathname)) return;

  const ruta = url.pathname.slice(scope.pathname.length);
  if (ruta.startsWith("admin/") || ruta.startsWith("privado/") || ruta.startsWith("app/")) return;
  if (ruta === "api/catalogo.php" || ruta === "api/tipos.php") return e.respondWith(primeroRed(req, ruta));
  if (ruta.startsWith("api/")) return; // wallet, estado, canjear: siempre en línea
  if (req.mode === "navigate") return e.respondWith(primeroRed(req, "index.html"));
  if (ruta.startsWith("assets/img/")) return e.respondWith(primeroCache(req, VERSION));
  e.respondWith(primeroRed(req));
});
