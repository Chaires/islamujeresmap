/* =========================================================================
   DATOS EDITABLES DEL SITIO
   -------------------------------------------------------------------------
   Aquí cambias textos, precios, fotos, videos de Instagram y coordenadas.
   No necesitas tocar app.js.

   - Fotos: pon tus imágenes en assets/img/ y escribe el nombre aquí.
   - Instagram: pega la liga del reel o post, por ejemplo
       "https://www.instagram.com/reel/C1a2B3c4D5e/"
     Si lo dejas vacío ("") se muestra un aviso en lugar del video.
   - Coordenadas: abre Google Maps, mantén presionado el lugar exacto y copia
     los dos números (latitud, longitud).
   ========================================================================= */

const CONFIG_SITIO = {
  nombre: "Isla Mujeres Pass",
  // Número de WhatsApp Business: código de país (52) + 10 dígitos, sin espacios.
  whatsapp: "529987672154",
  moneda: "MXN",
  // Centro y zoom inicial del mapa
  mapaCentro: [21.2335, -86.7315],
  mapaZoom: 13,
};

const PUNTOS = [
  {
    id: "zaza",
    nombre: "Beach Club Zaza",
    tipo: "Beach club",
    icono: "🏖️",
    color: "#ff6b6b",
    coords: [21.2445, -86.7432], // APROXIMADO: confirmar en Google Maps
    resumen: "Camastros frente al mar turquesa, cócteles y música chill todo el día.",
    descripcion:
      "Un beach club relajado en la costa oeste de la isla, con aguas tranquilas ideales para nadar. Disfruta de camastros, servicio de alimentos y bebidas, y algunos de los atardeceres más bonitos de Isla Mujeres.",
    datos: ["Abierto de 10:00 a 18:00", "Daypass con consumo mínimo", "Ideal para familias y parejas"],
    fotos: ["zaza-1.jpg", "zaza-2.jpg", "zaza-3.jpg"],
    instagram: "",
  },
  {
    id: "ferry",
    nombre: "Ultramar Ferry",
    tipo: "Transporte",
    icono: "⛴️",
    color: "#4dabf7",
    coords: [21.2526, -86.7466], // APROXIMADO: confirmar en Google Maps
    resumen: "Llegada principal a la isla desde Cancún. Salidas cada 30 minutos.",
    descripcion:
      "El muelle de Ultramar es la puerta de entrada a Isla Mujeres. Desde aquí estás a pasos del centro, de Playa Norte y de las rentas de carritos de golf.",
    datos: ["Cruce de aprox. 20 minutos", "Salidas desde Puerto Juárez", "A 5 minutos caminando del centro"],
    fotos: ["ferry-1.jpg", "ferry-2.jpg", "ferry-3.jpg"],
    instagram: "",
  },
  {
    id: "punta-norte",
    nombre: "Punta Norte",
    tipo: "Playa",
    icono: "🌴",
    color: "#20c997",
    coords: [21.2611, -86.7514], // APROXIMADO: confirmar en Google Maps
    resumen: "Playa Norte: arena blanca, agua cristalina y poca profundidad.",
    descripcion:
      "Considerada una de las playas más bonitas de México. El mar es tan bajo que puedes caminar decenas de metros mar adentro. Perfecta para ver el atardecer con un coco en mano.",
    datos: ["Agua tranquila y baja", "Atardeceres espectaculares", "Restaurantes y bares cerca"],
    fotos: ["punta-norte-1.jpg", "punta-norte-2.jpg", "punta-norte-3.jpg"],
    instagram: "",
  },
  {
    id: "punta-sur",
    nombre: "Punta Sur",
    tipo: "Mirador",
    icono: "🌅",
    color: "#fcc419",
    coords: [21.2049, -86.7108], // APROXIMADO: confirmar en Google Maps
    resumen: "El primer punto de México donde sale el sol. Acantilados y esculturas.",
    descripcion:
      "En el extremo sur de la isla encontrarás acantilados sobre el mar Caribe, un parque de esculturas y vistas impresionantes. Es el primer lugar de México en recibir los rayos del sol.",
    datos: ["Ve al amanecer", "Parque de esculturas", "Lleva calzado cómodo"],
    fotos: ["punta-sur-1.jpg", "punta-sur-2.jpg", "punta-sur-3.jpg"],
    instagram: "",
  },
  {
    id: "loretta",
    nombre: "Beach Club Loretta",
    tipo: "Beach club",
    icono: "🍹",
    color: "#f783ac",
    coords: [21.2318, -86.7362], // APROXIMADO: confirmar en Google Maps
    resumen: "Ambiente festivo, DJ en vivo y alberca con vista al mar.",
    descripcion:
      "Un beach club con energía: DJ, mixología de autor, alberca y noches temáticas. El lugar ideal para celebrar con amigos.",
    datos: ["DJ los fines de semana", "Noches temáticas", "Reservación recomendada"],
    fotos: ["loretta-1.jpg", "loretta-2.jpg", "loretta-3.jpg"],
    instagram: "",
  },
  {
    id: "letras",
    nombre: "Letras Isla Mujeres",
    tipo: "Foto obligada",
    icono: "📸",
    color: "#845ef7",
    coords: [21.2559, -86.7479], // APROXIMADO: confirmar en Google Maps
    resumen: "Las letras de colores para la foto del recuerdo.",
    descripcion:
      "Ningún viaje está completo sin la foto en las letras de Isla Mujeres. Llega temprano o al atardecer para tener la mejor luz y menos gente.",
    datos: ["Mejor luz: mañana o atardecer", "Acceso gratuito", "Cerca del centro"],
    fotos: ["letras-1.jpg", "letras-2.jpg", "letras-3.jpg"],
    instagram: "",
  },
  {
    id: "ixchel",
    nombre: "Museo Ixchel",
    tipo: "Cultura",
    icono: "🏛️",
    color: "#ff922b",
    coords: [21.2068, -86.7121], // APROXIMADO: confirmar en Google Maps
    resumen: "Historia maya y el templo dedicado a la diosa Ixchel.",
    descripcion:
      "Conoce la historia de Isla Mujeres y de Ixchel, diosa maya de la luna, la fertilidad y el agua. Un recorrido cultural imperdible cerca de Punta Sur.",
    datos: ["Recorrido de 45 minutos", "Ruinas del templo de Ixchel", "Combínalo con Punta Sur"],
    fotos: ["ixchel-1.jpg", "ixchel-2.jpg", "ixchel-3.jpg"],
    instagram: "",
  },
];

