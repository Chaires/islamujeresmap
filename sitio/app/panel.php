<?php
declare(strict_types=1);

/** Diseño común del panel (encabezado, menú y pie). */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/metricas.php';

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

function menu_para(array $u): array
{
    $todo = [
        'index.php'      => ['🏠', 'Inicio', ['superadmin', 'admin', 'vendedor']],
        'escanear.php'   => ['📷', 'Escanear', ['superadmin', 'admin']],
        'canjes.php'     => ['🎟️', 'Canjes', ['superadmin', 'admin']],
        'clientes.php'   => ['👥', 'Mis clientes', ['vendedor']],
        'precios.php'    => ['💲', 'Precios y comisiones', ['vendedor']],
        'cupones.php'    => ['🏷️', 'Cupones', ['superadmin']],
        'mapa.php'       => ['📍', 'Mapa', ['superadmin']],
        'usuarios.php'   => ['🧑‍💼', 'Usuarios', ['superadmin', 'admin']],
        'incentivos.php' => ['🎁', 'Incentivos', ['superadmin', 'vendedor']],
        'reportes.php'   => ['📊', 'Reportes', ['superadmin', 'admin']],
    ];
    $menu = [];
    foreach ($todo as $archivo => [$icono, $texto, $roles]) {
        if (in_array($u['rol'], $roles, true) && is_file(__DIR__ . '/../admin/' . $archivo)) {
            if ($u['rol'] === 'admin' && $archivo === 'usuarios.php') $texto = 'Vendedores';
            if ($u['rol'] === 'superadmin' && $archivo === 'incentivos.php' && function_exists('solicitudes_pendientes') && ($n = solicitudes_pendientes())) $texto .= " ($n)";
            $menu[$archivo] = [$icono, $texto];
        }
    }
    return $menu;
}

function pagina_inicio(string $titulo, array $u): void
{
    $actual = basename($_SERVER['SCRIPT_NAME']);
    $menu = menu_para($u);
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($titulo) ?> · Panel Isla Mujeres Pass</title><meta name="robots" content="noindex">
<meta name="theme-color" content="#0bb5b5"><link rel="icon" href="../assets/img/icono.png">
<link rel="manifest" href="manifest.webmanifest">
<link rel="stylesheet" href="admin.css?v=4"></head>
<body>
<header class="top">
  <button class="top__menu" type="button" aria-label="Menú" onclick="document.body.classList.toggle('menu-abierto')">☰</button>
  <a class="top__marca" href="./">🐢 <b>Panel</b></a>
  <span class="top__user"><?= e($u['nombre']) ?> · <small><?= e(ROLES[$u['rol']]) ?><?= $u['rol'] === 'vendedor' ? ' · ' . e((string) $u['codigo']) : '' ?></small></span>
  <a class="top__salir" href="salir.php">Salir</a>
</header>
<div class="marco">
<nav class="lateral" aria-label="Secciones">
  <?php foreach ($menu as $archivo => [$icono, $texto]): ?>
    <a href="<?= e($archivo) ?>" class="<?= $archivo === $actual ? 'activo' : '' ?>"><span><?= $icono ?></span><?= e($texto) ?></a>
  <?php endforeach; ?>
  <a href="../" target="_blank" rel="noopener"><span>🌐</span>Ver sitio</a>
</nav>
<main class="contenido">
  <h1><?= e($titulo) ?></h1>
  <?php if ($flash): ?><div class="flash flash--<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php
}

function pagina_fin(): void
{
    echo "</main></div><div class=\"velo\" onclick=\"document.body.classList.remove('menu-abierto')\"></div></body></html>";
}

function flash(string $tipo, string $msg): void
{
    $_SESSION['flash'] = [$tipo, $msg];
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function tel_wa(string $tel): string
{
    $n = preg_replace('/\D/', '', $tel);
    return strlen($n) === 10 ? '52' . $n : $n;
}
