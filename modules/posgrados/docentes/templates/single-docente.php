<?php
get_header();

while (have_posts()): the_post();

$prefijo_abrev = get_post_meta(get_the_ID(), 'prefijo_abrev', true);
$prefijo_full  = get_post_meta(get_the_ID(), 'prefijo_full', true);
$nombre        = get_post_meta(get_the_ID(), 'nombre', true);
$apellido      = get_post_meta(get_the_ID(), 'apellido', true);
$cv            = get_post_meta(get_the_ID(), 'cv', true);

$titulo_completo = dp_nombre_completo(get_the_ID());

$iniciales = '';
if ($nombre && $apellido) {
    $iniciales = strtoupper(substr($nombre, 0, 1) . substr($apellido, 0, 1));
} elseif ($nombre) {
    $iniciales = strtoupper(substr($nombre, 0, 2));
} else {
    $iniciales = 'US';
}

$color_avatar    = generar_color_avatar($titulo_completo);
$gradiente_avatar = generar_gradiente($color_avatar);

$imagen_id = get_post_thumbnail_id();
$imagen_html = $imagen_id ? wp_get_attachment_image(
    $imagen_id,
    'large',
    false,
    [
        'class' => 'rounded-circle object-fit-cover',
        'style' => 'width: 140px; height: 140px;',
        'alt'   => $titulo_completo,
    ]
) : '';

$equipos = get_the_terms(get_the_ID(), 'equipo-docente');
$docente_posgrados = [];
if ($equipos && !is_wp_error($equipos)) {
    foreach ($equipos as $equipo) {
        $page = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($equipo->term_id) : null;
        $equipo_color = function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : '#1d3a72';
        if ($page) {
            $docente_posgrados[] = array_merge(
                $page,
                [
                    'label' => function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name,
                    'color' => $equipo_color,
                ]
            );
        } else {
            $docente_posgrados[] = [
                'title' => $equipo->name,
                'permalink' => get_term_link($equipo),
                'label' => function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name,
                'color' => $equipo_color,
            ];
        }
    }
}

$equipo_destacado = ($equipos && !is_wp_error($equipos)) ? $equipos[0] : null;
$equipo_color_base = ($equipo_destacado && function_exists('get_equipo_color')) ? get_equipo_color($equipo_destacado->term_id) : '#1d3a72';
$hero_gradient = "linear-gradient(135deg, {$equipo_color_base} 0%, " . ajustar_luminosidad($equipo_color_base, -20) . " 100%)";
$equipo_destacado_link = ($equipo_destacado && !is_wp_error(get_term_link($equipo_destacado))) ? get_term_link($equipo_destacado) : '';
$ultima_actualizacion = get_the_modified_date(get_option('date_format'));
$slug_docente = get_post_field('post_name', get_the_ID());
$docente_correos = dp_get_docente_emails(get_the_ID());
$docente_correo_principal = dp_get_docente_principal_email(get_the_ID());
$docente_redes = dp_get_docente_socials(get_the_ID());

$docentes_url = get_post_type_archive_link('docente');
?>

