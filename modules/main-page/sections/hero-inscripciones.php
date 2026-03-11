<?php

// ==================================================
// HERO INSCRIPCIONES ANIMADO (Sistema unificado FLACSO 2025)
// ==================================================

if (!function_exists('flacso_section_hero_render')) {
    function flacso_section_hero_render() {
        $settings = Flacso_Main_Page_Settings::get_section('hero');
        $unique_id = 'hero_' . wp_generate_password(6, false);

        $background = esc_url($settings['background_image'] ?? '');
        $title = esc_html($settings['title'] ?? '');
        $subtitle = esc_html($settings['subtitle'] ?? '');
        $buttons_config = $settings['buttons'] ?? [];
        if (!is_array($buttons_config)) {
            $buttons_config = [];
        }
        $show_hero_buttons = !empty($settings['show_buttons']);
        $allowed_button_styles = ['primary', 'outline', 'light', 'ghost'];
        $hero_buttons = [];
        foreach ($buttons_config as $button_item) {
            $button_label = trim((string) ($button_item['label'] ?? ''));
            $button_url = Flacso_Main_Page_Settings::normalize_url_output($button_item['url'] ?? '');
            if (empty($button_item['enabled']) || !$button_label || !$button_url) {
                continue;
            }
            $style = $button_item['style'] ?? 'primary';
            if (!in_array($style, $allowed_button_styles, true)) {
                $style = 'primary';
            }
            $hero_buttons[] = [
                'label' => esc_html($button_label),
                'url' => $button_url,
                'style' => $style,
            ];
        }
        $bubble_primary_label = esc_html($settings['bubble_primary_label'] ?? '');
        $bubble_primary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['bubble_primary_url'] ?? '');
        $bubble_secondary_label = esc_html($settings['bubble_secondary_label'] ?? '');
        $bubble_secondary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['bubble_secondary_url'] ?? '');
        $bubble_primary_enabled = !empty($settings['bubble_primary_enabled']);
        $bubble_secondary_enabled = !empty($settings['bubble_secondary_enabled']);
        $bubble_primary_style = $settings['bubble_primary_style'] ?? 'primary';
        $bubble_secondary_style = $settings['bubble_secondary_style'] ?? 'outline';
        $fab_style_map = [
            'primary' => 'fab-btn--primary',
            'outline' => 'fab-btn--outline',
            'light' => 'fab-btn--light',
            'ghost' => 'fab-btn--ghost',
        ];
        $bubble_primary_class = $fab_style_map[$bubble_primary_style] ?? $fab_style_map['primary'];
        $bubble_secondary_class = $fab_style_map[$bubble_secondary_style] ?? $fab_style_map['outline'];

        $primary_button = $hero_buttons[0] ?? null;
        $secondary_button = $hero_buttons[1] ?? null;
        if ($bubble_primary_enabled) {
            if (empty($bubble_primary_label) && $primary_button) {
                $bubble_primary_label = $primary_button['label'];
            }
            if (empty($bubble_primary_url) && $primary_button) {
                $bubble_primary_url = $primary_button['url'];
            }
        }
        if ($bubble_secondary_enabled) {
            if (empty($bubble_secondary_label) && $secondary_button) {
                $bubble_secondary_label = $secondary_button['label'];
            }
            if (empty($bubble_secondary_url) && $secondary_button) {
                $bubble_secondary_url = $secondary_button['url'];
            }
        }

        ob_start();
        ?>

        <style>
        /* ======================================================
           HERO ANIMADO - FLACSO Uruguay
           ====================================================== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container {
            background: var(--global-palette1, #1d3a72);
            color: var(--global-palette9, #ffffff);
            min-height: clamp(520px, 82vh, 880px);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            position: relative;
            overflow: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            padding-top: 0;
            isolation: isolate;
            animation: heroFadeIn 1.2s ease-out forwards;
        }

        @supports (height: 100svh) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container {
                min-height: clamp(520px, 82svh, 880px);
            }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container {
                align-items: center;
                min-height: 90vh;
            }
        }

        @media (min-width: 1024px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container {
                min-height: 100vh;
            }
        }

        @keyframes heroFadeIn {
            from { opacity: 0; transform: scale(1.02); }
            to { opacity: 1; transform: scale(1); }
        }

        /* ===== Imagen de fondo con filtro de color institucional ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(
                    color-mix(in srgb, var(--global-palette1, #1d3a72) 62%, transparent),
                    color-mix(in srgb, var(--global-palette1, #1d3a72) 92%, transparent)
                ),
                url("<?php echo $background; ?>") center/cover no-repeat;
            will-change: transform;
            animation: bgMove 20s ease-in-out infinite alternate;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container::after {
            content: "";
            position: absolute;
            inset: auto 0 0 0;
            height: min(36%, 260px);
            background: linear-gradient(180deg, rgba(10, 26, 52, 0), rgba(8, 20, 44, 0.74));
            z-index: 0;
        }

        @keyframes bgMove {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.05) translate(-2%, -2%); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-content {
            position: relative;
            z-index: 1;
            text-align: left;
            width: 100%;
            max-width: 1120px;
            margin: 0 auto;
            padding: clamp(1rem, 4vw, 2rem) 1rem clamp(1.2rem, 6vw, 2.8rem);
            display: flex;
            justify-content: flex-start;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy-panel {
            width: min(100%, 660px);
            background: linear-gradient(165deg, rgba(7, 22, 49, 0.66), rgba(10, 29, 63, 0.52));
            border: 1px solid rgba(255, 255, 255, 0.26);
            border-radius: 22px;
            padding: clamp(1rem, 3.2vw, 1.6rem);
            backdrop-filter: blur(8px);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.28);
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-content {
                text-align: center;
                justify-content: center;
                padding: 80px 2rem;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy-panel {
                border-radius: 28px;
                padding: clamp(1.3rem, 2.8vw, 2.1rem);
            }
        }

        /* ===== Título y subtítulo ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
            font-size: clamp(1.9rem, 8vw, 2.8rem);
            font-weight: 800;
            margin-bottom: 0.85rem;
            text-shadow: 0 8px 24px rgba(3, 11, 24, 0.36);
            font-family: "Sora", var(--global-heading-font-family, "Helvetica Neue", sans-serif);
            line-height: 1.1;
            opacity: 0;
            animation: slideDown 1.2s ease-out forwards;
            animation-delay: 0.4s;
            color: #ffffff;
        }

        @media (min-width: 576px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                font-size: clamp(2.1rem, 6.4vw, 3rem);
            }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                font-size: 2.75rem;
                margin-bottom: 1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                font-size: 3.5rem;
            }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
            font-size: clamp(0.98rem, 3.8vw, 1.2rem);
            margin-bottom: 1.4rem;
            opacity: 0;
            font-family: "Manrope", var(--global-body-font-family, "Helvetica Neue", sans-serif);
            line-height: 1.6;
            max-width: 62ch;
            margin-left: 0;
            margin-right: 0;
            animation: fadeUp 1.3s ease-out forwards;
            animation-delay: 1s;
            color: rgba(255, 255, 255, 0.94);
        }

        @media (min-width: 576px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                font-size: clamp(1.06rem, 3vw, 1.25rem);
            }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                font-size: 1.35rem;
                margin-bottom: 2.2rem;
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (min-width: 1024px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                font-size: 1.5rem;
            }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== Botones principales ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.72rem;
            align-items: stretch;
            opacity: 0;
            animation: fadeInBtns 1.2s ease-out forwards;
            animation-delay: 1.5s;
        }

        @keyframes fadeInBtns {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
                flex-direction: row;
                justify-content: center;
                gap: 0.85rem;
            }
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
            min-width: 0;
            text-transform: none;
            padding: 0.74rem 1.05rem;
            font-size: 0.95rem;
            width: 100%;
            max-width: 360px;
        }

        @media (min-width: 576px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
                font-size: 1.02rem;
            }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
                min-width: 170px;
                width: auto;
                max-width: none;
                padding: 0.82rem 1.55rem;
            }
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--primary {
            background: var(--global-palette-btn-bg, var(--global-palette1, #1d3a72));
            border-color: var(--global-palette-btn-bg, var(--global-palette1, #1d3a72));
            color: var(--global-palette9, #ffffff);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline {
            background: transparent;
            border-color: var(--global-palette2, #f7b733);
            color: var(--global-palette2, #f7b733);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--light {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.35);
            color: var(--global-palette9, #ffffff);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost {
            background: transparent;
            border-color: rgba(255,255,255,0.4);
            color: var(--global-palette9, #ffffff);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost:hover {
            background: rgba(255,255,255,0.15);
        }

        /* ===== FAB flotantes ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-fab-stack {
            position: fixed;
            right: 30px;
            bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 2147483647 !important;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-fab-stack.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            animation: fadeStack 0.35s ease-out forwards;
        }

        @keyframes fadeStack {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn {
            border-radius: 999px;
            padding: 12px 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--global-palette3, #0f1a2d);
            text-decoration: none;
            border: 2px solid transparent;
            min-width: 220px;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn i {
            font-size: 1.2rem;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--primary {
            background: var(--global-palette2, #f7b733);
            color: var(--global-palette3, #0f1a2d);
            border: 2px solid var(--global-palette2, #f7b733);
            transition-delay: .05s;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--outline {
            background: var(--global-palette9, #ffffff);
            color: var(--global-palette1, #1d3a72);
            border: 2px solid var(--global-palette1, #1d3a72);
            box-shadow: 0 6px 18px rgba(13, 31, 68, 0.16);
            transition-delay: .12s;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--light {
            background: rgba(255, 255, 255, 0.95);
            color: var(--global-palette3, #0f1a2d);
            border: 2px solid rgba(255, 255, 255, 0.85);
            transition-delay: .12s;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--ghost {
            background: transparent;
            color: var(--global-palette9, #ffffff);
            border: 2px solid rgba(255, 255, 255, 0.8);
            transition-delay: .12s;
        }

        .fab-top {
            background: var(--global-palette1, #1d3a72);
            color: var(--global-palette9, #ffffff);
            border: 2px solid var(--global-palette1, #1d3a72);
            width: 56px;
            height: 56px;
            padding: 0;
            font-size: 1.4rem;
            border-radius: 50%;
            transition-delay: .18s;
            min-width: unset !important;
            animation: floatUp 4s ease-in-out infinite alternate;
        }
        .fab-top--light {
            background: var(--global-palette9, #ffffff);
            color: var(--global-palette1, #1d3a72);
            border-color: var(--global-palette9, #ffffff);
        }
        .fab-top--dark {
            background: var(--global-palette1, #1d3a72);
            color: var(--global-palette9, #ffffff);
            border-color: var(--global-palette1, #1d3a72);
        }

        @keyframes floatUp {
            0% { transform: translateY(0); }
            100% { transform: translateY(-6px); }
        }

        @media (max-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-fab-stack {
                left: 12px;
                right: 12px;
                bottom: 12px;
                gap: 8px;
                align-items: stretch;
            }
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn {
                padding: 10px 14px;
                font-size: .85rem;
                min-width: 0;
            }
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
                margin-left: auto;
            }
        }
        </style>

        <div class="flacso-hero-<?php echo esc_attr($unique_id); ?>">
            <section class="flacso-hero-container" id="hero-<?php echo esc_attr($unique_id); ?>">
                <div class="flacso-hero-content flacso-content-shell">
                    <div class="flacso-hero-copy-panel">
                        <h1 class="flacso-hero-title"><?php echo $title; ?></h1>
                        <p class="flacso-hero-subtitle"><?php echo $subtitle; ?></p>
                        <?php if ($show_hero_buttons && !empty($hero_buttons)) : ?>
                            <div class="flacso-hero-buttons">
                                <?php
                                $style_class_map = [
                                    'primary' => 'hero-btn--primary',
                                    'outline' => 'hero-btn--outline',
                                    'light' => 'hero-btn--light',
                                    'ghost' => 'hero-btn--ghost',
                                ];
                                foreach ($hero_buttons as $button_data) :
                                    $style_class = $style_class_map[$button_data['style']] ?? $style_class_map['primary'];
                                ?>
                                    <a href="<?php echo esc_url($button_data['url']); ?>" class="flacso-btn flacso-btn-anim hero-btn <?php echo esc_attr($style_class); ?>">
                                        <?php echo $button_data['label']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Burbujas flotantes -->
            <div class="flacso-fab-stack" id="flacso-fab-stack-<?php echo esc_attr($unique_id); ?>">
                <?php if ($bubble_primary_enabled && $bubble_primary_url && $bubble_primary_label) : ?>
                    <a class="fab-btn <?php echo esc_attr($bubble_primary_class); ?>" href="<?php echo esc_url($bubble_primary_url); ?>" title="<?php echo $bubble_primary_label; ?>">
                        <i class="bi bi-mortarboard-fill"></i> <?php echo $bubble_primary_label; ?>
                    </a>
                <?php endif; ?>
                <?php if ($bubble_secondary_enabled && $bubble_secondary_url && $bubble_secondary_label) : ?>
                    <a class="fab-btn <?php echo esc_attr($bubble_secondary_class); ?>" href="<?php echo esc_url($bubble_secondary_url); ?>" title="<?php echo $bubble_secondary_label; ?>">
                        <i class="bi bi-journal-text"></i> <?php echo $bubble_secondary_label; ?>
                    </a>
                <?php endif; ?>
                <button class="fab-btn fab-top" title="<?php esc_attr_e('Ir arriba', 'flacso-main-page'); ?>" id="fab-top-<?php echo esc_attr($unique_id); ?>">
                    <i class="bi bi-arrow-up-short"></i>
                </button>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const heroSection = document.getElementById("hero-<?php echo esc_attr($unique_id); ?>");
            const fabStack = document.getElementById("flacso-fab-stack-<?php echo esc_attr($unique_id); ?>");
            const fabTop = document.getElementById("fab-top-<?php echo esc_attr($unique_id); ?>");
            const heroButtons = heroSection ? Array.from(heroSection.querySelectorAll(".hero-btn")) : [];
            const heroHasButtons = heroButtons.length > 0;

            if (!heroSection || !fabStack) return;

            function equalizeButtonWidths() {
                const btns = fabStack.querySelectorAll(".fab-btn:not(.fab-top)");
                let maxWidth = 0;
                btns.forEach(b => {
                    b.style.width = "auto";
                    maxWidth = Math.max(maxWidth, b.offsetWidth);
                });
                btns.forEach(b => b.style.width = maxWidth + "px");
            }
            equalizeButtonWidths();
            window.addEventListener("resize", equalizeButtonWidths);

            const parseColor = (value) => {
                if (!value) {
                    return null;
                }
                const rgbMatch = value.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)\\)/i);
                if (rgbMatch) {
                    return rgbMatch.slice(1, 4).map((part) => Number(part));
                }
                const hexMatch = value.trim().replace(/^#/, '').match(/^([0-9a-f]{3,8})$/i);
                if (hexMatch) {
                    const hex = hexMatch[1];
                    if (hex.length === 3) {
                        return hex.split('').map(ch => parseInt(ch + ch, 16));
                    }
                    if (hex.length === 6 || hex.length === 8) {
                        return [hex.slice(0, 2), hex.slice(2, 4), hex.slice(4, 6)].map((part) => parseInt(part, 16));
                    }
                }
                return null;
            };

            const getLuminance = (rgb) => {
                if (!rgb) {
                    return 0.5;
                }
                const srgb = rgb.map((value) => {
                    const channel = value / 255;
                    return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
                });
                return 0.2126 * srgb[0] + 0.7152 * srgb[1] + 0.0722 * srgb[2];
            };

            const applyFabContrast = () => {
                if (!fabTop) {
                    return;
                }
                const heroStyle = window.getComputedStyle(heroSection);
                let heroRgb = parseColor(heroStyle.backgroundColor);
                if (!heroRgb) {
                    const rootStyle = window.getComputedStyle(document.documentElement);
                    heroRgb = parseColor(rootStyle.getPropertyValue('--global-palette1') || '#1d3a72');
                }
                const luminance = getLuminance(heroRgb);
                const useLight = luminance < 0.5;
                fabTop.classList.toggle('fab-top--light', useLight);
                fabTop.classList.toggle('fab-top--dark', !useLight);
            };

            applyFabContrast();
            window.addEventListener('resize', applyFabContrast);

            const toggleFabVisibility = (show) => {
                if (show) {
                    fabStack.classList.add("show");
                    equalizeButtonWidths();
                } else {
                    fabStack.classList.remove("show");
                }
            };

            if ('IntersectionObserver' in window && heroHasButtons) {
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        toggleFabVisibility(!entry.isIntersecting);
                    });
                }, { threshold: 0.25 });
                observer.observe(heroSection);
            } else {
                toggleFabVisibility(true);
            }

            function equalizeHeroButtonWidths() {
                if (!heroButtons.length) return;
                let maxWidth = 0;
                heroButtons.forEach(btn => {
                    btn.style.minWidth = "auto";
                    btn.style.width = "auto";
                    maxWidth = Math.max(maxWidth, btn.offsetWidth);
                });
                heroButtons.forEach(btn => {
                    btn.style.minWidth = maxWidth + "px";
                });
            }
            equalizeHeroButtonWidths();
            window.addEventListener("resize", equalizeHeroButtonWidths);

            if (fabTop) {
                fabTop.addEventListener("click", () => {
                    heroSection.scrollIntoView({ behavior: "smooth", block: "start" });
                });
            }
        });
        </script>

        <?php
        return ob_get_clean();
    }
}


