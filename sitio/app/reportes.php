<?php
declare(strict_types=1);

/**
 * Reportes semanales y mensuales (CSV que abre en Excel).
 * Se generan solos: al cerrar cada semana (lunes) y cada mes (día 1), la primera vez que alguien
 * entra al panel, o con la tarea programada de cPanel (app/cron-reportes.php).
 */
require_once __DIR__ . '/metricas.php';

const RUTA_REPORTES = RUTA_PRIVADA . '/reportes';

/** Rango y nombre de un reporte. $clave: "2026-W47" (semanal) o "2026-11" (mensual). */
function info_reporte(string $tipo, string $clave): ?array
{
    if ($tipo === 'semanal' && preg_match('/^(\d{4})-W(\d{2})$/', $clave, $m)) {
        $d = (new DateTime())->setISODate((int) $m[1], (int) $m[2])->format('Y-m-d');
        $h = date('Y-m-d', strtotime($d . ' +6 days'));
        return ['desde' => $d, 'hasta' => $h, 'nombre' => 'Semana del ' . date('d/m/Y', strtotime($d)) . ' al ' . date('d/m/Y', strtotime($h))];
    }
    if ($tipo === 'mensual' && preg_match('/^\d{4}-\d{2}$/', $clave)) {
        return ['desde' => $clave . '-01', 'hasta' => date('Y-m-t', strtotime($clave . '-01')), 'nombre' => 'Mes de ' . nombre_periodo('mes', $clave)];
    }
    return null;
}

/** Arma el CSV de un periodo y lo devuelve como texto. */
function csv_reporte(string $tipo, string $clave): string
{
    $i = info_reporte($tipo, $clave);
    $ventas = ventas_en($i['desde'], $i['hasta']);
    $tot = totales($ventas);
    $todos = coleccion('canjes');
    $nuevos = array_filter($todos, fn($c) => ($d = substr((string) $c['creado'], 0, 10)) >= $i['desde'] && $d <= $i['hasta']);
    $nombres = nombres_usuarios();

    $f = fopen('php://temp', 'w+');
    fwrite($f, "\xEF\xBB\xBF");
    $fila = fn(array $x) => fputcsv($f, $x);
    $fila(['Isla Mujeres Pass · Reporte ' . $tipo, $i['nombre']]);
    $fila(['Generado', date('d/m/Y H:i')]);
    $fila([]);
    $fila(['RESUMEN']);
    $fila(['Cupones pedidos en el periodo', count($nuevos)]);
    $fila(['Cupones validados (ventas)', $tot['n']]);
    $fila(['Personas atendidas', $tot['personas']]);
    $fila(['Ventas MXN', $tot['venta']]);
    $fila(['Comisiones MXN', round($tot['comision'], 2)]);
    $fila(['Venta promedio MXN', round($tot['ticket'], 2)]);
    $fila([]);
    $fila(['POR PRODUCTO']);
    $fila(['Cupón', 'Validados', 'Venta MXN', 'Comisión MXN']);
    foreach (agrupar($ventas, fn($c) => $c['cupon']) as $k => $g) $fila([$k, $g['n'], $g['venta'], round($g['comision'], 2)]);
    $fila([]);
    $fila(['POR VENDEDOR']);
    $fila(['Vendedor', 'Código', 'Clientes nuevos', 'Validados', 'Venta MXN', 'Comisión MXN']);
    $clientes = [];
    foreach ($nuevos as $c) if (!empty($c['vendedor_id'])) $clientes[$c['vendedor_id']] = ($clientes[$c['vendedor_id']] ?? 0) + 1;
    $porV = agrupar($ventas, fn($c) => $c['vendedor_id'] ?? '');
    foreach (coleccion('usuarios') as $u) {
        if ($u['rol'] !== 'vendedor') continue;
        $g = $porV[$u['id']] ?? ['n' => 0, 'venta' => 0, 'comision' => 0];
        if (!$g['n'] && empty($clientes[$u['id']])) continue;
        $fila([$u['nombre'], $u['codigo'], $clientes[$u['id']] ?? 0, $g['n'], $g['venta'], round($g['comision'], 2)]);
    }
    if (isset($porV[''])) $fila(['Sin vendedor (directo)', '', '', $porV['']['n'], $porV['']['venta'], 0]);
    $fila([]);
    $fila(['DETALLE DE VENTAS']);
    $fila(['Código', 'Validado', 'Cupón', 'Opción', 'Personas', 'Venta MXN', 'Comisión %', 'Comisión MXN', 'Vendedor', 'Cliente', 'Teléfono', 'Correo', 'País', 'Validó']);
    usort($ventas, fn($a, $b) => strcmp((string) $a['validado_en'], (string) $b['validado_en']));
    foreach ($ventas as $c) {
        $vc = venta_y_comision($c);
        $fila([$c['codigo'], $c['validado_en'] ?? '', $c['cupon'], $c['opcion'], $c['personas'], $vc['venta'], $c['vendedor_id'] ? ($c['comision_pct'] ?? '') : '',
            $vc['comision'] ?: '', $nombres[$c['vendedor_id'] ?? ''] ?? '', $c['nombre'], $c['telefono'], $c['correo'], $c['pais'], $c['validado_por_nombre'] ?? '']);
    }
    rewind($f);
    $txt = stream_get_contents($f);
    fclose($f);
    return $txt;
}

function archivo_reporte(string $tipo, string $clave): string
{
    return RUTA_REPORTES . '/' . $tipo . '-' . $clave . '.csv';
}

/** Genera los reportes de la semana y el mes que ya cerraron (si faltan). Devuelve los creados. */
function reportes_automaticos(): array
{
    if (!is_dir(RUTA_REPORTES)) {
        @mkdir(RUTA_REPORTES, 0750, true);
        @file_put_contents(RUTA_REPORTES . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $pendientes = [
        ['semanal', date('o-\WW', strtotime('monday last week'))],
        ['mensual', date('Y-m', strtotime('first day of last month'))],
    ];
    $creados = [];
    foreach ($pendientes as [$tipo, $clave]) {
        $ruta = archivo_reporte($tipo, $clave);
        if (is_file($ruta)) continue;
        if (@file_put_contents($ruta, csv_reporte($tipo, $clave)) !== false) $creados[] = basename($ruta);
    }
    return $creados;
}

/** Reportes guardados, del más nuevo al más viejo. */
function lista_reportes(): array
{
    $l = [];
    foreach (glob(RUTA_REPORTES . '/*.csv') ?: [] as $r) {
        if (preg_match('/^(semanal|mensual)-(.+)\.csv$/', basename($r), $m) && ($i = info_reporte($m[1], $m[2]))) {
            $l[] = ['archivo' => basename($r), 'tipo' => $m[1], 'clave' => $m[2], 'nombre' => $i['nombre'], 'desde' => $i['desde'], 'creado' => filemtime($r)];
        }
    }
    usort($l, fn($a, $b) => [$b['desde'], $a['tipo']] <=> [$a['desde'], $b['tipo']]);
    return $l;
}
