<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal para gestionar el CPT "evento"
 */
class CPT_Eventos_Manager {

    /**
     * Constructor. Inicializa hooks y acciones.
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta']);
        add_action('wp_ajax_buscar_posts_evento', [$this, 'search_posts_ajax']);
        add_action('admin_menu', [$this, 'add_visualizer_menu']);
    }

    /**
     * Registra el Custom Post Type "evento"
     */
    public static function register_post_type(): void {
        $labels = [
            'name'               => __('Eventos', 'cpt-eventos'),
            'singular_name'      => __('Evento', 'cpt-eventos'),
            'add_new'            => __('Agregar nuevo', 'cpt-eventos'),
            'add_new_item'       => __('Agregar nuevo evento', 'cpt-eventos'),
            'edit_item'          => __('Editar evento', 'cpt-eventos'),
            'new_item'           => __('Nuevo evento', 'cpt-eventos'),
            'menu_name'          => __('Eventos', 'cpt-eventos'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'show_in_rest'       => true,
            'has_archive'        => false,
        ];

        register_post_type('evento', $args);
    }

    /**
     * Agrega meta box al CPT evento
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'cpt_evento_details',
            __('Detalles del evento', 'cpt-eventos'),
            [$this, 'render_meta_box'],
            'evento',
            'normal',
            'high'
        );
    }

    /**
     * Renderiza el contenido del meta box
     */
    public function render_meta_box(WP_Post $post): void {
        $inicio_fecha   = get_post_meta($post->ID, 'evento_inicio_fecha', true);
        $inicio_hora    = get_post_meta($post->ID, 'evento_inicio_hora', true);
        $fin_fecha      = get_post_meta($post->ID, 'evento_fin_fecha', true);
        $fin_hora       = get_post_meta($post->ID, 'evento_fin_hora', true);
        $post_asociado  = get_post_meta($post->ID, 'evento_post_asociado', true);
        $titulo_asociado = $post_asociado ? get_the_title($post_asociado) : '';
        $display_title = get_post_meta($post->ID, 'evento_display_title', true);

        wp_nonce_field('cpt_evento_save', 'cpt_evento_nonce');
        $search_nonce = wp_create_nonce('flacso_buscar_posts_evento');
        ?>
        <p><strong><?php esc_html_e('Inicio', 'cpt-eventos'); ?>:</strong></p>
        <input type="date" name="evento_inicio_fecha" value="<?php echo esc_attr($inicio_fecha); ?>">
        <input type="time" name="evento_inicio_hora" value="<?php echo esc_attr($inicio_hora); ?>">

        <p><strong><?php esc_html_e('Fin', 'cpt-eventos'); ?>:</strong></p>
        <input type="date" name="evento_fin_fecha" value="<?php echo esc_attr($fin_fecha); ?>">
        <input type="time" name="evento_fin_hora" value="<?php echo esc_attr($fin_hora); ?>">

        <p><label for="buscar_post_asociado"><strong><?php esc_html_e('Buscar post o página asociada', 'cpt-eventos'); ?>:</strong></label></p>
        <input type="hidden" name="evento_post_asociado" id="evento_post_asociado" value="<?php echo esc_attr($post_asociado); ?>">
        <input type="text" id="buscar_post_asociado" value="<?php echo esc_attr($titulo_asociado); ?>" placeholder="<?php esc_attr_e('Escribí para buscar…', 'cpt-eventos'); ?>" style="width:100%">
        <div id="resultados_busqueda_post" style="max-height:180px;overflow:auto;margin-top:5px;border:1px solid #ccc;border-radius:4px;display:none;"></div>

        <p>
            <label for="evento_display_title"><strong><?php esc_html_e('Nombre visible en la landing', 'cpt-eventos'); ?></strong></label><br>
            <input type="text" id="evento_display_title" name="evento_display_title" value="<?php echo esc_attr($display_title); ?>" style="width:100%" placeholder="<?php esc_attr_e('Si se deja vacío se usará el título del contenido asociado', 'cpt-eventos'); ?>">
        </p>

        <script>
        jQuery(document).ready(function($){
            let timer;
            const input = $('#buscar_post_asociado');
            const resultados = $('#resultados_busqueda_post');
            const campoID = $('#evento_post_asociado');

            input.on('input', function(){
                clearTimeout(timer);
                const q = $(this).val().trim();
                if(q.length < 2){
                    resultados.hide();
                    return;
                }
                timer = setTimeout(() => {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'buscar_posts_evento',
                            q: q,
                            nonce: '<?php echo esc_js($search_nonce); ?>'
                        },
                        success: function(res){
                            resultados.empty();
                            if(res.success && res.data.length){
                                res.data.forEach(item => {
                                    resultados.append(
                                        `<div class="opcion-post" data-id="${item.id}" style="padding:6px 8px;cursor:pointer;border-bottom:1px solid #eee;">${item.titulo} <small style="color:#666;">(${item.tipo})</small></div>`
                                    );
                                });
                                resultados.show();
                            } else {
                                resultados.html('<div style="padding:6px 8px;color:#666;"><?php echo esc_js(__('Sin resultados', 'cpt-eventos')); ?></div>').show();
                            }
                        }
                    });
                }, 300);
            });

            resultados.on('click', '.opcion-post', function(){
                const id = $(this).data('id');
                const texto = $(this).text();
                campoID.val(id);
                input.val(texto);
                resultados.hide();
            });

            $(document).on('click', function(e){
                if(!$(e.target).closest('#buscar_post_asociado, #resultados_busqueda_post').length){
                    resultados.hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Guarda los metadatos del evento
     */
    public function save_meta(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['cpt_evento_nonce']) || !wp_verify_nonce($_POST['cpt_evento_nonce'], 'cpt_evento_save')) {
            return;
        }

        if (get_post_type($post_id) !== 'evento' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, 'evento_inicio_fecha', sanitize_text_field($_POST['evento_inicio_fecha'] ?? ''));
        update_post_meta($post_id, 'evento_inicio_hora', sanitize_text_field($_POST['evento_inicio_hora'] ?? ''));
        update_post_meta($post_id, 'evento_fin_fecha', sanitize_text_field($_POST['evento_fin_fecha'] ?? ''));
        update_post_meta($post_id, 'evento_fin_hora', sanitize_text_field($_POST['evento_fin_hora'] ?? ''));
        update_post_meta($post_id, 'evento_post_asociado', isset($_POST['evento_post_asociado']) ? (int) $_POST['evento_post_asociado'] : 0);
        update_post_meta($post_id, 'evento_display_title', sanitize_text_field($_POST['evento_display_title'] ?? ''));
    }

    /**
     * AJAX: Buscar posts o páginas para asociar a evento
     */
    public function search_posts_ajax(): void {
        check_ajax_referer('flacso_buscar_posts_evento', 'nonce');
        $q = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        if ($q === '') {
            wp_send_json_success([]);
        }

        $resultados = get_posts([
            's'              => $q,
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => 10,
        ]);

        $data = array_map(static function ($p) {
            return [
                'id'     => $p->ID,
                'titulo' => $p->post_title,
                'tipo'   => $p->post_type === 'page' ? __('Página', 'cpt-eventos') : __('Post', 'cpt-eventos'),
            ];
        }, $resultados);

        wp_send_json_success($data);
    }

    /**
     * Agrega el menú para el visualizador de eventos
     */
    public function add_visualizer_menu(): void {
        add_submenu_page(
            'edit.php?post_type=evento',
            __('Visualizador de Eventos', 'cpt-eventos'),
            __('Visualizador', 'cpt-eventos'),
            'edit_posts',
            'visualizador_eventos',
            [$this, 'render_visualizer']
        );
    }

    /**
     * Renderiza el visualizador de eventos
     */
    public function render_visualizer(): void {
        $eventos = get_posts([
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'meta_key'       => 'evento_inicio_fecha',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('Visualizador de Eventos', 'cpt-eventos') . '</h1>';

        if (empty($eventos)) {
            echo '<p>' . esc_html__('No hay eventos registrados.', 'cpt-eventos') . '</p></div>';
            return;
        }

        $this->render_visualizer_prioritized($eventos);
        return;

        ?>
        <style>
            .grid-eventos {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .evento-card {
                background: #fff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 6px rgba(0,0,0,0.08);
                border: 1px solid #ddd;
                display: flex;
                flex-direction: column;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .evento-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .evento-img img {
                width: 100%;
                height: 160px;
                object-fit: cover;
            }
            .evento-content {
                padding: 12px 15px;
                flex: 1;
            }
            .evento-content h2 {
                margin: 0 0 8px;
                font-size: 16px;
            }
            .evento-content p {
                margin: 4px 0;
                color: #444;
                font-size: 13px;
            }
            .evento-meta {
                font-size: 12px;
                color: #777;
            }
            .evento-tiempo {
                font-style: italic;
                color: #555;
            }
            .evento-acciones {
                padding: 10px 15px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
                text-align: right;
            }
            .evento-acciones a {
                text-decoration: none;
                color: #0073aa;
                font-weight: 500;
            }
            .evento-acciones a:hover {
                color: #00a0d2;
            }
        </style>
        <div class="grid-eventos">
        <?php

        $hoy = current_time('timestamp');

        foreach ($eventos as $evento) {
            $inicio_fecha   = get_post_meta($evento->ID, 'evento_inicio_fecha', true);
            $inicio_hora    = get_post_meta($evento->ID, 'evento_inicio_hora', true);
            $fin_fecha      = get_post_meta($evento->ID, 'evento_fin_fecha', true);
            $fin_hora       = get_post_meta($evento->ID, 'evento_fin_hora', true);
            $post_asociado  = (int) get_post_meta($evento->ID, 'evento_post_asociado', true);

            if (!$post_asociado || get_post_status($post_asociado) !== 'publish') {
                continue;
            }

            $inicio_timestamp = strtotime(trim($inicio_fecha . ' ' . $inicio_hora));
            $fin_timestamp    = $fin_fecha ? strtotime(trim($fin_fecha . ' ' . $fin_hora)) : null;
            $faltan_dias      = $inicio_timestamp ? floor(($inicio_timestamp - $hoy) / DAY_IN_SECONDS) : null;
            $duracion_dias    = ($inicio_timestamp && $fin_timestamp) ? floor(($fin_timestamp - $inicio_timestamp) / DAY_IN_SECONDS) : 0;

            $img = get_the_post_thumbnail_url($post_asociado, 'medium') ?: 'https://via.placeholder.com/300x160?text=Evento';

            echo '<div class="evento-card">';
            echo '<div class="evento-img"><img src="' . esc_url($img) . '" alt=""></div>';
            echo '<div class="evento-content">';
            echo '<h2>' . esc_html(get_the_title($post_asociado)) . '</h2>';
            if ($inicio_fecha) {
                echo '<p class="evento-meta">📅 ' . esc_html(date_i18n('j F Y', strtotime($inicio_fecha))) . ' — ⏰ ' . esc_html($inicio_hora) . '</p>';
            }

            if ($duracion_dias >= 1 && $fin_fecha) {
                echo '<p class="evento-meta">' . sprintf(
                    esc_html__('Hasta el %s', 'cpt-eventos'),
                    esc_html(date_i18n('j F Y', strtotime($fin_fecha)))
                ) . '</p>';
            }

            if ($faltan_dias !== null) {
                if ($faltan_dias > 1) {
                    echo '<p class="evento-tiempo">' . sprintf(esc_html__('Faltan %s días', 'cpt-eventos'), $faltan_dias) . '</p>';
                } elseif ($faltan_dias === 1) {
                    echo '<p class="evento-tiempo">' . esc_html__('Mañana', 'cpt-eventos') . '</p>';
                } elseif ($faltan_dias === 0) {
                    echo '<p class="evento-tiempo">' . esc_html__('Hoy', 'cpt-eventos') . '</p>';
                } elseif ($faltan_dias < 0 && $fin_timestamp && $fin_timestamp > $hoy) {
                    echo '<p class="evento-tiempo">' . esc_html__('En curso', 'cpt-eventos') . '</p>';
                } else {
                    echo '<p class="evento-tiempo">' . esc_html__('Finalizado', 'cpt-eventos') . '</p>';
                }
            }

            echo '</div>';
            echo '<div class="evento-acciones">';
            echo '<a href="' . esc_url(get_edit_post_link($evento->ID)) . '">' . esc_html__('Editar evento', 'cpt-eventos') . '</a> · ';
            echo '<a href="' . esc_url(get_permalink($post_asociado)) . '" target="_blank" rel="noopener">' . esc_html__('Ver post asociado', 'cpt-eventos') . '</a>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div></div>';
    }

    private function render_visualizer_prioritized(array $eventos): void {
        $hoy = (int) current_time('timestamp');
        $proximos = [];
        $historicos = [];

        foreach ($eventos as $evento) {
            if (!($evento instanceof WP_Post)) {
                continue;
            }

            $item = $this->build_visualizer_item($evento, $hoy);
            if (!$item) {
                continue;
            }

            if ('historico' === $item['grupo']) {
                $historicos[] = $item;
            } else {
                $proximos[] = $item;
            }
        }

        usort($proximos, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return ((int) $a['priority'] < (int) $b['priority']) ? -1 : 1;
            }

            $a_start = is_int($a['inicio_timestamp']) ? (int) $a['inicio_timestamp'] : PHP_INT_MAX;
            $b_start = is_int($b['inicio_timestamp']) ? (int) $b['inicio_timestamp'] : PHP_INT_MAX;
            if ($a_start === $b_start) {
                return 0;
            }
            return ($a_start < $b_start) ? -1 : 1;
        });

        usort($historicos, static function (array $a, array $b): int {
            $a_start = is_int($a['inicio_timestamp']) ? (int) $a['inicio_timestamp'] : PHP_INT_MIN;
            $b_start = is_int($b['inicio_timestamp']) ? (int) $b['inicio_timestamp'] : PHP_INT_MIN;
            if ($a_start === $b_start) {
                return 0;
            }
            return ($a_start > $b_start) ? -1 : 1;
        });

        $count_en_curso = 0;
        $count_7_dias = 0;
        $count_30_dias = 0;

        foreach ($proximos as $item) {
            if ('en_curso' === $item['status_key']) {
                $count_en_curso++;
                continue;
            }

            if (!is_int($item['inicio_timestamp']) || $item['inicio_timestamp'] < $hoy) {
                continue;
            }

            $diff = (int) $item['inicio_timestamp'] - $hoy;
            if ($diff <= 7 * DAY_IN_SECONDS) {
                $count_7_dias++;
            }
            if ($diff <= 30 * DAY_IN_SECONDS) {
                $count_30_dias++;
            }
        }

        $proximos_destacados = [];
        $proximos_mas_adelante = [];
        foreach ($proximos as $item) {
            if ('en_curso' === $item['status_key']) {
                $proximos_destacados[] = $item;
                continue;
            }

            if (
                is_int($item['inicio_timestamp'])
                && (int) $item['inicio_timestamp'] >= $hoy
                && ((int) $item['inicio_timestamp'] - $hoy) <= 30 * DAY_IN_SECONDS
            ) {
                $proximos_destacados[] = $item;
                continue;
            }

            $proximos_mas_adelante[] = $item;
        }

        ?>
        <style>
            .evento-visualizer-v2 .evento-toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 14px 0 6px;
            }
            .evento-visualizer-v2 .evento-chip {
                background: #f0f6fc;
                border: 1px solid #c5d9ed;
                border-radius: 999px;
                color: #174a7c;
                font-size: 12px;
                font-weight: 600;
                padding: 6px 10px;
            }
            .evento-visualizer-v2 .evento-chip.is-primary {
                background: #174a7c;
                border-color: #174a7c;
                color: #fff;
            }
            .evento-visualizer-v2 .evento-section {
                margin-top: 16px;
            }
            .evento-visualizer-v2 .evento-section-title {
                margin: 0 0 10px;
                font-size: 18px;
                font-weight: 700;
            }
            .evento-visualizer-v2 .evento-empty {
                margin: 0;
                color: #555;
            }
            .evento-visualizer-v2 .grid-eventos {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .evento-visualizer-v2 .evento-card {
                background: #fff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 6px rgba(0,0,0,0.08);
                border: 1px solid #ddd;
                display: flex;
                flex-direction: column;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .evento-visualizer-v2 .evento-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .evento-visualizer-v2 .evento-card.is-urgente {
                border-color: #dba617;
                box-shadow: 0 3px 10px rgba(219, 166, 23, 0.25);
            }
            .evento-visualizer-v2 .evento-card.status-en_curso {
                border-color: #2f7d32;
                box-shadow: 0 3px 10px rgba(47, 125, 50, 0.2);
            }
            .evento-visualizer-v2 .evento-card.status-finalizado,
            .evento-visualizer-v2 .evento-card.status-sin_fecha {
                opacity: 0.92;
            }
            .evento-visualizer-v2 .evento-img img {
                width: 100%;
                height: 160px;
                object-fit: cover;
            }
            .evento-visualizer-v2 .evento-content {
                padding: 12px 15px;
                flex: 1;
            }
            .evento-visualizer-v2 .evento-content h2 {
                margin: 0 0 8px;
                font-size: 16px;
            }
            .evento-visualizer-v2 .evento-content p {
                margin: 4px 0;
                color: #444;
                font-size: 13px;
            }
            .evento-visualizer-v2 .evento-meta {
                font-size: 12px;
                color: #777;
            }
            .evento-visualizer-v2 .evento-tiempo {
                margin-top: 10px;
            }
            .evento-visualizer-v2 .evento-tiempo-badge {
                display: inline-block;
                font-size: 12px;
                font-weight: 700;
                border-radius: 999px;
                background: #edf5ff;
                color: #1f4f82;
                border: 1px solid #c8ddf4;
                padding: 4px 10px;
            }
            .evento-visualizer-v2 .evento-card.status-finalizado .evento-tiempo-badge,
            .evento-visualizer-v2 .evento-card.status-sin_fecha .evento-tiempo-badge {
                background: #f4f5f6;
                border-color: #d8dde3;
                color: #4f5b66;
            }
            .evento-visualizer-v2 .evento-card.status-en_curso .evento-tiempo-badge {
                background: #edf8ee;
                border-color: #cae8cc;
                color: #1f5c24;
            }
            .evento-visualizer-v2 .evento-acciones {
                padding: 10px 15px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
                text-align: right;
            }
            .evento-visualizer-v2 .evento-acciones a {
                text-decoration: none;
                color: #0073aa;
                font-weight: 500;
            }
            .evento-visualizer-v2 .evento-acciones a:hover {
                color: #00a0d2;
            }
            .evento-visualizer-v2 .evento-historico {
                margin-top: 24px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                padding: 8px 12px 14px;
            }
            .evento-visualizer-v2 .evento-historico > summary {
                cursor: pointer;
                font-weight: 600;
                padding: 4px 2px;
                user-select: none;
            }
            .evento-visualizer-v2 .evento-historico[open] > summary {
                margin-bottom: 8px;
            }
        </style>
        <?php

        echo '<div class="evento-visualizer-v2">';
        echo '<div class="evento-toolbar">';
        echo '<span class="evento-chip is-primary">' . sprintf(esc_html__('En foco (en curso + 30 dias): %s', 'cpt-eventos'), number_format_i18n(count($proximos_destacados))) . '</span>';
        echo '<span class="evento-chip">' . sprintf(esc_html__('En curso: %s', 'cpt-eventos'), number_format_i18n($count_en_curso)) . '</span>';
        echo '<span class="evento-chip">' . sprintf(esc_html__('En 7 dias: %s', 'cpt-eventos'), number_format_i18n($count_7_dias)) . '</span>';
        echo '<span class="evento-chip">' . sprintf(esc_html__('En 30 dias: %s', 'cpt-eventos'), number_format_i18n($count_30_dias)) . '</span>';
        echo '<span class="evento-chip">' . sprintf(esc_html__('Mas adelante: %s', 'cpt-eventos'), number_format_i18n(count($proximos_mas_adelante))) . '</span>';
        echo '<span class="evento-chip">' . sprintf(esc_html__('Historico: %s', 'cpt-eventos'), number_format_i18n(count($historicos))) . '</span>';
        echo '</div>';

        echo '<section class="evento-section">';
        echo '<h2 class="evento-section-title">' . esc_html__('Lo que se viene (en curso + 30 dias)', 'cpt-eventos') . '</h2>';
        if (empty($proximos_destacados)) {
            echo '<p class="evento-empty">' . esc_html__('No hay eventos cercanos en este momento.', 'cpt-eventos') . '</p>';
        } else {
            echo '<div class="grid-eventos">';
            foreach ($proximos_destacados as $item) {
                $this->render_visualizer_prioritized_card($item);
            }
            echo '</div>';
        }
        echo '</section>';

        echo '<details class="evento-historico">';
        echo '<summary>' . sprintf(esc_html__('Ver mas adelante (%s)', 'cpt-eventos'), number_format_i18n(count($proximos_mas_adelante))) . '</summary>';
        if (empty($proximos_mas_adelante)) {
            echo '<p class="evento-empty">' . esc_html__('No hay eventos fuera de la ventana de 30 dias.', 'cpt-eventos') . '</p>';
        } else {
            echo '<div class="grid-eventos">';
            foreach ($proximos_mas_adelante as $item) {
                $this->render_visualizer_prioritized_card($item);
            }
            echo '</div>';
        }
        echo '</details>';

        echo '<details class="evento-historico">';
        echo '<summary>' . sprintf(esc_html__('Ver historico (%s)', 'cpt-eventos'), number_format_i18n(count($historicos))) . '</summary>';
        if (empty($historicos)) {
            echo '<p class="evento-empty">' . esc_html__('No hay eventos historicos para mostrar.', 'cpt-eventos') . '</p>';
        } else {
            echo '<div class="grid-eventos">';
            foreach ($historicos as $item) {
                $this->render_visualizer_prioritized_card($item);
            }
            echo '</div>';
        }
        echo '</details>';
        echo '</div>';
        echo '</div>';
    }

    private function build_visualizer_item(WP_Post $evento, int $hoy): ?array {
        $inicio_fecha = (string) get_post_meta($evento->ID, 'evento_inicio_fecha', true);
        $inicio_hora = (string) get_post_meta($evento->ID, 'evento_inicio_hora', true);
        $fin_fecha = (string) get_post_meta($evento->ID, 'evento_fin_fecha', true);
        $fin_hora = (string) get_post_meta($evento->ID, 'evento_fin_hora', true);
        $display_title = trim((string) get_post_meta($evento->ID, 'evento_display_title', true));
        $post_asociado = (int) get_post_meta($evento->ID, 'evento_post_asociado', true);

        if (!$post_asociado || get_post_status($post_asociado) !== 'publish') {
            return null;
        }

        $inicio_timestamp = $this->parse_evento_timestamp($inicio_fecha, $inicio_hora);
        $fin_timestamp = $this->parse_evento_timestamp($fin_fecha, $fin_hora);
        $duracion_dias = ($inicio_timestamp && $fin_timestamp)
            ? (int) floor(($fin_timestamp - $inicio_timestamp) / DAY_IN_SECONDS)
            : 0;
        $faltan_dias = $inicio_timestamp
            ? (int) floor(($inicio_timestamp - $hoy) / DAY_IN_SECONDS)
            : null;

        $status_key = 'sin_fecha';
        $status_label = esc_html__('Sin fecha', 'cpt-eventos');
        $priority = 99;
        $grupo = 'historico';

        if ($inicio_timestamp) {
            if ($inicio_timestamp > $hoy) {
                $grupo = 'proximos';
                if ($faltan_dias > 1) {
                    $status_key = 'proximo';
                    $status_label = sprintf(
                        esc_html__('Faltan %s dias', 'cpt-eventos'),
                        number_format_i18n($faltan_dias)
                    );
                    $priority = 3;
                } elseif (1 === $faltan_dias) {
                    $status_key = 'manana';
                    $status_label = esc_html__('Manana', 'cpt-eventos');
                    $priority = 2;
                } else {
                    $status_key = 'hoy';
                    $status_label = esc_html__('Hoy', 'cpt-eventos');
                    $priority = 1;
                }
            } elseif (
                !$fin_timestamp
                && wp_date('Y-m-d', $inicio_timestamp) === wp_date('Y-m-d', $hoy)
            ) {
                $grupo = 'proximos';
                $status_key = 'hoy';
                $status_label = esc_html__('Hoy', 'cpt-eventos');
                $priority = 1;
            } elseif ($fin_timestamp && $fin_timestamp > $hoy) {
                $grupo = 'proximos';
                $status_key = 'en_curso';
                $status_label = esc_html__('En curso', 'cpt-eventos');
                $priority = 0;
            } else {
                $status_key = 'finalizado';
                $status_label = esc_html__('Finalizado', 'cpt-eventos');
            }
        }

        $dias_para_inicio = $inicio_timestamp
            ? (int) floor(($inicio_timestamp - $hoy) / DAY_IN_SECONDS)
            : null;
        $is_urgente = in_array($status_key, ['en_curso', 'hoy', 'manana'], true)
            || ('proximo' === $status_key && null !== $dias_para_inicio && $dias_para_inicio <= 7);

        return [
            'evento_id' => (int) $evento->ID,
            'post_asociado' => $post_asociado,
            'titulo' => '' !== $display_title ? $display_title : get_the_title($post_asociado),
            'img' => get_the_post_thumbnail_url($post_asociado, 'medium') ?: 'https://via.placeholder.com/300x160?text=Evento',
            'inicio_hora' => $inicio_hora,
            'fin_fecha' => $fin_fecha,
            'inicio_timestamp' => $inicio_timestamp,
            'fin_timestamp' => $fin_timestamp,
            'duracion_dias' => $duracion_dias,
            'status_key' => $status_key,
            'status_label' => $status_label,
            'priority' => $priority,
            'is_urgente' => $is_urgente,
            'grupo' => $grupo,
        ];
    }

    private function parse_evento_timestamp(string $fecha, string $hora = ''): ?int {
        $fecha = trim($fecha);
        if ('' === $fecha) {
            return null;
        }

        $hora = trim($hora);
        $hora = '' !== $hora ? substr($hora, 0, 5) : '00:00';
        $tz = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->getTimestamp();
        }

        $fallback = strtotime($fecha . ' ' . $hora);
        return false === $fallback ? null : (int) $fallback;
    }

    private function render_visualizer_prioritized_card(array $item): void {
        $card_classes = ['evento-card', 'status-' . sanitize_html_class((string) $item['status_key'])];
        if (!empty($item['is_urgente'])) {
            $card_classes[] = 'is-urgente';
        }

        $datetime_label = '';
        if (is_int($item['inicio_timestamp'])) {
            $datetime_label = date_i18n('j F Y', (int) $item['inicio_timestamp']);
            if (!empty($item['inicio_hora'])) {
                $datetime_label .= ' - ' . (string) $item['inicio_hora'];
            }
        }

        echo '<article class="' . esc_attr(implode(' ', $card_classes)) . '">';
        echo '<div class="evento-img"><img src="' . esc_url((string) $item['img']) . '" alt=""></div>';
        echo '<div class="evento-content">';
        echo '<h2>' . esc_html((string) $item['titulo']) . '</h2>';

        if ('' !== $datetime_label) {
            echo '<p class="evento-meta">' . esc_html($datetime_label) . '</p>';
        }

        if ((int) $item['duracion_dias'] >= 1 && !empty($item['fin_fecha'])) {
            $fin_timestamp = is_int($item['fin_timestamp']) ? (int) $item['fin_timestamp'] : null;
            if ($fin_timestamp) {
                echo '<p class="evento-meta">' . sprintf(
                    esc_html__('Hasta el %s', 'cpt-eventos'),
                    esc_html(date_i18n('j F Y', $fin_timestamp))
                ) . '</p>';
            }
        }

        echo '<p class="evento-tiempo"><span class="evento-tiempo-badge">' . esc_html((string) $item['status_label']) . '</span></p>';
        echo '</div>';
        echo '<div class="evento-acciones">';
        echo '<a href="' . esc_url(get_edit_post_link((int) $item['evento_id'])) . '">' . esc_html__('Editar evento', 'cpt-eventos') . '</a> · ';
        echo '<a href="' . esc_url(get_permalink((int) $item['post_asociado'])) . '" target="_blank" rel="noopener">' . esc_html__('Ver post asociado', 'cpt-eventos') . '</a>';
        echo '</div>';
        echo '</article>';
    }
}
