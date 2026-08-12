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
            'es_integrado',
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
            'seminarios_componentes',
            'mail_contacto',
            'acreditacion',
            'descripcion_horas',
            'mostrar_en_formulario',
            'es_asincronico',
            'abierto_publico',
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
        $raw_meta = Seminario_Meta::get_meta($post->ID);
        $meta = self::get_display_meta($post->ID, $raw_meta);
        $integrated_parent = empty($raw_meta['es_integrado']) ? self::get_integrated_parent($post->ID) : null;
        return array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'meta' => $meta,
            'seminario_integrado' => $integrated_parent ? self::build_integrated_summary($integrated_parent) : null,
            'seminarios_componentes_data' => !empty($raw_meta['es_integrado']) ? self::get_components_data($post->ID) : array(),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
            'featured_media' => (int) get_post_thumbnail_id($post->ID),
            'ofertas_academicas' => self::get_related_offers($post->ID),
            'taxonomies' => Seminario_Taxonomies::get_taxonomies($post->ID),
        );
    }

    public static function get_integrated_parent($component_id)
    {
        $wrappers = get_posts(array(
            'post_type' => 'seminario',
            'post_status' => current_user_can('edit_posts') ? array('publish', 'draft', 'private') : array('publish'),
            'posts_per_page' => -1,
            'meta_key' => '_seminario_es_integrado',
            'meta_value' => '1',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        foreach ($wrappers as $wrapper) {
            $ids = get_post_meta($wrapper->ID, '_seminario_seminarios_componentes', true);
            if (is_array($ids) && in_array((int) $component_id, array_map('intval', $ids), true)) {
                return $wrapper;
            }
        }
        return null;
    }

    public static function get_all_component_ids()
    {
        $ids = array();
        $wrappers = get_posts(array(
            'post_type' => 'seminario',
            'post_status' => current_user_can('edit_posts') ? array('publish', 'draft', 'private') : array('publish'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_seminario_es_integrado',
            'meta_value' => '1',
        ));
        foreach ($wrappers as $wrapper_id) {
            $component_ids = get_post_meta($wrapper_id, '_seminario_seminarios_componentes', true);
            if (is_array($component_ids)) {
                $ids = array_merge($ids, array_map('intval', $component_ids));
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    public static function get_component_posts($wrapper_id)
    {
        $ids = get_post_meta($wrapper_id, '_seminario_seminarios_componentes', true);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : array();
        if (empty($ids)) {
            return array();
        }
        return get_posts(array(
            'post_type' => 'seminario',
            'post_status' => current_user_can('edit_posts') ? array('publish', 'draft', 'private') : array('publish'),
            'post__in' => $ids,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ));
    }

    public static function get_related_offers($post_id)
    {
        $raw_meta = Seminario_Meta::get_meta($post_id);
        if (empty($raw_meta['es_integrado'])) {
            return Seminario_Taxonomies::get_related_ofertas($post_id);
        }
        $offers = array();
        foreach (self::get_component_posts($post_id) as $component) {
            foreach (Seminario_Taxonomies::get_related_ofertas($component->ID) as $offer) {
                if (isset($offer['id'])) {
                    $offers[(int) $offer['id']] = $offer;
                }
            }
        }
        return array_values($offers);
    }

    public static function get_components_data($wrapper_id)
    {
        $items = array();
        foreach (self::get_component_posts($wrapper_id) as $component) {
            $items[] = array(
                'id' => $component->ID,
                'title' => get_the_title($component),
                'slug' => $component->post_name,
                'link' => get_permalink($component),
                'featured_image' => get_the_post_thumbnail_url($component->ID, 'full') ?: null,
                'meta' => Seminario_Meta::get_meta($component->ID),
            );
        }
        return $items;
    }

    public static function build_integrated_summary($wrapper)
    {
        $meta = Seminario_Meta::get_meta($wrapper->ID);
        return array(
            'id' => $wrapper->ID,
            'title' => get_the_title($wrapper),
            'slug' => $wrapper->post_name,
            'url' => get_permalink($wrapper),
            'preinscripcion_url' => home_url('/formacion/seminarios/' . $wrapper->post_name . '/preinscripcion/'),
            'featured_image' => get_the_post_thumbnail_url($wrapper->ID, 'full') ?: null,
            'meta' => array(
                'valor_uyu' => $meta['valor_uyu'],
                'valor_uyu_15_descuento' => $meta['valor_uyu_15_descuento'],
                'valor_usd' => $meta['valor_usd'],
                'valor_usd_15_descuento' => $meta['valor_usd_15_descuento'],
                'abierto_publico' => $meta['abierto_publico'],
            ),
        );
    }

    public static function get_display_meta($post_id, $raw_meta = null)
    {
        $meta = is_array($raw_meta) ? $raw_meta : Seminario_Meta::get_meta($post_id);
        if (!empty($meta['es_integrado'])) {
            return self::aggregate_integrated_meta($post_id, $meta);
        }
        $parent = self::get_integrated_parent($post_id);
        if ($parent) {
            $parent_meta = Seminario_Meta::get_meta($parent->ID);
            foreach (array('valor_uyu', 'valor_uyu_15_descuento', 'valor_usd', 'valor_usd_15_descuento') as $price_key) {
                $meta[$price_key] = $parent_meta[$price_key];
            }
        }
        return $meta;
    }

    private static function aggregate_integrated_meta($wrapper_id, $wrapper_meta)
    {
        $components = self::get_component_posts($wrapper_id);
        $starts = array();
        $ends = array();
        $credits = 0.0;
        $hours = 0;
        $meetings = array();
        $teachers = array();
        $modalities = array();
        $general_objectives = array();
        $approval_methods = array();
        $specific_objectives = array();
        $academic_units = array();
        $all_async = !empty($components);
        $all_masters = !empty($components);
        $all_doctorates = !empty($components);
        foreach ($components as $component) {
            $meta = Seminario_Meta::get_meta($component->ID);
            if (!empty($meta['periodo_inicio'])) $starts[] = $meta['periodo_inicio'];
            if (!empty($meta['periodo_fin'])) $ends[] = $meta['periodo_fin'];
            $credits += is_numeric($meta['creditos']) ? (float) $meta['creditos'] : 0;
            $hours += is_numeric($meta['carga_horaria']) ? (int) $meta['carga_horaria'] : 0;
            $meetings = array_merge($meetings, is_array($meta['encuentros_sincronicos']) ? $meta['encuentros_sincronicos'] : array());
            $teachers = array_merge($teachers, is_array($meta['docentes']) ? $meta['docentes'] : array());
            if (!empty($meta['modalidad'])) $modalities[] = wp_strip_all_tags($meta['modalidad']);
            $component_title = get_the_title($component);
            if (!empty($meta['objetivo_general'])) {
                $general_objectives[] = '<h3>' . esc_html($component_title) . '</h3>' . wp_kses_post($meta['objetivo_general']);
            }
            if (!empty($meta['forma_aprobacion'])) {
                $approval_methods[] = '<h3>' . esc_html($component_title) . '</h3>' . wp_kses_post($meta['forma_aprobacion']);
            }
            foreach (is_array($meta['objetivos_especificos']) ? $meta['objetivos_especificos'] : array() as $objective) {
                if ($objective !== '') $specific_objectives[] = $objective;
            }
            foreach (is_array($meta['unidades_academicas']) ? $meta['unidades_academicas'] : array() as $unit) {
                if (!is_array($unit)) continue;
                $unit['seminario_id'] = $component->ID;
                $unit['seminario_titulo'] = $component_title;
                $academic_units[] = $unit;
            }
            $all_async = $all_async && !empty($meta['es_asincronico']);
            $all_masters = $all_masters && !empty($meta['acredita_maestria']);
            $all_doctorates = $all_doctorates && !empty($meta['acredita_doctorado']);
        }
        sort($starts);
        sort($ends);
        $wrapper_meta['periodo_inicio'] = !empty($starts) ? reset($starts) : '';
        $wrapper_meta['periodo_fin'] = !empty($ends) ? end($ends) : '';
        $wrapper_meta['creditos'] = $credits > 0 ? $credits : '';
        $wrapper_meta['carga_horaria'] = $hours > 0 ? $hours : '';
        $wrapper_meta['encuentros_sincronicos'] = $meetings;
        $wrapper_meta['docentes'] = array_values(array_unique(array_map('intval', $teachers)));
        $wrapper_meta['modalidad'] = implode(' / ', array_values(array_unique(array_filter($modalities))));
        $wrapper_meta['objetivo_general'] = implode('', $general_objectives);
        $wrapper_meta['forma_aprobacion'] = implode('', $approval_methods);
        $wrapper_meta['objetivos_especificos'] = $specific_objectives;
        $wrapper_meta['unidades_academicas'] = $academic_units;
        $wrapper_meta['es_asincronico'] = $all_async;
        $wrapper_meta['acredita_maestria'] = $all_masters;
        $wrapper_meta['acredita_doctorado'] = $all_doctorates;
        return $wrapper_meta;
    }
}
