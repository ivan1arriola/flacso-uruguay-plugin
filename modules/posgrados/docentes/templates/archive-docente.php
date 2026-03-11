<?php
get_header();

$docentes = get_posts([
    'post_type'      => 'docente',
    'posts_per_page' => -1,
    'meta_key'       => 'apellido',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
]);

$equipos = get_terms([
    'taxonomy'   => 'equipo-docente',
    'hide_empty' => true,
]);

$posgrados_relaciones = [];
$program_cards = [];

if ($equipos && !is_wp_error($equipos)) {
    foreach ($equipos as $equipo) {
        $page_data = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($equipo->term_id) : null;
        if ($page_data) {
            $posgrados_relaciones[$page_data['id']] = $page_data['title'];
        }

        $program_cards[] = [
            'term'  => $equipo,
            'color' => function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : '#1d3a72',
            'count' => (int) $equipo->count,
            'page'  => $page_data,
        ];
    }

    usort($program_cards, function ($a, $b) {
        if ($a['count'] === $b['count']) {
            return strcasecmp($a['term']->name, $b['term']->name);
        }
        return $b['count'] <=> $a['count'];
    });
}

$total_docentes  = $docentes ? count($docentes) : 0;
$total_posgrados = $program_cards ? count($program_cards) : 0;
$user_can_edit   = is_user_logged_in() && current_user_can('edit_pages');

$docentes_items         = [];
$docentes_con_contacto  = 0;
$docentes_con_cv        = 0;
$docentes_sin_equipo    = 0;

$substring = function ($text, int $length = 1): string {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    return function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length);
};

$to_upper = function ($text): string {
    return function_exists('mb_strtoupper') ? mb_strtoupper($text) : strtoupper($text);
};

foreach ($docentes as $docente) {
    $nombre_completo = function_exists('dp_nombre_completo') ? dp_nombre_completo($docente->ID) : get_the_title($docente);
    $prefijo_abrev   = get_post_meta($docente->ID, 'prefijo_abrev', true);
    $prefijo_full    = get_post_meta($docente->ID, 'prefijo_full', true);
    $nombre          = get_post_meta($docente->ID, 'nombre', true);
    $apellido        = get_post_meta($docente->ID, 'apellido', true);
    $nombre_sin_prefijo = trim(($nombre ?: '') . ' ' . ($apellido ?: ''));
    if ($nombre_sin_prefijo === '') {
        $nombre_sin_prefijo = $nombre_completo;
    }

    if ($nombre && $apellido) {
        $iniciales = $to_upper($substring($nombre, 1) . $substring($apellido, 1));
    } else {
        $iniciales = $to_upper($substring($nombre_completo, 2));
    }

    $color_avatar     = function_exists('generar_color_avatar') ? generar_color_avatar($nombre_completo) : '#1d3a72';
    $gradiente_avatar = function_exists('generar_gradiente') ? generar_gradiente($color_avatar) : $color_avatar;

    $img_id   = get_post_thumbnail_id($docente->ID);
    $img_html = '';
    if ($img_id) {
        $img_html = wp_get_attachment_image(
            $img_id,
            'medium',
            false,
            [
                'class'   => 'flacso-docentes-card__avatar-img',
                'alt'     => $nombre_completo,
                'loading' => 'lazy',
            ]
        );
    }

    $docente_equipos = get_the_terms($docente->ID, 'equipo-docente');
    if (is_wp_error($docente_equipos) || !$docente_equipos) {
        $docente_equipos = [];
        $docentes_sin_equipo++;
    }

    $equipos_slugs = $docente_equipos ? wp_list_pluck($docente_equipos, 'slug') : [];

    $docente_correos           = function_exists('dp_get_docente_emails') ? dp_get_docente_emails($docente->ID) : [];
    $docente_correo_principal  = function_exists('dp_get_docente_principal_email') ? dp_get_docente_principal_email($docente->ID) : null;
    $docente_redes             = function_exists('dp_get_docente_socials') ? dp_get_docente_socials($docente->ID) : [];

    if ($docente_correos) {
        $docentes_con_contacto++;
    }

    $cv_bruto    = get_post_meta($docente->ID, 'cv', true);
    $cv_preview  = $cv_bruto ? wp_trim_words(wp_strip_all_tags($cv_bruto), 36, '...') : '';
    if ($cv_bruto) {
        $docentes_con_cv++;
    }

    $docente_pages_ids = [];
    $docente_programas = [];

    if ($docente_equipos) {
        foreach ($docente_equipos as $equipo) {
            $page_data    = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($equipo->term_id) : null;
            $equipo_color = function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : '#1d3a72';

            if ($page_data) {
                $docente_pages_ids[] = $page_data['id'];
                $docente_programas[] = array_merge($page_data, [
                    'color'   => $equipo_color,
                    'label'   => function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name,
                    'is_page' => true,
                ]);
            } else {
                $docente_programas[] = [
                    'id'        => 'term-' . $equipo->term_id,
                    'title'     => $equipo->name,
                    'permalink' => '',
                    'excerpt'   => '',
                    'thumbnail' => '',
                    'color'     => $equipo_color,
                    'label'     => $equipo->name,
                    'is_page'   => false,
                ];
            }
        }
    }

    $docente_pages_ids = array_values(array_unique(array_filter($docente_pages_ids)));
    $data_pages_attr   = $docente_pages_ids ? implode(' ', $docente_pages_ids) : '';
    $docente_programas = array_values($docente_programas);
    $ultima_actualizacion = get_post_modified_time(get_option('date_format'), false, $docente->ID, true);

    $docentes_items[] = [
        'id'                 => $docente->ID,
        'title'              => $nombre_completo,
        'prefijo_full'       => $prefijo_full,
        'prefijo_abrev'      => $prefijo_abrev,
        'nombre'             => $nombre,
        'apellido'           => $apellido,
        'nombre_attr'        => strtolower(remove_accents($nombre_sin_prefijo)),
        'equipos_attr'       => implode(' ', array_map('sanitize_title', $equipos_slugs)),
        'pages_attr'         => $data_pages_attr,
        'avatar_html'        => $img_html,
        'avatar_fallback'    => [
            'gradiente' => $gradiente_avatar,
            'iniciales' => $iniciales ?: 'FL',
        ],
        'programas'          => $docente_programas,
        'cv_preview'         => $cv_preview,
        'has_cv'             => !empty($cv_bruto),
        'ultima_actualizacion'=> $ultima_actualizacion,
        'correo_principal'   => $docente_correo_principal,
        'correos'            => $docente_correos,
        'redes'              => $docente_redes,
        'permalink'          => get_permalink($docente->ID),
        'edit_url'           => $user_can_edit ? get_edit_post_link($docente->ID) : '',
    ];
}

