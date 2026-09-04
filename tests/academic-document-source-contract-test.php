<?php

$root = dirname(__DIR__);
$helper_file = $root . '/modules/oferta-academica/includes/class-academic-document-source-admin.php';
$init_file = $root . '/modules/oferta-academica/init.php';

function document_source_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

document_source_assert(file_exists($helper_file), 'debe existir el editor de fuentes de documentos');
$helper = (string) file_get_contents($helper_file);
$init = (string) file_get_contents($init_file);

document_source_assert(strpos($init, 'class-academic-document-source-admin.php') !== false, 'init debe cargar el editor de documentos');
document_source_assert(strpos($init, 'FLACSO_Academic_Document_Source_Admin::init();') !== false, 'init debe inicializar el editor de documentos');

document_source_assert(strpos($helper, "'malla_curricular_modo'") !== false, 'malla debe guardar la modalidad elegida');
document_source_assert(strpos($helper, "'malla_curricular_pdf_id'") !== false, 'malla debe guardar el attachment PDF');
document_source_assert(strpos($helper, "'calendario_modo'") !== false, 'calendario debe guardar la modalidad elegida');
document_source_assert(strpos($helper, "'calendario_pdf_id'") !== false, 'calendario debe guardar el attachment PDF');

document_source_assert(strpos($helper, 'wp_enqueue_media();') !== false, 'debe habilitar la Biblioteca de medios');
document_source_assert(strpos($helper, "library: { type: 'application/pdf' }") !== false, 'el selector debe limitarse a PDF');
document_source_assert(strpos($helper, "attachment.mime !== 'application/pdf'") !== false, 'el cliente debe rechazar archivos que no sean PDF');
document_source_assert(strpos($helper, "get_post_mime_type(\$attachment_id) === 'application/pdf'") !== false, 'el servidor debe validar MIME PDF');
document_source_assert(strpos($helper, 'wp_get_attachment_url($attachment_id)') !== false, 'el servidor debe derivar la URL desde el attachment');

document_source_assert(strpos($helper, 'input[name="flacso_oferta[malla_curricular]"]') !== false, 'debe integrar el selector con la malla de Oferta');
document_source_assert(strpos($helper, 'input[name="calendario_academico"]') !== false, 'debe integrar el selector con el calendario de Cohorte');
document_source_assert(strpos($helper, "self::MODE_LINK") !== false && strpos($helper, "self::MODE_PDF") !== false, 'debe permitir enlace y PDF');

echo "OK academic-document-source-contract-test\n";
