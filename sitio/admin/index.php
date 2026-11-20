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
    <link rel="stylesheet" href="admin.css?v=4"></head>
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

// ---------- Inicio (resumen según el rol)
$canjes = coleccion('canjes');
$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');

if ($u['rol'] === 'vendedor') {
    $canjes = array_values(array_filter($canjes, fn($c) => ($c['vendedor_id'] ?? null) === $u['id']));
}

$tot = ['canjes' => 0, 'validados' => 0, 'pendientes' => 0, 'ventas' => 0, 'comisiones' => 0, 'mes_ventas' => 0, 'mes_comisiones' => 0, 'hoy' => 0];
foreach ($canjes as $c) {
    $tot['canjes']++;
    $vc = venta_y_comision($c);
    if ($c['estatus'] === 'usado') {
        $tot['validados']++;
        $tot['ventas'] += $vc['venta'];
        $tot['comisiones'] += $vc['comision'];
        if (substr((string) ($c['validado_en'] ?? $c['creado']), 0, 10) >= $inicioMes) {
            $tot['mes_ventas'] += $vc['venta'];
            $tot['mes_comisiones'] += $vc['comision'];
        }
    }
    if (in_array($c['estatus'], ['pendiente', 'confirmado'], true)) $tot['pendientes']++;
    if ($c['fecha'] === $hoy && $c['estatus'] !== 'cancelado') $tot['hoy']++;
}

pagina_inicio('Hola, ' . explode(' ', $u['nombre'])[0], $u);
?>
<section class="kpis">
  <?php if ($u['rol'] === 'vendedor'): ?>
    <div><b><?= $tot['canjes'] ?></b>Clientes con tu código</div>
    <div><b><?= $tot['validados'] ?></b>Ventas validadas</div>
    <div><b><?= dinero($tot['mes_ventas']) ?></b>Ventas del mes</div>
    <div class="kpi--verde"><b><?= dinero($tot['mes_comisiones']) ?></b>Comisión del mes</div>
  <?php else: ?>
    <div><b><?= $tot['canjes'] ?></b>Canjes totales</div>
    <div><b><?= $tot['hoy'] ?></b>Para usar hoy</div>
    <div><b><?= $tot['pendientes'] ?></b>Por validar</div>
    <div><b><?= dinero($tot['mes_ventas']) ?></b>Ventas del mes</div>
    <div class="kpi--verde"><b><?= dinero($tot['mes_comisiones']) ?></b>Comisiones del mes</div>
  <?php endif; ?>
</section>

<?php if ($u['rol'] === 'vendedor'): ?>
  <div class="caja">
    <h2>Tu código de vendedor</h2>
    <p class="codigo-grande"><?= e((string) $u['codigo']) ?></p>
    <p>Pídele a tus clientes que lo escriban al canjear, o compárteles tu liga y se llena solo:</p>
    <?php $liga = rtrim((string) config()['url_sitio'], '/') . '/?vendedor=' . rawurlencode((string) $u['codigo']); ?>
    <p><input class="copiar" readonly value="<?= e($liga) ?>" onclick="this.select()"></p>
    <p><a class="btn btn--wa" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('🌴 Cupones exclusivos en Isla Mujeres: ' . $liga) ?>">Compartir por WhatsApp</a></p>
  </div>
<?php endif; ?>
<?php
if (is_file(__DIR__ . '/../app/dashboard.php')) {
    require __DIR__ . '/../app/dashboard.php';
}
pagina_fin();
