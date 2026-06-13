<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Motor de render para la oferta académica
 * Renderiza programas por taxonomía
 */
class Oferta_Renderer {
    private static $styles_enqueued = false;

    public static function init(): void {
        // Los estilos se cargan de forma perezosa desde los métodos de render.
    }

    public static function enqueue_styles(): void {
        if (self::$styles_enqueued) {
            return;
        }

        wp_enqueue_style('dashicons');
        if (!wp_style_is('bootstrap-css', 'enqueued')) {
            wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', [], '5.3.0');
        }
        if (!wp_style_is('bootstrap-icons', 'enqueued')) {
            wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', [], '1.11.3');
        }
        $css_relative = 'modules/oferta-academica/assets/css/oferta-academica.css';
        $css_path = FLACSO_URUGUAY_PATH . $css_relative;
        $css_version = file_exists($css_path) ? filemtime($css_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_enqueue_style(
            'flacso-oferta-academica',
            plugins_url($css_relative, FLACSO_URUGUAY_FILE),
            [],
            $css_version
        );

        $js_relative = 'modules/oferta-academica/assets/js/oferta-consulta-flotante.js';
        $js_path = FLACSO_URUGUAY_PATH . $js_relative;
        $js_version = file_exists($js_path) ? filemtime($js_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_enqueue_script(
            'flacso-oferta-consulta-flotante',
            plugins_url($js_relative, FLACSO_URUGUAY_FILE),
            [],
            $js_version,
            true
        );

        self::$styles_enqueued = true;
    }

    private static function should_include_private_programs(): bool {
        return current_user_can('manage_options');
    }

    private static function oferta_post_statuses(): array {
        return self::should_include_private_programs() ? ['publish', 'private'] : ['publish'];
    }

    private static function is_private_program(int $post_id, int $page_id = 0): bool {
        if ('private' === get_post_status($post_id)) {
            return true;
        }

        return false;
    }

    private static function is_password_protected_program(int $post_id): bool {
        return trim((string) get_post_field('post_password', $post_id)) !== '';
    }

    private static function exclude_password_protected_from_query_args(array $query_args): array {
        $query_args['has_password'] = false;
        return $query_args;
    }

    private static function has_visible_html_content(string $html): bool {
        $html = trim($html);
        if ($html === '') {
            return false;
        }

        if (preg_match('/<(img|iframe|video|audio|object|embed|svg|canvas|table)\b/i', $html)) {
            return true;
        }

        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text) !== '';
    }

    public static function format_duration_months(string $months_str, string $textdomain = 'flacso-oferta-academica'): string {
        $raw_value = trim(str_replace(',', '.', $months_str));
        if ($raw_value === '' || !is_numeric($raw_value)) {
            return '';
        }

        $value = (float) $raw_value;
        $integer = (int) floor($value);
        $fraction = round($value - $integer, 2);

        if (abs($fraction - 0.5) < 0.001) {
            if ($integer > 0) {
                return sprintf(
                    '%1$d %2$s y medio',
                    $integer,
                    1 === $integer ? __('mes', $textdomain) : __('meses', $textdomain)
                );
            }

            return __('Medio mes', $textdomain);
        }

        $display_value = abs($value - round($value)) < 0.001
            ? (string) ((int) round($value))
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return sprintf(
            '%1$s %2$s',
            $display_value,
            (abs($value - 1.0) < 0.001) ? __('mes', $textdomain) : __('meses', $textdomain)
        );
    }

    public static function normalize_duration_html(string $html, string $months_str = '', string $textdomain = 'flacso-oferta-academica'): string {
        $html = trim($html);
        $formatted_months = self::format_duration_months($months_str, $textdomain);

        if ($html === '') {
            return $formatted_months !== '' ? '<p>' . esc_html($formatted_months) . '</p>' : '';
        }

        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return $formatted_months !== '' ? '<p>' . esc_html($formatted_months) . '</p>' : '';
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*mes(?:es)?$/iu', $text, $matches) || preg_match('/^(\d+(?:[.,]\d+)?)$/u', $text, $matches)) {
            $formatted_text = self::format_duration_months((string) $matches[1], $textdomain);
            if ($formatted_text !== '') {
                return '<p>' . esc_html($formatted_text) . '</p>';
            }
        }

        return $html;
    }

    private static function create_site_date(string $value, string $precision = 'day'): ?DateTimeImmutable {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();

        if ($precision === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d|', $value, $timezone);
            return $date ?: null;
        }

        if ($precision === 'month' && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('Y-m|', $value, $timezone);
            return $date ?: null;
        }

        return null;
    }

    private static function format_site_date(string $value, string $format, string $precision = 'day'): string {
        $date = self::create_site_date($value, $precision);
        if (!$date) {
            return '';
        }

        return wp_date($format, $date->getTimestamp(), wp_timezone());
    }

    private static function detect_proximo_inicio_precision(string $value, string $stored_precision = ''): string {
        $value = trim($value);
        $stored_precision = strtolower(trim($stored_precision));

        if (in_array($stored_precision, ['day', 'month', 'year'], true)) {
            return $stored_precision;
        }

        if (preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $value) || preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/', $value)) {
            return 'day';
        }

        if (preg_match('/^\d{4}[-\/]\d{1,2}$/', $value) || preg_match('/^\d{1,2}[-\/]\d{4}$/', $value)) {
            return 'month';
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return 'year';
        }

