<?php
get_header();

$equipos = get_terms([
    'taxonomy'   => 'equipo-docente',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
?>

<div class="flacso-docentes-scope container py-5 dp-directory">
    <nav aria-label="breadcrumb" class="docentes-breadcrumb mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Posgrados asociados</li>
        </ol>
    </nav>

        <section class="dp-directory-hero mb-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <p class="text-uppercase text-white-50 small mb-2">Mapa de posgrados</p>
                    <h1 class="display-6 fw-semibold text-white mb-3">Equipos academicos = posgrados asociados</h1>
                    <p class="text-white-75 lead mb-0">
                        Cada tarjeta vincula una pagina de posgrado con su equipo academico. Usa el buscador para filtrar por nombre de programa o integrante.
                    </p>
                </div>
            </div>
        </section>

    <?php if ($equipos && !is_wp_error($equipos)): ?>
        <section class="dp-directory-toolbar card border-0 shadow-sm mb-4" role="search" aria-label="Buscar posgrados">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6 col-lg-4">
                        <label for="posgrado-search" class="visually-hidden">Buscar posgrado asociado</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </span>
                            <input type="search" id="posgrado-search" class="form-control" placeholder="Buscar posgrado o integrante" aria-controls="posgrado-grid">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-0 text-muted small">El listado incluye todos los posgrados registrados, incluso aquellos sin integrantes publicados todavia.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="dp-program-grid" id="posgrado-grid">
            <?php foreach ($equipos as $equipo):
                $color      = function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : '#1d3a72';
                $page_data  = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($equipo->term_id) : null;
                $page_title = $page_data ? $page_data['title'] : $equipo->name;
                $relacion_nombre = function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name;
                $page_excerpt_raw = $page_data ? $page_data['excerpt'] : ($equipo->description ?: __('Este posgrado aun no tiene descripcion.', 'flacso-posgrados-docentes'));
                $page_excerpt = wp_trim_words(wp_strip_all_tags($page_excerpt_raw), 28, '...');
                $term_link   = get_term_link($equipo);
                if (is_wp_error($term_link)) {
                    $term_link = '';
                }
                $page_link   = $page_data ? $page_data['permalink'] : $term_link;
                $page_thumb  = ($page_data && !empty($page_data['thumbnail'])) ? $page_data['thumbnail'] : '';
                $docentes_preview = get_posts([
                    'post_type'      => 'docente',
                    'posts_per_page' => 4,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                    'tax_query'      => [[
                        'taxonomy' => 'equipo-docente',
                        'field'    => 'term_id',
                        'terms'    => $equipo->term_id,
                    ]],
                ]);
            ?>
                <article class="dp-program-card" data-title="<?php echo esc_attr(strtolower($page_title . ' ' . $relacion_nombre)); ?>">
                    <div class="d-flex flex-column gap-2">
                        <span class="dp-program-card__badge" style="background: <?php echo esc_attr($color); ?>20; color: <?php echo esc_attr($color); ?>;">
                            <i class="bi bi-layers me-1" aria-hidden="true"></i><?php echo esc_html($relacion_nombre); ?>
                        </span>
                        <h2 class="h4 mb-1"><?php echo esc_html($page_title); ?></h2>
                        <p class="text-muted mb-3"><?php echo esc_html($page_excerpt); ?></p>
                    </div>
                    <div class="dp-program-card__media<?php echo $page_thumb ? '' : ' dp-program-card__media--placeholder'; ?>"<?php if ($page_thumb): ?> style="background-image: url('<?php echo esc_url($page_thumb); ?>');"<?php else: ?> style="background: <?php echo esc_attr($color); ?>;"<?php endif; ?>>
                        <?php if (!$page_thumb): ?>
                            <span><?php echo esc_html($page_title); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dp-program-card__body align-items-center">
                        <div>
                            <p class="text-uppercase small fw-semibold text-muted mb-2">Integrantes destacados (<?php echo intval($equipo->count); ?>)</p>
                            <?php if ($docentes_preview): ?>
                                <ul class="dp-program-card__avatars" role="list">
                                    <?php foreach ($docentes_preview as $docente):
                                        $nombre_docente = dp_nombre_completo($docente->ID);
                                        $avatar = get_the_post_thumbnail($docente->ID, [80, 80], ['class' => 'rounded-circle object-fit-cover', 'loading' => 'lazy']);
                                        if (!$avatar) {
                                            $color_avatar = generar_color_avatar($nombre_docente);
                                            $initial = strtoupper(substr($nombre_docente, 0, 1));
                                            $avatar = '<div class="dp-program-avatar-placeholder" style="background:' . esc_attr($color_avatar) . ';"><span>' . esc_html($initial) . '</span></div>';
                                        }
                                    ?>
                                        <li>
                                            <a href="<?php echo esc_url(get_permalink($docente->ID)); ?>"
                                               class="d-inline-flex"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="<?php echo esc_attr($nombre_docente); ?>">
                                                <?php echo $avatar; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0"><?php esc_html_e('Sin integrantes publicados todavia.', 'flacso-posgrados-docentes'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-uppercase small fw-semibold text-muted mb-2">Ultima actividad</p>
                            <?php if ($page_data && !empty($page_data['modified'])): ?>
                                <p class="mb-0 fw-semibold"><?php echo esc_html($page_data['modified']); ?></p>
                            <?php else: ?>
                                <p class="mb-0 text-muted"><?php esc_html_e('Sin informacion disponible.', 'flacso-posgrados-docentes'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dp-program-card__footer">
                        <?php if ($page_link): ?>
                            <a class="btn btn-primary btn-sm" href="<?php echo esc_url($page_link); ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Ver pagina del posgrado
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo esc_url($term_link); ?>">
                            <i class="bi bi-people me-1" aria-hidden="true"></i>Ver integrantes asociados
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="dp-directory-empty text-center py-5">
            <div class="card border-0 shadow-sm p-5">
                <i class="bi bi-info-circle dp-directory-empty__icon mb-3" aria-hidden="true"></i>
                <h4 class="text-dark mb-2">No hay posgrados registrados</h4>
                <p class="text-muted mb-0">Todavia no se han creado equipos academicos para los posgrados.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
get_footer();
