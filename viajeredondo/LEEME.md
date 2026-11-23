# Viaje Redondo · sitio público (nuevo)

Sitio móvil para clientes: catálogo de tours, detalle, reserva y cupón con folio que se envía por WhatsApp.
Mismo estilo de tecnología que `sitio/` (HTML, CSS y JS sin compilación + PHP pequeño para cPanel).

## Páginas
- `index.html` portada: buscador, categorías, favoritos, destinos, yates, cómo reservar.
- `tours.html` catálogo con filtros (`?d=destino`, `?c=categoria`, `?q=texto`).
- `tour.html?t=slug` detalle con calculadora de precio estimado.
- `reservar.html` hasta 5 tours + datos del cliente + código de vendedor.
- `cupon.html` cupón con folio, estado "pendiente" y botón para enviarlo por WhatsApp.

## Flujo de reserva (igual que hoy)
1. El cliente arma su reserva y genera el cupón.
2. `api/solicitud.php` recalcula precios con `data/tours.json`, guarda la solicitud como **pendiente**
   en `privado/datos/solicitudes.json` y devuelve folio (`VR-AAMMDD-XXXX`).
3. El cliente envía el cupón por WhatsApp; se valida con proveedores y se cobra.
Si el servidor no responde, el cupón se genera en el navegador y el mensaje de WhatsApp sale igual.
Las solicitudes guardadas quedan listas para el panel/CRM (vendedor, estado, historial).

## Dónde cambiar cosas
- Colores, fuentes: `assets/css/marca.css`. Logos: `assets/img/logo/`.
- WhatsApp, tipo de cambio, redes: `assets/js/config.js` (y `privado/config.php` en el servidor).
- Tours: `data/tours.json` (61 tours tomados de viajeredondo.net/experiences/tours/ el 2026-11-23).
- Destinos y categorías: `assets/js/comun.js` (`DESTINOS`, `CATEGORIAS`).
- Código de vendedor: cualquier enlace con `?ref=CODIGO` lo guarda 30 días en el navegador del cliente.

## Probar en local
```
cd viajeredondo && php -S 127.0.0.1:8090
```
