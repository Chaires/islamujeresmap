<?php
declare(strict_types=1);

require __DIR__ . '/../app/editor.php';
$u = requerir(['superadmin']);
$todos = catalogo('puntos');
$punto = function (string $id) use ($todos) {
    foreach ($todos as $p) if ($p['id'] === $id) return $p;
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    revisar_csrf();
    $d = $_POST;
    $original = (string) ($d['original'] ?? '');

    if (($d['accion'] ?? '') === 'eliminar') {
        eliminar('puntos', 'id', $original);
        flash('ok', 'Pin eliminado del mapa.');
        redirigir('mapa.php');
    }

    $nombre = texto_post($d, 'nombre', 60);
    $id = $original !== '' ? $original : slug($nombre);
    if ($original === '') {
        $base = $id; $n = 2;
        while ($punto($id)) $id = $base . '-' . $n++;
    }
    $lat = (float) ($d['lat'] ?? 0);
    $lng = (float) ($d['lng'] ?? 0);
    $trad = [];
    foreach (IDIOMAS_TRAD as $l => $_) {
        $t = (array) ($d['trad'][$l] ?? []);
        $trad[$l] = ['nombre' => texto_post($t, 'nombre', 60), 'tipo' => texto_post($t, 'tipo', 40), 'resumen' => texto_post($t, 'resumen', 200),
            'descripcion' => texto_post($t, 'descripcion', 1200), 'datos' => lineas($t['datos'] ?? '')];
    }
    $cupon = (string) ($d['cupon'] ?? '');
    $registro = [
        'id' => $id, 'activo' => !empty($d['activo']), 'orden' => (int) ($d['orden'] ?? 99), 'nombre' => $nombre,
        'tipo' => texto_post($d, 'tipo', 40), 'icono' => mb_substr(trim((string) ($d['icono'] ?? '')), 0, 4) ?: '📍',
        'color' => color_valido((string) ($d['color'] ?? '')), 'coords' => [round($lat, 6), round($lng, 6)],
        'resumen' => texto_post($d, 'resumen', 200), 'descripcion' => texto_post($d, 'descripcion', 1200), 'datos' => lineas($d['datos'] ?? ''),
        'fotos' => fotos_validas(array_merge(lineas($d['fotos'] ?? ''), subir_fotos('subir', $id))),
        'instagram' => filter_var(trim((string) ($d['instagram'] ?? '')), FILTER_VALIDATE_URL) ?: '',
        'cupon' => cupon_por_id($cupon) ? $cupon : null, 'trad' => $trad,
    ];
    $errores = [];
    if (mb_strlen($nombre) < 2) $errores[] = 'falta el nombre';
    if ($lat < 14 || $lat > 33 || $lng < -119 || $lng > -86) $errores[] = 'toca el mapa para poner la ubicación';
    if (!$registro['fotos']) $errores[] = 'agrega al menos una foto';
    if ($errores) {
        $_SESSION['borrador_punto'] = $registro;
        flash('error', 'Revisa: ' . implode(', ', $errores) . '.');
        redirigir('mapa.php?editar=' . urlencode($original ?: 'nuevo'));
    }
    modificar('puntos', function (array &$datos) use ($registro, $original) {
        foreach ($datos as $i => $p) {
            if ($p['id'] === $original) { $datos[$i] = $registro; return; }
        }
        $datos[] = $registro;
    });
    flash('ok', 'Pin "' . $nombre . '" guardado.');
    redirigir('mapa.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $_SESSION['borrador_punto'] ?? ($_GET['editar'] === 'nuevo'
        ? ['id' => '', 'activo' => true, 'orden' => count($todos) + 1, 'nombre' => '', 'tipo' => '', 'icono' => '📍', 'color' => '#0bb5b5', 'coords' => [0, 0],
           'resumen' => '', 'descripcion' => '', 'datos' => [], 'fotos' => [], 'instagram' => '', 'cupon' => null, 'trad' => []]
        : $punto((string) $_GET['editar']));
    unset($_SESSION['borrador_punto']);
}
$cupones = catalogo('cupones');

