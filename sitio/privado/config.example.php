<?php
/*
 * CONFIGURACIÓN PRIVADA
 * ---------------------------------------------------------------
 * 1. Copia este archivo y renómbralo a  config.php  (en esta misma carpeta).
 * 2. Llena tus datos.
 * 3. NUNCA subas config.php ni tu llave .json a GitHub.
 *    La carpeta "privado" está bloqueada para el navegador con .htaccess.
 */
return [
    // Dirección pública del sitio, sin "/" al final
    'url_sitio' => 'https://viajeredondo.net/experiences/islamujerescupones',

    'nombre_negocio' => 'Isla Mujeres Pass',

    // WhatsApp Business: 52 + 10 dígitos (el mismo que en assets/js/datos.js)
    'whatsapp' => '529987672154',

    // Frase larga y secreta (cualquier texto de 30+ caracteres). Protege los enlaces de Wallet.
    'secreto' => 'CAMBIA-ESTO-por-una-frase-larga-y-unica-1234567890',

    // Panel: https://viajeredondo.net/experiences/islamujerescupones/admin/
    // Con estos datos entras LA PRIMERA VEZ y quedas como Superadmin. Después creas los demás usuarios
    // desde el panel y estos datos ya no se usan (puedes cambiar tu contraseña en Usuarios).
    'admin_usuario'  => 'admin',
    'admin_password' => 'CAMBIA-ESTA-CONTRASEÑA',

    // ---------------- Google Wallet ----------------
    // Issuer ID: lo ves en https://pay.google.com/business/console  (número largo)
    'wallet_issuer_id' => '',
    // Nombre del archivo .json de la cuenta de servicio de Google Cloud, guardado en esta carpeta "privado"
    'wallet_llave_json' => 'google-wallet-llave.json',
    // Color de fondo del pase
    'wallet_color' => '#0BB5B5',

    // ---------------- Correo (versión 2, apagado) ----------------
    'correo_activo'    => false,
    // Correo de tu dominio creado en cPanel, ej: reservas@viajeredondo.net
    'correo_remitente' => 'reservas@viajeredondo.net',
    // A dónde te llega la copia de cada canje
    'correo_copia'     => '',
];
