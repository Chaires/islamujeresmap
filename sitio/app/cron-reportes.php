<?php
/**
 * Tarea programada (opcional) para cPanel → Cron Jobs, por ejemplo cada lunes a las 6:00:
 *   php /home/TU_USUARIO/public_html/experiences/islamujerescupones/app/cron-reportes.php
 * Sin esta tarea los reportes igual se generan la primera vez que alguien entra al panel.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/reportes.php';
$creados = reportes_automaticos();
echo $creados ? 'Reportes creados: ' . implode(', ', $creados) . "\n" : "Nada nuevo.\n";
