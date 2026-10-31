<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estado de un evento según sus fechas. Es independiente de WordPress para poder probarlo.
 */
final class Evento_Schedule {
    public const SIN_FECHA = 'sin_fecha';
    public const PROXIMO = 'proximo';
    public const EN_CURSO = 'en_curso';
    public const FINALIZADO = 'finalizado';

    /** @return int|null Marca de tiempo local; sin hora se toma el inicio (o el final) del día. */
    public static function timestamp(string $date, string $time, bool $end_of_day, \DateTimeZone $tz): ?int {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        $time = trim($time);
        $time = $time !== '' ? substr($time, 0, 5) : ($end_of_day ? '23:59' : '00:00');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
        return $dt instanceof \DateTimeImmutable ? $dt->getTimestamp() : null;
    }

    /**
     * @return array{state:string,start:?int,end:?int}
     */
    public static function resolve(string $start_date, string $start_time, string $end_date, string $end_time, int $now, \DateTimeZone $tz): array {
        $start = self::timestamp($start_date, $start_time, false, $tz);
        if ($start === null) {
            return ['state' => self::SIN_FECHA, 'start' => null, 'end' => null];
        }
        // Sin fecha de fin el evento dura hasta el cierre del día en que empieza.
        $end = self::timestamp($end_date !== '' ? $end_date : $start_date, $end_time, true, $tz);
        if ($end === null || $end < $start) {
            $end = $start;
        }
        $state = $start > $now ? self::PROXIMO : ($end >= $now ? self::EN_CURSO : self::FINALIZADO);
        return ['state' => $state, 'start' => $start, 'end' => $end];
    }
}

final class Evento_Admin_List {
    private const VIEW_VAR = 'evento_cuando';

    /** @var array<int,array{state:string,start:?int,end:?int}>|null */
    private static $schedules = null;

