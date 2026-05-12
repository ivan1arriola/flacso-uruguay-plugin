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
            'valor_uyu',
            'valor_uyu_15_descuento',
            'valor_usd',
            'valor_usd_15_descuento',
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
            'mail_contacto',
            'acreditacion',
            'descripcion_horas',
            'mostrar_en_formulario',
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
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'meta' => Seminario_Meta::get_meta($post->ID),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
            'featured_media' => (int) get_post_thumbnail_id($post->ID),
            'ofertas_academicas' => Seminario_Taxonomies::get_related_ofertas($post->ID),
            'taxonomies' => Seminario_Taxonomies::get_taxonomies($post->ID),
        );
    }
}
