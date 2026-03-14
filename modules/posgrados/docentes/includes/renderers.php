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
            $pref_full  = get_post_meta($id, 'prefijo_full', true);
            $pref       = $pref_abrev ?: $pref_full;
            $nombre     = get_post_meta($id, 'nombre', true);
            $apellido   = get_post_meta($id, 'apellido', true);
            $display_name = trim(($nombre ?: '') . ' ' . ($apellido ?: ''));
            if ($display_name === '') {
                $display_name = $titulo;
            }
            $cv_raw = get_post_meta($id, 'cv', true);
            $img_col_order  = ($i % 2 === 0) ? 'order-md-2' : 'order-md-1';
            $text_col_order = ($i % 2 === 0) ? 'order-md-1' : 'order-md-2';
            $h_id  = 'doc-list-h-' . $id;
            $cv_id = 'doc-list-cv-' . $id;
            ?>
            <article class="card border-0 shadow-sm mb-5 hover-lift" role="listitem" aria-labelledby="<?php echo esc_attr($h_id); ?>" aria-describedby="<?php echo esc_attr($cv_id); ?>">
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-3 text-center <?php echo esc_attr($img_col_order); ?>">
                            <?php echo dp_avatar_markup($id, $display_name, 190, 'shadow-lg border border-2 border-white'); ?>
                        </div>
                        <div class="col-md-9 <?php echo esc_attr($text_col_order); ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <h3 id="<?php echo esc_attr($h_id); ?>" class="mb-1"><?php echo esc_html($display_name); ?></h3>
                                    <?php if ($pref): ?>
                                        <p class="text-muted small mb-0"><?php echo esc_html($pref); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo esc_url(get_permalink($id)); ?>" class="btn btn-outline-secondary btn-sm" aria-label="<?php echo esc_attr(sprintf(__('Ver perfil de %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                        <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden"><?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?></span>
                                    </a>
                                    <?php if (current_user_can('edit_post', $id)): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($id, '')); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-palette2 d-print-none" aria-label="<?php echo esc_attr(sprintf(__('Editar docente: %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i><span aria-hidden="true"><?php esc_html_e('Editar docente', 'flacso-posgrados-docentes'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($cv_raw): ?>
                                <div id="<?php echo esc_attr($cv_id); ?>" class="cv-completo" style="line-height:1.65">
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

    if (!$doc_id) {
        return '<p class="alert alert-warning" role="status">⚠️ Selecciona un perfil en el bloque.</p>';
    }

    if ($rol === '') {
        return '<p class="alert alert-warning" role="status">⚠️ Falta el rol del perfil destacado.</p>';
    }

    $post = get_post($doc_id);
    if (!$post || $post->post_type !== 'docente') {
        return '<p class="alert alert-danger" role="alert">❌ El perfil seleccionado no existe.</p>';
    }

    $titulo = dp_nombre_completo($doc_id);

    $pref = '';
    foreach (['prefijo_full', 'titulo', 'prefijo', 'prefijo_abrev'] as $meta_key) {
        $meta_val = trim((string) get_post_meta($doc_id, $meta_key, true));
        if ($meta_val !== '') {
            $pref = $meta_val;
            break;
        }
    }
    $cv_raw = (string) get_post_meta($doc_id, 'cv', true);

    $admin = '';
    if (current_user_can('edit_post', $doc_id)) {
        $admin = '<a class="btn btn-sm btn-palette2 docente-destacado__edit d-print-none" target="_blank" rel="noopener" href="' . esc_url(get_edit_post_link($doc_id, '')) . '">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i><span aria-hidden="true">' . esc_html__('Editar', 'flacso-posgrados-docentes') . '</span>
        </a>';
    }

    static $styles_printed = false;
    $should_print_styles = !$styles_printed;
    $styles_printed = true;

    ob_start();
    ?>
    <section class="card border-0 shadow-sm docente-destacado" aria-labelledby="dest-<?php echo esc_attr($doc_id); ?>">
        <div class="card-body p-3 p-md-4">
            <?php if ($admin): ?>
                <div class="docente-destacado__toolbar"><?php echo $admin; ?></div>
            <?php endif; ?>
            <div class="docente-destacado__header">
                <div class="docente-destacado__avatar-wrap">
                    <?php echo dp_avatar_markup($doc_id, $titulo, 200, 'shadow-lg border border-4 border-white'); ?>
                </div>
                <div class="docente-destacado__title">
                    <h2 id="dest-<?php echo esc_attr($doc_id); ?>" class="mb-2"><?php echo esc_html($titulo); ?></h2>
                    <p class="docente-destacado__role mb-2"><?php echo esc_html($rol); ?></p>
                    <?php if ($pref): ?>
                        <p class="docente-destacado__prefijo mb-0"><?php echo esc_html($pref); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($cv_raw): ?>
                <div class="cv-completo mt-3">
                    <?php
                        $cv_html = (strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw;
                        echo dp_safe_cv_html($cv_html);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php if ($should_print_styles): ?>
    <style>
    .docente-destacado {
        --dd-primary: var(--global-palette1, #1d3a72);
        --dd-primary-soft: rgba(29, 58, 114, 0.1);
        --dd-border: rgba(17, 39, 74, 0.16);
        --dd-text: var(--global-palette4, #1f2937);
        --dd-muted: var(--global-palette5, #657487);
        position: relative;
        overflow: hidden;
        border-radius: 1.2rem;
        border: 1px solid var(--dd-border);
        background:
            radial-gradient(115% 80% at 100% -8%, rgba(80, 147, 198, 0.14), transparent 58%),
            linear-gradient(170deg, #ffffff 0%, #f4f8ff 58%, #f8fbff 100%);
        transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        box-shadow: 0 .85rem 1.8rem rgba(15, 23, 42, .08) !important;
    }
    .docente-destacado::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--dd-primary) 0%, #2f6ca6 46%, #62aad9 100%);
        pointer-events: none;
    }
    .docente-destacado:hover {
        transform: translateY(-2px);
        border-color: rgba(29, 58, 114, 0.26);
        box-shadow: 0 1.05rem 2rem rgba(15, 23, 42, .12) !important;
    }
    .docente-destacado .card-body {
        padding: clamp(1rem, 2vw, 1.45rem) !important;
    }
    .docente-destacado__toolbar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: .55rem;
    }
    .docente-destacado__header {
        display: grid;
        grid-template-columns: clamp(108px, 11.5vw, 148px) minmax(0, 1fr);
        align-items: center;
        gap: .95rem;
        text-align: left;
    }
    .docente-destacado__avatar-wrap {
        width: 100%;
        max-width: 148px;
        line-height: 0;
        filter: drop-shadow(0 8px 16px rgba(15, 23, 42, .12));
    }
    .docente-destacado__avatar-wrap .dp-docente-avatar,
    .docente-destacado__avatar-wrap .dp-docente-avatar__img {
        width: 100% !important;
        height: auto !important;
        aspect-ratio: 1 / 1;
        border-radius: 1rem !important;
        object-fit: cover;
    }
    .docente-destacado__title {
        min-width: 0;
    }
    .docente-destacado__title h2 {
        margin: 0 0 .42rem !important;
        color: var(--dd-primary);
        font-size: clamp(1.4rem, 2.1vw, 2.05rem);
        line-height: 1.14;
        letter-spacing: -0.012em;
        overflow-wrap: anywhere;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
    }
    .docente-destacado__role {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: .34rem .82rem;
        border-radius: 999px;
        border: 1px solid rgba(29, 58, 114, 0.2);
        background: var(--dd-primary-soft);
        color: var(--dd-primary);
        font-weight: 700;
        font-size: clamp(.92rem, 1.25vw, 1.12rem);
        line-height: 1.2;
        max-width: 100%;
        overflow-wrap: anywhere;
    }
    .docente-destacado__prefijo {
        margin-top: .28rem;
        color: var(--dd-muted);
        font-size: clamp(.96rem, 1.15vw, 1.08rem);
        line-height: 1.42;
        overflow-wrap: anywhere;
    }
    .docente-destacado .cv-completo {
        margin-top: .9rem !important;
        padding-top: .9rem;
        border-top: 1px solid rgba(17, 24, 39, .1);
        color: var(--dd-text);
        font-size: clamp(.99rem, 1.04vw, 1.08rem);
        line-height: 1.66;
        overflow-wrap: anywhere;
    }
    .docente-destacado .cv-completo p { margin-bottom: .82rem; }
    .docente-destacado .cv-completo ul,
    .docente-destacado .cv-completo ol { padding-left: 1.2rem; margin-bottom: .82rem; }
    .docente-destacado .cv-completo > :last-child { margin-bottom: 0; }
    .docente-destacado__edit {
        border-radius: 12px;
        padding: .36rem .72rem;
        min-height: 0 !important;
        font-size: .95rem;
        font-weight: 700;
        border: 1px solid rgba(29, 58, 114, .16);
        background: rgba(255, 255, 255, .92);
        color: var(--dd-primary);
        box-shadow: 0 .55rem 1rem rgba(15, 23, 42, .1);
    }
    .docente-destacado__edit:hover { transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) {
        .docente-destacado,
        .docente-destacado__edit {
            transition: none !important;
        }
    }
    @media (max-width: 767.98px) {
        .docente-destacado__toolbar { justify-content: center; margin-bottom: .7rem; }
        .docente-destacado__edit { width: 100%; max-width: 320px; justify-content: center; }
        .docente-destacado__header {
            grid-template-columns: 1fr;
            justify-items: center;
            text-align: center;
            gap: .8rem;
        }
        .docente-destacado__title h2 {
            font-size: clamp(1.32rem, 5.6vw, 1.72rem);
        }
        .docente-destacado .cv-completo {
            font-size: .98rem;
            line-height: 1.62;
        }
    }
    </style>
    <?php endif; ?>
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
    $prefijo_full  = !empty($meta['prefijo_full'][0])  ? trim((string) $meta['prefijo_full'][0])  : '';
    $prefijo       = $prefijo_abrev !== '' ? $prefijo_abrev : $prefijo_full;

    $nombre     = dp_nombre_completo($doc_id);
    $cv_raw     = isset($meta['cv'][0]) ? (string) $meta['cv'][0] : '';
    $cv_html    = $cv_raw !== '' ? dp_safe_cv_html((strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw) : '<em>' . esc_html__('CV no disponible', 'flacso-posgrados-docentes') . '</em>';
    $avatar_html = !empty($atts['showAvatar']) ? dp_avatar_markup($doc_id, $nombre, 168, 'mx-sm-0 mx-auto') : '';
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
              <i class="bi bi-person-badge-fill me-2" aria-hidden="true"></i><?php echo $prefijo ? esc_html($prefijo . ' ') : ''; ?><?php echo esc_html($nombre); ?>
            </<?php echo $heading; ?>>
            <div class="flacso-docente-cv">
              <?php echo $cv_html; ?>
            </div>
            <?php if (current_user_can('edit_post', $doc_id)): ?>
              <div class="mt-3 d-print-none">
                <a class="btn btn-sm btn-palette2" href="<?php echo esc_url(get_edit_post_link($doc_id, '')); ?>" target="_blank" rel="noopener"
                   aria-label="<?php echo esc_attr(sprintf(__('Editar ficha de %s', 'flacso-posgrados-docentes'), $nombre)); ?>">
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
