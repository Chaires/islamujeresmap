<?php
declare(strict_types=1);

require __DIR__ . '/../app/editor.php';
$u = requerir(['superadmin']);
$todos = catalogo('cupones');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    revisar_csrf();
    $d = $_POST;
    $original = (string) ($d['original'] ?? '');

    if (($d['accion'] ?? '') === 'eliminar') {
        eliminar('cupones', 'id', $original);
        flash('ok', 'Cupón eliminado. Los canjes que ya existían se conservan.');
        redirigir('cupones.php');
    }

    $titulo = texto_post($d, 'titulo', 80);
    $id = $original !== '' ? $original : slug($titulo);
    if ($original === '') {
        $base = $id; $n = 2;
        while (cupon_por_id($id)) $id = $base . '-' . $n++;
    }

    $precios = [];
    foreach ((array) ($d['precios'] ?? []) as $p) {
        $concepto = texto_post($p, 'concepto', 80);
        if ($concepto === '') continue;
        $promo = max(0, (int) ($p['promo'] ?? 0));
        $precios[] = ['concepto' => $concepto, 'normal' => max($promo, (int) ($p['normal'] ?? 0)), 'promo' => $promo, 'por_persona' => !empty($p['por_persona'])];
    }

    $fotos = fotos_validas(array_merge(lineas($d['fotos'] ?? ''), subir_fotos('subir', $id)));

    $trad = [];
    foreach (IDIOMAS_TRAD as $l => $_) {
        $t = (array) ($d['trad'][$l] ?? []);
        $trad[$l] = [
            'titulo' => texto_post($t, 'titulo', 80), 'etiqueta' => texto_post($t, 'etiqueta', 30), 'categoria' => texto_post($t, 'categoria', 40),
            'resumen' => texto_post($t, 'resumen', 200), 'descripcion' => texto_post($t, 'descripcion', 1200),
            'incluye' => lineas($t['incluye'] ?? ''), 'conceptos' => lineas($t['conceptos'] ?? ''),
        ];
    }

    $registro = [
        'id' => $id, 'activo' => !empty($d['activo']), 'orden' => (int) ($d['orden'] ?? 99),
        'titulo' => $titulo, 'etiqueta' => texto_post($d, 'etiqueta', 30), 'categoria' => texto_post($d, 'categoria', 40),
        'icono' => mb_substr(trim((string) ($d['icono'] ?? '')), 0, 4) ?: '🎟️', 'color' => color_valido((string) ($d['color'] ?? '')),
        'resumen' => texto_post($d, 'resumen', 200), 'descripcion' => texto_post($d, 'descripcion', 1200), 'incluye' => lineas($d['incluye'] ?? ''),
        'precios' => $precios, 'fotos' => $fotos,
        'instagram' => filter_var(trim((string) ($d['instagram'] ?? '')), FILTER_VALIDATE_URL) ?: '',
        'comision' => min(100, max(0, round((float) ($d['comision'] ?? 10), 2))), 'trad' => $trad,
    ];

    $errores = [];
    if (mb_strlen($titulo) < 3) $errores[] = 'falta el título';
    if ($registro['categoria'] === '') $errores[] = 'falta la categoría';
    if (!$precios) $errores[] = 'agrega al menos un precio';
    if (!$fotos) $errores[] = 'agrega al menos una foto';
    if ($errores) {
        $_SESSION['borrador_cupon'] = $registro;
        flash('error', 'Revisa: ' . implode(', ', $errores) . '.');
        redirigir('cupones.php?editar=' . urlencode($original ?: 'nuevo'));
    }
    modificar('cupones', function (array &$datos) use ($registro, $original) {
        foreach ($datos as $i => $c) {
            if ($c['id'] === $original) { $datos[$i] = $registro; return; }
        }
        $datos[] = $registro;
    });
    flash('ok', 'Cupón "' . $titulo . '" guardado. Ya se ve así en la página.');
    redirigir('cupones.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $_SESSION['borrador_cupon'] ?? ($_GET['editar'] === 'nuevo'
        ? ['id' => '', 'activo' => true, 'orden' => count($todos) + 1, 'titulo' => '', 'etiqueta' => '', 'categoria' => '', 'icono' => '🎟️', 'color' => '#0bb5b5',
           'resumen' => '', 'descripcion' => '', 'incluye' => [], 'precios' => [], 'fotos' => [], 'instagram' => '', 'comision' => 10, 'trad' => []]
        : cupon_por_id((string) $_GET['editar']));
    unset($_SESSION['borrador_cupon']);
}

