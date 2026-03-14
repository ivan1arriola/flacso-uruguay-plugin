<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_posgrados_vigentes')) {
    function flacso_posgrados_vigentes(): array {
        return [
            12330 => ['EDUTIC', 'Maestria'],
            12336 => ['MESYP', 'Maestria'],
            12343 => ['MG', 'Maestria'],
            12310 => ['EAPET', 'Especializacion'],
            12316 => ['EGCCD', 'Especializacion'],
            12278 => ['DEPPI', 'Diplomado'],
            14444 => ['DESI', 'Diplomado'],
            12282 => ['DEVBG', 'Diplomado'],
            12288 => ['DEVNNA', 'Diplomado'],
            13202 => ['DCCH', 'Diploma'],
            12295 => ['DAVIA', 'Diploma'],
            12299 => ['DG', 'Diploma'],
            20668 => ['IAPE', 'Diploma'],
            12302 => ['DIDYP', 'Diploma'],
            14657 => ['DSMSYT', 'Diploma'],
        ];
    }
}

if (!function_exists('flacso_listar_paginas_collect_items')) {
    /**
     * @param WP_Query $query
     * @param array<int,array<int,string>> $vigentes
     * @return array<int,array<string,mixed>>
     */
    function flacso_listar_paginas_collect_items(WP_Query $query, array $vigentes, bool $mostrar_inactivos): array {
        $items = [];

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $vigente = array_key_exists($id, $vigentes);

            if (!$vigente && !$mostrar_inactivos) {
                continue;
            }

            $created = (int) get_post_time('U', true, $id);
            $items[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'thumb' => get_the_post_thumbnail_url($id, 'large') ?: 'https://via.placeholder.com/960x960/e7ebf2/1d3a72?text=FLACSO',
                'url' => get_permalink($id),
                'vigente' => $vigente,
                'es_nuevo' => (current_time('timestamp') - $created) < (30 * DAY_IN_SECONDS),
                'abbr' => $vigente ? (string) ($vigentes[$id][0] ?? '') : '',
                'tipo' => $vigente ? (string) ($vigentes[$id][1] ?? '') : '',
            ];
        }

        wp_reset_postdata();
        return $items;
    }
}

