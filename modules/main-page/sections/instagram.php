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

    $display_count = min($total, 5);

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
    $modal_id = $section_id . '-modal';

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
                                $caption_full = trim((string) ($item['caption'] ?? ''));
                                $preview_image = (string) ($item['media_url'] ?? $item['thumbnail_url'] ?? '');
                                $thumbnail_image = (string) ($item['thumbnail_url'] ?? $preview_image);
                                $media_type = (string) ($item['media_type'] ?? '');
                                if ($media_type === 'VIDEO') {
                                    $preview_image = $thumbnail_image;
                                }
                                if ($preview_image === '') {
                                    $preview_image = $thumbnail_image;
                                }
                            ?>
                                <button
                                    type="button"
                                    class="flacso-ig-feed-item"
                                    aria-label="<?php echo esc_attr__('Ampliar publicacion de Instagram', 'flacso-main-page'); ?>"
                                    data-flacso-ig-open
                                    data-image="<?php echo esc_url($preview_image); ?>"
                                    data-thumbnail="<?php echo esc_url($thumbnail_image); ?>"
                                    data-permalink="<?php echo esc_url($item['permalink'] ?? $profile_url); ?>"
                                    data-caption="<?php echo esc_attr($caption_full); ?>"
                                    data-media-type="<?php echo esc_attr($media_type); ?>"
                                    data-modal-id="<?php echo esc_attr($modal_id); ?>">
                                    <span class="flacso-ig-feed-image" style="background-image: url('<?php echo esc_url($thumbnail_image); ?>');">
                                        <?php if ($media_type === 'VIDEO') : ?>
                                            <span class="flacso-ig-feed-type-icon"><i class="bi bi-play-fill"></i></span>
                                        <?php elseif ($media_type === 'CAROUSEL_ALBUM') : ?>
                                            <span class="flacso-ig-feed-type-icon"><i class="bi bi-images"></i></span>
                                        <?php endif; ?>
                                        <span class="flacso-ig-feed-overlay">
                                            <i class="bi bi-arrows-fullscreen"></i>
                                            <span class="flacso-ig-feed-overlay-caption"><?php echo esc_html($caption_preview); ?></span>
                                        </span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="flacso-instagram-modal" id="<?php echo esc_attr($modal_id); ?>" hidden aria-hidden="true">
                            <div class="flacso-instagram-modal__backdrop" data-flacso-ig-close></div>
                            <div class="flacso-instagram-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
                                <button type="button" class="flacso-instagram-modal__close" data-flacso-ig-close aria-label="<?php echo esc_attr__('Cerrar vista ampliada', 'flacso-main-page'); ?>">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                </button>
                                <figure class="flacso-instagram-modal__figure">
                                    <img src="" alt="" data-flacso-ig-modal-image>
                                </figure>
                                <div class="flacso-instagram-modal__content">
                                    <p class="flacso-instagram-modal__kicker" id="<?php echo esc_attr($modal_id); ?>-title">@flacsouruguay</p>
                                    <p class="flacso-instagram-modal__caption" data-flacso-ig-modal-caption></p>
                                    <a href="<?php echo esc_url($profile_url); ?>" class="flacso-instagram-button flacso-instagram-button--primary" target="_blank" rel="noopener noreferrer" data-flacso-ig-modal-link>
                                        <i class="bi bi-instagram" aria-hidden="true"></i>
                                        <?php esc_html_e('Ver en Instagram', 'flacso-main-page'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script>
    (function () {
        var root = document.currentScript.previousElementSibling;
        if (!root || !root.classList || !root.classList.contains('flacso-instagram-section')) {
            var title = document.getElementById('<?php echo esc_js($section_id); ?>');
            root = title ? title.closest('.flacso-instagram-section') : null;
        }
        if (!root) {
            return;
        }

        var activeModal = null;
        var lastTrigger = null;

        function closeModal() {
            if (!activeModal) {
                return;
            }

            activeModal.hidden = true;
            activeModal.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('flacso-instagram-modal-open');

            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }

            activeModal = null;
        }

        function openModal(trigger) {
            var modalId = trigger.getAttribute('data-modal-id');
            var modal = modalId ? document.getElementById(modalId) : null;
            if (!modal) {
                return;
            }

            var image = modal.querySelector('[data-flacso-ig-modal-image]');
            var caption = modal.querySelector('[data-flacso-ig-modal-caption]');
            var link = modal.querySelector('[data-flacso-ig-modal-link]');
            var closeButton = modal.querySelector('[data-flacso-ig-close]');
            var imageUrl = trigger.getAttribute('data-image') || trigger.getAttribute('data-thumbnail') || '';
            var captionText = trigger.getAttribute('data-caption') || '';
            var permalink = trigger.getAttribute('data-permalink') || '<?php echo esc_js($profile_url); ?>';
            var mediaType = trigger.getAttribute('data-media-type') || '';

            if (image) {
                image.src = imageUrl;
                image.alt = captionText || (mediaType === 'VIDEO' ? 'Video de Instagram de FLACSO Uruguay' : 'Publicacion de Instagram de FLACSO Uruguay');
            }

            if (caption) {
                caption.textContent = captionText || 'Publicacion reciente de FLACSO Uruguay.';
            }

            if (link) {
                link.href = permalink;
            }

            lastTrigger = trigger;
            activeModal = modal;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('flacso-instagram-modal-open');

            if (closeButton && typeof closeButton.focus === 'function') {
                closeButton.focus();
            }
        }

        root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-flacso-ig-open]');
            if (trigger) {
                event.preventDefault();
                openModal(trigger);
                return;
            }

            if (event.target.closest('[data-flacso-ig-close]')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }());
    </script>
    <?php
    return ob_get_clean();
}
}
