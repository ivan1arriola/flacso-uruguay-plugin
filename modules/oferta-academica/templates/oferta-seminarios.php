<?php
/**
 * Template para mostrar los seminarios asociados a una oferta académica.
 */

if (!defined('ABSPATH')) {
    exit;
}

$oferta_id = get_the_ID();

// Si estamos en una página asociada, resolvemos el ID de la oferta
if (get_post_type($oferta_id) !== 'oferta-academica') {
    $associated_ofertas = get_posts([
        'post_type' => 'oferta-academica',
        'meta_key' => '_oferta_page_id',
        'meta_value' => $oferta_id,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    if (!empty($associated_ofertas)) {
        $oferta_id = $associated_ofertas[0];
    }
}

$seminarios = [];
if (class_exists('Oferta_Seminarios_Integration')) {
    $seminarios = Oferta_Seminarios_Integration::get_programa_seminarios_data($oferta_id);
}

get_header();

// Bloque de depuración para administradores
if (current_user_can('manage_options')) {
    echo '<div class="container mt-4"><div class="alert alert-warning shadow-sm" style="font-family: monospace; font-size: 13px;">';
    echo '<strong>[DEBUG MODE]</strong><br>';
    echo 'ID consultado: ' . get_queried_object_id() . '<br>';
    echo 'ID de la Oferta: ' . (int)$oferta_id . '<br>';
    echo 'Tipo de Post: ' . get_post_type($oferta_id) . '<br>';
    echo 'Seminarios detectados: ' . count($seminarios) . '<br>';
    if (!empty($seminarios)) {
        echo 'IDs de Seminarios: ' . implode(', ', array_column($seminarios, 'id')) . '<br>';
    }
    echo '</div></div>';
}
?>
<div class="flacso-oferta-academica-seminarios-view">
    <div class="container py-5">
        <header class="mb-5 text-center">
            <h1 class="display-4 fw-bold"><?php echo sprintf(__('Seminarios de %s', 'flacso-uruguay'), get_the_title($oferta_id)); ?></h1>
            <p class="lead"><?php _e('Explora los seminarios específicos asociados a este programa académico.', 'flacso-uruguay'); ?></p>
            <div class="mt-3">
                <a href="<?php echo get_permalink($oferta_id); ?>" class="btn btn-outline-primary btn-sm">
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
