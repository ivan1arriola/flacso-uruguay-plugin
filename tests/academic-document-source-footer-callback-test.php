<?php
/** Evita que el hook admin_footer pase un string a render_editor(?WP_Post). */
$root = dirname(__DIR__);
$source = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-document-source-admin.php');
function check_document_footer(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . PHP_EOL); exit(1); }
}
check_document_footer(strpos($source, "add_action('admin_footer-post.php', [self::class, 'render_classic_editor'])") !== false, 'hook de edición clásica');
check_document_footer(strpos($source, "add_action('admin_footer-post-new.php', [self::class, 'render_classic_editor'])") !== false, 'hook de nueva entrada');
check_document_footer(strpos($source, 'public static function render_classic_editor(): void') !== false, 'callback sin parámetros');
check_document_footer(strpos($source, 'self::render_editor();') !== false, 'delegación al renderizador');
check_document_footer(strpos($source, 'public static function render_editor(?WP_Post $target_post = null): void') !== false, 'inyección explícita conservada');
echo "OK document source footer callback\n";
