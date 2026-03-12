<?php
// ==================================================
// SECCION INSTAGRAM - FLACSO URUGUAY
// ==================================================

if (!function_exists('flacso_section_instagram_render')) {
function flacso_section_instagram_render() {
    $profile_url = (string) apply_filters('flacso_main_page_instagram_profile_url', 'https://www.instagram.com/flacsouruguay/');
    if ($profile_url === '') {
        $profile_url = 'https://www.instagram.com/flacsouruguay/';
    }

    $embed_url = (string) apply_filters('flacso_main_page_instagram_embed_url', rtrim($profile_url, '/') . '/embed/');
    if ($embed_url === '') {
        $embed_url = 'https://www.instagram.com/flacsouruguay/embed/';
    }

    $title = (string) apply_filters('flacso_main_page_instagram_title', 'Seguinos en Instagram');
    $description = (string) apply_filters(
        'flacso_main_page_instagram_description',
        'Publicamos novedades institucionales, actividades academicas, lanzamientos y contenidos destacados de FLACSO Uruguay.'
    );
    $cta_label = (string) apply_filters('flacso_main_page_instagram_cta_label', 'Ir a @flacsouruguay');
    $reels_url = (string) apply_filters('flacso_main_page_instagram_reels_url', rtrim($profile_url, '/') . '/reels/');
    $reels_label = (string) apply_filters('flacso_main_page_instagram_reels_label', 'Ver Reels');

    $section_id = 'flacso-instagram-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <section class="flacso-instagram-section" aria-labelledby="<?php echo esc_attr($section_id); ?>">
        <div class="flacso-content-shell">
            <div class="flacso-instagram-grid">
                <div class="flacso-instagram-copy">
                    <p class="flacso-instagram-eyebrow">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                        @flacsouruguay
                    </p>
                    <h2 class="flacso-instagram-title" id="<?php echo esc_attr($section_id); ?>">
                        <?php echo esc_html($title); ?>
                    </h2>
                    <p class="flacso-instagram-description">
                        <?php echo esc_html($description); ?>
                    </p>
                    <div class="flacso-instagram-actions">
                        <a href="<?php echo esc_url($profile_url); ?>" class="flacso-btn flacso-btn-primary flacso-btn-anim" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($cta_label); ?>
                        </a>
                        <a href="<?php echo esc_url($reels_url); ?>" class="flacso-btn flacso-btn-outline flacso-btn-anim" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($reels_label); ?>
                        </a>
                    </div>
                </div>
                <div class="flacso-instagram-embed">
                    <iframe
                        src="<?php echo esc_url($embed_url); ?>"
                        loading="lazy"
                        title="<?php esc_attr_e('Perfil de Instagram de FLACSO Uruguay', 'flacso-main-page'); ?>"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowtransparency="true">
                    </iframe>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
}

