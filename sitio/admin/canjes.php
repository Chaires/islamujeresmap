<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
$u = requerir(['superadmin', 'admin']);

// ---------- Cambiar estatus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    revisar_csrf();
    $r = cambiar_estatus((string) ($_POST['id'] ?? ''), (string) ($_POST['estatus'] ?? ''), $u);
    $r ? flash('ok', 'Canje ' . $r['codigo'] . ': ' . ESTATUS[$r['estatus']] . '.') : flash('error', 'No se pudo actualizar.');
    redirigir('canjes.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'estatus', 'vendedor', 'desde', 'hasta']))));
}

// ---------- Filtros
$q = trim((string) ($_GET['q'] ?? ''));
$fEstatus = (string) ($_GET['estatus'] ?? '');
$fVendedor = (string) ($_GET['vendedor'] ?? '');
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? $_GET['desde'] : '';
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? $_GET['hasta'] : '';

$nombres = nombres_usuarios();
$vendedores = array_filter(coleccion('usuarios'), fn($x) => $x['rol'] === 'vendedor');

$lista = array_filter(coleccion('canjes'), function ($c) use ($q, $fEstatus, $fVendedor, $desde, $hasta) {
    if ($fEstatus !== '' && $c['estatus'] !== $fEstatus) return false;
    if ($fVendedor === '-' && !empty($c['vendedor_id'])) return false;
    if ($fVendedor !== '' && $fVendedor !== '-' && ($c['vendedor_id'] ?? '') !== $fVendedor) return false;
    $dia = substr((string) $c['creado'], 0, 10);
    if ($desde && $dia < $desde) return false;
    if ($hasta && $dia > $hasta) return false;
    if ($q !== '') {
        $texto = mb_strtolower(implode(' ', [$c['codigo'], $c['nombre'], $c['correo'], $c['telefono'], $c['cupon'], $c['vendedor_codigo'] ?? '']));
        $digitos = preg_replace('/\D/', '', $q);
        if (!str_contains($texto, mb_strtolower($q)) && !($digitos !== '' && strlen($digitos) >= 4 && str_contains(preg_replace('/\D/', '', $c['telefono']), $digitos))) return false;
    }
    return true;
});
usort($lista, fn($a, $b) => strcmp((string) $b['creado'], (string) $a['creado']));

// ---------- Descargar CSV (abre en Excel)
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="canjes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Estatus', 'Cupón', 'Opción', 'Precio MXN', 'Personas', 'Venta MXN', 'Comisión %', 'Comisión MXN', 'Vendedor', 'Código vendedor',
        'Nombre', 'Teléfono', 'Correo', 'País', 'Estado', 'Fecha de uso', 'Idioma', 'Moneda', 'Precio moneda', 'Validado por', 'Validado en', 'Creado']);
    foreach ($lista as $c) {
        $vc = venta_y_comision($c);
        fputcsv($out, [$c['codigo'], ESTATUS[$c['estatus']] ?? $c['estatus'], $c['cupon'], $c['opcion'], $c['precio'], $c['personas'],
            $c['estatus'] === 'usado' ? $vc['venta'] : '', $c['estatus'] === 'usado' && $c['vendedor_id'] ? ($c['comision_pct'] ?? '') : '', $vc['comision'] ?: '',
            $nombres[$c['vendedor_id'] ?? ''] ?? '', $c['vendedor_codigo'] ?? '', $c['nombre'], $c['telefono'], $c['correo'], $c['pais'], $c['estado'], $c['fecha'],
            $c['idioma'] ?? 'es', $c['moneda'] ?? 'MXN', $c['precio_moneda'] ?? '', $c['validado_por_nombre'] ?? '', $c['validado_en'] ?? '', $c['creado']]);
    }
    exit;
}

$porPagina = 60;
$total = count($lista);
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$lista = array_slice($lista, ($pagina - 1) * $porPagina, $porPagina);
$filtros = array_filter(['q' => $q, 'estatus' => $fEstatus, 'vendedor' => $fVendedor, 'desde' => $desde, 'hasta' => $hasta]);

