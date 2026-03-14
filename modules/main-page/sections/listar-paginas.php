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

        ob_start();
        ?>
        <section id="<?php echo esc_attr($instance_id); ?>" class="flacso-catalogo-3d" data-flacso-catalogo-3d>
            <div class="flacso-catalogo-3d__viewport" tabindex="0" aria-label="<?php esc_attr_e('Catalogo 3D de programas', 'flacso-main-page'); ?>">
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
            <div class="flacso-catalogo-3d__controls">
                <button type="button" data-catalogo-prev aria-label="<?php esc_attr_e('Anterior', 'flacso-main-page'); ?>"><i class="bi bi-chevron-left"></i></button>
                <div class="flacso-catalogo-3d__dots" data-catalogo-dots></div>
                <button type="button" data-catalogo-next aria-label="<?php esc_attr_e('Siguiente', 'flacso-main-page'); ?>"><i class="bi bi-chevron-right"></i></button>
            </div>
        </section>
        <style>
            #<?php echo esc_html($instance_id); ?>{padding:1rem 0 1.4rem;--flacso-cat-accent:#173f7d;--flacso-cat-deep:#102c58}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport{position:relative;min-height:clamp(420px,58vw,610px);height:clamp(420px,58vw,610px);perspective:1800px;touch-action:pan-y;cursor:grab;outline:none;background:linear-gradient(180deg,#e4e9f1 0%,#d2d9e3 58%,#cbd3de 100%);border-radius:22px;border:1px solid rgba(16,44,88,.13);overflow:hidden}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::before{content:"";position:absolute;left:-15%;right:-15%;top:-48%;height:74%;background:radial-gradient(ellipse at center,rgba(255,255,255,.82) 0%,rgba(255,255,255,0) 68%);pointer-events:none}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport::after{content:"";position:absolute;left:8%;right:8%;bottom:12%;height:28px;border-radius:999px;background:radial-gradient(ellipse at center,rgba(16,44,88,.22) 0%,rgba(16,44,88,0) 70%);pointer-events:none}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__viewport.is-dragging{cursor:grabbing}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__track{position:absolute;inset:0;transform-style:preserve-3d}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__card{--w:min(390px,84vw);position:absolute;left:50%;top:50%;width:var(--w);text-decoration:none;color:inherit;background:linear-gradient(180deg,#ffffff 0%,#f5f7fb 100%);border-radius:20px;overflow:hidden;border:1px solid rgba(16,44,88,.16);box-shadow:0 20px 45px rgba(12,24,45,.24);transform-origin:center center;transition:transform .56s cubic-bezier(.2,.84,.26,1),opacity .28s,filter .28s,box-shadow .28s;will-change:transform,opacity}
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
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__controls{display:flex;align-items:center;justify-content:center;gap:.7rem;margin-top:.85rem}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__controls>button{width:40px;height:40px;border-radius:50%;border:1px solid rgba(16,44,88,.24);background:#fff;color:var(--flacso-cat-accent);display:inline-flex;align-items:center;justify-content:center;box-shadow:0 6px 14px rgba(16,44,88,.16)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dots{display:inline-flex;gap:.44rem;background:rgba(255,255,255,.6);border-radius:999px;padding:.32rem .52rem;border:1px solid rgba(16,44,88,.12)}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dot{width:8px;height:8px;border-radius:50%;border:0;background:rgba(16,44,88,.28);padding:0}
            #<?php echo esc_html($instance_id); ?> .flacso-catalogo-3d__dot.is-active{background:var(--flacso-cat-accent);transform:scale(1.25)}
        </style>
        <script>
            (function () {
                const root = document.getElementById('<?php echo esc_js($instance_id); ?>');
                if (!root || root.dataset.catalogoInit === '1') return;
                root.dataset.catalogoInit = '1';

                const viewport = root.querySelector('.flacso-catalogo-3d__viewport');
                const cards = Array.from(root.querySelectorAll('.flacso-catalogo-3d__card'));
                const dotsWrap = root.querySelector('[data-catalogo-dots]');
                const prevBtn = root.querySelector('[data-catalogo-prev]');
                const nextBtn = root.querySelector('[data-catalogo-next]');
                if (!viewport || !cards.length || !dotsWrap) return;

                let current = 0;
                let dragStart = 0;
                let dragNow = 0;
                let isDragging = false;
                let suppressClickUntil = 0;
                let wheelLock = false;
                let tiltX = 0;
                let tiltY = 0;
                let rafTilt = 0;

                const mod = function (value, total) { return ((value % total) + total) % total; };
                const nd = function (index, active, total) {
                    let diff = index - active;
                    const half = Math.floor(total / 2);
                    if (diff > half) diff -= total;
                    if (diff < -half) diff += total;
                    return diff;
                };

                const cfg = function () {
                    const w = window.innerWidth;
                    if (w <= 575) return { x: 84, z: 92, r: 0, s: 0.9, fs: 0.82, so: 0.56, fo: 0, fb: 5 };
                    if (w <= 767) return { x: 116, z: 130, r: 18, s: 0.9, fs: 0.8, so: 0.52, fo: 0, fb: 5 };
                    if (w <= 991) return { x: 166, z: 158, r: 24, s: 0.88, fs: 0.76, so: 0.48, fo: 0.06, fb: 5 };
                    return { x: 242, z: 205, r: 30, s: 0.86, fs: 0.72, so: 0.45, fo: 0.04, fb: 6 };
                };

                const syncHeight = function () {
                    const base = window.innerWidth <= 575 ? 420 : (window.innerWidth <= 767 ? 470 : (window.innerWidth <= 991 ? 520 : 600));
                    const max = cards.reduce(function (m, c) { return Math.max(m, c.offsetHeight || 0); }, 0);
                    const h = Math.max(base, max + 100);
                    viewport.style.height = h + 'px';
                    viewport.style.minHeight = h + 'px';
                };

                function goTo(index) {
                    current = mod(index, cards.length);
                    render();
                }
                function next() { goTo(current + 1); }
                function prev() { goTo(current - 1); }

                dotsWrap.innerHTML = '';
                cards.forEach(function (_, i) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'flacso-catalogo-3d__dot';
                    dot.setAttribute('aria-label', 'Ir al programa ' + (i + 1));
                    dot.addEventListener('click', function () { goTo(i); });
                    dotsWrap.appendChild(dot);
                });
                const dots = Array.from(dotsWrap.querySelectorAll('.flacso-catalogo-3d__dot'));

                function render(skipHeightSync) {
                    if (!skipHeightSync) syncHeight();
                    const c = cfg();
                    cards.forEach(function (card, i) {
                        const diff = nd(i, current, cards.length);
                        const abs = Math.abs(diff);
                        const dir = diff > 0 ? 1 : -1;
                        let transform = '';
                        let opacity = '0';
                        let zIndex = 1;
                        let filter = 'blur(0px) saturate(1)';

                        if (diff === 0) {
                            transform = 'translate3d(-50%,-50%,0px) rotateX(' + tiltX.toFixed(2) + 'deg) rotateY(' + tiltY.toFixed(2) + 'deg) scale(1.02)';
                            opacity = '1';
                            zIndex = 30;
                        } else if (abs === 1) {
                            transform = 'translate3d(calc(-50% + ' + (dir * c.x) + 'px),-50%,' + (-c.z) + 'px) rotateY(' + (-dir * c.r) + 'deg) scale(' + c.s + ')';
                            opacity = String(c.so);
                            zIndex = 20;
                            filter = 'blur(.3px) saturate(.9)';
                        } else if (abs === 2) {
                            transform = 'translate3d(calc(-50% + ' + (dir * (c.x * 1.68)) + 'px),-50%,' + (-c.z * 2.05) + 'px) rotateY(' + (-dir * (c.r + 9)) + 'deg) scale(' + c.fs + ')';
                            opacity = String(c.fo);
                            zIndex = 10;
                            filter = 'blur(' + c.fb + 'px) saturate(.75)';
                        } else {
                            transform = 'translate3d(-50%,-50%,' + (-c.z * 2.7) + 'px) rotateY(0deg) scale(.7)';
                            opacity = '0';
                            zIndex = 1;
                            filter = 'blur(7px) saturate(.65)';
                        }

                        const active = diff === 0;
                        card.style.transform = transform;
                        card.style.opacity = opacity;
                        card.style.zIndex = String(zIndex);
                        card.style.filter = filter;
                        card.dataset.active = active ? '1' : '0';
                        card.tabIndex = active ? 0 : -1;
                    });

                    dots.forEach(function (dot, i) {
                        dot.classList.toggle('is-active', i === current);
                    });
                }

                if (prevBtn) prevBtn.addEventListener('click', prev);
                if (nextBtn) nextBtn.addEventListener('click', next);

                viewport.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft') { event.preventDefault(); prev(); }
                    if (event.key === 'ArrowRight') { event.preventDefault(); next(); }
                });

                viewport.addEventListener('pointerdown', function (event) {
                    isDragging = true;
                    dragStart = event.clientX;
                    dragNow = event.clientX;
                    tiltX = 0;
                    tiltY = 0;
                    render(true);
                    viewport.classList.add('is-dragging');
                });
                viewport.addEventListener('pointermove', function (event) {
                    if (!isDragging) return;
                    dragNow = event.clientX;
                });
                const finishDrag = function () {
                    if (!isDragging) return;
                    isDragging = false;
                    viewport.classList.remove('is-dragging');
                    const delta = dragNow - dragStart;
                    if (Math.abs(delta) > 42) {
                        delta < 0 ? next() : prev();
                        suppressClickUntil = Date.now() + 260;
                    }
                };
                viewport.addEventListener('pointerup', finishDrag);
                viewport.addEventListener('pointercancel', finishDrag);
                viewport.addEventListener('pointerleave', finishDrag);

                viewport.addEventListener('mousemove', function (event) {
                    if (isDragging) return;
                    const rect = viewport.getBoundingClientRect();
                    if (!rect.width || !rect.height) return;
                    const px = ((event.clientX - rect.left) / rect.width) - 0.5;
                    const py = ((event.clientY - rect.top) / rect.height) - 0.5;
                    const nextTiltX = Math.max(-6, Math.min(6, -py * 8));
                    const nextTiltY = Math.max(-8, Math.min(8, px * 11));
                    if (Math.abs(nextTiltX - tiltX) < 0.1 && Math.abs(nextTiltY - tiltY) < 0.1) return;
                    tiltX = nextTiltX;
                    tiltY = nextTiltY;
                    if (rafTilt) return;
                    rafTilt = window.requestAnimationFrame(function () {
                        rafTilt = 0;
                        render(true);
                    });
                });

                viewport.addEventListener('mouseleave', function () {
                    if (isDragging) return;
                    tiltX = 0;
                    tiltY = 0;
                    render(true);
                });

                viewport.addEventListener('wheel', function (event) {
                    if (wheelLock) return;
                    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
                    if (Math.abs(delta) < 24) return;
                    event.preventDefault();
                    wheelLock = true;
                    delta > 0 ? next() : prev();
                    window.setTimeout(function () { wheelLock = false; }, 220);
                }, { passive: false });

                cards.forEach(function (card, i) {
                    card.addEventListener('click', function (event) {
                        if (Date.now() < suppressClickUntil) {
                            event.preventDefault();
                            event.stopPropagation();
                            return;
                        }
                        const active = card.dataset.active === '1';
                        const disabled = card.classList.contains('is-disabled');
                        if (disabled) {
                            event.preventDefault();
                            return;
                        }
                        if (!active) {
                            event.preventDefault();
                            event.stopPropagation();
                            goTo(i);
                        }
                    });
                });

                window.addEventListener('resize', function () {
                    tiltX = 0;
                    tiltY = 0;
                    render();
                });
                render();
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
