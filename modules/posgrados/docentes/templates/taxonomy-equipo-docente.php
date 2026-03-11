<?php
get_header();

$term = get_queried_object();
$page_data = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($term->term_id) : null;
$relacion_nombre = function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($term->term_id, $term->name) : $term->name;
$posgrado_title = $page_data ? $page_data['title'] : '';
$page_title = $posgrado_title ?: $relacion_nombre;
$page_excerpt = $page_data ? $page_data['excerpt'] : $term->description;
$page_link = $page_data ? $page_data['permalink'] : '';
$page_thumbnail = $page_data && !empty($page_data['thumbnail']) ? $page_data['thumbnail'] : '';

$tax_query = [[
    'taxonomy' => 'equipo-docente',
    'field'    => 'term_id',
    'terms'    => $term->term_id,
]];

$docentes = get_posts([
    'post_type'      => 'docente',
    'posts_per_page' => -1,
    'meta_key'       => 'apellido',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'tax_query'      => $tax_query,
]);

$color_equipo = function_exists('get_equipo_color') ? get_equipo_color($term->term_id) : '#1d3a72';
$gradiente_equipo = "linear-gradient(135deg, {$color_equipo} 0%, " . ajustar_luminosidad($color_equipo, -20) . " 100%)";

$equipos_docente_url = home_url('/equipo-docente/');
$equipos_term_link = get_term_link('equipo-docente');
if (!is_wp_error($equipos_term_link) && $equipos_term_link) {
    $equipos_docente_url = $equipos_term_link;
}

$term_link_url = get_term_link($term);
if (is_wp_error($term_link_url)) {
    $term_link_url = '';
}

$total_docentes_equipo = count($docentes);
$ultimo_docente_equipo = get_posts([
    'post_type'      => 'docente',
    'posts_per_page' => 1,
    'orderby'        => 'modified',
    'order'          => 'DESC',
    'fields'         => 'ids',
    'tax_query'      => $tax_query,
]);
$equipo_last_updated = $ultimo_docente_equipo ? get_post_modified_time(get_option('date_format'), false, $ultimo_docente_equipo[0], true) : '';
?>

