<?php
declare(strict_types=1);

/**
 * POST api/solicitud.php
 * Recibe la reserva del sitio, recalcula los precios con el catálogo del servidor,
 * la guarda como "pendiente" y devuelve el folio y la llave del cupón.
 */

require __DIR__ . '/_comun.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$crudo = file_get_contents('php://input', false, null, 0, 32768);
$entrada = json_decode((string) $crudo, true);
if (!is_array($entrada)) {
    responder(['ok' => false, 'error' => 'Solicitud inválida'], 400);
}

$cfg = config();
$idioma = ($entrada['idioma'] ?? 'es') === 'en' ? 'en' : 'es';

// Límite sencillo por IP para evitar abuso
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|vr');
$bloqueado = modificar('limites', function (array &$l) use ($ipHash, $cfg) {
    $ahora = time();
    foreach ($l as $k => $v) {
        $l[$k] = array_values(array_filter($v, fn($t) => $t > $ahora - 3600));
        if (!$l[$k]) {
            unset($l[$k]);
        }
    }
    $l[$ipHash] = $l[$ipHash] ?? [];
    if (count($l[$ipHash]) >= (int) $cfg['limite_por_hora']) {
        return true;
    }
    $l[$ipHash][] = $ahora;
    return false;
});
if ($bloqueado) {
    responder(['ok' => false, 'error' => 'Demasiadas solicitudes, intenta más tarde.'], 429);
}

$c = (array) ($entrada['cliente'] ?? []);
$cliente = [
    'nombre' => limpiar_texto($c['nombre'] ?? '', 80),
    'whatsapp' => limpiar_texto($c['whatsapp'] ?? '', 25),
    'correo' => limpiar_texto($c['correo'] ?? '', 80),
    'hotel' => limpiar_texto($c['hotel'] ?? '', 100),
    'comentarios' => limpiar_texto($c['comentarios'] ?? '', 600),
];
if ($cliente['nombre'] === '' || strlen(preg_replace('/\D/', '', $cliente['whatsapp'])) < 7) {
    responder(['ok' => false, 'error' => 'Faltan nombre o WhatsApp'], 422);
}
$vendedor = strtoupper(limpiar_texto($entrada['vendedor'] ?? '', 30));
if ($vendedor !== '' && !preg_match('/^[A-Z0-9_-]+$/', $vendedor)) {
    $vendedor = '';
}

$tours = tours_por_slug();
$items = [];
$total = 0.0;
foreach (array_slice((array) ($entrada['items'] ?? []), 0, (int) $cfg['max_tours']) as $it) {
    if (!is_array($it) || !isset($tours[$it['slug'] ?? ''])) {
        continue;
    }
    $tour = $tours[$it['slug']];
    $fecha = (string) ($it['fecha'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = '';
    }
    $menores = array_slice(array_map(fn($e) => max(0, min(17, (int) $e)), (array) ($it['menores'] ?? [])), 0, 20);
    $sel = [
        'adultos' => max(1, min(60, (int) ($it['adultos'] ?? 1))),
        'menores' => $menores,
        'opcion' => isset($it['opcion']) && $it['opcion'] !== null ? (int) $it['opcion'] : null,
        'horas' => isset($it['horas']) ? (int) $it['horas'] : null,
        'extra' => !empty($it['extra']),
    ];
    [$detalle, $moneda, $monto] = calcular_item($tour, $sel, $idioma);
    $total += a_pesos($monto, $moneda);
    $items[] = [
        'slug' => $tour['slug'],
        'titulo' => texto_idioma($tour['titulo'], $idioma),
        'imagen' => $tour['imagen'],
        'fecha' => $fecha,
        'adultos' => $sel['adultos'],
        'menores' => $menores,
        'opcion' => $sel['opcion'],
        'horas' => $sel['horas'],
        'extra' => $sel['extra'],
        'detalle' => $detalle,
        'moneda' => $moneda,
        'total' => $monto,
    ];
}
if (!$items) {
    responder(['ok' => false, 'error' => 'La reserva no tiene tours válidos'], 422);
}

$llave = bin2hex(random_bytes(12));
$registro = modificar('solicitudes', function (array &$lista) use ($cliente, $vendedor, $items, $total, $idioma, $llave, $cfg, $ipHash) {
    $existentes = array_flip(array_column($lista, 'folio'));
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $sufijo = '';
        for ($i = 0; $i < 4; $i++) {
            $sufijo .= $letras[random_int(0, strlen($letras) - 1)];
        }
        $folio = 'VR-' . date('ymd') . '-' . $sufijo;
    } while (isset($existentes[$folio]));

    $r = [
        'folio' => $folio,
        'llave' => hash('sha256', $llave),
        'creado' => date('c'),
        'estado' => 'pendiente',
        'origen' => 'sitio',
        'idioma' => $idioma,
        'cliente' => $cliente,
        'vendedor' => $vendedor,
        'items' => $items,
        'total_mxn' => (int) round($total),
        'tipo_cambio' => (float) $cfg['tipo_cambio'],
        'ip' => substr($ipHash, 0, 16),
        'historial' => [['fecha' => date('c'), 'estado' => 'pendiente', 'nota' => 'Solicitud creada desde el sitio']],
    ];
    $lista[] = $r;
    return $r;
});

responder(['ok' => true, 'llave' => $llave, 'cupon' => cupon_publico($registro)]);
