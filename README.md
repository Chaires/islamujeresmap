# Isla Mujeres Pass

Landing móvil para turistas: mapa interactivo de Isla Mujeres con 7 puntos de interés y catálogo
de cupones (carritos de golf, catamarán, jet ski, tequila tasting, beach clubs y más).

- Al canjear un cupón: se guarda el canje, se envía la confirmación por WhatsApp, se descarga el cupón
  en PNG y se agrega a Google Wallet.
- Hecho en HTML, CSS y JavaScript sin dependencias externas de compilación, con un backend PHP pequeño
  para hosting compartido (cPanel).

Todo lo que se sube al hosting está en `sitio/`. Pasos de instalación: [INSTRUCCIONES.md](INSTRUCCIONES.md).

Las credenciales (`sitio/privado/config.php`, la llave `.json` de Google) y la base de datos
nunca se suben al repositorio.
