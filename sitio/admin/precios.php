<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
$u = requerir(['vendedor']);
$cupones = array_filter(catalogo('cupones'), fn($c) => !empty($c['activo']));

pagina_inicio('Precios y comisiones', $u);
?>
<p class="nota">Precios públicos con cupón (en pesos) y lo que ganas por cada venta validada con tu código <b class="codigo"><?= e((string) $u['codigo']) ?></b>.</p>
<div class="lista">
<?php foreach ($cupones as $c): $pct = (float) ($c['comision'] ?? 10); ?>
  <article class="canje st-confirmado">
    <div class="canje__top"><h2><?= $c['icono'] ?> <?= e($c['titulo']) ?></h2><span class="pill pill--vendedor"><?= e(rtrim(rtrim(number_format($pct, 2), '0'), '.')) ?>%</span></div>
    <?php foreach ($c['precios'] as $p): ?>
      <p><?= e($p['concepto']) ?>: <b><?= dinero((float) $p['promo']) ?></b><?= $p['normal'] > $p['promo'] ? ' <s class="nota">' . dinero((float) $p['normal']) . '</s>' : '' ?>
        → ganas <b style="color:#0ca678"><?= dinero(round($p['promo'] * $pct / 100, 2)) ?></b><?= !empty($p['por_persona']) ? ' por persona' : '' ?></p>
    <?php endforeach; ?>
    <p class="acciones" style="margin-top:8px"><a class="btn btn--wa btn--chico" target="_blank" rel="noopener"
      href="https://wa.me/?text=<?= rawurlencode($c['icono'] . ' ' . $c['titulo'] . ' con descuento en Isla Mujeres: ' . rtrim((string) config()['url_sitio'], '/') . '/?vendedor=' . rawurlencode((string) $u['codigo']) . '#cupon-' . $c['id']) ?>">Compartir</a></p>
  </article>
<?php endforeach; ?>
</div>
<?php
pagina_fin();
