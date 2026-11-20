<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
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

try {
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $q = $pdo->prepare("SELECT COUNT(*) FROM canjes WHERE ip = ? AND creado > ?");
    $q->execute([$ip, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $q->fetchColumn() >= 15) {
        responder_json(['ok' => false, 'error' => 'Demasiados canjes seguidos. Intenta más tarde.'], 429);
    }

    $ins = $pdo->prepare('INSERT INTO canjes (codigo, cupon_id, cupon, opcion, precio, nombre, telefono, correo, personas, pais, estado, fecha, idioma, moneda, precio_moneda, cupon_cliente, opcion_cliente, ip, creado)
        VALUES (:codigo, :cupon_id, :cupon, :opcion, :precio, :nombre, :telefono, :correo, :personas, :pais, :estado, :fecha, :idioma, :moneda, :precio_moneda, :cupon_cliente, :opcion_cliente, :ip, :creado)');
    for ($intento = 0; $intento < 5; $intento++) {
        $canje['codigo'] = generar_codigo();
        try {
            $ins->execute($canje + ['ip' => $ip, 'creado' => date('Y-m-d H:i:s')]);
            break;
        } catch (PDOException $ex) {
            if ($intento === 4) throw $ex; // código repetido: reintenta
        }
    }
} catch (Throwable $ex) {
    error_log('canjear: ' . $ex->getMessage());
    responder_json(['ok' => false, 'error' => 'No pudimos guardar tu cupón. Intenta de nuevo.'], 500);
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
]);
