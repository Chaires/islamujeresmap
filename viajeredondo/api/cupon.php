<?php
declare(strict_types=1);

/** GET api/cupon.php?f=FOLIO&k=LLAVE  → datos públicos del cupón */

require __DIR__ . '/_comun.php';

$folio = (string) ($_GET['f'] ?? '');
$llave = (string) ($_GET['k'] ?? '');
if (!preg_match('/^VR-\d{6}-[A-Z0-9]{4}$/', $folio) || !preg_match('/^[a-f0-9]{24}$/', $llave)) {
    responder(['ok' => false, 'error' => 'Cupón inválido'], 400);
}
$hash = hash('sha256', $llave);
foreach (coleccion('solicitudes') as $s) {
    if ($s['folio'] === $folio && hash_equals($s['llave'], $hash)) {
        responder(['ok' => true, 'cupon' => cupon_publico($s)]);
    }
}
responder(['ok' => false, 'error' => 'No encontrado'], 404);
