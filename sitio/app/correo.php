<?php
declare(strict_types=1);

/**
 * Correo de confirmación (VERSIÓN 2).
 * Está apagado: se activa poniendo 'correo_activo' => true en privado/config.php
 * y creando en cPanel la cuenta de correo del remitente.
 * Usa mail() de PHP, que en GoDaddy sale por su propio servidor de correo.
 */
function enviar_correo_confirmacion(array $canje): bool
{
    $c = config();
    if (empty($c['correo_activo']) || empty($c['correo_remitente'])) {
        return false;
    }
    $negocio = (string) $c['nombre_negocio'];
    $asunto = '=?UTF-8?B?' . base64_encode('Tu cupón ' . $canje['codigo'] . ' · ' . $canje['cupon']) . '?=';
    $wallet = '';
    if (wallet_configurado()) {
        $url = rtrim((string) $c['url_sitio'], '/') . '/api/wallet.php?codigo=' . urlencode($canje['codigo']) . '&t=' . token_codigo($canje['codigo']);
        $wallet = '<p><a href="' . e($url) . '" style="display:inline-block;background:#000;color:#fff;padding:12px 22px;border-radius:30px;text-decoration:none;font-weight:bold">Agregar a Google Wallet</a></p>';
    }
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#12324a">'
        . '<h2 style="color:#0bb5b5">¡Tu cupón está listo, ' . e(explode(' ', $canje['nombre'])[0]) . '!</h2>'
        . '<p><b>' . e($canje['cupon']) . '</b><br>' . e($canje['opcion']) . '</p>'
        . '<p style="font-size:24px;font-family:monospace;letter-spacing:3px;background:#fff8ec;padding:10px;text-align:center">' . e($canje['codigo']) . '</p>'
        . '<p>Fecha: <b>' . e(fecha_larga($canje['fecha'])) . '</b><br>Personas: <b>' . (int) $canje['personas'] . '</b></p>'
        . $wallet
        . '<p>Presenta este código en taquilla o en el negocio.</p>'
        . '<p style="color:#4f6b80;font-size:12px">' . e($negocio) . '</p></div>';

    $cabeceras = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . '=?UTF-8?B?' . base64_encode($negocio) . '?= <' . $c['correo_remitente'] . '>',
        'Reply-To: ' . $c['correo_remitente'],
    ];
    if (!empty($c['correo_copia'])) {
        $cabeceras[] = 'Bcc: ' . $c['correo_copia'];
    }
    return @mail($canje['correo'], $asunto, $html, implode("\r\n", $cabeceras), '-f' . $c['correo_remitente']);
}
