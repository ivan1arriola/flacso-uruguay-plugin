<?php
/**
 * Detalles del evento: modalidad, lugar, estado, gratuidad, programa, fin con zona horaria,
 * Schema.org Event y archivo .ics.
 *
 * Reutiliza los metas `_charla_*` que ya lee la REST. Inicio y fin se guardan como ISO 8601
 * con offset (`_charla_inicio`, `_charla_fin`) a partir de los campos fecha/hora del editor.
 * shortcut: la zona horaria es la del sitio (wp_timezone), sin selector; ampliar si hay eventos en otra zona.
 */

if (!defined('ABSPATH')) {
    exit;
}

const FLACSO_EVENTO_ESTADOS = [
    'programado' => 'Programado',
    'reprogramado' => 'Reprogramado',
    'cancelado' => 'Cancelado',
];

const FLACSO_EVENTO_MODALIDADES = [
    'virtual' => 'Virtual',
    'presencial' => 'Presencial',
    'hibrida' => 'Híbrida',
];

function flacso_eventos_get_estado(int $event_id): string {
    $estado = (string) get_post_meta($event_id, '_evento_estado', true);
    return isset(FLACSO_EVENTO_ESTADOS[$estado]) ? $estado : 'programado';
}

add_action('init', 'flacso_eventos_register_detail_meta', 20);
function flacso_eventos_register_detail_meta(): void {
    $auth = static function () {
        return current_user_can('edit_posts');
    };
    $defs = [
        '_charla_modalidad' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
        '_charla_plataforma_reunion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
        '_charla_lugar_nombre' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_direccion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_fin' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_evento_estado' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
        '_evento_gratuito' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
        '_evento_programa_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
    ];
    foreach ($defs as $key => $def) {
        register_post_meta('evento', $key, array_merge(['single' => true, 'show_in_rest' => true, 'auth_callback' => $auth], $def));
    }
}

add_action('add_meta_boxes_evento', static function (): void {
    add_meta_box('flacso_evento_details', __('Modalidad, lugar y estado', 'flacso-uruguay'), 'flacso_eventos_render_details_meta_box', 'evento', 'normal', 'high');
});

