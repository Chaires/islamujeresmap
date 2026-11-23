(function () {
  "use strict";
  const { t, tx, esc, DESTINOS } = VR;

  VR.textosPagina = {
    etiqueta: { es: "Tu reserva", en: "Your booking" },
    titulo: { es: "Revisa y envía tu reserva", en: "Review and send your booking" },
    sub: { es: "Confirma tus tours, déjanos tus datos y te generamos un cupón con folio para enviarlo por WhatsApp.", en: "Check your tours, leave your details and we'll create a coupon with a booking number to send on WhatsApp." },
    vacio: { es: "Tu reserva está vacía. Explora el catálogo y agrega los tours que quieras (hasta 5).", en: "Your booking is empty. Browse the catalog and add the tours you like (up to 5)." },
    explorar: { es: "Explorar tours", en: "Browse tours" },
    agregarMas: { es: "+ Agregar otro tour", en: "+ Add another tour" },
    tusDatos: { es: "Tus datos", en: "Your details" },
    nombre: { es: "Nombre completo *", en: "Full name *" },
    whatsapp: { es: "WhatsApp o teléfono *", en: "WhatsApp or phone *" },
    correo: { es: "Correo electrónico", en: "Email" },
    hotel: { es: "Hotel o zona donde te hospedas", en: "Hotel or area where you're staying" },
    hotelNota: { es: "Nos ayuda a confirmar el punto de recogida.", en: "Helps us confirm your pickup point." },
    comentarios: { es: "Comentarios", en: "Comments" },
    vendedor: { es: "Código de vendedor (opcional)", en: "Agent code (optional)" },
    vendedorNota: { es: "Si un asesor de Viaje Redondo te atendió, escribe su código.", en: "If a Viaje Redondo agent helped you, enter their code." },
    resumen: { es: "Resumen", en: "Summary" },
    totalAprox: { es: "Total aproximado", en: "Approximate total" },
    generar: { es: "Generar mi cupón", en: "Create my coupon" },
    generando: { es: "Generando…", en: "Creating…" },
    noCobro: { es: "No se cobra nada ahora. Validamos disponibilidad y precio final con el proveedor y te contactamos por WhatsApp para confirmar y cobrar.", en: "Nothing is charged now. We check availability and the final price with the operator and contact you on WhatsApp to confirm and charge." },
    cambio: { es: "Los precios en USD se convierten a pesos con un tipo de cambio aproximado de ${tc} MXN.", en: "USD prices are converted to pesos at an approximate rate of ${tc} MXN." },
    editar: { es: "Editar", en: "Edit" },
    quitar: { es: "Quitar", en: "Remove" },
    faltan: { es: "Escribe tu nombre y tu WhatsApp para generar el cupón.", en: "Enter your name and WhatsApp to create the coupon." },
    sinFecha: { es: "Sin fecha", en: "No date" }
  };

  VR.encabezado();
  VR.pie();
  VR.traducirPagina();

  const $ = id => document.getElementById(id);
  let tours = [];

  // Recupera datos del cliente de una reserva anterior y el código de vendedor
  try {
    const c = JSON.parse(localStorage.getItem("vr_cliente") || "{}");
    ["nombre", "whatsapp", "correo", "hotel"].forEach(k => { if (c[k]) $("c-" + k).value = c[k]; });
  } catch (e) { /* nada */ }
  $("c-vendedor").value = VR.referido();

  function personas(it) {
    const a = it.adultos + " " + (it.adultos === 1 ? t("adulto") : t("adultos"));
    const m = it.menores && it.menores.length ? " + " + it.menores.length + " " + (it.menores.length === 1 ? t("menor") : t("menores")) + " (" + it.menores.join(", ") + ")" : "";
    return a + m;
  }
  function fechaBonita(f) {
    if (!f) return t("sinFecha");
    const d = new Date(f + "T12:00:00");
    return d.toLocaleDateString(VR.idioma() === "en" ? "en-US" : "es-MX", { weekday: "short", day: "numeric", month: "short", year: "numeric" });
  }

  function lineas() {
    return VR.leerReserva().map(it => {
      const tour = tours.find(x => x.slug === it.slug);
      return tour ? { it, tour, calc: VR.calcular(tour, it) } : null;
    }).filter(Boolean);
  }

  function pintar() {
    const ls = lineas();
    $("vacio").classList.toggle("oculto", ls.length > 0);
    $("formulario").classList.toggle("oculto", ls.length === 0);
    $("agregar-mas").classList.toggle("oculto", ls.length >= VR.C.maxTours);
    $("renglones").innerHTML = ls.map(({ it, tour, calc }) =>
      '<div class="renglon"><img src="' + esc(tour.imagen) + '" alt="">' +
      "<div><h3>" + esc(tx(tour.titulo)) + "</h3>" +
      "<p>📅 " + esc(fechaBonita(it.fecha)) + " · 👥 " + esc(personas(it)) + "</p>" +
      (calc.detalle ? "<p>" + esc(calc.detalle) + "</p>" : "") +
      '<div class="renglon__pie"><b>' + esc(VR.dinero(calc.total, calc.moneda)) + "</b><span>" +
      '<a class="enlace-quitar" style="color:var(--vr-cian);text-decoration:none;margin-right:14px" href="tour.html?t=' + encodeURIComponent(tour.slug) + '">' + t("editar") + "</a>" +
      '<button type="button" class="enlace-quitar" data-quitar="' + esc(tour.slug) + '">' + t("quitar") + "</button></span></div></div></div>"
    ).join("");

    let total = 0, pendiente = false, hayUsd = false;
    $("resumen-lineas").innerHTML = ls.map(({ tour, calc }) => {
      if (calc.total == null) pendiente = true; else total += VR.aPesos(calc.total, calc.moneda);
      if (calc.moneda === "USD") hayUsd = true;
      return '<div class="resumen__linea"><span>' + esc(tx(tour.titulo)) + "</span><b>" + esc(VR.dinero(calc.total, calc.moneda)) + "</b></div>";
    }).join("");
    $("total").textContent = VR.dinero(total, "MXN") + (pendiente ? " +" : "");
    $("nota-cambio").textContent = hayUsd ? t("cambio", { tc: VR.C.tipoCambio }) : "";
  }

  $("renglones").addEventListener("click", e => {
    const b = e.target.closest("[data-quitar]");
    if (b) { VR.quitarDeReserva(b.dataset.quitar); pintar(); }
  });

  function folioLocal() {
    const d = new Date();
    const p = n => String(n).padStart(2, "0");
    const az = Array.from({ length: 4 }, () => "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"[Math.floor(Math.random() * 32)]).join("");
    return "VR-" + String(d.getFullYear()).slice(2) + p(d.getMonth() + 1) + p(d.getDate()) + "-" + az;
  }

  /** Copia del cupón que se guarda en el navegador (respaldo si el servidor no responde) */
  function armarCupon(folio, cliente, vendedor) {
    const ls = lineas();
    let total = 0;
    const items = ls.map(({ it, tour, calc }) => {
      if (calc.total != null) total += VR.aPesos(calc.total, calc.moneda);
      return { slug: tour.slug, titulo: tx(tour.titulo), imagen: tour.imagen, fecha: it.fecha, adultos: it.adultos, menores: it.menores || [],
        detalle: calc.detalle, moneda: calc.moneda, total: calc.total };
    });
    return { folio, creado: new Date().toISOString(), estado: "pendiente", cliente, vendedor, items, total_mxn: Math.round(total), tipo_cambio: VR.C.tipoCambio };
  }

  $("formulario").addEventListener("submit", e => {
    e.preventDefault();
    const cliente = {
      nombre: $("c-nombre").value.trim(), whatsapp: $("c-whatsapp").value.trim(), correo: $("c-correo").value.trim(),
      hotel: $("c-hotel").value.trim(), comentarios: $("c-comentarios").value.trim()
    };
    const vendedor = $("c-vendedor").value.trim().toUpperCase();
    if (!cliente.nombre || cliente.whatsapp.replace(/\D/g, "").length < 7) {
      VR.aviso(t("faltan"));
      (cliente.nombre ? $("c-whatsapp") : $("c-nombre")).focus();
      return;
    }
    try { localStorage.setItem("vr_cliente", JSON.stringify({ nombre: cliente.nombre, whatsapp: cliente.whatsapp, correo: cliente.correo, hotel: cliente.hotel })); } catch (err) { /* nada */ }

    const btn = $("enviar");
    btn.disabled = true;
    btn.querySelector("span").textContent = t("generando");

    const items = VR.leerReserva();
    fetch("api/solicitud.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ idioma: VR.idioma(), cliente, vendedor, items })
    })
      .then(r => r.json())
      .then(res => {
        if (!res || !res.ok) throw new Error((res && res.error) || "error");
        terminar(res.cupon, "cupon.html?f=" + encodeURIComponent(res.cupon.folio) + "&k=" + encodeURIComponent(res.llave));
      })
      .catch(() => {
        // Sin servidor (o falló): el cupón se genera en el navegador y se manda igual por WhatsApp
        const cupon = armarCupon(folioLocal(), cliente, vendedor);
        cupon.local = true;
        terminar(cupon, "cupon.html?f=" + encodeURIComponent(cupon.folio));
      });
  });

  function terminar(cupon, destino) {
    try {
      const todos = JSON.parse(localStorage.getItem("vr_cupones") || "{}");
      todos[cupon.folio] = cupon;
      localStorage.setItem("vr_cupones", JSON.stringify(todos));
    } catch (e) { /* nada */ }
    VR.guardarReserva([]);
    location.href = destino;
  }

  VR.cargarTours().then(lista => { tours = lista; pintar(); });
})();
