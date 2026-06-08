<?php
require_once dirname(__DIR__, 2) . '/wp-load.php';

$posts = get_posts(['post_type' => 'oferta-academica', 'posts_per_page' => 1]);
if (empty($posts)) {
    die("No ofertas found\n");
}
$post_id = $posts[0]->ID;
echo "Post ID: " . $post_id . "\n";
$meta = get_post_meta($post_id);
echo "tabla_precio_id in meta: " . ($meta['tabla_precio_id'][0] ?? 'NOT FOUND') . "\n";

$schema = Oferta_Data_Schema::get_schema($post_id);
echo "schema tabla_precio_id: " . ($schema['tabla_precio_id'] ?? 'NOT FOUND') . "\n";
echo "schema precios_filas: " . print_r($schema['precios_filas'] ?? 'NOT FOUND', true) . "\n";