// Canjes por cupón (informativo)
$usos = [];
foreach (coleccion('canjes') as $c) $usos[$c['cupon_id']] = ($usos[$c['cupon_id']] ?? 0) + 1;
$categorias = array_values(array_unique(array_column($todos, 'categoria')));

pagina_inicio($editar ? ($editar['titulo'] ?: 'Nuevo cupón') : 'Cupones', $u);

if ($editar):
    $esNuevo = !cupon_por_id((string) $editar['id']);
    $precios = $editar['precios'];
    for ($i = 0; $i < 2; $i++) $precios[] = ['concepto' => '', 'normal' => '', 'promo' => '', 'por_persona' => false];
?>
<form method="post" enctype="multipart/form-data" class="editor">
  <?= campo_csrf() ?><input type="hidden" name="original" value="<?= $esNuevo ? '' : e($editar['id']) ?>">
  <div class="caja"><h2>Datos principales</h2><div class="form">
    <?= campo('Título', 'titulo', $editar['titulo'], 'required maxlength="80"') ?>
    <?= campo('Etiqueta (ej. -20%, Open bar)', 'etiqueta', $editar['etiqueta'], 'maxlength="30"') ?>
    <label>Categoría<input name="categoria" value="<?= e($editar['categoria']) ?>" list="cats" required></label>
    <datalist id="cats"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    <?= campo('Ícono (emoji)', 'icono', $editar['icono'], 'maxlength="4"') ?>
    <label>Color<input type="color" name="color" value="<?= e($editar['color']) ?>"></label>
    <?= campo('Orden en la lista', 'orden', $editar['orden'], 'type="number" min="0"') ?>
    <?= campo('Comisión del vendedor (%)', 'comision', $editar['comision'] ?? 10, 'type="number" min="0" max="100" step="0.5"') ?>
    <label class="check"><input type="checkbox" name="activo" value="1" <?= $editar['activo'] ? 'checked' : '' ?>> Visible en la página</label>
    <?= area('Resumen (tarjeta)', 'resumen', $editar['resumen'], '', 2) ?>
    <?= area('Descripción', 'descripcion', $editar['descripcion'], '', 4) ?>
    <?= area('Qué incluye', 'incluye', $editar['incluye'], 'Uno por renglón') ?>
    <?= campo('Video de Instagram (liga)', 'instagram', $editar['instagram'] ?? '', 'type="url" class="ancho" placeholder="https://www.instagram.com/reel/..."') ?>
  </div></div>

  <div class="caja"><h2>Precios (en pesos)</h2>
    <p class="nota">Deja vacío el concepto para borrar un renglón. Marca "por persona" si la venta se multiplica por el número de personas.</p>
    <div class="tabla-marco"><table class="precios">
      <thead><tr><th>Concepto</th><th>Precio normal</th><th>Precio con cupón</th><th>Por persona</th></tr></thead>
      <tbody><?php foreach ($precios as $i => $p): ?>
        <tr><td><input name="precios[<?= $i ?>][concepto]" value="<?= e($p['concepto']) ?>" placeholder="Ej. Adulto"></td>
          <td><input name="precios[<?= $i ?>][normal]" value="<?= e((string) $p['normal']) ?>" placeholder="Normal" type="number" min="0" inputmode="numeric"></td>
          <td><input name="precios[<?= $i ?>][promo]" value="<?= e((string) $p['promo']) ?>" placeholder="Con cupón" type="number" min="0" inputmode="numeric"></td>
          <td><input type="checkbox" name="precios[<?= $i ?>][por_persona]" value="1" <?= !empty($p['por_persona']) ? 'checked' : '' ?>></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>

  <div class="caja"><h2>Fotos</h2>
    <?= miniaturas($editar['fotos']) ?>
    <div class="form">
      <?= area('Fotos en orden (la primera es la portada)', 'fotos', $editar['fotos'], 'Borra un renglón para quitarla', 4) ?>
      <label class="ancho">Subir fotos nuevas <small class="nota">JPG, PNG o WebP, ideal 1200 × 750</small><input type="file" name="subir[]" accept="image/jpeg,image/png,image/webp" multiple></label>
    </div>
  </div>

  <?php foreach (IDIOMAS_TRAD as $l => $nombre): $t = $editar['trad'][$l] ?? []; ?>
  <details class="caja"><summary><?= $nombre ?> <small class="nota"><?= !empty($t['titulo']) ? '✓ traducido' : 'sin traducir: se muestra en español' ?></small></summary>
    <div class="form" style="margin-top:12px">
      <?= campo('Título', "trad[$l][titulo]", $t['titulo'] ?? '') ?>
      <?= campo('Etiqueta', "trad[$l][etiqueta]", $t['etiqueta'] ?? '') ?>
      <?= campo('Categoría', "trad[$l][categoria]", $t['categoria'] ?? '') ?>
      <?= area('Resumen', "trad[$l][resumen]", $t['resumen'] ?? '', '', 2) ?>
      <?= area('Descripción', "trad[$l][descripcion]", $t['descripcion'] ?? '', '', 4) ?>
      <?= area('Qué incluye', "trad[$l][incluye]", $t['incluye'] ?? [], 'Uno por renglón') ?>
      <?= area('Conceptos de precio', "trad[$l][conceptos]", $t['conceptos'] ?? [], 'Uno por renglón, en el mismo orden que los precios', 2) ?>
    </div>
  </details>
  <?php endforeach; ?>

  <p class="acciones barra-guardar"><button class="btn btn--turquesa">Guardar cupón</button><a class="btn btn--claro" href="cupones.php">Cancelar</a></p>