pagina_inicio('Canjes', $u);
?>
<form class="buscar" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Buscar código, nombre, correo o teléfono" type="search">
  <select name="estatus"><option value="">Todos los estatus</option>
    <?php foreach (ESTATUS as $k => $v): ?><option value="<?= $k ?>" <?= $fEstatus === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select>
  <select name="vendedor"><option value="">Todos los vendedores</option><option value="-" <?= $fVendedor === '-' ? 'selected' : '' ?>>Sin vendedor</option>
    <?php foreach ($vendedores as $v): ?><option value="<?= e($v['id']) ?>" <?= $fVendedor === $v['id'] ? 'selected' : '' ?>><?= e($v['nombre'] . ' · ' . $v['codigo']) ?></option><?php endforeach; ?>
  </select>
  <input type="date" name="desde" value="<?= e($desde) ?>" title="Desde" style="flex:0 1 160px">
  <input type="date" name="hasta" value="<?= e($hasta) ?>" title="Hasta" style="flex:0 1 160px">
  <button class="btn">Filtrar</button>
  <a class="btn btn--claro" href="canjes.php?<?= e(http_build_query($filtros + ['csv' => 1])) ?>">⬇ Excel (CSV)</a>
</form>
<p class="nota"><?= $total ?> canje<?= $total === 1 ? '' : 's' ?>. Un cupón cuenta como venta cuando está <b>Validado</b>.</p>

<?php if (!$lista): ?><p class="vacio">No hay canjes con esos filtros.</p><?php endif; ?>
<div class="lista">
<?php foreach ($lista as $c): $vc = venta_y_comision($c); ?>
  <article class="canje st-<?= e($c['estatus']) ?>">
    <div class="canje__top"><span class="codigo"><?= e($c['codigo']) ?></span><span class="pill pill--<?= e($c['estatus']) ?>"><?= e(ESTATUS[$c['estatus']] ?? $c['estatus']) ?></span></div>
    <h2><?= e($c['cupon']) ?></h2>
    <p><?= e($c['opcion']) ?> · <b><?= dinero((float) $c['precio']) ?> MXN</b><?php if (($c['moneda'] ?? 'MXN') !== 'MXN'): ?> <small>(<?= e($c['moneda'] . ' ' . $c['precio_moneda']) ?>)</small><?php endif; ?></p>
    <p>👤 <?= e($c['nombre']) ?> · <?= (int) $c['personas'] ?> pers.</p>
    <p>📱 <?= e($c['telefono']) ?> · ✉️ <?= e($c['correo']) ?></p>
    <p>📅 Usa el <?= e(fecha_larga($c['fecha'])) ?> · <?= e($c['estado'] . ', ' . $c['pais']) ?></p>
    <?php if (!empty($c['vendedor_id'])): ?><p><span class="pill pill--vendedor">🧑‍💼 <?= e(($nombres[$c['vendedor_id']] ?? '¿?') . ' · ' . $c['vendedor_codigo']) ?></span></p><?php endif; ?>
    <?php if ($c['estatus'] === 'usado'): ?><p class="nota">✔ Validado por <?= e($c['validado_por_nombre'] ?? '—') ?> el <?= e(substr((string) ($c['validado_en'] ?? ''), 0, 16)) ?> · Venta <?= dinero($vc['venta']) ?><?= $vc['comision'] ? ' · Comisión ' . dinero($vc['comision']) : '' ?></p><?php endif; ?>
    <div class="canje__acc">
      <a class="btn btn--wa btn--chico" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($c['telefono'])) ?>?text=<?= rawurlencode('Hola ' . explode(' ', $c['nombre'])[0] . ', te escribimos de Isla Mujeres Pass por tu cupón ' . $c['codigo'] . ' (' . $c['cupon'] . ').') ?>">WhatsApp</a>
      <form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <select name="estatus" onchange="this.form.submit()" aria-label="Cambiar estatus">
          <?php foreach (ESTATUS as $k => $v): ?><option value="<?= $k ?>" <?= $c['estatus'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select><noscript><button class="btn btn--chico">OK</button></noscript>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if ($total > $porPagina): ?>
<nav class="paginas">
  <?php if ($pagina > 1): ?><a class="btn btn--claro btn--chico" href="?<?= e(http_build_query($filtros + ['p' => $pagina - 1])) ?>">← Anteriores</a><?php endif; ?>
  <?php if ($pagina * $porPagina < $total): ?><a class="btn btn--claro btn--chico" href="?<?= e(http_build_query($filtros + ['p' => $pagina + 1])) ?>">Siguientes →</a><?php endif; ?>
</nav>
<?php endif;
pagina_fin();
