<?php
declare(strict_types=1);

/** Ayudas para los editores de cupones y del mapa. */

require_once __DIR__ . '/panel.php';

const IDIOMAS_TRAD = ['en' => '🇺🇸 Inglés', 'pt' => '🇧🇷 Portugués', 'zh' => '🇨🇳 Mandarín'];

function lineas(?string $texto): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $texto)), fn($x) => $x !== ''));
}

function texto_post(array $d, string $k, int $max = 400): string
{
    return mb_substr(trim(strip_tags((string) ($d[$k] ?? ''))), 0, $max);
}

function slug(string $t): string
{
    $t = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-') ?: 'item';
}

function color_valido(string $c): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : '#0bb5b5';
}

/** Sube fotos (JPG, PNG o WebP de hasta 6 MB) a assets/img y devuelve sus nombres. */
function subir_fotos(string $campo, string $prefijo): array
{
    $nombres = [];
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'])) return $nombres;
    $dir = __DIR__ . '/../assets/img/';
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK || $f['size'][$i] > 6 * 1024 * 1024) continue;
        $info = @getimagesize($f['tmp_name'][$i]);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (!$ext) continue;
        $n = 1;
        while (glob($dir . $prefijo . '-' . $n . '.*')) $n++;
        $nombre = $prefijo . '-' . $n . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'][$i], $dir . $nombre)) {
            @chmod($dir . $nombre, 0644);
            $nombres[] = $nombre;
        }
    }
    return $nombres;
}

/** Limpia la lista de fotos: solo nombres de archivo que existen en assets/img. */
function fotos_validas(array $lista): array
{
    $ok = [];
    foreach ($lista as $x) {
        $x = basename($x);
        if (preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $x) && is_file(__DIR__ . '/../assets/img/' . $x)) $ok[] = $x;
    }
    return array_values(array_unique($ok));
}

function campo(string $etiqueta, string $nombre, $valor, string $extra = ''): string
{
    return '<label>' . e($etiqueta) . '<input name="' . e($nombre) . '" value="' . e((string) $valor) . '" ' . $extra . '></label>';
}

function area(string $etiqueta, string $nombre, $valor, string $ayuda = '', int $filas = 3): string
{
    $v = is_array($valor) ? implode("\n", $valor) : (string) $valor;
    return '<label class="ancho">' . e($etiqueta) . ($ayuda ? ' <small class="nota">' . e($ayuda) . '</small>' : '')
        . '<textarea name="' . e($nombre) . '" rows="' . $filas . '">' . e($v) . '</textarea></label>';
}

function miniaturas(array $fotos): string
{
    $h = '<div class="minis">';
    foreach ($fotos as $f) $h .= '<img src="../assets/img/' . e($f) . '" alt="' . e($f) . '" title="' . e($f) . '" loading="lazy">';
    return $h . '</div>';
}
