# Isla Mujeres Pass · Cómo subirlo a GoDaddy (cPanel)

Todo lo que se sube está en la carpeta **`sitio/`**.

## 1. Subir los archivos

1. Entra a GoDaddy → **Mis productos** → **Hosting web** → **Administrar** → **cPanel Admin**.
2. Abre **Administrador de archivos** y entra a `public_html`, que es la raíz de viajeredondo.net.
3. Entra a la carpeta `experiences` (créala con **+ Carpeta** si no existe) y dentro crea la carpeta
   `islamujerescupones`. La ruta final queda `public_html/experiences/islamujerescupones/`.
   Si viajeredondo.net no es tu dominio principal en cPanel, su carpeta raíz puede llamarse distinto
   (por ejemplo `public_html/viajeredondo.net`). Úsala en lugar de `public_html`.
4. Dentro de `islamujerescupones`, presiona **Cargar** y sube el archivo `isla-mujeres-pass.zip`.
5. Haz clic derecho en el ZIP → **Extract** (Extraer) ahí mismo. Después borra el ZIP.
6. Revisa que en el Administrador de archivos esté activada la opción **Mostrar archivos ocultos**
   (Configuración, arriba a la derecha) y que existan los archivos `.htaccess` en la carpeta principal,
   en `app/` y en `privado/`. Son los que bloquean el acceso a tus datos.

## 2. Crear tu archivo de configuración

1. En la carpeta `privado/`, haz clic derecho en `config.example.php` → **Copy** y nómbralo `config.php`.
2. Haz clic derecho en `config.php` → **Edit** y llena:
   - `url_sitio`: ya viene puesto `https://viajeredondo.net/experiences/islamujerescupones`. Déjalo así.
   - `secreto`: cualquier frase larga inventada.
   - `admin_usuario` y `admin_password`: con estos datos entras **la primera vez** al panel y quedas como **Superadmin**.
3. Guarda.

Listo: la página queda en **https://viajeredondo.net/experiences/islamujerescupones/** y el panel en
**https://viajeredondo.net/experiences/islamujerescupones/admin/**.

> **Si ya tenías la versión anterior subida:** sube el ZIP nuevo encima (reemplaza archivos) y **no borres**
> `privado/config.php` ni `privado/canjes.sqlite`. La primera vez que entres al panel, los canjes viejos
> pasan solos al sistema nuevo.

> Revisa en cPanel → **Select PHP Version** que uses PHP 8.0 o más nuevo.

## 3. Activar Google Wallet

Esto se hace una sola vez. Es gratis.

1. **Cuenta de emisor:** entra a <https://pay.google.com/business/console>, crea tu perfil de negocio
   y abre **Google Wallet API**. Copia tu **Issuer ID** (un número largo).
2. **Cuenta de servicio:** en <https://console.cloud.google.com>:
   - Crea un proyecto (por ejemplo "isla-mujeres-pass").
   - Busca **Google Wallet API** y presiona **Habilitar**.
   - Ve a **IAM y administración → Cuentas de servicio → Crear cuenta de servicio**.
   - Dentro de la cuenta: **Claves → Agregar clave → JSON**. Se descarga un archivo `.json`.
3. **Dar permiso:** regresa a la consola de Google Pay & Wallet → **Usuarios** → invita el correo
   de la cuenta de servicio (termina en `iam.gserviceaccount.com`) con rol **Desarrollador**.
4. **Subir la llave:** renombra el `.json` a `google-wallet-llave.json` y súbelo a la carpeta `privado/`
   del hosting. **No lo subas a GitHub ni lo mandes por chat.**
5. En `privado/config.php` pega tu Issuer ID en `wallet_issuer_id`.

Mientras Google revisa tu cuenta (modo demo), solo los correos que agregues como
**usuarios de prueba** en la consola pueden guardar el pase. Cuando quieras abrirlo a todos,
pide **acceso de publicación** en la misma consola.

