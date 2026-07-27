<?php
// ==================================================
// SECCION INSTAGRAM - FLACSO URUGUAY
// ==================================================

if (!function_exists('flacso_section_instagram_render')) {
function flacso_instagram_select_dynamic_feed_items(array $feed): array {
    $feed = array_values($feed);
    $total = count($feed);
    if ($total <= 0) {
        return [];
    }

    $display_count = min($total, 6);

    $display_count = (int) apply_filters('flacso_main_page_instagram_dynamic_display_count', $display_count, $feed);
    $display_count = max(1, min($total, $display_count));

    $selected = array_slice($feed, 0, $display_count);
    $has_video = array_reduce($selected, static function(bool $carry, array $item): bool {
        return $carry || (($item['media_type'] ?? '') === 'VIDEO');
    }, false);

    if (!$has_video) {
        foreach (array_slice($feed, $display_count) as $item) {
            if (($item['media_type'] ?? '') !== 'VIDEO') {
                continue;
            }
            $selected[$display_count - 1] = $item;
            break;
        }
    }

    return array_values($selected);
}

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
    $section_id = 'flacso-instagram-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <section class="flacso-instagram-section" aria-labelledby="<?php echo esc_attr($section_id); ?>">
        <div class="flacso-content-shell">
            <div class="flacso-instagram-panel">
                <div class="flacso-instagram-copy">
                    <p class="flacso-instagram-eyebrow">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                        Instagram oficial
                    </p>
                    <h2 class="flacso-instagram-title" id="<?php echo esc_attr($section_id); ?>">
                        <?php echo esc_html($title); ?>
                    </h2>
                    <p class="flacso-instagram-description">
                        <?php echo esc_html($description); ?>
                    </p>
                    <div class="flacso-instagram-tags" aria-label="<?php echo esc_attr__('Temas publicados en Instagram', 'flacso-main-page'); ?>">
                        <span>Novedades</span>
                        <span>Actividades</span>
                        <span>Agenda academica</span>
                    </div>
                    <div class="flacso-instagram-actions">
                        <a href="<?php echo esc_url($profile_url); ?>" class="flacso-instagram-button flacso-instagram-button--primary" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-instagram" aria-hidden="true"></i>
                            <?php echo esc_html($cta_label); ?>
                        </a>
                    </div>
                </div>
                <div class="flacso-instagram-showcase">
                    <div class="flacso-instagram-showcase-header">
                        <span class="flacso-instagram-live-dot" aria-hidden="true"></span>
                        <span>Feed oficial</span>
                        <strong>@flacsouruguay</strong>
                    </div>
                    <div class="flacso-instagram-embed">
                    <?php
                    $feed = class_exists('Flacso_Instagram_API') ? Flacso_Instagram_API::get_feed() : new WP_Error('no_class', 'API class not found');
                    if (!is_wp_error($feed) && is_array($feed)) {
                        $feed = flacso_instagram_select_dynamic_feed_items($feed);
                    }
                    
                    if (is_wp_error($feed) || empty($feed)) :
                        // Fallback to static card if error or no token
                    ?>
                    <a href="<?php echo esc_url($profile_url); ?>" target="_blank" rel="noopener noreferrer" class="flacso-instagram-static-card">
                        <div class="flacso-ig-static-icon">
                            <i class="bi bi-instagram"></i>
                        </div>
                        <h3>@flacsouruguay</h3>
                        <span class="flacso-ig-static-btn">Ver perfil &rarr;</span>
                    </a>
                    <?php else : ?>
                        <div class="flacso-instagram-api-feed">
                            <?php foreach ($feed as $item) : 
                                $caption_preview = wp_trim_words($item['caption'] ?? '', 15);
                                $thumbnail_image = (string) ($item['thumbnail_url'] ?? $item['media_url'] ?? '');
                                $media_type = (string) ($item['media_type'] ?? '');
                                $permalink = (string) ($item['permalink'] ?? $profile_url);
                            ?>
                                <a
                                    class="flacso-ig-feed-item"
                                    href="<?php echo esc_url($permalink); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="<?php echo esc_attr__('Ver esta publicación en Instagram', 'flacso-main-page'); ?>">
                                    <span class="flacso-ig-feed-image">
                                        <img src="<?php echo esc_url($thumbnail_image); ?>" alt="<?php echo esc_attr($caption_preview ?: __('Publicación de FLACSO Uruguay en Instagram', 'flacso-main-page')); ?>" loading="lazy" decoding="async">
                                        <?php if ($media_type === 'VIDEO') : ?>
                                            <span class="flacso-ig-feed-type-icon"><i class="bi bi-play-fill"></i></span>
                                        <?php elseif ($media_type === 'CAROUSEL_ALBUM') : ?>
                                            <span class="flacso-ig-feed-type-icon"><i class="bi bi-images"></i></span>
                                        <?php endif; ?>
                                        <span class="flacso-ig-feed-overlay">
                                            <i class="bi bi-instagram"></i>
                                            <span class="flacso-ig-feed-overlay-caption"><?php echo esc_html($caption_preview); ?></span>
                                        </span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
}
