/*
 * CONFIGURACIÓN DEL SITIO
 * Cambia aquí el número de WhatsApp, el tipo de cambio y los datos de contacto.
 */
window.VR_CONFIG = {
  nombre: "Viaje Redondo",
  lema: { es: "Tú eliges el destino, nosotros te llevamos", en: "You choose the destination, we take you there" },

  // WhatsApp que recibe las reservas (código de país + número, sin espacios ni signos)
  whatsapp: "529987672154",
  telefonos: ["+52 998 767 2154", "+52 614 128 0651"],
  correo: "ventas@viajeredondo.travel",
  rnt: "0423005e4deb2",

  // Tipo de cambio para mostrar el total aproximado en pesos
  tipoCambio: 18,

  // Máximo de tours en una misma reserva
  maxTours: 5,

  redes: {
    instagram: "https://www.instagram.com/viajeredondo.travel/",
    facebook: "https://www.facebook.com/Viajeredondotravel",
    youtube: "https://www.youtube.com/@viajeredondo.travel",
    tiktok: "https://www.tiktok.com/@chairesalex"
  },

  // Logos (assets/img/logo/). "claro" se usa sobre fondos oscuros.
  logos: {
    claro: "assets/img/logo/icono-blanco.png",
    oscuro: "assets/img/logo/icono-cian.png",
    completo: "assets/img/logo/logo-turquesa.png"
  }
};
