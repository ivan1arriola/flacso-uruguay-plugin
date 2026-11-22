<?php
/**
 * WordPress solo registra tipos de contenido de hasta 20 caracteres: con más, register_post_type()
 * devuelve un error y el tipo no existe (sin pantalla de edición ni datos).
 * Ejecutable sin WordPress: php tests/post-type-slug-length-test.php
 */
$root = dirname(__DIR__);
$types = [];
foreach (['includes', 'modules'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match_all("/const\s+POST_TYPE\s*=\s*'([^']+)'/", $code, $m)) {
            foreach ($m[1] as $slug) { $types[$slug] = basename($file->getPathname()); }
        }
        if (preg_match_all("/register_post_type\(\s*'([^']+)'/", $code, $m)) {
            foreach ($m[1] as $slug) { $types[$slug] = basename($file->getPathname()); }
        }
    }
}

if (count($types) < 8) { fwrite(STDERR, "FAIL: se esperaban al menos 8 tipos de contenido, se encontraron " . count($types) . "\n"); exit(1); }
$long = [];
foreach ($types as $slug => $origin) {
    if (strlen($slug) > 20) { $long[] = "{$slug} (" . strlen($slug) . " caracteres, {$origin})"; }
}
if ($long) { fwrite(STDERR, "FAIL: tipos de contenido de más de 20 caracteres: " . implode('; ', $long) . "\n"); exit(1); }

echo "OK slugs de tipos de contenido de hasta 20 caracteres (" . count($types) . " revisados)\n";
