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
flush_rewrite_rules(true);
echo "seed ok\n";
