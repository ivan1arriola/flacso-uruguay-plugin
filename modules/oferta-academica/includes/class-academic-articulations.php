<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Planes de articulación académica (contenido editorial).
 *
 * Las etapas son unidades curriculares, no matrículas ni cohortes. Sus
 * conexiones expresan precedencia y las Ofertas determinan titulaciones.
 * No interviene en preinscripciones, pagos ni habilitaciones de estudiantes.
 */
final class FLACSO_Academic_Articulations {
    public const POST_TYPE = 'articulacion-academica';
    public const META_NODES = '_flacso_articulacion_etapas';
    public const META_EDGES = '_flacso_articulacion_transiciones';
    public const META_NOTES = '_flacso_articulacion_presentaciones';
    public const META_INDEX = '_flacso_articulacion_ofertas_index';
    public const NONCE = 'flacso_articulacion_nonce';

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Planes de articulación', 'flacso-uruguay'),
                'singular_name' => __('Plan de articulación', 'flacso-uruguay'),
                'add_new_item' => __('Nuevo plan de articulación', 'flacso-uruguay'),
                'edit_item' => __('Editar plan de articulación', 'flacso-uruguay'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => FLACSO_Admin_Panel::PAGE_SLUG,
            'show_in_rest' => false,
            'supports' => ['title', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** @return array<int,string> */
    private static function mentions(int $offer_id): array {
        $value = get_post_meta($offer_id, 'menciones', true);
        return FLACSO_Oferta_Academica::sanitize_text_list(is_array($value) ? $value : []);
    }

    /**
     * Valida la configuración completa sin modificar el estado persistido.
     * No descarta silenciosamente aristas inválidas: deben corregirse.
     *
     * @return array<string,array>|WP_Error
     */
    public static function validate($input) {
        if (!is_array($input)) {
            return new WP_Error('articulacion_formato', __('Formato de articulación no válido.', 'flacso-uruguay'));
        }
        $nodes = [];
        $by_key = [];
        $raw_nodes = $input['etapas'] ?? [];
        if (!is_array($raw_nodes)) {
            return new WP_Error('articulacion_etapas', __('Las etapas deben ser una lista.', 'flacso-uruguay'));
        }
        foreach ($raw_nodes as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $key = sanitize_key((string) ($raw['clave'] ?? ''));
            $name = sanitize_text_field((string) ($raw['nombre'] ?? ''));
            $level = absint($raw['ciclo'] ?? 0);
            $offer = absint($raw['oferta_id'] ?? 0);
            $mention = sanitize_text_field((string) ($raw['mencion'] ?? ''));
            $description = wp_kses_post((string) ($raw['descripcion'] ?? ''));
            $calendar = esc_url_raw((string) ($raw['calendario_url'] ?? ''), ['https']);
            if ($key === '' && $name === '' && $offer === 0 && $level === 0) {
                continue;
            }
            if ($key === '' || $name === '' || $level < 1 || !$offer) {
                return new WP_Error('articulacion_etapa_incompleta', __('Cada etapa requiere identificador, nombre, número de ciclo y Oferta Académica.', 'flacso-uruguay'));
            }
            if (isset($by_key[$key])) {
                return new WP_Error('articulacion_clave_repetida', __('Hay dos etapas con el mismo identificador.', 'flacso-uruguay'));
            }
            if (get_post_type($offer) !== FLACSO_Oferta_Academica::POST_TYPE || get_post_status($offer) === 'trash') {
                return new WP_Error('articulacion_oferta_invalida', __('Una etapa referencia una Oferta Académica inexistente o eliminada.', 'flacso-uruguay'));
            }
            if ($mention !== '' && !in_array($mention, self::mentions($offer), true)) {
                return new WP_Error('articulacion_mencion_invalida', __('La mención de una etapa debe existir en su Oferta Académica.', 'flacso-uruguay'));
            }
            $node = [
                'clave' => $key,
                'ciclo' => $level,
                'nombre' => $name,
                'oferta_id' => $offer,
                'mencion' => $mention,
                'descripcion' => $description,
                'calendario_url' => $calendar,
            ];
            $nodes[] = $node;
            $by_key[$key] = $node;
        }
        usort($nodes, static function (array $a, array $b): int {
            return $a['ciclo'] <=> $b['ciclo'];
        });

        $edges = [];
        $unique_edges = [];
        $incoming = [];
        $raw_edges = $input['transiciones'] ?? [];
        if (!is_array($raw_edges)) {
            return new WP_Error('articulacion_transiciones', __('Las transiciones deben ser una lista.', 'flacso-uruguay'));
        }
        foreach ($raw_edges as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $from = sanitize_key((string) ($raw['origen'] ?? ''));
            $to = sanitize_key((string) ($raw['destino'] ?? ''));
            $mention = sanitize_text_field((string) ($raw['mencion'] ?? ''));
            if ($from === '' && $to === '' && $mention === '') {
                continue;
            }
            if (!isset($by_key[$from], $by_key[$to]) || $from === $to) {
                return new WP_Error('articulacion_destino', __('Una transición apunta a etapas inexistentes o a sí misma.', 'flacso-uruguay'));
            }
            if ($by_key[$to]['ciclo'] !== $by_key[$from]['ciclo'] + 1) {
                return new WP_Error('articulacion_orden', __('Las transiciones deben conectar ciclos consecutivos en sentido ascendente.', 'flacso-uruguay'));
            }
            if ($mention !== '' && !in_array($mention, self::mentions($by_key[$from]['oferta_id']), true)) {
                return new WP_Error('articulacion_mencion_transicion', __('La mención habilitante debe pertenecer a la Oferta Académica de origen.', 'flacso-uruguay'));
            }
            $unique = $from . '|' . $to . '|' . $mention;
            if (isset($unique_edges[$unique])) {
                return new WP_Error('articulacion_arista_repetida', __('Hay transiciones duplicadas.', 'flacso-uruguay'));
            }
            $unique_edges[$unique] = true;
            $incoming[$to] = true;
            $edges[] = ['origen' => $from, 'destino' => $to, 'mencion' => $mention];
        }

        if ($nodes) {
            $lowest = min(array_column($nodes, 'ciclo'));
            if ($lowest !== 1) {
                return new WP_Error('articulacion_inicio', __('La articulación debe comenzar por el Ciclo 1.', 'flacso-uruguay'));
            }
            foreach ($nodes as $node) {
                if ($node['ciclo'] > 1 && !isset($incoming[$node['clave']])) {
                    return new WP_Error('articulacion_sin_requisito', __('Toda etapa de un ciclo superior requiere una transición desde el ciclo anterior.', 'flacso-uruguay'));
                }
            }
        }

        $notes = [];
        $seen_notes = [];
        $member_offers = array_unique(array_column($nodes, 'oferta_id'));
        $raw_notes = $input['presentaciones'] ?? [];
        if (!is_array($raw_notes)) {
            return new WP_Error('articulacion_presentaciones', __('Las presentaciones deben ser una lista.', 'flacso-uruguay'));
        }
        foreach ($raw_notes as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $offer = absint($raw['oferta_id'] ?? 0);
            $intro = wp_kses_post((string) ($raw['introduccion'] ?? ''));
            $continuation = wp_kses_post((string) ($raw['continuidad'] ?? ''));
            if (!$offer && trim(wp_strip_all_tags($intro . $continuation)) === '') {
                continue;
            }
            if (!in_array($offer, $member_offers, true) || isset($seen_notes[$offer])) {
                return new WP_Error('articulacion_presentacion_oferta', __('Cada presentación debe corresponder a una Oferta del plan, sin duplicados.', 'flacso-uruguay'));
            }
            $seen_notes[$offer] = true;
            $notes[] = [
                'oferta_id' => $offer,
                'introduccion' => $intro,
                'continuidad' => $continuation,
            ];
        }

        return ['etapas' => $nodes, 'transiciones' => $edges, 'presentaciones' => $notes];
    }

    public static function offer_index(array $nodes): string {
        $ids = array_unique(array_map('absint', array_column($nodes, 'oferta_id')));
        sort($ids, SORT_NUMERIC);
        return $ids ? '|' . implode('||', $ids) . '|' : '';
    }

    /**
     * Modelo de lectura para los dos canales editoriales (web y carta).
     * Solo se exponen planes publicados y destinos de ofertas públicas.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function for_offer(int $offer_id): array {
        if ($offer_id < 1 || get_post_type($offer_id) !== FLACSO_Oferta_Academica::POST_TYPE) {
            return [];
        }
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [[
                'key' => self::META_INDEX,
                'value' => '|' . $offer_id . '|',
                'compare' => 'LIKE',
            ]],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $plans = [];
        foreach ($posts as $plan) {
            $raw_nodes = get_post_meta($plan->ID, self::META_NODES, true);
            $raw_nodes = is_array($raw_nodes) ? $raw_nodes : [];
            $nodes = [];
            $keys = [];
            foreach ($raw_nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $target = absint($node['oferta_id'] ?? 0);
                if ($target < 1 || get_post_type($target) !== FLACSO_Oferta_Academica::POST_TYPE || get_post_status($target) !== 'publish') {
                    continue;
                }
                $key = (string) ($node['clave'] ?? '');
                $nodes[] = [
                    'clave' => $key,
                    'ciclo' => absint($node['ciclo'] ?? 0),
                    'nombre' => (string) ($node['nombre'] ?? ''),
                    'oferta_id' => $target,
                    'oferta_titulo' => get_the_title($target),
                    'oferta_url' => get_permalink($target),
                    'mencion' => (string) ($node['mencion'] ?? ''),
                    'descripcion' => (string) ($node['descripcion'] ?? ''),
                    'calendario_url' => (string) ($node['calendario_url'] ?? ''),
                ];
                $keys[$key] = true;
            }
            if (!in_array($offer_id, array_column($nodes, 'oferta_id'), true)) {
                continue;
            }
            $raw_edges = get_post_meta($plan->ID, self::META_EDGES, true);
            $edges = [];
            foreach (is_array($raw_edges) ? $raw_edges : [] as $edge) {
                if (isset($keys[$edge['origen'] ?? ''], $keys[$edge['destino'] ?? ''])) {
                    $edges[] = [
                        'origen' => (string) $edge['origen'],
                        'destino' => (string) $edge['destino'],
                        'mencion' => (string) ($edge['mencion'] ?? ''),
                    ];
                }
            }
            $raw_notes = get_post_meta($plan->ID, self::META_NOTES, true);
            $note = [];
            foreach (is_array($raw_notes) ? $raw_notes : [] as $item) {
                if (absint($item['oferta_id'] ?? 0) === $offer_id) {
                    $note = $item;
                    break;
                }
            }
            $plans[] = [
                'id' => (int) $plan->ID,
                'nombre' => (string) $plan->post_title,
                'etapas' => $nodes,
                'transiciones' => $edges,
                'presentacion' => [
                    'introduccion' => (string) ($note['introduccion'] ?? ''),
                    'continuidad' => (string) ($note['continuidad'] ?? ''),
                ],
                'oferta_actual' => $offer_id,
            ];
        }
        return $plans;
    }
}