if (!function_exists('flacso_listar_paginas_render_grid')) {
    /**
     * @param array<int,array<string,mixed>> $items
     */
    function flacso_listar_paginas_render_grid(array $items): string {
        ob_start();
        ?>
        <style>
            .flacso-grid{display:grid;grid-template-columns:1fr;gap:1.2rem;padding:1rem 0}
            @media (min-width:768px){.flacso-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
            @media (min-width:1024px){.flacso-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
            .flacso-grid .flacso-card{display:flex;flex-direction:column;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 10px 24px rgba(13,26,48,.14);text-decoration:none;color:inherit}
            .flacso-grid .flacso-card__img{aspect-ratio:1/1;background-size:cover;background-position:center;position:relative}
            .flacso-grid .flacso-card__img::after{content:\"\";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.08),rgba(0,0,0,.65))}
            .flacso-grid .flacso-card__title{position:absolute;left:1rem;right:1rem;bottom:1rem;color:#fff;font-weight:700;z-index:1}
            .flacso-grid .flacso-card__content{padding:.8rem 1rem}
            .flacso-grid .flacso-badges{display:flex;gap:.4rem;flex-wrap:wrap}
            .flacso-grid .flacso-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .64rem;border-radius:999px;background:#173f7d;color:#fff;font-size:.74rem;font-weight:600}
            .flacso-grid .flacso-badge.nuevo{background:#f4c700;color:#162640}
            .flacso-grid .flacso-card.inactivo{opacity:.48;filter:grayscale(.65);pointer-events:none}
        </style>
        <div class="flacso-grid">
            <?php foreach ($items as $item) : ?>
                <?php $active = !empty($item['vigente']); ?>
                <a class="flacso-card <?php echo $active ? '' : 'inactivo'; ?>" href="<?php echo esc_url((string) $item['url']); ?>">
                    <div class="flacso-card__img" style="background-image:url('<?php echo esc_url((string) $item['thumb']); ?>');">
                        <div class="flacso-card__title"><?php echo esc_html((string) $item['title']); ?></div>
                    </div>
                    <div class="flacso-card__content">
                        <div class="flacso-badges">
                            <?php if ($active && !empty($item['es_nuevo'])) : ?><span class="flacso-badge nuevo"><i class="bi bi-stars"></i><?php esc_html_e('Nuevo', 'flacso-main-page'); ?></span><?php endif; ?>
                            <?php if ($active && !empty($item['abbr'])) : ?><span class="flacso-badge"><i class="bi bi-hash"></i><?php echo esc_html((string) $item['abbr']); ?></span><?php endif; ?>
                            <?php if ($active && !empty($item['tipo'])) : ?><span class="flacso-badge"><i class="bi bi-mortarboard"></i><?php echo esc_html((string) $item['tipo']); ?></span><?php endif; ?>
                            <?php if (!$active) : ?><span class="flacso-badge"><i class="bi bi-x-circle"></i><?php esc_html_e('No vigente', 'flacso-main-page'); ?></span><?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_listar_paginas_render_catalogo_3d')) {
    /**
     * @param array<int,array<string,mixed>> $items
     */
    function flacso_listar_paginas_render_catalogo_3d(array $items): string {
        $instance_id = function_exists('wp_unique_id') ? wp_unique_id('flacso-catalogo-3d-') : ('flacso-catalogo-3d-' . wp_rand(1000, 9999));
        $help_id = $instance_id . '-help';
        $status_id = $instance_id . '-status';

        ob_start();
        ?>
        <section id="<?php echo esc_attr($instance_id); ?>" class="flacso-catalogo-3d" data-flacso-catalogo-3d role="region" aria-label="<?php esc_attr_e('Catalogo de programas', 'flacso-main-page'); ?>">
            <p id="<?php echo esc_attr($help_id); ?>" class="flacso-catalogo-3d__sr-only">
                <?php esc_html_e('Usa flecha izquierda y derecha para navegar. Enter abre el programa activo.', 'flacso-main-page'); ?>
            </p>
            <div class="flacso-catalogo-3d__viewport" tabindex="0" aria-label="<?php esc_attr_e('Catalogo 3D de programas', 'flacso-main-page'); ?>" aria-describedby="<?php echo esc_attr($help_id . ' ' . $status_id); ?>">
                <div class="flacso-catalogo-3d__track">
                    <?php foreach ($items as $index => $item) : ?>
                        <?php $active = !empty($item['vigente']); ?>
                        <a class="flacso-catalogo-3d__card<?php echo $active ? '' : ' is-disabled'; ?>" href="<?php echo esc_url($active ? (string) $item['url'] : '#'); ?>" data-index="<?php echo esc_attr((string) $index); ?>">
                            <div class="flacso-catalogo-3d__media" style="background-image:url('<?php echo esc_url((string) $item['thumb']); ?>');">
                                <div class="flacso-catalogo-3d__overlay"></div>
                                <h3 class="flacso-catalogo-3d__title"><?php echo esc_html((string) $item['title']); ?></h3>
                            </div>
                            <div class="flacso-catalogo-3d__meta">
                                <?php if ($active && !empty($item['es_nuevo'])) : ?><span class="flacso-catalogo-3d__badge is-new"><i class="bi bi-stars"></i><?php esc_html_e('Nuevo', 'flacso-main-page'); ?></span><?php endif; ?>
                                <?php if ($active && !empty($item['abbr'])) : ?><span class="flacso-catalogo-3d__badge"><i class="bi bi-hash"></i><?php echo esc_html((string) $item['abbr']); ?></span><?php endif; ?>
                                <?php if ($active && !empty($item['tipo'])) : ?><span class="flacso-catalogo-3d__badge"><i class="bi bi-mortarboard"></i><?php echo esc_html((string) $item['tipo']); ?></span><?php endif; ?>
                                <?php if (!$active) : ?><span class="flacso-catalogo-3d__badge"><i class="bi bi-x-circle"></i><?php esc_html_e('No vigente', 'flacso-main-page'); ?></span><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flacso-catalogo-3d__dots" data-catalogo-dots aria-label="<?php esc_attr_e('Selector de programas', 'flacso-main-page'); ?>"></div>
            <p id="<?php echo esc_attr($status_id); ?>" class="flacso-catalogo-3d__sr-only" data-catalogo-status aria-live="polite" aria-atomic="true"></p>
        </section>
        <style>
            #<?php echo esc_html($instance_id); ?>{padding:1rem 0 1.4rem;--flacso-cat-accent:#173f7d;--flacso-cat-deep:#102c58}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport{position:relative;min-height:clamp(420px,58vw,610px);height:clamp(420px,58vw,610px);perspective:1800px;touch-action:pan-y;outline:none;background:linear-gradient(180deg,#e4e9f1 0%,#d2d9e3 58%,#cbd3de 100%);border-radius:22px;border:1px solid rgba(16,44,88,.13);overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::-webkit-scrollbar{height:8px}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::-webkit-scrollbar-thumb{background:rgba(16,44,88,.24);border-radius:999px}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::before{content:"";position:absolute;left:-15%;right:-15%;top:-48%;height:74%;background:radial-gradient(ellipse at center,rgba(255,255,255,.82) 0%,rgba(255,255,255,0) 68%);pointer-events:none}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::after{content:"";position:absolute;left:8%;right:8%;bottom:12%;height:28px;border-radius:999px;background:radial-gradient(ellipse at center,rgba(16,44,88,.22) 0%,rgba(16,44,88,0) 70%);pointer-events:none}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport.is-dragging{cursor:grabbing}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__track{position:relative;display:flex;gap:1rem;align-items:stretch;padding:1rem;min-height:100%}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card{--w:min(390px,84vw);position:relative;flex:0 0 var(--w);width:var(--w);display:flex;flex-direction:column;text-decoration:none;color:inherit;background:linear-gradient(180deg,#ffffff 0%,#f5f7fb 100%);border-radius:20px;overflow:hidden;border:1px solid rgba(16,44,88,.16);box-shadow:0 20px 45px rgba(12,24,45,.24);transform-origin:center center;transition:transform .56s cubic-bezier(.2,.84,.26,1),opacity .28s,filter .28s,box-shadow .28s;will-change:transform,opacity;scroll-snap-align:start}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:12px;background:linear-gradient(180deg,var(--flacso-cat-deep) 0%,var(--flacso-cat-accent) 100%);opacity:.95;z-index:3}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card::after{content:"";position:absolute;left:14px;right:14px;bottom:-16px;height:20px;background:radial-gradient(ellipse at center,rgba(16,44,88,.32) 0%,rgba(16,44,88,0) 72%);pointer-events:none}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card[data-active="1"]{box-shadow:0 26px 56px rgba(12,24,45,.28)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card.is-disabled{filter:grayscale(.75) saturate(.72)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__media{position:relative;aspect-ratio:4/5;background-size:cover;background-position:center}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,20,43,.04) 20%,rgba(6,20,43,.78) 95%)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__title{position:absolute;left:1.1rem;right:1rem;bottom:.95rem;margin:0;color:#fff;font-size:clamp(1.02rem,.92rem + .58vw,1.46rem);line-height:1.13;font-weight:700;text-shadow:0 2px 12px rgba(0,0,0,.55)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__meta{padding:.88rem .95rem .95rem 1.15rem;display:flex;gap:.4rem;flex-wrap:wrap;border-top:1px solid rgba(16,44,88,.1);background:linear-gradient(180deg,#f7f9fd 0%,#edf2f9 100%)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__badge{display:inline-flex;align-items:center;gap:.3rem;padding:.34rem .65rem;border-radius:999px;background:var(--flacso-cat-accent);color:#fff;font-size:.74rem;font-weight:600;line-height:1}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__badge.is-new{background:#f4c700;color:#162640}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dots{margin-top:.92rem;display:flex;flex-wrap:wrap;justify-content:center;gap:.56rem}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dot{border:0;width:.62rem;min-width:.62rem;height:.62rem;border-radius:999px;background:rgba(16,44,88,.28);padding:0;transition:all .2s ease;cursor:pointer}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dot.is-active{background:rgba(16,44,88,.94);width:.96rem;min-width:.96rem}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__sr-only{position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;overflow:hidden !important;clip:rect(0,0,0,0) !important;white-space:nowrap !important;border:0 !important}
            #<?php echo esc_html($instance_id); ?>.is-ready .flacso-catalogo-3d__viewport{overflow:hidden;scroll-snap-type:none;cursor:default}
            #<?php echo esc_html($instance_id); ?>.is-ready .flacso-catalogo-3d__track{position:absolute;inset:0;display:block;padding:0;transform-style:preserve-3d}
            #<?php echo esc_html($instance_id); ?>.is-ready .flacso-catalogo-3d__card{position:absolute;left:50%;top:50%;flex:none;scroll-snap-align:none}
        </style>
        <script>
            (function () {
                const root = document.getElementById('<?php echo esc_js($instance_id); ?>');
                if (!root || root.dataset.catalogoInit === '1') return;
                root.dataset.catalogoInit = '1';

                const viewport = root.querySelector('.flacso-catalogo-3d__viewport');
                const cards = Array.from(root.querySelectorAll('.flacso-catalogo-3d__card'));
                const dotsWrap = root.querySelector('[data-catalogo-dots]');
                const statusEl = root.querySelector('[data-catalogo-status]');
                if (!viewport || !cards.length) return;

                let active = 0;
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
                            pointerEvents: 'auto',
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
                    cards.forEach(function (_, index) {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'flacso-catalogo-3d__dot';
                        dot.setAttribute('aria-label', 'Ir al programa ' + (index + 1));
                        dot.addEventListener('click', function () {
                            active = index;
                            update();
                        });
                        dotsWrap.appendChild(dot);
                    });
                }

                function updateStatus() {
                    if (!statusEl || !cards[active]) return;
                    const label = cards[active].dataset.title || ('Programa ' + (active + 1));
                    const disabled = cards[active].classList.contains('is-disabled');
                    statusEl.textContent = 'Programa ' + (active + 1) + ' de ' + cards.length + ': ' + label + (disabled ? '. No vigente.' : '.');
                }

                function update() {
                    window.requestAnimationFrame(function () {
                        cards.forEach(function (card, index) {
                            const state = getVisualState(index, active, cards.length);
                            const isActive = index === active;

                            card.style.display = 'flex';
                            card.style.opacity = String(state.opacity);
                            card.style.zIndex = String(state.zIndex);
                            card.style.filter = 'blur(' + state.blur + 'px)';
                            card.style.boxShadow = state.shadow;
                            card.style.pointerEvents = state.pointerEvents;
                            card.style.transform = 'translate(-50%, -50%) translateX(' + state.x + 'px) scale(' + state.scale + ') rotateY(' + state.rotateY + 'deg)';
                            card.style.cursor = state.pointerEvents === 'auto' ? 'pointer' : 'default';
                            card.dataset.active = isActive ? '1' : '0';
                            card.tabIndex = isActive ? 0 : -1;
                            card.setAttribute('aria-current', isActive ? 'true' : 'false');
                            card.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                        });

                        if (dotsWrap) {
                            Array.from(dotsWrap.children).forEach(function (dot, index) {
                                dot.classList.toggle('is-active', index === active);
                            });
                        }

                        updateStatus();
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

                cards.forEach(function (card, index) {
                    const titleEl = card.querySelector('.flacso-catalogo-3d__title');
                    const title = titleEl ? titleEl.textContent.trim() : ('Programa ' + (index + 1));
                    card.dataset.title = title;
                    card.setAttribute('aria-label', title + '. Programa ' + (index + 1) + ' de ' + cards.length + '.');

                    card.addEventListener('click', function (event) {
                        if (moved) {
                            event.preventDefault();
                            return;
                        }
                        if (index !== active) {
                            event.preventDefault();
                            active = index;
                            update();
                            return;
                        }
                        if (card.classList.contains('is-disabled')) {
                            event.preventDefault();
                        }
                    });

                    card.addEventListener('dragstart', function (event) {
                        event.preventDefault();
                    });
                });

                root.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft') { event.preventDefault(); prev(); }
                    if (event.key === 'ArrowRight') { event.preventDefault(); next(); }
                    if (event.key === 'Home') { event.preventDefault(); active = 0; update(); }
                    if (event.key === 'End') { event.preventDefault(); active = cards.length - 1; update(); }
                    if (event.key === 'PageUp') { event.preventDefault(); prev(); }
                    if (event.key === 'PageDown') { event.preventDefault(); next(); }
                    if ((event.key === 'Enter' || event.key === ' ') && document.activeElement === viewport) {
                        const activeCard = cards[active];
                        if (activeCard && !activeCard.classList.contains('is-disabled')) {
                            event.preventDefault();
                            activeCard.click();
                        }
                    }
                });

                root.addEventListener('dragstart', function (event) {
                    event.preventDefault();
                });

                root.addEventListener('pointerdown', function (event) {
                    if (event.pointerType === 'mouse') return;
                    dragging = true;
                    moved = false;
                    startX = event.clientX;
                    currentX = event.clientX;
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

                    window.setTimeout(function () {
                        moved = false;
                    }, 50);
                }

                window.addEventListener('pointerup', endDrag);
                window.addEventListener('pointercancel', endDrag);
                window.addEventListener('resize', update);

                root.classList.add('is-ready');
                if (cards.length <= 1) {
                    root.classList.add('is-single');
                    if (dotsWrap) dotsWrap.style.display = 'none';
                }

                buildDots();
                update();
            })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_listar_paginas_shortcode')) {
    /**
     * [listar_paginas padre=\"Diplomas\"]
     * [listar_paginas padre_id=\"12294\"]
     * [listar_paginas padre_id=\"12294\" vista=\"grid\"]
     */
    function flacso_listar_paginas_shortcode($atts = []): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'padre'             => '',
            'padre_id'          => '',
            'posts_per_page'    => -1,
            'mostrar_inactivos' => '0',
            'vista'             => 'catalogo_3d',
        ], $atts, 'listar_paginas');

        $parent_id = 0;
        if (!empty($atts['padre_id'])) {
            $parent_id = absint($atts['padre_id']);
        } else {
            $padre = sanitize_text_field((string) $atts['padre']);
            if ($padre === '') {
                return '<div class="notice notice-error"><p>' . esc_html__('Debes indicar el atributo "padre" o "padre_id".', 'flacso-main-page') . '</p></div>';
            }

            $padre_query = new WP_Query([
                'post_type'              => 'page',
                'title'                  => $padre,
                'post_status'            => 'all',
                'posts_per_page'         => 1,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'orderby'                => 'post_date ID',
                'order'                  => 'ASC',
            ]);

            if (!empty($padre_query->post)) {
                $parent_id = (int) $padre_query->post->ID;
            } else {
                wp_reset_postdata();
                return sprintf('<div class="notice notice-error"><p>%s</p></div>', esc_html(sprintf(__('No existe la pagina padre "%s".', 'flacso-main-page'), $padre)));
            }

            wp_reset_postdata();
        }

        if (!$parent_id) {
            return '<div class="notice notice-error"><p>' . esc_html__('No se pudo determinar la pagina padre solicitada.', 'flacso-main-page') . '</p></div>';
        }

        $query = new WP_Query([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_parent'    => $parent_id,
            'posts_per_page' => intval($atts['posts_per_page']),
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return '<div class="notice notice-info"><p>' . esc_html__('No hay paginas disponibles.', 'flacso-main-page') . '</p></div>';
        }

        $items = flacso_listar_paginas_collect_items($query, flacso_posgrados_vigentes(), rest_sanitize_boolean($atts['mostrar_inactivos']));
        if (!$items) {
            return '<div class="notice notice-info"><p>' . esc_html__('No hay programas para mostrar con los filtros actuales.', 'flacso-main-page') . '</p></div>';
        }

        if (sanitize_key((string) $atts['vista']) === 'grid') {
            return flacso_listar_paginas_render_grid($items);
        }

        return flacso_listar_paginas_render_catalogo_3d($items);
    }

    add_shortcode('listar_paginas', 'flacso_listar_paginas_shortcode');
}
