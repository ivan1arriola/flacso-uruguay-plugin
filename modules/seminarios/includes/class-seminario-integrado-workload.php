<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Derivación canónica de carga horaria para Seminarios Integrados.
 *
 * La carga horaria de un Seminario Integrado es la suma transitiva de la carga
 * horaria de sus Seminarios componentes. El valor no se persiste en el
 * Seminario Integrado: se calcula en lectura, igual que los créditos.
 */
final class FLACSO_Seminario_Integrado_Workload {
    public static function init(): void {
        add_filter('get_post_metadata', [self::class, 'filter_derived_metadata'], 15, 5);
        add_filter('update_post_metadata', [self::class, 'prevent_derived_writes'], 15, 5);
        add_filter('add_post_metadata', [self::class, 'prevent_derived_adds'], 15, 5);

        if (is_admin()) {
            add_action('admin_footer-post.php', [self::class, 'render_admin_guard']);
            add_action('admin_footer-post-new.php', [self::class, 'render_admin_guard']);
        }
    }

    public static function workload(int $seminar_id, array $visited = []): float {
        if ($seminar_id <= 0 || isset($visited[$seminar_id])) {
            return 0.0;
        }
        $visited[$seminar_id] = true;

        $components = FLACSO_Seminario_Integrado::component_seminar_ids($seminar_id);
        if (empty($components)) {
            // Evita reentrar en get_post_metadata al leer el valor base.
            $raw = function_exists('get_metadata_raw')
                ? get_metadata_raw('post', $seminar_id, 'carga_horaria', true)
                : get_post_meta($seminar_id, 'carga_horaria', true);
            return max(0.0, (float) $raw);
        }

        $total = 0.0;
        foreach ($components as $component_id) {
            $total += self::workload((int) $component_id, $visited);
        }
        return $total;
    }

    public static function filter_derived_metadata($value, $object_id, $meta_key, $single, $meta_type) {
        $object_id = absint($object_id);
        if ((string) $meta_key !== 'carga_horaria' || $object_id <= 0) {
            return $value;
        }
        if (get_post_type($object_id) !== FLACSO_Seminario::POST_TYPE) {
            return $value;
        }
        if (!FLACSO_Seminario_Integrado::is_integrated($object_id)) {
            return $value;
        }

        $workload = self::workload($object_id);
        return $single ? $workload : [$workload];
    }

    public static function prevent_derived_writes($check, $object_id, $meta_key, $meta_value, $prev_value) {
        return self::should_block(absint($object_id), (string) $meta_key) ? true : $check;
    }

    public static function prevent_derived_adds($check, $object_id, $meta_key, $meta_value, $unique) {
        return self::should_block(absint($object_id), (string) $meta_key) ? true : $check;
    }

    private static function should_block(int $object_id, string $meta_key): bool {
        return $meta_key === 'carga_horaria'
            && get_post_type($object_id) === FLACSO_Seminario::POST_TYPE
            && FLACSO_Seminario_Integrado::is_integrated($object_id);
    }

    public static function render_admin_guard(): void {
        global $post;
        if (!$post instanceof WP_Post || $post->post_type !== FLACSO_Seminario::POST_TYPE) {
            return;
        }
        if (!FLACSO_Seminario_Integrado::is_integrated((int) $post->ID)) {
            return;
        }

        $workload = self::workload((int) $post->ID);
        ?>
        <script>
        jQuery(function($) {
            var $field = $('[name="carga_horaria"]');
            if (!$field.length) return;

            $field
                .val(<?php echo wp_json_encode((string) $workload); ?>)
                .prop('readonly', true)
                .attr('aria-readonly', 'true');

            if (!$field.next('.flacso-derived-workload-help').length) {
                $field.after('<p class="description flacso-derived-workload-help"><strong>Valor derivado:</strong> suma de la carga horaria de los seminarios componentes.</p>');
            }
        });
        </script>
        <?php
    }
}
