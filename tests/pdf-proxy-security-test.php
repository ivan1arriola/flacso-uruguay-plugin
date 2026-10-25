<?php
/** Pruebas aisladas de la contención de rutas y la lista blanca del proxy de PDFs. */

$sandbox = sys_get_temp_dir() . '/flacso-pdf-proxy-' . bin2hex(random_bytes(4));
$uploads = $sandbox . '/wp-content/uploads';
mkdir($uploads . '/2026', 0777, true);
file_put_contents($sandbox . '/wp-config.php', '<?php // secreto');
file_put_contents($uploads . '/2026/programa.pdf', '%PDF-1.4');
file_put_contents($uploads . '/2026/notas.txt', 'no es pdf');

class WP_Error {
    public string $code;
    public function __construct(string $code = '', string $message = '') { $this->code = $code; }
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function add_action(string $name, $callback, int $priority = 10): void {}
function home_url(string $path = ''): string { return 'https://flacso.edu.uy' . $path; }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function trailingslashit(string $value): string { return rtrim($value, '/\\') . '/'; }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function wp_get_upload_dir(): array {
    return ['baseurl' => 'https://flacso.edu.uy/wp-content/uploads', 'basedir' => $GLOBALS['uploads']];
}
$GLOBALS['uploads'] = $uploads;

require dirname(__DIR__) . '/modules/oferta-academica/includes/pdf-proxy.php';

function pdf_proxy_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$norm = static function ($path) { return $path === false ? false : str_replace('\\', '/', $path); };
$expected = $norm(realpath($uploads . '/2026/programa.pdf'));

// Mapeo a disco: solo PDF dentro de uploads.
pdf_proxy_assert($norm(flacso_map_wp_uploads_file('https://flacso.edu.uy/wp-content/uploads/2026/programa.pdf')) === $expected, 'Sirve PDF legítimo de uploads');
pdf_proxy_assert(flacso_map_wp_uploads_file('https://flacso.edu.uy/wp-content/uploads/../../wp-config.php') === false, 'Bloquea ../ hacia wp-config.php');
pdf_proxy_assert(flacso_map_wp_uploads_file('https://flacso.edu.uy/wp-content/uploads/2026/../../../wp-config.php?x=.pdf') === false, 'Bloquea ../ con sufijo .pdf en la query');
pdf_proxy_assert(flacso_map_wp_uploads_file('https://flacso.edu.uy/wp-content/uploads/%2e%2e/%2e%2e/wp-config.php') === false, 'Bloquea ../ codificado');
pdf_proxy_assert(flacso_map_wp_uploads_file('https://flacso.edu.uy/wp-content/uploads/2026/notas.txt') === false, 'No sirve archivos que no son PDF');
pdf_proxy_assert(flacso_map_wp_uploads_file('https://otro.example/wp-content/uploads/2026/programa.pdf') === false, 'No mapea hosts ajenos al disco local');

// Lista blanca de hosts, también para URLs que terminan en .pdf.
$blocked = [
    'http://127.0.0.1/interno.pdf',
    'http://169.254.169.254/latest/meta-data/x.pdf',
    'https://evil.example/archivo.pdf',
    'https://evil.example/wp-content/uploads/a.pdf',
    'file:///etc/passwd.pdf',
    'https://flacso.edu.uy.evil.example/a.pdf',
];
foreach ($blocked as $url) {
    pdf_proxy_assert(is_wp_error(flacso_resolve_pdf_export_url($url)), 'Rechaza ' . $url);
}

$allowed = [
    'https://flacso.edu.uy/wp-content/uploads/2026/programa.pdf',
    'https://flacso.edu.uy/documentos/plan.pdf',
    'https://drive.google.com/file/d/abc123/view',
    'https://docs.google.com/document/d/abc123/edit',
];
foreach ($allowed as $url) {
    pdf_proxy_assert(!is_wp_error(flacso_resolve_pdf_export_url($url)), 'Permite ' . $url);
}

pdf_proxy_assert(is_wp_error(flacso_resolve_pdf_export_url('https://flacso.edu.uy/otra-pagina/')), 'Rechaza páginas del sitio que no son PDF ni uploads');

echo "OK pdf proxy security\n";
