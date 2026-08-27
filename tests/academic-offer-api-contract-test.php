<?php

define('ABSPATH', __DIR__ . '/../');

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function absint($value) { return abs((int) $value); }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-offer-api.php';

function academic_offer_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("Fallo en '%s': esperado %s, obtuve %s\n", $label, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

$wordpress_payload = FLACSO_Academic_Offer_API::to_wordpress_payload([
    'title' => 'Diploma de prueba',
    'status' => 'draft',
    'featuredMediaId' => 91,
    'associatedPostId' => 301,
    'seminarIds' => [7, 8],
    'program' => ['id' => 17],
    'type' => ['id' => 4],
    'fields' => [
        'duracion_meses' => '12',
        'correo' => 'academica@example.org',
    ],
]);

academic_offer_assert_same('Diploma de prueba', $wordpress_payload['title'], 'titulo semantico');
academic_offer_assert_same(91, $wordpress_payload['featured_media'], 'media traducida dentro del plugin');
academic_offer_assert_same(301, $wordpress_payload['associated_post_id'], 'pagina legacy traducida dentro del plugin');
academic_offer_assert_same([7, 8], $wordpress_payload['_oferta_seminarios_ids'], 'seminarios traducidos dentro del plugin');
academic_offer_assert_same(['id' => 17], $wordpress_payload['program'], 'Programa canonico');
academic_offer_assert_same([17], $wordpress_payload['taxonomies']['area_tematica'], 'slug interno confinado al plugin');
academic_offer_assert_same([4], $wordpress_payload['taxonomies']['tipo-oferta-academica'], 'tipo interno confinado al plugin');
academic_offer_assert_same('12', $wordpress_payload['duracion_meses'], 'campo academico a persistencia');

$domain_item = FLACSO_Academic_Offer_API::to_domain_item([
    'id' => 44,
    'title' => ['rendered' => 'Maestria de prueba'],
    'content' => ['rendered' => '<p>Contenido</p>', 'protected' => true],
    'excerpt' => ['rendered' => 'Resumen'],
    'slug' => 'maestria-prueba',
    'status' => 'publish',
    'link' => 'https://flacso.edu.uy/formacion/maestrias/maestria-prueba/',
    'featured_media' => 21,
    'featured_image_data' => ['id' => 21, 'large' => 'https://example.org/image.jpg'],
    'associated_post_id' => 305,
    '_oferta_seminarios_ids' => ['9'],
    'program' => ['id' => 17, 'name' => 'Educacion'],
    'taxonomies' => [
        'tipo-oferta-academica' => [['id' => 4, 'name' => 'Maestrias']],
    ],
]);

academic_offer_assert_same('Maestria de prueba', $domain_item['title'], 'DTO sin title.rendered');
academic_offer_assert_same(true, $domain_item['visibility']['passwordProtected'], 'visibilidad semantica');
academic_offer_assert_same(21, $domain_item['featuredMedia']['id'], 'media semantica');
academic_offer_assert_same(17, $domain_item['program']['id'], 'Programa en DTO');
academic_offer_assert_same(4, $domain_item['type']['id'], 'tipo en DTO');
academic_offer_assert_same([9], $domain_item['seminarIds'], 'IDs normalizados');

fwrite(STDOUT, "OK academic offer API contract\n");