function flacso_eventos_render_details_meta_box($post): void {
    wp_nonce_field('flacso_evento_details_save', 'flacso_evento_details_nonce');
    $m = static function (string $key, $default = '') use ($post) {
        $v = get_post_meta($post->ID, $key, true);
        return $v === '' ? $default : $v;
    };
    $programas = get_posts(['post_type' => 'programa-academico', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    $select = static function (string $name, array $options, string $current): void {
        echo '<select name="' . esc_attr($name) . '">';
        foreach ($options as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($label));
        }
        echo '</select>';
    };
    ?>
    <p><label><strong><?php esc_html_e('Estado', 'flacso-uruguay'); ?></strong><br>
        <?php $select('evento_estado', FLACSO_EVENTO_ESTADOS, flacso_eventos_get_estado($post->ID)); ?></label></p>
    <p><label><strong><?php esc_html_e('Modalidad', 'flacso-uruguay'); ?></strong><br>
        <?php $select('evento_modalidad', FLACSO_EVENTO_MODALIDADES, (string) $m('_charla_modalidad', 'virtual')); ?></label></p>
    <p><label><?php esc_html_e('Plataforma (virtual o híbrida)', 'flacso-uruguay'); ?><br>
        <?php $select('evento_plataforma', ['zoom' => 'Zoom', 'meet' => 'Meet'], flacso_eventos_normalize_reunion_platform($m('_charla_plataforma_reunion'))); ?></label></p>
    <p><label><?php esc_html_e('Lugar (presencial o híbrida)', 'flacso-uruguay'); ?><br>
        <input class="widefat" type="text" name="evento_lugar" value="<?php echo esc_attr($m('_charla_lugar_nombre')); ?>"></label></p>
    <p><label><?php esc_html_e('Dirección', 'flacso-uruguay'); ?><br>
        <input class="widefat" type="text" name="evento_direccion" value="<?php echo esc_attr($m('_charla_direccion')); ?>"></label></p>
    <p><label><input type="checkbox" name="evento_gratuito" value="1" <?php checked((bool) $m('_evento_gratuito', 1)); ?>> <?php esc_html_e('Actividad gratuita', 'flacso-uruguay'); ?></label></p>
    <p><label><?php esc_html_e('Oferta académica relacionada (opcional)', 'flacso-uruguay'); ?><br>
        <select name="evento_programa_id"><option value="0">—</option>
        <?php foreach ($programas as $p) {
            printf('<option value="%d"%s>%s</option>', $p->ID, selected((int) $m('_evento_programa_id', 0), $p->ID, false), esc_html($p->post_title));
        } ?>
        </select></label></p>
    <?php
}

add_action('save_post_evento', 'flacso_eventos_save_details', 30);
function flacso_eventos_save_details($post_id): void {
    if (
        !isset($_POST['flacso_evento_details_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_evento_details_nonce'])), 'flacso_evento_details_save')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }
    $in = static function (string $key): string {
        return sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
    };

    $estado = $in('evento_estado');
    $modalidad = $in('evento_modalidad');
    $modalidad = isset(FLACSO_EVENTO_MODALIDADES[$modalidad]) ? $modalidad : 'virtual';
    update_post_meta($post_id, '_evento_estado', isset(FLACSO_EVENTO_ESTADOS[$estado]) ? $estado : 'programado');
    update_post_meta($post_id, '_charla_modalidad', $modalidad);
    update_post_meta($post_id, '_charla_plataforma_reunion', flacso_eventos_normalize_reunion_platform($in('evento_plataforma')));
    update_post_meta($post_id, '_charla_lugar_nombre', $in('evento_lugar'));
    update_post_meta($post_id, '_charla_direccion', $in('evento_direccion'));
    update_post_meta($post_id, '_evento_gratuito', isset($_POST['evento_gratuito']) ? 1 : 0);
    update_post_meta($post_id, '_evento_programa_id', absint($_POST['evento_programa_id'] ?? 0));

    // Inicio/fin ISO con offset, derivados de los campos fecha/hora (que siguen siendo la fuente).
    $iso = static function (string $date, string $time) {
        $dt = $date !== '' ? DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . ($time ?: '00:00'), wp_timezone()) : false;
        return $dt ?: null;
    };
    $start = $iso((string) get_post_meta($post_id, 'evento_inicio_fecha', true), (string) get_post_meta($post_id, 'evento_inicio_hora', true));
    $end_date = (string) get_post_meta($post_id, 'evento_fin_fecha', true) ?: (string) get_post_meta($post_id, 'evento_inicio_fecha', true);
    $end = $iso($end_date, (string) get_post_meta($post_id, 'evento_fin_hora', true));

    $errors = [];
    if ($start && $end && $end <= $start) {
        $errors[] = __('La fecha de fin debe ser posterior a la de inicio.', 'flacso-uruguay');
        $end = null;
    }
    if (in_array($modalidad, ['presencial', 'hibrida'], true) && $in('evento_lugar') === '') {
        $errors[] = __('Una actividad presencial o híbrida necesita un lugar.', 'flacso-uruguay');
    }
    if ($end) {
        update_post_meta($post_id, '_charla_fin', $end->format('Y-m-d\TH:i:sP'));
        if ($start) {
            update_post_meta($post_id, '_charla_duracion_minutos', (int) (($end->getTimestamp() - $start->getTimestamp()) / 60));
        }
    } else {
        delete_post_meta($post_id, '_charla_fin');
    }
    if ($errors) {
        set_transient('flacso_evento_errors_' . get_current_user_id(), $errors, 60);
    }
}

add_action('admin_notices', static function (): void {
    $key = 'flacso_evento_errors_' . get_current_user_id();
    $errors = get_transient($key);
    if (!$errors) {
        return;
    }
    delete_transient($key);
    foreach ($errors as $e) {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($e) . '</p></div>';
    }
});

