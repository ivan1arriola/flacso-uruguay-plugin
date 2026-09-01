<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reglas canónicas para Seminarios Integrados.
 *
 * Un Seminario es integrado cuando `componentes` contiene al menos un Seminario.
 * Los datos derivados nunca se persisten como una copia en el integrado:
 * - créditos = suma transitiva de créditos de los Seminarios componentes;
 * - docentes de una Edición integrada = unión de docentes de sus Ediciones componentes;
 * - encuentros sincrónicos = unión de encuentros de sus Ediciones componentes.
 *
 * `ediciones_componentes` sólo puede contener Ediciones cuyos Seminarios padre
 * sean componentes directos del Seminario integrado, con máximo una Edición por
 * componente directo.
 */
final class FLACSO_Seminario_Integrado {
    private static bool $reconciling = false;

    public static function init(): void {
        add_filter('get_post_metadata', [self::class, 'filter_derived_metadata'], 20, 5);
        add_filter('update_post_metadata', [self::class, 'prevent_derived_writes'], 20, 5);
        add_filter('add_post_metadata', [self::class, 'prevent_derived_adds'], 20, 5);

        add_action('added_post_meta', [self::class, 'on_meta_changed'], 30, 4);
        add_action('updated_post_meta', [self::class, 'on_meta_changed'], 30, 4);
        add_action('save_post_' . FLACSO_Edicion::POST_TYPE, [self::class, 'reconcile_edition_components'], 40, 1);

        if (is_admin()) {
            add_action('admin_footer-post.php', [self::class, 'render_admin_guard']);
            add_action('admin_footer-post-new.php', [self::class, 'render_admin_guard']);
        }
    }

    public static function component_seminar_ids(int $seminar_id): array {
        $raw = get_post_meta($seminar_id, FLACSO_Seminario::META_COMPONENTES, true);
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $component) {
            $id = is_array($component)
                ? absint($component['seminario_id'] ?? 0)
                : absint($component);
            if ($id > 0 && $id !== $seminar_id && get_post_type($id) === FLACSO_Seminario::POST_TYPE) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    public static function is_integrated(int $seminar_id): bool {
        return !empty(self::component_seminar_ids($seminar_id));
    }

    public static function credits(int $seminar_id, array $visited = []): float {
        if ($seminar_id <= 0 || isset($visited[$seminar_id])) {
            return 0.0;
        }
        $visited[$seminar_id] = true;

        $components = self::component_seminar_ids($seminar_id);
        if (empty($components)) {
            return max(0.0, (float) get_post_meta($seminar_id, 'creditos', true));
        }

        $total = 0.0;
        foreach ($components as $component_id) {
            $total += self::credits($component_id, $visited);
        }
        return $total;
    }

    /**
     * Devuelve las Ediciones componentes válidas de una Edición integrada.
     * Sólo acepta Ediciones cuyo Seminario padre sea un componente directo.
     * Como máximo se conserva una Edición por Seminario componente.
     */
    public static function component_edition_ids(int $edition_id): array {
        if (get_post_type($edition_id) !== FLACSO_Edicion::POST_TYPE) {
            return [];
        }

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        $allowed_seminars = array_fill_keys(self::component_seminar_ids($seminar_id), true);
        if (empty($allowed_seminars)) {
            return [];
        }

        $raw = get_post_meta($edition_id, 'ediciones_componentes', true);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        $used_parent = [];
        foreach ($raw as $component) {
            $component_edition_id = is_array($component)
                ? absint($component['edicion_id'] ?? 0)
                : absint($component);
            if ($component_edition_id <= 0 || $component_edition_id === $edition_id) {
                continue;
            }
            if (get_post_type($component_edition_id) !== FLACSO_Edicion::POST_TYPE) {
                continue;
            }

            $component_parent = absint(get_post_meta($component_edition_id, FLACSO_Edicion::META_PARENT_ID, true));
            if (!isset($allowed_seminars[$component_parent]) || isset($used_parent[$component_parent])) {
                continue;
            }

            $result[] = $component_edition_id;
            $used_parent[$component_parent] = true;
        }
        return $result;
    }

    public static function edition_teachers(int $edition_id, array $visited = []): array {
        if ($edition_id <= 0 || isset($visited[$edition_id])) {
            return [];
        }
        $visited[$edition_id] = true;

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            $teachers = get_post_meta($edition_id, 'docentes', true);
            return self::unique_positive_ids(is_array($teachers) ? $teachers : []);
        }

        $teachers = [];
        foreach (self::component_edition_ids($edition_id) as $component_edition_id) {
            $teachers = array_merge($teachers, self::edition_teachers($component_edition_id, $visited));
        }
        return self::unique_positive_ids($teachers);
    }

