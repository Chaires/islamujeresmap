<?php
declare(strict_types=1);

/**
 * Google Wallet: crea el enlace "Agregar a Google Wallet" firmando un JWT
 * con la llave de la cuenta de servicio (no requiere librerías extra).
 * Docs: https://developers.google.com/wallet/generic
 */

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function texto_wallet(string $valor): array
{
    return ['defaultValue' => ['language' => 'es-MX', 'value' => $valor]];
}

function enlace_google_wallet(array $canje): string
{
    $c = config();
    $llave = json_decode((string) file_get_contents(RUTA_PRIVADA . '/' . $c['wallet_llave_json']), true);
    if (!is_array($llave) || empty($llave['client_email']) || empty($llave['private_key'])) {
        throw new RuntimeException('La llave JSON de Google Wallet no es válida.');
    }

    $issuer = preg_replace('/\D/', '', (string) $c['wallet_issuer_id']);
    $base = rtrim((string) $c['url_sitio'], '/');
    $partes = parse_url($base);
    $origen = ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? '') . (isset($partes['port']) ? ':' . $partes['port'] : '');
    $claseId = $issuer . '.isla_mujeres_cupon';
    $objetoId = $issuer . '.' . preg_replace('/[^A-Za-z0-9._-]/', '_', $canje['codigo']);
    $precio = (int) $canje['precio'] === 0 ? 'GRATIS' : '$' . number_format((int) $canje['precio']) . ' MXN';

    $objeto = [
        'id' => $objetoId,
        'classId' => $claseId,
        'state' => 'ACTIVE',
        'cardTitle' => texto_wallet((string) $c['nombre_negocio']),
        'subheader' => texto_wallet('Cupón · ' . fecha_larga($canje['fecha'])),
        'header' => texto_wallet($canje['cupon']),
        'hexBackgroundColor' => $c['wallet_color'] ?? '#0BB5B5',
        'logo' => [
            'sourceUri' => ['uri' => $base . '/assets/img/wallet-logo.png'],
            'contentDescription' => texto_wallet((string) $c['nombre_negocio']),
        ],
        'heroImage' => [
            'sourceUri' => ['uri' => $base . '/assets/img/' . rawurlencode($canje['cupon_id']) . '-1.jpg'],
            'contentDescription' => texto_wallet($canje['cupon']),
        ],
        'barcode' => [
            'type' => 'QR_CODE',
            'value' => $canje['codigo'],
            'alternateText' => $canje['codigo'],
        ],
        'textModulesData' => [
            ['id' => 'cliente', 'header' => 'Cliente', 'body' => $canje['nombre']],
            ['id' => 'personas', 'header' => 'Personas', 'body' => (string) $canje['personas']],
            ['id' => 'opcion', 'header' => 'Opción', 'body' => $canje['opcion'] . ' · ' . $precio],
            ['id' => 'nota', 'header' => 'Cómo usarlo', 'body' => 'Presenta este código QR en taquilla o en el negocio.'],
        ],
        'validTimeInterval' => [
            'end' => ['date' => $canje['fecha'] . 'T23:59:59-05:00'],
        ],
        'linksModuleData' => [
            'uris' => [
                ['id' => 'whatsapp', 'uri' => 'https://wa.me/' . preg_replace('/\D/', '', (string) ($c['whatsapp'] ?? '')), 'description' => 'Escríbenos por WhatsApp'],
                ['id' => 'sitio', 'uri' => $base . '/', 'description' => 'Más cupones en Isla Mujeres'],
            ],
        ],
    ];

    $claims = [
        'iss' => $llave['client_email'],
        'aud' => 'google',
        'typ' => 'savetowallet',
        'iat' => time(),
        'origins' => [$origen],
        'payload' => [
            'genericClasses' => [['id' => $claseId]],
            'genericObjects' => [$objeto],
        ],
    ];

    $cabecera = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $cuerpo = b64url(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $firma = '';
    if (!openssl_sign($cabecera . '.' . $cuerpo, $firma, $llave['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No se pudo firmar el pase de Google Wallet.');
    }
    return 'https://pay.google.com/gp/v/save/' . $cabecera . '.' . $cuerpo . '.' . b64url($firma);
}
