<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
$u = requerir(['superadmin', 'admin']);

// Últimos validados por esta persona
$mios = array_filter(coleccion('canjes'), fn($c) => ($c['validado_por'] ?? '') === $u['id']);
usort($mios, fn($a, $b) => strcmp((string) $b['validado_en'], (string) $a['validado_en']));
$mios = array_slice($mios, 0, 8);

pagina_inicio('Escanear cupón', $u);
?>
<div class="escaner" data-csrf="<?= e($_SESSION['csrf']) ?>">
  <div class="camara" id="camara">
    <video id="video" playsinline muted></video>
    <div class="camara__marco"></div>
    <button type="button" class="btn btn--turquesa camara__iniciar" id="btn-camara">📷 Abrir cámara</button>
  </div>
  <form class="buscar" id="form-manual" autocomplete="off">
    <input id="codigo-manual" placeholder="O escribe el código: IM-XXXXXX" autocapitalize="characters" spellcheck="false">
    <button class="btn">Buscar</button>
  </form>
  <div id="resultado"></div>
  <div id="pendientes" class="flash flash--error" hidden></div>
</div>

<?php if ($mios): ?>
<h2 style="font-size:1.1rem">Tus últimas validaciones</h2>
<div class="tabla-marco"><table>
  <?php foreach ($mios as $c): ?>
    <tr><td class="codigo"><?= e($c['codigo']) ?></td><td><?= e($c['cupon']) ?><br><small><?= e($c['nombre']) ?></small></td><td class="num"><small><?= e(substr((string) $c['validado_en'], 5, 11)) ?></small></td></tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<script src="../assets/vendor/jsQR.js"></script>
<script src="escanear.js?v=2"></script>
<?php
pagina_fin();
