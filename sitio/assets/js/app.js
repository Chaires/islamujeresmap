/* =========================================================================
   Isla Mujeres Pass · lógica de la página
   Los textos, precios y fotos se editan en datos.js, no aquí.
   ========================================================================= */
(function () {
  "use strict";

  const $ = (sel, raiz = document) => raiz.querySelector(sel);
  const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));
  const RUTA_IMG = "assets/img/";
  const API = "api/";

  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  /* ---------------------------------------------------------------- Idioma y moneda */
  const leer = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };
  const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } };
  let idioma = IDIOMAS[leer("im_idioma")] ? leer("im_idioma") : null;
  let tasas = { ...MONEDA.tipoCambioRespaldo };
  const lang = () => idioma || "es";
  const info = () => IDIOMAS[lang()];

  function t(clave, vars) {
    let txt = (TEXTOS[lang()] && TEXTOS[lang()][clave]) ?? TEXTOS.es[clave] ?? clave;
    if (vars) Object.entries(vars).forEach(([k, v]) => { txt = txt.split("{" + k + "}").join(v); });
    return txt;
  }

  // Contenido traducido (si falta una traducción se usa el español de datos.js)
  function tradPunto(p) {
    const tr = ((TRADUCCIONES[lang()] || {}).puntos || {})[p.id] || {};
    return { ...p, ...tr };
  }
  function tradCupon(c) {
    const tr = ((TRADUCCIONES[lang()] || {}).cupones || {})[c.id] || {};
    const conceptos = tr.conceptos || [];
    return { ...c, ...tr, precios: c.precios.map((p, i) => ({ ...p, concepto: conceptos[i] || p.concepto })) };
  }

  const pesos = (n) => "$" + Math.round(n).toLocaleString("es-MX") + " MXN";
  const pesosCorto = (n) => "$" + Math.round(n).toLocaleString("es-MX");

  // Convierte un precio en pesos a la moneda del idioma (+ recargo si no es español)
  function convertir(mxn) {
    const moneda = info().moneda;
    if (moneda === "MXN") return { moneda, valor: mxn };
    const valor = Math.ceil(mxn * (tasas[moneda] || MONEDA.tipoCambioRespaldo[moneda]) * (1 + MONEDA.recargoExtranjero));
    return { moneda, valor };
  }
  function formatoMoneda(valor, moneda) {
    if (moneda === "MXN") return pesosCorto(valor);
    if (moneda === "USD") return "US$" + valor.toLocaleString("en-US");
    return new Intl.NumberFormat(IDIOMAS[lang()].locale, { style: "currency", currency: moneda, maximumFractionDigits: 0 }).format(valor);
  }
  function precioTxt(mxn) {
    if (mxn === 0) return t("gratis");
    const c = convertir(mxn);
    return formatoMoneda(c.valor, c.moneda);
  }
  // Precio con la nota discreta en pesos (solo si el idioma no es español)
  function precioHTML(mxn, clase) {
    const nota = mxn > 0 && lang() !== "es" ? `<small class="nota-mxn">${esc(t("notaPesos", { mxn: pesos(mxn) }))}</small>` : "";
    return `<span class="${clase || "ahora"}">${esc(precioTxt(mxn))}</span>${nota}`;
  }

  const ICONO_WA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.5-.2Z"/></svg>';

  function enlaceWhatsApp(texto) {
    const base = "https://wa.me/" + CONFIG_SITIO.whatsapp;
    return texto ? base + "?text=" + encodeURIComponent(texto) : base;
  }

  function aviso(msg) {
    const el = $("#aviso");
    el.textContent = msg;
    el.classList.add("visible");
    clearTimeout(aviso._t);
    aviso._t = setTimeout(() => el.classList.remove("visible"), 3200);
  }

  /* ---------------------------------------------------------------- Modal */
  const modal = $("#modal");
  const contenidoModal = $("#modal-contenido");
  let focoPrevio = null;

  function abrirModal(html, color) {
    focoPrevio = document.activeElement;
    contenidoModal.innerHTML = html;
    contenidoModal.scrollTop = 0;
    modal.style.setProperty("--c", color || "var(--turquesa)");
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("sin-scroll");
    setTimeout(() => $(".modal__cerrar").focus({ preventScroll: true }), 50);
  }
  function cerrarModal() {
    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("sin-scroll");
    contenidoModal.innerHTML = ""; // detiene videos
    if (focoPrevio) focoPrevio.focus({ preventScroll: true });
  }
  modal.addEventListener("click", (e) => { if (e.target.closest("[data-cerrar]")) cerrarModal(); });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && modal.classList.contains("abierto")) cerrarModal(); });

  /* ---------------------------------------------------------------- Bloques */
  function htmlGaleria(fotos, alt) {
    const imgs = fotos.map((f, i) =>
      `<img src="${RUTA_IMG + esc(f)}" alt="${esc(alt)} · foto ${i + 1}" loading="${i ? "lazy" : "eager"}" onerror="this.style.visibility='hidden'">`).join("");
    const puntos = fotos.length > 1 ? fotos.map((_, i) => `<button type="button" aria-label="Foto ${i + 1}" data-i="${i}" class="${i ? "" : "activo"}"></button>`).join("") : "";
    const flechas = fotos.length > 1 ? '<button type="button" class="galeria__flecha izq" aria-label="Anterior">‹</button><button type="button" class="galeria__flecha der" aria-label="Siguiente">›</button>' : "";
    return `<div class="galeria"><div class="galeria__pista">${imgs}</div>${flechas}<div class="galeria__puntos">${puntos}</div></div>`;
  }
  function activarGaleria(raiz) {
    const pista = $(".galeria__pista", raiz);
    if (!pista) return;
    const puntos = $$(".galeria__puntos button", raiz);
    const ir = (i) => pista.scrollTo({ left: i * pista.clientWidth, behavior: "smooth" });
    const actual = () => Math.round(pista.scrollLeft / pista.clientWidth);
    puntos.forEach((b) => b.addEventListener("click", () => ir(+b.dataset.i)));
    const izq = $(".galeria__flecha.izq", raiz), der = $(".galeria__flecha.der", raiz);
    if (izq) izq.addEventListener("click", () => ir(Math.max(0, actual() - 1)));
    if (der) der.addEventListener("click", () => ir(Math.min(puntos.length - 1, actual() + 1)));
    pista.addEventListener("scroll", () => {
      const i = actual();
      puntos.forEach((b, j) => b.classList.toggle("activo", i === j));
    }, { passive: true });
  }

  function htmlInstagram(url) {
    const m = String(url || "").match(/instagram\.com\/(?:[^/]+\/)?(p|reel|reels|tv)\/([A-Za-z0-9_-]+)/);
    if (!m) {
      return `<div class="video video--vacio"><span>🎬</span>${esc(t("videoVacio"))}</div>`;
    }
    const tipo = m[1] === "reels" ? "reel" : m[1];
    return `<div class="video"><iframe src="https://www.instagram.com/${tipo}/${m[2]}/embed/" loading="lazy" allowtransparency="true" allow="encrypted-media" title="Video de Instagram"></iframe></div>`;
  }

  /* ---------------------------------------------------------------- Mapa */
  let mapa, marcadores = {}, capas = {};

  function iniciarMapa() {
    mapa = L.map("mapa-leaflet", {
      center: CONFIG_SITIO.mapaCentro,
      zoom: CONFIG_SITIO.mapaZoom,
      minZoom: 11,
      zoomSnap: 0.5,
      maxZoom: 18,
      zoomControl: false,
      tap: true,
      maxBounds: [[21.12, -86.85], [21.34, -86.6]],
      maxBoundsViscosity: 0.8,
    });
    mapa.attributionControl.setPrefix(false);
    // Créditos del mapa (obligatorios): se muestran al tocar el botón ⓘ
    const creditos = mapa.attributionControl.getContainer();
    creditos.classList.add("creditos");
    creditos.setAttribute("title", "Créditos del mapa");
    L.DomEvent.on(creditos, "click", (e) => { L.DomEvent.stop(e); creditos.classList.toggle("abierto"); });
    L.control.zoom({ position: "bottomleft" }).addTo(mapa);

    // Mapas de Esri: no requieren llave ni cuenta
    const esri = (servicio, extra) => L.tileLayer(`https://server.arcgisonline.com/ArcGIS/rest/services/${servicio}/MapServer/tile/{z}/{y}/{x}`, { maxZoom: 18, ...extra });
    capas.satelite = L.layerGroup([
      esri("World_Imagery", { attribution: "Imágenes © Esri, Maxar, Earthstar Geographics" }),
      esri("Reference/World_Boundaries_and_Places"),
    ]);
    capas.calles = esri("World_Street_Map", { attribution: "© Esri, HERE, Garmin, OpenStreetMap" });
    capas.satelite.addTo(mapa);

    $$(".mapa-capas button").forEach((b) => b.addEventListener("click", () => {
      $$(".mapa-capas button").forEach((x) => x.classList.toggle("activo", x === b));
      Object.values(capas).forEach((c) => mapa.removeLayer(c));
      capas[b.dataset.capa].addTo(mapa);
    }));

    PUNTOS.forEach((p) => {
      const m = L.marker(p.coords, { icon: iconoPunto(p), title: tradPunto(p).nombre, riseOnHover: true, keyboard: true }).addTo(mapa);
      m.on("click", () => abrirPunto(p.id));
      marcadores[p.id] = m;
    });

    // Los nombres de los puntos solo se muestran de cerca para que no se encimen
    const nombres = () => $("#mapa-leaflet").classList.toggle("sin-nombres", mapa.getZoom() < 15);
    mapa.on("zoomend", nombres);

    const limites = L.latLngBounds(PUNTOS.map((p) => p.coords)).pad(0.08);
    const margen = { paddingTopLeft: [10, 60], paddingBottomRight: [10, 10] };
    const verTodo = () => mapa.flyToBounds(limites, { ...margen, duration: 0.8 });
    mapa.fitBounds(limites, margen);
    nombres();
    $("#mapa-reset").addEventListener("click", verTodo);
  }

  function iconoPunto(p) {
    const tp = tradPunto(p);
    return L.divIcon({
      className: "pin",
      html: `<div class="pin__cuerpo" style="--c:${p.color}"><span>${p.icono}</span></div><div class="pin__pulso" style="--c:${p.color}"></div><div class="pin__nombre">${esc(tp.nombre)}</div>`,
      iconSize: [46, 46],
      iconAnchor: [23, 52],
    });
  }

  function resaltarMarcador(id) {
    Object.entries(marcadores).forEach(([k, m]) => {
      const el = m.getElement();
      if (el) el.classList.toggle("activo", k === id);
    });
  }

  function iniciarChips() {
    $("#chips-puntos").innerHTML = PUNTOS.map((p) =>
      `<button type="button" class="chip" role="listitem" data-id="${p.id}"><span class="punto" style="background:${p.color}"></span>${p.icono} ${esc(tradPunto(p).nombre)}</button>`).join("");
    $$("#chips-puntos .chip").forEach((b) => b.addEventListener("click", () => {
      const p = PUNTOS.find((x) => x.id === b.dataset.id);
      $("#mapa").scrollIntoView({ behavior: "smooth", block: "center" });
      mapa.flyTo(p.coords, 16, { duration: 1 });
      resaltarMarcador(p.id);
      setTimeout(() => abrirPunto(p.id), 900);
    }));
  }

  function abrirPunto(id) {
    const base = PUNTOS.find((x) => x.id === id);
    if (!base) return;
    const p = tradPunto(base);
    resaltarMarcador(id);
    const destino = p.coords.join(",");
    const ruta = `https://www.google.com/maps/dir/?api=1&destination=${destino}&travelmode=driving`;
    const cuponBase = p.cupon ? CUPONES.find((c) => c.id === p.cupon) : null;
    const cuponDe = cuponBase ? tradCupon(cuponBase) : null;
    abrirModal(`
      ${htmlGaleria(p.fotos, p.nombre)}
      <div class="ficha">
        <span class="ficha__tipo">${p.icono} ${esc(p.tipo)}</span>
        <h2 id="modal-titulo">${esc(p.nombre)}</h2>
        <p>${esc(p.descripcion)}</p>
        <ul class="lista-check">${p.datos.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>
        <h3>${t("video")}</h3>
        ${htmlInstagram(p.instagram)}
        <div class="acciones acciones--2">
          <a class="btn btn--primario" href="${ruta}" target="_blank" rel="noopener">${t("trazarRuta")}</a>
          <button type="button" class="btn btn--claro" data-ver-mapa>${t("verMapa")}</button>
        </div>
        <div class="acciones">
          ${cuponDe ? `<button type="button" class="btn btn--coral" data-ver-cupon>🎟️ ${esc(cuponDe.titulo)} · ${esc(precioTxt(cuponDe.precios[0].promo))}</button>` : ""}
          <a class="btn btn--wa" href="${enlaceWhatsApp(t("waInfo", { lugar: p.nombre }))}" target="_blank" rel="noopener">${ICONO_WA} ${t("preguntaWa")}</a>
        </div>
      </div>`, p.color);
    activarGaleria(contenidoModal);
    if (cuponDe) $("[data-ver-cupon]", contenidoModal).addEventListener("click", () => abrirCupon(cuponDe.id));
    $("[data-ver-mapa]", contenidoModal).addEventListener("click", () => {
      cerrarModal();
      $("#mapa").scrollIntoView({ behavior: "smooth", block: "center" });
      mapa.flyTo(p.coords, 17, { duration: 1 });
    });
  }

  /* ---------------------------------------------------------------- Cupones */
  let filtroActual = "*";

  function iniciarCupones() {
    // Se filtra por la categoría en español; se muestra traducida
    const vistas = new Map();
    CUPONES.forEach((c) => { if (!vistas.has(c.categoria)) vistas.set(c.categoria, tradCupon(c).categoria); });
    const categorias = [["*", t("todos")], ...vistas.entries()];
    $("#filtros").innerHTML = categorias.map(([clave, texto]) =>
      `<button type="button" role="tab" class="filtro ${clave === filtroActual ? "activo" : ""}" data-cat="${esc(clave)}">${esc(texto)}</button>`).join("");
    $$("#filtros .filtro").forEach((b) => b.addEventListener("click", () => {
      filtroActual = b.dataset.cat;
      $$("#filtros .filtro").forEach((x) => x.classList.toggle("activo", x === b));
      pintarCupones();
    }));
    pintarCupones();
  }

  function pintarCupones() {
    const lista = CUPONES.filter((c) => filtroActual === "*" || c.categoria === filtroActual).map(tradCupon);
    $("#lista-cupones").innerHTML = lista.map((c, i) => {
      const p = c.precios[0];
      const yaUsado = usados.has(c.id);
      return `<button type="button" class="cupon${yaUsado ? " cupon--usado" : ""}" data-id="${c.id}" style="--c:${c.color}; animation-delay:${i * 60}ms">
        <div class="cupon__foto"><img src="${RUTA_IMG + esc(c.fotos[0])}" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
          <span class="cupon__etiqueta">${esc(c.etiqueta)}</span><span class="cupon__icono">${c.icono}</span></div>
        <div class="cupon__cuerpo">
          <span class="cupon__cat">${esc(c.categoria)}</span>
          <h3>${esc(c.titulo)}</h3>
          <p>${esc(c.resumen)}</p>
          <div class="cupon__precio">${p.normal > p.promo ? `<span class="antes">${esc(precioTxt(p.normal))}</span>` : ""}<span class="ahora">${c.precios.length > 1 ? esc(t("desde")) + " " : ""}${esc(precioTxt(p.promo))}</span></div>
          ${p.promo > 0 && lang() !== "es" ? `<small class="nota-mxn">${esc(t("notaPesos", { mxn: pesos(p.promo) }))}</small>` : ""}
          <span class="cupon__cta">${esc(yaUsado ? "✓ " + t("usado") : t("canjearCta"))}</span>
        </div></button>`;
    }).join("");
    $$("#lista-cupones .cupon").forEach((b) => b.addEventListener("click", () =>
      usados.has(b.dataset.id) ? aviso(t("yaUsado")) : abrirCupon(b.dataset.id)));
  }

  /* ------------------------------------------- Cupones ya usados por este visitante */
  // El navegador guarda los códigos que pidió (con su token) y pregunta cuáles ya se validaron.
  const usados = new Set();
  function misCanjes() {
    try { return JSON.parse(leer("im_mis") || "[]"); } catch (e) { return []; }
  }
  function recordarCanje(codigo, token, cuponId) {
    const lista = misCanjes().filter((x) => x.codigo !== codigo);
    lista.unshift({ codigo, token, cupon_id: cuponId });
    guardar("im_mis", JSON.stringify(lista.slice(0, 30)));
  }
  async function revisarUsados() {
    const lista = misCanjes();
    if (!lista.length) return;
    try {
      const r = await fetch(API + "estado.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ codigos: lista }) });
      const resp = await r.json();
      (resp.canjes || []).forEach((c) => { if (c.estatus === "usado") usados.add(c.cupon_id); });
      if (usados.size) pintarCupones();
    } catch (e) { /* sin servidor */ }
  }

  function hoyISO() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
  }

  function abrirCupon(id) {
    const base = CUPONES.find((x) => x.id === id);
    if (!base) return;
    const c = tradCupon(base);
    const filasPrecio = c.precios.map((p) =>
      `<tr><td>${esc(p.concepto)}</td><td>${p.normal > p.promo ? `<span class="antes">${esc(precioTxt(p.normal))}</span>` : ""}${precioHTML(p.promo)}</td></tr>`).join("");
    const opciones = c.precios.length > 1 ? `
      <div class="campo"><label for="f-opcion">${t("opcion")}</label>
        <select id="f-opcion" name="opcion">${c.precios.map((p, i) => `<option value="${i}">${esc(p.concepto)} · ${esc(precioTxt(p.promo))}</option>`).join("")}</select></div>` : "";

    abrirModal(`
      ${htmlGaleria(c.fotos, c.titulo)}
      <div class="ficha">
        <span class="ficha__tipo">${c.icono} ${esc(c.categoria)} · ${esc(c.etiqueta)}</span>
        <h2 id="modal-titulo">${esc(c.titulo)}</h2>
        <p>${esc(c.descripcion)}</p>
        <h3>${t("incluye")}</h3>
        <ul class="lista-check">${c.incluye.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>
        <h3>${t("precios")}</h3>
        <table class="precios"><tbody>${filasPrecio}</tbody></table>
        <h3>${t("video")}</h3>
        ${htmlInstagram(c.instagram)}
        <h3 id="canjear">${t("canjeaTitulo")}</h3>
        <form class="form" id="form-canje" novalidate>
          ${opciones}
          <div class="campo"><label for="f-nombre">${t("nombre")}</label>
            <input id="f-nombre" name="nombre" autocomplete="name" required maxlength="80" placeholder="${esc(t("nombreEj"))}">
            <div class="campo__error">${t("nombreErr")}</div></div>
          <div class="form__fila">
            <div class="campo"><label for="f-tel">${t("tel")}</label>
              <input id="f-tel" name="telefono" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" placeholder="${esc(t("telEj"))}">
              <div class="campo__error">${t("telErr")}</div></div>
            <div class="campo"><label for="f-correo">${t("correo")}</label>
              <input id="f-correo" name="correo" type="email" inputmode="email" autocomplete="email" required maxlength="120" placeholder="name@email.com">
              <div class="campo__error">${t("correoErr")}</div></div>
          </div>
          <div class="form__fila">
            <div class="campo"><label for="f-personas">${t("personas")}</label>
              <div class="contador"><button type="button" data-paso="-1" aria-label="−">−</button>
                <input id="f-personas" name="personas" type="number" inputmode="numeric" min="1" max="50" value="2" required>
                <button type="button" data-paso="1" aria-label="+">+</button></div>
              <div class="campo__error">${t("personasErr")}</div></div>
            <div class="campo"><label for="f-fecha">${t("fecha")}</label>
              <input id="f-fecha" name="fecha" type="date" required min="${hoyISO()}">
              <div class="campo__error">${t("fechaErr")}</div></div>
          </div>
          <div class="form__fila">
            <div class="campo"><label for="f-pais">${t("pais")}</label>
              <select id="f-pais" name="pais" required autocomplete="country-name">
                <option value="">${t("selecciona")}</option>${PAISES.map((p) => `<option value="${esc(p)}">${esc(nombrePais(p))}</option>`).join("")}</select>
              <div class="campo__error">${t("paisErr")}</div></div>
            <div class="campo" id="campo-estado">${campoEstadoTexto()}</div>
          </div>
          <div class="campo campo--vendedor"><label for="f-vendedor">${t("vendedor")}</label>
            <input id="f-vendedor" name="vendedor" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="${esc(t("vendedorEj"))}" value="${esc(leer("im_vendedor") || "")}">
            <div class="campo__error">${t("vendedorErr")}</div></div>
          <div class="trampa" aria-hidden="true"><label>No llenar <input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
          <button type="submit" class="btn btn--coral">${t("obtener")}</button>
          <p class="form__legal">${t("legal")}</p>
        </form>
      </div>`, c.color);
    activarGaleria(contenidoModal);
    prepararFormulario(c, base);
  }

  const nombrePais = (p) => ((PAISES_TRAD[lang()] || {})[p]) || p;
  const campoEstadoTexto = () => `<label for="f-estado">${t("estado")}</label>
    <input id="f-estado" name="estado" required maxlength="60" autocomplete="address-level1" placeholder="${esc(t("estadoEj"))}">
    <div class="campo__error">${t("estadoErr")}</div>`;

  function prepararFormulario(cupon, base) {
    const form = $("#form-canje");
    const personas = $("#f-personas", form);
    $$(".contador button", form).forEach((b) => b.addEventListener("click", () => {
      personas.value = Math.min(50, Math.max(1, (+personas.value || 1) + +b.dataset.paso));
    }));

    // País → si es México, el estado es una lista
    const pais = $("#f-pais", form);
    pais.addEventListener("change", () => {
      const campo = $("#campo-estado", form);
      const valor = ($("[name=estado]", campo) || {}).value || "";
      if (pais.value === "México") {
        campo.innerHTML = `<label for="f-estado">${t("estadoMx")}</label><select id="f-estado" name="estado" required autocomplete="address-level1"><option value="">${t("selecciona")}</option>${ESTADOS_MX.map((e) => `<option${e === valor ? " selected" : ""}>${esc(e)}</option>`).join("")}</select><div class="campo__error">${t("estadoErr")}</div>`;
      } else if (campo.querySelector("select")) {
        campo.innerHTML = campoEstadoTexto();
      }
    });

    form.addEventListener("input", (e) => {
      const campo = e.target.closest(".campo");
      if (campo) campo.classList.remove("error");
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const datos = Object.fromEntries(new FormData(form).entries());
      if (!validar(form, datos)) {
        const primero = $(".campo.error", form);
        if (primero) primero.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }
      const i = +datos.opcion || 0;
      const opcion = cupon.precios[i];
      const conv = convertir(opcion.promo);
      const boton = $("button[type=submit]", form);
      boton.disabled = true;
      boton.innerHTML = `<span class="cargando"></span> ${esc(t("generando"))}`;

      const envio = {
        cupon_id: cupon.id,
        cupon: base.titulo,                 // en español, para el panel
        opcion: base.precios[i].concepto,   // en español, para el panel
        cupon_cliente: cupon.titulo,        // en el idioma del cliente
        opcion_cliente: opcion.concepto,
        precio: opcion.promo,               // siempre en pesos
        idioma: lang(),
        moneda: conv.moneda,
        precio_moneda: opcion.promo === 0 ? 0 : conv.valor,
        nombre: datos.nombre.trim(),
        telefono: datos.telefono.trim(),
        correo: datos.correo.trim(),
        personas: +datos.personas,
        pais: datos.pais,
        estado: (datos.estado || "").trim(),
        fecha: datos.fecha,
        vendedor: (datos.vendedor || "").trim().toUpperCase(),
        sitio_web: datos.sitio_web || "",
      };

      let resp = null;
      try {
        const r = await fetch(API + "canjear.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(envio),
        });
        resp = await r.json();
        if (!r.ok || !resp.ok) throw new Error(resp && resp.error ? resp.error : "Error del servidor");
      } catch (err) {
        if (resp && resp.error) {
          if (resp.campo === "vendedor") {
            const c = $(".campo--vendedor", form);
            c.classList.add("error");
            c.scrollIntoView({ behavior: "smooth", block: "center" });
          } else if (resp.campo === "usado") {
            usados.add(cupon.id);
            pintarCupones();
            aviso(t("yaUsado"));
          } else {
            aviso(resp.error);
          }
          boton.disabled = false;
          boton.innerHTML = t("obtener");
          return;
        }
        // Sin servidor (por ejemplo, abriendo el archivo localmente): el cupón se genera aquí.
        resp = { ok: true, codigo: codigoLocal(), wallet: false, correo: false, sinServidor: true };
      }
      if (resp.token) recordarCanje(resp.codigo, resp.token, cupon.id);
      envio.vendedor = resp.vendedor || (resp.sinServidor ? envio.vendedor : "");
      mostrarExito(cupon, envio, resp);
    });
  }

  function validar(form, d) {
    let ok = true;
    const marcar = (nombre, malo) => {
      const el = $(`[name=${nombre}]`, form);
      if (!el) return;
      el.closest(".campo").classList.toggle("error", malo);
      if (malo) ok = false;
    };
    marcar("nombre", (d.nombre || "").trim().length < 2);
    marcar("telefono", (d.telefono || "").replace(/\D/g, "").length < 8);
    marcar("correo", !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((d.correo || "").trim()));
    marcar("personas", !(+d.personas >= 1 && +d.personas <= 50));
    marcar("fecha", !d.fecha || d.fecha < hoyISO());
    marcar("pais", !d.pais);
    marcar("estado", (d.estado || "").trim().length < 2);
    return ok;
  }

  function codigoLocal() {
    const letras = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    const arr = new Uint32Array(6);
    (window.crypto || window.msCrypto).getRandomValues(arr);
    return "IM-" + Array.from(arr, (n) => letras[n % letras.length]).join("");
  }

  function fechaLarga(iso, locale) {
    const [a, m, d] = iso.split("-").map(Number);
    return new Date(a, m - 1, d).toLocaleDateString(locale || info().locale, { weekday: "long", day: "numeric", month: "long", year: "numeric" });
  }

  // Precio en la moneda del cliente y, si no es español, su equivalente en pesos
  function precioDoble(d) {
    if (d.precio === 0) return t("gratis");
    if (d.moneda === "MXN") return pesos(d.precio);
    return `${formatoMoneda(d.precio_moneda, d.moneda)} (${pesos(d.precio)})`;
  }

  const ETIQUETAS_WA = {
    en: { cupon: "Coupon", codigo: "Code", nombre: "Name", tel: "Phone", correo: "Email", pais: "Country", idioma: "Language" },
    pt: { cupon: "Cupom", codigo: "Código", nombre: "Nome", tel: "Telefone", correo: "E-mail", pais: "País", idioma: "Idioma" },
    zh: { cupon: "优惠券", codigo: "代码", nombre: "姓名", tel: "电话", correo: "邮箱", pais: "国家", idioma: "语言" },
  };

  // El mensaje llega al negocio: etiquetas en español y, si el cliente usa otro idioma, también en el suyo
  function mensajeWhatsApp(cupon, d, codigo) {
    const ex = ETIQUETAS_WA[lang()];
    const L = (es, clave, claveT) => (ex ? `${ex[clave] || t(claveT)} / ${es}` : es);
    return [
      t("waCanje") + (ex ? "\n¡Hola! Quiero canjear mi cupón" : ""),
      "",
      `*${L("Cupón", "cupon")}:* ${d.cupon}${ex ? ` (${cupon.titulo})` : ""}`,
      `*${L("Opción", "", "cOpcion")}:* ${d.opcion}`,
      `*${L("Precio", "", "cPrecio")}:* ${precioDoble(d)}`,
      `*${L("Código", "codigo")}:* ${codigo}`,
      `*${L("Fecha", "", "cFecha")}:* ${fechaLarga(d.fecha, "es-MX")}`,
      `*${L("Personas", "", "cPersonas")}:* ${d.personas}`,
      "",
      `*${L("Nombre", "nombre")}:* ${d.nombre}`,
      `*${L("Teléfono", "tel")}:* ${d.telefono}`,
      `*${L("Correo", "correo")}:* ${d.correo}`,
      `*${L("País / Estado", "pais")}:* ${d.pais}, ${d.estado}`,
      ex ? `*${L("Idioma", "idioma")}:* ${info().nombre}` : "",
      d.vendedor ? `*Vendedor:* ${d.vendedor}` : "",
    ].filter((x, i, arr) => x !== "" || (arr[i - 1] !== "" && i < arr.length - 1)).join("\n");
  }

  /* ---------------------------------------------------------------- Éxito */
  function mostrarExito(cupon, d, resp) {
    const codigo = resp.codigo;
    const wa = enlaceWhatsApp(mensajeWhatsApp(cupon, d, codigo));
    const wallet = resp.wallet
      ? `<a class="btn btn--wallet" href="${API}wallet.php?codigo=${encodeURIComponent(codigo)}&t=${encodeURIComponent(resp.token || "")}" target="_blank" rel="noopener">${t("wallet")}</a>`
      : `<button type="button" class="btn btn--wallet" disabled title="${esc(t("walletPronto"))}">${t("wallet")}</button>`;
    const notaCorreo = resp.correo ? t("notaCorreo", { correo: esc(d.correo) }) : t("notaGuardar");
    const precioCliente = d.precio === 0 ? esc(t("gratis")) : esc(formatoMoneda(d.precio_moneda, d.moneda));
    const precioPesos = d.precio > 0 && d.moneda !== "MXN" ? `<small class="exito__pesos">= ${esc(pesos(d.precio))}</small>` : "";

    contenidoModal.innerHTML = `
      <div class="exito">
        <div class="exito__icono">✓</div>
        <h2 id="modal-titulo">${esc(t("listo", { nombre: d.nombre.split(" ")[0] }))}</h2>
        <p>${esc(cupon.titulo)} · ${esc(fechaLarga(d.fecha))}</p>
        <div class="exito__precio"><span>${esc(d.opcion_cliente)}</span><b>${precioCliente}</b>${precioPesos}</div>
        <div class="exito__codigo">${esc(codigo)}</div>
        <div class="exito__cupon" id="vista-cupon"></div>
        <div class="paso"><b>1</b> ${t("paso1")}</div>
        <div class="acciones">
          <a class="btn btn--wa" href="${wa}" target="_blank" rel="noopener" id="btn-wa">${ICONO_WA} ${t("enviarWa")}</a>
        </div>
        <div class="paso"><b>2</b> ${t("paso2")}</div>
        <div class="acciones acciones--2">
          <button type="button" class="btn btn--primario" id="btn-descargar">${t("descargar")}</button>
          ${wallet}
        </div>
        <p class="exito__nota">${notaCorreo}${resp.sinServidor ? `<br><small>${t("sinServidor")}</small>` : ""}</p>
      </div>`;
    contenidoModal.scrollTop = 0;

    dibujarCupon(cupon, d, codigo).then((canvas) => {
      $("#vista-cupon").appendChild(canvas);
      $("#btn-descargar").addEventListener("click", () => descargarCanvas(canvas, `cupon-${codigo}.png`));
    });
  }

  function descargarCanvas(canvas, nombre) {
    canvas.toBlob((blob) => {
      if (!blob) return aviso(t("errorImagen"));
      const archivo = new File([blob], nombre, { type: "image/png" });
      // En celulares que lo permiten, abre "Compartir / Guardar imagen"
      if (navigator.canShare && navigator.canShare({ files: [archivo] }) && /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
        navigator.share({ files: [archivo], title: "Mi cupón Isla Mujeres" }).catch(() => {});
        return;
      }
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url; a.download = nombre;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 4000);
      aviso(t("descargado"));
    }, "image/png");
  }

  /* Dibuja el cupón como imagen 1080 x 1500 (lista para mostrar en taquilla) */
  async function dibujarCupon(cupon, d, codigo) {
    if (document.fonts && document.fonts.ready) { try { await document.fonts.ready; } catch (e) { /* sin fuentes */ } }
    const W = 1080, H = 1660;
    const cv = document.createElement("canvas");
    cv.width = W; cv.height = H;
    const g = cv.getContext("2d");
    const titulo = '"Baloo 2", "Nunito", system-ui, sans-serif';
    const texto = '"Nunito", system-ui, sans-serif';

    const redondo = (x, y, w, h, r) => { g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w, y, x + w, y + h, r); g.arcTo(x + w, y + h, x, y + h, r); g.arcTo(x, y + h, x, y, r); g.arcTo(x, y, x + w, y, r); g.closePath(); };

    // Fondo
    const fondo = g.createLinearGradient(0, 0, W, H);
    fondo.addColorStop(0, "#0bb5b5"); fondo.addColorStop(1, "#0a6e8a");
    g.fillStyle = fondo; g.fillRect(0, 0, W, H);
    g.fillStyle = "rgba(255,255,255,.07)";
    for (let i = 0; i < 6; i++) { g.beginPath(); g.arc(W - 80 + i * 10, 120 + i * 260, 160 - i * 12, 0, Math.PI * 2); g.fill(); }

    // Tarjeta
    const X = 60, Y = 60, CW = W - 120, CH = H - 120;
    g.save(); g.shadowColor = "rgba(0,0,0,.25)"; g.shadowBlur = 40; g.shadowOffsetY = 16;
    redondo(X, Y, CW, CH, 48); g.fillStyle = "#fff"; g.fill(); g.restore();

    // Cabecera de color
    g.save(); redondo(X, Y, CW, CH, 48); g.clip();
    g.fillStyle = cupon.color; g.fillRect(X, Y, CW, 420);
    g.fillStyle = "rgba(255,255,255,.15)";
    g.beginPath(); g.arc(X + CW - 60, Y + 60, 200, 0, Math.PI * 2); g.fill();
    g.restore();

    g.fillStyle = "#fff"; g.textBaseline = "alphabetic";
    g.font = `800 40px ${texto}`; g.fillText(CONFIG_SITIO.nombre.toUpperCase(), X + 60, Y + 90);
    // Título: si no cabe en una línea, se reduce y se acomoda en dos (y el icono se hace más chico)
    let fs = 74;
    g.font = `800 ${fs}px ${titulo}`;
    while (g.measureText(cupon.titulo).width > (CW - 120) * 1.8 && fs > 44) { fs -= 4; g.font = `800 ${fs}px ${titulo}`; }
    const dosLineas = g.measureText(cupon.titulo).width > CW - 120;
    g.font = dosLineas ? "84px serif" : "120px serif";
    g.fillText(cupon.icono, X + 54, dosLineas ? Y + 210 : Y + 250);
    g.font = `800 ${fs}px ${titulo}`;
    ajustarTexto(g, cupon.titulo, X + 60, dosLineas ? Y + 390 - fs * 1.1 : Y + 360, CW - 120, fs);

    // Etiqueta de descuento
    g.save(); g.translate(X + CW - 190, Y + 200); g.rotate(0.08);
    redondo(-110, -60, 220, 120, 60); g.fillStyle = "#ffc93c"; g.fill();
    g.fillStyle = "#12324a"; g.font = `800 ${cupon.etiqueta.length > 6 ? 34 : 56}px ${titulo}`; g.textAlign = "center"; g.textBaseline = "middle";
    g.fillText(cupon.etiqueta, 0, 4); g.restore();
    g.textAlign = "left"; g.textBaseline = "alphabetic";

    // Muescas tipo boleto
    const yCorte = Y + 1100;
    g.fillStyle = "#0a8a9a";
    [X, X + CW].forEach((cx) => { g.beginPath(); g.arc(cx, yCorte, 36, 0, Math.PI * 2); g.fill(); });
    g.strokeStyle = "#d8e3ea"; g.lineWidth = 4; g.setLineDash([18, 14]);
    g.beginPath(); g.moveTo(X + 60, yCorte); g.lineTo(X + CW - 60, yCorte); g.stroke(); g.setLineDash([]);

    // Datos
    const filas = [
      [t("cCliente"), d.nombre],
      [t("cFecha"), fechaLarga(d.fecha)],
      [t("cPersonas"), String(d.personas)],
      [t("cOpcion"), d.opcion_cliente],
      [t("cPrecio"), precioDoble(d)],
      [t("cOrigen"), `${d.estado}, ${nombrePais(d.pais)}`],
    ];
    let y = Y + 500;
    filas.forEach(([k, v]) => {
      g.fillStyle = "#4f6b80"; g.font = `700 30px ${texto}`; g.fillText(k.toUpperCase(), X + 60, y);
      g.fillStyle = "#12324a"; g.font = `800 42px ${texto}`; ajustarTexto(g, v, X + 60, y + 50, CW - 120, 42, 1);
      y += 98;
    });

    // QR + código
    const qrTam = 300, qx = X + 60, qy = yCorte + 60;
    dibujarQR(g, codigo, qx, qy, qrTam);
    g.fillStyle = "#4f6b80"; g.font = `700 30px ${texto}`; g.fillText(t("cCodigo"), qx + qrTam + 50, qy + 70);
    g.fillStyle = "#12324a"; g.font = `800 64px ui-monospace, Menlo, monospace`; g.fillText(codigo, qx + qrTam + 50, qy + 145);
    g.fillStyle = "#4f6b80"; g.font = `600 30px ${texto}`;
    ajustarTexto(g, t("cPresenta"), qx + qrTam + 50, qy + 210, CW - qrTam - 170, 38, 3);

    return cv;
  }

  function ajustarTexto(g, txt, x, y, ancho, alto, maxLineas = 2) {
    const palabras = String(txt).split(" ");
    let linea = "", n = 0;
    for (let i = 0; i < palabras.length; i++) {
      const prueba = linea ? linea + " " + palabras[i] : palabras[i];
      if (g.measureText(prueba).width > ancho && linea) {
        if (n === maxLineas - 1) { g.fillText(recortar(g, linea + " " + palabras.slice(i).join(" "), ancho), x, y + n * alto * 1.1); return; }
        g.fillText(linea, x, y + n * alto * 1.1); n++; linea = palabras[i];
      } else linea = prueba;
    }
    g.fillText(recortar(g, linea, ancho), x, y + n * alto * 1.1);
  }
  function recortar(g, t, ancho) {
    if (g.measureText(t).width <= ancho) return t;
    while (t.length > 1 && g.measureText(t + "…").width > ancho) t = t.slice(0, -1);
    return t + "…";
  }

  function dibujarQR(g, valor, x, y, tam) {
    if (typeof qrcode !== "function") return;
    const qr = qrcode(0, "M");
    qr.addData(valor); qr.make();
    const n = qr.getModuleCount(), celda = tam / n;
    g.fillStyle = "#fff"; g.fillRect(x - 10, y - 10, tam + 20, tam + 20);
    g.fillStyle = "#12324a";
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
      if (qr.isDark(r, c)) g.fillRect(x + c * celda, y + r * celda, Math.ceil(celda), Math.ceil(celda));
    }
  }

  /* ---------------------------------------------------------------- Inicio */
  // Textos fijos del HTML (data-t = contenido, data-t-aria = etiqueta accesible)
  function aplicarTextos() {
    document.documentElement.lang = lang() === "zh" ? "zh-CN" : lang();
    $$("[data-t]").forEach((el) => { el.innerHTML = t(el.dataset.t); });
    $$("[data-t-aria]").forEach((el) => { el.setAttribute("aria-label", t(el.dataset.tAria)); el.title = t(el.dataset.tAria); });
    $("#btn-idioma-txt").textContent = info().bandera + " " + lang().toUpperCase();
    $("#wa-header").href = enlaceWhatsApp(t("waHeader"));
    if (mapa) {
      const cred = mapa.attributionControl.getContainer();
      cred.title = t("creditos");
    }
  }

  function pintarTodo() {
    aplicarTextos();
    iniciarChips();
    iniciarCupones();
    if (mapa) PUNTOS.forEach((p) => {
      marcadores[p.id].setIcon(iconoPunto(p));
      const el = marcadores[p.id].getElement();
      if (el) el.title = tradPunto(p).nombre;
    });
  }

  /* Selector de idioma: aparece la primera vez y con el botón 🌐 */
  const modalIdioma = $("#modal-idioma");
  function abrirIdiomas() {
    $("#idiomas-lista").innerHTML = Object.entries(IDIOMAS).map(([k, v]) =>
      `<button type="button" class="idioma ${k === idioma ? "activo" : ""}" data-idioma="${k}" lang="${v.locale}">
        <span class="idioma__bandera">${v.bandera}</span><span class="idioma__nombre">${v.nombre}</span><span class="idioma__moneda">${v.moneda}</span></button>`).join("");
    $("#idioma-titulo").textContent = t("elegirIdioma");
    $("#idioma-sub").textContent = t("elegirIdiomaSub");
    modalIdioma.classList.add("abierto");
    modalIdioma.setAttribute("aria-hidden", "false");
    document.body.classList.add("sin-scroll");
    setTimeout(() => { const b = $(".idioma", modalIdioma); if (b) b.focus(); }, 50);
  }
  function elegirIdioma(k) {
    idioma = k;
    guardar("im_idioma", k);
    modalIdioma.classList.remove("abierto");
    modalIdioma.setAttribute("aria-hidden", "true");
    if (!modal.classList.contains("abierto")) document.body.classList.remove("sin-scroll");
    else cerrarModal();
    pintarTodo();
  }
  modalIdioma.addEventListener("click", (e) => {
    const b = e.target.closest("[data-idioma]");
    if (b) elegirIdioma(b.dataset.idioma);
    else if (e.target.closest("[data-cerrar-idioma]") && idioma) elegirIdioma(idioma);
  });

  // Tipo de cambio en línea (si falla se usan los valores de respaldo de idiomas.js)
  function cargarTipoCambio() {
    fetch(API + "tipos.php").then((r) => r.json()).then((j) => {
      if (!j || !j.ok || !j.tasas) return;
      ["USD", "BRL", "CNY"].forEach((m) => { if (j.tasas[m] > 0) tasas[m] = j.tasas[m]; });
      if (lang() !== "es" && !modal.classList.contains("abierto")) iniciarCupones();
    }).catch(() => {});
  }

  // Liga de vendedor: ?vendedor=ABC123 se recuerda para llenar el campo solo
  const vParam = new URLSearchParams(location.search).get("vendedor");
  if (vParam && /^[A-Za-z0-9]{3,12}$/.test(vParam)) guardar("im_vendedor", vParam.toUpperCase());

  document.addEventListener("DOMContentLoaded", () => {
    iniciarMapa();
    pintarTodo();
    cargarTipoCambio();
    revisarUsados();
    $("#btn-idioma").addEventListener("click", abrirIdiomas);
    // Abrir un cupón directo con #cupon-catamaran
    const h = location.hash.match(/^#cupon-(.+)$/);
    if (!idioma) abrirIdiomas();
    else if (h) abrirCupon(h[1]);
  });
})();
