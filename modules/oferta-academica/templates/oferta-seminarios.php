<?php
/**
 * Template para mostrar los seminarios asociados a una oferta académica.
 */

if (!defined('ABSPATH')) {
    exit;
}

$request_path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';

$resolve_oferta_id = static function () use ($request_path): int {
    $candidate_id = (int) get_queried_object_id();
    if ($candidate_id <= 0) {
        $candidate_id = (int) get_the_ID();
    }

    if ($candidate_id > 0 && get_post_type($candidate_id) === 'oferta-academica') {
        return $candidate_id;
    }

    if ($candidate_id > 0) {
        $associated_ofertas = get_posts([
            'post_type' => 'oferta-academica',
            'meta_key' => '_oferta_page_id',
            'meta_value' => $candidate_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($associated_ofertas)) {
            return (int) $associated_ofertas[0];
        }
    }

    if (is_string($request_path) && preg_match('|/programa/([^/]+)/|', $request_path, $matches)) {
        $slug = sanitize_title((string) $matches[1]);
        if ($slug !== '') {
            $post_obj = get_page_by_path($slug, OBJECT, 'oferta-academica');
            if ($post_obj instanceof WP_Post) {
                return (int) $post_obj->ID;
            }
        }
    }

    return 0;
};

$oferta_id = $resolve_oferta_id();
$oferta_title = $oferta_id > 0 ? get_the_title($oferta_id) : '';
$oferta_permalink = $oferta_id > 0 ? get_permalink($oferta_id) : '';

$seminarios = [];
if ($oferta_id > 0 && class_exists('Oferta_Seminarios_Integration')) {
    $seminarios = Oferta_Seminarios_Integration::get_programa_seminarios_data($oferta_id);
}

get_header();
?>
<div class="flacso-oferta-academica-seminarios-view">
    <div class="container py-5">
        <header class="mb-5 text-center">
            <h1 class="display-4 fw-bold">
                <?php
                $programa_label = is_string($oferta_title) && $oferta_title !== ''
                    ? $oferta_title
                    : __('este programa', 'flacso-uruguay');
                echo sprintf(__('Seminarios de %s', 'flacso-uruguay'), esc_html($programa_label));
                ?>
            </h1>
            <p class="lead"><?php _e('Explora los seminarios específicos asociados a este programa académico.', 'flacso-uruguay'); ?></p>
            <div class="mt-3">
                <a href="<?php echo esc_url(is_string($oferta_permalink) && $oferta_permalink !== '' ? $oferta_permalink : home_url('/formacion/seminarios/')); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left"></i> <?php _e('Volver al programa', 'flacso-uruguay'); ?>
                </a>
            </div>
        </header>

        <?php if (!empty($seminarios)) : ?>
            <div class="row g-4">
                <?php foreach ($seminarios as $seminario) : ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="card h-100 shadow-sm border-0 flacso-seminario-card">
                            <?php if ($seminario['thumbnail']) : ?>
                                <div class="card-img-top overflow-hidden" style="height: 200px;">
                                    <?php echo wp_get_attachment_image($seminario['thumbnail'], 'medium_large', false, ['class' => 'w-100 h-100 object-fit-cover']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h2 class="card-title h5 mb-3 fw-bold"><?php echo esc_html($seminario['titulo']); ?></h2>
                                
                                <div class="flacso-seminario-meta mb-3 small text-muted">
                                    <?php if ($seminario['modalidad']) : ?>
                                        <div class="mb-1">
                                            <i class="bi bi-geo-alt"></i> <?php echo esc_html($seminario['modalidad']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($seminario['fecha_inicio']) : ?>
                                        <div class="mb-1">
                                            <i class="bi bi-calendar-event"></i> 
                                            <?php 
                                            $date = date_i18n(get_option('date_format'), strtotime($seminario['fecha_inicio']));
                                            echo sprintf(__('Inicia: %s', 'flacso-uruguay'), $date); 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($seminario['excerpt']) : ?>
                                    <p class="card-text small text-secondary flex-grow-1">
                                        <?php echo wp_trim_words($seminario['excerpt'], 20); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-auto pt-3">
                                    <a href="<?php echo esc_url($seminario['permalink']); ?>" class="btn btn-primary w-100">
                                        <?php _e('Más información', 'flacso-uruguay'); ?>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-info-circle display-4 mb-3 d-block"></i>
                <p class="mb-0"><?php _e('No hay seminarios asociados a este programa en este momento.', 'flacso-uruguay'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
