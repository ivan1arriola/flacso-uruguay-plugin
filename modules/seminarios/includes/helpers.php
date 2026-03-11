<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Helpers
{
    public static function meta_keys()
    {
        return array(
            'nombre',
            'periodo_inicio',
            'periodo_fin',
            'creditos',
            'carga_horaria',
            'acredita_maestria',
            'acredita_doctorado',
            'forma_aprobacion',
            'modalidad',
            'objetivo_general',
            'presentacion_seminario',
            'encuentros_sincronicos',
            'objetivos_especificos',
            'unidades_academicas',
            'docentes',
        );
    }

    public static function taxonomy_keys()
    {
        return array();
    }

    public static function normalize_terms($terms)
    {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        } elseif (!is_array($terms)) {
            $terms = array();
        }

        $clean = array();
        foreach ($terms as $term) {
            if (is_numeric($term)) {
                $id = absint($term);
                if ($id > 0) {
                    $clean[] = $id;
                }
            } else {
                $slug = sanitize_title($term);
                if ($slug !== '') {
                    $clean[] = $slug;
                }
            }
        }

        return $clean;
    }

    public static function permissions_write()
    {
        return current_user_can('edit_posts');
    }

    public static function permissions_terms()
    {
        return current_user_can('manage_categories');
    }

    public static function build_response($post)
    {
        return array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'content' => $post->post_content,
            'status' => $post->post_status,
            'meta' => Seminario_Meta::get_meta($post->ID),
            'posgrados' => Seminario_Taxonomies::get_related_ofertas($post->ID),
            'taxonomies' => Seminario_Taxonomies::get_taxonomies($post->ID),
        );
    }
}
