<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
iniciar_sesion_php();
migrar_sqlite();

$u = usuario_actual();

// ---------- Inicio de sesión
if (!$u) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] > 6) sleep(min(10, $_SESSION['intentos'] - 6));
        $u = intentar_login((string) ($_POST['usuario'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($u) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['intentos'] = 0;
            $volver = basename((string) ($_GET['volver'] ?? ''));
            redirigir(preg_match('/^[a-z]+\.php$/', $volver) ? $volver : './');
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
    $sinUsuarios = !coleccion('usuarios');
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Entrar · Panel Isla Mujeres Pass</title><meta name="robots" content="noindex"><link rel="icon" href="../assets/img/icono.png">
    <link rel="manifest" href="manifest.webmanifest"><meta name="theme-color" content="#0bb5b5">
    <link rel="stylesheet" href="admin.css?v=6"></head>
    <body class="login"><form method="post" class="tarjeta">
      <h1>🐢 Panel Isla Mujeres Pass</h1>
      <?php if ($sinUsuarios): ?><p class="nota">Primera vez: entra con el usuario y la contraseña de <code>privado/config.php</code>. Quedarás como Superadmin.</p><?php endif; ?>
      <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
      <label>Usuario o correo<input name="usuario" autocomplete="username" required autofocus></label>
      <label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
      <button>Entrar</button>
    </form></body></html><?php
    exit;
}

// ---------- Inicio: tablero según el rol
require_once __DIR__ . '/../app/reportes.php';
try { reportes_automaticos(); } catch (Throwable $e) { error_log('reportes: ' . $e->getMessage()); }

$periodo = isset(PERIODOS[$_GET['periodo'] ?? '']) ? $_GET['periodo'] : 'mes';
[$desde, $hasta] = rango($periodo);
$esVendedor = $u['rol'] === 'vendedor';
$todos = coleccion('canjes');
$mios = $esVendedor ? array_values(array_filter($todos, fn($c) => ($c['vendedor_id'] ?? null) === $u['id'])) : $todos;
$ventas = ventas_en($desde, $hasta, null, $mios);
$tot = totales($ventas);
$hoy = date('Y-m-d');
$enPeriodo = array_filter($mios, fn($c) => substr((string) $c['creado'], 0, 10) >= $desde && substr((string) $c['creado'], 0, 10) <= $hasta);
$porValidar = count(array_filter($mios, fn($c) => in_array($c['estatus'], ['pendiente', 'confirmado'], true)));
$paraHoy = count(array_filter($mios, fn($c) => $c['fecha'] === $hoy && in_array($c['estatus'], ['pendiente', 'confirmado'], true)));
$serie = serie_diaria($ventas, $desde, $hasta);
$porCupon = agrupar($ventas, fn($c) => $c['cupon']);
$nombres = nombres_usuarios();

pagina_inicio('Hola, ' . explode(' ', $u['nombre'])[0], $u);
?>
<nav class="periodos" aria-label="Periodo">
  <?php foreach (PERIODOS as $k => $v): ?><a href="?periodo=<?= $k ?>" class="<?= $k === $periodo ? 'activo' : '' ?>"><?= $v ?></a><?php endforeach; ?>
</nav>

<?php if ($u['rol'] === 'superadmin' && ($pend = solicitudes_pendientes())): ?>
  <a class="flash flash--aviso" href="incentivos.php">🎁 Tienes <?= $pend ?> solicitud<?= $pend === 1 ? '' : 'es' ?> de bono por revisar →</a>
<?php endif; ?>

<section class="kpis">
  <div><b><?= dinero($tot['venta']) ?></b>Ventas validadas</div>
  <div><b><?= $tot['n'] ?></b>Cupones validados</div>
  <div class="kpi--verde"><b><?= dinero($tot['comision']) ?></b><?= $esVendedor ? 'Tu comisión' : 'Comisiones a pagar' ?></div>
  <?php if ($esVendedor): ?>
    <div><b><?= count($enPeriodo) ?></b>Clientes con tu código</div>
  <?php else: ?>
    <div><b><?= dinero($tot['ticket']) ?></b>Venta promedio</div>
    <div><b><?= $porValidar ?></b>Por validar <small>(<?= $paraHoy ?> hoy)</small></div>
  <?php endif; ?>
</section>

<?php if ($esVendedor): $liga = rtrim((string) config()['url_sitio'], '/') . '/?vendedor=' . rawurlencode((string) $u['codigo']); ?>
  <div class="caja">
    <h2>Tu código de vendedor</h2>
    <p class="codigo-grande"><?= e((string) $u['codigo']) ?></p>
    <p>Pídele a tus clientes que lo escriban al canjear, o compárteles tu liga y se llena solo:</p>
    <p><input class="copiar" readonly value="<?= e($liga) ?>" onclick="this.select()"></p>
    <p class="acciones"><a class="btn btn--wa" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('🌴 Cupones exclusivos en Isla Mujeres: ' . $liga) ?>">Compartir por WhatsApp</a>
      <a class="btn btn--claro" href="precios.php">Ver precios y comisiones</a></p>
  </div>
<?php endif; ?>

<div class="caja">
  <h2>Ventas validadas por día</h2>
  <?php $max = max(1, ...array_values($serie ?: [0])); ?>
  <?php if (!array_sum($serie)): ?><p class="vacio">Todavía no hay ventas validadas en este periodo.</p><?php else: ?>
  <div class="grafica" role="img" aria-label="Ventas por día">
    <span class="grafica__max"><?= dinero($max) ?></span>
    <?php foreach ($serie as $dia => $v): ?>
      <div class="grafica__col" tabindex="0" data-tip="<?= e(date('d/m', strtotime($dia)) . ' · ' . dinero($v)) ?>"><i style="height:<?= $v ? max(2, round($v * 100 / $max)) : 0 ?>%"></i></div>
    <?php endforeach; ?>
  </div>
  <div class="grafica__eje"><span><?= date('d/m', strtotime(array_key_first($serie))) ?></span><span><?= date('d/m', strtotime(array_key_last($serie))) ?></span></div>
  <?php endif; ?>
</div>

<div class="dos">
  <div class="caja">
    <h2>Productos más vendidos</h2>
    <?php if (!$porCupon): ?><p class="vacio">Sin ventas en este periodo.</p><?php else: ?>
    <table class="tabla-simple"><thead><tr><th>Cupón</th><th class="num">Validados</th><th class="num">Venta</th></tr></thead><tbody>
    <?php foreach (array_slice($porCupon, 0, 10, true) as $k => $g): ?>
      <tr><td><?= e($k) ?><div class="barrita"><i style="width:<?= round($g['venta'] * 100 / max(1, $tot['venta'])) ?>%"></i></div></td><td class="num"><?= $g['n'] ?></td><td class="num"><?= dinero($g['venta']) ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </div>

  <?php if (!$esVendedor):
    $porVendedor = [];
    foreach (coleccion('usuarios') as $x) if ($x['rol'] === 'vendedor') $porVendedor[$x['id']] = ['nombre' => $x['nombre'], 'codigo' => $x['codigo'], 'activo' => $x['activo'], 'clientes' => 0, 'n' => 0, 'venta' => 0, 'comision' => 0.0];
    foreach ($enPeriodo as $c) if (isset($porVendedor[$c['vendedor_id'] ?? ''])) $porVendedor[$c['vendedor_id']]['clientes']++;
    foreach (agrupar($ventas, fn($c) => $c['vendedor_id'] ?? null) as $id => $g) if (isset($porVendedor[$id])) $porVendedor[$id] = array_merge($porVendedor[$id], $g);
    uasort($porVendedor, fn($a, $b) => $b['venta'] <=> $a['venta'] ?: $b['clientes'] <=> $a['clientes']);
    $sinVendedor = totales(array_filter($ventas, fn($c) => empty($c['vendedor_id'])));
  ?>
  <div class="caja">
    <h2>Rendimiento por vendedor</h2>
    <?php if (!$porVendedor): ?><p class="vacio">Aún no hay vendedores. Créalos en <a href="usuarios.php"><?= $u['rol'] === 'admin' ? 'Vendedores' : 'Usuarios' ?></a>.</p><?php else: ?>
    <table class="tabla-simple"><thead><tr><th>Vendedor</th><th class="num">Clientes</th><th class="num">Validados</th><th class="num">Venta</th><th class="num">Comisión</th></tr></thead><tbody>
    <?php foreach ($porVendedor as $v): ?>
      <tr class="<?= $v['activo'] ? '' : 'inactivo' ?>"><td><?= e($v['nombre']) ?> <small class="codigo"><?= e((string) $v['codigo']) ?></small></td><td class="num"><?= $v['clientes'] ?></td><td class="num"><?= $v['n'] ?></td><td class="num"><?= dinero($v['venta']) ?></td><td class="num"><?= dinero($v['comision']) ?></td></tr>
    <?php endforeach; ?>
      <tr><td class="nota">Sin vendedor (directo)</td><td></td><td class="num"><?= $sinVendedor['n'] ?></td><td class="num"><?= dinero($sinVendedor['venta']) ?></td><td class="num">—</td></tr>
    </tbody></table>
    <?php endif; ?>
  </div>
  <?php else:
    $incentivos = array_filter(coleccion('incentivos'), fn($i) => !empty($i['activo']));
  ?>
  <div class="caja">
    <h2>Tus metas</h2>
    <?php if (!$incentivos): ?><p class="vacio">Por ahora no hay bonos activos.</p><?php endif; ?>
    <?php foreach ($incentivos as $inc): $a = avance($inc, $u['id']); ?>
      <p><b><?= e($inc['nombre']) ?></b> · <?= e($inc['premio']) ?><br><small><?= formato_meta($inc, $a['valor']) ?> de <?= formato_meta($inc, $a['meta']) ?> (<?= e(nombre_periodo($inc['periodo'], $a['clave'])) ?>)</small></p>
      <div class="progreso <?= $a['logrado'] ? 'progreso--listo' : '' ?>"><i style="width:<?= $a['pct'] ?>%"></i></div>
    <?php endforeach; ?>
    <?php if ($incentivos): ?><p><a class="btn btn--claro btn--chico" href="incentivos.php">Ver incentivos</a></p><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php
pagina_fin();
