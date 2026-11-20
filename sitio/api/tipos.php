<?php
declare(strict_types=1);

/**
 * Tipo de cambio: cuánto vale 1 peso mexicano en USD, BRL y CNY.
 * Se consulta en open.er-api.com (gratis, sin llave) y se guarda 12 horas.
 * Si falla, la página usa los valores de respaldo de assets/js/idiomas.js.
 */
require __DIR__ . '/../app/bootstrap.php';

$cache = RUTA_PRIVADA . '/tipos-cambio.json';
$datos = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;

if (!is_array($datos) || ($datos['hora'] ?? 0) < time() - 12 * 3600) {
    $ctx = stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]);
    $resp = @file_get_contents('https://open.er-api.com/v6/latest/MXN', false, $ctx);
    $j = $resp ? json_decode($resp, true) : null;
    if (is_array($j) && ($j['result'] ?? '') === 'success' && !empty($j['rates']['USD'])) {
        $datos = [
            'hora' => time(),
            'tasas' => [
                'USD' => (float) $j['rates']['USD'],
                'BRL' => (float) ($j['rates']['BRL'] ?? 0),
                'CNY' => (float) ($j['rates']['CNY'] ?? 0),
            ],
        ];
        @file_put_contents($cache, json_encode($datos));
    }
}

if (!is_array($datos) || empty($datos['tasas'])) {
    responder_json(['ok' => false]);
}
header('Cache-Control: public, max-age=3600');
responder_json(['ok' => true, 'tasas' => $datos['tasas'], 'actualizado' => date('c', (int) $datos['hora'])]);