pagina_inicio($editar ? ($editar['nombre'] ?: 'Nuevo pin') : 'Mapa', $u);
$pinesJs = json_encode(array_map(fn($p) => ['id' => $p['id'], 'nombre' => $p['nombre'], 'icono' => $p['icono'], 'color' => $p['color'], 'coords' => $p['coords'], 'activo' => $p['activo']], $todos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<?php if ($editar): $esNuevo = !$punto((string) $editar['id']); ?>
<form method="post" enctype="multipart/form-data" class="editor">
  <?= campo_csrf() ?><input type="hidden" name="original" value="<?= $esNuevo ? '' : e($editar['id']) ?>">
  <div class="caja"><h2>Ubicación</h2>
    <p class="nota">Toca el mapa o arrastra el pin al lugar exacto. También puedes pegar las coordenadas de Google Maps.</p>
    <div id="mapa-editor" class="mapa-editor"></div>
    <div class="form">
      <?= campo('Latitud', 'lat', $editar['coords'][0] ?: '', 'id="lat" inputmode="decimal" required') ?>
      <?= campo('Longitud', 'lng', $editar['coords'][1] ?: '', 'id="lng" inputmode="decimal" required') ?>
      <label>Pegar de Google Maps<input id="pegar" placeholder="21.2445, -86.7432"></label>
    </div>
  </div>
  <div class="caja"><h2>Datos</h2><div class="form">
    <?= campo('Nombre', 'nombre', $editar['nombre'], 'required maxlength="60"') ?>
    <?= campo('Tipo (ej. Playa, Museo)', 'tipo', $editar['tipo'], 'maxlength="40"') ?>
    <?= campo('Ícono (emoji)', 'icono', $editar['icono'], 'maxlength="4"') ?>
    <label>Color<input type="color" name="color" value="<?= e($editar['color']) ?>"></label>
    <?= campo('Orden', 'orden', $editar['orden'], 'type="number" min="0"') ?>
    <label>Cupón ligado<select name="cupon"><option value="">— Ninguno —</option>
      <?php foreach ($cupones as $c): ?><option value="<?= e($c['id']) ?>" <?= ($editar['cupon'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['titulo']) ?></option><?php endforeach; ?>
    </select></label>
    <label class="check"><input type="checkbox" name="activo" value="1" <?= $editar['activo'] ? 'checked' : '' ?>> Visible en el mapa</label>
    <?= area('Resumen', 'resumen', $editar['resumen'], '', 2) ?>
    <?= area('Descripción', 'descripcion', $editar['descripcion'], '', 4) ?>
    <?= area('Datos rápidos', 'datos', $editar['datos'], 'Uno por renglón') ?>
    <?= campo('Video de Instagram (liga)', 'instagram', $editar['instagram'] ?? '', 'type="url" placeholder="https://www.instagram.com/reel/..."') ?>
  </div></div>
  <div class="caja"><h2>Fotos</h2>
    <?= miniaturas($editar['fotos']) ?>
    <div class="form">
      <?= area('Fotos en orden', 'fotos', $editar['fotos'], 'Borra un renglón para quitarla', 4) ?>
      <label class="ancho">Subir fotos nuevas<input type="file" name="subir[]" accept="image/jpeg,image/png,image/webp" multiple></label>
    </div>
  </div>
  <?php foreach (IDIOMAS_TRAD as $l => $nombre): $t = $editar['trad'][$l] ?? []; ?>
  <details class="caja"><summary><?= $nombre ?> <small class="nota"><?= !empty($t['resumen']) ? '✓ traducido' : 'sin traducir: se muestra en español' ?></small></summary>
    <div class="form" style="margin-top:12px">
      <?= campo('Nombre (si cambia)', "trad[$l][nombre]", $t['nombre'] ?? '') ?>
      <?= campo('Tipo', "trad[$l][tipo]", $t['tipo'] ?? '') ?>
      <?= area('Resumen', "trad[$l][resumen]", $t['resumen'] ?? '', '', 2) ?>
      <?= area('Descripción', "trad[$l][descripcion]", $t['descripcion'] ?? '', '', 4) ?>
      <?= area('Datos rápidos', "trad[$l][datos]", $t['datos'] ?? [], 'Uno por renglón') ?>
    </div>
  </details>
  <?php endforeach; ?>
  <p class="acciones barra-guardar"><button class="btn btn--turquesa">Guardar pin</button><a class="btn btn--claro" href="mapa.php">Cancelar</a></p>
</form>
<?php if (!$esNuevo): ?>
<form method="post" onsubmit="return confirm('¿Eliminar este pin del mapa?')">
  <?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="original" value="<?= e($editar['id']) ?>">
  <button class="btn btn--rojo btn--chico">Eliminar pin</button>
</form>
<?php endif; ?>
<?php else: ?>
<p class="acciones"><a class="btn btn--turquesa" href="?editar=nuevo">➕ Nuevo pin</a></p>
<div id="mapa-editor" class="mapa-editor"></div>
<div class="tabla-marco"><table class="tabla-movil">
  <thead><tr><th>Pin</th><th>Tipo</th><th>Cupón</th><th></th></tr></thead>
  <tbody><?php foreach ($todos as $p): ?>
    <tr class="<?= $p['activo'] ? '' : 'inactivo' ?>">
      <td><b><?= $p['icono'] ?> <?= e($p['nombre']) ?></b><?= $p['activo'] ? '' : ' <small>(oculto)</small>' ?></td>
      <td data-l="Tipo"><?= e($p['tipo']) ?></td>
      <td data-l="Cupón"><?= e((string) ($p['cupon'] ?? '')) ?></td>
      <td><a class="btn btn--claro btn--chico" href="?editar=<?= e($p['id']) ?>">Editar</a></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<script src="../assets/vendor/leaflet/leaflet.js"></script>
<script>
(() => {
  const pines = <?= $pinesJs ?>;
  const editando = <?= json_encode($editar['id'] ?? null) ?>;
  const enForm = !!document.getElementById("lat");
  const mapa = L.map("mapa-editor", { zoomControl: true });
  L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", { maxZoom: 19, attribution: "Esri" }).addTo(mapa);
  L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}", { maxZoom: 19 }).addTo(mapa);
  const icono = (p, grande) => L.divIcon({ className: "", iconSize: [grande ? 44 : 32, grande ? 44 : 32],
    html: `<div style="width:100%;height:100%;border-radius:50%;background:${p.color};display:grid;place-items:center;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4);font-size:${grande ? 22 : 16}px;opacity:${p.activo === false ? .45 : 1}">${p.icono}</div>` });
  const limites = [];
  pines.forEach((p) => {
    if (enForm && p.id === editando) return;
    const m = L.marker(p.coords, { icon: icono(p), title: p.nombre }).addTo(mapa);
    if (!enForm) m.on("click", () => location.href = "?editar=" + encodeURIComponent(p.id));
    limites.push(p.coords);
  });
  if (!enForm) { mapa.fitBounds(limites, { padding: [20, 20] }); return; }

  const lat = document.getElementById("lat"), lng = document.getElementById("lng");
  const datos = () => ({ color: document.querySelector("[name=color]").value, icono: document.querySelector("[name=icono]").value || "📍" });
  let marcador = null;
  function poner(la, ln, mover) {
    lat.value = (+la).toFixed(6); lng.value = (+ln).toFixed(6);
    if (!marcador) {
      marcador = L.marker([la, ln], { draggable: true, icon: icono(datos(), true), zIndexOffset: 1000 }).addTo(mapa);
      marcador.on("dragend", () => { const p = marcador.getLatLng(); poner(p.lat, p.lng); });
    } else marcador.setLatLng([la, ln]);
    if (mover) mapa.setView([la, ln], Math.max(mapa.getZoom() || 16, 16));
  }
  mapa.on("click", (e) => poner(e.latlng.lat, e.latlng.lng));
  [lat, lng].forEach((x) => x.addEventListener("change", () => { if (+lat.value && +lng.value) poner(lat.value, lng.value, true); }));
  document.getElementById("pegar").addEventListener("input", (e) => {
    const m = e.target.value.match(/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/);
    if (m) poner(m[1], m[2], true);
  });
  ["color", "icono"].forEach((n) => document.querySelector(`[name=${n}]`).addEventListener("input", () => marcador && marcador.setIcon(icono(datos(), true))));
  if (+lat.value && +lng.value) { mapa.setView([+lat.value, +lng.value], 16); poner(lat.value, lng.value); }
  else mapa.fitBounds(limites.length ? limites : [[21.20, -86.75], [21.27, -86.70]], { padding: [20, 20] });
})();
</script>
<?php
pagina_fin();
