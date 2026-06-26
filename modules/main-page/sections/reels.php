<?php
// ==================================================
// SECCION REELS INSTAGRAM - FLACSO URUGUAY
// ==================================================

if (!function_exists('flacso_section_reels_render')) {
function flacso_section_reels_render() {
    $feed = class_exists('Flacso_Instagram_API') ? Flacso_Instagram_API::get_feed() : new WP_Error('no_class', 'API class not found');
    
    // Si no hay datos, o hay error, no renderizamos nada para los reels
    if (is_wp_error($feed) || empty($feed)) {
        return '';
    }
    
    // Filtrar solo los videos
    $reels = array_filter($feed, function($item) {
        return $item['media_type'] === 'VIDEO';
    });

    if (empty($reels)) {
        return '';
    }

    $settings = class_exists('Flacso_Main_Page_Settings') ? Flacso_Main_Page_Settings::get_section('reels') : [];
    $title = (string) apply_filters('flacso_main_page_reels_title', $settings['title'] ?? 'Reels Destacados');
    $section_id = 'flacso-reels-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <section class="flacso-home-block flacso-reels-section" aria-labelledby="<?php echo esc_attr($section_id); ?>">
        <div class="flacso-content-shell">
            <header class="text-center mb-5">
                <h2 id="<?php echo esc_attr($section_id); ?>"><?php echo esc_html($title); ?></h2>
            </header>
            
            <div class="flacso-reels-grid">
                <?php foreach ($reels as $reel) : ?>
                    <div class="flacso-reel-item">
                        <video 
                            controls 
                            preload="metadata" 
                            poster="<?php echo esc_url($reel['thumbnail_url']); ?>" 
                            class="flacso-reel-video"
                        >
                            <source src="<?php echo esc_url($reel['media_url']); ?>" type="video/mp4">
                            Tu navegador no soporta el formato de video.
                        </video>
                        <?php if (!empty($reel['caption'])): ?>
                            <div class="flacso-reel-caption">
                                <p><?php echo esc_html(wp_trim_words($reel['caption'], 20)); ?></p>
                                <a href="<?php echo esc_url($reel['permalink']); ?>" target="_blank" rel="noopener noreferrer" class="flacso-reel-link">
                                    Ver en Instagram <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
}