/* Precios de ejemplo: cámbialos por los reales. */
const CUPONES = [
  {
    id: "carritos",
    titulo: "Renta de carrito de golf",
    etiqueta: "-20%",
    categoria: "Transporte",
    icono: "🛺",
    color: "#20c997",
    resumen: "Recorre toda la isla a tu ritmo.",
    descripcion:
      "Carrito de golf para 4 personas con gasolina incluida. Recógelo cerca del ferry y recorre la isla de Punta Norte a Punta Sur.",
    incluye: ["Carrito para 4 personas", "Gasolina incluida", "Mapa de la isla", "Seguro básico"],
    precios: [
      { concepto: "Medio día (4 h)", normal: 1200, promo: 960 },
      { concepto: "Día completo (9:00 a 17:00)", normal: 1800, promo: 1440 },
    ],
    fotos: ["carritos-1.jpg", "carritos-2.jpg"],
    instagram: "",
  },
  {
    id: "upgrade-tour",
    titulo: "Upgrade de tour",
    etiqueta: "VIP",
    categoria: "Tours",
    icono: "⭐",
    color: "#fcc419",
    resumen: "Mejora tu tour con barra libre y snorkel.",
    descripcion:
      "Convierte tu tour en una experiencia VIP: barra libre premium, equipo de snorkel y lugar preferente en la embarcación.",
    incluye: ["Barra libre premium", "Equipo de snorkel", "Asiento preferente", "Toalla"],
    precios: [{ concepto: "Por persona", normal: 650, promo: 490 }],
    fotos: ["upgrade-tour-1.jpg", "upgrade-tour-2.jpg"],
    instagram: "",
  },
  {
    id: "tequila",
    titulo: "Tequila tasting",
    etiqueta: "2x1",
    categoria: "Experiencias",
    icono: "🥃",
    color: "#ff922b",
    resumen: "Cata guiada de 5 tequilas mexicanos.",
    descripcion:
      "Aprende a distinguir blanco, reposado y añejo con un experto. Incluye maridaje con botanas mexicanas.",
    incluye: ["5 tequilas premium", "Guía experto", "Maridaje mexicano", "Duración: 1 hora"],
    precios: [{ concepto: "2 personas", normal: 1400, promo: 700 }],
    fotos: ["tequila-1.jpg", "tequila-2.jpg"],
    instagram: "",
  },
  {
    id: "jetski",
    titulo: "Jet ski",
    etiqueta: "-15%",
    categoria: "Aventura",
    icono: "🌊",
    color: "#4dabf7",
    resumen: "Adrenalina sobre el Caribe turquesa.",
    descripcion:
      "Maneja un jet ski con instructor por la costa de la isla. Para 1 o 2 personas por moto.",
    incluye: ["Jet ski para 1 o 2 personas", "Chaleco salvavidas", "Instructor", "Plática de seguridad"],
    precios: [
      { concepto: "30 minutos", normal: 1300, promo: 1105 },
      { concepto: "1 hora", normal: 2200, promo: 1870 },
    ],
    fotos: ["jetski-1.jpg", "jetski-2.jpg"],
    instagram: "",
  },
  {
    id: "yates",
    titulo: "Yates privados",
    etiqueta: "-10%",
    categoria: "Exclusivo",
    icono: "🛥️",
    color: "#3bc9db",
    resumen: "Tu propio yate con capitán para tu grupo.",
    descripcion:
      "Renta un yate privado con capitán y tripulación. Paradas para snorkel, bebidas y un día inolvidable en el mar.",
    incluye: ["Capitán y tripulación", "Bebidas nacionales", "Equipo de snorkel", "Hasta 10 personas"],
    precios: [{ concepto: "4 horas", normal: 18000, promo: 16200 }],
    fotos: ["yates-1.jpg", "yates-2.jpg"],
    instagram: "",
  },
  {
    id: "catamaran",
    titulo: "Sunset en catamarán",
    etiqueta: "-25%",
    categoria: "Tours",
    icono: "⛵",
    color: "#f783ac",
    resumen: "Atardecer en el mar con barra libre y música.",
    descripcion:
      "Navega al atardecer en catamarán con barra libre, música y snacks. La forma más bonita de despedir el día en el Caribe.",
    incluye: ["2.5 horas de navegación", "Barra libre nacional", "Snacks", "Música en vivo"],
    precios: [{ concepto: "Por persona", normal: 1600, promo: 1200 }],
    fotos: ["catamaran-1.jpg", "catamaran-2.jpg"],
    instagram: "",
  },
  {
    id: "fiestas",
    titulo: "Fiestas en beach clubs",
    etiqueta: "Cover gratis",
    categoria: "Eventos",
    icono: "🎉",
    color: "#845ef7",
    resumen: "Noches temáticas, DJ y hookah frente al mar.",
    descripcion:
      "Acceso a las noches temáticas y eventos de los beach clubs de la isla. Incluye cover y bebida de bienvenida.",
    incluye: ["Cover incluido", "Bebida de bienvenida", "Acceso a zona VIP (sujeto a cupo)"],
    precios: [{ concepto: "Por persona", normal: 400, promo: 0 }],
    fotos: ["fiestas-1.jpg", "fiestas-2.jpg"],
    instagram: "",
  },
  {
    id: "promocionales",
    titulo: "Productos promocionales",
    etiqueta: "Regalo",
    categoria: "Tienda",
    icono: "🎁",
    color: "#ff6b6b",
    resumen: "Playeras, gorras y termos de Isla Mujeres.",
    descripcion:
      "Llévate un recuerdo oficial: playeras, gorras y termos con diseño de Isla Mujeres. Recibe un regalo sorpresa en tu compra.",
    incluye: ["Regalo sorpresa", "Diseños exclusivos", "Entrega en punto de venta"],
    precios: [
      { concepto: "Playera", normal: 450, promo: 380 },
      { concepto: "Gorra", normal: 350, promo: 290 },
    ],
    fotos: ["promocionales-1.jpg", "promocionales-2.jpg"],
    instagram: "",
  },
];

const PAISES = [
  "México", "Estados Unidos", "Canadá", "Argentina", "Brasil", "Chile", "Colombia",
  "España", "Francia", "Alemania", "Italia", "Reino Unido", "Perú", "Otro",
];

const ESTADOS_MX = [
  "Aguascalientes", "Baja California", "Baja California Sur", "Campeche", "Chiapas",
  "Chihuahua", "Ciudad de México", "Coahuila", "Colima", "Durango", "Estado de México",
  "Guanajuato", "Guerrero", "Hidalgo", "Jalisco", "Michoacán", "Morelos", "Nayarit",
  "Nuevo León", "Oaxaca", "Puebla", "Querétaro", "Quintana Roo", "San Luis Potosí",
  "Sinaloa", "Sonora", "Tabasco", "Tamaulipas", "Tlaxcala", "Veracruz", "Yucatán", "Zacatecas",
];
