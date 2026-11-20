<?php
declare(strict_types=1);

/** Inicio de sesión y permisos del panel. */

require_once __DIR__ . '/negocio.php';

function iniciar_sesion_php(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('impanel');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function usuario_actual(): ?array
{
    iniciar_sesion_php();
    if (empty($_SESSION['uid'])) return null;
    $u = buscar('usuarios', 'id', $_SESSION['uid']);
    if (!$u || empty($u['activo'])) {
        unset($_SESSION['uid']);
        return null;
    }
    return $u;
}

/** Devuelve el usuario o null. La primera vez crea el Superadmin con los datos de config.php. */
function intentar_login(string $usuario, string $password): ?array
{
    $usuario = trim(mb_strtolower($usuario));
    $c = config();
    if (!coleccion('usuarios')) {
        $pwCfg = (string) ($c['admin_password'] ?? '');
        if ($pwCfg === '' || str_starts_with($pwCfg, 'CAMBIA')) return null;
        if (hash_equals(mb_strtolower((string) $c['admin_usuario']), $usuario) && hash_equals($pwCfg, $password)) {
            return insertar('usuarios', [
                'id' => nuevo_id('us'), 'nombre' => 'Superadmin', 'usuario' => $usuario, 'correo' => '', 'telefono' => '',
                'rol' => 'superadmin', 'codigo' => null, 'password' => password_hash($password, PASSWORD_DEFAULT),
                'activo' => true, 'creado' => date('c'), 'creado_por' => null,
            ]);
        }
        return null;
    }
    foreach (coleccion('usuarios') as $u) {
        if (!empty($u['activo']) && (mb_strtolower((string) $u['usuario']) === $usuario || ($u['correo'] && mb_strtolower((string) $u['correo']) === $usuario))
            && password_verify($password, (string) $u['password'])) {
            return $u;
        }
    }
    return null;
}

/** Corta la página si el usuario no tiene alguno de los roles. */
function requerir(array $roles): array
{
    $u = usuario_actual();
    if (!$u) {
        header('Location: ./?volver=' . urlencode(basename($_SERVER['SCRIPT_NAME'])));
        exit;
    }
    if (!in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para ver esta sección.');
    }
    return $u;
}

function revisar_csrf(): void
{
    iniciar_sesion_php();
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
        http_response_code(400);
        exit('La sesión expiró. Recarga la página.');
    }
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/** Quién puede crear a quién. */
function roles_que_puede_crear(array $u): array
{
    return match ($u['rol']) {
        'superadmin' => ['admin', 'vendedor', 'superadmin'],
        'admin' => ['vendedor'],
        default => [],
    };
}
