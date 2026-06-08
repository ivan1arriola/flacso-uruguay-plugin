<?php
require_once 'wp-load.php';
$query = new WP_Query([
    'post_type' => 'oferta-academica',
    'posts_per_page' => 1,
    'orderby' => 'ID',
    'order' => 'DESC'
]);
if ($query->have_posts()) {
    $post_id = $query->posts[0]->ID;
    $schema = Oferta_Data_Schema::get_schema($post_id);
    print_r($schema);
}
