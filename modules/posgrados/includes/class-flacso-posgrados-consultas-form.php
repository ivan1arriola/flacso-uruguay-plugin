<?php

if (!class_exists('FLACSO_Posgrados_Consultas_Form')) {
    class FLACSO_Posgrados_Consultas_Form {
        private static $localized_frontend = false;

        public static function init(): void {
            self::define_constants();
            add_action('init', [__CLASS__, 'register_block']);
            add_action('wp_ajax_flacso_enviar_consulta', [__CLASS__, 'handle_ajax']);
            add_action('wp_ajax_nopriv_flacso_enviar_consulta', [__CLASS__, 'handle_ajax']);
            add_action('template_redirect', [__CLASS__, 'maybe_render_thankyou']);
        }

        private static function define_constants(): void {
            if (!defined('FLACSO_CONSULTAS_HABILITADO')) {
                define('FLACSO_CONSULTAS_HABILITADO', true);
            }
            if (!defined('FLACSO_WEBHOOK_URL')) {
                define('FLACSO_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbx7Vyd3cOX0_kyY78dASZKsULA6bH_F4r08vjoFBPwtP-b_19JZV5T0mQS-QXSuuamt/exec');
            }
            if (!defined('FLACSO_EMAIL_CONTACTO')) {
                define('FLACSO_EMAIL_CONTACTO', 'inscripciones@flacso.edu.uy');
            }
            if (!defined('FLACSO_USE_NONCE')) {
                define('FLACSO_USE_NONCE', false);
            }
            if (!defined('FLACSO_RELAXED_MODE')) {
                define('FLACSO_RELAXED_MODE', true);
            }
            if (!defined('FLACSO_WEBHOOK_TIMEOUT')) {
                define('FLACSO_WEBHOOK_TIMEOUT', 25);
            }
        }

        public static function register_block(): void {

            $block_js     = 'blocks/consultas-form/index.js';
            $block_js_url = FLACSO_POSGRADOS_PLUGIN_URL . $block_js;
            $block_js_path = FLACSO_POSGRADOS_PLUGIN_PATH . $block_js;
            $block_js_ver = file_exists($block_js_path) ? filemtime($block_js_path) : time();

            wp_register_script(
                'flacso-consultas-form-block',
                $block_js_url,
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-server-side-render'],
                $block_js_ver,
                true
            );

            $style_path = FLACSO_POSGRADOS_PLUGIN_PATH . 'assets/css/block-consultas-form.css';
            $style_url  = FLACSO_POSGRADOS_PLUGIN_URL . 'assets/css/block-consultas-form.css';
            $style_ver  = file_exists($style_path) ? filemtime($style_path) : $block_js_ver;

            wp_register_style(
                'flacso-consultas-form-style',
                $style_url,
                [],
                $style_ver
            );

            $frontend_js_path = FLACSO_POSGRADOS_PLUGIN_PATH . 'assets/js/form-consultas-frontend.js';
            $frontend_js_url  = FLACSO_POSGRADOS_PLUGIN_URL . 'assets/js/form-consultas-frontend.js';
            $frontend_ver     = file_exists($frontend_js_path) ? filemtime($frontend_js_path) : $block_js_ver;

            wp_register_script(
                'flacso-consultas-form-frontend',
                $frontend_js_url,
                ['jquery'],
                $frontend_ver,
                true
            );

            register_block_type_from_metadata(
                FLACSO_POSGRADOS_PLUGIN_PATH . 'blocks/consultas-form',
                [
                    'render_callback' => [__CLASS__, 'render_block'],
                ]
            );
        }

        public static function render_block(array $attributes, string $content, $block): string {
            wp_enqueue_style('flacso-consultas-form-style');
            wp_enqueue_script('flacso-consultas-form-frontend');

            if (!self::$localized_frontend) {
                wp_localize_script('flacso-consultas-form-frontend', 'FLACSO_CONSULTAS_FORM', [
                    'ajaxUrl'  => admin_url('admin-ajax.php'),
                    'timeout'  => FLACSO_WEBHOOK_TIMEOUT,
                    'useNonce' => FLACSO_USE_NONCE,
                    'nonce'    => FLACSO_USE_NONCE ? wp_create_nonce('flacso_consultas_form') : '',
                    'messages' => [
                        'invalid' => __('Revisá los campos marcados en rojo.', 'flacso-posgrados-docentes'),
                    ],
                ]);
                self::$localized_frontend = true;
            }

            if (!wp_style_is('bootstrap-icons', 'enqueued')) {
                wp_enqueue_style(
                    'bootstrap-icons',
                    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
                    [],
                    '1.11.3'
                );
            }

            $show_pre = !isset($attributes['showPreinscription']) || (bool) $attributes['showPreinscription'];
            $page_id  = get_the_ID() ?: 0;
            $title    = get_the_title($page_id);
            $permalink = get_permalink($page_id);
            $current_url = $permalink ?: home_url('/');

            ob_start();
            ?>
            <div class="flacso-pos-consultas-block" data-show-preinsc="<?php echo $show_pre ? '1' : '0'; ?>">
                <h3 class="mb-1"><strong><?php esc_html_e('Solicitá información', 'flacso-posgrados-docentes'); ?></strong></h3>
                <p class="mb-4 text-muted" style="line-height:1.5">
                    <?php esc_html_e('Llená el formulario y recibí toda la información actualizada.', 'flacso-posgrados-docentes'); ?>
                </p>

                <?php if (!FLACSO_CONSULTAS_HABILITADO): ?>
                    <div class="alert alert-warning mb-0" role="alert" aria-live="polite">
                        <p class="mb-2"><strong><?php esc_html_e('El formulario está temporalmente fuera de servicio.', 'flacso-posgrados-docentes'); ?></strong></p>
                        <p class="mb-0"><?php esc_html_e('Podés escribirnos a', 'flacso-posgrados-docentes'); ?>
                            <a href="mailto:<?php echo esc_attr(FLACSO_EMAIL_CONTACTO); ?>" class="alert-link">
                                <strong><?php echo esc_html(FLACSO_EMAIL_CONTACTO); ?></strong>
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <form class="flacso-pos-consultas-form needs-validation" method="post" novalidate>
                        <input type="hidden" name="id_pagina" value="<?php echo esc_attr($page_id); ?>">
                        <input type="hidden" name="titulo_posgrado" value="<?php echo esc_attr($title); ?>">
                        <input type="hidden" name="url_base" value="<?php echo esc_url($current_url); ?>">
                        <input type="hidden" name="url_referer" value="<?php echo esc_url(wp_get_referer() ?: $current_url); ?>">
                        <?php if (function_exists('fc_render_campaign_attribution_hidden_fields')) { fc_render_campaign_attribution_hidden_fields(function_exists('fc_get_current_request_url') ? fc_get_current_request_url() : $current_url, wp_get_referer() ?: ''); } ?>
                        <?php if (FLACSO_USE_NONCE) { wp_nonce_field('flacso_consultas_form', 'flacso_nonce'); } ?>

                        <?php echo self::render_input('nombre', __('Nombre', 'flacso-posgrados-docentes'), 'text', true, [
                            'pattern' => '[A-Za-zÁáÉéÍíÓóÚúÑñ\\s\\-]{2,}',
                            'autocomplete' => 'given-name',
                        ]); ?>

                        <?php echo self::render_input('apellido', __('Apellido', 'flacso-posgrados-docentes'), 'text', true, [
                            'pattern' => '[A-Za-zÁáÉéÍíÓóÚúÑñ\\s\\-]{2,}',
                            'autocomplete' => 'family-name',
                        ]); ?>

                        <?php echo self::render_select('pais', __('País de residencia', 'flacso-posgrados-docentes'), self::get_countries()); ?>
                        <?php echo self::render_select('nivel_academico', __('Nivel académico', 'flacso-posgrados-docentes'), self::get_academic_levels()); ?>
                        <?php echo self::render_input('correo', __('Correo electrónico', 'flacso-posgrados-docentes'), 'email', true, ['autocomplete' => 'email']); ?>
                        <?php echo self::render_input('profesion', __('Profesión', 'flacso-posgrados-docentes'), 'text', true, [
                            'pattern' => '[A-Za-zÁáÉéÍíÓóÚúÑñ0-9\\s\\-\\_\\(\\)\\.]{2,}',
                            'autocomplete' => 'organization-title',
                        ]); ?>

                        <button type="submit" class="btn btn-primary w-100 py-2 mt-2 fw-bold rounded-pill">
                            <span class="btn-text">
                                <i class="bi bi-send-fill me-2" aria-hidden="true"></i>
                                <?php esc_html_e('Enviar consulta', 'flacso-posgrados-docentes'); ?>
                            </span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                <?php esc_html_e('Enviando…', 'flacso-posgrados-docentes'); ?>
                            </span>
                        </button>

                        <div class="flacso-pos-consultas-message alert d-none" role="alert" aria-live="polite"></div>
                    </form>
                <?php endif; ?>

                <?php if ($show_pre): ?>
                    <div class="d-grid gap-2 mt-4">
                        <a class="btn btn-preinsc btn-lg py-3 fw-bold" href="<?php echo esc_url(trailingslashit($current_url) . 'preinscripcion'); ?>" onclick="if(typeof window.flacsoMetaTrack === 'function'){ window.flacsoMetaTrack('InitiateCheckout', { content_name: '<?php echo esc_js($title); ?>', content_category: 'oferta_academica' }); }">
                            <i class="bi bi-stars me-2" aria-hidden="true"></i>
                            <?php esc_html_e('Preinscripción', 'flacso-posgrados-docentes'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function render_input(string $name, string $label, string $type, bool $required = true, array $extra = []): string {
            $id = 'flacso-pos-consultas-' . $name;
            $attrs = '';
            foreach ($extra as $attr => $value) {
                $attrs .= sprintf(' %s="%s"', esc_attr($attr), esc_attr($value));
            }
            if ($required) {
                $attrs .= ' required';
            }

            ob_start();
            ?>
            <div class="form-floating mb-3">
                <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="form-control"
                    placeholder="<?php echo esc_attr($label); ?>"<?php echo $attrs; ?> />
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php echo $required ? ' *' : ''; ?></label>
                <div class="invalid-feedback"><?php esc_html_e('Revisá este campo.', 'flacso-posgrados-docentes'); ?></div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function render_select(string $name, string $label, array $options): string {
            $id = 'flacso-pos-consultas-' . $name;
            ob_start();
            ?>
            <div class="form-floating mb-3">
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="form-select" required>
                    <option value="" selected disabled><?php esc_html_e('Seleccioná…', 'flacso-posgrados-docentes'); ?></option>
                    <?php foreach ($options as $value => $text): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($text); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?> *</label>
                <div class="invalid-feedback"><?php esc_html_e('Seleccioná una opción válida.', 'flacso-posgrados-docentes'); ?></div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function get_countries(): array {
            return [
                'Uruguay' => 'Uruguay',
                'Argentina' => 'Argentina',
                'Bolivia' => 'Bolivia',
                'Brasil' => 'Brasil',
                'Chile' => 'Chile',
                'Colombia' => 'Colombia',
                'Costa Rica' => 'Costa Rica',
                'Cuba' => 'Cuba',
                'Ecuador' => 'Ecuador',
                'El Salvador' => 'El Salvador',
                'Guatemala' => 'Guatemala',
                'Haití' => 'Haití',
                'Honduras' => 'Honduras',
                'México' => 'México',
                'Nicaragua' => 'Nicaragua',
                'Panamá' => 'Panamá',
                'Paraguay' => 'Paraguay',
                'Perú' => 'Perú',
                'República Dominicana' => 'República Dominicana',
                'Venezuela' => 'Venezuela',
                'Otro' => __('Otro', 'flacso-posgrados-docentes'),
            ];
        }

        private static function get_academic_levels(): array {
            return [
                __('Título universitario', 'flacso-posgrados-docentes') => __('Título universitario', 'flacso-posgrados-docentes'),
                __('Título terciario no universitario', 'flacso-posgrados-docentes') => __('Título terciario no universitario', 'flacso-posgrados-docentes'),
                __('Estudiante en curso (aún no egresado/a)', 'flacso-posgrados-docentes') => __('Estudiante en curso (aún no egresado/a)', 'flacso-posgrados-docentes'),
                __('Sin formación terciaria', 'flacso-posgrados-docentes') => __('Sin formación terciaria', 'flacso-posgrados-docentes'),
            ];
        }

        public static function handle_ajax(): void {
            if (
                function_exists('flacso_enviar_consulta_func')
                && (
                    !empty($_POST['dynamic_info_form_id'])
                    || !empty($_POST['selected_offer_ids'])
                    || isset($_POST['selected_offers'])
                )
            ) {
                flacso_enviar_consulta_func();
                return;
            }

            if (FLACSO_USE_NONCE) {
                $nonce = $_POST['flacso_nonce'] ?? '';
                if (empty($nonce) || !wp_verify_nonce($nonce, 'flacso_consultas_form')) {
                    if (!FLACSO_RELAXED_MODE) {
                        wp_send_json_error(__('Error de seguridad. Recargá la página e intentá nuevamente.', 'flacso-posgrados-docentes'));
                    }
                }
            }

            if (!FLACSO_CONSULTAS_HABILITADO) {
                wp_send_json_error(__('El formulario está temporalmente fuera de servicio.', 'flacso-posgrados-docentes'));
            }

            $fields = [
                'nombre','apellido','pais','nivel_academico','correo','profesion',
                'id_pagina','titulo_posgrado','url_base','url_referer',
                'campaign_provider','campaign_source','campaign_medium','campaign_name',
                'campaign_external_id','campaign_content','campaign_term',
                'utm_source','utm_medium','utm_campaign','utm_id','utm_content','utm_term',
                'gclid','gbraid','wbraid','fbclid',
                'landing_url','referrer_url',
            ];

            $data = [];
            foreach ($fields as $field) {
                $value = $_POST[$field] ?? '';
                if ($field === 'id_pagina') {
                    $data[$field] = absint($value);
                } elseif (in_array($field, ['url_base', 'url_referer', 'landing_url', 'referrer_url'], true)) {
                    $data[$field] = esc_url_raw($value);
                } elseif ($field === 'correo') {
                    $data[$field] = sanitize_email($value);
                } else {
                    $data[$field] = sanitize_text_field($value);
                }
            }

            $data['fecha_envio'] = current_time('c');
            $data['ip_usuario']  = self::get_user_ip();
            $data['user_agent']  = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
            if (function_exists('fc_enrich_info_request_program_context')) {
                $data = fc_enrich_info_request_program_context($data);
            }

            $required = ['nombre','apellido','pais','nivel_academico','correo','profesion'];
            $missing  = [ ];
            foreach ($required as $field) {
                if (empty(trim($data[$field] ?? ''))) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                if (!FLACSO_RELAXED_MODE) {
                    wp_send_json_error(__('Completá todos los campos obligatorios.', 'flacso-posgrados-docentes'));
                }
            }

            if (!empty($data['correo']) && !is_email($data['correo'])) {
                if (!FLACSO_RELAXED_MODE) {
                    wp_send_json_error(__('Correo inválido.', 'flacso-posgrados-docentes'));
                }
            }
            if (empty($data['id_pagina'])) {
                wp_send_json_error(__('No se pudo identificar la oferta académica.', 'flacso-posgrados-docentes'));
            }

            if (function_exists('fc_record_info_request_entry')) {
                $stored_entry = fc_record_info_request_entry([
                    'nombre'          => $data['nombre'],
                    'apellido'        => $data['apellido'],
                    'correo'          => $data['correo'],
                    'pais'            => $data['pais'],
                    'nivel_academico' => $data['nivel_academico'],
                    'profesion'       => $data['profesion'],
                    'programa_id'     => $data['id_pagina'],
                    'programa_titulo' => $data['titulo_posgrado'],
                    'url_base'        => $data['url_base'],
                    'url_referer'     => $data['url_referer'],
                    'ip'              => $data['ip_usuario'],
                    'user_agent'      => $data['user_agent'],
                    'fecha_envio'     => $data['fecha_envio'],
                ]);
                if (!empty($stored_entry['error'])) {
                    error_log('[FLACSO] Error al guardar solicitud de información: ' . $stored_entry['error']);
                }
            }

            $delivery = function_exists('fc_send_info_request_webhook')
                ? fc_send_info_request_webhook($data)
                : ['ok' => false, 'error' => 'fc_send_info_request_webhook no disponible', 'code' => 0, 'body' => ''];

            if (empty($delivery['ok'])) {
                error_log(
                    '[FLACSO] Error webhook solicitud de informacion: '
                    . ($delivery['error'] ?? 'desconocido')
                    . ' code=' . (int) ($delivery['code'] ?? 0)
                    . ' body=' . substr((string) ($delivery['body'] ?? ''), 0, 500)
                );
                if (FLACSO_RELAXED_MODE) {
                    wp_send_json_success([
                        'note' => ((int) ($delivery['code'] ?? 0) > 0) ? 'http_code_relajado' : 'webhook_error_relajado',
                        'code' => (int) ($delivery['code'] ?? 0),
                    ]);
                }
                wp_send_json_error(__('No se pudo procesar la consulta. Intentá más tarde.', 'flacso-posgrados-docentes'));
            }

            wp_send_json_success(['note' => 'ok']);
        }

        private static function get_user_ip(): string {
            $keys = [
                'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR',
            ];

            foreach ($keys as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(explode(',', $_SERVER[$key])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return sanitize_text_field($ip);
                    }
                }
            }

            return 'unknown';
        }

        private static function apply_virtual_page_title(string $base_title): void {
            $base_title = trim($base_title);
            $site_name  = trim((string) get_bloginfo('name'));
            $full_title = $site_name ? $base_title . ' - ' . $site_name : $base_title;

            add_filter(
                'pre_get_document_title',
                static function () use ($full_title) {
                    return $full_title;
                },
                999
            );

            add_filter(
                'document_title_parts',
                static function ($parts) use ($base_title) {
                    $parts['title'] = $base_title;
                    unset($parts['tagline']);
                    return $parts;
                },
                999
            );

            add_filter(
                'wp_title',
                static function () use ($full_title) {
                    return $full_title;
                },
                999
            );

            add_filter(
                'rank_math/frontend/title',
                static function () use ($full_title) {
                    return $full_title;
                },
                999
            );

            add_filter(
                'wpseo_title',
                static function () use ($full_title) {
                    return $full_title;
                },
                999
            );
        }

        public static function maybe_render_thankyou(): void {
            $path = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (!preg_match('#/gracias/?$#', $path ?? '')) {
                return;
            }

            $pid = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
            $title = $pid ? get_the_title($pid) : '';
            $thumb = ($pid && has_post_thumbnail($pid))
                ? wp_get_attachment_image_url(get_post_thumbnail_id($pid), 'full')
                : '';
            $back = $pid ? get_permalink($pid) : home_url('/');
            $page_title = $title
                ? sprintf('Consulta enviada - %s', $title)
                : 'Consulta enviada';

            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = true;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                $wp_query->is_posts_page = false;
                $wp_query->set_404(false);
            }

            status_header(200);
            nocache_headers();
            self::apply_virtual_page_title($page_title);

            $thankyou_style_path = dirname(__DIR__) . '/assets/css/consultas-thankyou.css';
            wp_enqueue_style(
                'flacso-posgrados-consultas-thankyou',
                FLACSO_POSGRADOS_PLUGIN_URL . 'assets/css/consultas-thankyou.css',
                [],
                file_exists($thankyou_style_path) ? (string) filemtime($thankyou_style_path) : null
            );

            get_header();
            ?>
            <main class="flacso-pos-consultas-thanks">
                <div class="flacso-pos-consultas-thanks__container">
                    <div class="flacso-pos-consultas-thanks__inner">
                        <div class="flacso-pos-consultas-thanks__message">
                            <div class="flacso-pos-consultas-thanks__check" aria-hidden="true">✓</div>
                            <h1><?php esc_html_e('¡Gracias por tu consulta!', 'flacso-posgrados-docentes'); ?></h1>

                            <?php if ($title): ?>
                                <p class="flacso-pos-consultas-thanks__lead"><?php printf(esc_html__('Gracias por tu interés en %s de FLACSO Uruguay.', 'flacso-posgrados-docentes'), esc_html($title)); ?></p>
                            <?php else: ?>
                                <p class="flacso-pos-consultas-thanks__lead"><?php esc_html_e('Hemos recibido tu consulta correctamente.', 'flacso-posgrados-docentes'); ?></p>
                            <?php endif; ?>
                            <p><?php esc_html_e('En breve recibirás un correo con toda la información detallada.', 'flacso-posgrados-docentes'); ?></p>
                        </div>

                        <div class="flacso-pos-consultas-thanks__actions">
                            <a class="flacso-pos-consultas-thanks__button flacso-pos-consultas-thanks__button--primary" href="<?php echo esc_url($back); ?>">
                                <span aria-hidden="true">←</span>
                                <?php esc_html_e('Volver al programa', 'flacso-posgrados-docentes'); ?>
                            </a>
                            <a class="flacso-pos-consultas-thanks__button flacso-pos-consultas-thanks__button--secondary" href="<?php echo esc_url(home_url('/formacion/')); ?>">
                                <span aria-hidden="true">◇</span>
                                <?php esc_html_e('Ver todos los posgrados', 'flacso-posgrados-docentes'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
            <script>
            (function() {
                if (typeof window.flacsoMetaTrack !== 'function') {
                    return;
                }
                try {
                    // Meta Lead: consulta WordPress enviada correctamente.
                    // Debe ejecutarse solo después de confirmación AJAX exitosa y redirección a /gracias.
                    window.flacsoMetaTrack('Lead', {
                        lead_type: 'consulta_wordpress_oferta',
                        form_type: 'consulta_oferta_academica',
                        lead_source: 'wordpress_form',
                        lead_context: 'oferta_academica',
                        content_name: <?php echo wp_json_encode((string) $title); ?>,
                        content_category: 'oferta_academica',
                        content_type: 'oferta_academica',
                        status: 'submitted'
                    });
                } catch (e) {
                    if (window.console && typeof window.console.warn === 'function') {
                        console.warn('[Posgrados Consultas] Error enviando Lead:', e);
                    }
                }
            })();
            </script>
            <?php
            get_footer();
            exit;
        }
    }

    FLACSO_Posgrados_Consultas_Form::init();
}