$ultimo_actualizado = get_posts([
    'post_type'      => 'docente',
    'posts_per_page' => 1,
    'orderby'        => 'modified',
    'order'          => 'DESC',
    'fields'         => 'ids',
]);
$last_updated_date = $ultimo_actualizado ? get_post_modified_time(get_option('date_format'), false, $ultimo_actualizado[0], true) : '';

$posgrados_root_url = '';
if (class_exists('FLACSO_Posgrados_Pages')) {
    $root_id = (int) FLACSO_Posgrados_Pages::ROOT_PAGE_ID;
    if ($root_id) {
        $posgrados_root_url = get_permalink($root_id);
    }
}
if (!$posgrados_root_url) {
    $posgrados_root_url = home_url('/');
}

$contact_email = defined('FLACSO_EMAIL_CONTACTO') ? FLACSO_EMAIL_CONTACTO : get_option('admin_email');
?>

<div class="flacso-docentes-scope flacso-docentes-landing">
    <div class="container">
        <section class="flacso-docentes-hero flacso-docentes-hero--stacked">
            <div class="flacso-docentes-hero__content">
                <p class="flacso-docentes-hero__eyebrow"><?php esc_html_e('Equipo academico FLACSO Uruguay', 'flacso-posgrados-docentes'); ?></p>
                <h1 class="flacso-docentes-hero__title"><?php esc_html_e('Directorio del equipo academico', 'flacso-posgrados-docentes'); ?></h1>
                <p class="flacso-docentes-hero__lead">
                    <?php esc_html_e('Buscá perfiles por programa, equipo o palabra clave en un formato más sencillo.', 'flacso-posgrados-docentes'); ?>
                </p>
                <div class="flacso-docentes-hero__actions">
                    <a class="btn btn-light btn-lg" href="<?php echo esc_url($posgrados_root_url); ?>">
                        <i class="bi bi-map me-1" aria-hidden="true"></i><?php esc_html_e('Ver mapa de posgrados', 'flacso-posgrados-docentes'); ?>
                    </a>
                </div>
            </div>
            <?php if ($docentes_items): ?>
                <div class="flacso-docentes-filters flacso-docentes-filters--full" role="region" aria-label="<?php esc_attr_e('Filtros del directorio del equipo academico', 'flacso-posgrados-docentes'); ?>">
                    <div class="flacso-docentes-filters__card">
                    <div class="flacso-docentes-field">
                        <label for="buscador-docentes-archive"><?php esc_html_e('Buscar por nombre o palabra clave', 'flacso-posgrados-docentes'); ?></label>
                        <div class="flacso-docentes-field__control">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search"
                                   id="buscador-docentes-archive"
                                   placeholder="<?php esc_attr_e('Ej.: Magister, Educación digital…', 'flacso-posgrados-docentes'); ?>"
                                   aria-controls="grid-docentes"
                                   aria-describedby="docentes-live-region">
                        </div>
                    </div>
                    <?php if ($equipos && !is_wp_error($equipos)): ?>
                        <div class="flacso-docentes-field">
                            <label for="filtro-equipo"><?php esc_html_e('Filtrar por equipo academico', 'flacso-posgrados-docentes'); ?></label>
                            <div class="flacso-docentes-field__control">
                                <i class="bi bi-collection" aria-hidden="true"></i>
                                <select id="filtro-equipo" aria-controls="grid-docentes">
                                    <option value=""><?php esc_html_e('Todos los equipos', 'flacso-posgrados-docentes'); ?></option>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <?php $equipo_label = function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name; ?>
                                        <option value="<?php echo esc_attr($equipo->slug); ?>"><?php echo esc_html($equipo_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($posgrados_relaciones)): ?>
                        <div class="flacso-docentes-field">
                            <label for="filtro-posgrado"><?php esc_html_e('Filtrar por página del posgrado', 'flacso-posgrados-docentes'); ?></label>
                            <div class="flacso-docentes-field__control">
                                <i class="bi bi-window-stack" aria-hidden="true"></i>
                                <select id="filtro-posgrado" aria-controls="grid-docentes">
                                    <option value=""><?php esc_html_e('Todas las páginas', 'flacso-posgrados-docentes'); ?></option>
                                    <?php foreach ($posgrados_relaciones as $page_id => $title): ?>
                                        <option value="<?php echo esc_attr($page_id); ?>"><?php echo esc_html($title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($posgrados_relaciones)): ?>
                        <p class="flacso-docentes-filters__hint">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <?php esc_html_e('Una misma persona puede participar en varios posgrados. Combina los filtros para encontrar coincidencias especificas.', 'flacso-posgrados-docentes'); ?>
                        </p>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($docentes_items): ?>
            <p id="docentes-live-region" class="visually-hidden" aria-live="polite" aria-atomic="true"></p>

            <section class="flacso-docentes-grid" id="grid-docentes">
                <?php foreach ($docentes_items as $item): ?>
                    <?php $card_heading_id = 'docente-' . $item['id']; ?>
                    <article class="flacso-docentes-card docente-item"
                             data-equipos="<?php echo esc_attr($item['equipos_attr']); ?>"
                             data-nombre="<?php echo esc_attr($item['nombre_attr']); ?>"
                             data-pages="<?php echo esc_attr($item['pages_attr']); ?>"
                             aria-labelledby="<?php echo esc_attr($card_heading_id); ?>">
                        <div class="flacso-docentes-card__avatar">
                            <?php if ($item['avatar_html']): ?>
                                <?php echo $item['avatar_html']; ?>
                            <?php else: ?>
                                <div class="flacso-docentes-card__avatar-fallback" style="background: <?php echo esc_attr($item['avatar_fallback']['gradiente']); ?>;">
                                    <span><?php echo esc_html($item['avatar_fallback']['iniciales']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flacso-docentes-card__summary text-center">
                            <span class="flacso-docentes-card__badge"><?php esc_html_e('Integrante', 'flacso-posgrados-docentes'); ?></span>
                            <?php if ($item['prefijo_abrev']): ?>
                                <span class="flacso-docentes-card__abbr"><?php echo esc_html($item['prefijo_abrev']); ?></span>
                            <?php endif; ?>
                            <?php
                                $display_name = trim(($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? ''));
                                if ($display_name === '') {
                                    $display_name = $item['title'];
                                }
                            ?>
                            <h3 id="<?php echo esc_attr($card_heading_id); ?>"><?php echo esc_html($display_name); ?></h3>
                        </div>

                        <?php if ($item['cv_preview']): ?>
                            <p class="flacso-docentes-card__excerpt"><?php echo esc_html($item['cv_preview']); ?></p>
                        <?php endif; ?>

                        <div class="flacso-docentes-card__footer">
                            <?php if (!empty($item['edit_url'])): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($item['edit_url']); ?>">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i><?php esc_html_e('Editar docente', 'flacso-posgrados-docentes'); ?>
                                </a>
                            <?php endif; ?>
                            <a class="btn btn-primary btn-sm" href="<?php echo esc_url($item['permalink']); ?>">
                                <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i><?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="flacso-docentes-empty text-center">
                <div class="flacso-docentes-empty__card">
                    <i class="bi bi-emoji-dizzy" aria-hidden="true"></i>
                    <h2><?php esc_html_e('Todavia no hay perfiles publicados', 'flacso-posgrados-docentes'); ?></h2>
                    <p><?php esc_html_e('Cuando se sumen perfiles apareceran automaticamente en este directorio.', 'flacso-posgrados-docentes'); ?></p>
                    <?php if ($user_can_edit): ?>
                        <a class="btn btn-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=docente')); ?>">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?php esc_html_e('Cargar primer perfil', 'flacso-posgrados-docentes'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
