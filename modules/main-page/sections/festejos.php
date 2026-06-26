<?php

if (!defined('ABSPATH')) {
    exit;
}

function flacso_section_festejos_render(): string {
    $settings = Flacso_Main_Page_Settings::get_settings();
    $festejos = $settings['festejos'] ?? [];
    $items = $festejos['items'] ?? [];
    
    // Filter out items without title or image
    $valid_items = array_filter($items, function($item) {
        return !empty($item['title']) && !empty($item['image']);
    });

    if (empty($valid_items)) {
        return '';
    }

    $title = (string) apply_filters('flacso_main_page_festejos_title', $festejos['title'] ?? 'Festejos de los 20 años de FLACSO');
    $description = (string) ($festejos['description'] ?? '');
    $section_id = 'flacso-festejos-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <section class="flacso-home-block flacso-festejos-section" aria-labelledby="<?php echo esc_attr($section_id); ?>">
        <div class="flacso-content-shell">
            <header class="flacso-festejos-header mb-5">
                <div class="flacso-festejos-header-content">
                    <h2 id="<?php echo esc_attr($section_id); ?>" class="flacso-festejos-title"><?php echo esc_html($title); ?></h2>
                    <?php if (!empty($description)): ?>
                        <p class="flacso-festejos-description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="flacso-festejos-controls">
                    <button type="button" class="flacso-festejos-arrow flacso-festejos-arrow--prev" aria-label="<?php esc_attr_e('Anterior', 'flacso-main-page'); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="flacso-festejos-arrow flacso-festejos-arrow--next" aria-label="<?php esc_attr_e('Siguiente', 'flacso-main-page'); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </header>
            
            <div class="flacso-festejos-carousel-wrapper">
                <div class="flacso-festejos-carousel" id="<?php echo esc_attr($section_id); ?>-carousel">
                    <?php foreach ($valid_items as $item) : 
                        $is_video = ($item['type'] === 'video' || $item['type'] === 'instagram');
                        $icon = $item['type'] === 'instagram' ? 'bi-instagram' : ($item['type'] === 'video' ? 'bi-play-circle-fill' : 'bi-arrow-right');
                    ?>
                        <div class="flacso-festejos-slide">
                            <a href="<?php echo esc_url($item['url']); ?>" class="flacso-festejos-card" <?php echo $is_video ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <div class="flacso-festejos-media">
                                    <img src="<?php echo esc_url($item['image']); ?>" alt="" loading="lazy">
                                    <?php if ($is_video): ?>
                                        <div class="flacso-festejos-play-overlay">
                                            <i class="bi <?php echo esc_attr($icon); ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flacso-festejos-content">
                                    <?php if ($item['type'] === 'instagram'): ?>
                                        <span class="flacso-festejos-badge"><i class="bi bi-instagram"></i> Instagram</span>
                                    <?php endif; ?>
                                    <h3 class="flacso-festejos-card-title"><?php echo esc_html($item['title']); ?></h3>
                                    <span class="flacso-festejos-action">
                                        <?php echo $is_video ? 'Ver contenido' : 'Leer más'; ?> <i class="bi bi-arrow-right-short"></i>
                                    </span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const carousel = document.getElementById('<?php echo esc_js($section_id); ?>-carousel');
                if (!carousel) return;
                
                const prevBtn = carousel.closest('.flacso-festejos-section').querySelector('.flacso-festejos-arrow--prev');
                const nextBtn = carousel.closest('.flacso-festejos-section').querySelector('.flacso-festejos-arrow--next');
                
                const scrollAmount = 320 + 24; // Card width + gap
                
                nextBtn.addEventListener('click', () => {
                    carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                });
                
                prevBtn.addEventListener('click', () => {
                    carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                });
                
                // Actualizar opacidad de botones
                const updateButtons = () => {
                    prevBtn.style.opacity = carousel.scrollLeft <= 10 ? '0.5' : '1';
                    prevBtn.style.pointerEvents = carousel.scrollLeft <= 10 ? 'none' : 'auto';
                    
                    const isAtEnd = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 10;
                    nextBtn.style.opacity = isAtEnd ? '0.5' : '1';
                    nextBtn.style.pointerEvents = isAtEnd ? 'none' : 'auto';
                };
                
                carousel.addEventListener('scroll', updateButtons, { passive: true });
                updateButtons(); // Init
            });
            </script>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
