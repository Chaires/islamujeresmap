/*
 * Funciones compartidas por todas las páginas: idioma, catálogo, precios,
 * reserva (carrito), encabezado y pie.
 */
(function () {
  "use strict";
  const C = window.VR_CONFIG;

  /* ---------------- Idioma ---------------- */
  const TEXTOS = {
    navDestinos: { es: "Destinos", en: "Destinations" },
    navTours: { es: "Tours", en: "Tours" },
    navYates: { es: "Yates", en: "Yachts" },
    navComo: { es: "Cómo reservar", en: "How to book" },
    navContacto: { es: "Contacto", en: "Contact" },
    miReserva: { es: "Mi reserva", en: "My booking" },
    desde: { es: "Desde", en: "From" },
    porPersona: { es: "por persona", en: "per person" },
    porConfirmar: { es: "Por confirmar", en: "To be confirmed" },
    verTour: { es: "Ver tour", en: "View tour" },
    enReserva: { es: "En tu reserva", en: "In your booking" },
    agregado: { es: "Agregado a tu reserva", en: "Added to your booking" },
    verReserva: { es: "Ver reserva", en: "View booking" },
    maxTours: { es: "Tu reserva ya tiene el máximo de {n} tours.", en: "Your booking already has the maximum of {n} tours." },
    todos: { es: "Todos", en: "All" },
    todosDestinos: { es: "Todos los destinos", en: "All destinations" },
    todasCategorias: { es: "Todas las categorías", en: "All categories" },
    tours: { es: "tours", en: "tours" },
    tour1: { es: "tour", en: "tour" },
    adultos: { es: "adultos", en: "adults" },
    adulto: { es: "adulto", en: "adult" },
    menores: { es: "menores", en: "minors" },
    menor: { es: "menor", en: "minor" },
    horas: { es: "horas", en: "hours" },
    pieTexto: {
      es: "Agencia de viajes con base en el Caribe Mexicano. Tours, traslados, hoteles y experiencias con atención personal por WhatsApp.",
      en: "Travel agency based in the Mexican Caribbean. Tours, transfers, hotels and experiences with personal service on WhatsApp."
    },
    pieExplora: { es: "Explora", en: "Explore" },
    pieDestinos: { es: "Destinos", en: "Destinations" },
    pieContacto: { es: "Contacto", en: "Contact" },
    pieRnt: { es: "Agencia registrada · RNT", en: "Registered agency · RNT" },
    pieDerechos: { es: "Todos los derechos reservados.", en: "All rights reserved." },
    waHola: { es: "Hola, quiero información sobre sus tours.", en: "Hi, I'd like information about your tours." },
    menu: { es: "Menú", en: "Menu" }
  };

  function idioma() {
    try { return localStorage.getItem("vr_idioma") === "en" ? "en" : "es"; } catch (e) { return "es"; }
  }
  function cambiarIdioma(nuevo) {
    try { localStorage.setItem("vr_idioma", nuevo); } catch (e) { /* sin almacenamiento */ }
    location.reload();
  }
  function t(clave, vars) {
    const v = TEXTOS[clave] || (VR.textosPagina && VR.textosPagina[clave]);
    let s = v ? (v[idioma()] || v.es) : clave;
    if (vars) Object.keys(vars).forEach(k => { s = s.replace("{" + k + "}", vars[k]); });
    return s;
  }
  /** Texto bilingüe de los datos ({es, en}) */
  function tx(obj) {
    if (obj == null) return "";
    if (typeof obj === "string") return obj;
    return obj[idioma()] || obj.es || "";
  }
  /** Aplica traducciones a elementos con data-t="clave" (y data-t-ph para placeholder) */
  function traducirPagina(raiz) {
    (raiz || document).querySelectorAll("[data-t]").forEach(el => { el.innerHTML = t(el.dataset.t); });
    (raiz || document).querySelectorAll("[data-t-ph]").forEach(el => { el.placeholder = t(el.dataset.tPh); });
    document.documentElement.lang = idioma();
  }

  /* ---------------- Catálogo ---------------- */
  const CATEGORIAS = {
    acuaticas:     { es: "Acuáticas",       en: "Water",         ico: "🌊", fondo: "#e2f8fc" },
    ecotours:      { es: "Ecotours",        en: "Eco tours",     ico: "🌿", fondo: "#effbd6" },
    arqueologicos: { es: "Arqueológicos",   en: "Archaeological", ico: "🏛️", fondo: "#fff1e9" },
    parques:       { es: "Parques Xcaret",  en: "Xcaret Parks",  ico: "🦜", fondo: "#e8f7ee" },
    extremas:      { es: "Extremas",        en: "Extreme",       ico: "🪂", fondo: "#fde8ef" },
    tematicas:     { es: "Temáticas",       en: "Themed",        ico: "🏴‍☠️", fondo: "#efeefe" },
    interactivas:  { es: "Delfines",        en: "Dolphins",      ico: "🐬", fondo: "#e2f8fc" },
    antros:        { es: "Antros",          en: "Nightlife",     ico: "🪩", fondo: "#fde8ef" },
    yates:         { es: "Yates",           en: "Yachts",        ico: "🛥️", fondo: "#eaf2ff" }
  };
  const DESTINOS = {
    "cancun":       { nombre: "Cancún", portada: "isla-mujeres", texto: { es: "Playas turquesa, vida nocturna y yates", en: "Turquoise beaches, nightlife and yachts" } },
    "riviera-maya": { nombre: "Riviera Maya", portada: "xcaret-plus", texto: { es: "Parques Xcaret, cenotes y aventura", en: "Xcaret parks, cenotes and adventure" } },
    "isla-mujeres": { nombre: "Isla Mujeres", portada: "sunsation", texto: { es: "Catamarán, Playa Norte y fiesta", en: "Catamaran, Playa Norte and party" } },
    "tulum":        { nombre: "Tulum", portada: "tulum-casa-tortugas", texto: { es: "Ruinas frente al mar y tortugas", en: "Seaside ruins and sea turtles" } },
    "yucatan":      { nombre: "Chichén Itzá & Yucatán", portada: "chichen-itza-deluxe", texto: { es: "Maravilla del mundo y cenotes", en: "A world wonder and cenotes" } },
    "holbox":       { nombre: "Holbox", portada: "holbox-plus-cenote", texto: { es: "Isla sin autos y aguas calmas", en: "Car-free island, calm waters" } },
    "bacalar":      { nombre: "Bacalar", portada: "bacalar-plus-ponton", texto: { es: "La laguna de los siete colores", en: "The lagoon of seven colors" } },
    "cozumel":      { nombre: "Cozumel", portada: "cozumel-cielo-lancha", texto: { es: "El Cielo y arrecifes de colores", en: "El Cielo and colorful reefs" } }
  };

  let cacheTours = null;
  function cargarTours() {
    if (cacheTours) return Promise.resolve(cacheTours);
    return fetch("data/tours.json", { cache: "no-cache" })
      .then(r => { if (!r.ok) throw new Error("tours.json " + r.status); return r.json(); })
      .then(d => { cacheTours = d.tours.slice().sort((a, b) => a.orden - b.orden); return cacheTours; });
  }

  /* ---------------- Precios ---------------- */
  function aPesos(monto, moneda) { return moneda === "USD" ? monto * C.tipoCambio : monto; }
  function dinero(monto, moneda) {
    if (monto == null || isNaN(monto)) return t("porConfirmar");
    return "$" + Math.round(monto).toLocaleString("en-US") + " " + (moneda || "MXN");
  }
  /** Precio de referencia para mostrar en tarjetas */
  function precioTarjeta(tour) {
    const p = tour.precio || {};
    if (tour.yate) {
      const min = Math.min.apply(null, tour.yate.tarifas.map(x => x.precio));
      return { desde: true, monto: min, moneda: "MXN", sufijo: "" };
    }
    return { desde: !!p.desde || !!tour.opciones || !!tour.opciones_por_dia, monto: p.monto, moneda: p.moneda || "MXN", sufijo: t("porPersona") };
  }
  /** Lista plana de opciones elegibles: [{etiqueta, precio, grupo}] */
  function opcionesDe(tour) {
    if (tour.opciones_por_dia) {
      const out = [];
      tour.opciones_por_dia.forEach(g => g.opciones.forEach(o => out.push({ etiqueta: tx(g.dia) + " · " + tx(o.etiqueta), precio: o.precio })));
      return out;
    }
    if (tour.opciones) return tour.opciones.map(o => ({ etiqueta: tx(o.etiqueta), precio: o.precio }));
    return [];
  }
  /** Tarifa de yate elegida: por posición (sel.tarifa) o, en reservas viejas, por horas */
  function tarifaYate(tour, sel) {
    const ts = tour.yate.tarifas;
    if (sel.tarifa != null && ts[sel.tarifa]) return ts[sel.tarifa];
    return ts.find(x => x.horas === sel.horas) || ts[0];
  }
  function etiquetaTarifa(tarifa) { return tarifa.etiqueta ? tx(tarifa.etiqueta) : tarifa.horas + " " + t("horas"); }
  /**
   * Calcula el estimado de un renglón de la reserva.
   * La misma lógica está en api/solicitud.php para que el servidor la valide.
   */
  function calcular(tour, sel) {
    const pax = (sel.adultos || 0) + (sel.menores || []).length;
    if (tour.yate) {
      const tarifa = tarifaYate(tour, sel);
      const muelle = (tour.yate.muelle_por_persona || 0) * pax;
      return { unitario: tarifa.precio, moneda: "MXN", total: tarifa.precio + muelle, detalle: etiquetaTarifa(tarifa) + (muelle ? " + muelle" : "") };
    }
    if (sel.extra && tour.extra) {
      return { unitario: tour.extra.precio.monto, moneda: tour.extra.precio.moneda, total: tour.extra.precio.monto, detalle: tx(tour.extra.etiqueta) };
    }
    const ops = opcionesDe(tour);
    if (ops.length && sel.opcion != null && ops[sel.opcion]) {
      const o = ops[sel.opcion];
      const m = o.precio.monto;
      return { unitario: m, moneda: o.precio.moneda || "MXN", total: m == null ? null : m * pax, detalle: o.etiqueta };
    }
    const p = tour.precio || {};
    return { unitario: p.monto, moneda: p.moneda || "MXN", total: p.monto == null ? null : p.monto * pax, detalle: "" };
  }

  /* ---------------- Reserva (carrito) ---------------- */
  function leerReserva() {
    try { const r = JSON.parse(localStorage.getItem("vr_reserva") || "[]"); return Array.isArray(r) ? r : []; } catch (e) { return []; }
  }
  function guardarReserva(lista) {
    try { localStorage.setItem("vr_reserva", JSON.stringify(lista)); } catch (e) { /* sin almacenamiento */ }
    pintarContador();
  }
  function agregarAReserva(item) {
    const lista = leerReserva();
    const i = lista.findIndex(x => x.slug === item.slug);
    if (i >= 0) lista[i] = item;
    else {
      if (lista.length >= C.maxTours) { aviso(t("maxTours", { n: C.maxTours })); return false; }
      lista.push(item);
    }
    guardarReserva(lista);
    return true;
  }
  function quitarDeReserva(slug) { guardarReserva(leerReserva().filter(x => x.slug !== slug)); }
  function pintarContador() {
    const n = leerReserva().length;
    document.querySelectorAll("[data-contador]").forEach(el => { el.textContent = n ? String(n) : ""; });
  }

  /* Código de vendedor: ?ref=CODIGO en cualquier enlace queda guardado 30 días */
  function guardarReferido() {
    const ref = new URLSearchParams(location.search).get("ref");
    if (ref && /^[A-Za-z0-9_-]{2,30}$/.test(ref)) {
      try { localStorage.setItem("vr_ref", JSON.stringify({ codigo: ref.toUpperCase(), hasta: Date.now() + 30 * 864e5 })); } catch (e) { /* nada */ }
    }
  }
  function referido() {
    try {
      const r = JSON.parse(localStorage.getItem("vr_ref") || "null");
      return r && r.hasta > Date.now() ? r.codigo : "";
    } catch (e) { return ""; }
  }

  /* ---------------- Utilidades de interfaz ---------------- */
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }
  function urlWhatsApp(texto) { return "https://wa.me/" + C.whatsapp + "?text=" + encodeURIComponent(texto); }

  const ICONO_WA = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.5-.2Z"/></svg>';
  const ICONO_FLECHA = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>';
  const ICONO_PIN = '<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>';

  function tarjetaTour(tour, enReserva) {
    const p = precioTarjeta(tour);
    const cat = CATEGORIAS[tour.categoria] || {};
    const dest = DESTINOS[tour.destino] || {};
    return '<a class="tarjeta" href="tour.html?t=' + encodeURIComponent(tour.slug) + '">' +
      '<div class="tarjeta__foto"><img loading="lazy" src="' + esc(tour.imagen) + '" alt="' + esc(tx(tour.titulo)) + '">' +
      '<span class="tarjeta__cat">' + (cat.ico || "") + " " + esc(tx(cat)) + "</span>" +
      (enReserva ? '<span class="tarjeta__en-reserva">✓ ' + t("enReserva") + "</span>" : "") +
      '<span class="tarjeta__destino">' + ICONO_PIN + esc(dest.nombre || "") + "</span></div>" +
      '<div class="tarjeta__cuerpo"><h3>' + esc(tx(tour.titulo)) + "</h3><p>" + esc(tx(tour.descripcion)) + "</p>" +
      '<div class="tarjeta__pie"><div class="precio"><small>' + (p.desde ? t("desde") : (p.sufijo || "&nbsp;")) + "</small>" +
      "<b>" + esc(dinero(p.monto, p.moneda)) + "</b></div>" +
      '<span class="tarjeta__ir" aria-label="' + t("verTour") + '">' + ICONO_FLECHA + "</span></div></div></a>";
  }

  let temporizadorAviso;
  function aviso(html) {
    let el = document.querySelector(".toast");
    if (!el) { el = document.createElement("div"); el.className = "toast"; el.setAttribute("role", "status"); document.body.appendChild(el); }
    el.innerHTML = html;
    requestAnimationFrame(() => el.classList.add("visible"));
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(() => el.classList.remove("visible"), 4200);
  }

  function animarAlVer() {
    const els = document.querySelectorAll(".aparece:not(.visto)");
    if (!("IntersectionObserver" in window)) { els.forEach(e => e.classList.add("visto")); return; }
    const io = new IntersectionObserver(ent => ent.forEach(e => { if (e.isIntersecting) { e.target.classList.add("visto"); io.unobserve(e.target); } }), { rootMargin: "0px 0px -8% 0px" });
    els.forEach(e => io.observe(e));
  }

  /* ---------------- Encabezado y pie ---------------- */
  function encabezado(opciones) {
    const transparente = opciones && opciones.transparente;
    const otro = idioma() === "es" ? "EN" : "ES";
    const nav =
      '<a href="index.html#destinos">' + t("navDestinos") + "</a>" +
      '<a href="tours.html">' + t("navTours") + "</a>" +
      '<a href="tours.html?c=yates">' + t("navYates") + "</a>" +
      '<a href="index.html#como">' + t("navComo") + "</a>" +
      '<a href="#contacto">' + t("navContacto") + "</a>";
    const h = document.createElement("header");
    h.className = "barra" + (transparente ? "" : " barra--solida");
    h.innerHTML =
      '<div class="envoltura barra__fila">' +
      '<a class="marca" href="index.html" aria-label="' + esc(C.nombre) + '">' +
      '<img class="logo-claro" src="' + C.logos.claro + '" alt="" width="40" height="40">' +
      '<img class="logo-oscuro" src="' + C.logos.oscuro + '" alt="" width="40" height="40">' +
      '<span class="marca__texto">Viaje Redondo<small>Caribe Mexicano</small></span></a>' +
      '<nav class="barra__nav" aria-label="Principal">' + nav + "</nav>" +
      '<div class="barra__acciones">' +
      '<button type="button" class="chip-btn" data-idioma aria-label="Language">' + otro + "</button>" +
      '<a class="chip-btn chip-btn--carrito" href="reservar.html" aria-label="' + t("miReserva") + '">' +
      '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16l-1.5 12.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5L4 7Zm4 0V6a4 4 0 0 1 8 0v1"/></svg>' +
      '<span class="chip-btn__num" data-contador></span></a>' +
      '<button type="button" class="chip-btn menu-btn" aria-expanded="false" aria-label="' + t("menu") + '">' +
      '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg></button>' +
      "</div></div>" +
      '<nav class="cajon" aria-label="Menú móvil">' + nav + "</nav>";
    document.body.prepend(h);

    h.querySelector("[data-idioma]").addEventListener("click", () => cambiarIdioma(idioma() === "es" ? "en" : "es"));
    const btn = h.querySelector(".menu-btn");
    btn.addEventListener("click", () => {
      const abierto = h.classList.toggle("abierta");
      btn.setAttribute("aria-expanded", String(abierto));
    });
    h.querySelectorAll(".cajon a").forEach(a => a.addEventListener("click", () => h.classList.remove("abierta")));
    if (transparente) {
      const revisar = () => h.classList.toggle("barra--solida", window.scrollY > 40);
      revisar();
      window.addEventListener("scroll", revisar, { passive: true });
    }
    pintarContador();
  }

  function pie() {
    const anio = new Date().getFullYear();
    const destinos = Object.keys(DESTINOS).slice(0, 6).map(k => '<a href="tours.html?d=' + k + '">' + esc(DESTINOS[k].nombre) + "</a>").join("");
    const f = document.createElement("footer");
    f.className = "pie";
    f.id = "contacto";
    f.innerHTML =
      '<div class="envoltura"><div class="pie__rejilla">' +
      '<div class="pie__marca"><img src="assets/img/logo/logo-turquesa.png" alt="Viaje Redondo" width="84" height="84"><p>' + t("pieTexto") + "</p>" +
      '<span class="pie__rnt">' + t("pieRnt") + ": " + esc(C.rnt) + "</span></div>" +
      "<div><h4>" + t("pieExplora") + '</h4><a href="tours.html">' + t("navTours") + '</a><a href="tours.html?c=yates">' + t("navYates") +
      '</a><a href="index.html#como">' + t("navComo") + '</a><a href="reservar.html">' + t("miReserva") + "</a></div>" +
      "<div><h4>" + t("pieDestinos") + "</h4>" + destinos + "</div>" +
      "<div><h4>" + t("pieContacto") + '</h4><a href="' + urlWhatsApp(t("waHola")) + '" target="_blank" rel="noopener">WhatsApp ' + esc(C.telefonos[0]) + "</a>" +
      '<a href="tel:' + C.telefonos[1].replace(/\s/g, "") + '">' + esc(C.telefonos[1]) + "</a>" +
      '<a href="mailto:' + esc(C.correo) + '">' + esc(C.correo) + "</a>" +
      '<a href="' + C.redes.instagram + '" target="_blank" rel="noopener">Instagram</a>' +
      '<a href="' + C.redes.facebook + '" target="_blank" rel="noopener">Facebook</a>' +
      '<a href="' + C.redes.youtube + '" target="_blank" rel="noopener">YouTube</a>' +
      '<a href="' + C.redes.tiktok + '" target="_blank" rel="noopener">TikTok</a></div>' +
      "</div>" +
      '<div class="pie__legal"><span>© ' + anio + " " + esc(C.nombre) + ". " + t("pieDerechos") + "</span><span>" + esc(tx(C.lema)) + "</span></div></div>";
    document.body.appendChild(f);

    const wa = document.createElement("a");
    wa.className = "wa-flotante";
    wa.href = urlWhatsApp(t("waHola"));
    wa.target = "_blank";
    wa.rel = "noopener";
    wa.setAttribute("aria-label", "WhatsApp");
    wa.innerHTML = ICONO_WA;
    document.body.appendChild(wa);
  }

  guardarReferido();

  window.VR = {
    C, t, tx, idioma, traducirPagina, CATEGORIAS, DESTINOS, cargarTours, dinero, aPesos, precioTarjeta, opcionesDe, calcular, tarifaYate, etiquetaTarifa,
    leerReserva, guardarReserva, agregarAReserva, quitarDeReserva, referido, esc, urlWhatsApp, tarjetaTour, aviso, animarAlVer,
    encabezado, pie, ICONO_WA, ICONO_FLECHA, ICONO_PIN, textosPagina: null
  };
})();