        return 'year';
    }

    private static function mb_ucfirst(string $text): string {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($text);
    }

    public static function format_proximo_inicio_text(string $value, string $stored_precision = ''): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $precision = self::detect_proximo_inicio_precision($value, $stored_precision);

        if ($precision === 'year' && preg_match('/^\d{4}$/', $value)) {
            return 'en ' . $value;
        }

        if ($precision === 'month') {
            if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $value, $matches) || preg_match('/^(\d{1,2})[-\/](\d{4})$/', $value, $matches)) {
                $year = strlen($matches[1]) === 4 ? (int) $matches[1] : (int) $matches[2];
                $month = strlen($matches[1]) === 4 ? (int) $matches[2] : (int) $matches[1];
                $month_name = self::format_site_date(sprintf('%04d-%02d', $year, $month), 'F', 'month');
                if ($month_name !== '') {
                    return self::mb_ucfirst($month_name) . ' del ' . $year;
                }
            }
        }

        if ($precision === 'day') {
            if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $value, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } elseif (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $value, $matches)) {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
            } else {
                $year = $month = $day = 0;
            }

            if ($year > 0 && checkdate($month, $day, $year)) {
                $month_name = self::format_site_date(sprintf('%04d-%02d', $year, $month), 'F', 'month');
                if ($month_name !== '') {
                    return $day . ' ' . self::mb_ucfirst($month_name) . ' del ' . $year;
                }
            }
        }

        return $value;
    }

    /**
     * Render de página completa (hero + categorías + secciones + seminarios)
     */
    public static function render_oferta_pagina(array $attributes = []): string {
        self::enqueue_styles();

        $hero_title_default = __('Oferta Académica', 'flacso-oferta-academica');
        $hero_subtitle_default = __('Explora nuestras Maestrías, Especializaciones, Diplomados, Diplomas y Seminarios', 'flacso-oferta-academica');
        $hero_title = !empty($attributes['heroTitle'])
            ? (string) $attributes['heroTitle']
            : apply_filters('flacso_oferta_academica_hero_title', $hero_title_default);
        $hero_subtitle = !empty($attributes['heroSubtitle'])
            ? (string) $attributes['heroSubtitle']
            : apply_filters('flacso_oferta_academica_hero_subtitle', $hero_subtitle_default);

        $hero_image_id = isset($attributes['heroImageId']) ? (int) $attributes['heroImageId'] : 0;
        $hero_image = $hero_image_id ? wp_get_attachment_image_url($hero_image_id, 'full') : '';
        if (!$hero_image) {
            $page_id = is_singular() ? (int) get_queried_object_id() : 0;
            if ($page_id && has_post_thumbnail($page_id)) {
                $hero_image = get_the_post_thumbnail_url($page_id, 'full');
            }
        }
        if (!$hero_image) {
            $hero_image = apply_filters('flacso_oferta_academica_hero_image', '');
        }

        $terms = get_terms([
            'taxonomy' => 'tipo-oferta-academica',
            'hide_empty' => false,
        ]);

        $order_preferida = ['maestria', 'especializacion', 'diplomado', 'diploma'];
        if (!is_wp_error($terms)) {
            usort($terms, function($a, $b) use ($order_preferida) {
                $ai = array_search($a->slug, $order_preferida, true);
                $bi = array_search($b->slug, $order_preferida, true);
                $ai = ($ai === false) ? 999 : $ai;
                $bi = ($bi === false) ? 999 : $bi;
                if ($ai === $bi) return strcasecmp($a->name, $b->name);
                return $ai <=> $bi;
            });
        } else {
            $terms = [];
        }

        $link_maestria = !is_wp_error(get_term_link('maestria', 'tipo-oferta-academica')) ? get_term_link('maestria', 'tipo-oferta-academica') : home_url('/formacion/maestrias/');
        $link_especializacion = !is_wp_error(get_term_link('especializacion', 'tipo-oferta-academica')) ? get_term_link('especializacion', 'tipo-oferta-academica') : home_url('/formacion/especializaciones/');
        $link_diplomado = !is_wp_error(get_term_link('diplomado', 'tipo-oferta-academica')) ? get_term_link('diplomado', 'tipo-oferta-academica') : home_url('/formacion/diplomados/');
        $link_diploma = !is_wp_error(get_term_link('diploma', 'tipo-oferta-academica')) ? get_term_link('diploma', 'tipo-oferta-academica') : home_url('/formacion/diplomas/');
        $link_seminarios = home_url('/seminarios/');

        ob_start();
        ?>
        <section class="flacso-oferta-hero flacso-oferta-hero--full" style="--flacso-oferta-hero-image: <?php echo $hero_image ? 'url(' . esc_url($hero_image) . ')' : 'none'; ?>;">
            <div class="container">
                <div class="flacso-oferta-hero__content text-center">
                    <h1 class="flacso-oferta-hero__title mb-3"><?php echo esc_html($hero_title); ?></h1>
                    <p class="flacso-oferta-hero__subtitle mb-4"><?php echo esc_html($hero_subtitle); ?></p>
                    <div class="flacso-oferta-hero__actions" role="navigation" aria-label="<?php esc_attr_e('Navegación de la oferta académica', 'flacso-oferta-academica'); ?>">
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--solid" href="<?php echo esc_url($link_maestria); ?>">
                            <?php esc_html_e('Maestrías', 'flacso-oferta-academica'); ?>
                        </a>
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--solid" href="<?php echo esc_url($link_especializacion); ?>">
                            <?php esc_html_e('Especializaciones', 'flacso-oferta-academica'); ?>
                        </a>
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--solid" href="<?php echo esc_url($link_diplomado); ?>">
                            <?php esc_html_e('Diplomados', 'flacso-oferta-academica'); ?>
                        </a>
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--solid" href="<?php echo esc_url($link_diploma); ?>">
                            <?php esc_html_e('Diplomas', 'flacso-oferta-academica'); ?>
                        </a>
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--solid" href="<?php echo esc_url($link_seminarios); ?>">
                            <?php esc_html_e('Seminarios', 'flacso-oferta-academica'); ?>
                        </a>       </a>
                        <a class="flacso-oferta-hero__btn flacso-oferta-hero__btn--convenios" href="https://flacso.edu.uy/convenios/">
                            <?php esc_html_e('Convenios', 'flacso-oferta-academica'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="flacso-oferta-body">
            <div class="container">
                <?php
                foreach ($terms as $term) :
                    $query_args = [
                        'post_type' => 'oferta-academica',
                        'post_status' => self::oferta_post_statuses(),
                        'posts_per_page' => -1,
                        'orderby' => 'menu_order',
                        'order' => 'ASC',
                        'tax_query' => [
                            [
                                'taxonomy' => 'tipo-oferta-academica',
                                'field' => 'term_id',
                                'terms' => $term->term_id,
                            ],
                        ],
                    ];
                    $query = new WP_Query(self::exclude_password_protected_from_query_args($query_args));
                    ?>
                    <div class="flacso-oferta-section" id="<?php echo esc_attr($term->slug); ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                            <div class="text-center w-100">
                                <h2 class="flacso-oferta-section__title mb-0"><?php echo esc_html($term->name); ?></h2>
                            </div>
                        </div>

                        <?php if ($query->have_posts()) : ?>
                            <?php
                            $rendered_cards = 0;
                            ob_start();
                            while ($query->have_posts()) {
                                $query->the_post();
                                if (self::render_program_card(get_the_ID(), $term)) {
                                    $rendered_cards++;
                                }
                            }
                            $cards_markup = ob_get_clean();
                            wp_reset_postdata();
                            ?>
                            <?php if ($rendered_cards > 0) : ?>
                                <div class="row g-4">
                                    <?php echo $cards_markup; ?>
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info mb-4">
                                    <?php esc_html_e('No hay programas disponibles en esta categoría.', 'flacso-oferta-academica'); ?>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="alert alert-info mb-4">
                                <?php esc_html_e('No hay programas disponibles en esta categoría.', 'flacso-oferta-academica'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="flacso-oferta-section" id="seminarios">
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                        <div class="text-center w-100">
                            <h2 class="flacso-oferta-section__title mb-0"><?php esc_html_e('Seminarios', 'flacso-oferta-academica'); ?></h2>
                        </div>
                    </div>
                    <?php echo self::render_seminarios_bootstrap(); ?>
                    <div class="flacso-oferta-section__actions">
                        <a class="flacso-oferta-section__btn flacso-oferta-section__btn--primary" href="<?php echo esc_url(home_url('/seminarios/')); ?>">
                            <?php esc_html_e('Ver todos los seminarios abiertos', 'flacso-oferta-academica'); ?>
                        </a>
                        <a class="flacso-oferta-section__btn flacso-oferta-section__btn--outline" href="https://flacso.edu.uy/preguntas-frecuentes/">
                            <?php esc_html_e('Preguntas frecuentes', 'flacso-oferta-academica'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <?php
        if (class_exists('Oferta_Consulta_Form') && method_exists('Oferta_Consulta_Form', 'render_floating_form')) {
            echo Oferta_Consulta_Form::render_floating_form();
        }
        ?>

        <script>
        (function() {
            const links = document.querySelectorAll('.flacso-oferta-hero__actions a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    const href = link.getAttribute('href');
                    if (!href || !href.startsWith('#')) return;
                    const target = document.querySelector(href);
                    if (!target) return;
                    e.preventDefault();
                    window.scrollTo({ top: target.offsetTop - 96, behavior: 'smooth' });
                });
            });

            const countdowns = document.querySelectorAll('[data-countdown]');
            countdowns.forEach(el => {
                const dateStr = el.getAttribute('data-countdown');
                const normalizedDateStr = /^\d{4}-\d{2}-\d{2}$/.test(dateStr) ? `${dateStr}T00:00:00` : dateStr;
                const target = new Date(normalizedDateStr);
                if (!target.getTime()) return;
                const today = new Date();
                today.setHours(0,0,0,0);
                const diff = Math.ceil((target - today) / (1000*60*60*24));
                const label = el.querySelector('.flacso-oferta-countdown__text');
                if (!label) return;
                if (diff > 0) {
                    label.textContent = '<?php echo esc_js(__('Faltan', 'flacso-oferta-academica')); ?> ' + diff + ' <?php echo esc_js(__('días', 'flacso-oferta-academica')); ?>';
                } else if (diff === 0) {
                    label.textContent = '<?php echo esc_js(__('Comienza hoy', 'flacso-oferta-academica')); ?>';
                } else {
                    el.style.display = 'none';
                    el.setAttribute('aria-hidden', 'true');
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render completo de un programa/oferta para insertar en una pagina legacy.
     * Incluye CTA de preinscripcion, formulario de consulta y docentes.
     */
    public static function render_oferta_programa(array $attributes = [], $block = null): string {
        self::enqueue_styles();

        $oferta_id = self::resolve_oferta_programa_id($attributes, $block);
        $is_editor_preview = self::is_editor_preview_context();

        if ($oferta_id <= 0) {
            return $is_editor_preview
                ? '<p>' . esc_html__('No se encontro una oferta academica vinculada a esta pagina. Asocia la pagina desde el CPT o define un ofertaId en el bloque.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $oferta = get_post($oferta_id);
        if (!$oferta || $oferta->post_type !== 'oferta-academica') {
            return $is_editor_preview
                ? '<p>' . esc_html__('La oferta academica seleccionada no es valida.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $context_page_id = self::resolve_context_page_id($attributes, $block);
        $associated_page_id = (int) get_post_meta($oferta_id, '_oferta_page_id', true);
        $source_page_id = $associated_page_id > 0 ? $associated_page_id : $context_page_id;

        $title = get_the_title($oferta_id);
        $abreviacion = (string) get_post_meta($oferta_id, 'abreviacion', true);
        $correo = (string) get_post_meta($oferta_id, 'correo', true);
        $inscripciones_abiertas = (bool) get_post_meta($oferta_id, 'inscripciones_abiertas', true);

        $tipo = '';
        $tipos = wp_get_post_terms($oferta_id, 'tipo-oferta-academica', ['fields' => 'names']);
        if (!is_wp_error($tipos) && !empty($tipos)) {
            $tipo = (string) $tipos[0];
        }

        $hero_image = get_the_post_thumbnail_url($oferta_id, 'full');
        if (!$hero_image && $source_page_id > 0) {
            $hero_image = get_the_post_thumbnail_url($source_page_id, 'full');
        }

        $sections = self::get_programa_html_sections($oferta_id);
        $docente_ids = self::collect_programa_docente_ids($oferta_id);

        $proximo_inicio_html = class_exists('Oferta_Blocks')
            ? Oferta_Blocks::render_dato_proximo_inicio(['ofertaId' => $oferta_id])
            : '';
        $calendario_markup = class_exists('Oferta_Blocks')
            ? Oferta_Blocks::render_dato_calendario(['ofertaId' => $oferta_id, 'displayMode' => 'auto'])
            : '';
        $malla_markup = class_exists('Oferta_Blocks')
            ? Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $oferta_id, 'displayMode' => 'auto'])
            : '';

        $mostrar_preinscripcion = !array_key_exists('mostrarPreinscripcion', $attributes) || !empty($attributes['mostrarPreinscripcion']);
        $mostrar_formulario = !array_key_exists('mostrarFormulario', $attributes) || !empty($attributes['mostrarFormulario']);

        $preinscripcion_url = self::build_preinscripcion_url($source_page_id, $oferta_id);

        $has_consulta_form = $mostrar_formulario
            && class_exists('Oferta_Consulta_Form')
            && method_exists('Oferta_Consulta_Form', 'render_floating_form');

        $root_id = function_exists('wp_unique_id')
            ? wp_unique_id('flacso-oa-programa-')
            : ('flacso-oa-programa-' . wp_rand(1000, 9999));

        ob_start();
        ?>
        <section id="<?php echo esc_attr($root_id); ?>" class="flacso-oa-programa">
            <header class="flacso-oa-programa__hero">
                <div class="flacso-oa-programa__hero-media">
                    <?php if ($hero_image) : ?>
                        <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
                    <?php else : ?>
                        <div class="flacso-oa-programa__hero-media-fallback" aria-hidden="true">
                            <i class="bi bi-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flacso-oa-programa__hero-content">
                    <?php if ($tipo !== '') : ?>
                        <p class="flacso-oa-programa__eyebrow"><?php echo esc_html($tipo); ?></p>
                    <?php endif; ?>
                    <h1 class="flacso-oa-programa__title"><?php echo esc_html($title); ?></h1>
                    <?php if ($abreviacion !== '') : ?>
                        <p class="flacso-oa-programa__abbr"><?php echo esc_html($abreviacion); ?></p>
                    <?php endif; ?>

                    <div class="flacso-oa-programa__meta">
                        <?php if ($correo !== '') : ?>
                            <a class="flacso-oa-programa__mail" href="mailto:<?php echo esc_attr(antispambot($correo)); ?>">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <span><?php echo esc_html($correo); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($proximo_inicio_html !== '') : ?>
                        <div class="flacso-oa-programa__inicio">
                            <?php echo $proximo_inicio_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php endif; ?>

                    <div class="flacso-oa-programa__actions">
                        <?php if ($mostrar_preinscripcion && $preinscripcion_url !== '' && $inscripciones_abiertas) : ?>
                            <a class="flacso-oa-programa__btn flacso-oa-programa__btn--preinscripcion" href="<?php echo esc_url($preinscripcion_url); ?>">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                <span><?php esc_html_e('Preinscripcion', 'flacso-oferta-academica'); ?></span>
                            </a>
                        <?php elseif ($mostrar_preinscripcion) : ?>
                            <span class="flacso-oa-programa__btn flacso-oa-programa__btn--disabled" aria-disabled="true">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <span><?php esc_html_e('Preinscripcion no disponible', 'flacso-oferta-academica'); ?></span>
                            </span>
                        <?php endif; ?>

                        <?php if ($has_consulta_form) : ?>
                            <button type="button" class="flacso-oa-programa__btn flacso-oa-programa__btn--consulta" data-oa-programa-open-consulta>
                                <i class="bi bi-send" aria-hidden="true"></i>
                                <span><?php esc_html_e('Solicitar informacion', 'flacso-oferta-academica'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if ($calendario_markup !== '' || $malla_markup !== '') : ?>
                <section class="flacso-oa-programa__documents" aria-label="<?php esc_attr_e('Documentos del programa', 'flacso-oferta-academica'); ?>">
                    <div class="flacso-oa-programa__documents-grid">
                        <?php if ($calendario_markup !== '') : ?>
                            <?php echo $calendario_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php if ($malla_markup !== '') : ?>
                            <?php echo $malla_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($sections)) : ?>
                <section class="flacso-oa-programa__sections" aria-label="<?php esc_attr_e('Detalle del programa', 'flacso-oferta-academica'); ?>">
                    <?php foreach ($sections as $section_label => $section_html) : ?>
                        <article class="flacso-oa-programa-section">
                            <h2 class="flacso-oa-programa-section__title"><?php echo esc_html($section_label); ?></h2>
                            <div class="flacso-oa-programa-section__content">
                                <?php echo wp_kses_post($section_html); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="flacso-oa-programa__docentes" aria-label="<?php esc_attr_e('Docentes', 'flacso-oferta-academica'); ?>">
                <h2 class="flacso-oa-programa__docentes-title"><?php esc_html_e('Docentes', 'flacso-oferta-academica'); ?></h2>
                <?php
                $docentes_grid = self::render_docentes_grid_markup($docente_ids);
                if ($docentes_grid['count'] > 0) :
                ?>
                    <div class="flacso-oa-programa-docentes-grid">
                        <?php echo $docentes_grid['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php else : ?>
                    <p class="flacso-oa-programa__docentes-empty"><?php esc_html_e('Docentes a confirmar.', 'flacso-oferta-academica'); ?></p>
                <?php endif; ?>
            </section>
        </section>

        <?php if ($has_consulta_form) : ?>
            <?php echo Oferta_Consulta_Form::render_floating_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <script>
            (function() {
                var root = document.getElementById(<?php echo wp_json_encode($root_id); ?>);
                if (!root) return;
                root.addEventListener('click', function(event) {
                    var trigger = event.target.closest('[data-oa-programa-open-consulta]');
                    if (!trigger) return;
                    event.preventDefault();
                    var scope = document.querySelector('[data-flacso-oa-consulta]');
                    if (!scope) return;
                    var select = scope.querySelector('select[name="oferta_id"]');
                    if (select) {
                        select.value = <?php echo (int) $oferta_id; ?>;
                    }
                    var opener = scope.querySelector('[data-oa-consulta-open]');
                    if (opener) opener.click();
                });
            })();
            </script>
        <?php endif; ?>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renderiza la coordinacion academica de una oferta educativa agrupando docentes por rol.
     */
    public static function render_oferta_coordinacion_academica(array $attributes = [], $block = null): string {
        self::enqueue_styles();

        $oferta_id = self::resolve_oferta_programa_id($attributes, $block);
        $is_editor_preview = self::is_editor_preview_context();

        if ($oferta_id <= 0) {
            return $is_editor_preview
                ? '<p>' . esc_html__('No se encontro una oferta academica vinculada. Define un ofertaId o usa este bloque en una pagina asociada.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $groups = self::collect_docente_groups_by_role($oferta_id, 'coordinacion_academica', 'rol');
        if (empty($groups)) {
            return $is_editor_preview
                ? '<p>' . esc_html__('La oferta seleccionada no tiene coordinacion academica cargada.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $rendered_groups = [];
        foreach ($groups as $group) {
            $docentes_grid = self::render_docentes_grid_markup($group['docentes']);
            if ($docentes_grid['count'] <= 0) {
                continue;
            }

            ob_start();
            ?>
            <article class="flacso-oa-coordinacion-group">
                <h3 class="flacso-oa-coordinacion-group__title"><?php echo esc_html($group['label']); ?></h3>
                <div class="flacso-oa-programa-docentes-grid">
                    <?php echo $docentes_grid['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </article>
            <?php
            $rendered_groups[] = (string) ob_get_clean();
        }

        if (empty($rendered_groups)) {
            return $is_editor_preview
                ? '<p>' . esc_html__('No hay perfiles de coordinacion visibles con la configuracion actual.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        ob_start();
        ?>
        <section class="flacso-oa-programa__docentes flacso-oa-coordinacion" aria-label="<?php esc_attr_e('Coordinacion academica de la oferta educativa', 'flacso-oferta-academica'); ?>">
            <h2 class="flacso-oa-programa__docentes-title"><?php esc_html_e('Coordinacion Academica de la Oferta Educativa', 'flacso-oferta-academica'); ?></h2>
            <div class="flacso-oa-coordinacion__groups">
                <?php echo implode('', $rendered_groups); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function get_programa_html_sections(int $oferta_id): array {
        $map = [
            __('Modalidad', 'flacso-oferta-academica') => 'modalidad_html',
            __('Duracion', 'flacso-oferta-academica') => 'duracion_html',
            __('Objetivos', 'flacso-oferta-academica') => 'objetivos_html',
            __('Perfil de ingreso', 'flacso-oferta-academica') => 'perfil_ingreso_html',
            __('Requisitos de ingreso', 'flacso-oferta-academica') => 'requisitos_ingreso_html',
            __('Perfil de egreso', 'flacso-oferta-academica') => 'perfil_egreso_html',
            __('Requisitos de egreso', 'flacso-oferta-academica') => 'requisitos_egreso_html',
            __('Titulos y certificaciones', 'flacso-oferta-academica') => 'titulos_certificaciones_html',
        ];

        $sections = [];
        $duration_months = (string) get_post_meta($oferta_id, 'duracion_meses', true);
        foreach ($map as $label => $meta_key) {
            $html = trim((string) get_post_meta($oferta_id, $meta_key, true));
            if ('duracion_html' === $meta_key) {
                $html = self::normalize_duration_html($html, $duration_months);
            }
            if (!self::has_visible_html_content($html)) {
                continue;
            }
            $sections[$label] = $html;
        }

        return $sections;
    }

    private static function collect_programa_docente_ids(int $oferta_id): array {
        $ids = [];

        $direct = get_post_meta($oferta_id, '_oferta_docentes_ids', true);
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        foreach ([
            ['meta_key' => 'coordinacion_academica', 'label_key' => 'rol'],
            ['meta_key' => 'equipos', 'label_key' => 'nombre'],
        ] as $config) {
            $groups = self::collect_docente_groups_by_role($oferta_id, $config['meta_key'], $config['label_key']);
            foreach ($groups as $group) {
                $ids = array_merge($ids, $group['docentes']);
            }
        }

        return self::normalize_docente_ids($ids);
    }

    /**
     * Obtiene grupos de docentes para una meta de oferta, conservando la etiqueta de rol/nombre.
     *
     * @return array<int, array{label:string, docentes:array<int, int>}>
     */
    private static function collect_docente_groups_by_role(int $oferta_id, string $meta_key, string $label_key): array {
        $groups = get_post_meta($oferta_id, $meta_key, true);
        if (!is_array($groups)) {
            return [];
        }

        $default_label = $label_key === 'rol'
            ? __('Sin rol asignado', 'flacso-oferta-academica')
            : __('Sin nombre', 'flacso-oferta-academica');

        $normalized = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $label = isset($group[$label_key]) ? trim(sanitize_text_field((string) $group[$label_key])) : '';
            $docentes = isset($group['docentes']) && is_array($group['docentes']) ? $group['docentes'] : [];
            $docente_ids = self::normalize_docente_ids($docentes);
            if (empty($docente_ids)) {
                continue;
            }

            $normalized[] = [
                'label' => $label !== '' ? $label : $default_label,
                'docentes' => $docente_ids,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private static function normalize_docente_ids(array $ids): array {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
    }

    /**
     * @param array<int, int> $docente_ids
     * @return array{count:int, html:string}
     */
    private static function render_docentes_grid_markup(array $docente_ids): array {
        $cards = [];
        foreach ($docente_ids as $docente_id) {
            $card_html = self::render_docente_card_markup((int) $docente_id);
            if ($card_html === '') {
                continue;
            }
            $cards[] = $card_html;
        }

        return [
            'count' => count($cards),
            'html' => implode('', $cards),
        ];
    }

    private static function render_docente_card_markup(int $docente_id): string {
        $docente = get_post($docente_id);
        if (!$docente || $docente->post_type !== 'docente') {
            return '';
        }

        $can_view = $docente->post_status === 'publish' || self::should_include_private_programs();
        if (!$can_view) {
            return '';
        }

        $nombre = function_exists('dp_nombre_completo') ? dp_nombre_completo($docente_id) : get_the_title($docente_id);
        $nombre = $nombre ?: get_the_title($docente_id);
        $prefijo = (string) get_post_meta($docente_id, 'prefijo_abrev', true);
        $titulo_docente = (string) get_post_meta($docente_id, 'titulo', true);
        $resumen = trim((string) get_the_excerpt($docente_id));
        if ($resumen === '') {
            $cv_text = wp_strip_all_tags((string) get_post_meta($docente_id, 'cv', true));
            $resumen = wp_trim_words($cv_text, 22);
        }

        $inicial = '';
        if ($nombre !== '') {
            $inicial = function_exists('mb_substr') ? mb_substr($nombre, 0, 1, 'UTF-8') : substr($nombre, 0, 1);
            $inicial = function_exists('mb_strtoupper') ? mb_strtoupper((string) $inicial, 'UTF-8') : strtoupper((string) $inicial);
        }

        ob_start();
        ?>
        <article class="flacso-oa-programa-docente-card">
            <a href="<?php echo esc_url(get_permalink($docente_id)); ?>" class="flacso-oa-programa-docente-card__link">
                <div class="flacso-oa-programa-docente-card__media">
                    <?php if (has_post_thumbnail($docente_id)) : ?>
                        <?php echo get_the_post_thumbnail($docente_id, 'medium', ['loading' => 'lazy']); ?>
                    <?php else : ?>
                        <span class="flacso-oa-programa-docente-card__placeholder" aria-hidden="true">
                            <?php echo esc_html($inicial); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flacso-oa-programa-docente-card__body">
                    <?php if ($prefijo !== '') : ?>
                        <p class="flacso-oa-programa-docente-card__prefijo"><?php echo esc_html($prefijo); ?></p>
                    <?php endif; ?>
                    <h3 class="flacso-oa-programa-docente-card__name"><?php echo esc_html($nombre); ?></h3>
                    <?php if ($titulo_docente !== '') : ?>
                        <p class="flacso-oa-programa-docente-card__title"><?php echo esc_html($titulo_docente); ?></p>
                    <?php endif; ?>
                    <?php if ($resumen !== '') : ?>
                        <p class="flacso-oa-programa-docente-card__excerpt"><?php echo esc_html($resumen); ?></p>
                    <?php endif; ?>
                    <span class="flacso-oa-programa-docente-card__cta"><?php esc_html_e('Ver perfil', 'flacso-oferta-academica'); ?></span>
                </div>
            </a>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    private static function resolve_oferta_programa_id(array $attributes, $block = null): int {
        $attribute_id = isset($attributes['ofertaId']) ? (int) $attributes['ofertaId'] : 0;
        if ($attribute_id > 0) {
            $post = get_post($attribute_id);
            if ($post && $post->post_type === 'oferta-academica') {
                return $attribute_id;
            }
        }

        if ($block && isset($block->context) && is_array($block->context)) {
            $context_post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : 0;
            $context_post_type = isset($block->context['postType']) ? (string) $block->context['postType'] : '';
            if ($context_post_id > 0 && $context_post_type === 'oferta-academica') {
                return $context_post_id;
            }
        }

        if (is_singular('oferta-academica')) {
            return (int) get_queried_object_id();
        }

        $page_id = self::resolve_context_page_id($attributes, $block);
        if ($page_id <= 0) {
            return 0;
        }

        $ids = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => self::oferta_post_statuses(),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_oferta_page_id',
            'meta_value' => $page_id,
            'orderby' => 'ID',
            'order' => 'DESC',
        ]);

        return !empty($ids) ? (int) $ids[0] : 0;
    }

    private static function resolve_context_page_id(array $attributes, $block = null): int {
        if (isset($attributes['postId'])) {
            $post_id = (int) $attributes['postId'];
            if ($post_id > 0) {
                return $post_id;
            }
        }

        if ($block && isset($block->context) && is_array($block->context) && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        if (is_singular()) {
            $queried = (int) get_queried_object_id();
            if ($queried > 0) {
                return $queried;
            }
        }

        if (isset($_REQUEST['post_id'])) {
            $request_post_id = (int) wp_unslash($_REQUEST['post_id']);
            if ($request_post_id > 0) {
                return $request_post_id;
            }
        }

        return 0;
    }

    private static function build_preinscripcion_url(int $page_id, int $oferta_id): string {
        $base_permalink = get_permalink($oferta_id);
        if (!$base_permalink) {
            return '';
        }

        return trailingslashit($base_permalink) . 'preinscripcion/';
    }

    private static function is_editor_preview_context(): bool {
        if (is_admin()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context = isset($_REQUEST['context']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['context'])) : '';
            if ($context === 'edit') {
                return true;
            }

            $route = isset($_REQUEST['rest_route']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['rest_route'])) : '';
            if ($route && strpos($route, '/wp/v2/block-renderer/') !== false) {
                return true;
            }
        }

        return false;
    }

    private static function render_program_card(int $post_id, $term): bool {
        if (self::is_password_protected_program($post_id)) {
            return false;
        }

        $title     = get_the_title($post_id);
        $excerpt   = wp_trim_words(get_the_excerpt($post_id), 22);
        $permalink = get_permalink($post_id);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'large');

        $page_id = absint(get_post_meta($post_id, '_oferta_page_id', true));
        $excerpt   = wp_trim_words(get_the_excerpt($post_id), 22);
        $permalink = get_permalink($post_id);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'large');

        $page_id = absint(get_post_meta($post_id, '_oferta_page_id', true));
        $is_private = self::is_private_program($post_id, $page_id);
        if ($is_private && !self::should_include_private_programs()) {
            return false;
        }

        $link    = $permalink;
        if (!$thumbnail && $page_id) {
            $thumbnail = get_the_post_thumbnail_url($page_id, 'large');
        }
        $proximo_raw = get_post_meta($post_id, 'proximo_inicio', true);
        $proximo_precision = (string) get_post_meta($post_id, 'proximo_inicio_precision', true);
        if (is_array($proximo_raw)) {
            $proximo_raw = reset($proximo_raw);
        }
        $proximo_raw = (string) $proximo_raw;

        $proximo_fmt = '';
        $is_exact_date = false;
        $proximo_iso = '';

        if (!empty($proximo_raw)) {
            $precision = self::detect_proximo_inicio_precision($proximo_raw, $proximo_precision);
            $formatted = self::format_proximo_inicio_text($proximo_raw, $proximo_precision);

            if ($precision === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximo_raw)) {
                $proximo_fmt = $formatted;
                $proximo_iso = $proximo_raw;
                $is_exact_date = true;
            } elseif ($formatted !== '') {
                $proximo_fmt = 'próximo inicio ' . $formatted;
            } else {
                $proximo_fmt = 'próximo inicio: ' . $proximo_raw;
            }
        }

        ?>
        <div class="col-md-6 col-lg-4">
            <a
                href="<?php echo esc_url($link); ?>"
                class="flacso-oa-card h-100 d-block text-decoration-none<?php echo $is_private ? ' flacso-oa-card--private' : ''; ?>"
                aria-label="<?php echo esc_attr(sprintf(__('Ver detalles: %s', 'flacso-oferta-academica'), $title)); ?>"
            >
                <div class="flacso-oa-card__media ratio ratio-1x1 mb-0">
                    <?php if ($thumbnail) : ?>
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" class="w-100 h-100 object-fit-cover" loading="lazy" />
                    <?php else : ?>
                        <div class="flacso-oa-card__placeholder d-flex align-items-center justify-content-center">
                            <i class="bi bi-image" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flacso-oa-card__body p-4">
                    <?php if ($is_private && self::should_include_private_programs()) : ?>
                        <div class="flacso-oa-card__badges mb-2">
                            <span class="flacso-oa-card__status-badge">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <?php esc_html_e('Privado', 'flacso-oferta-academica'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <h3 class="flacso-oa-card__title mb-1"><?php echo esc_html($title); ?></h3>
                    <?php if ($excerpt) : ?>
                        <p class="flacso-oa-card__excerpt mb-2"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>
                    <?php if ($proximo_fmt) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-calendar3 text-primary" aria-hidden="true"></i>
                            <?php if ($is_exact_date) : ?>
                                <time datetime="<?php echo esc_attr($proximo_iso); ?>"><?php echo esc_html($proximo_fmt); ?></time>
                            <?php else : ?>
                                <span><?php echo esc_html(ucfirst($proximo_fmt)); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_exact_date) : ?>
                        <div class="flacso-oferta-countdown" data-countdown="<?php echo esc_attr($proximo_iso); ?>" aria-live="polite">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <span class="flacso-oferta-countdown__text"><?php esc_html_e('Cargando', 'flacso-oferta-academica'); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="flacso-oa-card__footer mt-2">
                        <span class="flacso-oa-card__cta fw-semibold">
                            <?php esc_html_e('Ver detalles', 'flacso-oferta-academica'); ?>
                            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                        </span>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return true;
    }

    /**
     * Renderizar programas por tipo (taxonomía programa)
     * 
     * @param string $programa "Maestría", "Especialización", "Diplomado", "Diploma"
     * @return string HTML
     */
    public static function render_by_taxonomy(string $programa): string {
        self::enqueue_styles();

        $query_args = [
            'post_type' => 'oferta-academica',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'tipo-oferta-academica',
                    'field' => 'name',
                    'terms' => $programa,
                ],
            ],
        ];

        $query = new WP_Query(self::exclude_password_protected_from_query_args($query_args));

        ob_start();
        ?>
        <div class="flacso-oferta-listado" data-programa="<?php echo esc_attr($programa); ?>">
            <div class="oferta-grid">
                <?php
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        self::render_card(get_the_ID());
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p>No hay programas disponibles en esta categoría.</p>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar seminarios (compatible con cpt-seminario)
     */
    public static function render_seminarios(): string {
        self::enqueue_styles();

        if (!post_type_exists('seminario')) {
            return '<p>El plugin CPT Seminarios no está activo.</p>';
        }

        $query_args = [
            'post_type' => 'seminario',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($query_args);

        ob_start();
        ?>
        <div class="flacso-oferta-listado flacso-seminarios" data-type="seminarios">
            <div class="oferta-grid">
                <?php
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        self::render_seminario_card(get_the_ID());
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p>No hay seminarios disponibles.</p>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderizar seminarios con grid Bootstrap
     */
    public static function render_seminarios_bootstrap(): string {
        self::enqueue_styles();

        if (!post_type_exists('seminario')) {
            return '<div class="alert alert-warning">El plugin CPT Seminarios no está activo.</div>';
        }
        $query_args = [
            'post_type'      => 'seminario',
            'post_status'    => 'publish',
            'posts_per_page' => 40,
            'meta_key'       => '_seminario_periodo_inicio',
            'orderby'        => 'meta_value',
            'meta_type'      => 'DATE',
            'order'          => 'ASC',
        ];

        $query = new WP_Query($query_args);
        
        $eight_days_ago_ts = strtotime('-8 days');
        $eight_days_ago_ts = mktime(0, 0, 0, (int) date('m', $eight_days_ago_ts), (int) date('d', $eight_days_ago_ts), (int) date('Y', $eight_days_ago_ts));

        ob_start();
        if ($query->have_posts()) :
        ?>
            <div class="row g-4">
                <?php
                $index = 0;
                while ($query->have_posts()) {
                    $query->the_post();
                    
                    $fecha_raw = get_post_meta(get_the_ID(), '_seminario_periodo_inicio', true) ?: get_post_meta(get_the_ID(), 'periodo_inicio', true);
                    $ts = $fecha_raw ? strtotime($fecha_raw) : 0;
                    
                    if ($ts > 0 && $ts < $eight_days_ago_ts) {
                        continue;
                    }

                    $index++;
                    if ($index > 12) {
                        break;
                    }
                    self::render_seminario_card_bootstrap(get_the_ID(), $index);
                }
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <div class="alert alert-info">
                <?php esc_html_e('No hay seminarios disponibles.', 'flacso-oferta-academica'); ?>
            </div>
        <?php
        endif;
        return ob_get_clean();
    }

    /**
     * Renderizar card de programa
     */
    private static function render_card($post_id): void {
        if (self::is_password_protected_program($post_id)) {
            return;
        }

        $title = get_the_title($post_id);
        $excerpt = get_the_excerpt($post_id);
        $permalink = get_permalink($post_id);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
        
        $page_id = get_post_meta($post_id, '_oferta_page_id', true);
        $link = $permalink;
        
        ?>
        <a href="<?php echo esc_url($link); ?>" class="oferta-card">
            <?php if ($thumbnail) : ?>
                <div class="oferta-card-image" style="background-image: url('<?php echo esc_url($thumbnail); ?>')"></div>
            <?php endif; ?>
            <div class="oferta-card-content">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if ($excerpt) : ?>
                    <p><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
            </div>
        </a>
        <?php
    }

    private static function render_card_bootstrap($post_id, $categoria = '', $index = 0): void {
        if (self::is_password_protected_program($post_id)) {
            return;
        }

        $title = get_the_title($post_id);
        $excerpt = get_the_excerpt($post_id);
        $permalink = get_permalink($post_id);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');

        $page_id = get_post_meta($post_id, '_oferta_page_id', true);
        $link = $permalink;
        if (!$thumbnail && $page_id) {
            $thumbnail = get_the_post_thumbnail_url($page_id, 'medium');
        }

        $span_class = '';
        if ($index % 7 === 0) {
            $span_class = ' flacso-oferta-card--wide';
        }
        if ($index % 9 === 0) {
            $span_class .= ' flacso-oferta-card--tall';
        }
        ?>
        <div class="flacso-oferta-grid-item<?php echo esc_attr($span_class); ?>">
            <a href="<?php echo esc_url($link); ?>" class="card h-100 flacso-oferta-card text-decoration-none">
                <?php if ($thumbnail) : ?>
                    <div class="ratio ratio-1x1 flacso-oferta-card__media">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" class="w-100 h-100 object-fit-cover">
                    </div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h3 class="h5 card-title mb-2"><?php echo esc_html($title); ?></h3>
                    <?php if ($excerpt) : ?>
                        <p class="card-text text-muted small mb-3"><?php echo esc_html(wp_trim_words($excerpt, 18)); ?></p>
                    <?php endif; ?>
                    <?php if ($categoria) : ?>
                        <span class="badge rounded-pill bg-primary-subtle text-primary mt-auto flacso-oferta-card__badge">
                            <?php echo esc_html($categoria); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php
    }

    /**
     * Renderizar card de seminario
     */
    private static function render_seminario_card($post_id): void {
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
        
        // Metadatos específicos de seminarios
        $fecha_inicio = get_post_meta($post_id, 'fecha_inicio', true);
        $modalidad = get_post_meta($post_id, 'modalidad', true);
        
        ?>
        <a href="<?php echo esc_url($permalink); ?>" class="oferta-card seminario-card">
            <?php if ($thumbnail) : ?>
                <div class="oferta-card-image" style="background-image: url('<?php echo esc_url($thumbnail); ?>')"></div>
            <?php endif; ?>
            <div class="oferta-card-content">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if ($fecha_inicio) : ?>
                    <p class="seminario-fecha">
                        <i class="dashicons dashicons-calendar"></i>
                        <?php echo esc_html(date_i18n('j F, Y', strtotime($fecha_inicio))); ?>
                    </p>
                <?php endif; ?>
                <?php if ($modalidad) : ?>
                    <p class="seminario-modalidad">
                        <i class="dashicons dashicons-location"></i>
                        <?php echo esc_html($modalidad); ?>
                    </p>
                <?php endif; ?>
            </div>
        </a>
        <?php
    }

        private static function render_seminario_card_bootstrap($post_id, $index = 0): void {
        $title       = get_the_title($post_id);
        $permalink   = get_permalink($post_id);
        $thumb       = get_the_post_thumbnail_url($post_id, 'large');

        $fecha_raw   = get_post_meta($post_id, '_seminario_periodo_inicio', true) ?: get_post_meta($post_id, 'periodo_inicio', true);
        $modalidad   = get_post_meta($post_id, 'modalidad', true);
        $creditos    = get_post_meta($post_id, 'creditos', true);

        $ts          = $fecha_raw ? strtotime($fecha_raw) : 0;
        $is_exact_date = $fecha_raw ? (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fecha_raw)) : false;
        
        if ($ts && !$is_exact_date) {
            $parts = explode('-', trim($fecha_raw));
            if (count($parts) === 2) {
                $fecha_fmt = date_i18n('F Y', $ts);
            } elseif (count($parts) === 1) {
                $fecha_fmt = date_i18n('Y', $ts);
            } else {
                $fecha_fmt = date_i18n('F Y', $ts);
            }
        } else {
            $fecha_fmt   = $ts ? date_i18n('l j \d\e F Y', $ts) : '';
        }
        
        $fecha_iso   = $ts ? date('Y-m-d', $ts) : '';
        $faltan_dias = $ts ? floor(($ts - current_time('timestamp')) / DAY_IN_SECONDS) : null;
        $faltan_txt  = is_int($faltan_dias) && $faltan_dias >= 0
            ? sprintf(__('Faltan %d días', 'flacso-oferta-academica'), $faltan_dias)
            : '';

        ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?php echo esc_url($permalink); ?>" class="flacso-oa-card h-100 d-block text-decoration-none" aria-label="<?php echo esc_attr(sprintf(__('Ver seminario: %s', 'flacso-oferta-academica'), $title)); ?>">
                <div class="flacso-oa-card__media ratio ratio-1x1 mb-0">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" class="w-100 h-100 object-fit-cover" loading="lazy" />
                    <?php else : ?>
                        <div class="flacso-oa-card__placeholder d-flex align-items-center justify-content-center">
                            <i class="bi bi-image" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flacso-oa-card__body p-4">
                    <h3 class="flacso-oa-card__title mb-2"><?php echo esc_html($title); ?></h3>
                    <?php if ($fecha_fmt) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-calendar3 text-primary" aria-hidden="true"></i>
                            <?php if ($is_exact_date) : ?>
                                <time datetime="<?php echo esc_attr($fecha_iso); ?>"><?php echo esc_html($fecha_fmt); ?></time>
                            <?php else : ?>
                                <span><?php echo esc_html(ucfirst($fecha_fmt)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($modalidad) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-camera-video text-primary" aria-hidden="true"></i>
                            <span><?php echo esc_html($modalidad); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($creditos !== '' && $creditos !== null) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-award text-primary" aria-hidden="true"></i>
                            <span><?php printf(__('Créditos: %s', 'flacso-oferta-academica'), esc_html($creditos)); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($fecha_raw && is_int($faltan_dias) && $faltan_dias >= 0) : ?>
                        <div class="flacso-oferta-countdown mt-2" data-countdown="<?php echo esc_attr($fecha_iso); ?>" aria-live="polite">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <span class="flacso-oferta-countdown__text">
                                <?php echo esc_html($faltan_txt ?: __('Calendario', 'flacso-oferta-academica')); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="flacso-oa-card__footer mt-2">
                        <span class="flacso-oa-card__cta fw-semibold">
                            <?php esc_html_e('Ver detalles', 'flacso-oferta-academica'); ?>
                            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                        </span>
                    </div>
                </div>
            </a>
        </div>
        <?php
    }
}
