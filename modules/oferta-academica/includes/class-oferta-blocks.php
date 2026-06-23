<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra los bloques de Gutenberg para Oferta Académica.
 * Incluye los bloques completos y los datos individuales.
 */
class Oferta_Blocks {

    public static function init(): void {
        self::register_blocks();
    }

    public static function register_blocks(): void {
        $blocks_base_path = dirname(__DIR__) . '/blocks/';

        // Editor JS para bloques completos (oferta-academica y oferta-academica-pagina)
        $script_relative = 'modules/oferta-academica/assets/js/oferta-block.js';
        $script_path     = FLACSO_URUGUAY_PATH . $script_relative;
        $script_url      = FLACSO_URUGUAY_URL . $script_relative;
        $script_version  = file_exists($script_path) ? filemtime($script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-block-editor',
            $script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render'],
            $script_version,
            true
        );

        // Editor JS para dato-proximo-inicio
        $dato_script_relative = 'modules/oferta-academica/assets/js/dato-proximo-inicio-block.js';
        $dato_script_path     = FLACSO_URUGUAY_PATH . $dato_script_relative;
        $dato_script_url      = FLACSO_URUGUAY_URL . $dato_script_relative;
        $dato_script_version  = file_exists($dato_script_path) ? filemtime($dato_script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-dato-proximo-inicio-block',
            $dato_script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-data'],
            $dato_script_version,
            true
        );

        // Editor JS para bloques de documentos (calendario y Malla curricular)
        $documento_script_relative = 'modules/oferta-academica/assets/js/dato-documento-pdf-block.js';
        $documento_script_path     = FLACSO_URUGUAY_PATH . $documento_script_relative;
        $documento_script_url      = FLACSO_URUGUAY_URL . $documento_script_relative;
        $documento_script_version  = file_exists($documento_script_path) ? filemtime($documento_script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-dato-documento-pdf-block',
            $documento_script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-data', 'wp-api-fetch'],
            $documento_script_version,
            true
        );

        register_block_type('flacso-uruguay/dato-proximo-inicio', [
            'editor_script' => 'flacso-oferta-dato-proximo-inicio-block',
            'render_callback' => [__CLASS__, 'render_dato_proximo_inicio'],
            'attributes' => [
                'ofertaId' => ['type' => 'integer', 'default' => 0],
                'postId' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_block_type('flacso-uruguay/dato-calendario', [
            'editor_script' => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_calendario'],
            'attributes' => [
                'ofertaId' => ['type' => 'integer', 'default' => 0],
                'postId' => ['type' => 'integer', 'default' => 0],
                'pdfUrlFallback' => ['type' => 'string', 'default' => ''],
                'displayMode' => ['type' => 'string', 'default' => 'auto'],
            ],
        ]);

        register_block_type('flacso-uruguay/dato-malla-curricular', [
            'editor_script' => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_malla_curricular'],
            'attributes' => [
                'ofertaId' => ['type' => 'integer', 'default' => 0],
                'postId' => ['type' => 'integer', 'default' => 0],
                'pdfUrlFallback' => ['type' => 'string', 'default' => ''],
                'displayMode' => ['type' => 'string', 'default' => 'auto'],
            ],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-pagina', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_completa'],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-programa', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_programa'],
        ]);

        register_block_type($blocks_base_path . 'oferta-coordinacion-academica', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_coordinacion_academica'],
        ]);

        // Bloques individuales por tipo (compatibilidad legacy)
        register_block_type($blocks_base_path . 'maestrias', [
            'render_callback' => [__CLASS__, 'render_maestrias'],
        ]);
        register_block_type($blocks_base_path . 'especializaciones', [
            'render_callback' => [__CLASS__, 'render_especializaciones'],
        ]);
        register_block_type($blocks_base_path . 'diplomados', [
            'render_callback' => [__CLASS__, 'render_diplomados'],
        ]);
        register_block_type($blocks_base_path . 'diplomas', [
            'render_callback' => [__CLASS__, 'render_diplomas'],
        ]);
        register_block_type($blocks_base_path . 'seminarios', [
            'render_callback' => [__CLASS__, 'render_seminarios'],
        ]);
    }

    private static function ensure_styles(): void {
        if (class_exists('Oferta_Renderer') && method_exists('Oferta_Renderer', 'enqueue_styles')) {
            Oferta_Renderer::enqueue_styles();
        }
    }

    public static function render_oferta_completa($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_pagina((array) $attributes);
    }