/** Datos de inicio/fin como DateTimeImmutable (fin = inicio si falta). */
function flacso_eventos_get_range(int $event_id): ?array {
    try {
        $start = new DateTimeImmutable((string) get_post_meta($event_id, '_charla_inicio', true));
        $end_raw = (string) get_post_meta($event_id, '_charla_fin', true);
        return [$start, $end_raw !== '' ? new DateTimeImmutable($end_raw) : $start];
    } catch (Exception $e) {
        return null;
    }
}

add_action('wp_head', 'flacso_eventos_print_schema');
function flacso_eventos_print_schema(): void {
    if (!is_singular('evento')) {
        return;
    }
    $id = get_queried_object_id();
    $range = flacso_eventos_get_range($id);
    if (!$range) {
        return;
    }
    $modalidad = (string) get_post_meta($id, '_charla_modalidad', true) ?: 'virtual';
    $lugar = (string) get_post_meta($id, '_charla_lugar_nombre', true);
    $estado = flacso_eventos_get_estado($id);

    $physical = ['@type' => 'Place', 'name' => $lugar, 'address' => (string) get_post_meta($id, '_charla_direccion', true)];
    $virtual = ['@type' => 'VirtualLocation', 'url' => get_permalink($id)];
    $location = $modalidad === 'virtual' ? $virtual : ($modalidad === 'presencial' ? $physical : [$physical, $virtual]);
    $mode = ['virtual' => 'OnlineEventAttendanceMode', 'presencial' => 'OfflineEventAttendanceMode', 'hibrida' => 'MixedEventAttendanceMode'][$modalidad] ?? 'OnlineEventAttendanceMode';
    $status = ['programado' => 'EventScheduled', 'reprogramado' => 'EventRescheduled', 'cancelado' => 'EventCancelled'][$estado];

    $performers = [];
    foreach (flacso_eventos_get_speaker_ids($id) as $pid) {
        $performers[] = ['@type' => 'Person', 'name' => function_exists('dp_nombre_completo') ? dp_nombre_completo($pid) : get_the_title($pid), 'url' => get_permalink($pid)];
    }
    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => get_the_title($id),
        'description' => wp_strip_all_tags(get_the_excerpt($id)),
        'startDate' => $range[0]->format('c'),
        'endDate' => $range[1]->format('c'),
        'eventStatus' => 'https://schema.org/' . $status,
        'eventAttendanceMode' => 'https://schema.org/' . $mode,
        'location' => $location,
        'image' => get_the_post_thumbnail_url($id, 'large') ?: null,
        'performer' => $performers ?: null,
        'organizer' => ['@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')],
        'url' => get_permalink($id),
    ]);
    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
}

/** URL del .ics del evento. */
function flacso_eventos_ics_url(int $event_id): string {
    return add_query_arg('evento_ics', '1', get_permalink($event_id));
}

add_action('template_redirect', 'flacso_eventos_serve_ics');
function flacso_eventos_serve_ics(): void {
    if (!is_singular('evento') || empty($_GET['evento_ics'])) {
        return;
    }
    $id = get_queried_object_id();
    $range = flacso_eventos_get_range($id);
    if (!$range) {
        return;
    }
    $esc = static function (string $s): string {
        return preg_replace('/\r?\n/', '\\n', str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s));
    };
    $utc = static function (DateTimeImmutable $d): string {
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    };
    $place = trim(get_post_meta($id, '_charla_lugar_nombre', true) . ' ' . get_post_meta($id, '_charla_direccion', true));
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//FLACSO Uruguay//Eventos//ES', 'BEGIN:VEVENT',
        'UID:evento-' . $id . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
        'DTSTAMP:' . $utc(new DateTimeImmutable('now')),
        'DTSTART:' . $utc($range[0]), 'DTEND:' . $utc($range[1]),
        'SUMMARY:' . $esc(get_the_title($id)),
        'DESCRIPTION:' . $esc(get_permalink($id)),
        'URL:' . get_permalink($id),
        'STATUS:' . (flacso_eventos_get_estado($id) === 'cancelado' ? 'CANCELLED' : 'CONFIRMED'),
    ];
    if ($place !== '') {
        $lines[] = 'LOCATION:' . $esc($place);
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';
    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="evento-' . $id . '.ics"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}
