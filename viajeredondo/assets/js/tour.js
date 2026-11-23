(function () {
  "use strict";
  const { t, tx, esc, CATEGORIAS, DESTINOS } = VR;

  VR.textosPagina = {
    sobre: { es: "Sobre esta experiencia", en: "About this experience" },
    info: { es: "Información importante", en: "Good to know" },
    incluye: { es: "Incluye", en: "Included" },
    noIncluye: { es: "No incluye", en: "Not included" },
    opciones: { es: "Opciones y precios", en: "Options and prices" },
    notaPrecio: { es: "Precio público estimado. Te confirmamos disponibilidad y precio final antes de cobrar.", en: "Estimated public price. We confirm availability and the final price before charging." },
    fecha: { es: "Fecha deseada", en: "Preferred date" },
    horasLbl: { es: "Horas de navegación", en: "Sailing hours" },
    opcionLbl: { es: "Opción", en: "Option" },
    adultosLbl: { es: "Adultos", en: "Adults" },
    menoresLbl: { es: "Menores", en: "Children" },
    menoresNota: { es: "Indica la edad de cada uno", en: "Tell us each child's age" },
    pasajeros: { es: "Pasajeros", en: "Guests" },
    estimado: { es: "Total estimado", en: "Estimated total" },
    reservarAhora: { es: "Reservar ahora", en: "Book now" },
    agregar: { es: "Agregar y seguir viendo", en: "Add and keep browsing" },
    actualizar: { es: "Actualizar en mi reserva", en: "Update my booking" },
    preguntar: { es: "Preguntar por WhatsApp", en: "Ask on WhatsApp" },
    noCobro: { es: "No se cobra nada ahora. Recibes un cupón con folio y lo validamos contigo por WhatsApp.", en: "Nothing is charged now. You get a coupon with a booking number and we confirm with you on WhatsApp." },
    tambien: { es: "También te puede gustar", en: "You may also like" },
    masEn: { es: "Más en {d}", en: "More in {d}" },
    reservar: { es: "Reservar", en: "Book" },
    faltaFecha: { es: "Elige la fecha de tu tour.", en: "Choose the date for your tour." },
    edad: { es: "Edad", en: "Age" },
    años: { es: "años", en: "yrs" },
    elige: { es: "Elige una opción", en: "Choose an option" },
    noEncontrado: { es: "No encontramos este tour.", en: "We couldn't find this tour." },
    muelle: { es: "Impuesto de muelle: ${m} MXN por persona.", en: "Dock fee: ${m} MXN per person." },
    paxExtra: { es: "Persona extra: ${m} MXN.", en: "Extra guest: ${m} MXN." },
    inicio: { es: "Inicio", en: "Home" },
    tourPrivado: { es: "Quiero el tour privado", en: "I want the private tour" },
    hola: { es: "Hola, quiero información del tour {x}.", en: "Hi, I'd like information about the {x} tour." },
    galeria: { es: "Galería", en: "Gallery" },
    fotos: { es: "fotos", en: "photos" },
    verFoto: { es: "Ver foto", en: "View photo" },
    cerrar: { es: "Cerrar", en: "Close" }
  };

  VR.encabezado({ transparente: true });
  VR.pie();
  VR.traducirPagina();

  const slug = new URLSearchParams(location.search).get("t");
  const sel = { fecha: "", adultos: 2, menores: [], opcion: null, horas: null, tarifa: null, extra: false };
  let tour = null;
  let galeria = [];
  let fotoActual = 0;

  const $ = id => document.getElementById(id);
  const hoy = new Date();
  $("r-fecha").min = new Date(hoy.getTime() - hoy.getTimezoneOffset() * 6e4).toISOString().slice(0, 10);

  VR.cargarTours().then(tours => {
    tour = tours.find(x => x.slug === slug);
    if (!tour) {
      $("d-titulo").textContent = t("noEncontrado");
      document.querySelector(".detalle").innerHTML = '<p class="vacio"><a class="btn btn--rosa" href="tours.html">' + t("navTours") + "</a></p>";
      return;
    }
    const titulo = tx(tour.titulo);
    const cat = CATEGORIAS[tour.categoria] || {};
    const dest = DESTINOS[tour.destino] || {};
    document.title = titulo + " · Viaje Redondo";
    $("d-foto").src = tour.imagen;
    $("d-foto").alt = titulo;
    $("d-titulo").textContent = titulo;
    $("d-migas").innerHTML = '<a href="index.html">' + t("inicio") + '</a> / <a href="tours.html">' + t("navTours") + '</a> / <a href="tours.html?d=' + tour.destino + '">' + esc(dest.nombre) + "</a>";
    $("d-chips").innerHTML = '<span class="chip">' + VR.ICONO_PIN + esc(dest.nombre) + '</span><a class="chip" style="text-decoration:none" href="tours.html?c=' + tour.categoria + '">' + (cat.ico || "") + " " + esc(tx(cat)) + "</a>";
    $("d-desc").textContent = tx(tour.descripcion);

    const lista = (arr, id, bloque) => {
      if (arr && arr.length) { $(id).innerHTML = arr.map(x => "<li>" + esc(x) + "</li>").join(""); $(bloque).classList.remove("oculto"); }
    };
    lista(tx(tour.detalles), "d-detalles", "b-detalles");
    lista(tx(tour.incluye), "d-incluye", "b-incluye");
    lista(tx(tour.no_incluye), "d-noincluye", "b-noincluye");

    // Tablas de opciones / tarifas
    let tablas = "";
    if (tour.yate) {
      tablas += '<table class="tabla-opciones">' + tour.yate.tarifas.map(x => "<tr><td>" + esc(VR.etiquetaTarifa(x)) + "</td><td>" + VR.dinero(x.precio, "MXN") + "</td></tr>").join("") + "</table>";
      const notas = [];
      if (tour.yate.muelle_por_persona) notas.push(t("muelle", { m: tour.yate.muelle_por_persona }));
      if (tour.yate.persona_extra) notas.push(t("paxExtra", { m: tour.yate.persona_extra.toLocaleString("en-US") }));
      if (notas.length) tablas += '<p class="aviso">' + notas.join(" ") + "</p>";
    }
    if (tour.opciones_por_dia) {
      tablas += tour.opciones_por_dia.map(g => '<table class="tabla-opciones"><caption>' + esc(tx(g.dia)) + "</caption>" +
        g.opciones.map(o => "<tr><td>" + esc(tx(o.etiqueta)) + "</td><td>" + esc(o.precio.texto || "—") + "</td></tr>").join("") + "</table>").join("");
    } else if (tour.opciones) {
      tablas += '<table class="tabla-opciones">' + tour.opciones.map(o => "<tr><td>" + esc(tx(o.etiqueta)) + "</td><td>" + esc(o.precio.texto || "—") + "</td></tr>").join("") + "</table>";
    }
    if (tour.extra) {
      tablas += '<table class="tabla-opciones"><caption>' + esc(tx(tour.extra.etiqueta)) + "</caption><tr><td>" + esc(tx(tour.extra.detalles).join(" · ")) + "</td><td>" + esc(tour.extra.precio.texto) + "</td></tr></table>";
    }
    if (tablas) { $("d-opciones").innerHTML = tablas; $("b-opciones").classList.remove("oculto"); }

    // Galería de fotos
    if (tour.galeria && tour.galeria.length > 1) {
      galeria = tour.galeria;
      $("d-galeria").innerHTML = galeria.map((src, i) =>
        '<button type="button" class="galeria__foto" data-foto="' + i + '" aria-label="' + t("verFoto") + " " + (i + 1) + '"><img loading="lazy" src="' + esc(src) + '" alt=""></button>').join("");
      $("b-galeria").classList.remove("oculto");
      $("d-galeria-n").textContent = galeria.length;
    }

    // Precio de referencia
    const p = VR.precioTarjeta(tour);
    const precioTxt = VR.dinero(p.monto, p.moneda);
    $("r-desde").textContent = p.desde ? t("desde") : "";
    $("r-precio").textContent = precioTxt;
    $("r-sufijo").textContent = p.sufijo || "";
    $("bi-desde").textContent = p.desde ? t("desde") : (p.sufijo || "");
    $("bi-precio").textContent = precioTxt;

    // Controles según el tipo de tour
    if (tour.yate) {
      $("r-horas").innerHTML = tour.yate.tarifas.map((x, i) => '<option value="' + i + '">' + esc(VR.etiquetaTarifa(x)) + " · " + VR.dinero(x.precio, "MXN") + "</option>").join("");
      $("c-horas").classList.remove("oculto");
      sel.tarifa = 0;
      sel.horas = tour.yate.tarifas[0].horas;
      document.querySelector('[data-t="adultosLbl"]').textContent = t("pasajeros");
    }
    const ops = VR.opcionesDe(tour);
    if (ops.length) {
      $("r-opcion").innerHTML = ops.map((o, i) => '<option value="' + i + '">' + esc(o.etiqueta) + " · " + esc(o.precio.texto || "—") + "</option>").join("");
      $("c-opcion").classList.remove("oculto");
      sel.opcion = 0;
    }
    if (tour.extra) {
      $("r-extra-txt").textContent = t("tourPrivado") + " · " + tx(tour.extra.etiqueta) + " (" + tour.extra.precio.texto + ")";
      $("c-extra").classList.remove("oculto");
    }

    // Si ya está en la reserva, recupera lo que eligió
    const previo = VR.leerReserva().find(x => x.slug === tour.slug);
    if (previo) {
      Object.assign(sel, previo);
      $("r-fecha").value = previo.fecha || "";
      if (tour.yate) {
        const tf = VR.tarifaYate(tour, previo);
        sel.tarifa = tour.yate.tarifas.indexOf(tf);
        sel.horas = tf.horas;
        $("r-horas").value = sel.tarifa;
      }
      if (previo.opcion != null) $("r-opcion").value = previo.opcion;
      $("r-extra").checked = !!previo.extra;
      $("r-agregar").textContent = t("actualizar");
    }

    const wa = $("r-wa");
    wa.innerHTML = VR.ICONO_WA + "<span>" + t("preguntar") + "</span>";
    wa.href = VR.urlWhatsApp(t("hola", { x: titulo }));

    recalcular();

    // Relacionados
    const rel = tours.filter(x => x.slug !== tour.slug && x.destino === tour.destino)
      .concat(tours.filter(x => x.slug !== tour.slug && x.destino !== tour.destino && x.categoria === tour.categoria)).slice(0, 8);
    $("rel-titulo").textContent = t("masEn", { d: dest.nombre });
    const enReserva = new Set(VR.leerReserva().map(x => x.slug));
    $("relacionados").innerHTML = rel.map(x => VR.tarjetaTour(x, enReserva.has(x.slug))).join("");
  });

  function pintarEdades() {
    $("r-edades").innerHTML = sel.menores.map((edad, i) =>
      '<select data-edad="' + i + '" aria-label="' + t("edad") + " " + (i + 1) + '">' +
      Array.from({ length: 18 }, (_, n) => '<option value="' + n + '"' + (n === edad ? " selected" : "") + ">" + n + " " + t("años") + "</option>").join("") +
      "</select>").join("");
  }

  function recalcular() {
    $("r-adultos").textContent = sel.adultos;
    $("r-menores").textContent = sel.menores.length;
    pintarEdades();
    const r = VR.calcular(tour, sel);
    $("r-total").textContent = r.total == null ? t("porConfirmar") : VR.dinero(r.total, r.moneda);
  }

  document.querySelector(".caja-reserva").addEventListener("click", e => {
    const b = e.target.closest("[data-paso]");
    if (!b || !tour) return;
    const paso = Number(b.dataset.paso);
    if (b.dataset.campo === "adultos") sel.adultos = Math.max(1, Math.min(60, sel.adultos + paso));
    else if (paso > 0 && sel.menores.length < 20) sel.menores.push(8);
    else if (paso < 0) sel.menores.pop();
    recalcular();
  });
  $("r-edades").addEventListener("change", e => {
    const s = e.target.closest("[data-edad]");
    if (s) sel.menores[Number(s.dataset.edad)] = Number(s.value);
  });
  $("r-fecha").addEventListener("change", e => { sel.fecha = e.target.value; });
  $("r-horas").addEventListener("change", e => {
    sel.tarifa = Number(e.target.value);
    sel.horas = tour.yate.tarifas[sel.tarifa].horas;
    recalcular();
  });
  $("r-opcion").addEventListener("change", e => { sel.opcion = Number(e.target.value); recalcular(); });
  $("r-extra").addEventListener("change", e => { sel.extra = e.target.checked; recalcular(); });

  function agregar() {
    if (!tour) return false;
    sel.fecha = $("r-fecha").value;
    if (!sel.fecha) {
      VR.aviso(t("faltaFecha"));
      $("r-fecha").focus();
      return false;
    }
    return VR.agregarAReserva({
      slug: tour.slug, fecha: sel.fecha, adultos: sel.adultos, menores: sel.menores.slice(),
      opcion: sel.opcion, horas: sel.horas, tarifa: sel.tarifa, extra: sel.extra
    });
  }
  $("r-agregar").addEventListener("click", () => {
    if (agregar()) {
      $("r-agregar").textContent = t("actualizar");
      VR.aviso("✓ " + t("agregado") + ' <a href="reservar.html">' + t("verReserva") + " →</a>");
    }
  });
  $("r-reservar").addEventListener("click", () => { if (agregar()) location.href = "reservar.html"; });

  /* Visor de fotos */
  const visor = $("visor");
  function abrirFoto(i) {
    fotoActual = (i + galeria.length) % galeria.length;
    $("visor-img").src = galeria[fotoActual];
    $("visor-n").textContent = (fotoActual + 1) + " / " + galeria.length;
    visor.classList.remove("oculto");
    document.body.style.overflow = "hidden";
  }
  function cerrarFoto() { visor.classList.add("oculto"); document.body.style.overflow = ""; }
  $("d-galeria").addEventListener("click", e => { const b = e.target.closest("[data-foto]"); if (b) abrirFoto(Number(b.dataset.foto)); });
  $("visor-cerrar").addEventListener("click", cerrarFoto);
  $("visor-ant").addEventListener("click", () => abrirFoto(fotoActual - 1));
  $("visor-sig").addEventListener("click", () => abrirFoto(fotoActual + 1));
  visor.addEventListener("click", e => { if (e.target === visor) cerrarFoto(); });
  document.addEventListener("keydown", e => {
    if (visor.classList.contains("oculto")) return;
    if (e.key === "Escape") cerrarFoto();
    if (e.key === "ArrowLeft") abrirFoto(fotoActual - 1);
    if (e.key === "ArrowRight") abrirFoto(fotoActual + 1);
  });
  let toqueX = null;
  visor.addEventListener("touchstart", e => { toqueX = e.touches[0].clientX; }, { passive: true });
  visor.addEventListener("touchend", e => {
    if (toqueX == null) return;
    const dx = e.changedTouches[0].clientX - toqueX;
    if (Math.abs(dx) > 40) abrirFoto(fotoActual + (dx < 0 ? 1 : -1));
    toqueX = null;
  });
})();
