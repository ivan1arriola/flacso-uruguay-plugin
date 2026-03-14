<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$docentes_query = new WP_Query([
    'post_type' => 'docente',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => [
        'meta_value' => 'ASC',
        'title' => 'ASC',
    ],
    'meta_key' => 'apellido',
    'no_found_rows' => true,
]);

$total_docentes = (int) $docentes_query->post_count;
?>

<div class="flacso-docentes-landing">
    <main id="main" class="site-main">
        <div class="site-container">
            <section class="flacso-docentes-hero flacso-docentes-hero--stacked">
                <div class="flacso-docentes-hero__content">
                    <p class="flacso-docentes-hero__eyebrow"><?php esc_html_e('Directorio academico', 'flacso-posgrados-docentes'); ?></p>
                    <h1 class="flacso-docentes-hero__title"><?php esc_html_e('Docentes', 'flacso-posgrados-docentes'); ?></h1>
                    <p class="flacso-docentes-hero__lead"><?php esc_html_e('Explora los perfiles individuales del cuerpo docente y encuentra rapidamente a cada integrante por nombre.', 'flacso-posgrados-docentes'); ?></p>
                </div>

                <div class="flacso-docentes-hero__panel">
                    <article class="flacso-docentes-stat">
                        <span class="flacso-docentes-stat__label"><?php esc_html_e('Perfiles publicados', 'flacso-posgrados-docentes'); ?></span>
                        <strong class="flacso-docentes-stat__value"><?php echo esc_html(number_format_i18n($total_docentes)); ?></strong>
                    </article>
                </div>
            </section>

            <section class="flacso-docentes-filters flacso-docentes-filters--full" aria-label="<?php esc_attr_e('Buscar docentes', 'flacso-posgrados-docentes'); ?>">
                <div class="flacso-docentes-filters__card">
                    <div class="flacso-docentes-field">
                        <label for="buscador-docentes-archive"><?php esc_html_e('Buscar por nombre', 'flacso-posgrados-docentes'); ?></label>
                        <div class="flacso-docentes-field__control">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input
                                type="search"
                                id="buscador-docentes-archive"
                                placeholder="<?php esc_attr_e('Ej. Ana Perez', 'flacso-posgrados-docentes'); ?>"
                                aria-controls="grid-docentes"
                            >
                        </div>
                    </div>

                    <p id="docentes-live-region" class="flacso-docentes-filters__hint" aria-live="polite">
                        <?php echo esc_html(sprintf(_n('%d perfil visible', '%d perfiles visibles', $total_docentes, 'flacso-posgrados-docentes'), $total_docentes)); ?>
                    </p>
                </div>
            </section>

            <section id="grid-docentes" class="flacso-docentes-grid" aria-label="<?php esc_attr_e('Listado de docentes', 'flacso-posgrados-docentes'); ?>">
                <?php if ($docentes_query->have_posts()) : ?>
                    <?php while ($docentes_query->have_posts()) : $docentes_query->the_post(); ?>
                        <?php
                        $docente_id = get_the_ID();
                        $nombre = (string) get_post_meta($docente_id, 'nombre', true);
                        $apellido = (string) get_post_meta($docente_id, 'apellido', true);

                        $nombre_completo = function_exists('dp_nombre_completo')
                            ? dp_nombre_completo($docente_id)
                            : get_the_title($docente_id);
                        $nombre_completo = $nombre_completo ?: get_the_title($docente_id);

                        $prefijo_abrev = (string) get_post_meta($docente_id, 'prefijo_abrev', true);
                        $titulo = (string) get_post_meta($docente_id, 'titulo', true);

                        $cv_raw = (string) get_post_meta($docente_id, 'cv', true);
                        $resumen = wp_trim_words(wp_strip_all_tags($cv_raw), 28);
                        if ($resumen === '') {
                            $resumen = wp_trim_words(wp_strip_all_tags(get_the_excerpt($docente_id)), 28);
                        }

                        $correo_principal = function_exists('dp_get_docente_principal_email')
                            ? dp_get_docente_principal_email($docente_id)
                            : null;
                        $correo = is_array($correo_principal) ? (string) ($correo_principal['email'] ?? '') : '';

                        $iniciales = function_exists('dp_iniciales')
                            ? dp_iniciales($nombre, $apellido, 'DP')
                            : strtoupper(substr($nombre_completo, 0, 2));
                        $avatar_color = function_exists('dp_color_from_string') ? dp_color_from_string($nombre_completo) : '#1d3a72';
                        ?>

                        <article class="flacso-docentes-card docente-item" data-nombre="<?php echo esc_attr(strtolower($nombre_completo)); ?>">
                            <div class="flacso-docentes-card__avatar">
                                <?php if (has_post_thumbnail($docente_id)) : ?>
                                    <?php
                                    echo get_the_post_thumbnail(
                                        $docente_id,
                                        'medium',
                                        [
                                            'class' => 'flacso-docentes-card__avatar-img',
                                            'alt' => $nombre_completo,
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                        ]
                                    );
                                    ?>
                                <?php else : ?>
                                    <span class="flacso-docentes-card__avatar-fallback" style="background-color: <?php echo esc_attr($avatar_color); ?>;">
                                        <?php echo esc_html($iniciales); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flacso-docentes-card__summary">
                                <?php if ($prefijo_abrev !== '') : ?>
                                    <span class="flacso-docentes-card__abbr"><?php echo esc_html($prefijo_abrev); ?></span>
                                <?php endif; ?>
                                <h2 class="flacso-docentes-card__title"><?php echo esc_html($nombre_completo); ?></h2>
                                <?php if ($titulo !== '') : ?>
                                    <p class="flacso-docentes-card__subtitle"><?php echo esc_html($titulo); ?></p>
                                <?php endif; ?>
                                <?php if ($resumen !== '') : ?>
                                    <p class="flacso-docentes-card__excerpt"><?php echo esc_html($resumen); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ($correo !== '') : ?>
                                <div class="flacso-docentes-card__contact">
                                    <a class="flacso-docentes-contact" href="mailto:<?php echo esc_attr(antispambot($correo)); ?>">
                                        <i class="bi bi-envelope" aria-hidden="true"></i>
                                        <span><?php echo esc_html(antispambot($correo)); ?></span>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <footer class="flacso-docentes-card__footer">
                                <a class="btn btn-primary" href="<?php echo esc_url(get_permalink($docente_id)); ?>">
                                    <?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?>
                                </a>
                            </footer>
                        </article>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="flacso-docentes-empty">
                        <div class="flacso-docentes-empty__card">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <h2><?php esc_html_e('No hay docentes publicados.', 'flacso-posgrados-docentes'); ?></h2>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php
get_footer();
