<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
$u = requerir(['vendedor']);

$q = trim((string) ($_GET['q'] ?? ''));
$lista = array_values(array_filter(coleccion('canjes'), function ($c) use ($u, $q) {
    if (($c['vendedor_id'] ?? null) !== $u['id']) return false;
    return $q === '' || str_contains(mb_strtolower($c['nombre'] . ' ' . $c['telefono'] . ' ' . $c['cupon']), mb_strtolower($q));
}));
usort($lista, fn($a, $b) => strcmp((string) $b['creado'], (string) $a['creado']));

pagina_inicio('Mis clientes', $u);
?>
<form class="buscar" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar nombre, teléfono o cupón"><button class="btn">Buscar</button></form>
<p class="nota">Clientes que canjearon con tu código. Tu comisión cuenta cuando el cupón queda <b>Validado</b>.</p>
<?php if (!$lista): ?><p class="vacio">Aún no tienes clientes. Comparte tu código <b class="codigo"><?= e((string) $u['codigo']) ?></b>.</p><?php else: ?>
<div class="tabla-marco"><table class="tabla-movil">
  <thead><tr><th>Cliente</th><th>Teléfono</th><th>Cupón</th><th>Fecha de uso</th><th>Estatus</th><th class="num">Comisión</th></tr></thead>
  <tbody><?php foreach ($lista as $c): $vc = venta_y_comision($c); ?>
    <tr>
      <td><b><?= e($c['nombre']) ?></b></td>
      <td data-l="Teléfono"><a href="https://wa.me/<?= e(tel_wa($c['telefono'])) ?>" target="_blank" rel="noopener"><?= e($c['telefono']) ?></a></td>
      <td data-l="Cupón"><?= e($c['cupon']) ?></td>
      <td data-l="Fecha de uso"><?= e(date('d/m/Y', strtotime($c['fecha']))) ?></td>
      <td data-l="Estatus"><span class="pill pill--<?= e($c['estatus']) ?>"><?= e(ESTATUS[$c['estatus']] ?? '') ?></span></td>
      <td class="num" data-l="Comisión"><?= $c['estatus'] === 'usado' ? dinero($vc['comision']) : '—' ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
pagina_fin();
