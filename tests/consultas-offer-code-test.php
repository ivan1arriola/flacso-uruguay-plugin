<?php
/** Consultas: la abreviación normalizada de la oferta alimenta offer_code / content_ids de Meta. */
$root = dirname(__DIR__);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['metas'] = [1 => ' MESYP ', 2 => '', 3 => 'Mg. Ed'];
function sanitize_text_field($v) { return trim((string) $v); }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['metas'][$id] ?? ''; }
function offer_code_assert(bool $c, string $m): void { if (!$c) { fwrite(STDERR, "FAIL: {$m}\n"); exit(1); } }

$src = file_get_contents($root . '/modules/main-page/includes/flacso-consultas.php');
offer_code_assert((bool) preg_match('/^function flacso_consultas_offer_code\(.*?^}/ms', $src, $m), 'existe el helper');
offer_code_assert(flacso_consultas_offer_code_probe($m[0]) === '', 'sin clase devuelve vacío');
require_once $root . '/modules/oferta-academica/includes/class-oferta-academica.php';
offer_code_assert(flacso_consultas_offer_code(1) === 'mesyp', 'sin el slug igual queda en minúscula');
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
offer_code_assert(flacso_consultas_offer_code(1) === 'mesyp', 'normaliza la abreviación a minúscula');
offer_code_assert(flacso_consultas_offer_code(3) === 'mg-ed', 'usa el mismo slug que los tags de Mautic');
offer_code_assert(flacso_consultas_offer_code(2) === '', 'sin abreviación queda vacío');
offer_code_assert(strpos($src, 'name="offer_code"') !== false && strpos($src, 'data-offer-code') !== false, 'el formulario expone los códigos');
echo "OK consultas offer code\n";

function flacso_consultas_offer_code_probe(string $code): string { eval($code); return flacso_consultas_offer_code(1); }
