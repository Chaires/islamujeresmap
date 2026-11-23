(function () {
  "use strict";
  const { t, tx, esc, CATEGORIAS, DESTINOS } = VR;

  VR.textosPagina = {
    portadaEtiqueta: { es: "Agencia de viajes · Caribe Mexicano", en: "Travel agency · Mexican Caribbean" },
    portadaTitulo: { es: "Tú eliges el destino, <em>nosotros te llevamos</em>", en: "You choose the destination, <em>we take you there</em>" },
    portadaSub: {
      es: "Tours, yates, parques y experiencias en Cancún, Riviera Maya y Yucatán. Arma tu reserva en minutos y te confirmamos por WhatsApp.",
      en: "Tours, yachts, parks and experiences in Cancún, the Riviera Maya and Yucatán. Build your booking in minutes and we confirm on WhatsApp."
    },
    verTours: { es: "Ver todos los tours", en: "See all tours" },
    hablarWa: { es: "Hablar por WhatsApp", en: "Chat on WhatsApp" },
    buscaDestino: { es: "Destino", en: "Destination" },
    buscaTipo: { es: "Tipo de experiencia", en: "Experience type" },
    buscaTexto: { es: "¿Qué quieres hacer?", en: "What do you want to do?" },
    buscaPh: { es: "Snorkel, cenotes, catamarán…", en: "Snorkel, cenotes, catamaran…" },
    buscar: { es: "Buscar", en: "Search" },
    confRnt: { es: "Agencia registrada <b>RNT</b>", en: "Registered agency <b>RNT</b>" },
    confTop: { es: "Top Agency Sales <b>RCD Hotels 2025</b>", en: "Top Agency Sales <b>RCD Hotels 2025</b>" },
    confAbonos: { es: "Aparta con anticipo y paga en <b>abonos</b>", en: "Book with a deposit, pay in <b>installments</b>" },
    catEtiqueta: { es: "Encuentra tu plan", en: "Find your plan" },
    catTitulo: { es: "¿Qué se te antoja vivir?", en: "What are you in the mood for?" },
    favEtiqueta: { es: "Los favoritos", en: "Favorites" },
    favTitulo: { es: "Experiencias que todos quieren", en: "Experiences everyone wants" },
    favSub: { es: "Los tours más reservados por nuestros viajeros. Toca cualquiera para ver qué incluye y armar tu reserva.", en: "Our travelers' most booked tours. Tap any to see what's included and build your booking." },
    verCatalogo: { es: "Ver catálogo completo →", en: "See full catalog →" },
    destEtiqueta: { es: "Destinos", en: "Destinations" },
    destTitulo: { es: "Del Caribe a la selva maya", en: "From the Caribbean to the Maya jungle" },
    destSub: { es: "Operamos desde el destino: conocemos cada playa, cada guía y cada capitán por su nombre.", en: "We operate on location: we know every beach, guide and captain by name." },
    yatEtiqueta: { es: "Renta de yates", en: "Yacht charters" },
    yatTitulo: { es: "Tu día privado en el mar Caribe", en: "Your private day on the Caribbean" },
    yatSub: { es: "Fiesta, snorkel o atardecer frente a Cancún e Isla Mujeres. Elige tu yate, las horas y cuántos van; nosotros nos encargamos del resto.", en: "Party, snorkel or sunset off Cancún and Isla Mujeres. Pick your yacht, the hours and the group size; we handle the rest." },
    yatFlota: { es: "yates en la flota", en: "yachts in the fleet" },
    yatHoras: { es: "de navegación", en: "of sailing" },
    yatPax: { es: "personas por yate", en: "guests per yacht" },
    yatBoton: { es: "Ver yates y precios", en: "See yachts and prices" },
    comoEtiqueta: { es: "Cómo reservar", en: "How to book" },
    comoTitulo: { es: "Tres pasos, cero complicaciones", en: "Three steps, zero hassle" },
    paso1: { es: "Arma tu reserva", en: "Build your booking" },
    paso1t: { es: "Elige hasta 5 tours, la fecha y cuántas personas van. Ves el precio estimado al instante.", en: "Pick up to 5 tours, the date and group size. See the estimated price instantly." },
    paso2: { es: "Recibe tu cupón", en: "Get your coupon" },
    paso2t: { es: "Te generamos un cupón con folio y lo envías por WhatsApp con un solo toque. No pagas nada todavía.", en: "We create a coupon with a booking number and you send it on WhatsApp in one tap. Nothing to pay yet." },
    paso3: { es: "Confirmamos y pagas", en: "We confirm, you pay" },
    paso3t: { es: "Validamos disponibilidad y precio final con el proveedor, y apartas con un anticipo o pagas en abonos.", en: "We check availability and the final price with the operator, then you book with a deposit or pay in installments." },
    porEtiqueta: { es: "Por qué Viaje Redondo", en: "Why Viaje Redondo" },
    porTitulo: { es: "Expertos que viven donde tú quieres estar", en: "Experts who live where you want to be" },
    r1: { es: "Presencia local real", en: "Truly local" },
    r1t: { es: "Resolvemos cambios e imprevistos en minutos, no en días.", en: "We handle changes and surprises in minutes, not days." },
    r2: { es: "Aparta y paga en abonos", en: "Deposit and installments" },
    r2t: { es: "Reserva con un anticipo accesible y liquida antes de tu fecha.", en: "Book with an affordable deposit and pay the rest before your date." },
    r3: { es: "Todo en un solo lugar", en: "All in one place" },
    r3t: { es: "Tours, traslados, hoteles all inclusive, vuelos y yates con un solo equipo.", en: "Tours, transfers, all-inclusive hotels, flights and yachts with one team." },
    r4: { es: "Agencia registrada", en: "Registered agency" },
    r4t: { es: "Registro Nacional de Turismo vigente y facturación de tus servicios.", en: "Valid National Tourism Registry and invoices for your services." },
    llaTitulo: { es: "¿Armamos tu viaje completo?", en: "Shall we plan your whole trip?" },
    llaSub: { es: "Hotel, traslados y tours en un solo paquete. Escríbenos y te cotizamos hoy mismo.", en: "Hotel, transfers and tours in one package. Message us for a quote today." },
    llaBoton: { es: "Cotizar por WhatsApp", en: "Get a quote on WhatsApp" }
  };

  VR.encabezado({ transparente: true });
  VR.pie();
  VR.traducirPagina();

  const wa = VR.urlWhatsApp(t("waHola"));
  document.getElementById("wa-portada").href = wa;
  document.getElementById("wa-llamado").href = wa;

  VR.cargarTours().then(tours => {
    const enReserva = new Set(VR.leerReserva().map(x => x.slug));

    // Portada con fotos que rotan
    const fotos = ["isla-mujeres", "xcaret-plus", "bacalar-plus-ponton", "chichen-itza-deluxe", "holbox-plus-cenote"]
      .map(s => tours.find(x => x.slug === s)).filter(Boolean);
    const caja = document.getElementById("portada-fotos");
    caja.innerHTML = fotos.map((x, i) => '<img src="' + esc(x.imagen) + '" alt="" ' + (i ? 'loading="lazy"' : 'fetchpriority="high"') + ">").join("");
    const imgs = caja.querySelectorAll("img");
    let actual = 0;
    imgs[0].classList.add("activa");
    if (!matchMedia("(prefers-reduced-motion: reduce)").matches) {
      setInterval(() => {
        imgs[actual].classList.remove("activa");
        actual = (actual + 1) % imgs.length;
        imgs[actual].classList.add("activa");
      }, 5500);
    }

    // Buscador
    const cuenta = (campo, valor) => tours.filter(x => x[campo] === valor).length;
    document.getElementById("busca-destino").innerHTML = '<option value="">' + t("todosDestinos") + "</option>" +
      Object.keys(DESTINOS).map(k => '<option value="' + k + '">' + esc(DESTINOS[k].nombre) + "</option>").join("");
    document.getElementById("busca-categoria").innerHTML = '<option value="">' + t("todasCategorias") + "</option>" +
      Object.keys(CATEGORIAS).map(k => '<option value="' + k + '">' + esc(tx(CATEGORIAS[k])) + "</option>").join("");

    // Categorías
    document.getElementById("categorias").innerHTML = Object.keys(CATEGORIAS).map(k => {
      const c = CATEGORIAS[k];
      const n = cuenta("categoria", k);
      return '<a class="categoria" href="tours.html?c=' + k + '"><span class="categoria__ico" style="background:' + c.fondo + '">' + c.ico + "</span>" +
        esc(tx(c)) + "<small>" + n + " " + (n === 1 ? t("tour1") : t("tours")) + "</small></a>";
    }).join("");

    // Destacados
    document.getElementById("destacados").innerHTML = tours.filter(x => x.destacado).map(x => VR.tarjetaTour(x, enReserva.has(x.slug))).join("");

    // Destinos
    document.getElementById("lista-destinos").innerHTML = Object.keys(DESTINOS).map(k => {
      const d = DESTINOS[k];
      const portada = tours.find(x => x.slug === d.portada);
      const n = cuenta("destino", k);
      return '<a class="destino" href="tours.html?d=' + k + '"><img loading="lazy" src="' + esc(portada ? portada.imagen : "") + '" alt="">' +
        "<div><h3>" + esc(d.nombre) + "</h3><small>" + esc(tx(d.texto)) + " · " + n + " " + (n === 1 ? t("tour1") : t("tours")) + "</small></div></a>";
    }).join("");

    document.getElementById("yates-num").textContent = cuenta("categoria", "yates");
    VR.animarAlVer();
  }).catch(err => {
    console.error(err);
    VR.animarAlVer();
  });
  VR.animarAlVer();
})();
