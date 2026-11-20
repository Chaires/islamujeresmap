<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
require_once __DIR__ . '/../app/metricas.php';
$u = requerir(['superadmin', 'vendedor']);
$esSuper = $u['rol'] === 'superadmin';
const ESTATUS_SOL = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    revisar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');

    // ----- Superadmin: crear / editar metas
    if ($esSuper && $accion === 'guardar') {
        $id = (string) ($_POST['id'] ?? '');
        $reg = [
            'id' => $id ?: nuevo_id('in'), 'nombre' => mb_substr(trim(strip_tags((string) $_POST['nombre'])), 0, 80),
            'descripcion' => mb_substr(trim(strip_tags((string) ($_POST['descripcion'] ?? ''))), 0, 400),
            'tipo' => isset(TIPOS_META[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'venta',
            'periodo' => isset(PERIODOS_META[$_POST['periodo'] ?? '']) ? $_POST['periodo'] : 'mes',
            'meta' => max(1, (float) ($_POST['meta'] ?? 0)), 'premio' => mb_substr(trim(strip_tags((string) ($_POST['premio'] ?? ''))), 0, 120),
            'activo' => !empty($_POST['activo']),
        ];
        if (mb_strlen($reg['nombre']) < 2 || $reg['premio'] === '') {
            flash('error', 'Escribe el nombre y el premio de la meta.');
            redirigir('incentivos.php' . ($id ? '?editar=' . urlencode($id) : '?editar=nuevo'));
        }
        modificar('incentivos', function (array &$d) use ($reg) {
            foreach ($d as $i => $x) if ($x['id'] === $reg['id']) { $d[$i] = $reg + $x; return; }
            $d[] = $reg + ['creado' => date('c')];
        });
        flash('ok', 'Meta "' . $reg['nombre'] . '" guardada.');
        redirigir('incentivos.php');
    }

    // ----- Superadmin: aprobar o rechazar
    if ($esSuper && in_array($accion, ['aprobada', 'rechazada'], true)) {
        $s = actualizar('solicitudes', 'id', (string) $_POST['id'], [
            'estatus' => $accion, 'nota' => mb_substr(trim(strip_tags((string) ($_POST['nota'] ?? ''))), 0, 300),
            'resuelto_por' => $u['id'], 'resuelto_en' => date('Y-m-d H:i:s'),
        ]);
        $s ? flash('ok', 'Solicitud ' . ESTATUS_SOL[$accion] . '.') : flash('error', 'No se encontró la solicitud.');
        redirigir('incentivos.php');
    }

    // ----- Vendedor: solicitar bono (solo si ya llegó a la meta y no lo pidió en este periodo)
    if (!$esSuper && $accion === 'solicitar') {
        $inc = buscar('incentivos', 'id', (string) $_POST['id']);
        if (!$inc || empty($inc['activo'])) { flash('error', 'Ese incentivo ya no está activo.'); redirigir('incentivos.php'); }
        $a = avance($inc, $u['id']);
        if (!$a['logrado']) { flash('error', 'Todavía no llegas a la meta.'); redirigir('incentivos.php'); }
        $creada = modificar('solicitudes', function (array &$d) use ($inc, $u, $a) {
            foreach ($d as $s) {
                if ($s['incentivo_id'] === $inc['id'] && $s['vendedor_id'] === $u['id'] && $s['periodo'] === $a['clave'] && $s['estatus'] !== 'rechazada') return false;
            }
            $d[] = ['id' => nuevo_id('so'), 'incentivo_id' => $inc['id'], 'incentivo' => $inc['nombre'], 'premio' => $inc['premio'],
                'vendedor_id' => $u['id'], 'periodo' => $a['clave'], 'tipo_periodo' => $inc['periodo'], 'tipo_meta' => $inc['tipo'], 'logro' => $a['valor'], 'meta' => $a['meta'],
                'comentario' => mb_substr(trim(strip_tags((string) ($_POST['comentario'] ?? ''))), 0, 300),
                'estatus' => 'pendiente', 'nota' => '', 'creado' => date('Y-m-d H:i:s'), 'resuelto_por' => null, 'resuelto_en' => null];
            return true;
        });
        $creada ? flash('ok', '¡Listo! Tu solicitud se envió al Superadmin para su aprobación.') : flash('error', 'Ya habías solicitado este bono en este periodo.');
        redirigir('incentivos.php');
    }
    redirigir('incentivos.php');
}

$incentivos = coleccion('incentivos');
$solicitudes = coleccion('solicitudes');
usort($solicitudes, fn($a, $b) => [$a['estatus'] !== 'pendiente', $b['creado']] <=> [$b['estatus'] !== 'pendiente', $a['creado']]);
$nombres = nombres_usuarios();

pagina_inicio('Incentivos', $u);

// =================== Vista del Superadmin
if ($esSuper):
    $editar = null;
    if (isset($_GET['editar'])) {
        $editar = $_GET['editar'] === 'nuevo'
            ? ['id' => '', 'nombre' => '', 'descripcion' => '', 'tipo' => 'venta', 'periodo' => 'mes', 'meta' => '', 'premio' => '', 'activo' => true]
            : buscar('incentivos', 'id', (string) $_GET['editar']);
    }
    $pendientes = array_filter($solicitudes, fn($s) => $s['estatus'] === 'pendiente');
?>
  <div class="caja">
    <h2>Solicitudes de bono <?= $pendientes ? '<span class="pill pill--pendiente">' . count($pendientes) . ' por revisar</span>' : '' ?></h2>
    <?php if (!$solicitudes): ?><p class="vacio">Aún no hay solicitudes.</p><?php endif; ?>
    <?php foreach (array_slice($solicitudes, 0, 40) as $s): ?>
      <div class="solicitud st-<?= e($s['estatus']) ?>">
        <p><b><?= e($nombres[$s['vendedor_id']] ?? '¿?') ?></b> pide <b><?= e($s['premio']) ?></b> por «<?= e($s['incentivo']) ?>»
          <span class="pill pill--<?= $s['estatus'] === 'aprobada' ? 'confirmado' : ($s['estatus'] === 'rechazada' ? 'cancelado' : 'pendiente') ?>"><?= ESTATUS_SOL[$s['estatus']] ?></span></p>
        <?php $tm = ['tipo' => $s['tipo_meta'] ?? 'venta']; ?>
        <p class="nota">Logró <?= e(formato_meta($tm, (float) $s['logro'])) ?> de <?= e(formato_meta($tm, (float) $s['meta'])) ?> · <?= e(nombre_periodo($s['tipo_periodo'], $s['periodo'])) ?> · <?= e(substr($s['creado'], 0, 16)) ?></p>
        <?php if ($s['comentario']): ?><p>💬 <?= e($s['comentario']) ?></p><?php endif; ?>
        <?php if ($s['estatus'] === 'pendiente'): ?>
          <form method="post" class="acciones"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <input name="nota" placeholder="Nota para el vendedor (opcional)" class="copiar" style="flex:1 1 220px;background:#fff">
            <button class="btn btn--turquesa btn--chico" name="accion" value="aprobada">✓ Aprobar</button>
            <button class="btn btn--rojo btn--chico" name="accion" value="rechazada">✕ Rechazar</button>
          </form>
        <?php elseif ($s['nota']): ?><p class="nota">Nota: <?= e($s['nota']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($editar): ?>
  <form method="post" class="caja"><h2><?= $editar['id'] ? 'Editar meta' : 'Nueva meta' ?></h2>
    <?= campo_csrf() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= e($editar['id']) ?>">
    <div class="form">
      <label>Nombre<input name="nombre" value="<?= e($editar['nombre']) ?>" required placeholder="Ej. Meta de noviembre"></label>
      <label>Premio<input name="premio" value="<?= e($editar['premio']) ?>" required placeholder="Ej. Bono de $1,000"></label>
      <label>Qué se mide<select name="tipo"><?php foreach (TIPOS_META as $k => $v): ?><option value="<?= $k ?>" <?= $editar['tipo'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <label>Meta<input name="meta" type="number" min="1" step="any" value="<?= e((string) $editar['meta']) ?>" required></label>
      <label>Periodo<select name="periodo"><?php foreach (PERIODOS_META as $k => $v): ?><option value="<?= $k ?>" <?= $editar['periodo'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <label class="check"><input type="checkbox" name="activo" value="1" <?= $editar['activo'] ? 'checked' : '' ?>> Activa (la ven los vendedores)</label>
      <label class="ancho">Descripción<textarea name="descripcion" rows="2"><?= e($editar['descripcion']) ?></textarea></label>
    </div>
    <p class="acciones"><button class="btn btn--turquesa">Guardar</button><a class="btn btn--claro" href="incentivos.php">Cancelar</a></p>
  </form>
  <?php else: ?>
  <p class="acciones"><a class="btn btn--turquesa" href="?editar=nuevo">➕ Nueva meta</a></p>
  <?php endif; ?>

  <div class="tabla-marco"><table class="tabla-movil">
    <thead><tr><th>Meta</th><th>Mide</th><th>Objetivo</th><th>Periodo</th><th>Premio</th><th></th></tr></thead>
    <tbody><?php if (!$incentivos): ?><tr><td colspan="6" class="vacio">Crea tu primera meta para motivar a tus vendedores.</td></tr><?php endif; ?>
    <?php foreach ($incentivos as $i): ?>
      <tr class="<?= $i['activo'] ? '' : 'inactivo' ?>"><td><b><?= e($i['nombre']) ?></b><?= $i['activo'] ? '' : ' <small>(inactiva)</small>' ?></td>
        <td data-l="Mide"><?= TIPOS_META[$i['tipo']] ?></td><td data-l="Objetivo"><?= e(formato_meta($i, (float) $i['meta'])) ?></td>
        <td data-l="Periodo"><?= PERIODOS_META[$i['periodo']] ?></td><td data-l="Premio"><?= e($i['premio']) ?></td>
        <td><a class="btn btn--claro btn--chico" href="?editar=<?= e($i['id']) ?>">Editar</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>

<?php
// =================== Vista del Vendedor
else:
    $mias = array_filter($solicitudes, fn($s) => $s['vendedor_id'] === $u['id']);
    $activos = array_filter($incentivos, fn($i) => !empty($i['activo']));
?>
  <?php if (!$activos): ?><p class="vacio">Por ahora no hay incentivos activos. Te avisaremos cuando haya uno.</p><?php endif; ?>
  <div class="lista">
  <?php foreach ($activos as $inc):
      $a = avance($inc, $u['id']);
      $ya = null;
      foreach ($mias as $s) if ($s['incentivo_id'] === $inc['id'] && $s['periodo'] === $a['clave'] && $s['estatus'] !== 'rechazada') $ya = $s;
  ?>
    <article class="caja">
      <h2>🎁 <?= e($inc['nombre']) ?></h2>
      <p><b><?= e($inc['premio']) ?></b> al llegar a <?= e(formato_meta($inc, $a['meta'])) ?> · <?= e(PERIODOS_META[$inc['periodo']]) ?></p>
      <?php if ($inc['descripcion']): ?><p class="nota"><?= e($inc['descripcion']) ?></p><?php endif; ?>
      <div class="progreso <?= $a['logrado'] ? 'progreso--listo' : '' ?>"><i style="width:<?= $a['pct'] ?>%"></i></div>
      <p class="nota">Llevas <?= e(formato_meta($inc, $a['valor'])) ?> (<?= $a['pct'] ?>%) · <?= e(nombre_periodo($inc['periodo'], $a['clave'])) ?></p>
      <?php if ($ya): ?>
        <p><span class="pill pill--<?= $ya['estatus'] === 'aprobada' ? 'confirmado' : 'pendiente' ?>"><?= $ya['estatus'] === 'aprobada' ? '✓ Bono aprobado' : '⏳ Solicitud enviada' ?></span></p>
      <?php elseif ($a['logrado']): ?>
        <form method="post" class="acciones"><?= campo_csrf() ?><input type="hidden" name="accion" value="solicitar"><input type="hidden" name="id" value="<?= e($inc['id']) ?>">
          <input name="comentario" class="copiar" style="background:#fff" placeholder="Comentario (opcional)">
          <button class="btn btn--turquesa">🎉 Solicitar mi bono</button></form>
      <?php else: ?>
        <p class="nota">Te faltan <?= e(formato_meta($inc, max(0, $a['meta'] - $a['valor']))) ?>. Cuando llegues, aquí aparece el botón para solicitar tu bono.</p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>

  <?php if ($mias): ?>
  <h2 style="font-size:1.1rem;margin-top:20px">Tus solicitudes</h2>
  <div class="tabla-marco"><table class="tabla-movil">
    <thead><tr><th>Bono</th><th>Periodo</th><th>Estatus</th><th>Nota</th></tr></thead>
    <tbody><?php foreach ($mias as $s): ?>
      <tr><td><b><?= e($s['premio']) ?></b><br><small><?= e($s['incentivo']) ?></small></td><td data-l="Periodo"><?= e(nombre_periodo($s['tipo_periodo'], $s['periodo'])) ?></td>
        <td data-l="Estatus"><span class="pill pill--<?= $s['estatus'] === 'aprobada' ? 'confirmado' : ($s['estatus'] === 'rechazada' ? 'cancelado' : 'pendiente') ?>"><?= ESTATUS_SOL[$s['estatus']] ?></span></td>
        <td data-l="Nota"><?= e($s['nota']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
<?php endif;
pagina_fin();
