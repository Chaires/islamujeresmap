(function () {
  "use strict";
  const { t, esc } = VR;

  VR.textosPagina = {
    titulo: { es: "Cupón de reserva", en: "Booking coupon" },
    lema: { es: "Tú eliges el destino, nosotros te llevamos", en: "You choose the destination, we take you there" },
    folio: { es: "Folio", en: "Booking no." },
    pendiente: { es: "Pendiente de confirmación", en: "Pending confirmation" },
    confirmada: { es: "Confirmada", en: "Confirmed" },
    cancelada: { es: "Cancelada", en: "Cancelled" },
    totalAprox: { es: "Total aproximado", en: "Approximate total" },
    siguiente: { es: "Envía este cupón por WhatsApp. Validamos disponibilidad y precio final con el proveedor y te confirmamos para hacer el cobro.", en: "Send this coupon on WhatsApp. We check availability and the final price with the operator and confirm with you before charging." },
    enviarWa: { es: "Enviar cupón por WhatsApp", en: "Send coupon on WhatsApp" },
    imprimir: { es: "Guardar o imprimir", en: "Save or print" },
    seguir: { es: "Seguir explorando tours", en: "Keep browsing tours" },
    noEncontrado: { es: "No encontramos este cupón en este dispositivo. Si ya lo enviaste por WhatsApp, ahí tienes tu folio.", en: "We couldn't find this coupon on this device. If you already sent it on WhatsApp, your booking number is there." },
    nombre: { es: "Nombre", en: "Name" },
    contacto: { es: "WhatsApp", en: "WhatsApp" },
    correo: { es: "Correo", en: "Email" },
    hotel: { es: "Hotel / zona", en: "Hotel / area" },
    comentarios: { es: "Comentarios", en: "Comments" },
    vendedor: { es: "Código de vendedor", en: "Agent code" },
    msgHola: { es: "Hola, quiero confirmar mi reserva.", en: "Hi, I'd like to confirm my booking." },
    msgVer: { es: "Ver cupón", en: "View coupon" },
    msgFecha: { es: "Fecha", en: "Date" },
    msgPersonas: { es: "Personas", en: "Guests" }
  };

  VR.encabezado();
  VR.traducirPagina();

  const q = new URLSearchParams(location.search);
  const folio = q.get("f") || "";
  const llave = q.get("k") || "";

  function local() {
    try { return (JSON.parse(localStorage.getItem("vr_cupones") || "{}"))[folio] || null; } catch (e) { return null; }
  }
  function personas(it) {
    const m = it.menores && it.menores.length;
    return it.adultos + " " + (it.adultos === 1 ? t("adulto") : t("adultos")) + (m ? " + " + m + " " + (m === 1 ? t("menor") : t("menores")) + " (" + it.menores.join(", ") + ")" : "");
  }

  function pintar(c) {
    if (!c) {
      document.getElementById("cupon").classList.add("oculto");
      document.getElementById("no-encontrado").classList.remove("oculto");
      return;
    }
    document.getElementById("folio").textContent = c.folio;
    const est = { confirmada: "confirmada", cancelada: "cancelada" }[c.estado] || "pendiente";
    document.getElementById("estado").textContent = t(est);
    document.getElementById("items").innerHTML = c.items.map(it =>
      '<div class="cupon__item"><span class="monto">' + esc(VR.dinero(it.total, it.moneda)) + "</span><b>" + esc(it.titulo) + "</b>" +
      "<p>📅 " + esc(it.fecha) + " · 👥 " + esc(personas(it)) + "</p>" + (it.detalle ? "<p>" + esc(it.detalle) + "</p>" : "") + "</div>").join("");
    document.getElementById("total").textContent = VR.dinero(c.total_mxn, "MXN");
    const cl = c.cliente || {};
    const filas = [["nombre", cl.nombre], ["contacto", cl.whatsapp], ["correo", cl.correo], ["hotel", cl.hotel], ["comentarios", cl.comentarios], ["vendedor", c.vendedor]]
      .filter(x => x[1]).map(x => "<div><b>" + t(x[0]) + ":</b> " + esc(x[1]) + "</div>").join("");
    document.getElementById("cliente").innerHTML = filas;

    // Mensaje de WhatsApp con todo el cupón (igual que hoy, pero con folio)
    let msg = t("msgHola") + "\n\n*" + t("folio") + ": " + c.folio + "*\n\n";
    c.items.forEach((it, i) => {
      msg += (i + 1) + ". " + it.titulo + (it.detalle ? " (" + it.detalle + ")" : "") + "\n   " + t("msgFecha") + ": " + it.fecha + " · " + t("msgPersonas") + ": " + personas(it) + " · " + VR.dinero(it.total, it.moneda) + "\n";
    });
    msg += "\n" + t("totalAprox").toUpperCase() + ": " + VR.dinero(c.total_mxn, "MXN") + "\n\n";
    msg += t("nombre") + ": " + (cl.nombre || "") + "\n" + t("contacto") + ": " + (cl.whatsapp || "");
    if (cl.hotel) msg += "\n" + t("hotel") + ": " + cl.hotel;
    if (c.vendedor) msg += "\n" + t("vendedor") + ": " + c.vendedor;
    if (cl.comentarios) msg += "\n" + t("comentarios") + ": " + cl.comentarios;
    if (llave) msg += "\n\n" + t("msgVer") + ": " + location.href;
    const wa = document.getElementById("enviar-wa");
    wa.innerHTML = VR.ICONO_WA + "<span>" + t("enviarWa") + "</span>";
    wa.href = VR.urlWhatsApp(msg);
  }

  document.getElementById("imprimir").addEventListener("click", () => window.print());

  if (llave) {
    fetch("api/cupon.php?f=" + encodeURIComponent(folio) + "&k=" + encodeURIComponent(llave), { cache: "no-store" })
      .then(r => r.json())
      .then(res => pintar(res && res.ok ? res.cupon : local()))
      .catch(() => pintar(local()));
  } else {
    pintar(local());
  }
})();
