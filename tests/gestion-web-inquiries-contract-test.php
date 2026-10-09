<?php
$source = file_get_contents(dirname(__DIR__) . '/modules/consultas/includes/class-flacso-consultas-admin.php');
function gestion_web_inquiries_assert($condition, $message): void { if (!$condition) { fwrite(STDERR, "Fallo: {$message}\n"); exit(1); } }
gestion_web_inquiries_assert(strpos($source, 'FLACSO_Academic_Assistant::VIEW_INQUIRIES') !== false, 'Consultas expone capability de lectura');
gestion_web_inquiries_assert(strpos($source, 'private static function can_view') !== false, 'separa lectura de gestión');
gestion_web_inquiries_assert(strpos($source, 'private static function can_manage') !== false, 'protege las mutaciones');
gestion_web_inquiries_assert(strpos($source, 'if ( ! $can_manage ) { unset( $tabs[\'exportar\'] ); }') !== false, 'oculta exportación para lectura');
echo "OK gestion web inquiries contract\n";
