<?php
declare(strict_types=1);

/** Cálculos para tableros, incentivos y reportes. */

require_once __DIR__ . '/negocio.php';

const PERIODOS = ['semana' => 'Esta semana', 'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado', '30' => 'Últimos 30 días', 'anio' => 'Este año', 'todo' => 'Todo'];

/** [desde, hasta] (fechas Y-m-d incluidas) de un periodo. */
function rango(string $periodo, ?string $hoy = null): array
{
    $t = strtotime($hoy ?? date('Y-m-d'));
    return match ($periodo) {
        'semana' => [date('Y-m-d', strtotime('monday this week', $t)), date('Y-m-d', strtotime('sunday this week', $t))],
        'mes' => [date('Y-m-01', $t), date('Y-m-t', $t)],
        'mes_pasado' => [date('Y-m-01', strtotime('first day of last month', $t)), date('Y-m-t', strtotime('last day of last month', $t))],
        '30' => [date('Y-m-d', strtotime('-29 days', $t)), date('Y-m-d', $t)],
        'anio' => [date('Y-01-01', $t), date('Y-12-31', $t)],
        default => ['2000-01-01', '2999-12-31'],
    };
}

/** Día en que cuenta una venta (cuando se validó). */
function dia_venta(array $c): string
{
    return substr((string) ($c['validado_en'] ?? $c['creado']), 0, 10);
}

/** Canjes validados dentro de un rango (opcional: de un vendedor). */
function ventas_en(string $desde, string $hasta, ?string $vendedorId = null, ?array $canjes = null): array
{
    return array_values(array_filter($canjes ?? coleccion('canjes'), function ($c) use ($desde, $hasta, $vendedorId) {
        if ($c['estatus'] !== 'usado') return false;
        if ($vendedorId !== null && ($c['vendedor_id'] ?? null) !== $vendedorId) return false;
        $d = dia_venta($c);
        return $d >= $desde && $d <= $hasta;
    }));
}

/** Totales de una lista de canjes validados. */
function totales(array $ventas): array
{
    $t = ['n' => 0, 'venta' => 0, 'comision' => 0.0, 'personas' => 0];
    foreach ($ventas as $c) {
        $vc = venta_y_comision($c);
        $t['n']++;
        $t['venta'] += $vc['venta'];
        $t['comision'] += $vc['comision'];
        $t['personas'] += (int) $c['personas'];
    }
    $t['ticket'] = $t['n'] ? $t['venta'] / $t['n'] : 0;
    return $t;
}

/** Agrupa ventas por una llave (cupón, vendedor, día...). */
function agrupar(array $ventas, callable $llave): array
{
    $g = [];
    foreach ($ventas as $c) {
        $k = $llave($c);
        if ($k === null) continue;
        $vc = venta_y_comision($c);
        $g[$k] ??= ['n' => 0, 'venta' => 0, 'comision' => 0.0];
        $g[$k]['n']++;
        $g[$k]['venta'] += $vc['venta'];
        $g[$k]['comision'] += $vc['comision'];
    }
    uasort($g, fn($a, $b) => $b['venta'] <=> $a['venta'] ?: $b['n'] <=> $a['n']);
    return $g;
}

/** Ventas por día para una gráfica de barras (rellena los días sin ventas). */
function serie_diaria(array $ventas, string $desde, string $hasta, int $maxDias = 31): array
{
    if (strtotime($hasta) - strtotime($desde) > ($maxDias - 1) * 86400) $desde = date('Y-m-d', strtotime($hasta . ' -' . ($maxDias - 1) . ' days'));
    if ($hasta > date('Y-m-d')) $hasta = date('Y-m-d');
    $serie = [];
    for ($t = strtotime($desde); $t <= strtotime($hasta); $t += 86400) $serie[date('Y-m-d', $t)] = 0;
    foreach ($ventas as $c) {
        $d = dia_venta($c);
        if (isset($serie[$d])) $serie[$d] += venta_y_comision($c)['venta'];
    }
    return $serie;
}

/* ---------------- Incentivos ---------------- */

const TIPOS_META = ['venta' => 'Ventas en pesos', 'cupones' => 'Cupones validados'];
const PERIODOS_META = ['semana' => 'Por semana', 'mes' => 'Por mes', 'total' => 'Acumulado (una vez)'];

/** Clave del periodo actual de un incentivo, p. ej. "2026-11" o "2026-W47". */
function clave_periodo(string $periodo, ?string $dia = null): string
{
    $t = strtotime($dia ?? date('Y-m-d'));
    return match ($periodo) {
        'semana' => date('o-\SW', $t),
        'mes' => date('Y-m', $t),
        default => 'total',
    };
}

function rango_de_clave(string $periodo, string $clave): array
{
    if ($periodo === 'semana' && preg_match('/^(\d{4})-SW(\d{2})$/', $clave, $m)) {
        $lunes = (new DateTime())->setISODate((int) $m[1], (int) $m[2])->format('Y-m-d');
        return [$lunes, date('Y-m-d', strtotime($lunes . ' +6 days'))];
    }
    if ($periodo === 'mes' && preg_match('/^\d{4}-\d{2}$/', $clave)) return [$clave . '-01', date('Y-m-t', strtotime($clave . '-01'))];
    return ['2000-01-01', '2999-12-31'];
}

function nombre_periodo(string $periodo, string $clave): string
{
    if ($periodo === 'semana') {
        [$d, $h] = rango_de_clave($periodo, $clave);
        return 'semana del ' . date('d/m', strtotime($d)) . ' al ' . date('d/m', strtotime($h));
    }
    if ($periodo === 'mes') {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[(int) substr($clave, 5, 2)] . ' ' . substr($clave, 0, 4);
    }
    return 'acumulado';
}

/** Avance de un vendedor en un incentivo para un periodo. */
function avance(array $inc, string $vendedorId, ?string $clave = null): array
{
    $clave ??= clave_periodo($inc['periodo']);
    [$d, $h] = rango_de_clave($inc['periodo'], $clave);
    $t = totales(ventas_en($d, $h, $vendedorId));
    $valor = $inc['tipo'] === 'venta' ? $t['venta'] : $t['n'];
    $meta = max(1, (float) $inc['meta']);
    return ['clave' => $clave, 'valor' => $valor, 'meta' => $meta, 'pct' => min(100, round($valor * 100 / $meta)), 'logrado' => $valor >= $meta];
}

function solicitudes_pendientes(): int
{
    return count(array_filter(coleccion('solicitudes'), fn($s) => $s['estatus'] === 'pendiente'));
}

function formato_meta(array $inc, float $v): string
{
    return $inc['tipo'] === 'venta' ? dinero($v) : number_format($v) . ' cupón' . ($v == 1 ? '' : 'es');
}