</form>
<?php if (!$esNuevo): ?>
<form method="post" onsubmit="return confirm('¿Eliminar este cupón? Es mejor desmarcar &quot;Visible&quot; si solo quieres ocultarlo.')">
  <?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="original" value="<?= e($editar['id']) ?>">
  <button class="btn btn--rojo btn--chico">Eliminar cupón</button>
</form>
<?php endif; ?>

<?php else: ?>
<p class="acciones"><a class="btn btn--turquesa" href="?editar=nuevo">➕ Nuevo cupón</a></p>
<div class="tabla-marco"><table class="tabla-movil">
  <thead><tr><th>Cupón</th><th>Categoría</th><th class="num">Precio</th><th class="num">Comisión</th><th class="num">Canjes</th><th></th></tr></thead>
  <tbody><?php foreach ($todos as $c): $p = $c['precios'][0] ?? ['promo' => 0]; ?>
    <tr class="<?= $c['activo'] ? '' : 'inactivo' ?>">
      <td><b><?= $c['icono'] ?> <?= e($c['titulo']) ?></b><?= $c['activo'] ? '' : ' <small>(oculto)</small>' ?></td>
      <td data-l="Categoría"><?= e($c['categoria']) ?></td>
      <td class="num" data-l="Precio"><?= count($c['precios']) > 1 ? 'desde ' : '' ?><?= dinero((float) $p['promo']) ?></td>
      <td class="num" data-l="Comisión"><?= e((string) ($c['comision'] ?? 10)) ?>%</td>
      <td class="num" data-l="Canjes"><?= $usos[$c['id']] ?? 0 ?></td>
      <td><a class="btn btn--claro btn--chico" href="?editar=<?= e($c['id']) ?>">Editar</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
pagina_fin();
