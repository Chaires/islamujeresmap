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

  const dinero = new Intl.NumberFormat("es-MX", { style: "currency", currency: CONFIG_SITIO.moneda, maximumFractionDigits: 0 });
  const precioTxt = (n) => (n === 0 ? "GRATIS" : dinero.format(n));
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

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
      return '<div class="video video--vacio"><span>🎬</span>Aquí va el video de Instagram.<br><small>Pega la liga del reel en datos.js</small></div>';
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
    L.control.zoom({ position: "bottomleft" }).addTo(mapa);

    capas.satelite = L.layerGroup([
      L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
        maxZoom: 18, attribution: "Imágenes © Esri, Maxar, Earthstar Geographics",
      }),
      L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png", {
        maxZoom: 18, subdomains: "abcd", attribution: "© OpenStreetMap · © CARTO",
      }),
    ]);
    capas.calles = L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", {
      maxZoom: 18, subdomains: "abcd", attribution: "© OpenStreetMap · © CARTO",
    });
    capas.satelite.addTo(mapa);

    $$(".mapa-capas button").forEach((b) => b.addEventListener("click", () => {
      $$(".mapa-capas button").forEach((x) => x.classList.toggle("activo", x === b));
      Object.values(capas).forEach((c) => mapa.removeLayer(c));
      capas[b.dataset.capa].addTo(mapa);
    }));

    PUNTOS.forEach((p) => {
      const icono = L.divIcon({
        className: "pin",
        html: `<div class="pin__cuerpo" style="--c:${p.color}"><span>${p.icono}</span></div><div class="pin__pulso" style="--c:${p.color}"></div><div class="pin__nombre">${esc(p.nombre)}</div>`,
        iconSize: [46, 46],
        iconAnchor: [23, 52],
      });
      const m = L.marker(p.coords, { icon: icono, title: p.nombre, riseOnHover: true, keyboard: true }).addTo(mapa);
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

  function resaltarMarcador(id) {
    Object.entries(marcadores).forEach(([k, m]) => {
      const el = m.getElement();
      if (el) el.classList.toggle("activo", k === id);
    });
  }

  function iniciarChips() {
    $("#chips-puntos").innerHTML = PUNTOS.map((p) =>
      `<button type="button" class="chip" role="listitem" data-id="${p.id}"><span class="punto" style="background:${p.color}"></span>${p.icono} ${esc(p.nombre)}</button>`).join("");
    $$("#chips-puntos .chip").forEach((b) => b.addEventListener("click", () => {
      const p = PUNTOS.find((x) => x.id === b.dataset.id);
      $("#mapa").scrollIntoView({ behavior: "smooth", block: "center" });
      mapa.flyTo(p.coords, 16, { duration: 1 });
      resaltarMarcador(p.id);
      setTimeout(() => abrirPunto(p.id), 900);
    }));
  }

  function abrirPunto(id) {
    const p = PUNTOS.find((x) => x.id === id);
    if (!p) return;
    resaltarMarcador(id);
    const destino = p.coords.join(",");
    const ruta = `https://www.google.com/maps/dir/?api=1&destination=${destino}&travelmode=driving`;
    const cuponDe = p.cupon ? CUPONES.find((c) => c.id === p.cupon) : null;
    abrirModal(`
      ${htmlGaleria(p.fotos, p.nombre)}
      <div class="ficha">
        <span class="ficha__tipo">${p.icono} ${esc(p.tipo)}</span>
        <h2 id="modal-titulo">${esc(p.nombre)}</h2>
        <p>${esc(p.descripcion)}</p>
        <ul class="lista-check">${p.datos.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>
        <h3>🎬 Míralo en video</h3>
        ${htmlInstagram(p.instagram)}
        <div class="acciones acciones--2">
          <a class="btn btn--primario" href="${ruta}" target="_blank" rel="noopener">🧭 Trazar ruta</a>
          <button type="button" class="btn btn--claro" data-ver-mapa>📍 Ver en el mapa</button>
        </div>
        <div class="acciones">
          ${cuponDe ? `<button type="button" class="btn btn--coral" data-ver-cupon>🎟️ ${esc(cuponDe.titulo)} · ${precioTxt(cuponDe.precios[0].promo)}</button>` : ""}
          <a class="btn btn--wa" href="${enlaceWhatsApp(`¡Hola! Quiero información sobre ${p.nombre} 🌴`)}" target="_blank" rel="noopener">${ICONO_WA} Pregunta por WhatsApp</a>
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
  let filtroActual = "Todos";

  function iniciarCupones() {
    const categorias = ["Todos", ...new Set(CUPONES.map((c) => c.categoria))];
    $("#filtros").innerHTML = categorias.map((c) =>
      `<button type="button" role="tab" class="filtro ${c === filtroActual ? "activo" : ""}" data-cat="${esc(c)}">${esc(c)}</button>`).join("");
    $$("#filtros .filtro").forEach((b) => b.addEventListener("click", () => {
      filtroActual = b.dataset.cat;
      $$("#filtros .filtro").forEach((x) => x.classList.toggle("activo", x === b));
      pintarCupones();
    }));
    pintarCupones();
  }

  function pintarCupones() {
    const lista = CUPONES.filter((c) => filtroActual === "Todos" || c.categoria === filtroActual);
    $("#lista-cupones").innerHTML = lista.map((c, i) => {
      const p = c.precios[0];
      return `<button type="button" class="cupon" data-id="${c.id}" style="--c:${c.color}; animation-delay:${i * 60}ms">
        <div class="cupon__foto"><img src="${RUTA_IMG + esc(c.fotos[0])}" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
          <span class="cupon__etiqueta">${esc(c.etiqueta)}</span><span class="cupon__icono">${c.icono}</span></div>
        <div class="cupon__cuerpo">
          <span class="cupon__cat">${esc(c.categoria)}</span>
          <h3>${esc(c.titulo)}</h3>
          <p>${esc(c.resumen)}</p>
          <div class="cupon__precio">${p.normal > p.promo ? `<span class="antes">${precioTxt(p.normal)}</span>` : ""}<span class="ahora">${c.precios.length > 1 ? "desde " : ""}${precioTxt(p.promo)}</span></div>
          <span class="cupon__cta">Canjear cupón →</span>
        </div></button>`;
    }).join("");
    $$("#lista-cupones .cupon").forEach((b) => b.addEventListener("click", () => abrirCupon(b.dataset.id)));
  }

  function hoyISO() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
  }

  function abrirCupon(id) {
    const c = CUPONES.find((x) => x.id === id);
    if (!c) return;
    const filasPrecio = c.precios.map((p) =>
      `<tr><td>${esc(p.concepto)}</td><td>${p.normal > p.promo ? `<span class="antes">${precioTxt(p.normal)}</span>` : ""}<span class="ahora">${precioTxt(p.promo)}</span></td></tr>`).join("");
    const opciones = c.precios.length > 1 ? `
      <div class="campo"><label for="f-opcion">Opción</label>
        <select id="f-opcion" name="opcion">${c.precios.map((p, i) => `<option value="${i}">${esc(p.concepto)} · ${precioTxt(p.promo)}</option>`).join("")}</select></div>` : "";

    abrirModal(`
      ${htmlGaleria(c.fotos, c.titulo)}
      <div class="ficha">
        <span class="ficha__tipo">${c.icono} ${esc(c.categoria)} · ${esc(c.etiqueta)}</span>
        <h2 id="modal-titulo">${esc(c.titulo)}</h2>
        <p>${esc(c.descripcion)}</p>
        <h3>✅ Qué incluye</h3>
        <ul class="lista-check">${c.incluye.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>
        <h3>💰 Precios</h3>
        <table class="precios"><tbody>${filasPrecio}</tbody></table>
        <h3>🎬 Míralo en video</h3>
        ${htmlInstagram(c.instagram)}
        <h3 id="canjear">🎟️ Canjea tu cupón</h3>
        <form class="form" id="form-canje" novalidate>
          ${opciones}
          <div class="campo"><label for="f-nombre">Nombre completo</label>
            <input id="f-nombre" name="nombre" autocomplete="name" required maxlength="80" placeholder="Ej. Ana López">
            <div class="campo__error">Escribe tu nombre.</div></div>
          <div class="form__fila">
            <div class="campo"><label for="f-tel">Teléfono (WhatsApp)</label>
              <input id="f-tel" name="telefono" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" placeholder="Ej. 998 123 4567">
              <div class="campo__error">Escribe un teléfono válido.</div></div>
            <div class="campo"><label for="f-correo">Correo</label>
              <input id="f-correo" name="correo" type="email" inputmode="email" autocomplete="email" required maxlength="120" placeholder="tu@correo.com">
              <div class="campo__error">Escribe un correo válido.</div></div>
          </div>
          <div class="form__fila">
            <div class="campo"><label for="f-personas">Número de personas</label>
              <div class="contador"><button type="button" data-paso="-1" aria-label="Menos">−</button>
                <input id="f-personas" name="personas" type="number" inputmode="numeric" min="1" max="50" value="2" required>
                <button type="button" data-paso="1" aria-label="Más">+</button></div>
              <div class="campo__error">Entre 1 y 50 personas.</div></div>
            <div class="campo"><label for="f-fecha">Fecha para usarlo</label>
              <input id="f-fecha" name="fecha" type="date" required min="${hoyISO()}">
              <div class="campo__error">Elige una fecha de hoy en adelante.</div></div>
          </div>
          <div class="form__fila">
            <div class="campo"><label for="f-pais">País</label>
              <select id="f-pais" name="pais" required autocomplete="country-name">
                <option value="">Selecciona…</option>${PAISES.map((p) => `<option>${esc(p)}</option>`).join("")}</select>
              <div class="campo__error">Selecciona tu país.</div></div>
            <div class="campo" id="campo-estado"><label for="f-estado">Estado / Provincia</label>
              <input id="f-estado" name="estado" required maxlength="60" autocomplete="address-level1" placeholder="Ej. Texas">
              <div class="campo__error">Escribe tu estado.</div></div>
          </div>
          <div class="trampa" aria-hidden="true"><label>No llenar <input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
          <button type="submit" class="btn btn--coral">🎟️ Obtener mi cupón</button>
          <p class="form__legal">Al canjear aceptas que te contactemos por WhatsApp o correo para confirmar tu reserva.</p>
        </form>
      </div>`, c.color);
    activarGaleria(contenidoModal);
    prepararFormulario(c);
  }

  function prepararFormulario(cupon) {
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
      const err = '<div class="campo__error">Selecciona tu estado.</div>';
      if (pais.value === "México") {
        campo.innerHTML = `<label for="f-estado">Estado</label><select id="f-estado" name="estado" required autocomplete="address-level1"><option value="">Selecciona…</option>${ESTADOS_MX.map((e) => `<option${e === valor ? " selected" : ""}>${esc(e)}</option>`).join("")}</select>${err}`;
      } else if (campo.querySelector("select")) {
        campo.innerHTML = `<label for="f-estado">Estado / Provincia</label><input id="f-estado" name="estado" required maxlength="60" autocomplete="address-level1" placeholder="Ej. Texas"><div class="campo__error">Escribe tu estado.</div>`;
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
      const opcion = cupon.precios[+datos.opcion || 0];
      const boton = $("button[type=submit]", form);
      boton.disabled = true;
      boton.innerHTML = '<span class="cargando"></span> Generando cupón…';

      const envio = {
        cupon_id: cupon.id,
        cupon: cupon.titulo,
        opcion: opcion.concepto,
        precio: opcion.promo,
        nombre: datos.nombre.trim(),
        telefono: datos.telefono.trim(),
        correo: datos.correo.trim(),
        personas: +datos.personas,
        pais: datos.pais,
        estado: (datos.estado || "").trim(),
        fecha: datos.fecha,
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
          aviso(resp.error);
          boton.disabled = false;
          boton.innerHTML = "🎟️ Obtener mi cupón";
          return;
        }
        // Sin servidor (por ejemplo, abriendo el archivo localmente): el cupón se genera aquí.
        resp = { ok: true, codigo: codigoLocal(), wallet: false, correo: false, sinServidor: true };
      }
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

  function fechaLarga(iso) {
    const [a, m, d] = iso.split("-").map(Number);
    return new Date(a, m - 1, d).toLocaleDateString("es-MX", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
  }

  function mensajeWhatsApp(cupon, d, codigo) {
    return [
      "¡Hola! Quiero canjear mi cupón 🎟️🌴",
      "",
      `*Cupón:* ${cupon.titulo} (${cupon.etiqueta})`,
      `*Opción:* ${d.opcion} · ${precioTxt(d.precio)}`,
      `*Código:* ${codigo}`,
      `*Fecha:* ${fechaLarga(d.fecha)}`,
      `*Personas:* ${d.personas}`,
      "",
      `*Nombre:* ${d.nombre}`,
      `*Teléfono:* ${d.telefono}`,
      `*Correo:* ${d.correo}`,
      `*País / Estado:* ${d.pais}, ${d.estado}`,
    ].join("\n");
  }

  /* ---------------------------------------------------------------- Éxito */
  function mostrarExito(cupon, d, resp) {
    const codigo = resp.codigo;
    const wa = enlaceWhatsApp(mensajeWhatsApp(cupon, d, codigo));
    const wallet = resp.wallet
      ? `<a class="btn btn--wallet" href="${API}wallet.php?codigo=${encodeURIComponent(codigo)}&t=${encodeURIComponent(resp.token || "")}" target="_blank" rel="noopener">Agregar a Google Wallet</a>`
      : `<button type="button" class="btn btn--wallet" disabled title="Disponible cuando el sitio esté en el hosting">Agregar a Google Wallet</button>`;
    const notaCorreo = resp.correo
      ? `📧 También enviamos la confirmación a <b>${esc(d.correo)}</b>.`
      : "Guarda tu cupón: descárgalo o agrégalo a tu Google Wallet.";

    contenidoModal.innerHTML = `
      <div class="exito">
        <div class="exito__icono">✓</div>
        <h2 id="modal-titulo">¡Cupón listo, ${esc(d.nombre.split(" ")[0])}!</h2>
        <p>${esc(cupon.titulo)} · ${esc(fechaLarga(d.fecha))}</p>
        <div class="exito__codigo">${esc(codigo)}</div>
        <div class="exito__cupon" id="vista-cupon"></div>
        <div class="paso"><b>1</b> Confirma tu reserva</div>
        <div class="acciones">
          <a class="btn btn--wa" href="${wa}" target="_blank" rel="noopener" id="btn-wa">${ICONO_WA} Enviar confirmación por WhatsApp</a>
        </div>
        <div class="paso"><b>2</b> Guarda tu cupón</div>
        <div class="acciones acciones--2">
          <button type="button" class="btn btn--primario" id="btn-descargar">⬇️ Descargar imagen</button>
          ${wallet}
        </div>
        <p class="exito__nota">${notaCorreo}${resp.sinServidor ? "<br><small>(Vista de prueba sin servidor: el botón de Google Wallet se activa en el hosting.)</small>" : ""}</p>
      </div>`;
    contenidoModal.scrollTop = 0;

    dibujarCupon(cupon, d, codigo).then((canvas) => {
      $("#vista-cupon").appendChild(canvas);
      $("#btn-descargar").addEventListener("click", () => descargarCanvas(canvas, `cupon-${codigo}.png`));
    });
  }

  function descargarCanvas(canvas, nombre) {
    canvas.toBlob((blob) => {
      if (!blob) return aviso("No se pudo crear la imagen.");
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
      aviso("Cupón descargado ✅");
    }, "image/png");
  }

  /* Dibuja el cupón como imagen 1080 x 1500 (lista para mostrar en taquilla) */
  async function dibujarCupon(cupon, d, codigo) {
    if (document.fonts && document.fonts.ready) { try { await document.fonts.ready; } catch (e) { /* sin fuentes */ } }
    const W = 1080, H = 1560;
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
    g.font = "120px serif"; g.fillText(cupon.icono, X + 54, Y + 250);
    g.font = `800 74px ${titulo}`;
    ajustarTexto(g, cupon.titulo, X + 60, Y + 360, CW - 120, 74);

    // Etiqueta de descuento
    g.save(); g.translate(X + CW - 190, Y + 200); g.rotate(0.08);
    redondo(-110, -60, 220, 120, 60); g.fillStyle = "#ffc93c"; g.fill();
    g.fillStyle = "#12324a"; g.font = `800 ${cupon.etiqueta.length > 6 ? 34 : 56}px ${titulo}`; g.textAlign = "center"; g.textBaseline = "middle";
    g.fillText(cupon.etiqueta, 0, 4); g.restore();
    g.textAlign = "left"; g.textBaseline = "alphabetic";

    // Muescas tipo boleto
    const yCorte = Y + 1000;
    g.fillStyle = "#0a8a9a";
    [X, X + CW].forEach((cx) => { g.beginPath(); g.arc(cx, yCorte, 36, 0, Math.PI * 2); g.fill(); });
    g.strokeStyle = "#d8e3ea"; g.lineWidth = 4; g.setLineDash([18, 14]);
    g.beginPath(); g.moveTo(X + 60, yCorte); g.lineTo(X + CW - 60, yCorte); g.stroke(); g.setLineDash([]);

    // Datos
    const filas = [
      ["Cliente", d.nombre],
      ["Fecha", fechaLarga(d.fecha)],
      ["Personas", String(d.personas)],
      ["Opción", `${d.opcion} · ${precioTxt(d.precio)}`],
      ["Origen", `${d.estado}, ${d.pais}`],
    ];
    let y = Y + 500;
    filas.forEach(([k, v]) => {
      g.fillStyle = "#4f6b80"; g.font = `700 30px ${texto}`; g.fillText(k.toUpperCase(), X + 60, y);
      g.fillStyle = "#12324a"; g.font = `800 42px ${texto}`; ajustarTexto(g, v, X + 60, y + 50, CW - 120, 42, 1);
      y += 100;
    });

    // QR + código
    const qrTam = 300, qx = X + 60, qy = yCorte + 60;
    dibujarQR(g, codigo, qx, qy, qrTam);
    g.fillStyle = "#4f6b80"; g.font = `700 30px ${texto}`; g.fillText("CÓDIGO DE CUPÓN", qx + qrTam + 50, qy + 70);
    g.fillStyle = "#12324a"; g.font = `800 64px ui-monospace, Menlo, monospace`; g.fillText(codigo, qx + qrTam + 50, qy + 145);
    g.fillStyle = "#4f6b80"; g.font = `600 30px ${texto}`;
    ajustarTexto(g, "Presenta este cupón en taquilla o en el negocio.", qx + qrTam + 50, qy + 210, CW - qrTam - 170, 38, 3);

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
  document.addEventListener("DOMContentLoaded", () => {
    $("#wa-header").href = enlaceWhatsApp("¡Hola! Vi Isla Mujeres Pass y quiero información 🌴");
    iniciarMapa();
    iniciarChips();
    iniciarCupones();
    // Abrir un cupón directo con #cupon-catamaran
    const h = location.hash.match(/^#cupon-(.+)$/);
    if (h) abrirCupon(h[1]);
  });
})();
