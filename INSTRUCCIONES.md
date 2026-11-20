# Isla Mujeres Pass · Cómo subirlo a GoDaddy (cPanel)

Todo lo que se sube está en la carpeta **`sitio/`**.

## 1. Subir los archivos

1. Entra a GoDaddy → **Mis productos** → **Hosting web** → **Administrar** → **cPanel Admin**.
2. Abre **Administrador de archivos** y entra a `public_html`
   (o a una subcarpeta, por ejemplo `public_html/pass`, si quieres que viva en `tu-dominio.com/pass`).
3. Presiona **Cargar** y sube el archivo `isla-mujeres-pass.zip`.
4. Haz clic derecho en el ZIP → **Extract** (Extraer). Después borra el ZIP.
5. Revisa que en el Administrador de archivos esté activada la opción **Mostrar archivos ocultos**
   (Configuración, arriba a la derecha) y que existan los archivos `.htaccess` en la carpeta principal,
   en `app/` y en `privado/`. Son los que bloquean el acceso a tus datos.

## 2. Crear tu archivo de configuración

1. En la carpeta `privado/`, haz clic derecho en `config.example.php` → **Copy** y nómbralo `config.php`.
2. Haz clic derecho en `config.php` → **Edit** y llena:
   - `url_sitio`: tu dirección, por ejemplo `https://islamujerespass.com` (sin `/` al final).
   - `secreto`: cualquier frase larga inventada.
   - `admin_password`: la contraseña para tu panel.
3. Guarda.

Listo: la página ya funciona en tu dominio. Los canjes se guardan solos y los ves en
**`tu-dominio.com/admin/`** (también puedes descargarlos a Excel).

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

## 4. Cambiar textos, precios, fotos y videos

Todo está en **`assets/js/datos.js`** (se edita desde cPanel con clic derecho → Edit):

- **Precios y textos** de cada cupón.
- **Fotos:** sube tus fotos a `assets/img/` con el mismo nombre que la de muestra
  (por ejemplo `catamaran-1.jpg`) o cambia el nombre en `datos.js`. Tamaño ideal: 1200 × 750 px.
- **Videos de Instagram:** pega la liga del reel en `instagram: "https://www.instagram.com/reel/XXXX/"`.
- **Ubicaciones del mapa:** las coordenadas son aproximadas. En Google Maps mantén presionado el lugar
  exacto, copia los dos números y pégalos en `coords: [21.xxxx, -86.xxxx]`.
- **WhatsApp:** el número está en `whatsapp: "529987672154"` (52 + 10 dígitos).

## 5. Correos de confirmación (versión 2)

Ya está programado pero **apagado**. Para activarlo:

1. En cPanel → **Cuentas de correo**, crea por ejemplo `reservas@tu-dominio.com`.
2. En `privado/config.php` pon `correo_activo => true`, tu `correo_remitente` y en `correo_copia`
   el correo donde quieres recibir una copia de cada canje.

## Qué hace cada carpeta

| Carpeta / archivo | Para qué sirve |
| --- | --- |
| `index.html` | La página principal (mapa y cupones) |
| `assets/js/datos.js` | Textos, precios, fotos, videos y ubicaciones |
| `assets/img/` | Fotos y logo |
| `api/` | Guarda los canjes y crea el pase de Google Wallet |
| `admin/` | Tu panel para ver y confirmar canjes |
| `privado/` | Configuración, llave de Google y base de datos (bloqueada al público) |
| `app/` | Código interno (bloqueado al público) |
