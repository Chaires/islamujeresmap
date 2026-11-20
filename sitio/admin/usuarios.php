<?php
declare(strict_types=1);

require __DIR__ . '/../app/panel.php';
$u = requerir(['superadmin', 'admin']);
$puedeCrear = roles_que_puede_crear($u);

/** ¿Puede $u editar a $otro? El admin solo maneja vendedores; nadie se desactiva a sí mismo. */
$puedeEditar = fn(array $otro) => in_array($otro['rol'], $puedeCrear, true) || $otro['id'] === $u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    revisar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $nombre = trim(strip_tags((string) ($_POST['nombre'] ?? '')));
    $usuario = mb_strtolower(preg_replace('/[^A-Za-z0-9._@-]/', '', (string) ($_POST['usuario'] ?? '')));
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $telefono = preg_replace('/[^\d+ ]/', '', (string) ($_POST['telefono'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rol = (string) ($_POST['rol'] ?? '');
    $errores = [];
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'el correo no es válido';
    if ($password !== '' && mb_strlen($password) < 8) $errores[] = 'la contraseña debe tener al menos 8 caracteres';

    if ($accion === 'crear') {
        if (!in_array($rol, $puedeCrear, true)) $errores[] = 'no puedes crear ese tipo de usuario';
        if (mb_strlen($nombre) < 2) $errores[] = 'falta el nombre';
        if (strlen($usuario) < 3) $errores[] = 'el usuario debe tener al menos 3 caracteres';
        if ($password === '') $errores[] = 'falta la contraseña';
        foreach (coleccion('usuarios') as $x) {
            if (mb_strtolower((string) $x['usuario']) === $usuario) $errores[] = 'ese usuario ya existe';
            if ($correo !== '' && mb_strtolower((string) $x['correo']) === mb_strtolower($correo)) $errores[] = 'ese correo ya está registrado';
        }
        if (!$errores) {
            $nuevo = insertar('usuarios', [
                'id' => nuevo_id('us'), 'nombre' => $nombre, 'usuario' => $usuario, 'correo' => $correo, 'telefono' => $telefono,
                'rol' => $rol, 'codigo' => $rol === 'vendedor' ? generar_codigo_vendedor($nombre) : null,
                'password' => password_hash($password, PASSWORD_DEFAULT), 'activo' => true, 'creado' => date('c'), 'creado_por' => $u['id'],
            ]);
            flash('ok', ROLES[$rol] . ' creado: ' . $nombre . ($nuevo['codigo'] ? ' · código ' . $nuevo['codigo'] : '') . '.');
            redirigir('usuarios.php');
        }
    } elseif ($accion === 'editar') {
        $otro = buscar('usuarios', 'id', (string) ($_POST['id'] ?? ''));
        if (!$otro || !$puedeEditar($otro)) $errores[] = 'no puedes editar a ese usuario';
        if (mb_strlen($nombre) < 2) $errores[] = 'falta el nombre';
        if (!$errores) {
            $cambios = ['nombre' => $nombre, 'correo' => $correo, 'telefono' => $telefono];
            if ($password !== '') $cambios['password'] = password_hash($password, PASSWORD_DEFAULT);
            if ($otro['id'] !== $u['id']) $cambios['activo'] = !empty($_POST['activo']);
            actualizar('usuarios', 'id', $otro['id'], $cambios);
            flash('ok', 'Cambios guardados para ' . $nombre . '.');
            redirigir('usuarios.php');
        }
    }
    if ($errores) flash('error', 'Revisa: ' . implode(', ', array_unique($errores)) . '.');
    redirigir('usuarios.php' . ($accion === 'editar' ? '?editar=' . urlencode((string) $_POST['id']) : ''));
}

$todos = coleccion('usuarios');
if ($u['rol'] === 'admin') {
    $todos = array_values(array_filter($todos, fn($x) => $x['rol'] === 'vendedor'));
}
usort($todos, fn($a, $b) => [array_search($a['rol'], array_keys(ROLES)), $a['nombre']] <=> [array_search($b['rol'], array_keys(ROLES)), $b['nombre']]);
$nombres = nombres_usuarios();
$editar = isset($_GET['editar']) ? buscar('usuarios', 'id', (string) $_GET['editar']) : null;
if ($editar && !$puedeEditar($editar)) $editar = null;

// Ventas validadas por vendedor (para la tabla)
$ventas = [];
foreach (coleccion('canjes') as $c) {
    if (!empty($c['vendedor_id']) && $c['estatus'] === 'usado') {
        $vc = venta_y_comision($c);
        $ventas[$c['vendedor_id']]['n'] = ($ventas[$c['vendedor_id']]['n'] ?? 0) + 1;
        $ventas[$c['vendedor_id']]['venta'] = ($ventas[$c['vendedor_id']]['venta'] ?? 0) + $vc['venta'];
        $ventas[$c['vendedor_id']]['comision'] = ($ventas[$c['vendedor_id']]['comision'] ?? 0) + $vc['comision'];
    }
}

pagina_inicio($u['rol'] === 'admin' ? 'Vendedores' : 'Usuarios', $u);

if ($editar): ?>
  <form class="caja" method="post">
    <h2>Editar a <?= e($editar['nombre']) ?> <span class="pill"><?= e(ROLES[$editar['rol']]) ?></span></h2>
    <?= campo_csrf() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?= e($editar['id']) ?>">
    <div class="form">
      <label>Nombre<input name="nombre" value="<?= e($editar['nombre']) ?>" required></label>
      <label>Usuario<input value="<?= e($editar['usuario']) ?>" disabled></label>
      <label>Correo<input name="correo" type="email" value="<?= e($editar['correo']) ?>"></label>
      <label>Teléfono<input name="telefono" type="tel" value="<?= e($editar['telefono']) ?>"></label>
      <label>Nueva contraseña<input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Déjalo vacío para no cambiarla"></label>
      <?php if ($editar['codigo']): ?><label>Código de vendedor<input value="<?= e($editar['codigo']) ?>" disabled></label><?php endif; ?>
      <?php if ($editar['id'] !== $u['id']): ?><label class="ancho" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="activo" value="1" style="width:auto;min-height:0" <?= $editar['activo'] ? 'checked' : '' ?>> Activo (si lo apagas ya no puede entrar y su código deja de funcionar)</label><?php endif; ?>
    </div>
    <p class="acciones"><button class="btn btn--turquesa">Guardar</button><a class="btn btn--claro" href="usuarios.php">Cancelar</a></p>
  </form>
<?php else: ?>
  <details class="caja" <?= $todos ? '' : 'open' ?>>
    <summary>➕ Crear <?= $u['rol'] === 'admin' ? 'vendedor' : 'usuario' ?></summary>
    <form method="post" style="margin-top:12px"><?= campo_csrf() ?><input type="hidden" name="accion" value="crear">
      <div class="form">
        <label>Nombre completo<input name="nombre" required></label>
        <label>Usuario (para entrar)<input name="usuario" required minlength="3" autocapitalize="none" autocomplete="off"></label>
        <label>Correo<input name="correo" type="email"></label>
        <label>Teléfono<input name="telefono" type="tel"></label>
        <label>Contraseña<input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>Rol<select name="rol"><?php foreach ($puedeCrear as $r): ?><option value="<?= $r ?>" <?= $r === 'vendedor' ? 'selected' : '' ?>><?= ROLES[$r] ?></option><?php endforeach; ?></select></label>
      </div>
      <p class="nota">A los vendedores se les asigna un código único automáticamente.</p>
      <button class="btn btn--turquesa">Crear</button>
    </form>
  </details>
<?php endif; ?>

<div class="tabla-marco"><table class="tabla-movil">
  <thead><tr><th>Nombre</th><th>Rol</th><th>Código</th><th>Usuario / contacto</th><th class="num">Ventas validadas</th><th class="num">Comisión</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($todos as $x): $v = $ventas[$x['id']] ?? null; ?>
    <tr class="<?= $x['activo'] ? '' : 'inactivo' ?>">
      <td><b><?= e($x['nombre']) ?></b><?= $x['activo'] ? '' : ' <small>(inactivo)</small>' ?><br><small class="nota">Alta por <?= e($nombres[$x['creado_por'] ?? ''] ?? 'sistema') ?></small></td>
      <td data-l="Rol"><span class="pill"><?= e(ROLES[$x['rol']]) ?></span></td>
      <td class="codigo" data-l="Código"><?= e((string) $x['codigo']) ?></td>
      <td data-l="Usuario"><span><?= e($x['usuario']) ?><br><small><?= e(trim($x['correo'] . ' ' . $x['telefono'])) ?></small></span></td>
      <td class="num" data-l="Ventas validadas"><?= $x['rol'] === 'vendedor' ? ($v ? $v['n'] . ' · ' . dinero($v['venta']) : '0') : '—' ?></td>
      <td class="num" data-l="Comisión"><?= $x['rol'] === 'vendedor' ? dinero($v['comision'] ?? 0) : '—' ?></td>
      <td><?php if ($puedeEditar($x)): ?><a class="btn btn--claro btn--chico" href="?editar=<?= e($x['id']) ?>">Editar</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php
pagina_fin();
