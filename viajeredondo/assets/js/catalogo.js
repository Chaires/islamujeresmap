(function () {
  "use strict";
  const { t, tx, esc, CATEGORIAS, DESTINOS } = VR;

  VR.textosPagina = {
    catEtiqueta: { es: "Catálogo", en: "Catalog" },
    catTitulo: { es: "Tours y experiencias", en: "Tours & experiences" },
    catSub: { es: "Filtra por destino o tipo de experiencia, abre cualquier tour para ver qué incluye y agrégalo a tu reserva.", en: "Filter by destination or experience type, open any tour to see what's included and add it to your booking." },
    buscar: { es: "Buscar", en: "Search" },
    buscaPh: { es: "Buscar tour…", en: "Search tours…" },
    vacio: { es: "No encontramos tours con esos filtros. Prueba con otra búsqueda o escríbenos y lo armamos a tu medida.", en: "No tours match those filters. Try another search or message us and we'll tailor one for you." },
    resultado: { es: "{n} experiencias", en: "{n} experiences" },
    resultado1: { es: "1 experiencia", en: "1 experience" },
    ordenRelevancia: { es: "Recomendados", en: "Recommended" },
    ordenMenor: { es: "Precio: menor a mayor", en: "Price: low to high" },
    ordenMayor: { es: "Precio: mayor a menor", en: "Price: high to low" }
  };

  VR.encabezado();
  VR.pie();
  VR.traducirPagina();

  const q = new URLSearchParams(location.search);
  const estado = {
    texto: q.get("q") || "",
    destino: DESTINOS[q.get("d")] ? q.get("d") : "",
    categoria: CATEGORIAS[q.get("c")] ? q.get("c") : "",
    orden: "relevancia"
  };

  const $texto = document.getElementById("f-texto");
  const $destino = document.getElementById("f-destino");
  const $orden = document.getElementById("f-orden");
  const $cats = document.getElementById("f-categorias");

  $texto.value = estado.texto;
  $destino.innerHTML = '<option value="">' + t("todosDestinos") + "</option>" +
    Object.keys(DESTINOS).map(k => '<option value="' + k + '">' + esc(DESTINOS[k].nombre) + "</option>").join("");
  $destino.value = estado.destino;
  $orden.innerHTML = ["relevancia", "menor", "mayor"].map(k => '<option value="' + k + '">' +
    t({ relevancia: "ordenRelevancia", menor: "ordenMenor", mayor: "ordenMayor" }[k]) + "</option>").join("");

  // Quita acentos para que "cancun" encuentre "Cancún"
  const normal = s => String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

  let tours = [];

  function pintarPildoras() {
    const claves = [""].concat(Object.keys(CATEGORIAS));
    $cats.innerHTML = claves.map(k => {
      const n = tours.filter(x => (!k || x.categoria === k) && (!estado.destino || x.destino === estado.destino)).length;
      const etiqueta = k ? CATEGORIAS[k].ico + " " + esc(tx(CATEGORIAS[k])) : t("todos");
      return '<button type="button" role="tab" class="pildora' + (estado.categoria === k ? " activa" : "") + '" data-c="' + k + '" aria-selected="' + (estado.categoria === k) + '">' +
        etiqueta + " <small>(" + n + ")</small></button>";
    }).join("");
  }

  function precioOrden(x) {
    const p = VR.precioTarjeta(x);
    return p.monto == null ? Infinity : VR.aPesos(p.monto, p.moneda);
  }

  function pintar() {
    const busca = normal(estado.texto.trim());
    let lista = tours.filter(x =>
      (!estado.destino || x.destino === estado.destino) &&
      (!estado.categoria || x.categoria === estado.categoria) &&
      (!busca || normal(tx(x.titulo) + " " + tx(x.descripcion) + " " + (DESTINOS[x.destino] || {}).nombre + " " + tx(CATEGORIAS[x.categoria])).includes(busca))
    );
    if (estado.orden === "menor") lista = lista.slice().sort((a, b) => precioOrden(a) - precioOrden(b));
    if (estado.orden === "mayor") lista = lista.slice().sort((a, b) => precioOrden(b) - precioOrden(a));

    const enReserva = new Set(VR.leerReserva().map(x => x.slug));
    document.getElementById("rejilla").innerHTML = lista.map(x => VR.tarjetaTour(x, enReserva.has(x.slug))).join("");
    document.getElementById("vacio").classList.toggle("oculto", lista.length > 0);
    document.getElementById("resultado").textContent = lista.length === 1 ? t("resultado1") : t("resultado", { n: lista.length });

    const partes = [];
    if (estado.categoria) partes.push(tx(CATEGORIAS[estado.categoria]));
    if (estado.destino) partes.push(DESTINOS[estado.destino].nombre);
    document.getElementById("titulo-catalogo").textContent = partes.length ? partes.join(" · ") : t("catTitulo");

    // Mantiene los filtros en la URL para poder compartir el enlace
    const u = new URLSearchParams();
    if (estado.destino) u.set("d", estado.destino);
    if (estado.categoria) u.set("c", estado.categoria);
    if (estado.texto) u.set("q", estado.texto);
    history.replaceState(null, "", "tours.html" + (u.toString() ? "?" + u : ""));
  }

  $texto.addEventListener("input", () => { estado.texto = $texto.value; pintar(); });
  $destino.addEventListener("change", () => { estado.destino = $destino.value; pintarPildoras(); pintar(); });
  $orden.addEventListener("change", () => { estado.orden = $orden.value; pintar(); });
  $cats.addEventListener("click", e => {
    const b = e.target.closest("[data-c]");
    if (!b) return;
    estado.categoria = b.dataset.c;
    pintarPildoras();
    pintar();
  });

  VR.cargarTours().then(lista => {
    tours = lista;
    pintarPildoras();
    pintar();
    const activa = $cats.querySelector(".activa");
    if (activa && activa.scrollIntoView) activa.scrollIntoView({ inline: "center", block: "nearest" });
  });
})();
