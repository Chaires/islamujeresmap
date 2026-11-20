<?php
declare(strict_types=1);

/**
 * ALMACÉN EN ARCHIVOS JSON
 * ---------------------------------------------------------------
 * Cada colección es un archivo en privado/datos/<nombre>.json (bloqueado al público).
 * Todas las lecturas y escrituras pasan por estas funciones, así que para migrar a
 * MySQL en el futuro solo hay que reescribir este archivo.
 *
 * Colecciones: canjes, usuarios, puntos, cupones, incentivos, solicitudes
 */

const RUTA_DATOS = RUTA_PRIVADA . '/datos';

function ruta_coleccion(string $nombre): string
{
    if (!preg_match('/^[a-z_]+$/', $nombre)) {
        throw new InvalidArgumentException('Colección inválida');
    }
    if (!is_dir(RUTA_DATOS)) {
        @mkdir(RUTA_DATOS, 0750, true);
        @file_put_contents(RUTA_DATOS . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return RUTA_DATOS . '/' . $nombre . '.json';
}

/** Lee una colección completa (lista de registros). */
function coleccion(string $nombre): array
{
    $ruta = ruta_coleccion($nombre);
    if (!is_file($ruta)) {
        return [];
    }
    $fp = fopen($ruta, 'rb');
    flock($fp, LOCK_SH);
    $txt = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $datos = json_decode((string) $txt, true);
    return is_array($datos) ? $datos : [];
}

/**
 * Modifica una colección de forma segura (con candado, para que dos personas
 * no se pisen). $fn recibe la lista por referencia y puede devolver un valor.
 */
function modificar(string $nombre, callable $fn)
{
    $ruta = ruta_coleccion($nombre);
    $fp = fopen($ruta, 'c+b');
    if (!$fp || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('No se pudo abrir ' . $nombre);
    }
    try {
        $txt = stream_get_contents($fp);
        $datos = json_decode((string) $txt, true);
        if (!is_array($datos)) {
            $datos = [];
        }
        $resultado = $fn($datos);
        $json = json_encode(array_values($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // Respaldo del día anterior por si algo sale mal
        $respaldo = RUTA_DATOS . '/respaldo/' . $nombre . '-' . date('Y-m-d') . '.json';
        if ($txt !== '' && !is_file($respaldo)) {
            @mkdir(dirname($respaldo), 0750, true);
            @file_put_contents($respaldo, $txt);
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        return $resultado;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function nuevo_id(string $prefijo): string
{
    return $prefijo . '_' . date('ymd') . bin2hex(random_bytes(4));
}

function buscar(string $nombre, string $campo, $valor): ?array
{
    foreach (coleccion($nombre) as $r) {
        if (($r[$campo] ?? null) === $valor) {
            return $r;
        }
    }
    return null;
}

/** Agrega un registro y lo devuelve. */
function insertar(string $nombre, array $registro): array
{
    modificar($nombre, function (array &$datos) use ($registro) {
        $datos[] = $registro;
    });
    return $registro;
}

/** Actualiza los registros donde $campo === $valor. Devuelve el registro actualizado o null. */
function actualizar(string $nombre, string $campo, $valor, array $cambios): ?array
{
    return modificar($nombre, function (array &$datos) use ($campo, $valor, $cambios) {
        foreach ($datos as $i => $r) {
            if (($r[$campo] ?? null) === $valor) {
                $datos[$i] = array_merge($r, $cambios);
                return $datos[$i];
            }
        }
        return null;
    });
}

function eliminar(string $nombre, string $campo, $valor): bool
{
    return modificar($nombre, function (array &$datos) use ($campo, $valor) {
        $antes = count($datos);
        $datos = array_values(array_filter($datos, fn($r) => ($r[$campo] ?? null) !== $valor));
        return count($datos) < $antes;
    });
}

/** Pasa los canjes de la versión anterior (SQLite) al JSON, una sola vez. */
function migrar_sqlite(): void
{
    $sqlite = RUTA_PRIVADA . '/canjes.sqlite';
    $marca = RUTA_DATOS . '/.migrado-sqlite';
    if (!is_file($sqlite) || is_file($marca) || !class_exists('PDO')) {
        return;
    }
    try {
        $pdo = new PDO('sqlite:' . $sqlite);
        $filas = $pdo->query('SELECT * FROM canjes')->fetchAll(PDO::FETCH_ASSOC);
        modificar('canjes', function (array &$datos) use ($filas) {
            $existentes = array_column($datos, 'codigo');
            foreach ($filas as $f) {
                if (in_array($f['codigo'], $existentes, true)) continue;
                unset($f['id']);
                $f['id'] = nuevo_id('cj');
                $f['precio'] = (int) $f['precio'];
                $f['personas'] = (int) $f['personas'];
                $f['estatus'] = $f['estatus'] === 'nuevo' ? 'pendiente' : $f['estatus'];
                $datos[] = $f + ['idioma' => 'es', 'moneda' => 'MXN', 'precio_moneda' => $f['precio'], 'vendedor_id' => null, 'vendedor_codigo' => null];
            }
        });
        @file_put_contents($marca, date('c'));
    } catch (Throwable $e) {
        error_log('migrar_sqlite: ' . $e->getMessage());
    }
}
