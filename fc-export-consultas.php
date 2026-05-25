<?php
// standalone script to export fc_consulta posts to JSON
// To run: php fc-export-consultas.php

// Find wp-load.php
$wp_load = null;
$possible_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../wp-load.php',
    __DIR__ . '/wp-load.php',
    dirname(__DIR__, 4) . '/wp-load.php', // WordPress root relative to plugin
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    echo "Error: wp-load.php not found. Make sure this script is placed inside a WordPress environment.\n";
    exit(1);
}

define('WP_USE_THEMES', false);
require_once $wp_load;

echo "WordPress loaded successfully from $wp_load.\n";

// Force register CPT just in case it was decommissioned so we can query it
if (!post_type_exists('fc_consulta') && function_exists('fc_register_cpt')) {
    fc_register_cpt();
}

$query_args = [
    'post_type'      => 'fc_consulta',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'orderby'        => 'ID',
    'order'          => 'ASC'
];

$query = new WP_Query($query_args);
$posts = $query->posts;

echo "Found " . count($posts) . " fc_consulta posts.\n";

$exported_data = [];

foreach ($posts as $post) {
    $post_id = $post->ID;
    
    $control_number = get_post_meta($post_id, 'fc_control_number', true);
    $nombre = get_post_meta($post_id, 'fc_nombre', true);
    $apellido = get_post_meta($post_id, 'fc_apellido', true);
    $email = get_post_meta($post_id, 'fc_email', true);
    $telefono = get_post_meta($post_id, 'fc_telefono', true);
    $asunto = get_post_meta($post_id, 'fc_asunto', true);
    $mensaje = get_post_meta($post_id, 'fc_mensaje', true);
    if (empty($mensaje)) {
        $mensaje = $post->post_content;
    }
    
    $url_referer = get_post_meta($post_id, 'fc_url_referer', true);
    $ip = get_post_meta($post_id, 'fc_ip', true);
    $user_agent = get_post_meta($post_id, 'fc_user_agent', true);
    $navegador = get_post_meta($post_id, 'fc_navegador', true);
    $sistema_operativo = get_post_meta($post_id, 'fc_sistema_operativo', true);
    $fecha_envio = get_post_meta($post_id, 'fc_fecha_envio', true);
    if (empty($fecha_envio)) {
        $fecha_envio = $post->post_date_gmt && $post->post_date_gmt !== '0000-00-00 00:00:00' ? $post->post_date_gmt : $post->post_date;
    }
    
    // Convert date to ISO 8601
    $created_at = date('c', strtotime($fecha_envio));
    
    $exported_data[] = [
        'wordpress_id' => $post_id,
        'control_number' => $control_number,
        'nombre' => $nombre ?: 'Sin nombre',
        'apellido' => $apellido ?: 'Sin apellido',
        'email' => $email ?: 'sin-email@flacso.edu.uy',
        'telefono' => $telefono ?: null,
        'asunto' => $asunto ?: 'Consulta sin asunto',
        'mensaje' => $mensaje ?: '',
        'url_referer' => $url_referer ?: null,
        'ip' => $ip ?: null,
        'user_agent' => $user_agent ?: null,
        'navegador' => $navegador ?: null,
        'sistema_operativo' => $sistema_operativo ?: null,
        'created_at' => $created_at
    ];
}

$output_file = __DIR__ . '/consultas_export.json';
$json_content = json_encode($exported_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($output_file, $json_content)) {
    echo "Successfully exported " . count($exported_data) . " items to $output_file.\n";
} else {
    echo "Error writing export file.\n";
}