    public static function init(): void {
        add_filter('manage_evento_posts_columns', [self::class, 'columns']);
        add_action('manage_evento_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_filter('manage_edit-evento_sortable_columns', [self::class, 'sortable_columns']);
        add_filter('views_edit-evento', [self::class, 'views']);
        add_action('pre_get_posts', [self::class, 'order_and_filter']);
        add_action('admin_head-edit.php', [self::class, 'print_styles']);
    }

    /** @return array<string,string> */
    public static function state_labels(): array {
        return [
            Evento_Schedule::EN_CURSO => __('En curso', 'flacso-uruguay'),
            Evento_Schedule::PROXIMO => __('Próximos', 'flacso-uruguay'),
            Evento_Schedule::FINALIZADO => __('Finalizados', 'flacso-uruguay'),
            Evento_Schedule::SIN_FECHA => __('Sin fecha', 'flacso-uruguay'),
        ];
    }

    private static function schedule(int $post_id): array {
        return self::all_schedules()[$post_id] ?? ['state' => Evento_Schedule::SIN_FECHA, 'start' => null, 'end' => null];
    }

    /** @return array<int,array{state:string,start:?int,end:?int}> */
    private static function all_schedules(): array {
        if (self::$schedules === null) {
            self::$schedules = [];
            $ids = get_posts([
                'post_type' => 'evento',
                'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);
            $now = time();
            $tz = wp_timezone();
            foreach ($ids as $id) {
                self::$schedules[(int) $id] = Evento_Schedule::resolve(
                    (string) get_post_meta($id, 'evento_inicio_fecha', true),
                    (string) get_post_meta($id, 'evento_inicio_hora', true),
                    (string) get_post_meta($id, 'evento_fin_fecha', true),
                    (string) get_post_meta($id, 'evento_fin_hora', true),
                    $now,
                    $tz
                );
            }
        }
        return self::$schedules;
    }

    /** @param array<string,string> $columns */
    public static function columns(array $columns): array {
        $out = [];
        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                $out['evento_cuando'] = __('Fecha del evento', 'flacso-uruguay');
                $out['evento_estado'] = __('Estado', 'flacso-uruguay');
                $out['evento_web'] = __('En la web', 'flacso-uruguay');
                $out['evento_inscripcion'] = __('Inscripción', 'flacso-uruguay');
                $label = __('Creado', 'flacso-uruguay');
            }
            $out[$key] = $label;
        }
        return $out;
    }

    public static function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'evento_cuando':
                $schedule = self::schedule($post_id);
                if ($schedule['start'] === null) {
                    echo '<span class="flacso-evento-muted">' . esc_html__('Sin fecha', 'flacso-uruguay') . '</span>';
                    break;
                }
                $start_time = trim((string) get_post_meta($post_id, 'evento_inicio_hora', true));
                echo '<strong>' . esc_html(wp_date('j M Y', $schedule['start'])) . '</strong>';
                if ($start_time !== '') {
                    echo ' <span class="flacso-evento-muted">' . esc_html(substr($start_time, 0, 5)) . '</span>';
                }
                if ($schedule['end'] !== null && wp_date('Y-m-d', $schedule['end']) !== wp_date('Y-m-d', $schedule['start'])) {
                    echo '<br><span class="flacso-evento-muted">' . esc_html(sprintf(__('hasta el %s', 'flacso-uruguay'), wp_date('j M Y', $schedule['end']))) . '</span>';
                }
                break;

            case 'evento_estado':
                $state = self::schedule($post_id)['state'];
                $labels = [
                    Evento_Schedule::EN_CURSO => __('En curso', 'flacso-uruguay'),
                    Evento_Schedule::PROXIMO => __('Próximo', 'flacso-uruguay'),
                    Evento_Schedule::FINALIZADO => __('Finalizado', 'flacso-uruguay'),
                    Evento_Schedule::SIN_FECHA => __('Sin fecha', 'flacso-uruguay'),
                ];
                echo '<span class="flacso-evento-badge flacso-evento-badge--' . esc_attr($state) . '">' . esc_html($labels[$state]) . '</span>';
                break;

            case 'evento_web':
                if (get_post_status($post_id) !== 'publish') {
                    echo '<span class="flacso-evento-muted">' . esc_html__('No publicado', 'flacso-uruguay') . '</span>';
                } elseif (!empty(get_post_meta($post_id, '_evento_mostrar_web', true))) {
                    echo '<span class="flacso-evento-badge flacso-evento-badge--visible">' . esc_html__('Visible', 'flacso-uruguay') . '</span>';
                } else {
                    echo '<span class="flacso-evento-muted">' . esc_html__('Oculto', 'flacso-uruguay') . '</span>';
                }
                break;

            case 'evento_inscripcion':
                $url = (string) get_post_meta($post_id, '_evento_formulario_url', true);
                if ($url !== '') {
                    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('Formulario', 'flacso-uruguay') . '</a>';
                } else {
                    echo '<span class="flacso-evento-muted">—</span>';
                }
                break;
        }
    }

    /** @param array<string,mixed> $columns */
    public static function sortable_columns(array $columns): array {
        $columns['evento_cuando'] = ['evento_cuando', true];
        $columns['evento_estado'] = ['evento_estado', false];
        return $columns;
    }

    /** @param array<string,string> $views */
    public static function views(array $views): array {
        $counts = array_fill_keys(array_keys(self::state_labels()), 0);
        foreach (array_keys(self::all_schedules()) as $id) {
            $counts[self::schedule($id)['state']]++;
        }
        $current = isset($_GET[self::VIEW_VAR]) ? sanitize_key(wp_unslash($_GET[self::VIEW_VAR])) : '';
        foreach (self::state_labels() as $state => $label) {
            if ($counts[$state] === 0) {
                continue;
            }
            $url = add_query_arg([self::VIEW_VAR => $state, 'post_type' => 'evento'], admin_url('edit.php'));
            $class = $current === $state ? ' class="current" aria-current="page"' : '';
            $views['evento_' . $state] = '<a href="' . esc_url($url) . '"' . $class . '>' . esc_html($label)
                . ' <span class="count">(' . number_format_i18n($counts[$state]) . ')</span></a>';
        }
        return $views;
    }

    public static function order_and_filter(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'evento') {
            return;
        }
        $screen_orderby = (string) $query->get('orderby');
        $filter = isset($_GET[self::VIEW_VAR]) ? sanitize_key(wp_unslash($_GET[self::VIEW_VAR])) : '';
        $by_state = $screen_orderby === 'evento_estado';
        $by_date = $screen_orderby === 'evento_cuando' || $screen_orderby === '';
        if ($filter === '' && !$by_state && !$by_date) {
            return;
        }

        $schedules = self::all_schedules();
        $ids = array_keys($schedules);
        if ($filter !== '' && isset(self::state_labels()[$filter])) {
            $ids = array_values(array_filter($ids, static function ($id) use ($schedules, $filter) {
                return $schedules[$id]['state'] === $filter;
            }));
        }

        // Sin orden elegido: lo que viene primero y lo más reciente de último. Con orden explícito se respeta la flecha.
        $explicit = $screen_orderby !== '';
        $direction = $explicit && strtolower((string) $query->get('order')) === 'asc' ? 1 : -1;
        $rank = [Evento_Schedule::EN_CURSO => 0, Evento_Schedule::PROXIMO => 1, Evento_Schedule::FINALIZADO => 2, Evento_Schedule::SIN_FECHA => 3];

        usort($ids, static function ($a, $b) use ($schedules, $by_state, $explicit, $direction, $rank) {
            if ($by_state || !$explicit) {
                $diff = $rank[$schedules[$a]['state']] <=> $rank[$schedules[$b]['state']];
                if ($diff !== 0) {
                    return $by_state ? $diff * $direction : $diff;
                }
            }
            $start_a = $schedules[$a]['start'];
            $start_b = $schedules[$b]['start'];
            if ($start_a === null || $start_b === null) {
                return ($start_a === null) <=> ($start_b === null);
            }
            // Próximos: el más cercano primero; el resto: el más reciente primero.
            $upcoming = in_array($schedules[$a]['state'], [Evento_Schedule::PROXIMO, Evento_Schedule::EN_CURSO], true);
            $order = $explicit ? $direction : ($upcoming ? 1 : -1);
            return ($start_a <=> $start_b) * $order;
        });

        $query->set('post__in', $ids ?: [0]);
        $query->set('orderby', 'post__in');
    }

    public static function print_styles(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'evento') {
            return;
        }
        ?>
        <style>
          body.post-type-evento .column-evento_cuando {width:15%}
          body.post-type-evento .column-evento_estado,body.post-type-evento .column-evento_web {width:10%}
          body.post-type-evento .column-evento_inscripcion {width:9%}
          body.post-type-evento .flacso-evento-muted {color:#66788f}
          body.post-type-evento .flacso-evento-badge {display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:650;background:#e9eef5;color:#3c4f66}
          body.post-type-evento .flacso-evento-badge--en_curso {background:#dff3e1;color:#1f5c24}
          body.post-type-evento .flacso-evento-badge--proximo {background:#dfecfa;color:#205687}
          body.post-type-evento .flacso-evento-badge--sin_fecha {background:#fff1c8;color:#785112}
          body.post-type-evento .flacso-evento-badge--visible {background:#dff3e1;color:#1f5c24}
          @media(max-width:782px) {
            body.post-type-evento .wp-list-table .column-evento_estado,body.post-type-evento .wp-list-table .column-evento_web,body.post-type-evento .wp-list-table .column-evento_inscripcion {display:none}
          }
        </style>
        <?php
    }
}

Evento_Admin_List::init();
