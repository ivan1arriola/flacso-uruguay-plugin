<?php
// Datos ficticios para el entorno local. No usar en producción.
$tipos = ['doctorado' => 'Doctorado', 'maestria' => 'Maestría', 'especializacion' => 'Especialización', 'diplomado' => 'Diplomado', 'diploma' => 'Diploma'];
$ids = [];
foreach ($tipos as $slug => $name) {
    $t = term_exists($slug, 'tipo-oferta-academica');
    if (!$t) { $t = wp_insert_term($name, 'tipo-oferta-academica', ['slug' => $slug]); }
    $ids[$slug] = is_array($t) ? (int) $t['term_id'] : 0;
}
$ofertas = [
    ['Maestría en Género y Políticas Públicas (ficticia)', 'maestria'],
    ['Diplomado en Educación e Innovación (ficticio)', 'diplomado'],
    ['Especialización en Políticas Sociales (ficticia)', 'especializacion'],
    ['Doctorado en Ciencias Sociales (ficticio)', 'doctorado'],
    ['Diploma en Estudios de la Niñez con un título largo para probar el recorte de texto (ficticio)', 'diploma'],
];
foreach ($ofertas as [$title, $tipo]) {
    if (get_posts(['post_type' => 'oferta-academica', 'title' => $title, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids'])) { continue; }
    $id = wp_insert_post(['post_type' => 'oferta-academica', 'post_title' => $title, 'post_status' => 'publish']);
    if ($id && !is_wp_error($id)) { wp_set_object_terms($id, [$ids[$tipo]], 'tipo-oferta-academica'); }
}
// Resto de las familias de plantilla: un registro de cada tipo, con datos ficticios.
$crear = function ($tipo, $titulo, $extra = []) {
    $existe = get_posts(['post_type' => $tipo, 'title' => $titulo, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids']);
    if ($existe) { return (int) $existe[0]; }
    $id = wp_insert_post(array_merge(['post_type' => $tipo, 'post_title' => $titulo, 'post_status' => 'publish'], $extra));
    return is_wp_error($id) ? 0 : (int) $id;
};
$ids = [
    'seminario' => $crear('seminario', 'Seminario de Teoria Social (ficticio)'),
    'docente'   => $crear('docente', 'Ana Perez (ficticia)'),
    'programa'  => $crear('programa-academico', 'Programa de Genero y Politicas (ficticio)'),
    'convenio'  => $crear('convenio', 'Convenio con Universidad Ejemplo (ficticio)', ['post_content' => 'Convenio de cooperacion academica ficticio.']),
    'evento'    => $crear('evento', 'Jornada abierta de ejemplo (ficticia)', ['post_content' => 'Evento ficticio para pruebas visuales.']),
    'noticia'   => $crear('post', 'Noticia de ejemplo para pruebas (ficticia)', ['post_content' => 'Texto de una noticia ficticia. ' . str_repeat('Contenido de prueba. ', 30)]),
    'pregunta'  => $crear('pregunta-frecuente', 'Como me inscribo? (ficticia)', ['post_content' => 'Respuesta ficticia.']),
];
flush_rewrite_rules(true);

// Rutas para el arnés de regresión visual: se publican en uploads como JSON.
$ofertas = get_posts(['post_type' => 'oferta-academica', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$ficha = $ofertas ? get_permalink($ofertas[0]) : '';
$rutas = [
    'portada' => home_url('/'), 'formacion' => home_url('/formacion/'), 'taxonomia' => home_url('/formacion/maestrias/'),
    'ficha-oferta' => $ficha, 'carta' => $ficha ? trailingslashit($ficha) . 'carta/' : '',
    'seminarios' => home_url('/formacion/seminarios/'), 'seminario' => $ids['seminario'] ? get_permalink($ids['seminario']) : '',
    'equipo' => home_url('/equipo/'), 'docente' => $ids['docente'] ? get_permalink($ids['docente']) : '',
    'programas' => home_url('/programas/'), 'programa' => $ids['programa'] ? get_permalink($ids['programa']) : '',
    'convenios' => home_url('/convenios/'), // La ficha de convenio responde 404 con datos mínimos: pendiente de saber qué dato exige.
    'contacto' => home_url('/contactos/'), 'faq' => home_url('/preguntas-frecuentes/'),
    'novedades' => home_url('/category/novedades/'), 'noticia' => $ids['noticia'] ? get_permalink($ids['noticia']) : '',
    'evento' => $ids['evento'] ? get_permalink($ids['evento']) : '',
    'gracias' => home_url('/gracias/'), 'busqueda' => home_url('/?s=genero'), '404' => home_url('/ruta-inexistente-flacso/'),
];
$dir = wp_upload_dir();
file_put_contents(trailingslashit($dir['basedir']) . 'flacso-rutas.json', wp_json_encode(array_filter($rutas), JSON_UNESCAPED_SLASHES));
echo "seed ok\n";
