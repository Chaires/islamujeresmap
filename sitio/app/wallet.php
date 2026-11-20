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

const WALLET_IDIOMAS = [
    'es' => ['locale' => 'es-MX', 'cupon' => 'Cupón', 'cliente' => 'Cliente', 'personas' => 'Personas', 'opcion' => 'Opción', 'precio' => 'Precio', 'como' => 'Cómo usarlo', 'presenta' => 'Presenta este código QR en taquilla o en el negocio.', 'gratis' => 'GRATIS', 'wa' => 'Escríbenos por WhatsApp', 'sitio' => 'Más cupones en Isla Mujeres'],
    'en' => ['locale' => 'en-US', 'cupon' => 'Coupon', 'cliente' => 'Guest', 'personas' => 'People', 'opcion' => 'Option', 'precio' => 'Price', 'como' => 'How to use it', 'presenta' => 'Show this QR code at the ticket office or venue.', 'gratis' => 'FREE', 'wa' => 'Message us on WhatsApp', 'sitio' => 'More coupons in Isla Mujeres'],
    'pt' => ['locale' => 'pt-BR', 'cupon' => 'Cupom', 'cliente' => 'Cliente', 'personas' => 'Pessoas', 'opcion' => 'Opção', 'precio' => 'Preço', 'como' => 'Como usar', 'presenta' => 'Apresente este QR code na bilheteria ou no estabelecimento.', 'gratis' => 'GRÁTIS', 'wa' => 'Fale conosco no WhatsApp', 'sitio' => 'Mais cupons em Isla Mujeres'],
    'zh' => ['locale' => 'zh-CN', 'cupon' => '优惠券', 'cliente' => '客人', 'personas' => '人数', 'opcion' => '选项', 'precio' => '价格', 'como' => '使用方法', 'presenta' => '请在售票处或商家出示此二维码。', 'gratis' => '免费', 'wa' => '通过 WhatsApp 联系我们', 'sitio' => '更多女人岛优惠券'],
];

function texto_wallet(string $valor, string $locale = 'es-MX'): array
{
    return ['defaultValue' => ['language' => $locale, 'value' => $valor]];
}

/** Precio en la moneda del cliente y, si no es en pesos, su equivalente en MXN */
function precio_wallet(array $canje, array $tx): string
{
    $mxn = (int) $canje['precio'];
    if ($mxn === 0) {
        return $tx['gratis'];
    }
    $pesos = '$' . number_format($mxn) . ' MXN';
    $moneda = $canje['moneda'] ?? 'MXN';
    if ($moneda === 'MXN' || empty($canje['precio_moneda'])) {
        return $pesos;
    }
    $simbolo = ['USD' => 'US$', 'BRL' => 'R$ ', 'CNY' => '¥'][$moneda] ?? ($moneda . ' ');
    return $simbolo . number_format((float) $canje['precio_moneda']) . ' (' . $pesos . ')';
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
    $tx = WALLET_IDIOMAS[$canje['idioma'] ?? 'es'] ?? WALLET_IDIOMAS['es'];
    $loc = $tx['locale'];
    $titulo = $canje['cupon_cliente'] ?: $canje['cupon'];
    $opcion = $canje['opcion_cliente'] ?: $canje['opcion'];
    $fecha = ($canje['idioma'] ?? 'es') === 'es' ? fecha_larga($canje['fecha']) : date('d/m/Y', strtotime($canje['fecha']));

    $objeto = [
        'id' => $objetoId,
        'classId' => $claseId,
        'state' => 'ACTIVE',
        'cardTitle' => texto_wallet((string) $c['nombre_negocio'], $loc),
        'subheader' => texto_wallet($tx['cupon'] . ' · ' . $fecha, $loc),
        'header' => texto_wallet($titulo, $loc),
        'hexBackgroundColor' => $c['wallet_color'] ?? '#0BB5B5',
        'logo' => [
            'sourceUri' => ['uri' => $base . '/assets/img/wallet-logo.png'],
            'contentDescription' => texto_wallet((string) $c['nombre_negocio'], $loc),
        ],
        'heroImage' => [
            'sourceUri' => ['uri' => $base . '/assets/img/' . rawurlencode($canje['cupon_id']) . '-1.jpg'],
            'contentDescription' => texto_wallet($titulo, $loc),
        ],
        'barcode' => [
            'type' => 'QR_CODE',
            'value' => $canje['codigo'],
            'alternateText' => $canje['codigo'],
        ],
        'textModulesData' => [
            ['id' => 'cliente', 'header' => $tx['cliente'], 'body' => $canje['nombre']],
            ['id' => 'personas', 'header' => $tx['personas'], 'body' => (string) $canje['personas']],
            ['id' => 'opcion', 'header' => $tx['opcion'], 'body' => $opcion],
            ['id' => 'precio', 'header' => $tx['precio'], 'body' => precio_wallet($canje, $tx)],
            ['id' => 'nota', 'header' => $tx['como'], 'body' => $tx['presenta']],
        ],
        'validTimeInterval' => [
            'end' => ['date' => $canje['fecha'] . 'T23:59:59-05:00'],
        ],
        'linksModuleData' => [
            'uris' => [
                ['id' => 'whatsapp', 'uri' => 'https://wa.me/' . preg_replace('/\D/', '', (string) ($c['whatsapp'] ?? '')), 'description' => $tx['wa']],
                ['id' => 'sitio', 'uri' => $base . '/', 'description' => $tx['sitio']],
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