<div class="flacso-docentes-scope container py-4 dp-directory">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <nav aria-label="breadcrumb" class="order-2 order-md-1">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="<?php echo esc_url($docentes_url); ?>">Equipo academico</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html($titulo_completo); ?></li>
            </ol>
        </nav>

        <a href="<?php echo esc_url($docentes_url); ?>" class="btn btn-outline-primary btn-sm order-1 order-md-2">
            <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Volver al listado
        </a>
    </div>

    <section class="dp-program-hero mb-5" style="background: <?php echo esc_attr($hero_gradient); ?>;">
        <div class="row g-4 align-items-center">
            <div class="col-md-auto text-center text-md-start">
                <?php if ($imagen_html): ?>
                    <div class="rounded-circle border border-3 border-white shadow" style="width: 140px; height: 140px; overflow: hidden;">
                        <?php echo $imagen_html; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow"
                         style="width: 140px; height: 140px; background: <?php echo esc_attr($gradiente_avatar); ?>;">
                        <span class="fw-bold display-5"><?php echo esc_html($iniciales); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md">
                <p class="text-white-50 text-uppercase small mb-2">Perfil academico</p>
                <h1 class="text-white mb-2"><?php echo esc_html($titulo_completo); ?></h1>
                <?php if ($prefijo_full): ?>
                    <p class="lead text-white-75 mb-3"><?php echo esc_html($prefijo_full); ?></p>
                <?php endif; ?>

                <?php if ($equipos && !is_wp_error($equipos)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($equipos as $equipo):
                            $color = function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : '#1d3a72';
                            $equipo_label = function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name;
                            $term_link = get_term_link($equipo);
                            if (is_wp_error($term_link)) {
                                $term_link = '';
                            }
                        ?>
                            <a href="<?php echo esc_url($term_link); ?>"
                               class="badge rounded-pill text-decoration-none"
                               style="border: 1px solid <?php echo esc_attr($color); ?>; color: var(--global-palette9, #ffffff);">
                                <?php echo esc_html($equipo_label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-3 text-white-75 small">
                    <?php if ($prefijo_abrev): ?>
                        <span><i class="bi bi-mortarboard me-1" aria-hidden="true"></i><?php echo esc_html($prefijo_abrev); ?></span>
                    <?php endif; ?>
                    <?php if ($slug_docente): ?>
                        <span><i class="bi bi-link-45deg me-1" aria-hidden="true"></i><?php echo esc_html($slug_docente); ?></span>
                    <?php endif; ?>
                    <?php if ($ultima_actualizacion): ?>
                        <span><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Actualizado <?php echo esc_html($ultima_actualizacion); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-auto">
                <div class="d-flex flex-column gap-2">
                    <a href="<?php echo esc_url($docentes_url); ?>" class="btn btn-light text-primary fw-semibold px-4" aria-label="Volver al archivo del equipo academico">
                        <i class="bi bi-people me-2" aria-hidden="true"></i>Ver archivo completo
                    </a>
                    <?php if ($equipo_destacado_link): ?>
                        <a href="<?php echo esc_url($equipo_destacado_link); ?>" class="btn btn-outline-light text-white fw-semibold px-4" aria-label="Ver posgrado destacado de <?php echo esc_attr($titulo_completo); ?>">
                            <i class="bi bi-layers me-2" aria-hidden="true"></i>Ver posgrado destacado
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0">Curriculum Vitae</h5>
                </div>
                <div class="card-body">
                    <?php if ($cv): ?>
                        <div class="contenido-cv"><?php echo wp_kses_post(wpautop($cv)); ?></div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-text fs-1 text-muted mb-3" aria-hidden="true"></i>
                            <p class="text-muted mb-0">Este perfil aun no tiene un curriculum publicado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-flex flex-column gap-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0">Contacto</h6>
                </div>
                <div class="card-body">
                    <?php if ($docente_correos): ?>
                        <ul class="dp-contact-list">
                            <?php foreach ($docente_correos as $correo): ?>
                                <li>
                                    <a class="dp-contact-link" href="mailto:<?php echo esc_attr(antispambot($correo['email'])); ?>">
                                        <i class="bi bi-envelope dp-contact-link__icon" aria-hidden="true"></i>
                                        <div>
                                            <strong><?php echo esc_html($correo['label'] ?: __('Correo', 'flacso-posgrados-docentes')); ?></strong>
                                            <p class="text-muted mb-0 small"><?php echo esc_html(antispambot($correo['email'])); ?></p>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay correos publicados.</p>
                    <?php endif; ?>
                    <?php if (!empty($docente_redes)): ?>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <?php foreach ($docente_redes as $red): ?>
                                <a class="dp-social-chip" href="<?php echo esc_url($red['url']); ?>" target="_blank" rel="noopener">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i><?php echo esc_html($red['label'] ?: 'Perfil'); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0">Metadatos</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong>ID:</strong> <?php echo esc_html(get_the_ID()); ?></li>
                        <?php if ($slug_docente): ?>
                            <li class="mb-2"><strong>Slug:</strong> <?php echo esc_html($slug_docente); ?></li>
                        <?php endif; ?>
                        <?php if ($ultima_actualizacion): ?>
                            <li class="mb-0"><strong>Actualizado:</strong> <?php echo esc_html($ultima_actualizacion); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0">Posgrados asociados</h6>
                </div>
                <div class="card-body">
                    <?php if ($docente_posgrados): ?>
                        <ul class="dp-directory-card__programs mb-0">
                            <?php foreach ($docente_posgrados as $programa):
                                $color_chip = $programa['color'] ?? '#1d3a72';
                                $link = isset($programa['permalink']) ? $programa['permalink'] : ($programa['link'] ?? '');
                                if (is_wp_error($link)) {
                                    $link = '';
                                }
                            ?>
                                <li class="dp-directory-card__program" style="background: <?php echo esc_attr($color_chip); ?>10;">
                                    <span class="badge rounded-pill" style="background: <?php echo esc_attr($color_chip); ?>20; color: <?php echo esc_attr($color_chip); ?>;">
                                        <i class="bi bi-layers me-1" aria-hidden="true"></i><?php echo esc_html($programa['label']); ?>
                                    </span>
                                    <?php if ($link): ?>
                                        <a href="<?php echo esc_url($link); ?>" class="fw-semibold text-decoration-none" target="_blank" rel="noopener"><?php echo esc_html($programa['title']); ?></a>
                                    <?php else: ?>
                                        <span class="fw-semibold"><?php echo esc_html($programa['title']); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay posgrados asociados publicados.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
endwhile;
get_footer();
