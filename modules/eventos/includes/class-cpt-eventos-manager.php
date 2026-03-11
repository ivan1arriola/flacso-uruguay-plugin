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

        echo '<div class="wrap"><h1>📅 ' . esc_html__('Visualizador de Eventos', 'cpt-eventos') . '</h1>';

        if (empty($eventos)) {
            echo '<p>' . esc_html__('No hay eventos registrados.', 'cpt-eventos') . '</p></div>';
            return;
        }

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
}
