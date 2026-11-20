<?php
declare(strict_types=1);

date_default_timezone_set('America/Cancun');

const RUTA_PRIVADA = __DIR__ . '/../privado';

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $archivo = RUTA_PRIVADA . '/config.php';
        if (!is_file($archivo)) {
            responder_json(['ok' => false, 'error' => 'Falta privado/config.php en el servidor.'], 500);
        }
        $cfg = require $archivo;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . RUTA_PRIVADA . '/canjes.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE IF NOT EXISTS canjes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT UNIQUE NOT NULL,
            cupon_id TEXT NOT NULL,
            cupon TEXT NOT NULL,
            opcion TEXT,
            precio INTEGER,
            nombre TEXT NOT NULL,
            telefono TEXT NOT NULL,
            correo TEXT NOT NULL,
            personas INTEGER NOT NULL,
            pais TEXT,
            estado TEXT,
            fecha TEXT NOT NULL,
            estatus TEXT NOT NULL DEFAULT "nuevo",
            ip TEXT,
            creado TEXT NOT NULL
        )');
        // Columnas agregadas en la versión con idiomas (se crean solas si faltan)
        $cols = array_column($pdo->query('PRAGMA table_info(canjes)')->fetchAll(), 'name');
        $nuevas = ['idioma' => 'TEXT DEFAULT "es"', 'moneda' => 'TEXT DEFAULT "MXN"', 'precio_moneda' => 'REAL', 'cupon_cliente' => 'TEXT', 'opcion_cliente' => 'TEXT'];
        foreach ($nuevas as $col => $tipo) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec("ALTER TABLE canjes ADD COLUMN $col $tipo");
            }
        }
    }
    return $pdo;
}

function responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function token_codigo(string $codigo): string
{
    return substr(hash_hmac('sha256', $codigo, (string) config()['secreto']), 0, 20);
}

function wallet_configurado(): bool
{
    $c = config();
    return !empty($c['wallet_issuer_id']) && is_file(RUTA_PRIVADA . '/' . ($c['wallet_llave_json'] ?? ''));
}

function generar_codigo(): string
{
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $c = 'IM-';
    for ($i = 0; $i < 6; $i++) {
        $c .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $c;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function fecha_larga(string $iso): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($iso . ' 12:00:00');
    if ($t === false) return $iso;
    return $dias[(int) date('w', $t)] . ' ' . date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
}
