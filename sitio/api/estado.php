<?php
declare(strict_types=1);

/**
 * Estado de los cupones que ya pidió este visitante (los guarda su navegador con su token).
 * Sirve para marcar en la página los cupones que ya usó.
 */
require __DIR__ . '/../app/bootstrap.php';

$d = json_decode((string) file_get_contents('php://input'), true);
$lista = is_array($d['codigos'] ?? null) ? array_slice($d['codigos'], 0, 30) : [];
$validos = [];
foreach ($lista as $x) {
    $codigo = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($x['codigo'] ?? '')));
    if ($codigo !== '' && hash_equals(token_codigo($codigo), (string) ($x['token'] ?? ''))) {
        $validos[$codigo] = true;
    }
}
$res = [];
if ($validos) {
    foreach (coleccion('canjes') as $c) {
        if (isset($validos[$c['codigo']])) {
            $res[] = ['codigo' => $c['codigo'], 'cupon_id' => $c['cupon_id'], 'estatus' => $c['estatus']];
        }
    }
}
responder_json(['ok' => true, 'canjes' => $res]);
