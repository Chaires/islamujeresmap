<?php
declare(strict_types=1);

require __DIR__ . '/../app/negocio.php';
require __DIR__ . '/../app/correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$d = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($d)) {
    responder_json(['ok' => false, 'error' => 'Datos inválidos'], 400);
}

// Trampa para robots: si el campo oculto viene lleno, fingimos éxito sin guardar.
if (!empty($d['sitio_web'])) {
    responder_json(['ok' => true, 'codigo' => generar_codigo(), 'wallet' => false, 'correo' => false]);
}

$limpio = static fn($v, int $max) => mb_substr(trim(strip_tags((string) ($v ?? ''))), 0, $max);
$canje = [
    'cupon_id' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($d['cupon_id'] ?? ''))),
    'cupon'    => $limpio($d['cupon'] ?? '', 80),
    'opcion'   => $limpio($d['opcion'] ?? '', 80),
    'precio'   => max(0, (int) ($d['precio'] ?? 0)),
    'nombre'   => $limpio($d['nombre'] ?? '', 80),
    'telefono' => $limpio($d['telefono'] ?? '', 20),
    'correo'   => $limpio($d['correo'] ?? '', 120),
    'personas' => (int) ($d['personas'] ?? 0),
    'pais'     => $limpio($d['pais'] ?? '', 60),
    'estado'   => $limpio($d['estado'] ?? '', 60),
    'fecha'    => (string) ($d['fecha'] ?? ''),
    'idioma'   => in_array($d['idioma'] ?? '', ['es', 'en', 'pt', 'zh'], true) ? $d['idioma'] : 'es',
    'moneda'   => in_array($d['moneda'] ?? '', ['MXN', 'USD', 'BRL', 'CNY'], true) ? $d['moneda'] : 'MXN',
    'precio_moneda'  => max(0, round((float) ($d['precio_moneda'] ?? 0), 2)),
    'cupon_cliente'  => $limpio($d['cupon_cliente'] ?? '', 80),
    'opcion_cliente' => $limpio($d['opcion_cliente'] ?? '', 80),
];
$codigoVendedor = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['vendedor'] ?? '')));
if ($canje['moneda'] === 'MXN') {
    $canje['precio_moneda'] = $canje['precio'];
}

$errores = [];
if ($canje['cupon_id'] === '' || $canje['cupon'] === '') $errores[] = 'cupón';
if (mb_strlen($canje['nombre']) < 2) $errores[] = 'nombre';
if (strlen(preg_replace('/\D/', '', $canje['telefono'])) < 8) $errores[] = 'teléfono';
if (!filter_var($canje['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'correo';
if ($canje['personas'] < 1 || $canje['personas'] > 50) $errores[] = 'personas';
if ($canje['pais'] === '' || mb_strlen($canje['estado']) < 2) $errores[] = 'país/estado';
$f = DateTime::createFromFormat('!Y-m-d', $canje['fecha']);
if (!$f || $f->format('Y-m-d') !== $canje['fecha'] || $canje['fecha'] < date('Y-m-d')) $errores[] = 'fecha';
if ($errores) {
    responder_json(['ok' => false, 'error' => 'Revisa: ' . implode(', ', $errores)], 422);
}

migrar_sqlite();

// Código de vendedor (opcional)
$vendedor = null;
if ($codigoVendedor !== '') {
    $vendedor = vendedor_por_codigo($codigoVendedor);
    if (!$vendedor) {
        responder_json(['ok' => false, 'campo' => 'vendedor', 'error' => 'El código de vendedor no existe.'], 422);
    }
}
$canje['vendedor_id'] = $vendedor['id'] ?? null;
$canje['vendedor_codigo'] = $vendedor['codigo'] ?? null;

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$correoBuscado = mb_strtolower($canje['correo']);
$telBuscado = solo_digitos($canje['telefono']);

try {
    // Todo dentro del mismo candado para que dos envíos simultáneos no se dupliquen.
    $resultado = modificar('canjes', function (array &$datos) use (&$canje, $ip, $correoBuscado, $telBuscado) {
        $haceUnaHora = date('Y-m-d H:i:s', time() - 3600);
        $recientes = 0;
        foreach ($datos as $c) {
            if (($c['ip'] ?? '') === $ip && ($c['creado'] ?? '') > $haceUnaHora) $recientes++;
            if (($c['cupon_id'] ?? '') !== $canje['cupon_id']) continue;
            $mismo = mb_strtolower((string) ($c['correo'] ?? '')) === $correoBuscado
                || ($telBuscado !== '' && solo_digitos((string) ($c['telefono'] ?? '')) === $telBuscado);
            if (!$mismo) continue;
            if (($c['estatus'] ?? '') === 'usado') return ['usado' => $c];
            if (in_array($c['estatus'] ?? '', ['pendiente', 'confirmado'], true)) return ['existente' => $c];
        }
        if ($recientes >= 15) return ['limite' => true];

        $existentes = array_flip(array_column($datos, 'codigo'));
        do {
            $canje['codigo'] = generar_codigo();
        } while (isset($existentes[$canje['codigo']]));
        $canje = ['id' => nuevo_id('cj'), 'codigo' => $canje['codigo']] + $canje
            + ['estatus' => 'pendiente', 'notas' => '', 'ip' => $ip, 'creado' => date('Y-m-d H:i:s')];
        $datos[] = $canje;
        return ['nuevo' => $canje];
    });
} catch (Throwable $ex) {
    error_log('canjear: ' . $ex->getMessage());
    responder_json(['ok' => false, 'error' => 'No pudimos guardar tu cupón. Intenta de nuevo.'], 500);
}

if (isset($resultado['limite'])) {
    responder_json(['ok' => false, 'error' => 'Demasiados canjes seguidos. Intenta más tarde.'], 429);
}
if (isset($resultado['usado'])) {
    responder_json(['ok' => false, 'campo' => 'usado', 'error' => 'Este cupón ya fue usado con ese correo o teléfono.'], 409);
}
if (isset($resultado['existente'])) {
    // Ya lo había pedido y sigue vigente: le regresamos el mismo código.
    $e = $resultado['existente'];
    responder_json([
        'ok' => true, 'repetido' => true, 'codigo' => $e['codigo'], 'token' => token_codigo($e['codigo']),
        'wallet' => wallet_configurado(), 'correo' => false, 'vendedor' => $e['vendedor_codigo'] ?? null,
    ]);
}

$correo = false;
try {
    $correo = enviar_correo_confirmacion($canje);
} catch (Throwable $ex) {
    error_log('correo: ' . $ex->getMessage());
}

responder_json([
    'ok'     => true,
    'codigo' => $canje['codigo'],
    'token'  => token_codigo($canje['codigo']),
    'wallet' => wallet_configurado(),
    'correo' => $correo,
    'vendedor' => $canje['vendedor_codigo'],
]);
