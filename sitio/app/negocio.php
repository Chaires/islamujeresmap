<?php
declare(strict_types=1);

/** Reglas del negocio: catálogo, ventas, comisiones y vendedores. */

require_once __DIR__ . '/bootstrap.php';

const ESTATUS = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'usado' => 'Validado', 'cancelado' => 'Cancelado'];
const ROLES = ['superadmin' => 'Superadmin', 'admin' => 'Administrador', 'vendedor' => 'Vendedor'];

/** Catálogo (cupones o puntos). La primera vez se llena con los datos iniciales. */
function catalogo(string $tipo): array
{
    $datos = coleccion($tipo);
    if (!$datos && !is_file(ruta_coleccion($tipo))) {
        $semilla = json_decode((string) @file_get_contents(__DIR__ . '/semilla/' . $tipo . '.json'), true) ?: [];
        modificar($tipo, function (array &$d) use ($semilla) {
            if (!$d) $d = $semilla;
        });
        $datos = coleccion($tipo);
    }
    usort($datos, fn($a, $b) => ($a['orden'] ?? 99) <=> ($b['orden'] ?? 99));
    return $datos;
}

function cupon_por_id(string $id): ?array
{
    foreach (catalogo('cupones') as $c) {
        if ($c['id'] === $id) return $c;
    }
    return null;
}

/** Venta en pesos de un canje: precio × personas si la opción es "por persona". */
function calcular_venta(array $canje): int
{
    $precio = (int) ($canje['precio'] ?? 0);
    $cupon = cupon_por_id((string) $canje['cupon_id']);
    $porPersona = false;
    foreach ($cupon['precios'] ?? [] as $p) {
        if (($p['concepto'] ?? '') === ($canje['opcion'] ?? '')) {
            $porPersona = !empty($p['por_persona']);
            break;
        }
    }
    return $porPersona ? $precio * max(1, (int) $canje['personas']) : $precio;
}

function porcentaje_comision(string $cuponId): float
{
    $c = cupon_por_id($cuponId);
    return (float) ($c['comision'] ?? 10);
}

/** Venta y comisión (solo cuenta si el cupón ya fue validado). */
function venta_y_comision(array $canje): array
{
    if (($canje['estatus'] ?? '') !== 'usado') {
        return ['venta' => 0, 'comision' => 0.0];
    }
    $venta = isset($canje['venta_mxn']) ? (int) $canje['venta_mxn'] : calcular_venta($canje);
    $pct = isset($canje['comision_pct']) ? (float) $canje['comision_pct'] : porcentaje_comision((string) $canje['cupon_id']);
    return ['venta' => $venta, 'comision' => empty($canje['vendedor_id']) ? 0.0 : round($venta * $pct / 100, 2)];
}

function vendedor_por_codigo(string $codigo): ?array
{
    $codigo = strtoupper(trim($codigo));
    if ($codigo === '') return null;
    foreach (coleccion('usuarios') as $u) {
        if (($u['rol'] ?? '') === 'vendedor' && !empty($u['activo']) && strtoupper((string) ($u['codigo'] ?? '')) === $codigo) {
            return $u;
        }
    }
    return null;
}

function generar_codigo_vendedor(string $nombre): string
{
    $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: 'VEN'), 0, 3));
    $base = str_pad($base, 3, 'X');
    $usados = array_map('strtoupper', array_filter(array_column(coleccion('usuarios'), 'codigo')));
    do {
        $codigo = $base . random_int(100, 999);
    } while (in_array($codigo, $usados, true));
    return $codigo;
}

function solo_digitos(string $t): string
{
    $d = preg_replace('/\D/', '', $t);
    return strlen($d) > 10 ? substr($d, -10) : $d; // compara los últimos 10 dígitos
}

function dinero(float $n): string
{
    return '$' . number_format($n, $n == floor($n) ? 0 : 2);
}

/**
 * Cambia el estatus de un canje. Al marcarlo "usado" guarda quién lo validó, cuándo,
 * la venta en pesos y el % de comisión de ese momento (así no cambia si luego se edita el precio).
 */
