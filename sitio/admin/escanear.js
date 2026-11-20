/* Escáner de cupones: cámara → código → ficha → validar. Si no hay internet, guarda la validación y la manda al volver. */
(() => {
  "use strict";
  const $ = (s) => document.querySelector(s);
  const raiz = $(".escaner");
  const csrf = raiz.dataset.csrf;
  const video = $("#video");
  const res = $("#resultado");
  const COLA = "im_cola_validar";
  let flujo = null, detector = null, lienzo = null, ultimo = "", buscando = false;

  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const dinero = (n) => "$" + Number(n).toLocaleString("es-MX");
  const leerCola = () => { try { return JSON.parse(localStorage.getItem(COLA) || "[]"); } catch (e) { return []; } };
  const guardarCola = (l) => { try { localStorage.setItem(COLA, JSON.stringify(l)); } catch (e) {} };
  const vibrar = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) {} };

  function normalizar(t) {
    const m = String(t).match(/codigo=([A-Za-z0-9-]+)/);
    if (m) t = m[1];
    t = String(t).toUpperCase().replace(/[^A-Z0-9]/g, "");
    if (t.startsWith("IM")) t = t.slice(2);
    return t ? "IM-" + t : "";
  }

  // ---------- Cámara
  async function abrirCamara() {
    try {
      flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
    } catch (e) {
      res.innerHTML = `<div class="flash flash--error">No se pudo abrir la cámara. Revisa que le diste permiso al navegador (la página debe abrirse con https). También puedes escribir el código.</div>`;
      return;
    }
    video.srcObject = flujo;
    await video.play();
    $("#camara").classList.add("activa");
    if ("BarcodeDetector" in window) {
      try { detector = new BarcodeDetector({ formats: ["qr_code"] }); } catch (e) { detector = null; }
    }
    lienzo = document.createElement("canvas");
    requestAnimationFrame(leerCuadro);
  }

  async function leerCuadro() {
    if (!flujo) return;
    if (!buscando && video.readyState >= 2) {
      let texto = "";
      try {
        if (detector) {
          const r = await detector.detect(video);
          if (r[0]) texto = r[0].rawValue;
        } else if (window.jsQR) {
          const w = 480, h = Math.round(480 * video.videoHeight / video.videoWidth) || 360;
          lienzo.width = w; lienzo.height = h;
          const g = lienzo.getContext("2d", { willReadFrequently: true });
          g.drawImage(video, 0, 0, w, h);
          const r = jsQR(g.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: "dontInvert" });
          if (r) texto = r.data;
        }
      } catch (e) { /* cuadro ilegible */ }
      const codigo = normalizar(texto);
      if (codigo && codigo !== ultimo) {
        ultimo = codigo;
        vibrar(60);
        buscar(codigo);
      }
    }
    setTimeout(() => requestAnimationFrame(leerCuadro), 120);
  }

  // ---------- Buscar y mostrar
  async function buscar(codigo) {
    buscando = true;
    res.innerHTML = `<div class="caja">Buscando <b class="codigo">${esc(codigo)}</b>…</div>`;
    try {
      const r = await fetch("api.php?accion=buscar&codigo=" + encodeURIComponent(codigo), { credentials: "same-origin" });
      const j = await r.json();
      if (r.status === 401) { location.href = "./?volver=escanear.php"; return; }
      if (!j.ok) { pintarError(j.error); vibrar([80, 60, 80]); return; }
      pintarFicha(j.canje);
    } catch (e) {
      // Sin conexión: permitimos validarlo y se envía después
      res.innerHTML = `<div class="caja ficha ficha--offline"><p class="codigo">${esc(codigo)}</p>
        <p>📴 Sin conexión. No se puede revisar ahora, pero puedes guardar la validación: se enviará sola al volver el internet y el sistema la rechazará si el cupón era falso o ya usado.</p>
        <div class="acciones"><button class="btn btn--turquesa" data-offline="${esc(codigo)}">Guardar validación</button>
        <button class="btn btn--claro" data-otro>Escanear otro</button></div></div>`;
    } finally {
      buscando = false;
    }
  }

  const mostrar = () => res.scrollIntoView({ behavior: "smooth", block: "start" });

  function pintarError(msg) {
    setTimeout(mostrar, 30);
    res.innerHTML = `<div class="caja ficha ficha--mal"><p class="ficha__icono">❌</p><p><b>${esc(msg)}</b></p>
      <button class="btn btn--claro" data-otro>Escanear otro</button></div>`;
  }

  function pintarFicha(c, aviso) {
    setTimeout(mostrar, 30);
    const ok = c.estatus === "pendiente" || c.estatus === "confirmado";
    const clase = c.estatus === "usado" ? "ficha--usado" : !ok ? "ficha--mal" : c.uso_previo ? "ficha--alerta" : "ficha--bien";
    const titulo = c.estatus === "usado"
      ? `⛔ Ya usado${c.validado_en ? " el " + esc(c.validado_en.slice(0, 16)) : ""}${c.validado_por ? " por " + esc(c.validado_por) : ""}`
      : c.estatus === "cancelado" ? "⛔ Cupón cancelado"
      : c.uso_previo ? `⚠️ Este cliente ya usó este cupón (código ${esc(c.uso_previo.codigo)})`
      : "✅ Cupón real y disponible";
    res.innerHTML = `<div class="caja ficha ${clase}">
      ${aviso ? `<p class="ficha__aviso">${esc(aviso)}</p>` : ""}
      <p class="ficha__titulo">${titulo}</p>
      <p class="codigo">${esc(c.codigo)} <span class="pill pill--${esc(c.estatus)}">${esc(c.estatus_txt)}</span></p>
      <h2>${esc(c.cupon)}</h2>
      <p>${esc(c.opcion)} · ${dinero(c.precio)} MXN${c.venta !== c.precio ? ` · total ${dinero(c.venta)}` : ""}</p>
      <p>👤 <b>${esc(c.nombre)}</b> · ${c.personas} pers.</p>
      <p>📱 ${esc(c.telefono)} · ✉️ ${esc(c.correo)}</p>
      <p>📅 ${esc(c.fecha_txt)}</p>
      ${c.vendedor ? `<p><span class="pill pill--vendedor">Vendedor ${esc(c.vendedor)}</span></p>` : ""}
      <div class="acciones">
        ${ok ? `<button class="btn btn--turquesa btn--grande" data-validar="${esc(c.codigo)}" ${c.uso_previo ? 'data-forzar="1"' : ""}>${c.uso_previo ? "Validar de todos modos" : "Validar y marcar como usado"}</button>` : ""}
        <button class="btn btn--claro" data-otro>Escanear otro</button>
      </div></div>`;
  }

  async function validar(codigo, forzar) {
    const boton = res.querySelector("[data-validar]");
    if (boton) { boton.disabled = true; boton.textContent = "Validando…"; }
    try {
      const r = await fetch("api.php?accion=validar", {
        method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF": csrf },
        body: JSON.stringify({ codigo, forzar: !!forzar }),
      });
      const j = await r.json();
      if (r.status === 401) { location.href = "./?volver=escanear.php"; return; }
      if (j.ok) { vibrar(200); pintarFicha(j.canje, "Listo: cupón validado. Ya no se puede volver a usar."); }
      else if (j.canje) { vibrar([80, 60, 80]); pintarFicha(j.canje, j.error); }
      else pintarError(j.error);
    } catch (e) {
      guardarOffline(codigo);
    }
  }

  // ---------- Sin conexión
  function guardarOffline(codigo) {
    const l = leerCola().filter((x) => x.codigo !== codigo);
    const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    l.push({ codigo, escaneado_en: d.toISOString().slice(0, 19).replace("T", " ") });
    guardarCola(l);
    res.innerHTML = `<div class="caja ficha ficha--offline"><p class="ficha__titulo">📴 Guardado sin conexión</p>
      <p>${esc(codigo)} se enviará en cuanto vuelva el internet.</p><button class="btn btn--claro" data-otro>Escanear otro</button></div>`;
    pintarPendientes();
  }

  function pintarPendientes(resultados) {
    const l = leerCola();
    const caja = $("#pendientes");
    let html = l.length ? `📴 ${l.length} validación(es) esperando internet: ${l.map((x) => esc(x.codigo)).join(", ")}` : "";
    if (resultados && resultados.length) html += (html ? "<br>" : "") + resultados.map(esc).join("<br>");
    caja.innerHTML = html;
    caja.hidden = !html;
  }

  async function sincronizar() {
    const l = leerCola();
    if (!l.length || !navigator.onLine) return;
    const quedan = [], mensajes = [];
    for (const x of l) {
      try {
        const r = await fetch("api.php?accion=validar", {
          method: "POST", credentials: "same-origin",
          headers: { "Content-Type": "application/json", "X-CSRF": csrf },
          body: JSON.stringify(x),
        });
        if (r.status === 401 || r.status >= 500) { quedan.push(x); continue; }
        const j = await r.json();
        mensajes.push(j.ok ? `✅ ${x.codigo} validado` : `❌ ${x.codigo}: ${j.error}`);
      } catch (e) { quedan.push(x); }
    }
    guardarCola(quedan);
    pintarPendientes(mensajes);
  }

  // ---------- Eventos
  $("#btn-camara").addEventListener("click", abrirCamara);
  $("#form-manual").addEventListener("submit", (e) => {
    e.preventDefault();
    const c = normalizar($("#codigo-manual").value);
    if (c) { ultimo = c; buscar(c); }
  });
  res.addEventListener("click", (e) => {
    const v = e.target.closest("[data-validar]");
    if (v) return validar(v.dataset.validar, v.dataset.forzar);
    const o = e.target.closest("[data-offline]");
    if (o) return guardarOffline(o.dataset.offline);
    if (e.target.closest("[data-otro]")) {
      ultimo = ""; res.innerHTML = ""; $("#codigo-manual").value = "";
      if (!flujo) $("#codigo-manual").focus();
    }
  });
  window.addEventListener("online", sincronizar);
  pintarPendientes();
  sincronizar();
})();
