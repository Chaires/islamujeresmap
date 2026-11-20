<?php
declare(strict_types=1);

/** Acciones del panel que se llaman desde JavaScript (escáner y sincronización sin conexión). */
require __DIR__ . '/../app/auth.php';

$u = usuario_actual();
if (!$u) responder_json(['ok' => false, 'error' => 'Tu sesión terminó. Vuelve a entrar.', 'sesion' => false], 401);

$accion = (string) ($_GET['accion'] ?? '');

/** Datos del canje que ve quien escanea. */
function ficha(array $c): array
{
    $previo = $c['estatus'] !== 'usado' ? uso_previo($c) : null;
    return [
        'id' => $c['id'], 'codigo' => $c['codigo'], 'estatus' => $c['estatus'], 'estatus_txt' => ESTATUS[$c['estatus']] ?? $c['estatus'],
        'cupon' => $c['cupon'], 'opcion' => $c['opcion'], 'precio' => (int) $c['precio'], 'personas' => (int) $c['personas'],
        'venta' => calcular_venta($c), 'nombre' => $c['nombre'], 'telefono' => $c['telefono'], 'correo' => $c['correo'],
        'fecha' => $c['fecha'], 'fecha_txt' => fecha_larga((string) $c['fecha']), 'vendedor' => $c['vendedor_codigo'] ?? null,
        'validado_en' => $c['validado_en'] ?? null, 'validado_por' => $c['validado_por_nombre'] ?? null,
        'uso_previo' => $previo ? ['codigo' => $previo['codigo'], 'validado_en' => $previo['validado_en'] ?? null] : null,
    ];
}

if ($accion === 'buscar') {
    if (!in_array($u['rol'], ['superadmin', 'admin'], true)) responder_json(['ok' => false, 'error' => 'Sin permiso.'], 403);
    $codigo = normalizar_codigo((string) ($_GET['codigo'] ?? ''));
    $c = $codigo ? buscar('canjes', 'codigo', $codigo) : null;
    if (!$c) responder_json(['ok' => false, 'error' => 'No existe ningún cupón con el código ' . ($codigo ?: '(vacío)') . '. Puede ser falso.', 'codigo' => $codigo], 404);
    responder_json(['ok' => true, 'canje' => ficha($c)]);
}

if ($accion === 'validar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($u['rol'], ['superadmin', 'admin'], true)) responder_json(['ok' => false, 'error' => 'Sin permiso.'], 403);
    revisar_csrf();
    $d = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $codigo = normalizar_codigo((string) ($d['codigo'] ?? ''));
    $c = $codigo ? buscar('canjes', 'codigo', $codigo) : null;
    if (!$c) responder_json(['ok' => false, 'error' => 'No existe el cupón ' . $codigo . '.'], 404);
    if ($c['estatus'] === 'usado') responder_json(['ok' => false, 'error' => 'Este cupón ya se había usado.', 'canje' => ficha($c)], 409);
    if ($c['estatus'] === 'cancelado') responder_json(['ok' => false, 'error' => 'Este cupón está cancelado.', 'canje' => ficha($c)], 409);
    if (($previo = uso_previo($c)) && empty($d['forzar'])) {
        responder_json(['ok' => false, 'error' => 'Este cliente ya usó este cupón antes (código ' . $previo['codigo'] . ').', 'canje' => ficha($c)], 409);
    }
    $c = cambiar_estatus($c['id'], 'usado', $u);
    if ($c && !empty($d['escaneado_en'])) {
        // Validado sin conexión: guardamos también la hora real del escaneo
        $c = actualizar('canjes', 'id', $c['id'], ['escaneado_en' => substr(preg_replace('/[^0-9T:\- ]/', '', (string) $d['escaneado_en']), 0, 19)]);
    }
    responder_json(['ok' => true, 'canje' => ficha($c)]);
}

responder_json(['ok' => false, 'error' => 'Acción no válida.'], 400);