    public static function edition_meetings(int $edition_id, array $visited = []): array {
        if ($edition_id <= 0 || isset($visited[$edition_id])) {
            return [];
        }
        $visited[$edition_id] = true;

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            $meetings = get_post_meta($edition_id, 'encuentros_sincronicos', true);
            return is_array($meetings) ? FLACSO_Edicion::sanitize_meetings($meetings) : [];
        }

        $meetings = [];
        foreach (self::component_edition_ids($edition_id) as $component_edition_id) {
            foreach (self::edition_meetings($component_edition_id, $visited) as $meeting) {
                $key = implode('|', [
                    (string) ($meeting['fecha'] ?? ''),
                    (string) ($meeting['hora_inicio'] ?? ''),
                    (string) ($meeting['hora_fin'] ?? ''),
                    (string) ($meeting['zona_horaria'] ?? 'America/Montevideo'),
                ]);
                $meetings[$key] = $meeting;
            }
        }

        $meetings = array_values($meetings);
        usort($meetings, static function (array $a, array $b): int {
            $ka = ($a['fecha'] ?? '') . ' ' . ($a['hora_inicio'] ?? '');
            $kb = ($b['fecha'] ?? '') . ' ' . ($b['hora_inicio'] ?? '');
            return strcmp($ka, $kb);
        });
        return $meetings;
    }

    /** Hace que las lecturas públicas y administrativas usen siempre valores derivados. */
    public static function filter_derived_metadata($value, $object_id, $meta_key, $single, $meta_type) {
        $object_id = absint($object_id);
        if ($object_id <= 0) {
            return $value;
        }

        if ($meta_key === 'creditos' && get_post_type($object_id) === FLACSO_Seminario::POST_TYPE && self::is_integrated($object_id)) {
            $credits = self::credits($object_id);
            return $single ? $credits : [$credits];
        }

        if (get_post_type($object_id) === FLACSO_Edicion::POST_TYPE) {
            $seminar_id = absint(get_post_meta($object_id, FLACSO_Edicion::META_PARENT_ID, true));
            if (self::is_integrated($seminar_id)) {
                if ($meta_key === 'docentes') {
                    $teachers = self::edition_teachers($object_id);
                    return $single ? $teachers : [$teachers];
                }
                if ($meta_key === 'encuentros_sincronicos') {
                    $meetings = self::edition_meetings($object_id);
                    return $single ? $meetings : [$meetings];
                }
            }
        }

        return $value;
    }

    /** Evita que UI/API conviertan un dato derivado en una copia persistida. */
    public static function prevent_derived_writes($check, $object_id, $meta_key, $meta_value, $prev_value) {
        return self::should_block_derived_write(absint($object_id), (string) $meta_key) ? true : $check;
    }

    public static function prevent_derived_adds($check, $object_id, $meta_key, $meta_value, $unique) {
        return self::should_block_derived_write(absint($object_id), (string) $meta_key) ? true : $check;
    }

    private static function should_block_derived_write(int $object_id, string $meta_key): bool {
        if ($meta_key === 'creditos' && get_post_type($object_id) === FLACSO_Seminario::POST_TYPE) {
            return self::is_integrated($object_id);
        }
        if (in_array($meta_key, ['docentes', 'encuentros_sincronicos'], true) && get_post_type($object_id) === FLACSO_Edicion::POST_TYPE) {
            $seminar_id = absint(get_post_meta($object_id, FLACSO_Edicion::META_PARENT_ID, true));
            return self::is_integrated($seminar_id);
        }
        return false;
    }

    public static function on_meta_changed($meta_id, $object_id, $meta_key, $meta_value): void {
        if (self::$reconciling) {
            return;
        }

        $object_id = absint($object_id);
        if ($meta_key === 'ediciones_componentes' && get_post_type($object_id) === FLACSO_Edicion::POST_TYPE) {
            self::reconcile_edition_components($object_id);
            return;
        }

        if ($meta_key === FLACSO_Seminario::META_COMPONENTES && get_post_type($object_id) === FLACSO_Seminario::POST_TYPE) {
            $edition_ids = get_posts([
                'post_type' => FLACSO_Edicion::POST_TYPE,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => FLACSO_Edicion::META_PARENT_ID,
                'meta_value' => $object_id,
            ]);
            foreach ($edition_ids as $edition_id) {
                self::reconcile_edition_components((int) $edition_id);
            }
        }
    }

    /** Persiste sólo relaciones válidas; los datos derivados siguen sin persistirse. */
    public static function reconcile_edition_components(int $edition_id): void {
        if (self::$reconciling || get_post_type($edition_id) !== FLACSO_Edicion::POST_TYPE) {
            return;
        }

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            return;
        }

        $valid_ids = self::component_edition_ids($edition_id);
        $normalized = [];
        foreach ($valid_ids as $index => $component_edition_id) {
            $normalized[] = [
                'edicion_id' => $component_edition_id,
                'orden' => $index + 1,
            ];
        }

        $raw = get_post_meta($edition_id, 'ediciones_componentes', true);
        $raw = is_array($raw) ? FLACSO_Edicion::sanitize_components($raw) : [];
        if ($raw === $normalized) {
            return;
        }

        self::$reconciling = true;
        update_post_meta($edition_id, 'ediciones_componentes', $normalized);
        self::$reconciling = false;
    }

    /**
     * Ajustes de UX: en entidades integradas los campos derivados son de sólo lectura
     * y el selector de Ediciones sólo ofrece las pertenecientes a componentes directos.
     */
    public static function render_admin_guard(): void {
        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        if ($post->post_type === FLACSO_Seminario::POST_TYPE && self::is_integrated((int) $post->ID)) {
            $credits = self::credits((int) $post->ID);
            ?>
            <script>
            jQuery(function($) {
                var $credit = $('[name="creditos"]');
                if ($credit.length) {
                    $credit.val(<?php echo wp_json_encode((string) $credits); ?>).prop('readonly', true).attr('aria-readonly', 'true');
                    if (!$credit.next('.flacso-derived-help').length) {
                        $credit.after('<p class="description flacso-derived-help"><strong>Valor derivado:</strong> suma de los créditos de los seminarios componentes.</p>');
                    }
                }
            });
            </script>
            <?php
            return;
        }

        if ($post->post_type !== FLACSO_Edicion::POST_TYPE) {
            return;
        }

        $seminar_id = absint(get_post_meta($post->ID, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            return;
        }

        $allowed_seminars = array_fill_keys(self::component_seminar_ids($seminar_id), true);
        $allowed_editions = [];
        if ($allowed_seminars) {
            $candidate_ids = get_posts([
                'post_type' => FLACSO_Edicion::POST_TYPE,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post__not_in' => [$post->ID],
            ]);
            foreach ($candidate_ids as $candidate_id) {
                $parent = absint(get_post_meta($candidate_id, FLACSO_Edicion::META_PARENT_ID, true));
                if (isset($allowed_seminars[$parent])) {
                    $allowed_editions[] = (int) $candidate_id;
                }
            }
        }
        ?>
        <script>
        jQuery(function($) {
            var allowed = <?php echo wp_json_encode(array_values($allowed_editions)); ?>.map(String);
            var $selector = $('#flacso-componente-selector');
            $selector.find('option[value]').each(function() {
                var value = String($(this).val() || '');
                if (value && allowed.indexOf(value) === -1) {
                    $(this).remove();
                }
            });

            $('#flacso-edicion-docentes').html(
                '<h3>Docentes derivados</h3><p>Los docentes de esta edición son la unión, sin duplicados, de los docentes de sus Ediciones componentes. Para cambiarlos, editá las Ediciones componentes.</p>'
            );

            $('.flacso-edicion-card').each(function() {
                var $card = $(this);
                var heading = $.trim($card.find('h3').first().text());
                if (heading === 'Encuentros sincrónicos') {
                    $card.html('<h3>Encuentros sincrónicos derivados</h3><p>La programación es la unión cronológica de los encuentros de las Ediciones componentes. Para cambiarla, editá las Ediciones componentes.</p>');
                }
            });
        });
        </script>
        <?php
    }

    private static function unique_positive_ids(array $ids): array {
        $result = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id > 0) {
                $result[$id] = $id;
            }
        }
        return array_values($result);
    }
}
