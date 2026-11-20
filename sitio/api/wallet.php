<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/wallet.php';

$codigo = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['codigo'] ?? '')));
$token = (string) ($_GET['t'] ?? '');

function pagina_error(string $msg): void
{
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<body style="font-family:sans-serif;padding:40px;text-align:center;color:#12324a"><h2>😕 ' . e($msg) . '</h2>'
        . '<p><a href="../">Volver</a></p></body>';
    exit;
}

if ($codigo === '' || !hash_equals(token_codigo($codigo), $token)) {
    pagina_error('Enlace de cupón no válido.');
}
if (!wallet_configurado()) {
    pagina_error('Google Wallet todavía no está configurado.');
}

$canje = buscar('canjes', 'codigo', $codigo);
if (!$canje || ($canje['estatus'] ?? '') === 'cancelado') {
    pagina_error('No encontramos ese cupón.');
}

try {
    header('Location: ' . enlace_google_wallet($canje), true, 302);
} catch (Throwable $ex) {
    error_log('wallet: ' . $ex->getMessage());
    pagina_error('No se pudo generar el pase. Intenta más tarde.');
}
