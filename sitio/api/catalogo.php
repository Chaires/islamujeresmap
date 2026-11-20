<?php
declare(strict_types=1);

/**
 * Catálogo que se edita en el panel (cupones y puntos del mapa).
 * La página lo carga como <script>; si este archivo no responde, se usa assets/js/datos.js.
 */
require __DIR__ . '/../app/negocio.php';

try {
    $c = catalogo_publico();
} catch (Throwable $e) {
    error_log('catalogo: ' . $e->getMessage());
    header('Content-Type: application/javascript; charset=utf-8');
    exit('/* catálogo no disponible: se usa datos.js */');
}
$json = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    exit($json);
}
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
echo "/* Catálogo del panel */\n(function (c) {\n"
    . "  if (!c || !c.cupones || !c.cupones.length) return;\n"
    . "  PUNTOS.splice(0, PUNTOS.length, ...c.puntos);\n"
    . "  CUPONES.splice(0, CUPONES.length, ...c.cupones);\n"
    . "  Object.keys(c.trad).forEach(function (l) { if (TRADUCCIONES[l]) { TRADUCCIONES[l].puntos = c.trad[l].puntos; TRADUCCIONES[l].cupones = c.trad[l].cupones; } });\n"
    . "  window.CATALOGO_PANEL = true;\n"
    . "})(" . $json . ");\n";