<div class="flacso-docentes-scope container py-4 dp-directory">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <nav aria-label="breadcrumb" class="order-2 order-md-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="<?php echo esc_url($equipos_docente_url); ?>">Posgrados asociados</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html($relacion_nombre); ?></li>
            </ol>
        </nav>

        <a href="<?php echo esc_url($equipos_docente_url); ?>" class="btn btn-outline-primary btn-sm order-1 order-md-2">
            <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Volver al mapa de posgrados
        </a>
    </div>

    <section class="dp-program-hero mb-5" style="background: <?php echo esc_attr($gradiente_equipo); ?>;">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="text-uppercase text-white-50 small mb-2">Posgrado asociado</p>
                <h1 class="text-white mb-2"><?php echo esc_html($relacion_nombre); ?></h1>
                <?php if ($posgrado_title): ?>
                    <p class="text-white-75 mb-3"><?php echo esc_html($posgrado_title); ?></p>
                <?php endif; ?>
                <?php if ($page_excerpt): ?>
                    <p class="lead text-white-75 mb-4"><?php echo esc_html($page_excerpt); ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <span class="dp-detail-badge text-white">
                        <i class="bi bi-people me-1" aria-hidden="true"></i><?php echo esc_html($total_docentes_equipo); ?> integrante<?php echo $total_docentes_equipo !== 1 ? 's' : ''; ?>
                    </span>
                    <?php if ($page_link): ?>
                        <a class="btn btn-light btn-sm text-primary fw-semibold" href="<?php echo esc_url($page_link); ?>" target="_blank" rel="noopener">
                            Ver pagina del posgrado
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <?php if ($page_thumbnail): ?>
                    <div class="dp-program-hero__media" style="background-image:url('<?php echo esc_url($page_thumbnail); ?>');"></div>
                <?php else: ?>
                    <div class="dp-program-hero__media dp-program-hero__media--placeholder">
                        <span><?php echo esc_html($relacion_nombre); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="dp-insight-card h-100" role="group" aria-label="Integrantes activos en el posgrado">
                <small class="text-uppercase text-muted">Integrantes activos</small>
                <strong class="fs-3 d-block"><?php echo esc_html($total_docentes_equipo); ?></strong>
                <?php if ($equipo_last_updated): ?>
                    <span class="text-muted small">Actualizado <?php echo esc_html($equipo_last_updated); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dp-insight-card h-100" role="group" aria-label="Color institucional del posgrado">
                <small class="text-uppercase text-muted">Color de referencia</small>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <span class="dp-color-swatch" style="background: <?php echo esc_attr($color_equipo); ?>;"></span>
                    <span class="fw-semibold"><?php echo esc_html($color_equipo); ?></span>
                </div>
                <p class="text-muted small mb-0">Aplicado en chips, badges y fondos relacionados al posgrado.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dp-insight-card h-100" role="group" aria-label="Enlaces del posgrado">
                <small class="text-uppercase text-muted">Enlaces rápidos</small>
                <?php if ($page_link): ?>
                    <a href="<?php echo esc_url($page_link); ?>" class="btn btn-link px-0" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Ver pagina del posgrado
                    </a>
                <?php endif; ?>
                <?php if ($term_link_url): ?>
                    <a href="<?php echo esc_url($term_link_url); ?>" class="btn btn-link px-0" target="_blank" rel="noopener">
                        <i class="bi bi-collection me-1" aria-hidden="true"></i>Ver archivo publico
                    </a>
                <?php else: ?>
                    <p class="text-muted mb-0">Sin enlace publico disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($docentes): ?>
        <section class="flacso-docentes-grid" id="grid-docentes">
            <?php foreach ($docentes as $docente):
                $nombre_completo = dp_nombre_completo($docente->ID);
                $prefijo_abrev   = get_post_meta($docente->ID, 'prefijo_abrev', true);
                $nombre          = get_post_meta($docente->ID, 'nombre', true);
                $apellido        = get_post_meta($docente->ID, 'apellido', true);
                $display_name    = trim(($nombre ?: '') . ' ' . ($apellido ?: ''));
                if ($display_name === '') {
                    $display_name = $nombre_completo;
                }
                $fallback = $display_name ?: $nombre_completo;
                $fallback_initials = function_exists('mb_substr') ? mb_substr($fallback, 0, 2) : substr($fallback, 0, 2);
                if ($fallback_initials === '') {
                    $fallback_initials = 'FL';
                }
                $iniciales = function_exists('dp_iniciales') ? dp_iniciales($nombre, $apellido, $fallback_initials) : strtoupper($fallback_initials);
                $color_avatar    = function_exists('generar_color_avatar') ? generar_color_avatar($nombre_completo) : '#1d3a72';
                $gradiente_avatar = function_exists('generar_gradiente') ? generar_gradiente($color_avatar) : $color_avatar;
                $img_id          = get_post_thumbnail_id($docente->ID);
                $img_html        = $img_id ? wp_get_attachment_image(
                    $img_id,
                    'medium',
                    false,
                    [
                        'class' => 'flacso-docentes-card__avatar-img',
                        'alt'   => $nombre_completo,
                        'loading' => 'lazy',
                    ]
                ) : '';
                $cv_bruto    = get_post_meta($docente->ID, 'cv', true);
                $cv_preview  = $cv_bruto ? wp_trim_words(wp_strip_all_tags($cv_bruto), 36, '...') : '';
                $card_heading_id  = 'docente-' . $docente->ID;
                $edit_url = current_user_can('edit_post', $docente->ID) ? get_edit_post_link($docente->ID) : '';
            ?>
                <article class="flacso-docentes-card" aria-labelledby="<?php echo esc_attr($card_heading_id); ?>">
                    <div class="flacso-docentes-card__avatar">
                        <?php if ($img_html): ?>
                            <?php echo $img_html; ?>
                        <?php else: ?>
                            <div class="flacso-docentes-card__avatar-fallback" style="background: <?php echo esc_attr($gradiente_avatar); ?>;">
                                <span><?php echo esc_html($iniciales); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flacso-docentes-card__summary text-center">
                        <span class="flacso-docentes-card__badge"><?php esc_html_e('Integrante', 'flacso-posgrados-docentes'); ?></span>
                        <?php if ($prefijo_abrev): ?>
                            <span class="flacso-docentes-card__abbr"><?php echo esc_html($prefijo_abrev); ?></span>
                        <?php endif; ?>
                        <h3 id="<?php echo esc_attr($card_heading_id); ?>"><?php echo esc_html($display_name); ?></h3>
                    </div>

                    <?php if ($cv_preview): ?>
                        <p class="flacso-docentes-card__excerpt"><?php echo esc_html($cv_preview); ?></p>
                    <?php endif; ?>

                    <div class="flacso-docentes-card__footer">
                        <?php if ($edit_url): ?>
                            <a href="<?php echo esc_url($edit_url); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i><?php esc_html_e('Editar docente', 'flacso-posgrados-docentes'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(get_permalink($docente->ID)); ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i><?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <div class="dp-directory-empty text-center py-5">
            <div class="card border-0 shadow-sm p-5">
                <i class="bi bi-info-circle dp-directory-empty__icon mb-3" aria-hidden="true"></i>
                <h4 class="text-dark mb-2">No hay integrantes en este posgrado</h4>
                <p class="text-muted mb-3">Actualmente no hay integrantes asignados a este posgrado asociado.</p>
                <a href="<?php echo esc_url($equipos_docente_url); ?>" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Volver a posgrados
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
get_footer();