Google pide usar su botón oficial "Add to Google Wallet" cuando el sitio salga al público.
Antes de pedir el acceso de publicación, avísame y cambio el botón negro incluido por el oficial
(<https://developers.google.com/wallet/generic/resources/brand-guidelines>).

## 4. El panel y sus roles

| Rol | Qué puede hacer |
| --- | --- |
| **Superadmin** | Todo: tablero global, cupones (precios, comisión, fotos, traducciones), pines del mapa, usuarios de cualquier rol, metas de incentivos y aprobar o rechazar bonos, escanear, canjes y reportes. |
| **Administrador** | Escanear y validar cupones, ver canjes, tablero de ventas y comisiones, reportes, y crear **solo vendedores**. No puede cambiar precios, cupones ni el mapa. |
| **Vendedor** | Su código único, su liga para compartir, sus ventas y comisiones, su lista de clientes (nombre y teléfono), precios con lo que gana, y sus metas con el botón para solicitar el bono. |

**Cómo funciona una venta**

1. El cliente pide su cupón en la página. Si un vendedor lo atendió, escribe su código (o entra con la liga del vendedor y se llena solo).
2. En el negocio, el Administrador abre **Escanear** en su celular, apunta la cámara al QR del cliente (o escribe el código) y ve si es real.
3. Al presionar **Validar**, el cupón queda **usado**: cuenta como venta, se calcula la comisión del vendedor y ese cliente
   (mismo correo o teléfono) ya no puede volver a usar ese cupón. En su teléfono el cupón aparece como "Ya usado".

**Comisión:** es un % por cupón que pones en **Cupones** (10 % de inicio). Si el precio es "por persona", la venta es precio × personas.

**Incentivos:** en **Incentivos** creas metas (por ventas en pesos o por número de cupones, por semana, por mes o acumuladas) y su premio.
Cuando un vendedor llega a la meta le aparece **Solicitar mi bono**; a ti te sale el aviso en el Inicio y en el menú para aprobar o rechazar.

## 5. Cambiar cupones, precios, fotos y el mapa

Todo se hace desde el panel con tu usuario Superadmin:

- **Cupones:** título, textos, precios (normal y con cupón), si es por persona, comisión, fotos (se suben desde ahí),
  video de Instagram, traducciones y si está visible.
- **Mapa:** agrega, edita o quita pines. Toca el mapa o arrastra el pin al lugar exacto, o pega las coordenadas de Google Maps
  (mantén presionado el lugar en Google Maps y copia los dos números).
- **WhatsApp:** el número está en `assets/js/datos.js` → `whatsapp: "529987672154"` (52 + 10 dígitos) y en `privado/config.php`.

`assets/js/datos.js` queda solo como respaldo: si el servidor fallara, la página usa esos datos.

## 6. Idiomas y monedas

Al abrir la página se pregunta el idioma: Español (pesos), English (dólares), Português (reales) o 中文 (yuanes).
El visitante puede cambiarlo después con el botón de la bandera, arriba.

- **Traducciones de cupones y pines:** en el panel, dentro de cada cupón o pin (Inglés, Portugués, Mandarín).
  Si falta una traducción se muestra en español. Los textos fijos de la página están en `assets/js/idiomas.js`.
- **Tipo de cambio:** se actualiza solo cada 12 horas (archivo `api/tipos.php`). Si el servidor no puede
  consultarlo, usa los valores de `tipoCambioRespaldo` en `idiomas.js`.
- **Aumento de 10 %:** en otros idiomas el precio convertido lleva 10 % extra. Se cambia en
  `recargoExtranjero: 0.10` dentro de `idiomas.js`.
- Los precios se capturan siempre en pesos en el panel; la conversión es automática.

## 7. Reportes y modo sin conexión

- **Reportes:** cada lunes se guarda solo el reporte de la semana anterior y cada día 1 el del mes anterior
  (se crean la primera vez que alguien entra al panel). Los descargas en **Reportes** y abren en Excel. También puedes bajar
  cualquier semana o mes al momento.
- **Opcional:** en cPanel → **Cron Jobs** puedes programar cada lunes a las 6:00:
  `php /home/TU_USUARIO/public_html/experiences/islamujerescupones/app/cron-reportes.php`
- **Sin internet:** la página se guarda en el teléfono del cliente después de la primera visita. Si pide un cupón sin señal,
  se le genera y se registra solo cuando vuelve el internet. El escáner del panel también abre sin señal: las validaciones
  se guardan y se envían al reconectar (el sistema las rechaza si el cupón era falso o ya usado).
- En el celular puedes usar **Agregar a pantalla de inicio** para tener la página o el panel como app.

## 8. Correos de confirmación (versión 2)

Ya está programado pero **apagado**. Para activarlo:

1. En cPanel → **Cuentas de correo**, crea por ejemplo `reservas@viajeredondo.net`.
2. En `privado/config.php` pon `correo_activo => true`, tu `correo_remitente` y en `correo_copia`
   el correo donde quieres recibir una copia de cada canje.

## Qué hace cada carpeta

| Carpeta / archivo | Para qué sirve |
| --- | --- |
| `index.html` | La página principal (mapa y cupones) |
| `assets/js/datos.js` | Respaldo de cupones y mapa (lo normal es editarlos en el panel) |
| `assets/img/` | Fotos y logo |
| `api/` | Guarda los canjes, entrega el catálogo y crea el pase de Google Wallet |
| `admin/` | El panel (Superadmin, Administrador y Vendedor) |
| `privado/` | Configuración, llave de Google, datos en `privado/datos/` y reportes (todo bloqueado al público) |
| `sw.js` | Modo sin conexión de la página |
| `app/` | Código interno (bloqueado al público) |
