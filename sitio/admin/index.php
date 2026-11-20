<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$c = config();
$error = '';

if (($c['admin_password'] ?? '') === '' || str_starts_with((string) $c['admin_password'], 'CAMBIA')) {
    exit('Configura admin_password en privado/config.php antes de usar el panel.');
}

if (isset($_GET['salir'])) {
    session_destroy();
    header('Location: ./');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Inicio de sesión
if (empty($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] > 8) {
            sleep(3);
        }
        if (hash_equals((string) $c['admin_usuario'], (string) ($_POST['usuario'] ?? ''))
            && hash_equals((string) $c['admin_password'], (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['intentos'] = 0;
            header('Location: ./');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
    pintar_inicio($error);
    exit;
}

$estatusValidos = ['nuevo' => 'Nuevo', 'confirmado' => 'Confirmado', 'usado' => 'Usado', 'cancelado' => 'Cancelado'];

// Cambiar estatus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    $nuevo = (string) ($_POST['estatus'] ?? '');
    if (isset($estatusValidos[$nuevo])) {
        db()->prepare('UPDATE canjes SET estatus = ? WHERE codigo = ?')->execute([$nuevo, (string) ($_POST['codigo'] ?? '')]);
    }
    header('Location: ./?' . http_build_query(['q' => $_POST['q'] ?? '', 'f' => $_POST['f'] ?? '']));
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$filtro = (string) ($_GET['f'] ?? 'proximos');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(codigo LIKE :q OR nombre LIKE :q OR telefono LIKE :q OR correo LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($filtro === 'proximos') {
    $where[] = 'fecha >= :hoy';
    $params[':hoy'] = date('Y-m-d');
} elseif (isset($estatusValidos[$filtro])) {
    $where[] = 'estatus = :st';
    $params[':st'] = $filtro;
}
$sql = 'SELECT * FROM canjes' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . ($filtro === 'proximos' ? 'fecha ASC' : 'creado DESC') . ' LIMIT 500';
$st = db()->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll();

// Exportar a Excel (CSV)
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="canjes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Cupón', 'Opción', 'Precio', 'Nombre', 'Teléfono', 'Correo', 'Personas', 'País', 'Estado', 'Fecha de uso', 'Estatus', 'Creado']);
    foreach ($filas as $f) {
        fputcsv($out, [$f['codigo'], $f['cupon'], $f['opcion'], $f['precio'], $f['nombre'], $f['telefono'], $f['correo'], $f['personas'], $f['pais'], $f['estado'], $f['fecha'], $f['estatus'], $f['creado']]);
    }
    exit;
}

$totales = db()->query("SELECT COUNT(*) total, SUM(fecha = date('now','-5 hours')) hoy, SUM(estatus='nuevo') nuevos, COALESCE(SUM(personas),0) personas FROM canjes")->fetch();

function tel_wa(string $tel): string
{
    $n = preg_replace('/\D/', '', $tel);
    return strlen($n) === 10 ? '52' . $n : $n;
}

function pintar_inicio(string $error): void
{
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin · Isla Mujeres Pass</title><meta name="robots" content="noindex"><link rel="stylesheet" href="admin.css"></head>
    <body class="login"><form method="post" class="tarjeta">
      <h1>🐢 Panel de canjes</h1>
      <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
      <label>Usuario<input name="usuario" autocomplete="username" required></label>
      <label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
      <button>Entrar</button>
    </form></body></html><?php
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · Isla Mujeres Pass</title><meta name="robots" content="noindex"><link rel="stylesheet" href="admin.css"></head>
<body>
<header><h1>🐢 Canjes</h1><a href="?salir=1">Salir</a></header>
<section class="kpis">
  <div><b><?= (int) $totales['total'] ?></b>Canjes totales</div>
  <div><b><?= (int) $totales['hoy'] ?></b>Para usar hoy</div>
  <div><b><?= (int) $totales['nuevos'] ?></b>Sin confirmar</div>
  <div><b><?= (int) $totales['personas'] ?></b>Personas</div>
</section>
<form class="buscar" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Buscar código, nombre, teléfono o correo" autofocus>
  <select name="f" onchange="this.form.submit()">
    <option value="proximos" <?= $filtro === 'proximos' ? 'selected' : '' ?>>Próximos</option>
    <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
    <?php foreach ($estatusValidos as $k => $v): ?><option value="<?= $k ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select>
  <button>Buscar</button>
  <a class="csv" href="?<?= e(http_build_query(['q' => $q, 'f' => $filtro, 'csv' => 1])) ?>">⬇ Excel</a>
</form>
<?php if (!$filas): ?><p class="vacio">No hay canjes con este filtro.</p><?php endif; ?>
<div class="lista">
<?php foreach ($filas as $f):
    $msg = "¡Hola {$f['nombre']}! Tu cupón {$f['codigo']} de {$f['cupon']} para el " . fecha_larga($f['fecha']) . " está confirmado ✅. Te esperamos en Isla Mujeres 🌴"; ?>
  <article class="canje st-<?= e($f['estatus']) ?>">
    <div class="canje__top"><span class="codigo"><?= e($f['codigo']) ?></span><span class="pill"><?= e($estatusValidos[$f['estatus']] ?? $f['estatus']) ?></span></div>
    <h2><?= e($f['cupon']) ?></h2>
    <p><?= e($f['opcion']) ?> · <?= (int) $f['precio'] === 0 ? 'GRATIS' : '$' . number_format((int) $f['precio']) ?></p>
    <p>📅 <b><?= e(fecha_larga($f['fecha'])) ?></b> · 👥 <?= (int) $f['personas'] ?></p>
    <p>👤 <?= e($f['nombre']) ?> · <?= e($f['estado']) ?>, <?= e($f['pais']) ?></p>
    <p>📞 <?= e($f['telefono']) ?> · ✉️ <a href="mailto:<?= e($f['correo']) ?>"><?= e($f['correo']) ?></a></p>
    <div class="canje__acc">
      <a class="wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($f['telefono'])) ?>?text=<?= rawurlencode($msg) ?>">WhatsApp al cliente</a>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="codigo" value="<?= e($f['codigo']) ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="f" value="<?= e($filtro) ?>">
        <select name="estatus" onchange="this.form.submit()">
          <?php foreach ($estatusValidos as $k => $v): ?><option value="<?= $k ?>" <?= $f['estatus'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
</body></html>
