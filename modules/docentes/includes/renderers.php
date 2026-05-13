<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('dp_docentes_wrap_output')) {
    function dp_docentes_wrap_output(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (function_exists('dp_docentes_enqueue_assets')) {
            dp_docentes_enqueue_assets();
        }
        return '<div class="flacso-docentes-scope">' . $html . '</div>';
    }
}

if (!function_exists('flacso_docentes_register_image_sizes')) {
    function flacso_docentes_register_image_sizes(): void {
        add_theme_support('post-thumbnails');
        add_image_size('docente_square_sm', 120, 120, true);
        add_image_size('docente_square', 168, 168, true);
        add_image_size('docente_square_lg', 200, 200, true);
    }
    add_action('after_setup_theme', 'flacso_docentes_register_image_sizes');
}

if (!function_exists('flacso_docentes_print_global_styles')) {
    function flacso_docentes_print_global_styles(): void {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
        .flacso-docentes-scope .btn-palette2 {
            background: var(--global-palette-btn-bg, var(--global-palette1, #1d3a72));
            border-color: var(--global-palette-btn-bg, var(--global-palette1, #1d3a72));
            color: var(--global-palette-btn, #ffffff);
        }
        .flacso-docentes-scope .btn-palette2:hover {
            background: var(--global-palette-btn-bg-hover, var(--global-palette1, #1d3a72));
            border-color: var(--global-palette-btn-bg-hover, var(--global-palette1, #1d3a72));
            color: var(--global-palette-btn, #ffffff);
        }
        .flacso-docentes-scope .btn-palette2:focus-visible {
            outline: 3px solid #000;
            outline-offset: 2px;
        }
        .flacso-docentes-scope .hover-lift {
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .flacso-docentes-scope .hover-lift:hover {
            transform: translateY(-3px);
            box-shadow: 0 .75rem 1.5rem rgba(0,0,0,.08)!important;
        }
        @media (prefers-reduced-motion: reduce) {
            .flacso-docentes-scope .hover-lift,
            .flacso-docentes-scope .docente-destacado {
                transition: none;
            }
        }
        .flacso-docentes-scope .docente-avatar {
            width: var(--doc-avatar, 168px);
            height: var(--doc-avatar, 168px);
            aspect-ratio: 1/1;
            object-fit: cover;
        }
        .flacso-docentes-scope .docentes-lista-completa {
            display: grid;
            gap: 1.35rem;
        }
        .flacso-docentes-scope .docentes-lista-card {
            border: 1px solid color-mix(in srgb, var(--global-palette1, #1d3a72) 12%, transparent);
            border-radius: 1.35rem;
            background:
                radial-gradient(180px 180px at 95% -5%, color-mix(in srgb, var(--global-palette2, #fed222) 24%, transparent), transparent 72%),
                var(--global-palette9, #ffffff);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.11);
            overflow: hidden;
            margin-bottom: 0;
        }
        .flacso-docentes-scope .docentes-lista-card .card-body {
            padding: clamp(1.1rem, 2vw, 1.6rem);
        }
        .flacso-docentes-scope .docentes-lista-card__top {
            margin-bottom: 0.75rem;
            padding-bottom: 0.7rem;
            border-bottom: 1px solid color-mix(in srgb, var(--global-palette1, #1d3a72) 10%, transparent);
        }
        .flacso-docentes-scope .docentes-lista-card__name {
            margin: 0;
            color: var(--global-palette1, #1d3a72);
            font-size: clamp(1.5rem, 2vw, 2rem);
            line-height: 1.15;
            text-wrap: balance;
        }
        .flacso-docentes-scope .docentes-lista-card__prefix {
            margin: 0.35rem 0 0;
            color: var(--global-palette5, #7a8696);
            font-size: 0.9rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .flacso-docentes-scope .docentes-lista-card__cv {
            color: var(--global-palette4, #2e2f34);
            font-size: 1.02rem;
            line-height: 1.72;
        }
        .flacso-docentes-scope .docentes-lista-card__cv > :last-child {
            margin-bottom: 0;
        }
        .flacso-docentes-scope .docentes-lista-card__view {
            border-radius: 999px;
            padding-inline: 0.8rem;
        }
        .flacso-docentes-scope .docentes-lista-card__edit {
            border-radius: 999px;
        }
        .flacso-docentes-scope .doc-grid .card {
            border: 0;
            background: var(--global-palette9,#fff);
            color: var(--global-palette4,#333);
            border-radius: 1rem;
        }
        .flacso-docentes-scope .doc-grid .card-body {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 1rem;
        }
        @media (min-width: 576px) {
            .flacso-docentes-scope .doc-grid .card-body { padding: 1.25rem; }
        }
        .flacso-docentes-scope .doc-name {
            color: var(--global-palette1,#1d3a72);
            font-family: var(--global-heading-font-family, inherit);
            line-height: 1.25;
            margin: .5rem 0 .25rem;
        }
        .flacso-docentes-scope .doc-role { color: var(--global-palette5,#7a8696); }
        .flacso-docentes-scope .doc-card:focus-within {
            outline: 3px solid color-mix(in srgb, var(--global-palette1, #1d3a72) 60%, transparent);
            outline-offset: 2px;
            border-radius: 1rem;
        }
        .flacso-docentes-scope .flacso-docente {
            background-color: var(--global-palette9,#fff);
            color: var(--global-palette4,#333);
            border-radius: 1rem;
        }
        .flacso-docentes-scope .flacso-docente .docente-nombre {
            color: var(--global-palette1,#1d3a72);
            font-family: var(--global-heading-font-family, inherit);
            line-height: 1.25;
        }
        .flacso-docentes-scope .flacso-docente .flacso-docente-cv p {
            margin-bottom: .8rem;
            color: var(--global-palette4,#333);
        }
        .flacso-docentes-scope a.btn,
        .flacso-docentes-scope .btn {
            min-height: 40px;
        }
        @media (max-width: 576px) {
            .flacso-docentes-scope .docente-avatar { --doc-avatar: 120px; }
            .flacso-docentes-scope .docentes-lista-card .row {
                text-align: center;
            }
            .flacso-docentes-scope .docentes-lista-card__top {
                flex-direction: column;
                align-items: center !important;
            }
            .flacso-docentes-scope .docentes-lista-card__name {
                font-size: 1.5rem;
            }
        }
        </style>
        <?php
    }
    add_action('wp_head', 'flacso_docentes_print_global_styles');
}

if (!function_exists('dp_docentes_lista_bloques')) {
function dp_docentes_lista_bloques($atts = [], $block = null) {
    $atts = wp_parse_args($atts, [
        'slug'           => '',
        'termId'         => 0,
        'useCurrentPage' => false,
        'pageId'         => 0,
    ]);

    $slug = sanitize_title($atts['slug']);
    $term_ids = [];

    if (!empty($atts['termId'])) {
        $term_obj = get_term((int) $atts['termId'], 'equipo-docente');
        if ($term_obj && !is_wp_error($term_obj)) {
            $term_ids = [$term_obj->term_id];
        }
    }

    if (!$term_ids && $slug) {
        $term_obj = get_term_by('slug', $slug, 'equipo-docente');
        if ($term_obj && !is_wp_error($term_obj)) {
            $term_ids = [$term_obj->term_id];
        } else {
            $message = "<p class='alert alert-danger' role='alert'>Equipo '" . esc_html($slug) . "' no encontrado.</p>";
            return dp_docentes_wrap_output($message);
        }
    }

    if (!$term_ids && !empty($atts['useCurrentPage'])) {
        $page_id = (int) $atts['pageId'];
        if (!$page_id && $block instanceof WP_Block && !empty($block->context['postId'])) {
            $page_id = (int) $block->context['postId'];
        }
        if (!$page_id && function_exists('get_the_ID')) {
            $page_id = (int) get_the_ID();
        }
        if ($page_id && function_exists('dp_get_equipo_term_ids_by_page')) {
            $term_ids = dp_get_equipo_term_ids_by_page($page_id);
        }
    }

    $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids))));

    if (!$term_ids) {
        return dp_docentes_wrap_output('<p class="alert alert-warning" role="status">Falta el equipo academico asociado.</p>');
    }

    $sections = [];
    $multiple = count($term_ids) > 1;

    foreach ($term_ids as $term_id) {
        $term = get_term($term_id, 'equipo-docente');
        if (!$term || is_wp_error($term)) {
            continue;
        }

        $term_label = function_exists('dp_get_equipo_relacion_nombre')
            ? dp_get_equipo_relacion_nombre($term_id, $term->name)
            : $term->name;

        $q = new WP_Query([
            'post_type'      => 'docente',
            'posts_per_page' => -1,
            'meta_key'       => 'apellido',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'tax_query'      => [[
                'taxonomy' => 'equipo-docente',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ]],
            'no_found_rows' => true,
        ]);

        if (!$q->have_posts()) {
            if (!$multiple) {
                return dp_docentes_wrap_output("<p class='alert alert-info' role='status'>No hay integrantes en este equipo.</p>");
            }
            continue;
        }

        $admin_top = '';
        if (is_user_logged_in() && (current_user_can('edit_others_posts') || current_user_can('manage_categories'))) {
            $edit_term = get_edit_term_link($term->term_id, 'equipo-docente');
            $list_doc  = admin_url('edit.php?post_type=docente&equipo-docente=' . rawurlencode($term->slug));
            $new_doc   = admin_url('post-new.php?post_type=docente');
            $admin_top .= '<div class="d-flex flex-wrap gap-2 justify-content-end mb-3 d-print-none" aria-label="Acciones del equipo academico">';
            if ($edit_term) {
                $admin_top .= '<a class="btn btn-sm btn-palette2" target="_blank" rel="noopener" href="' . esc_url($edit_term) . '"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i><span aria-hidden="true">Editar equipo</span></a>';
            }
            $admin_top .= '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' . esc_url($list_doc) . '"><i class="bi bi-people me-1" aria-hidden="true"></i><span aria-hidden="true">Integrantes de este equipo</span></a>';
            $admin_top .= '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' . esc_url($new_doc) . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span aria-hidden="true">Nuevo perfil</span></a>';
            $admin_top .= '</div>';
        }

        ob_start();
        if ($multiple) {
            echo '<div class="mb-4">';
            if ($term_label) {
                echo '<h2 class="h4 mb-3">' . esc_html($term_label) . '</h2>';
            }
        }
        echo $admin_top;
        echo '<div class="docentes-lista-completa" role="list" aria-label="' . esc_attr(sprintf(__('Integrantes del equipo %s', 'flacso-posgrados-docentes'), $term_label ?: $term->name)) . '">';
        $i = 0;
        while ($q->have_posts()) {
            $q->the_post();
            $i++;
            $id     = get_the_ID();
            $titulo = dp_nombre_completo($id);
            $pref_abrev = get_post_meta($id, 'prefijo_abrev', true);
            $titulo_academico = get_post_meta($id, 'titulo_academico', true);
            $pref       = $pref_abrev ?: $titulo_academico;
            $nombre     = get_post_meta($id, 'nombre', true);
            $apellido   = get_post_meta($id, 'apellido', true);
            $display_name = trim(($nombre ?: '') . ' ' . ($apellido ?: ''));
            if ($display_name === '') {
                $display_name = $titulo;
            }
            if (function_exists('dp_strip_prefix_from_name')) {
                $display_name = dp_strip_prefix_from_name($display_name, $pref_abrev);
            }
            $heading_name = trim(($pref_abrev ? $pref_abrev . ' ' : '') . $display_name);
            if ($heading_name === '') {
                $heading_name = $display_name;
            }
            $cv_raw = get_post_meta($id, 'cv', true);
            $img_col_order  = ($i % 2 === 0) ? 'order-md-2' : 'order-md-1';
            $text_col_order = ($i % 2 === 0) ? 'order-md-1' : 'order-md-2';
            $h_id  = 'doc-list-h-' . $id;
            $cv_id = 'doc-list-cv-' . $id;
            ?>
            <article class="card border-0 shadow-sm mb-5 hover-lift docentes-lista-card" role="listitem" aria-labelledby="<?php echo esc_attr($h_id); ?>" aria-describedby="<?php echo esc_attr($cv_id); ?>">
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-3 text-center <?php echo esc_attr($img_col_order); ?>">
                            <?php echo dp_avatar_markup($id, $display_name, 190, 'shadow-lg border border-2 border-white'); ?>
                        </div>
                        <div class="col-md-9 <?php echo esc_attr($text_col_order); ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2 docentes-lista-card__top">
                                <div>
                                    <h3 id="<?php echo esc_attr($h_id); ?>" class="mb-1 docentes-lista-card__name"><?php echo esc_html($heading_name); ?></h3>
                                    <?php if ($titulo_academico): ?>
                                        <p class="text-muted small mb-0 docentes-lista-card__prefix"><?php echo esc_html($titulo_academico); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo esc_url(get_permalink($id)); ?>" class="btn btn-outline-secondary btn-sm docentes-lista-card__view" aria-label="<?php echo esc_attr(sprintf(__('Ver perfil de %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                        <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden"><?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?></span>
                                    </a>
                                    <?php if (current_user_can('edit_post', $id)): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($id, '')); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-palette2 d-print-none docentes-lista-card__edit" aria-label="<?php echo esc_attr(sprintf(__('Editar docente: %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i><span aria-hidden="true"><?php esc_html_e('Editar docente', 'flacso-posgrados-docentes'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($cv_raw): ?>
                                <div id="<?php echo esc_attr($cv_id); ?>" class="cv-completo docentes-lista-card__cv" style="line-height:1.65">
                                    <?php
                                        $cv_html = (strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw;
                                        echo dp_safe_cv_html($cv_html);
                                    ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted"><em><?php esc_html_e('No hay información curricular disponible.', 'flacso-posgrados-docentes'); ?></em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
        if ($multiple) {
            echo '</div>';
        }
        $sections[] = ob_get_clean();
    }

    if (!$sections) {
        return dp_docentes_wrap_output("<p class='alert alert-info' role='status'>No hay integrantes en este equipo.</p>");
    }

    return dp_docentes_wrap_output(implode('', $sections));
}
}

if (!function_exists('dp_cv_docente_texto')) {
function dp_cv_docente_texto($atts = []) {
    $atts = wp_parse_args($atts, [
        'slug'  => '',
        'docId' => 0,
    ]);

    $doc_id = absint($atts['docId']);
    if (!$doc_id && !empty($atts['slug'])) {
        $doc = get_page_by_path($atts['slug'], OBJECT, 'docente');
        if ($doc) {
            $doc_id = $doc->ID;
        }
    }

    if (!$doc_id) {
        return __('⚠️ Selecciona un perfil en el bloque.', 'flacso-posgrados-docentes');
    }

    $post = get_post($doc_id);
    if (!$post || $post->post_type !== 'docente') {
        return __('❌ El perfil seleccionado no existe.', 'flacso-posgrados-docentes');
    }

    $cv = (string) get_post_meta($doc_id, 'cv', true);
    if (!$cv) {
        return __('ℹ️ No hay CV disponible para este perfil.', 'flacso-posgrados-docentes');
    }

    return esc_html(wp_strip_all_tags($cv));
}
}

if (!function_exists('dp_docente_destacado')) {
function dp_docente_destacado($atts = []) {
    $atts = wp_parse_args($atts, [
        'slug'  => '',
        'docId' => 0,
        'rol'   => '',
        'role'  => '',
    ]);

    // Compatibilidad: algunos bloques guardan "role" y otros "rol".
    $rol = '';
    if (!empty($atts['rol'])) {
        $rol = sanitize_text_field((string) $atts['rol']);
    } elseif (!empty($atts['role'])) {
        $rol = sanitize_text_field((string) $atts['role']);
    }

    $doc_id = absint($atts['docId']);
    if (!$doc_id && !empty($atts['slug'])) {
        $doc = get_page_by_path($atts['slug'], OBJECT, 'docente');
        if ($doc) {
            $doc_id = $doc->ID;
        }
    }

function dp_docente_destacado($attributes = []) {
    $rol = isset($attributes['rol']) ? (string) $attributes['rol'] : '';
    $doc_id = isset($attributes['docId']) ? (int) $attributes['docId'] : 0;

    if (!$doc_id) {
        return '<div class="alert alert-info docente-destacado-placeholder">Selecciona un docente para destacar.</div>';
    }

    $post = get_post($doc_id);
    if (!$post || $post->post_type !== 'docente') {
        return '<p class="alert alert-danger" role="alert">❌ El perfil seleccionado no existe.</p>';
    }

    $titulo = dp_nombre_completo($doc_id);

    $pref = '';
    foreach (['titulo_academico', 'prefijo', 'prefijo_abrev'] as $meta_key) {
        $meta_val = trim((string) get_post_meta($doc_id, $meta_key, true));
        if ($meta_val !== '') {
            $pref = $meta_val;
            break;
        }
    }
    $cv_raw = (string) get_post_meta($doc_id, 'cv', true);

    $admin = '';
    if (current_user_can('edit_post', $doc_id)) {
        $admin = '<a class="docente-destacado__edit-btn d-print-none" target="_blank" rel="noopener" href="' . esc_url(get_edit_post_link($doc_id, '')) . '" title="Editar Docente">
            <i class="bi bi-pencil" aria-hidden="true"></i>
        </a>';
    }

    ob_start();
    ?>
    <section class="docente-destacado-v2" aria-labelledby="dest-<?php echo esc_attr($doc_id); ?>">
        <?php if ($admin): ?>
            <?php echo $admin; ?>
        <?php endif; ?>
        
        <div class="dd-container">
            <div class="dd-header">
                <div class="dd-avatar-column">
                    <div class="dd-avatar-frame">
                        <?php echo dp_avatar_markup($doc_id, $titulo, 240, 'dd-img'); ?>
                    </div>
                </div>
                <div class="dd-info-column">
                    <?php if ($rol): ?>
                        <span class="dd-kicker"><?php echo esc_html($rol); ?></span>
                    <?php endif; ?>
                    
                    <h2 id="dest-<?php echo esc_attr($doc_id); ?>" class="dd-name"><?php echo esc_html($titulo); ?></h2>
                    
                    <?php if ($pref): ?>
                        <p class="dd-academic-title"><?php echo esc_html($pref); ?></p>
                    <?php endif; ?>

                    <?php if ($cv_raw): ?>
                        <div class="dd-bio">
                            <?php
                                $cv_html = (strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw;
                                echo dp_safe_cv_html(wp_trim_words($cv_html, 45));
                            ?>
                            <a href="<?php echo esc_url(get_permalink($doc_id)); ?>" class="dd-link">Ver perfil completo →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <style>
    .docente-destacado-v2 {
        --dd-p1: var(--global-palette1, #1d3a72);
        --dd-accent: var(--global-palette2, #fed222);
        --dd-text: var(--global-palette4, #1f2937);
        --dd-muted: #64748b;
        --dd-bg: #ffffff;
        --dd-radius: 24px;
        
        position: relative;
        background: var(--dd-bg);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: var(--dd-radius);
        padding: 3rem;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.05);
        margin: 2rem 0;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    /* Ayuda para el editor: área de clic clara */
    .is-selected .docente-destacado-v2 {
        outline: 3px solid var(--dd-p1);
        outline-offset: 4px;
    }

    .docente-destacado-v2:hover {
        transform: translateY(-4px);
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.1);
        border-color: var(--dd-p1);
    }

    .dd-container { position: relative; z-index: 2; }

    .dd-header {
        display: flex;
        gap: 3rem;
        align-items: center;
    }

    .dd-avatar-column { flex-shrink: 0; }
    
    .dd-avatar-frame {
        width: 180px;
        height: 180px;
        border-radius: 30px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        background: var(--dd-bg);
    }
    
    .dd-img { width: 100%; height: 100%; object-fit: cover; }

    .dd-info-column { flex-grow: 1; }

    .dd-kicker {
        display: inline-block;
        background: var(--dd-accent);
        color: var(--dd-p1);
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        padding: 0.35rem 0.85rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        letter-spacing: 0.05em;
    }

    .dd-name {
        margin: 0 0 0.5rem !important;
        font-size: 2.2rem;
        font-weight: 850;
        color: var(--dd-p1);
        line-height: 1.1;
        letter-spacing: -0.02em;
    }

    .dd-academic-title {
        font-size: 1.1rem;
        color: var(--dd-muted);
        font-weight: 600;
        margin-bottom: 1.5rem;
    }

    .dd-bio {
        font-size: 1.05rem;
        line-height: 1.7;
        color: var(--dd-text);
        border-left: 3px solid var(--dd-accent);
        padding-left: 1.5rem;
    }
    
    .dd-link {
        display: inline-block;
        margin-top: 1rem;
        font-weight: 700;
        color: var(--dd-p1);
        text-decoration: none;
        font-size: 0.9rem;
    }
    .dd-link:hover { text-decoration: underline; }

    .docente-destacado__edit-btn {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        width: 36px;
        height: 36px;
        background: var(--dd-bg);
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--dd-muted);
        text-decoration: none;
        transition: all 0.2s ease;
        z-index: 10;
    }
    .docente-destacado__edit-btn:hover {
        background: var(--dd-p1);
        color: #fff;
        transform: scale(1.1);
    }

    @media (max-width: 860px) {
        .dd-header { flex-direction: column; text-align: center; gap: 2rem; }
        .dd-bio { border-left: none; border-top: 3px solid var(--dd-accent); padding-left: 0; padding-top: 1.5rem; }
        .docente-destacado-v2 { padding: 2rem; }
    }
    </style>
    <?php
    return ob_get_clean();
}
}

if (!function_exists('flacso_render_docente_profile')) {
function flacso_render_docente_profile($atts = []) {
    $atts = wp_parse_args($atts, [
        'slug'    => '',
        'docId'   => 0,
        'heading' => 'h3',
        'showAvatar' => true,
    ]);

    $doc_id = absint($atts['docId']);
    if (!$doc_id && !empty($atts['slug'])) {
        $docente = get_page_by_path($atts['slug'], OBJECT, 'docente');
        if ($docente) {
            $doc_id = $docente->ID;
        }
    }

    if (!$doc_id) {
        return '<div class="alert alert-warning" role="status"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . esc_html__('Selecciona un perfil en el bloque.', 'flacso-posgrados-docentes') . '</div>';
    }

    $doc = get_post($doc_id);
    if (!$doc || $doc->post_type !== 'docente') {
        return '<div class="alert alert-danger" role="alert"><i class="bi bi-x-circle-fill me-2" aria-hidden="true"></i>' . esc_html__('No se encontró el perfil especificado.', 'flacso-posgrados-docentes') . '</div>';
    }

    $heading = strtolower((string) $atts['heading']);
    if (!in_array($heading, ['h1','h2','h3','h4','h5','h6'], true)) {
        $heading = 'h3';
    }

    $meta = get_post_meta($doc_id);
    $prefijo_abrev = !empty($meta['prefijo_abrev'][0]) ? trim((string) $meta['prefijo_abrev'][0]) : '';
    $titulo_academico = !empty($meta['titulo_academico'][0]) ? trim((string) $meta['titulo_academico'][0]) : '';
    $nombre_meta   = !empty($meta['nombre'][0]) ? trim((string) $meta['nombre'][0]) : '';
    $apellido_meta = !empty($meta['apellido'][0]) ? trim((string) $meta['apellido'][0]) : '';

    if (function_exists('dp_strip_prefix_from_name')) {
        $nombre_meta = dp_strip_prefix_from_name($nombre_meta, $prefijo_abrev);
    }

    $nombre_base = trim($nombre_meta . ' ' . $apellido_meta);
    if ($nombre_base === '') {
        $nombre_base = dp_nombre_completo($doc_id);
        if (function_exists('dp_strip_prefix_from_name')) {
            $nombre_base = dp_strip_prefix_from_name($nombre_base, $prefijo_abrev);
        }
    }

    $heading_name = trim(($prefijo_abrev ? $prefijo_abrev . ' ' : '') . $nombre_base);
    if ($heading_name === '') {
        $heading_name = get_the_title($doc_id);
    }

    $cv_raw     = isset($meta['cv'][0]) ? (string) $meta['cv'][0] : '';
    $cv_html    = $cv_raw !== '' ? dp_safe_cv_html((strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw) : '<em>' . esc_html__('CV no disponible', 'flacso-posgrados-docentes') . '</em>';
    $avatar_html = dp_avatar_markup($doc_id, $heading_name, 168, 'mx-sm-0 mx-auto');
    $heading_id = 'docente-nombre-' . $doc_id;

    ob_start(); ?>
    <article class="flacso-docente card border-0 shadow-sm my-4" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
      <div class="card-body py-4 px-3 px-md-4">
        <div class="row align-items-center gy-4 gx-4">
          <?php if ($avatar_html): ?>
          <div class="col-12 col-sm-4 col-md-3 text-sm-start text-center">
            <?php echo $avatar_html; ?>
          </div>
          <?php endif; ?>
          <div class="col">
                        <<?php echo $heading; ?> id="<?php echo esc_attr($heading_id); ?>" class="fw-bold mb-3 docente-nombre">
                                                        <?php echo esc_html($heading_name); ?>
                        </<?php echo $heading; ?>>
                        <?php if ($titulo_academico !== ''): ?>
                            <p class="text-muted small mb-3"><?php echo esc_html($titulo_academico); ?></p>
                        <?php endif; ?>
            <div class="flacso-docente-cv">
              <?php echo $cv_html; ?>
            </div>
            <?php if (current_user_can('edit_post', $doc_id)): ?>
              <div class="mt-3 d-print-none">
                <a class="btn btn-sm btn-palette2" href="<?php echo esc_url(get_edit_post_link($doc_id, '')); ?>" target="_blank" rel="noopener"
                                     aria-label="<?php echo esc_attr(sprintf(__('Editar ficha de %s', 'flacso-posgrados-docentes'), $heading_name)); ?>">
                  <i class="bi bi-pencil me-1" aria-hidden="true"></i><span aria-hidden="true"><?php esc_html_e('Editar', 'flacso-posgrados-docentes'); ?></span>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </article>
    <?php
    return ob_get_clean();
}
}
