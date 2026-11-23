<?php
// Todo rsync/zip de despliegue que excluye dev/ debe excluir también scripts/ (se serviría públicamente).
$root = dirname(__DIR__);
$checked = 0;
foreach (glob($root . '/.github/workflows/*.yml') as $file) {
    $text = (string) file_get_contents($file);
    $dev = preg_match_all('#(?:--exclude[= ]|-x\s+|^\s+)[\'"]dev/\*?[\'"]#m', $text);
    $scripts = preg_match_all('#(?:--exclude[= ]|-x\s+|^\s+)[\'"]scripts/\*?[\'"]#m', $text);
    if ($dev === 0) {
        continue;
    }
    $checked++;
    if ($scripts < $dev) {
        fwrite(STDERR, 'FAIL: ' . basename($file) . " excluye dev/ ({$dev}) pero scripts/ solo {$scripts} veces\n");
        exit(1);
    }
}
if ($checked < 2) {
    fwrite(STDERR, "FAIL: se esperaban al menos 2 workflows de empaquetado, hay {$checked}\n");
    exit(1);
}
echo "OK deploy-excludes-scripts\n";