    public static function render_oferta_programa($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_programa((array) $attributes, $block);
    }

    public static function render_oferta_coordinacion_academica($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_coordinacion_academica((array) $attributes, $block);
    }

    public static function render_maestrias($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Maestría');
    }

    public static function render_especializaciones($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Especialización');
    }

    public static function render_diplomados($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Diplomado');
    }

    public static function render_diplomas($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Diploma');
    }

    public static function render_seminarios($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_seminarios();
    }

    public static function render_dato_proximo_inicio($attributes, $content = '', $block = null): string {
        self::ensure_styles();

        $oferta_id = self::resolve_oferta_id((array) $attributes);
        if ($oferta_id <= 0) {
            return self::is_editor_preview_context()
                ? '<p>' . esc_html__('Selecciona una oferta académica para mostrar el próximo inicio.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $valor = trim((string) get_post_meta($oferta_id, 'proximo_inicio', true));
        if ($valor === '') {
            return self::is_editor_preview_context()
                ? '<p>' . esc_html__('La oferta seleccionada no tiene próximo inicio configurado.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $precision = trim((string) get_post_meta($oferta_id, 'proximo_inicio_precision', true));
        $formatted = class_exists('Oferta_Renderer') && method_exists('Oferta_Renderer', 'format_proximo_inicio_text')
            ? Oferta_Renderer::format_proximo_inicio_text($valor, $precision)
            : $valor;

        if ($formatted === '') {
            return '';
        }

        return sprintf(
            '<div class="flacso-oferta-proximo-inicio"><div class="flacso-oferta-proximo-inicio__pill"><span class="flacso-oferta-proximo-inicio__icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span><span class="flacso-oferta-proximo-inicio__content"><span class="flacso-oferta-proximo-inicio__label">%s</span><span class="flacso-oferta-proximo-inicio__value">%s</span></span></div></div>',
            esc_html__('Próximo inicio', 'flacso-oferta-academica'),
            esc_html($formatted)
        );
    }

    public static function render_dato_calendario($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return self::render_documento_block((array) $attributes, 'calendario');
    }

    public static function render_dato_malla_curricular($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return self::render_documento_block((array) $attributes, 'malla');
    }

    public static function render_documentos_programa(array $attributes = []): string {
        self::ensure_styles();

        $oferta_id = self::resolve_oferta_id($attributes);
        if ($oferta_id <= 0) {
            return '';
        }

        $documentos = self::resolve_documentos_state($oferta_id, []);
        $cards = [];

        if (!empty($documentos['cartamalla']['link']) && self::is_unified_document_selected($documentos['cartamalla'])) {
            $cards[] = self::render_document_card([
                'title' => __('Calendario y Malla Curricular', 'flacso-oferta-academica'),
                'description' => __('Incluye el calendario de cursada y la malla curricular completa.', 'flacso-oferta-academica'),
                'button_label' => __('Ver Documento (PDF)', 'flacso-oferta-academica'),
                'icon' => 'bi-journal-check',
                'url' => $documentos['cartamalla']['link'],
                'updated_at' => $documentos['cartamalla']['fecha'] ?? '',
            ]);
        } else {
            if (!empty($documentos['calendario']['link'])) {
                $cards[] = self::render_document_card([
                    'title' => __('Calendario Académico', 'flacso-oferta-academica'),
                    'description' => __('Fechas clave, módulos e hitos de la cursada.', 'flacso-oferta-academica'),
                    'button_label' => __('Ver Calendario (PDF)', 'flacso-oferta-academica'),
                    'icon' => 'bi-calendar2-check',
                    'url' => $documentos['calendario']['link'],
                    'updated_at' => $documentos['calendario']['fecha'] ?? '',
                ]);
            }

            if (!empty($documentos['malla']['link'])) {
                $cards[] = self::render_document_card([
                    'title' => __('Malla Curricular', 'flacso-oferta-academica'),
                    'description' => __('Programa completo, módulos y asignaturas del posgrado.', 'flacso-oferta-academica'),
                    'button_label' => __('Ver Malla Curricular (PDF)', 'flacso-oferta-academica'),
                    'icon' => 'bi-journal-bookmark',
                    'url' => $documentos['malla']['link'],
                    'updated_at' => $documentos['malla']['fecha'] ?? '',
                ]);
            }
        }

        return implode('', array_filter($cards));
    }

    private static function resolve_oferta_id(array $attributes): int {
        $oferta_id = isset($attributes['ofertaId']) ? (int) $attributes['ofertaId'] : 0;
        if (!$oferta_id && isset($attributes['postId'])) {
            $oferta_id = (int) $attributes['postId'];
        }
        if (!$oferta_id && is_singular('oferta-academica')) {
            $oferta_id = (int) get_the_ID();
        }

        return $oferta_id > 0 ? $oferta_id : 0;
    }

    private static function is_editor_preview_context(): bool {
        if (is_admin()) {
            return true;
        }

        // En el editor de bloques, ServerSideRender usa REST y is_admin() puede ser false.
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

    private static function parse_documentos_meta(int $oferta_id): array {
        $raw = get_post_meta($oferta_id, 'documentos', true);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalize_documento_entry($entry): array {
        if (!is_array($entry)) {
            return [];
        }

        $normalized = [];
        $link = esc_url_raw(trim((string) ($entry['link'] ?? '')));

        if (array_key_exists('enabled', $entry)) {
            $normalized['enabled'] = rest_sanitize_boolean($entry['enabled']);
        }

        if ($link !== '' || array_key_exists('link', $entry)) {
            $normalized['link'] = $link;
        }

        $fecha = trim((string) ($entry['fecha'] ?? ''));
        if ($fecha !== '') {
            $normalized['fecha'] = $fecha;
        }

        if (!empty($entry['historico']) && is_string($entry['historico'])) {
            $normalized['historico'] = $entry['historico'];
        }

        return $normalized;
    }

    private static function is_unified_document_selected(array $entry): bool {
        $link = trim((string) ($entry['link'] ?? ''));
        if (array_key_exists('enabled', $entry) && !$entry['enabled']) {
            return false;
        }

        return (!empty($entry['enabled']) && rest_sanitize_boolean($entry['enabled'])) || $link !== '';
    }

    private static function resolve_documentos_state(int $oferta_id, array $attributes = []): array {
        $documentos = self::parse_documentos_meta($oferta_id);
        $cartamalla = self::normalize_documento_entry($documentos['cartamalla'] ?? []);
        $calendario = self::normalize_documento_entry($documentos['calendario'] ?? []);
        $malla = self::normalize_documento_entry($documentos['malla'] ?? []);
        $legacy_calendario = esc_url_raw(trim((string) get_post_meta($oferta_id, 'calendario', true)));
        $legacy_malla = esc_url_raw(trim((string) get_post_meta($oferta_id, 'malla_curricular', true)));
        $fallback_url = esc_url_raw(trim((string) ($attributes['pdfUrlFallback'] ?? '')));

        if (self::is_unified_document_selected($cartamalla)) {
            if (empty($cartamalla['link']) && $legacy_calendario !== '' && $legacy_calendario === $legacy_malla) {
                $cartamalla['link'] = $legacy_calendario;
                $cartamalla['enabled'] = true;
            }
        } elseif ($legacy_calendario !== '' && $legacy_calendario === $legacy_malla && empty($calendario['link']) && empty($malla['link'])) {
            $cartamalla = array_merge($cartamalla, [
                'link' => $legacy_calendario,
                'enabled' => true,
            ]);
        } else {
            if (empty($calendario['link']) && $legacy_calendario !== '') {
                $calendario['link'] = $legacy_calendario;
            }

            if (empty($malla['link']) && $legacy_malla !== '') {
                $malla['link'] = $legacy_malla;
            }
        }

        if ($fallback_url !== '' && empty($cartamalla['link']) && empty($calendario['link'])) {
            $calendario['link'] = $fallback_url;
        }

        if ($fallback_url !== '' && empty($cartamalla['link']) && empty($malla['link'])) {
            $malla['link'] = $fallback_url;
        }

        return [
            'cartamalla' => $cartamalla,
            'calendario' => $calendario,
            'malla' => $malla,
        ];
    }

    private static function render_documento_block(array $attributes, string $type): string {
        $oferta_id = self::resolve_oferta_id($attributes);
        if ($oferta_id <= 0) {
            return self::is_editor_preview_context()
                ? '<p>' . esc_html__('Selecciona una oferta académica para mostrar este documento.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $display_mode = sanitize_text_field((string) ($attributes['displayMode'] ?? 'auto'));
        if ($display_mode === 'html') {
            return self::is_editor_preview_context()
                ? '<p>' . esc_html__('Este bloque todavía muestra la versión PDF. Para HTML usa el contenido principal de la oferta.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $documentos = self::resolve_documentos_state($oferta_id, $attributes);

        if (!empty($documentos['cartamalla']['link']) && self::is_unified_document_selected($documentos['cartamalla'])) {
            return self::render_document_card([
                'title' => __('Calendario y Malla Curricular', 'flacso-oferta-academica'),
                'description' => __('Incluye el calendario de cursada y la malla curricular completa.', 'flacso-oferta-academica'),
                'button_label' => __('Ver Documento (PDF)', 'flacso-oferta-academica'),
                'icon' => 'bi-journal-check',
                'url' => $documentos['cartamalla']['link'],
                'updated_at' => $documentos['cartamalla']['fecha'] ?? '',
            ]);
        }

        if ($type === 'calendario' && !empty($documentos['calendario']['link'])) {
            return self::render_document_card([
                'title' => __('Calendario Académico', 'flacso-oferta-academica'),
                'description' => __('Fechas clave, módulos e hitos de la cursada.', 'flacso-oferta-academica'),
                'button_label' => __('Ver Calendario (PDF)', 'flacso-oferta-academica'),
                'icon' => 'bi-calendar2-check',
                'url' => $documentos['calendario']['link'],
                'updated_at' => $documentos['calendario']['fecha'] ?? '',
            ]);
        }

        if ($type === 'malla' && !empty($documentos['malla']['link'])) {
            return self::render_document_card([
                'title' => __('Malla Curricular', 'flacso-oferta-academica'),
                'description' => __('Programa completo, módulos y asignaturas del posgrado.', 'flacso-oferta-academica'),
                'button_label' => __('Ver Malla Curricular (PDF)', 'flacso-oferta-academica'),
                'icon' => 'bi-journal-bookmark',
                'url' => $documentos['malla']['link'],
                'updated_at' => $documentos['malla']['fecha'] ?? '',
            ]);
        }

        return self::is_editor_preview_context()
            ? '<p>' . esc_html__('La oferta seleccionada no tiene un PDF disponible para este bloque.', 'flacso-oferta-academica') . '</p>'
            : '';
    }

    private static function render_document_card(array $args): string {
        $url = self::proxy_pdf_url((string) ($args['url'] ?? ''), (string) ($args['title'] ?? ''));
        if ($url === '') {
            return '';
        }

        $updated_markup = self::render_document_updated_label((string) ($args['updated_at'] ?? ''));

        ob_start();
        ?>
        <div class="flacso-oferta-documento-card-wrapper">
            <article class="flacso-oferta-documento-card">
                <div class="flacso-oferta-documento-card__icon">
                    <i class="bi <?php echo esc_attr((string) ($args['icon'] ?? 'bi-file-earmark-pdf')); ?>" aria-hidden="true"></i>
                </div>
                <h3 class="flacso-oferta-documento-card__title"><?php echo esc_html((string) ($args['title'] ?? '')); ?></h3>
                <?php if (!empty($args['description'])) : ?>
                    <p class="flacso-oferta-documento-card__desc"><?php echo esc_html((string) $args['description']); ?></p>
                <?php endif; ?>
                <?php if ($updated_markup !== '') : ?>
                    <?php echo $updated_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <a class="flacso-oferta-documento-card__button" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    <span><?php echo esc_html((string) ($args['button_label'] ?? __('Ver Documento (PDF)', 'flacso-oferta-academica'))); ?></span>
                </a>
            </article>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_document_updated_label(string $raw_date): string {
        $raw_date = trim($raw_date);
        if ($raw_date === '') {
            return '';
        }

        $timestamp = strtotime($raw_date);
        if (!$timestamp) {
            return '';
        }

        return sprintf(
            '<p class="flacso-oferta-documento-card__updated">%s <time datetime="%s">%s</time></p>',
            esc_html__('Última actualización:', 'flacso-oferta-academica'),
            esc_attr(gmdate('c', $timestamp)),
            esc_html(wp_date('d/m/Y H:i', $timestamp, wp_timezone()))
        );
    }

    private static function proxy_pdf_url(string $url, string $label = ''): string {
        $url = esc_url_raw(trim($url));
        if ($url === '') {
            return '';
        }

        if (function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($url, $label !== '' ? $label : __('Documento académico', 'flacso-oferta-academica'));
            if (is_string($proxied) && $proxied !== '') {
                return $proxied;
            }
        }

        return $url;
    }


}
