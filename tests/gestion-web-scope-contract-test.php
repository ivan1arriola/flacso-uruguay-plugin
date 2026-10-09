<?php
$source = file_get_contents(dirname(__DIR__) . '/includes/core/class-flacso-academic-assistant.php');
function gestion_web_assert($condition, $message): void { if (!$condition) { fwrite(STDERR, "Fallo: {$message}\n"); exit(1); } }
gestion_web_assert(strpos($source, "public const ROLE = 'gestion_web'") !== false, 'define el rol gestion_web');
gestion_web_assert(strpos($source, "LEGACY_ROLE = 'asistente_academica'") !== false, 'migra el rol anterior');
gestion_web_assert(strpos($source, 'assigned_offer_ids') !== false && strpos($source, 'assigned_seminar_ids') !== false, 'define asignaciones de catálogo');
gestion_web_assert(strpos($source, 'can_manage_academic_post') !== false, 'autoriza objetos según asignación');
gestion_web_assert(strpos($source, "add_filter('map_meta_cap'") !== false, 'protege edición directa con map_meta_cap');
gestion_web_assert(strpos($source, "add_action('pre_get_posts'") !== false, 'filtra listados del catálogo');
gestion_web_assert(strpos($source, 'show_user_profile') !== false && strpos($source, 'edit_user_profile') !== false, 'administra asignaciones desde perfiles');
gestion_web_assert(strpos($source, 'VIEW_INQUIRIES') !== false, 'declara lectura de consultas');
echo "OK gestion web scope contract\n";