function cambiar_estatus(string $id, string $estatus, array $quien): ?array
{
    if (!isset(ESTATUS[$estatus])) return null;
    $actual = buscar('canjes', 'id', $id);
    if (!$actual) return null;
    $cambios = ['estatus' => $estatus];
    if ($estatus === 'usado' && $actual['estatus'] !== 'usado') {
        $cambios += [
            'validado_por' => $quien['id'], 'validado_por_nombre' => $quien['nombre'], 'validado_en' => date('Y-m-d H:i:s'),
            'venta_mxn' => calcular_venta($actual), 'comision_pct' => porcentaje_comision((string) $actual['cupon_id']),
        ];
    } elseif ($estatus !== 'usado') {
        $cambios += ['validado_por' => null, 'validado_por_nombre' => null, 'validado_en' => null, 'venta_mxn' => null, 'comision_pct' => null];
    }
    return actualizar('canjes', 'id', $id, $cambios);
}

/** Nombre de cada usuario por id (para mostrar vendedores). */
function nombres_usuarios(): array
{
    return array_column(coleccion('usuarios'), 'nombre', 'id');
}

/** Normaliza lo que trae un QR o lo que escriben a mano: "im-abc123", "IM ABC123", una URL con ?codigo=... */
function normalizar_codigo(string $texto): string
{
    if (preg_match('/codigo=([A-Za-z0-9-]+)/', $texto, $m)) $texto = $m[1];
    $t = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto));
    if (str_starts_with($t, 'IM')) $t = substr($t, 2);
    return $t === '' ? '' : 'IM-' . $t;
}

/** ¿El mismo cliente (correo o teléfono) ya usó este cupón con otro código? */
function uso_previo(array $canje): ?array
{
    $correo = mb_strtolower((string) $canje['correo']);
    $tel = solo_digitos((string) $canje['telefono']);
    foreach (coleccion('canjes') as $c) {
        if ($c['id'] === $canje['id'] || $c['cupon_id'] !== $canje['cupon_id'] || $c['estatus'] !== 'usado') continue;
        if (mb_strtolower((string) $c['correo']) === $correo || ($tel !== '' && solo_digitos((string) $c['telefono']) === $tel)) return $c;
    }
    return null;
}

/** Catálogo público (solo lo activo) con la misma forma que assets/js/datos.js. */
function catalogo_publico(): array
{
    $cupones = array_values(array_filter(catalogo('cupones'), fn($c) => !empty($c['activo'])));
    $activos = array_flip(array_column($cupones, 'id'));
    $puntos = array_values(array_filter(catalogo('puntos'), fn($p) => !empty($p['activo'])));
    $trad = ['en' => ['puntos' => [], 'cupones' => []], 'pt' => ['puntos' => [], 'cupones' => []], 'zh' => ['puntos' => [], 'cupones' => []]];
    $limpiarTrad = fn($t) => array_filter($t ?? [], fn($v) => $v !== '' && $v !== [] && $v !== null);

    $outP = [];
    foreach ($puntos as $p) {
        foreach ($trad as $l => $_) if ($x = $limpiarTrad($p['trad'][$l] ?? [])) $trad[$l]['puntos'][$p['id']] = $x;
        $outP[] = [
            'id' => $p['id'], 'nombre' => $p['nombre'], 'tipo' => $p['tipo'], 'icono' => $p['icono'], 'color' => $p['color'],
            'coords' => [(float) $p['coords'][0], (float) $p['coords'][1]], 'resumen' => $p['resumen'], 'descripcion' => $p['descripcion'],
            'datos' => $p['datos'] ?? [], 'fotos' => $p['fotos'] ?: ['icono.png'], 'instagram' => $p['instagram'] ?? '',
            'cupon' => isset($activos[$p['cupon'] ?? '']) ? $p['cupon'] : null,
        ];
    }
    $outC = [];
    foreach ($cupones as $c) {
        foreach ($trad as $l => $_) if ($x = $limpiarTrad($c['trad'][$l] ?? [])) $trad[$l]['cupones'][$c['id']] = $x;
        $outC[] = [
            'id' => $c['id'], 'titulo' => $c['titulo'], 'etiqueta' => $c['etiqueta'], 'categoria' => $c['categoria'], 'icono' => $c['icono'],
            'color' => $c['color'], 'resumen' => $c['resumen'], 'descripcion' => $c['descripcion'], 'incluye' => $c['incluye'] ?? [],
            'precios' => array_map(fn($p) => ['concepto' => $p['concepto'], 'normal' => (int) $p['normal'], 'promo' => (int) $p['promo']], $c['precios']),
            'fotos' => $c['fotos'] ?: ['icono.png'], 'instagram' => $c['instagram'] ?? '',
        ];
    }
    return ['puntos' => $outP, 'cupones' => $outC, 'trad' => $trad];
}
