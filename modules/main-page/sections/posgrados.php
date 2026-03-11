<?php
// ==================================================
// SECCIÓN NUESTROS POSGRADOS - SISTEMA UNIFICADO FLACSO
// ==================================================

if (!function_exists('flacso_section_posgrados_render')) {
function flacso_section_posgrados_render($atts = []) {
    if (!class_exists('Flacso_Main_Page_Settings')) {
        return '<p style="color:red;">Error: Flacso_Main_Page_Settings no encontrado</p>';
    }
    
    $settings = Flacso_Main_Page_Settings::get_section('posgrados');

    $atts = shortcode_atts(
        [
            'mostrar_titulo' => null,
        ],
        $atts,
        'nuestros_posgrados'
    );

    $show_title = isset($atts['mostrar_titulo']) && $atts['mostrar_titulo'] !== null
        ? filter_var($atts['mostrar_titulo'], FILTER_VALIDATE_BOOLEAN)
        : !empty($settings['show_title']);

    $title = esc_html($settings['title'] ?? '');
    $intro = wp_kses_post($settings['intro'] ?? '');
    $cards = is_array($settings['cards']) ? $settings['cards'] : [];
    
    // Si no hay tarjetas, mostrar mensaje de debug
    if (empty($cards)) {
        error_log('FLACSO DEBUG - No cards found in posgrados settings');
    }

    ob_start();
    ?>
    <style>
    .nuestros-posgrados {
        padding: 80px 0;
        background: linear-gradient(135deg, var(--global-palette8, #f2f6ff) 0%, var(--global-palette9, #ffffff) 100%);
        font-family: var(--global-body-font-family, "Helvetica Neue", sans-serif);
        position: relative;
        overflow: hidden;
    }

    .nuestros-posgrados::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 15% 25%, rgba(30,61,115,0.05) 0%, transparent 35%),
            radial-gradient(circle at 85% 75%, rgba(254,210,34,0.05) 0%, transparent 35%);
        animation: flacso-float 16s ease-in-out infinite alternate;
    }

    .posgrados-container {
        position: relative;
    }

    .posgrados-titulo {
        text-align: center;
        color: var(--global-palette1, #1d3a72);
        font-family: var(--global-heading-font-family, "Helvetica Neue", sans-serif);
        font-weight: 900;
        font-size: 2.6rem;
        margin-bottom: 30px;
        letter-spacing: -0.5px;
    }

    .posgrados-descripcion {
        text-align: center;
        color: var(--global-palette4, #1f2933);
        font-size: 1.1rem;
        line-height: 1.7;
        max-width: var(--flacso-section-max-width);
        margin: 0 auto 60px;
    }

    .posgrados-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    @media (min-width: 576px) {
        .posgrados-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (min-width: 1024px) {
        .posgrados-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .posgrado-item {
        background: var(--global-palette9, #ffffff);
        border: 1px solid #f0f1f3;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
        touch-action: manipulation;
    }

    .posgrado-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .posgrado-imagen {
        width: 100%;
        aspect-ratio: 1/1;
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        background-color: #f8f9fa;
        position: relative;
        overflow: hidden;
    }

    .posgrado-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: var(--global-palette2, #f7b733);
        color: var(--global-palette3, #0f1a2d);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .posgrado-contenido {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 24px;
    }

    .posgrado-titulo-card {
        font-family: var(--global-heading-font-family, "Helvetica Neue", sans-serif);
        color: var(--global-palette3, #0f1a2d);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 10px;
        line-height: 1.3;
    }

    .posgrado-descripcion-card {
        color: var(--global-palette4, #6b7280);
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 15px;
        flex-grow: 1;
    }

    @media (max-width: 768px) {
        .nuestros-posgrados { padding: 60px 0; }
        .posgrados-titulo { font-size: 2rem; }
        .posgrado-imagen { height: 160px; }
        .posgrado-contenido { padding: 16px; }
    }
    </style>

    <section class="nuestros-posgrados">
        <div class="posgrados-container flacso-content-shell">
            <?php if ($show_title): ?>
                <h2 class="posgrados-titulo"><?php echo $title; ?></h2>
            <?php endif; ?>

            <div class="posgrados-descripcion">
                <?php echo $intro; ?>
            </div>

            <div class="posgrados-grid">
                <?php 
                if (empty($cards)): 
                    echo '<p style="grid-column: 1/-1; text-align:center; color:#999;">No hay tarjetas configuradas</p>';
                else:
                    foreach ($cards as $index => $card):
                    $card_title = esc_html($card['titulo'] ?? $card['title'] ?? '');
                    $card_type = esc_html($card['tipo'] ?? $card['type'] ?? '');
                    $card_url_raw = $card['url'] ?? '';
                    $card_url = Flacso_Main_Page_Settings::normalize_url_output($card_url_raw);
                    $card_image = esc_url($card['img'] ?? $card['image'] ?? '');
                    $card_desc = wp_kses_post($card['desc'] ?? '');
                    $card_desc_plain = trim(wp_strip_all_tags($card['desc'] ?? ''));
                    $card_id = 'posgrado-card-' . $index;
                    $desc_id = $card_id . '-description';
                    $has_description = ($card_desc_plain !== '') || ($card_type !== '');
                    $tag = $card_url ? 'a' : 'article';
                    $card_classes = ['posgrado-item'];
                    if ($card_url) {
                        $card_classes[] = 'posgrado-item--action';
                    }
                    
                    if (!$card_title && !$card_desc) {
                        continue;
                    }
                ?>
                    <<?php echo $tag; ?>
                        class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
                        <?php if ($card_url): ?>
                            href="<?php echo esc_url($card_url); ?>"
                        <?php endif; ?>
                        aria-labelledby="<?php echo esc_attr($card_id); ?>"
                        <?php if ($has_description): ?>
                            aria-describedby="<?php echo esc_attr($desc_id); ?>"
                        <?php endif; ?>
                        <?php if (!$card_url): ?>
                            role="group"
                        <?php endif; ?>
                    >
                        <?php if ($card_image): ?>
                            <div class="posgrado-imagen" style="background-image: url('<?php echo $card_image; ?>');">
                                <?php if ($card_type): ?>
                                    <span class="posgrado-badge"><?php echo $card_type; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="posgrado-contenido">
                            <h3 class="posgrado-titulo-card" id="<?php echo esc_attr($card_id); ?>"><?php echo $card_title; ?></h3>
                            <?php if ($card_desc): ?>
                                <p class="posgrado-descripcion-card" id="<?php echo esc_attr($desc_id); ?>"><?php echo $card_desc; ?></p>
                            <?php elseif ($card_type): ?>
                                <p class="posgrado-descripcion-card" id="<?php echo esc_attr($desc_id); ?>"><?php echo $card_type; ?></p>
                            <?php endif; ?>
                            <?php if ($card_url): ?>
                                <span class="visually-hidden"><?php esc_html_e('Toca para abrir la información del posgrado.', 'flacso-main-page'); ?></span>
                            <?php endif; ?>
                        </div>
                    </<?php echo $tag; ?>>
                <?php 
                    endforeach;
                endif; 
                ?>
            </div>
        </div>
    </section>
    <?php
    $output = ob_get_clean();
    
    return $output;
}
}

// Registrar el shortcode
add_shortcode('nuestros_posgrados', 'flacso_section_posgrados_render');

// Hook para asegurar que la configuración de posgrados esté inicializada
add_action('wp_loaded', function() {
    if (!class_exists('Flacso_Main_Page_Settings')) {
        return;
    }
    
    // Obtén configuración actual
    $current = get_option('flacso-main-page_settings', []);
    
    // Si posgrados está vacío, inicialízalo con los defaults
    if (empty($current['posgrados'])) {
        $defaults = Flacso_Main_Page_Settings::get_defaults();
        if (isset($defaults['posgrados'])) {
            $current['posgrados'] = $defaults['posgrados'];
            $sanitized = Flacso_Main_Page_Settings::sanitize($current);
            update_option('flacso-main-page_settings', $sanitized);
            error_log('FLACSO: Posgrados section initialized with defaults');
        }
    }
}, 5);

// Shortcode de prueba/debug
add_shortcode('nuestros_posgrados_debug', function() {
    if (!class_exists('Flacso_Main_Page_Settings')) {
        return '<p>ERROR: Flacso_Main_Page_Settings no disponible</p>';
    }
    $settings = Flacso_Main_Page_Settings::get_section('posgrados');
    $html = '<div style="background:#fff; border:2px solid #f00; padding:20px; margin:20px 0;">';
    $html .= '<h3 style="color:#f00;">DEBUG - Posgrados Settings</h3>';
    $html .= '<p><strong>Título:</strong> ' . htmlspecialchars($settings['title'] ?? 'N/A') . '</p>';
    $html .= '<p><strong>Mostrar título:</strong> ' . ($settings['show_title'] ? 'Sí' : 'No') . '</p>';
    $html .= '<p><strong>Tarjetas:</strong> ' . (isset($settings['cards']) ? count($settings['cards']) : '0') . '</p>';
    if (isset($settings['cards']) && !empty($settings['cards'])) {
        $html .= '<ul>';
        foreach ($settings['cards'] as $card) {
            $html .= '<li>' . htmlspecialchars($card['title'] ?? 'Sin título') . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</div>';
    return $html;
});

