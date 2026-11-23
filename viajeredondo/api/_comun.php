<?php
declare(strict_types=1);

/**
 * Funciones compartidas de la API pública.
 * Las solicitudes se guardan en privado/datos/solicitudes.json (bloqueado al público).
 * Toda lectura/escritura pasa por aquí para que migrar a MySQL sea cambiar solo este archivo.
 */

date_default_timezone_set('America/Cancun');

const RUTA_PRIVADA = __DIR__ . '/../privado';
const RUTA_DATOS = RUTA_PRIVADA . '/datos';
const RUTA_TOURS = __DIR__ . '/../data/tours.json';

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $base = ['whatsapp' => '529987672154', 'tipo_cambio' => 18, 'max_tours' => 5, 'limite_por_hora' => 12];
        $archivo = RUTA_PRIVADA . '/config.php';
        $cfg = is_file($archivo) ? array_merge($base, (array) require $archivo) : $base;
    }
    return $cfg;
}

function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ruta_coleccion(string $nombre): string
{
    if (!preg_match('/^[a-z_]+$/', $nombre)) {
        throw new InvalidArgumentException('Colección inválida');
    }
    if (!is_dir(RUTA_DATOS)) {
        @mkdir(RUTA_DATOS, 0750, true);
        @file_put_contents(RUTA_DATOS . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return RUTA_DATOS . '/' . $nombre . '.json';
}

function coleccion(string $nombre): array
{
    $ruta = ruta_coleccion($nombre);
    if (!is_file($ruta)) {
        return [];
    }
    $fp = fopen($ruta, 'rb');
    flock($fp, LOCK_SH);
    $txt = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $datos = json_decode((string) $txt, true);
    return is_array($datos) ? $datos : [];
}

/** Modifica una colección con candado. $fn recibe la lista por referencia. */
function modificar(string $nombre, callable $fn)
{
    $ruta = ruta_coleccion($nombre);
    $fp = fopen($ruta, 'c+b');
    if (!$fp || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('No se pudo abrir ' . $nombre);
    }
    try {
        $datos = json_decode((string) stream_get_contents($fp), true);
        if (!is_array($datos)) {
            $datos = [];
        }
        $resultado = $fn($datos);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fflush($fp);
        return $resultado;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** Catálogo indexado por slug */
function tours_por_slug(): array
{
    $d = json_decode((string) file_get_contents(RUTA_TOURS), true);
    $out = [];
    foreach (($d['tours'] ?? []) as $t) {
        $out[$t['slug']] = $t;
    }
    return $out;
}

function texto_idioma($obj, string $idioma): string
{
    if (is_string($obj)) {
        return $obj;
    }
    return (string) ($obj[$idioma] ?? $obj['es'] ?? '');
}

function a_pesos(?float $monto, ?string $moneda): float
{
    if ($monto === null) {
        return 0.0;
    }
    return $moneda === 'USD' ? $monto * (float) config()['tipo_cambio'] : $monto;
}

/**
 * Misma lógica que VR.calcular() en assets/js/comun.js.
 * Devuelve [detalle, moneda, total|null].
 */
function calcular_item(array $tour, array $sel, string $idioma): array
{
    $pax = (int) $sel['adultos'] + count($sel['menores']);
    $horas = $idioma === 'en' ? 'hours' : 'horas';

    if (!empty($tour['yate'])) {
        $tarifas = $tour['yate']['tarifas'];
        $tarifa = $tarifas[0];
        foreach ($tarifas as $x) {
            if ((int) $x['horas'] === (int) ($sel['horas'] ?? 0)) {
                $tarifa = $x;
            }
        }
        $muelle = (float) ($tour['yate']['muelle_por_persona'] ?? 0) * $pax;
        return [$tarifa['horas'] . ' ' . $horas . ($muelle ? ' + muelle' : ''), 'MXN', (float) $tarifa['precio'] + $muelle];
    }
    if (!empty($sel['extra']) && !empty($tour['extra'])) {
        $p = $tour['extra']['precio'];
        return [texto_idioma($tour['extra']['etiqueta'], $idioma), $p['moneda'] ?? 'MXN', $p['monto'] === null ? null : (float) $p['monto']];
    }
    $ops = [];
    if (!empty($tour['opciones_por_dia'])) {
        foreach ($tour['opciones_por_dia'] as $g) {
            foreach ($g['opciones'] as $o) {
                $ops[] = [texto_idioma($g['dia'], $idioma) . ' · ' . texto_idioma($o['etiqueta'], $idioma), $o['precio']];
            }
        }
    } elseif (!empty($tour['opciones'])) {
        foreach ($tour['opciones'] as $o) {
            $ops[] = [texto_idioma($o['etiqueta'], $idioma), $o['precio']];
        }
    }
    $i = $sel['opcion'];
    if ($ops && $i !== null && isset($ops[$i])) {
        [$etiqueta, $p] = $ops[$i];
        return [$etiqueta, $p['moneda'] ?? 'MXN', $p['monto'] === null ? null : (float) $p['monto'] * $pax];
    }
    $p = $tour['precio'] ?? [];
    return ['', $p['moneda'] ?? 'MXN', ($p['monto'] ?? null) === null ? null : (float) $p['monto'] * $pax];
}

function limpiar_texto($v, int $max): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string) $v));
    return mb_substr(strip_tags($s), 0, $max);
}

/** Datos del cupón que se pueden mostrar al cliente */
function cupon_publico(array $s): array
{
    return [
        'folio' => $s['folio'],
        'creado' => $s['creado'],
        'estado' => $s['estado'],
        'cliente' => $s['cliente'],
        'vendedor' => $s['vendedor'],
        'items' => $s['items'],
        'total_mxn' => $s['total_mxn'],
        'tipo_cambio' => $s['tipo_cambio'],
    ];
}
