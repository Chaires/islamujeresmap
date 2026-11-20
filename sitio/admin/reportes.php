<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
require_once __DIR__ . '/../app/reportes.php';
$u = requerir(['superadmin', 'admin']);
reportes_automaticos();

// Descargar uno guardado o uno al momento
if (isset($_GET['descargar']) || isset($_GET['tipo'])) {
    if (isset($_GET['descargar'])) {
        $archivo = basename((string) $_GET['descargar']);
        $ruta = RUTA_REPORTES . '/' . $archivo;
        if (!preg_match('/^(semanal|mensual)-[0-9W-]+\.csv$/', $archivo) || !is_file($ruta)) { http_response_code(404); exit('No existe.'); }
        $txt = (string) file_get_contents($ruta);
    } else {
        $tipo = $_GET['tipo'] === 'mensual' ? 'mensual' : 'semanal';
        $clave = (string) ($_GET['clave'] ?? '');
        if (!info_reporte($tipo, $clave)) { http_response_code(400); exit('Periodo inválido.'); }
        $archivo = $tipo . '-' . $clave . '.csv';
        $txt = csv_reporte($tipo, $clave);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="isla-mujeres-' . $archivo . '"');
    exit($txt);
}

$lista = lista_reportes();
$semanas = [];
for ($i = 0; $i < 12; $i++) { $t = strtotime("monday this week -$i weeks"); $semanas[date('o-\WW', $t)] = 'Semana del ' . date('d/m', $t) . ($i === 0 ? ' (en curso)' : ''); }
$meses = [];
for ($i = 0; $i < 12; $i++) { $t = strtotime(date('Y-m-01') . " -$i months"); $meses[date('Y-m', $t)] = ucfirst(nombre_periodo('mes', date('Y-m', $t))) . ($i === 0 ? ' (en curso)' : ''); }

pagina_inicio('Reportes', $u);
?>
<p class="nota">Cada lunes se crea solo el reporte de la semana anterior y cada día 1 el del mes anterior. Se abren en Excel o Google Sheets.</p>
<div class="dos">
  <form class="caja" method="get"><h2>Reporte semanal</h2><input type="hidden" name="tipo" value="semanal">
    <div class="form"><label>Semana<select name="clave"><?php foreach ($semanas as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
    <p><button class="btn btn--turquesa">⬇ Descargar</button></p></form>
  <form class="caja" method="get"><h2>Reporte mensual</h2><input type="hidden" name="tipo" value="mensual">
    <div class="form"><label>Mes<select name="clave"><?php foreach ($meses as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
    <p><button class="btn btn--turquesa">⬇ Descargar</button></p></form>
</div>
<h2 style="font-size:1.1rem">Reportes guardados automáticamente</h2>
<?php if (!$lista): ?><p class="vacio">Aún no hay reportes cerrados.</p><?php else: ?>
<div class="tabla-marco"><table class="tabla-movil">
  <thead><tr><th>Periodo</th><th>Tipo</th><th>Creado</th><th></th></tr></thead>
  <tbody><?php foreach ($lista as $r): ?>
    <tr><td><b><?= e($r['nombre']) ?></b></td><td data-l="Tipo"><?= $r['tipo'] === 'mensual' ? 'Mensual' : 'Semanal' ?></td>
      <td data-l="Creado"><?= date('d/m/Y H:i', $r['creado']) ?></td>
      <td><a class="btn btn--claro btn--chico" href="?descargar=<?= e(rawurlencode($r['archivo'])) ?>">⬇ Descargar</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
pagina_fin();
