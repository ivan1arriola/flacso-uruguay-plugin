<?php
// ==================================================
// SECCIÓN NOVEDADES - ESTILO UNIFICADO (Mobile-first + Bootstrap)
// ==================================================

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_section_novedades_render')) {
    function flacso_section_novedades_render()
    {
        $unique_id = 'novedades_' . wp_generate_password(6, false);

        $ajax_nonce = wp_create_nonce('flacso_section_novedades_nonce');
        $results = flacso_section_novedades_responsivas_render([
            'nonce' => $ajax_nonce,
        ]);

        ob_start();
        ?>
        <section class="flacso-novedades-section position-relative" id="<?php echo esc_attr($unique_id); ?>">
            <div class="flacso-content-shell">
                <div class="flacso-novedades-header text-center mb-4">
                    <h2 class="h3 mb-0">Novedades</h2>
                </div>
                <div class="flacso-novedades-results" data-novedades-wrapper>
                    <?php echo $results; ?>
                </div>
            </div>
        </section>
        <style>
            /* ==================================================
               ESTILOS EXTRA PARA LA SECCIÓN
            ================================================== */
            .flacso-novedades-section {
                background: transparent;
                color: var(--global-palette4);
                border-radius: 0;
                padding: clamp(0.8rem, 1.8vw, 1.2rem) 0;
                margin: 0; /* secciones pegadas */
                position: relative;
                overflow: visible;
            }

            .flacso-novedades-section::before {
                content: none;
            }

            .flacso-novedades-header h2 {
                color: var(--global-palette1);
                font-family: var(--global-heading-font-family);
            }

            @media (max-width: 768px) {
                .flacso-novedades-section {
                    padding: 0.7rem 0;
                }
            }
        </style>
        <script>
        (function() {
            const section = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!section) {
                return;
            }

            const loadingText = '<?php echo esc_js(__('Actualizando novedades…', 'flacso-main-page')); ?>';
            const errorText = '<?php echo esc_js(__('No pudimos cargar las novedades. Intenta nuevamente.', 'flacso-main-page')); ?>';
            let statusEl = null;

            const getListWrapper = () => section.querySelector('[data-novedades-list]');

            const mountStatus = (message) => {
                const wrapper = getListWrapper();
                if (!wrapper) {
                    return;
                }
                if (!statusEl) {
                    statusEl = document.createElement('div');
                    statusEl.className = 'novedades-ajax-status';
                    wrapper.prepend(statusEl);
                }
                statusEl.textContent = message;
                wrapper.classList.add('is-loading');
                statusEl.classList.remove('is-error');
            };

            const clearStatus = () => {
                const wrapper = getListWrapper();
                if (wrapper) {
                    wrapper.classList.remove('is-loading');
                }
                if (statusEl) {
                    statusEl.remove();
                    statusEl = null;
                }
            };

            const showError = () => {
                const wrapper = getListWrapper();
                if (!wrapper) {
                    return;
                }
                if (!statusEl) {
                    mountStatus('');
                }
                wrapper.classList.remove('is-loading');
                statusEl.textContent = errorText;
                statusEl.classList.add('is-error');
                setTimeout(() => {
                    if (statusEl) {
                        statusEl.remove();
                        statusEl = null;
                    }
                }, 2800);
            };

            const scrollToListTop = () => {
                const wrapper = getListWrapper();
                if (!wrapper) return;
                let headerOffset = 0;
                const header = document.querySelector('header.site-header, #masthead, .kadence-header');
                if (header) {
                    const styles = window.getComputedStyle(header);
                    const isFixed = /(fixed|sticky)/i.test(styles.position);
                    headerOffset = header.offsetHeight + (isFixed ? 16 : 0);
                }
                // Fallback si no hay header fijo
                if (!headerOffset) headerOffset = 110; // mayor que el anterior 80
                const top = wrapper.getBoundingClientRect().top + window.pageYOffset - headerOffset;
                window.scrollTo({ top: top > 0 ? top : 0, behavior: 'smooth' });
            };

            const updateMarkup = (html, metadata) => {
                const wrapper = getListWrapper();
                if (!wrapper) {
                    return;
                }
                wrapper.innerHTML = html;
                if (metadata) {
                    if (metadata.page) {
                        wrapper.dataset.currentPage = metadata.page;
                    }
                    if (metadata.total_pages) {
                        wrapper.dataset.totalPages = metadata.total_pages;
                    }
                    if (typeof metadata.search_term !== 'undefined') {
                        wrapper.dataset.searchTerm = metadata.search_term;
                    }
                }
            };

            const requestPage = (page, fallbackUrl = '') => {
                const wrapper = getListWrapper();
                if (!wrapper) {
                    return;
                }
                const ajaxUrl = wrapper.dataset.ajaxUrl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                const nonce = wrapper.dataset.nonce || '';
                const searchTerm = wrapper.dataset.searchTerm || '';
                const params = new URLSearchParams();
                params.append('action', 'flacso_section_novedades_paginate');
                params.append('nonce', nonce);
                params.append('page', String(page));
                if (searchTerm) {
                    params.append('search_term', searchTerm);
                }

                mountStatus(loadingText);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: params.toString(),
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data && data.success && data.data && data.data.html) {
                            updateMarkup(data.data.html, data.data);
                            clearStatus();
                            scrollToListTop();
                        } else {
                            throw new Error('invalid_response');
                        }
                    })
                    .catch(() => {
                        showError();
                        if (fallbackUrl) {
                            window.location.assign(fallbackUrl);
                        }
                    });
            };

            const escapeForRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            const extractPageFromHref = (href, pageVar) => {
                if (!href) {
                    return 0;
                }

                try {
                    const url = new URL(href, window.location.href);
                    const queryPage = parseInt(url.searchParams.get(pageVar) || '', 10);
                    if (queryPage > 0) {
                        return queryPage;
                    }

                    const prettyPageMatch = url.pathname.match(/\/page\/(\d+)\/?$/i);
                    if (prettyPageMatch && prettyPageMatch[1]) {
                        return parseInt(prettyPageMatch[1], 10) || 0;
                    }

                    if (pageVar) {
                        const namedPagePattern = new RegExp('/' + escapeForRegex(pageVar) + '/(\\d+)(?:/|$)', 'i');
                        const namedPageMatch = url.pathname.match(namedPagePattern);
                        if (namedPageMatch && namedPageMatch[1]) {
                            return parseInt(namedPageMatch[1], 10) || 0;
                        }
                    }
                } catch (error) {
                    return 0;
                }

                return 0;
            };

            section.addEventListener('click', (event) => {
                const link = event.target.closest('.novedades-pagination a');
                if (!link || !section.contains(link)) {
                    return;
                }
                const wrapper = getListWrapper();
                if (!wrapper) {
                    return;
                }
                const pageVar = wrapper.dataset.pageVar || 'nres_page';
                const currentPage = parseInt(wrapper.dataset.currentPage || '1', 10) || 1;
                const totalPages = parseInt(wrapper.dataset.totalPages || '1', 10) || 1;
                let nextPage = parseInt(link.dataset.page || '', 10);
                if (!nextPage) {
                    nextPage = extractPageFromHref(link.getAttribute('href') || '', pageVar);
                }

                if (!nextPage) {
                    const text = (link.textContent || '').toLowerCase();
                    if (text.includes('anterior')) {
                        nextPage = Math.max(1, currentPage - 1);
                    } else if (text.includes('siguiente')) {
                        nextPage = Math.min(totalPages, currentPage + 1);
                    } else {
                        const numericText = parseInt((link.textContent || '').trim(), 10);
                        if (numericText > 0) {
                            nextPage = numericText;
                        }
                    }
                }
                if (!nextPage) {
                    return;
                }
                event.preventDefault();
                if (nextPage === currentPage) {
                    return;
                }
                requestPage(nextPage, link.getAttribute('href') || '');
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_destacadas_render')) {
    function flacso_section_novedades_destacadas_render()
    {
        $posts = flacso_section_novedades_get_sticky_posts(6);
        if (empty($posts)) {
            return '';
        }

        $section_id = 'flacso-novedades-3d-' . wp_generate_password(6, false);

        ob_start(); ?>
        <section id="<?php echo esc_attr($section_id); ?>" class="flacso-novedades-3d">
            <div class="flacso-content-shell">
                <div class="flacso-novedades-3d__heading mb-4">
                    <h2 class="h3 mb-0"><?php esc_html_e('Novedades destacadas', 'flacso-main-page'); ?></h2>
                </div>

                <div class="flacso-novedades-3d__stage" data-flacso-3d-carousel>
                    <div class="flacso-novedades-3d__viewport">
                        <div class="flacso-novedades-3d__track">

                    <?php foreach ($posts as $index => $post) : ?>
                        <?php
                        $post_id = (int) $post->ID;
                        $post_title = wp_strip_all_tags(get_the_title($post_id));
                        $post_link = get_permalink($post_id);
                        $post_date_iso = get_the_date('c', $post_id);
                        $post_date_human = get_the_date('d/m/Y', $post_id);
                        $excerpt = has_excerpt($post_id)
                            ? get_the_excerpt($post_id)
                            : wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 24, '...');
                        $excerpt = wp_strip_all_tags((string) $excerpt);
                        $image_url = get_the_post_thumbnail_url($post_id, 'large');
                        if (!$image_url) {
                            $image_url = 'https://via.placeholder.com/1024x1024/e9edf2/1d3a72?text=Novedad';
                        }
                        $heading_id = 'novedad-title-' . $post_id;
                        $excerpt_id = 'novedad-excerpt-' . $post_id;
                        ?>
                            <article class="flacso-novedades-3d__card" role="article" aria-labelledby="<?php echo esc_attr($heading_id); ?>" aria-describedby="<?php echo esc_attr($excerpt_id); ?>">
                                <a href="<?php echo esc_url($post_link); ?>" class="flacso-novedades-3d__image-link" aria-label="<?php echo esc_attr(sprintf(__('Leer mas: %s', 'flacso-main-page'), $post_title)); ?>">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post_title); ?>" class="flacso-novedades-3d__image" loading="lazy">
                                </a>
                                <div class="flacso-novedades-3d__body">
                                    <h3 id="<?php echo esc_attr($heading_id); ?>" class="flacso-novedades-3d__title">
                                        <a href="<?php echo esc_url($post_link); ?>"><?php echo esc_html($post_title); ?></a>
                                    </h3>
                                    <div id="<?php echo esc_attr($excerpt_id); ?>" class="flacso-novedades-3d__excerpt">
                                        <?php echo esc_html($excerpt); ?>
                                    </div>
                                    <footer class="flacso-novedades-3d__meta">
                                        <time datetime="<?php echo esc_attr($post_date_iso); ?>"><?php echo esc_html($post_date_human); ?></time>
                                    </footer>
                                </div>
                            </article>
                    <?php endforeach; ?>

                        </div>
                    </div>
                </div>

                <div class="flacso-novedades-3d__dots" aria-label="<?php esc_attr_e('Selector de novedades', 'flacso-main-page'); ?>"></div>
            </div>
        </section>
        <style>
            .flacso-novedades-3d {
                --stage-radius: 28px;
                --card-radius: 24px;
                --card-width-desktop: 22.5rem;
                --card-width-tablet: 19rem;
                --card-width-mobile: 16.2rem;
                --card-height-desktop: clamp(33rem, 74vh, 39rem);
                --card-height-desktop: clamp(33rem, 74dvh, 39rem);
                --card-height-tablet: clamp(29rem, 70vh, 34rem);
                --card-height-tablet: clamp(29rem, 70dvh, 34rem);
                --card-height-mobile: clamp(24rem, 66vh, 28rem);
                --card-height-mobile: clamp(24rem, 66dvh, 28rem);

                padding-block: clamp(1rem, 2vw, 1.75rem);
                background: var(--global-palette9, #ffffff);
                font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            .flacso-main-page .flacso-home-block--novedades_destacadas {
                padding-block: clamp(1.25rem, 2.4vw, 2rem);
            }

            .flacso-novedades-3d .text-center {
                text-align: center;
            }

            .flacso-novedades-3d__heading {
                text-align: center;
                display: flex;
                justify-content: center;
            }

            .flacso-novedades-3d__heading h2 {
                color: var(--global-palette1, #1d3a72);
                margin-inline: auto;
            }

            .flacso-novedades-3d__stage {
                position: relative;
                border-radius: var(--stage-radius);
                padding: 0.35rem;
                background:
                    radial-gradient(circle at top, rgba(29, 58, 114, 0.09), rgba(29, 58, 114, 0.02) 45%, transparent 70%),
                    linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
                border: 1px solid rgba(29, 58, 114, 0.08);
                box-shadow: 0 1.25rem 3rem rgba(15, 26, 45, 0.08);
            }

            .flacso-novedades-3d__viewport {
                position: relative;
                min-height: calc(var(--card-height-desktop) + 1rem);
                perspective: 1800px;
                transform-style: preserve-3d;
                overflow: hidden;
                touch-action: pan-y;
                user-select: none;
            }

            .flacso-novedades-3d__track {
                position: relative;
                width: 100%;
                height: 100%;
                min-height: inherit;
            }

            .flacso-novedades-3d__card {
                position: absolute;
                top: 50%;
                left: 50%;
                width: min(var(--card-width-desktop), calc(100% - 1rem));
                height: var(--card-height-desktop);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                border-radius: var(--card-radius);
                background: #ffffff;
                border: 1px solid rgba(29, 58, 114, 0.12);
                box-shadow: 0 1rem 2.5rem rgba(15, 26, 45, 0.12);
                isolation: isolate;
                transform: translate(-50%, -50%);
                transform-origin: center center;
                transition:
                    transform 520ms cubic-bezier(0.22, 1, 0.36, 1),
                    opacity 520ms cubic-bezier(0.22, 1, 0.36, 1),
                    filter 520ms cubic-bezier(0.22, 1, 0.36, 1),
                    box-shadow 520ms cubic-bezier(0.22, 1, 0.36, 1);
                will-change: transform, opacity, filter;
                backface-visibility: hidden;
            }

            .flacso-novedades-3d__image-link {
                display: block;
                width: 100%;
                height: auto;
                aspect-ratio: 1 / 1;
                flex: 0 0 auto;
                background: #dce4ee;
                overflow: hidden;
                position: relative;
                z-index: 1;
                text-decoration: none;
                -webkit-user-drag: none;
                user-select: none;
                touch-action: pan-y;
            }

            .flacso-novedades-3d__image {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: transform 260ms ease;
                -webkit-user-drag: none;
                user-select: none;
                pointer-events: none;
            }

            .flacso-novedades-3d__card:hover .flacso-novedades-3d__image,
            .flacso-novedades-3d__card:focus-within .flacso-novedades-3d__image {
                transform: scale(1.04);
            }

            .flacso-novedades-3d__body {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                flex: 1 1 auto;
                min-height: 0;
                padding: 1rem;
                overflow: hidden;
                background: #ffffff;
                position: relative;
                z-index: 2;
            }

            .flacso-novedades-3d__title {
                margin: 0;
                color: var(--global-palette1, #1d3a72);
                font-size: 1.1rem;
                line-height: 1.3;
                font-weight: 700;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                overflow: hidden;
            }

            .flacso-novedades-3d__title a {
                color: inherit;
                text-decoration: none;
            }

            .flacso-novedades-3d__title a:hover,
            .flacso-novedades-3d__title a:focus-visible {
                text-decoration: underline;
            }

            .flacso-novedades-3d__excerpt {
                min-height: 0;
                color: var(--global-palette4, #2e2f34);
                font-size: 0.95rem;
                line-height: 1.55;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 4;
                overflow: hidden;
            }

            .flacso-novedades-3d__meta {
                padding-top: 0.55rem;
                border-top: 1px solid rgba(29, 58, 114, 0.08);
                color: var(--global-palette5, #7a8696);
                font-size: 0.88rem;
                margin-top: auto;
            }

            .flacso-novedades-3d__dots {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 0.5rem;
                flex-wrap: wrap;
                margin-top: 0.75rem;
            }

            .flacso-novedades-3d__dot {
                width: 0.5rem;
                height: 0.5rem;
                min-width: 0.5rem;
                min-height: 0.5rem;
                padding: 0;
                border: 0;
                border-radius: 999px;
                background: rgba(29, 58, 114, 0.24);
                cursor: pointer;
                transition: width 220ms ease, background 220ms ease;
                appearance: none;
                -webkit-appearance: none;
                line-height: 0;
                font-size: 0;
                flex: 0 0 auto;
            }

            .flacso-novedades-3d__dot.is-active {
                width: 0.95rem;
                min-width: 0.95rem;
                background: var(--global-palette1, #1d3a72);
            }

            @media (max-width: 991.98px) {
                .flacso-novedades-3d__stage {
                    padding-inline: 0.35rem;
                }

                .flacso-novedades-3d__viewport {
                    min-height: calc(var(--card-height-tablet) + 0.85rem);
                }

                .flacso-novedades-3d__card {
                    width: min(var(--card-width-tablet), calc(100% - 0.5rem));
                    height: var(--card-height-tablet);
                }
            }

            @media (max-width: 767.98px) {
                .flacso-main-page .flacso-home-block--novedades_destacadas {
                    padding-block: 1rem;
                }

                .flacso-novedades-3d__stage {
                    padding: 0.2rem;
                    border-radius: 22px;
                }

                .flacso-novedades-3d__viewport {
                    min-height: calc(var(--card-height-mobile) + 0.75rem);
                }

                .flacso-novedades-3d__card {
                    width: min(var(--card-width-mobile), calc(100% - clamp(2.8rem, 14vw, 4.4rem)));
                    height: var(--card-height-mobile);
                    border-radius: 18px;
                }

                .flacso-novedades-3d__body {
                    padding: 0.9rem;
                    gap: 0.7rem;
                }

                .flacso-novedades-3d__title {
                    font-size: 0.96rem;
                    line-height: 1.3;
                }

                .flacso-novedades-3d__excerpt {
                    font-size: 0.84rem;
                    line-height: 1.5;
                    -webkit-line-clamp: 4;
                }

                .flacso-novedades-3d__meta {
                    font-size: 0.76rem;
                }

                .flacso-novedades-3d__dot {
                    width: 0.44rem;
                    height: 0.44rem;
                    min-width: 0.44rem;
                    min-height: 0.44rem;
                }

                .flacso-novedades-3d__dot.is-active {
                    width: 0.82rem;
                    min-width: 0.82rem;
                }
            }

            @media (max-height: 860px) {
                .flacso-novedades-3d {
                    --card-height-desktop: clamp(31rem, 68vh, 36rem);
                    --card-height-desktop: clamp(31rem, 68dvh, 36rem);
                    --card-height-tablet: clamp(27rem, 66vh, 32rem);
                    --card-height-tablet: clamp(27rem, 66dvh, 32rem);
                    --card-height-mobile: clamp(23rem, 62vh, 26.8rem);
                    --card-height-mobile: clamp(23rem, 62dvh, 26.8rem);
                }

                .flacso-novedades-3d__body {
                    gap: 0.7rem;
                }

                .flacso-novedades-3d__excerpt {
                    -webkit-line-clamp: 4;
                }
            }
        </style>
        <script>
            (function () {
                function initCarousel() {
                    const section = document.getElementById('<?php echo esc_js($section_id); ?>');
                    if (!section || section.dataset.flacso3dInit === '1') return;
                    section.dataset.flacso3dInit = '1';

                    const root = section.querySelector('[data-flacso-3d-carousel]');
                    if (!root) return;

                    const viewport = root.querySelector('.flacso-novedades-3d__viewport');
                    const cards = Array.from(root.querySelectorAll('.flacso-novedades-3d__card'));
                    const dotsWrap = section.querySelector('.flacso-novedades-3d__dots');

                    if (!cards.length || !viewport) return;

                    let active = 0;
                    let autoplayId = null;
                    let resumeTimeoutId = null;
                    let startX = 0;
                    let currentX = 0;
                    let dragging = false;
                    let moved = false;

                    function mod(n, m) {
                        return ((n % m) + m) % m;
                    }

                    function getDistance(index, activeIndex, length) {
                        let raw = index - activeIndex;
                        if (raw > length / 2) raw -= length;
                        if (raw < -length / 2) raw += length;
                        return raw;
                    }

                    function getMode() {
                        if (window.innerWidth < 768) return 'mobile';
                        if (window.innerWidth < 992) return 'tablet';
                        return 'desktop';
                    }

                    function getVisualState(index, activeIndex, length) {
                        const dist = getDistance(index, activeIndex, length);
                        const abs = Math.abs(dist);
                        const mode = getMode();
                        const viewportWidth = viewport.clientWidth || window.innerWidth;
                        const sampleCard = cards[activeIndex] || cards[0];
                        const cardWidth = sampleCard ? sampleCard.offsetWidth : 320;
                        const sideSpace = Math.max(0, (viewportWidth - cardWidth) / 2);

                        let nearX, farX, nearScale, farScale, nearRotate, farRotate, farOpacity, maxVisibleDepth;

                        if (mode === 'mobile') {
                            const minPeekX = Math.max(56, Math.min(viewportWidth * 0.22, 88));
                            nearX = Math.max(Math.min(sideSpace * 0.9, 96), minPeekX);
                            farX = nearX + Math.max(34, Math.min(viewportWidth * 0.16, 52));
                            nearScale = 0.82;
                            farScale = 0.66;
                            nearRotate = dist > 0 ? -14 : 14;
                            farRotate = dist > 0 ? -24 : 24;
                            farOpacity = 0.18;
                            maxVisibleDepth = 1;
                        } else if (mode === 'tablet') {
                            nearX = Math.min(sideSpace * 0.9, 185);
                            farX = Math.min(sideSpace * 1.28, 270);
                            nearScale = 0.8;
                            farScale = 0.62;
                            nearRotate = dist > 0 ? -20 : 20;
                            farRotate = dist > 0 ? -30 : 30;
                            farOpacity = 0.2;
                            maxVisibleDepth = 2;
                        } else {
                            nearX = Math.min(sideSpace * 0.92, 280);
                            farX = Math.min(sideSpace * 1.34, 430);
                            nearScale = 0.78;
                            farScale = 0.56;
                            nearRotate = dist > 0 ? -24 : 24;
                            farRotate = dist > 0 ? -36 : 36;
                            farOpacity = 0.16;
                            maxVisibleDepth = 2;
                        }

                        if (abs === 0) {
                            return {
                                x: 0,
                                scale: 1,
                                rotateY: 0,
                                opacity: 1,
                                blur: 0,
                                zIndex: 30,
                                pointerEvents: 'auto',
                                shadow: '0 1.5rem 3.2rem rgba(15, 26, 45, 0.18)'
                            };
                        }

                        if (abs === 1) {
                            return {
                                x: dist > 0 ? nearX : -nearX,
                                scale: nearScale,
                                rotateY: nearRotate,
                                opacity: mode === 'mobile' ? 0.72 : 0.78,
                                blur: mode === 'mobile' ? 0.25 : 0.1,
                                zIndex: 20,
                                pointerEvents: 'auto',
                                shadow: '0 1.2rem 2.8rem rgba(15, 26, 45, 0.16)'
                            };
                        }

                        if (abs <= maxVisibleDepth) {
                            return {
                                x: dist > 0 ? farX : -farX,
                                scale: farScale,
                                rotateY: farRotate,
                                opacity: farOpacity,
                                blur: 1.6,
                                zIndex: 10,
                                pointerEvents: 'none',
                                shadow: '0 1.1rem 2.6rem rgba(15, 26, 45, 0.12)'
                            };
                        }

                        return {
                            x: dist > 0 ? farX + 80 : -(farX + 80),
                            scale: 0.6,
                            rotateY: dist > 0 ? -24 : 24,
                            opacity: 0,
                            blur: 6,
                            zIndex: 0,
                            pointerEvents: 'none',
                            shadow: '0 0.8rem 2rem rgba(15, 26, 45, 0.05)'
                        };
                    }

                    function buildDots() {
                        if (!dotsWrap) return;
                        dotsWrap.innerHTML = '';
                        cards.forEach((_, index) => {
                            const dot = document.createElement('button');
                            dot.type = 'button';
                            dot.className = 'flacso-novedades-3d__dot';
                            dot.setAttribute('aria-label', 'Ir a novedad ' + (index + 1));
                            dot.addEventListener('click', function () {
                                active = index;
                                update();
                                pauseTemporarily();
                            });
                            dotsWrap.appendChild(dot);
                        });
                    }

                    function update() {
                        window.requestAnimationFrame(() => {
                            cards.forEach((card, index) => {
                                const state = getVisualState(index, active, cards.length);
                                card.style.display = 'grid';
                                card.style.opacity = String(state.opacity);
                                card.style.zIndex = String(state.zIndex);
                                card.style.filter = 'blur(' + state.blur + 'px)';
                                card.style.boxShadow = state.shadow;
                                card.style.pointerEvents = state.pointerEvents;
                                card.style.transform =
                                    'translate(-50%, -50%) translateX(' + state.x + 'px) scale(' + state.scale + ') rotateY(' + state.rotateY + 'deg)';
                                card.setAttribute('aria-hidden', index === active ? 'false' : 'true');
                            });

                            if (dotsWrap) {
                                Array.from(dotsWrap.children).forEach((dot, index) => {
                                    dot.classList.toggle('is-active', index === active);
                                });
                            }
                        });
                    }

                    function next() {
                        active = mod(active + 1, cards.length);
                        update();
                    }

                    function prev() {
                        active = mod(active - 1, cards.length);
                        update();
                    }

                    function stopAutoplay() {
                        if (autoplayId) {
                            clearInterval(autoplayId);
                            autoplayId = null;
                        }
                    }

                    function startAutoplay() {
                        if (cards.length <= 1) return;
                        stopAutoplay();
                        autoplayId = window.setInterval(next, 4500);
                    }

                    function pauseTemporarily() {
                        stopAutoplay();
                        clearTimeout(resumeTimeoutId);
                        resumeTimeoutId = window.setTimeout(startAutoplay, 5500);
                    }

                    cards.forEach((card, index) => {
                        card.addEventListener('click', function (event) {
                            if (moved) {
                                event.preventDefault();
                                return;
                            }
                            if (index !== active) {
                                event.preventDefault();
                                active = index;
                                update();
                                pauseTemporarily();
                            }
                        });

                        card.addEventListener('dragstart', function (event) {
                            event.preventDefault();
                        });
                    });

                    root.addEventListener('mouseenter', stopAutoplay);
                    root.addEventListener('mouseleave', startAutoplay);

                    root.addEventListener('dragstart', function (event) {
                        event.preventDefault();
                    });

                    root.addEventListener('pointerdown', function (event) {
                        dragging = true;
                        moved = false;
                        startX = event.clientX;
                        currentX = event.clientX;
                        stopAutoplay();
                    });

                    window.addEventListener('pointermove', function (event) {
                        if (!dragging) return;
                        currentX = event.clientX;
                        if (Math.abs(currentX - startX) > 8) moved = true;
                    });

                    function endDrag() {
                        if (!dragging) return;
                        const delta = currentX - startX;
                        dragging = false;

                        if (delta <= -50) {
                            next();
                        } else if (delta >= 50) {
                            prev();
                        }

                        pauseTemporarily();

                        window.setTimeout(() => {
                            moved = false;
                        }, 50);
                    }

                    window.addEventListener('pointerup', endDrag);
                    window.addEventListener('pointercancel', endDrag);

                    window.addEventListener('resize', update);

                    if (cards.length <= 1) {
                        if (dotsWrap) dotsWrap.style.display = 'none';
                    }

                    buildDots();
                    update();
                    startAutoplay();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCarousel);
                }
                initCarousel();
            })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_buscador_render')) {
    function flacso_section_novedades_buscador_render($nonce = null) {
        $nonce = $nonce ?: wp_create_nonce('flacso_section_novedades_nonce');
        ob_start(); ?>
        <div class="flacso-novedades-search" role="search" aria-label="<?php esc_attr_e('Búsqueda de contenidos', 'flacso-main-page'); ?>">
            <form class="row g-3 align-items-start" action="<?php echo esc_url(home_url('/')); ?>" method="get" data-novedades-search-form data-nonce="<?php echo esc_attr($nonce); ?>" novalidate>
                <div class="col-12 col-lg-9">
                    <div class="input-group" data-field-wrapper>
                        <span class="input-group-text" id="flacso-search-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input type="search" name="s" class="form-control" placeholder="<?php esc_attr_e('Buscar…', 'flacso-main-page'); ?>" autocomplete="off" inputmode="search" aria-label="<?php esc_attr_e('Buscar en páginas y entradas', 'flacso-main-page'); ?>" aria-describedby="flacso-search-icon" />
                        <button type="reset" class="btn btn-outline-secondary d-none" data-clear aria-label="<?php esc_attr_e('Limpiar búsqueda', 'flacso-main-page'); ?>"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2 justify-content-lg-end">
                    <button type="submit" class="btn btn-success px-4" data-submit><?php esc_html_e('Buscar', 'flacso-main-page'); ?></button>
                    <button type="button" class="btn btn-link" data-reset-all><?php esc_html_e('Reiniciar', 'flacso-main-page'); ?></button>
                </div>
                <div class="col-12">
                    <div class="list-group novedades-search-results" data-novedades-search-results role="list" aria-live="polite" aria-atomic="false">
                        <div class="list-group-item text-muted py-3" data-placeholder>
                            <i class="bi bi-search me-2" aria-hidden="true"></i><?php esc_html_e('Teclea al menos dos letras para buscar en páginas y entradas.', 'flacso-main-page'); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        (function(){
            const form = document.querySelector('[data-novedades-search-form]');
            if (!form) {return;}
            const input = form.querySelector('input[name="s"]');
            const results = form.querySelector('[data-novedades-search-results]');
            const clearButton = form.querySelector('[data-clear]');
            const resetAllBtn = form.querySelector('[data-reset-all]');
            const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            const nonce = form.dataset.nonce || '';
            const fallbackBase = '<?php echo esc_js(home_url('/')); ?>';
            const fallbackLabel = '<?php echo esc_js(__('Ver resultados completos', 'flacso-main-page')); ?>';
            const searchEmptyText = '<?php echo esc_js(__('Sin resultados', 'flacso-main-page')); ?>';
            const searchErrorText = '<?php echo esc_js(__('Error de conexión', 'flacso-main-page')); ?>';
            let controller;let debounce;let keyboardIndex=-1;
            const placeholderMarkup = '<div class="list-group-item text-muted py-3"><i class="bi bi-search me-2" aria-hidden="true"></i><?php echo esc_js(__('Teclea al menos dos letras para buscar en páginas y entradas.', 'flacso-main-page')); ?></div>';
            const setPlaceholder = () => {if(!results)return;results.innerHTML = placeholderMarkup;form.classList.remove('has-results');};
            const showLoading = () => {if(!results)return;results.innerHTML='<div class="list-group-item py-3 fw-semibold"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span><?php echo esc_js(__('Buscando…', 'flacso-main-page')); ?></div>';};
            const buildFallbackUrl = (term) => {const params=new URLSearchParams();if(term){params.append('s',term);}const sep=fallbackBase.includes('?')?'&':'?';return fallbackBase+sep+params.toString();};
            const renderPlaceholderState=(term,message,extraClass)=>{if(!results)return;results.innerHTML='<div class="list-group-item py-3 novedades-search-'+extraClass+'"><i class="bi bi-search me-2" aria-hidden="true"></i>'+message+'<p class="mb-0"><a class="link-primary" href="'+buildFallbackUrl(term)+'">'+fallbackLabel+'</a></p></div>';form.classList.remove('has-results');};
            const getResultItems=()=>Array.from(results.querySelectorAll('.search-result-item'));
            const renderResults=(html,term)=>{if(!results)return;if(!html){renderPlaceholderState(term,searchEmptyText,'empty');return;}results.innerHTML=html;form.classList.add('has-results');keyboardIndex=-1;getResultItems().forEach((it,i)=>{it.setAttribute('role','listitem');it.dataset.index=i;});};
            const renderError=(term)=>{renderPlaceholderState(term,searchErrorText,'error');};
            const performSearch=(term)=>{if(!term||term.length<2){setPlaceholder();return;}if(controller){controller.abort();}controller=new AbortController();showLoading();const payload=new URLSearchParams();payload.append('action','flacso_section_novedades_search');payload.append('search_term',term);payload.append('nonce',nonce);fetch(ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:payload.toString(),signal:controller.signal}).then(r=>r.json()).then(data=>{if(data&&data.success&&data.data&&data.data.html){renderResults(data.data.html,term);}else{renderPlaceholderState(term,searchEmptyText,'empty');}}).catch(e=>{if(e.name==='AbortError'){return;}renderError(term);});};
            const updateClearVisibility=()=>{if(input.value.trim().length){clearButton.classList.remove('d-none');}else{clearButton.classList.add('d-none');}};
            input.addEventListener('input',()=>{updateClearVisibility();const term=input.value.trim();clearTimeout(debounce);if(term.length<2){setPlaceholder();return;}debounce=setTimeout(()=>performSearch(term),320);});
            form.addEventListener('submit',e=>{e.preventDefault();performSearch(input.value.trim());});
            if(clearButton){clearButton.addEventListener('click',()=>{input.value='';updateClearVisibility();setPlaceholder();input.focus();});}
            if(resetAllBtn){resetAllBtn.addEventListener('click',()=>{input.value='';updateClearVisibility();setPlaceholder();input.focus();});}
            const highlightItem=(newIndex)=>{const items=getResultItems();items.forEach(el=>el.classList.remove('active'));if(newIndex>=0&&newIndex<items.length){items[newIndex].classList.add('active');items[newIndex].focus({preventScroll:true});}};
            input.addEventListener('keydown',e=>{const items=getResultItems();if(!items.length)return;if(['ArrowDown','ArrowUp','End','Home'].includes(e.key)){e.preventDefault();}if(e.key==='ArrowDown'){keyboardIndex=Math.min(items.length-1,keyboardIndex+1);highlightItem(keyboardIndex);}else if(e.key==='ArrowUp'){keyboardIndex=Math.max(0,keyboardIndex-1);highlightItem(keyboardIndex);}else if(e.key==='Home'){keyboardIndex=0;highlightItem(keyboardIndex);}else if(e.key==='End'){keyboardIndex=items.length-1;highlightItem(keyboardIndex);}else if(e.key==='Enter'&&keyboardIndex>=0){items[keyboardIndex].click();}});
            results.addEventListener('keydown',e=>{const items=getResultItems();if(!items.length)return;const active=document.activeElement;const idx=items.indexOf(active);if(['ArrowDown','ArrowUp'].includes(e.key)){e.preventDefault();}if(e.key==='ArrowDown'){keyboardIndex=Math.min(items.length-1,idx+1);highlightItem(keyboardIndex);}else if(e.key==='ArrowUp'){keyboardIndex=Math.max(0,idx-1);highlightItem(keyboardIndex);}else if(e.key==='Escape'){input.focus();}});
            setPlaceholder();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_buscador_render_v2')) {
    function flacso_section_novedades_buscador_render_v2($nonce = null) {
        $nonce = $nonce ?: wp_create_nonce('flacso_section_novedades_nonce');
        $search_id = 'novedades-search-v2-' . wp_generate_password(6, false);
        ob_start(); ?>
        <div id="<?php echo esc_attr($search_id); ?>"
             class="flacso-novedades-search-v2"
             role="search"
             aria-label="<?php esc_attr_e('Buscar en novedades', 'flacso-main-page'); ?>"
             data-nonce="<?php echo esc_attr($nonce); ?>"
             data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <form class="flacso-novedades-search-v2__form" data-novedades-search-form-v2 novalidate>
                <label class="flacso-novedades-search-v2__label" for="<?php echo esc_attr($search_id . '-input'); ?>">
                    <?php esc_html_e('Buscar en novedades', 'flacso-main-page'); ?>
                </label>
                <div class="flacso-novedades-search-v2__controls">
                    <div class="flacso-novedades-search-v2__field">
                        <span class="flacso-novedades-search-v2__icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input
                            id="<?php echo esc_attr($search_id . '-input'); ?>"
                            type="search"
                            name="novedades_q"
                            class="flacso-novedades-search-v2__input"
                            placeholder="<?php esc_attr_e('Escribe tema, titulo o palabra clave', 'flacso-main-page'); ?>"
                            autocomplete="off"
                            inputmode="search"
                            aria-describedby="<?php echo esc_attr($search_id . '-help'); ?>" />
                    </div>
                    <button type="submit" class="flacso-novedades-search-v2__btn flacso-novedades-search-v2__btn--primary" data-search-submit>
                        <?php esc_html_e('Buscar', 'flacso-main-page'); ?>
                    </button>
                    <button type="button" class="flacso-novedades-search-v2__btn flacso-novedades-search-v2__btn--ghost" data-search-clear>
                        <?php esc_html_e('Limpiar', 'flacso-main-page'); ?>
                    </button>
                </div>
                <p id="<?php echo esc_attr($search_id . '-help'); ?>" class="flacso-novedades-search-v2__help">
                    <?php esc_html_e('Minimo 2 caracteres. El filtro se aplica al listado de novedades.', 'flacso-main-page'); ?>
                </p>
                <div class="flacso-novedades-search-v2__status" data-search-status aria-live="polite"></div>
            </form>
        </div>
        <style>
            .flacso-novedades-search-v2 {
                background: transparent;
                border: 0;
                border-radius: 0;
                padding: 0;
                box-shadow: none;
            }

            .flacso-novedades-search-v2__form {
                display: flex;
                flex-direction: column;
                gap: 0.65rem;
            }

            .flacso-novedades-search-v2__label {
                margin: 0;
                color: var(--global-palette1, #1d3a72);
                font-size: 0.9rem;
                font-weight: 800;
                letter-spacing: 0.03em;
                text-transform: uppercase;
            }

            .flacso-novedades-search-v2__controls {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto auto;
                gap: 0.55rem;
                align-items: stretch;
            }

            .flacso-novedades-search-v2__field {
                position: relative;
                display: flex;
                align-items: center;
            }

            .flacso-novedades-search-v2__icon {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #61718b;
                pointer-events: none;
                z-index: 2;
                line-height: 1;
            }

            .flacso-novedades-search-v2 .flacso-novedades-search-v2__field .flacso-novedades-search-v2__input {
                width: 100%;
                min-height: 48px;
                padding: 0.72rem 0.95rem 0.72rem 2.85rem !important;
                border: 1px solid rgba(29, 58, 114, 0.2);
                border-radius: 12px;
                background: #ffffff;
                color: var(--global-palette3, #0f1a2d);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .flacso-novedades-search-v2__input:focus {
                outline: none;
                border-color: rgba(29, 58, 114, 0.58);
                box-shadow: 0 0 0 3px rgba(29, 58, 114, 0.14);
            }

            .flacso-novedades-search-v2__btn {
                min-height: 46px;
                border-radius: 12px;
                border: 1px solid transparent;
                padding: 0.72rem 0.96rem;
                font-weight: 700;
                white-space: nowrap;
                transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
            }

            .flacso-novedades-search-v2__btn:disabled {
                opacity: 0.65;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }

            .flacso-novedades-search-v2__btn--primary {
                color: #ffffff;
                background: linear-gradient(135deg, var(--global-palette12, #1d3a72) 0%, var(--global-palette1, #274f9a) 100%);
                box-shadow: 0 8px 18px rgba(24, 57, 118, 0.24);
            }

            .flacso-novedades-search-v2__btn--primary:hover,
            .flacso-novedades-search-v2__btn--primary:focus-visible {
                transform: translateY(-1px);
                box-shadow: 0 12px 24px rgba(24, 57, 118, 0.28);
                outline: none;
            }

            .flacso-novedades-search-v2__btn--ghost {
                color: var(--global-palette1, #1d3a72);
                background: #f5f8fe;
                border-color: rgba(29, 58, 114, 0.22);
            }

            .flacso-novedades-search-v2__btn--ghost:hover,
            .flacso-novedades-search-v2__btn--ghost:focus-visible {
                transform: translateY(-1px);
                background: #ebf1fc;
                outline: none;
            }

            .flacso-novedades-search-v2__help {
                margin: 0;
                color: #627188;
                font-size: 0.86rem;
            }

            .flacso-novedades-search-v2__status {
                min-height: 1.2rem;
                font-size: 0.86rem;
                color: #60718a;
            }

            .flacso-novedades-search-v2__status.is-loading {
                color: var(--global-palette1, #1d3a72);
                font-weight: 600;
            }

            .flacso-novedades-search-v2__status.is-error {
                color: #b42318;
                font-weight: 600;
            }

            .flacso-novedades-search-v2__status.is-success {
                color: #167b4d;
                font-weight: 600;
            }

            @media (max-width: 991.98px) {
                .flacso-novedades-search-v2__controls {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <script>
        (function() {
            const root = document.getElementById('<?php echo esc_js($search_id); ?>');
            if (!root) {
                return;
            }

            const form = root.querySelector('[data-novedades-search-form-v2]');
            const input = root.querySelector('input[name="novedades_q"]');
            const submitBtn = root.querySelector('[data-search-submit]');
            const clearBtn = root.querySelector('[data-search-clear]');
            const statusEl = root.querySelector('[data-search-status]');
            const minChars = 2;
            const defaultAjaxUrl = root.dataset.ajaxUrl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            const defaultNonce = root.dataset.nonce || '';
            let debounceTimer = null;
            let requestController = null;
            let lastAppliedTerm = null;

            const messages = {
                loading: '<?php echo esc_js(__('Actualizando novedades...', 'flacso-main-page')); ?>',
                minChars: '<?php echo esc_js(__('Escribe al menos 2 caracteres para buscar.', 'flacso-main-page')); ?>',
                done: '<?php echo esc_js(__('Listado actualizado.', 'flacso-main-page')); ?>',
                cleared: '<?php echo esc_js(__('Filtro limpiado, mostrando todas las novedades.', 'flacso-main-page')); ?>',
                missingList: '<?php echo esc_js(__('No se encontro el listado de novedades para aplicar el filtro.', 'flacso-main-page')); ?>',
                failed: '<?php echo esc_js(__('No pudimos actualizar novedades. Intenta nuevamente.', 'flacso-main-page')); ?>',
            };

            const findListWrapper = () => document.querySelector('[data-novedades-list]');
            const normalizeTerm = (value) => (value || '').trim();

            const setStatus = (text, variant) => {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = text || '';
                statusEl.classList.remove('is-loading', 'is-error', 'is-success');
                if (variant) {
                    statusEl.classList.add(variant);
                }
            };

            const setBusy = (busy) => {
                if (!form) {
                    return;
                }
                form.setAttribute('aria-busy', busy ? 'true' : 'false');
                if (submitBtn) {
                    submitBtn.disabled = !!busy;
                }
                if (clearBtn) {
                    clearBtn.disabled = !!busy;
                }
            };

            const updateClearState = () => {
                if (!clearBtn || !input) {
                    return;
                }
                clearBtn.disabled = normalizeTerm(input.value) === '';
            };

            const scrollToList = (listWrapper) => {
                if (!listWrapper) {
                    return;
                }
                const top = listWrapper.getBoundingClientRect().top + window.pageYOffset - 110;
                window.scrollTo({ top: top > 0 ? top : 0, behavior: 'smooth' });
            };

            const applyResponse = (listWrapper, payload, term) => {
                if (!listWrapper || !payload || typeof payload.html !== 'string') {
                    return false;
                }

                listWrapper.innerHTML = payload.html;
                listWrapper.dataset.currentPage = String(payload.page || 1);
                listWrapper.dataset.totalPages = String(payload.total_pages || 1);
                listWrapper.dataset.searchTerm = term;
                return true;
            };

            const requestList = (rawTerm, options) => {
                const opts = options || {};
                const term = normalizeTerm(rawTerm);

                if (!opts.force && term === lastAppliedTerm) {
                    return;
                }

                if (term !== '' && term.length < minChars) {
                    setStatus(messages.minChars, null);
                    return;
                }

                const listWrapper = findListWrapper();
                if (!listWrapper) {
                    setStatus(messages.missingList, 'is-error');
                    return;
                }

                const ajaxUrl = listWrapper.dataset.ajaxUrl || defaultAjaxUrl;
                const nonce = listWrapper.dataset.nonce || defaultNonce;
                const params = new URLSearchParams();
                params.append('action', 'flacso_section_novedades_paginate');
                params.append('nonce', nonce);
                params.append('page', '1');
                params.append('search_term', term);

                if (requestController) {
                    requestController.abort();
                }
                requestController = new AbortController();

                setBusy(true);
                setStatus(messages.loading, 'is-loading');

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: params.toString(),
                    signal: requestController.signal,
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || !data.success || !data.data) {
                            throw new Error('invalid_response');
                        }

                        if (!applyResponse(listWrapper, data.data, term)) {
                            throw new Error('invalid_markup');
                        }

                        lastAppliedTerm = term;
                        setStatus(term ? messages.done : messages.cleared, 'is-success');
                        scrollToList(listWrapper);
                    })
                    .catch((error) => {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        setStatus(messages.failed, 'is-error');
                    })
                    .finally(() => {
                        setBusy(false);
                        updateClearState();
                    });
            };

            if (!form || !input) {
                return;
            }

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                requestList(input.value, { force: true });
            });

            input.addEventListener('input', function() {
                updateClearState();
                window.clearTimeout(debounceTimer);
                const term = normalizeTerm(input.value);

                if (term === '') {
                    debounceTimer = window.setTimeout(function() {
                        requestList('', { force: true });
                    }, 260);
                    return;
                }

                if (term.length < minChars) {
                    setStatus(messages.minChars, null);
                    if (lastAppliedTerm && lastAppliedTerm !== '') {
                        debounceTimer = window.setTimeout(function() {
                            requestList('', { force: true });
                        }, 220);
                    }
                    return;
                }

                debounceTimer = window.setTimeout(function() {
                    requestList(term, { force: false });
                }, 340);
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    updateClearState();
                    requestList('', { force: true });
                    input.focus();
                });
            }

            updateClearState();
            setStatus('', null);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_busqueda_render')) {
    function flacso_section_novedades_busqueda_render()
    {
        $unique_id = 'novedades-busqueda-' . wp_generate_password(6, false);
        $search = function_exists('flacso_section_novedades_buscador_render_v2')
            ? flacso_section_novedades_buscador_render_v2()
            : flacso_section_novedades_buscador_render();

        ob_start(); ?>
        <section id="<?php echo esc_attr($unique_id); ?>" class="flacso-novedades-busqueda">
            <div class="flacso-content-shell">
                <?php echo $search; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}


if (!function_exists('flacso_section_novedades_responsivas_render')) {
    function flacso_section_novedades_responsivas_render(array $args = [])
    {
        $defaults = [
            'page_var' => 'nres_page',
            'page' => null,
            'search_term' => '',
            'nonce' => '',
            'ajax_url' => '',
            'with_styles' => true,
        ];
        $args = wp_parse_args($args, $defaults);

        $settings = Flacso_Main_Page_Settings::get_section('novedades');
        $posts_per_page = $settings['per_page'] ?? 12;
        $category = 'novedades';
        $page_var = sanitize_key($args['page_var']);
        $current_page = isset($args['page']) && $args['page'] ? max(1, intval($args['page'])) : (isset($_GET[$page_var]) ? max(1, intval($_GET[$page_var])) : 1);
        $search_term = sanitize_text_field($args['search_term']);
        $nonce = $args['nonce'] ?: wp_create_nonce('flacso_section_novedades_nonce');
        $ajax_url = $args['ajax_url'] ?: admin_url('admin-ajax.php');

        $posts_data = flacso_section_novedades_get_posts($posts_per_page, $category, $current_page, $search_term);
        $list_markup = flacso_section_novedades_render_list_markup($posts_data, [
            'page_var' => $page_var,
            'current_page' => $current_page,
            'search_term' => $search_term,
        ]);

        ob_start(); ?>
        <div class="novedades-responsivas"
             data-novedades-list
             data-page-var="<?php echo esc_attr($page_var); ?>"
             data-current-page="<?php echo esc_attr($current_page); ?>"
             data-total-pages="<?php echo esc_attr($posts_data['total_pages'] ?? 1); ?>"
             data-nonce="<?php echo esc_attr($nonce); ?>"
             data-ajax-url="<?php echo esc_url($ajax_url); ?>"
             data-search-term="<?php echo esc_attr($search_term); ?>">
            <?php echo $list_markup; ?>
        </div>
        <?php if (!empty($args['with_styles'])) : ?>
        <style>
            /* Grid Bootstrap - 1 columna móvil, 2 tablet, 3 desktop */
            .novedades-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            @media (min-width: 768px) {
                .novedades-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (min-width: 1024px) {
                .novedades-grid {
                    grid-template-columns: repeat(3, 1fr);
                }
            }

            .novedades-grid .col {
                width: 100%;
            }

            .novedades-grid .card {
                background: var(--global-palette9, #ffffff);
                border: 1px solid #f0f1f3;
                border-radius: 12px;
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                height: 100%;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            }

            .novedades-grid .card:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            }

            .novedades-grid .card-img-container {
                position: relative;
                background-color: #f8f9fa;
                width: 100%;
                aspect-ratio: 1/1;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .novedades-grid .card-img-container img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .novedades-grid .card-img-link {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0, 0, 0, 0);
                transition: background 0.3s ease;
            }

            .novedades-grid .card-img-link:hover {
                background: rgba(0, 0, 0, 0.2);
            }

            .card-body {
                display: flex;
                flex-direction: column;
                flex-grow: 1;
                padding: 20px;
            }

            .novedades-grid .h5 {
                font-size: 1.05rem;
                font-weight: 700;
                line-height: 1.3;
                margin-bottom: 10px;
                color: var(--global-palette3, #0f1a2d);
            }

            .novedades-grid .h5 a {
                color: inherit;
                text-decoration: none;
            }

            .novedades-grid .h5 a:hover {
                color: var(--global-palette1, #1d3a72);
            }

            .novedades-grid .card-text {
                color: var(--global-palette4, #6b7280);
                font-size: 0.95rem;
                line-height: 1.5;
                margin-bottom: 15px;
                flex-grow: 1;
            }

            .novedades-grid footer {
                color: var(--global-palette5, #9ca3af) !important;
                font-size: 0.85rem;
                padding-top: 12px;
                border-top: 1px solid #e5e7eb !important;
                margin-top: auto;
            }

            .novedades-grid footer i {
                color: var(--global-palette1, #1d3a72);
                font-size: 0.9rem;
            }

            .novedades-pagination {
                margin-top: clamp(1.25rem, 3vw, 2rem);
            }

            .novedades-pagination .pagination {
                gap: 0.45rem !important;
            }

            .novedades-pagination .page-item {
                margin: 0;
            }

            .novedades-pagination .page-item:not(:first-child) .page-link {
                margin-left: 0;
            }

            .novedades-pagination .page-link {
                min-width: 2.75rem;
                height: 2.75rem;
                padding: 0 0.95rem;
                border-radius: 12px;
                border: 1px solid rgba(29, 58, 114, 0.16);
                background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
                color: var(--global-palette3, #0f1a2d);
                font-size: 1.08rem;
                font-weight: 600;
                line-height: 1;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 6px 16px rgba(15, 26, 45, 0.08);
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
                text-decoration: none !important;
                background-image: none !important;
            }

            .novedades-pagination .page-link.is-nav {
                min-width: 8.4rem;
                font-size: 1rem;
                font-weight: 700;
                padding: 0 1.05rem;
            }

            .novedades-pagination .page-link.is-dots {
                min-width: 2.75rem;
                padding: 0 0.65rem;
                letter-spacing: 0.06em;
            }

            .novedades-pagination .page-item:not(.active):not(.disabled) .page-link:hover,
            .novedades-pagination .page-item:not(.active):not(.disabled) .page-link:focus-visible {
                transform: translateY(-2px);
                border-color: rgba(29, 58, 114, 0.34);
                color: var(--global-palette1, #1d3a72);
                box-shadow: 0 10px 24px rgba(15, 26, 45, 0.14);
                outline: none;
            }

            .novedades-pagination .page-item.active .page-link {
                border-color: var(--global-palette12, #1d3a72);
                background: linear-gradient(135deg, var(--global-palette12, #1d3a72) 0%, var(--global-palette1, #274f9a) 100%);
                color: #fff;
                box-shadow: 0 12px 28px rgba(24, 57, 118, 0.28);
                transform: translateY(-1px);
            }

            .novedades-pagination .page-item.disabled .page-link {
                border-color: rgba(148, 163, 184, 0.3);
                background: #e8ecf3;
                color: #64748b;
                box-shadow: none;
                cursor: default;
            }

            @media (max-width: 768px) {
                .novedades-pagination .page-link {
                    min-width: 2.45rem;
                    height: 2.45rem;
                    padding: 0 0.65rem;
                    font-size: 0.98rem;
                }

                .novedades-pagination .page-link.is-nav {
                    min-width: 6.8rem;
                    font-size: 0.92rem;
                    padding: 0 0.85rem;
                }

                .novedades-pagination .page-link.is-dots {
                    min-width: 2.45rem;
                    padding: 0 0.55rem;
                }
            }

            @media (max-width: 768px) {
                .novedades-grid .card-body {
                    padding: 16px;
                }

                .novedades-grid .h5 {
                    font-size: 0.95rem;
                }
            }

            @media (max-height: 860px) {
                .novedades-grid .card-body {
                    padding: 16px;
                }

                .novedades-grid .card-text {
                    display: -webkit-box;
                    -webkit-line-clamp: 3;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
            }
        </style>
        <?php endif;
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_render_list_markup')) {
    function flacso_section_novedades_render_list_markup(array $posts_data, array $args = []): string
    {
        $defaults = [
            'page_var' => 'nres_page',
            'current_page' => 1,
            'search_term' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        $page_var = sanitize_key($args['page_var']);
        $current_page = max(1, intval($args['current_page']));
        $search_term = sanitize_text_field($args['search_term']);
        $posts = $posts_data['posts'] ?? [];
        $total_pages = max(1, intval($posts_data['total_pages'] ?? 1));

        ob_start();

        if (empty($posts)) {
            echo '<div class="alert alert-info text-center p-4 mb-4">' . esc_html__('No hay novedades para mostrar.', 'flacso-main-page') . '</div>';
        } else {
            ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 novedades-grid">
                <?php foreach ($posts as $index => $post) {
                    echo flacso_section_novedades_render_post_card($post, false, $index);
                } ?>
            </div>
            <?php
        }

        if ($total_pages > 1) {
            $pagination = paginate_links([
                'base' => esc_url(add_query_arg($page_var, '%#%')),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => '&laquo; ' . esc_html__('Anterior', 'flacso-main-page'),
                'next_text' => esc_html__('Siguiente', 'flacso-main-page') . ' &raquo;',
                'type' => 'array',
            ]);

            if (!empty($pagination)) : ?>
                <nav class="novedades-pagination mt-4" role="navigation" aria-label="<?php echo esc_attr__('Paginación de novedades', 'flacso-main-page'); ?>">
                    <ul class="pagination justify-content-center flex-wrap gap-2">
                        <?php
                        $extract_page_from_href = static function (string $href, string $page_var): int {
                            if ($href === '') {
                                return 0;
                            }

                            $decoded_href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
                            $parts = wp_parse_url($decoded_href);
                            if (!is_array($parts)) {
                                return 0;
                            }

                            if (!empty($parts['query'])) {
                                parse_str((string) $parts['query'], $query_args);
                                if (!empty($query_args[$page_var])) {
                                    return max(1, (int) $query_args[$page_var]);
                                }
                            }

                            $path = isset($parts['path']) ? (string) $parts['path'] : '';
                            if ($path !== '') {
                                if (preg_match('#/page/(\\d+)/?$#i', $path, $matches)) {
                                    return max(1, (int) $matches[1]);
                                }

                                $named_pattern = '#/' . preg_quote($page_var, '#') . '/(\\d+)(?:/|$)#i';
                                if (preg_match($named_pattern, $path, $matches)) {
                                    return max(1, (int) $matches[1]);
                                }
                            }

                            return 0;
                        };

                        foreach ($pagination as $link) {
                            $is_current = strpos($link, 'current') !== false;
                            $is_dots = strpos($link, 'dots') !== false;
                            $is_disabled = strpos($link, 'disabled') !== false || $is_dots;
                            $is_prev = strpos($link, 'prev') !== false;
                            $is_next = strpos($link, 'next') !== false;
                            $text = trim(strip_tags($link));
                            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                            $href = '';
                            if (preg_match('/href="([^"]+)"/', $link, $matches)) {
                                $href = $matches[1];
                            }
                            $classes = ['page-item'];
                            if ($is_current) {
                                $classes[] = 'active';
                            }
                            if ($is_disabled && !$is_current) {
                                $classes[] = 'disabled';
                            }
                            if ($is_prev) {
                                $classes[] = 'is-prev';
                            }
                            if ($is_next) {
                                $classes[] = 'is-next';
                            }
                            if ($is_dots) {
                                $classes[] = 'is-dots';
                            }
                            $aria_current = $is_current ? ' aria-current="page"' : '';
                            if ($href) {
                                $target_page = $extract_page_from_href($href, $page_var);
                                if ($target_page <= 0 && $is_prev) {
                                    $target_page = max(1, $current_page - 1);
                                }
                                if ($target_page <= 0 && $is_next) {
                                    $target_page = min($total_pages, $current_page + 1);
                                }
                                if ($target_page <= 0 && preg_match('/^\d+$/', $text)) {
                                    $target_page = (int) $text;
                                }

                                if ($is_prev) {
                                    $aria_label = esc_attr__('Ir a la página anterior', 'flacso-main-page');
                                } elseif ($is_next) {
                                    $aria_label = esc_attr__('Ir a la página siguiente', 'flacso-main-page');
                                } else {
                                    $aria_label = esc_attr(sprintf(__('Ir a página %s', 'flacso-main-page'), $text));
                                }

                                $link_classes = ['page-link'];
                                if ($is_prev || $is_next) {
                                    $link_classes[] = 'is-nav';
                                } elseif ($is_dots) {
                                    $link_classes[] = 'is-dots';
                                }

                                $data_page_attr = $target_page > 0 ? ' data-page="' . esc_attr((string) $target_page) . '"' : '';
                                $link_element = sprintf(
                                    '<a class="%s" href="%s"%s aria-label="%s">%s</a>',
                                    esc_attr(implode(' ', $link_classes)),
                                    esc_url($href),
                                    $data_page_attr,
                                    $aria_label,
                                    esc_html($text)
                                );
                            } else {
                                $state_class = $is_current ? 'active' : 'disabled';
                                if ($is_prev || $is_next) {
                                    $extra_class = ' is-nav';
                                } elseif ($is_dots) {
                                    $extra_class = ' is-dots';
                                } else {
                                    $extra_class = '';
                                }
                                $link_element = sprintf('<span class="page-link %s%s" tabindex="-1">%s</span>', esc_attr($state_class), esc_attr($extra_class), esc_html($text));
                            }
                            echo '<li class="' . esc_attr(implode(' ', $classes)) . '"' . $aria_current . '>' . $link_element . '</li>';
                        }
                        ?>
                    </ul>
                </nav>
            <?php
            endif;
        }

        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_admin_menu_render')) {
    function flacso_section_novedades_admin_menu_render()
    {
        if (!current_user_can('edit_posts')) {
            return '';
        }

        $nonce = wp_create_nonce('flacso_section_novedades_admin');
        $highlighted = flacso_section_novedades_get_sticky_posts(6);

        ob_start(); ?>
        <div class="flacso-novedades-admin" data-nonce="<?php echo esc_attr($nonce); ?>" aria-label="<?php esc_attr_e('Administrar noticias fijadas', 'flacso-main-page'); ?>">
            <div class="flacso-novedades-admin-highlights">
                <h3><?php esc_html_e('Destacadas', 'flacso-main-page'); ?></h3>
                <?php if (!empty($highlighted)) : ?>
                <div class="flacso-novedades-admin-list" data-novedades-order-list>
                    <?php foreach ($highlighted as $index => $post) : ?>
                        <?php echo flacso_section_novedades_admin_render_post_item($post, $index); ?>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                    <p class="text-muted"><?php esc_html_e('No hay novedades destacadas.', 'flacso-main-page'); ?></p>
                <?php endif; ?>
            </div>
            <div class="flacso-novedades-admin-search" role="search">
                <label class="screen-reader-text" for="flacso-novedades-search"><?php esc_html_e('Buscar novedades', 'flacso-main-page'); ?></label>
                <div class="flacso-novedades-search-field">
                    <input id="flacso-novedades-search"
                           data-novedades-search-input
                           type="search"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Buscar novedades…', 'flacso-main-page'); ?>"
                           autocomplete="off">
                    <span class="flacso-novedades-search-hint"><?php esc_html_e('Escribe para buscar artículos', 'flacso-main-page'); ?></span>
                </div>
                <div class="flacso-novedades-search-results" data-novedades-search-results>
                    <p class="text-muted small"><?php esc_html_e('Resultados aparecerán aquí y podrás fijarlos o desfijarlos.', 'flacso-main-page'); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_admin_render_post_item')) {
    function flacso_section_novedades_admin_render_post_item($post, int $order_index = 0, bool $show_order_controls = true)
    {
        $is_sticky = is_sticky($post->ID);
        $title = esc_html(get_the_title($post->ID));
        ob_start(); ?>
        <div class="flacso-novedades-admin-item" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <div class="flacso-novedades-admin-item__label">
                <?php if ($show_order_controls) : ?>
                    <span class="flacso-novedades-admin-order" aria-hidden="true"><?php echo esc_html($order_index + 1); ?></span>
                <?php endif; ?>
                <span class="flacso-novedades-admin-title"><?php echo $title; ?></span>
            </div>
            <div class="flacso-novedades-admin-actions">
                <?php if ($show_order_controls) : ?>
                    <button type="button"
                            class="flacso-novedades-order-action"
                            data-order-action="up"
                            aria-label="<?php esc_attr_e('Mover arriba', 'flacso-main-page'); ?>">
                        <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                    </button>
                    <button type="button"
                            class="flacso-novedades-order-action"
                            data-order-action="down"
                            aria-label="<?php esc_attr_e('Mover abajo', 'flacso-main-page'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
                <button type="button"
                        class="flacso-novedades-pin-toggle <?php echo $is_sticky ? 'is-sticky' : ''; ?>"
                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                        data-sticky="<?php echo $is_sticky ? '1' : '0'; ?>"
                        aria-pressed="<?php echo $is_sticky ? 'true' : 'false'; ?>">
                    <?php echo $is_sticky ? esc_html__('Desfijar', 'flacso-main-page') : esc_html__('Fijar', 'flacso-main-page'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
if (!function_exists('flacso_section_novedades_get_manageable_posts')) {
    function flacso_section_novedades_get_manageable_posts($limit = 10)
    {
        return get_posts([
            'post_type'      => 'post',
            'posts_per_page' => $limit,
            'category_name'  => 'novedades',
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'ignore_sticky_posts' => 1,
        ]);
    }
}

// Asignación estable de color por categoría usando hash del slug
if (!function_exists('flacso_novedades_get_category_badge_style')) {
    function flacso_novedades_get_category_badge_style(string $slug): string
    {
        static $cache = [];
        if (isset($cache[$slug])) {
            return $cache[$slug];
        }
        // Paleta (bg, text) basada en variables globales para consistencia de marca
        $palette = [
            ['var(--global-palette12)', 'var(--global-palette9)'], // azul
            ['var(--global-palette14)', 'var(--global-palette9)'], // naranja vivo
            ['var(--global-palette11)', 'var(--global-palette9)'], // verde
            ['var(--global-palette15)', 'var(--global-palette3)'], // amarillo suave
            ['var(--global-palette2)', 'var(--global-palette3)'],  // énfasis secundario
            ['var(--global-palette1)', 'var(--global-palette9)'],  // azul oscuro
        ];
        $hash = hexdec(substr(md5($slug), 0, 8));
        $index = $hash % count($palette);
        [$bg, $color] = $palette[$index];
        // Sombra ligera adaptada al fondo (usar alpha sobre color principal si es hex conocido)
        $shadow = '0 4px 14px rgba(0,0,0,.12)';
        $style = "background:$bg;color:$color;border:1px solid rgba(0,0,0,.08);box-shadow:$shadow;";
        $cache[$slug] = $style;
        return $style;
    }
}

if (!function_exists('flacso_section_novedades_render_post_card')) {
    function flacso_section_novedades_render_post_card($post, $is_sticky = false, $index = 0)
    {
        $categories = get_the_category($post->ID);
        $other_categories = array_filter($categories, function ($cat) {
            return !in_array(strtolower($cat->name), ['novedades', 'archivo'], true);
        });

        $has_thumbnail = has_post_thumbnail($post->ID);
        $image_id = $has_thumbnail ? get_post_thumbnail_id($post->ID) : 0;
        $image_url = $has_thumbnail ? (wp_get_attachment_image_src($image_id, 'large')[0] ?? '') : '';
        $post_title = esc_html(get_the_title($post->ID));
        $post_link = get_permalink($post->ID);
        $post_date = get_the_date('', $post->ID);
        $modified_date = get_the_modified_date('', $post->ID);

        $main_category = !empty($other_categories) ? reset($other_categories) : null;
        // Se mantiene clase genérica; el color se aplica inline para consistencia entre renders.
        $category_style = '';
        if ($main_category) {
            $category_style = flacso_novedades_get_category_badge_style($main_category->slug);
        }

        $image_is_square = false;
        if ($has_thumbnail && $image_url) {
            $metadata = wp_get_attachment_metadata($image_id);
            if ($metadata && isset($metadata['width'], $metadata['height'])) {
                $ratio = $metadata['width'] / max(1, $metadata['height']);
                $image_is_square = ($ratio >= 0.9 && $ratio <= 1.1);
            }
        }

        $delay = $index * 80;
        $heading_id = 'novedad-title-' . $post->ID;
        $excerpt_id = 'novedad-excerpt-' . $post->ID;

        ob_start(); ?>
        <div class="col">
            <article
                class="card h-100"
                style="transition-delay: <?php echo intval($delay); ?>ms"
                role="article"
                aria-labelledby="<?php echo esc_attr($heading_id); ?>"
                aria-describedby="<?php echo esc_attr($excerpt_id); ?>"
            >
                <div class="card-img-container">
                    <?php if ($has_thumbnail && $image_url) : ?>
                        <a href="<?php echo esc_url($post_link); ?>" class="card-img-link" aria-label="<?php echo esc_attr(sprintf(__('Leer mas: %s', 'flacso-main-page'), $post_title)); ?>">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post_title); ?>" loading="lazy">
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($post_link); ?>" class="card-img-link" aria-label="<?php echo esc_attr(sprintf(__('Leer mas: %s', 'flacso-main-page'), $post_title)); ?>">
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="background:var(--global-palette8);">
                                <i class="bi bi-newspaper display-1" aria-hidden="true"></i>
                                <span class="visually-hidden"><?php esc_html_e('Noticia sin imagen', 'flacso-main-page'); ?></span>
                            </div>
                        </a>
                    <?php endif; ?>

                </div>
                <div class="card-body">
                    <h3 id="<?php echo esc_attr($heading_id); ?>" class="h5">
                        <a href="<?php echo esc_url($post_link); ?>">
                            <?php echo $post_title; ?>
                        </a>
                    </h3>
                    <div id="<?php echo esc_attr($excerpt_id); ?>" class="card-text">
                        <?php
                        $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post->ID)), 20);
                        echo esc_html($excerpt);
                        ?>
                    </div>
                    <footer class="d-flex justify-content-between align-items-center small">
                        <span class="d-flex align-items-center gap-1">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <time datetime="<?php echo esc_attr(get_the_date('c', $post->ID)); ?>"><?php echo esc_html($post_date); ?></time>
                        </span>
                    </footer>
                </div>
            </article>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('flacso_section_novedades_get_saved_highlight_order')) {
    function flacso_section_novedades_get_saved_highlight_order(): array
    {
        $order = get_option('flacso_section_novedades_highlight_order', []);
        if (!is_array($order)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $order)));
    }
}

if (!function_exists('flacso_section_novedades_get_sticky_posts')) {
    function flacso_section_novedades_get_sticky_posts($limit = 4)
    {
        $stickies = get_option('sticky_posts', []);
        if (!is_array($stickies)) {
            $stickies = [];
        }

        $stickies = array_values(array_unique(array_filter(array_map('absint', $stickies))));
        if (empty($stickies)) {
            return [];
        }

        // Mantener solo IDs que correspondan a posts publicados de la categoria novedades.
        $valid_ids = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_name' => 'novedades',
            'post__in' => $stickies,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'post__in',
            'ignore_sticky_posts' => 1,
        ]);

        if (empty($valid_ids)) {
            return [];
        }

        $stickies = array_values(array_intersect($stickies, $valid_ids));
        if (empty($stickies)) {
            return [];
        }

        $query = new WP_Query([
            'post__in' => $stickies,
            'posts_per_page' => max(1, (int) $limit),
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_name' => 'novedades',
            'ignore_sticky_posts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return $query->have_posts() ? $query->posts : [];
    }
}

if (!function_exists('flacso_section_novedades_toggle_sticky_ajax')) {
    function flacso_section_novedades_toggle_sticky_ajax()
    {
        check_ajax_referer('flacso_section_novedades_admin', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(__('Noticia inválida.', 'flacso-main-page'), 400);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Noticia inválida.', 'flacso-main-page'), 404);
        }

        if ($post->post_type !== 'post' || !has_category('novedades', $post_id)) {
            wp_send_json_error(__('Solo se pueden fijar publicaciones de la categoría Novedades.', 'flacso-main-page'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('No tienes permisos para modificar esta noticia.', 'flacso-main-page'), 403);
        }

        $sticky = isset($_POST['sticky']) && $_POST['sticky'] === '1';
        if (!function_exists('stick_post')) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }

        if ($sticky) {
            stick_post($post_id);
        } else {
            unstick_post($post_id);
        }

        $saved_order = flacso_section_novedades_get_saved_highlight_order();
        if ($sticky) {
            if (!in_array($post_id, $saved_order, true)) {
                $saved_order[] = $post_id;
            }
        } else {
            $saved_order = array_values(array_filter($saved_order, function ($id) use ($post_id) {
                return $id !== $post_id;
            }));
        }
        update_option('flacso_section_novedades_highlight_order', $saved_order);

        wp_send_json_success([
            'is_sticky' => is_sticky($post_id) ? 1 : 0,
            'message' => $sticky ? __('Noticia fijada.', 'flacso-main-page') : __('Noticia desfijada.', 'flacso-main-page'),
        ]);
    }
}

if (!function_exists('flacso_section_novedades_save_highlight_order_ajax')) {
    function flacso_section_novedades_save_highlight_order_ajax()
    {
        check_ajax_referer('flacso_section_novedades_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('No tienes permisos para reordenar novedades.', 'flacso-main-page'), 403);
        }

        $order = isset($_POST['order']) ? array_map('absint', (array) $_POST['order']) : [];
        $stickies = get_option('sticky_posts', []);
        if (!is_array($stickies)) {
            $stickies = [];
        }
        $stickies = array_values(array_unique(array_map('absint', $stickies)));

        // Solo conservar sticky IDs validos para el carrusel de novedades.
        if (!empty($stickies)) {
            $stickies = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'category_name' => 'novedades',
                'post__in' => $stickies,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'post__in',
                'ignore_sticky_posts' => 1,
            ]);
            $stickies = is_array($stickies) ? array_values(array_map('absint', $stickies)) : [];
        }

        $filtered_order = [];
        foreach ($order as $post_id) {
            if (in_array($post_id, $stickies, true)) {
                $filtered_order[] = $post_id;
            }
        }
        foreach ($stickies as $post_id) {
            if (!in_array($post_id, $filtered_order, true)) {
                $filtered_order[] = $post_id;
            }
        }

        update_option('flacso_section_novedades_highlight_order', $filtered_order);

        wp_send_json_success([
            'order' => $filtered_order,
        ]);
    }
}

if (!function_exists('flacso_section_novedades_admin_search_ajax')) {
    function flacso_section_novedades_admin_search_ajax()
    {
        check_ajax_referer('flacso_section_novedades_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('No tienes permisos para buscar novedades.', 'flacso-main-page'), 403);
        }

        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        
        // El panel de destacadas administra solo posts de la categoria novedades.
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'category_name'  => 'novedades',
        ];

        if ($search_term !== '') {
            $args['s'] = $search_term;
        }

        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            wp_send_json_success([
                'html' => '<p class="text-muted small">' . esc_html__('No se encontraron resultados.', 'flacso-main-page') . '</p>',
            ]);
        }

        ob_start();
        echo '<div class="flacso-novedades-admin-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $post_item = get_post();
            if ($post_item) {
                echo flacso_section_novedades_admin_render_post_item($post_item, 0, false);
            }
        }
        echo '</div>';
        wp_reset_postdata();

        wp_send_json_success(['html' => ob_get_clean()]);
    }
}

if (!function_exists('flacso_section_novedades_search_ajax')) {
    function flacso_section_novedades_search_ajax()
    {
        check_ajax_referer('flacso_section_novedades_nonce', 'nonce');
        $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
        if (strlen($search_term) < 2) {
            wp_send_json_success(['html' => '']);
        }
        // Buscar en todos los posts y páginas publicados
        $args = [
            'post_type' => ['post','page'],
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'ignore_sticky_posts' => 1,
            's' => $search_term,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            wp_send_json_success(['html' => '']);
        }

        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $title = get_the_title($post_id);
            $permalink = get_permalink($post_id);
            $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: 'https://via.placeholder.com/200x200?text=FLACSO';
            $date = get_the_date('', $post_id);
            $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 22);
            // Determinar ícono según tipo de contenido (post/page). $post_item no está disponible aquí, usar get_post_type($post_id)
            $icon = (get_post_type($post_id) === 'page') ? 'bi bi-file' : 'bi bi-file-text';
            ?>
            <a class="search-result-item" href="<?php echo esc_url($permalink); ?>">
                <img class="search-result-thumb" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                <div>
                    <div class="search-result-meta">
                        <i class="<?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
                        <time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>"><?php echo esc_html($date); ?></time>
                    </div>
                    <h4 class="h6 mb-1"><?php echo esc_html($title); ?></h4>
                    <p class="search-result-excerpt mb-0"><?php echo esc_html($excerpt); ?></p>
                </div>
            </a>
            <?php
        }
        wp_reset_postdata();

        wp_send_json_success(['html' => ob_get_clean()]);
    }
}

if (!function_exists('flacso_section_novedades_paginate_ajax')) {
    function flacso_section_novedades_paginate_ajax()
    {
        check_ajax_referer('flacso_section_novedades_nonce', 'nonce');

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
        $settings = Flacso_Main_Page_Settings::get_section('novedades');
        $per_page = $settings['per_page'] ?? 12;

        $posts_data = flacso_section_novedades_get_posts($per_page, 'novedades', $page, $search_term);
        $html = flacso_section_novedades_render_list_markup($posts_data, [
            'page_var' => 'nres_page',
            'current_page' => $page,
            'search_term' => $search_term,
        ]);

        wp_send_json_success([
            'html' => $html,
            'page' => $page,
            'total_pages' => $posts_data['total_pages'] ?? 1,
            'search_term' => $search_term,
        ]);
    }
}

add_action('wp_ajax_flacso_section_novedades_search', 'flacso_section_novedades_search_ajax');
add_action('wp_ajax_nopriv_flacso_section_novedades_search', 'flacso_section_novedades_search_ajax');
add_action('wp_ajax_flacso_section_novedades_paginate', 'flacso_section_novedades_paginate_ajax');
add_action('wp_ajax_nopriv_flacso_section_novedades_paginate', 'flacso_section_novedades_paginate_ajax');
add_action('wp_ajax_flacso_section_novedades_toggle_sticky', 'flacso_section_novedades_toggle_sticky_ajax');
add_action('wp_ajax_flacso_section_novedades_admin_search', 'flacso_section_novedades_admin_search_ajax');
add_action('wp_ajax_flacso_section_novedades_save_highlight_order', 'flacso_section_novedades_save_highlight_order_ajax');

if (!function_exists('flacso_section_novedades_get_posts')) {
    function flacso_section_novedades_get_posts($posts_per_page, $category, $paged, string $search_term = '')
    {
        $stickies = get_option('sticky_posts', []);
        if (!is_array($stickies)) {
            $stickies = [];
        }

        $query_args = [
            'posts_per_page'      => $posts_per_page,
            'category_name'       => $category,
            'paged'               => $paged,
            'ignore_sticky_posts' => 1,
            'post_status'         => 'publish',
            'post__not_in'        => $stickies,
        ];

        if ($search_term !== '') {
            $query_args['s'] = $search_term;
        }

        $query = new WP_Query($query_args);

        return [
            'posts'       => $query->have_posts() ? $query->posts : [],
            'total_pages' => $query->max_num_pages ?: 1,
        ];
    }
}


