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
            min-height: clamp(460px, 72vh, 760px);
            display: flex;
            align-items: center;
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
                min-height: clamp(460px, 72svh, 760px);
            }
        }

        @media (max-width: 767.98px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container {
                align-items: flex-end;
                min-height: clamp(440px, 68svh, 640px);
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
                    rgba(4, 14, 34, 0.62),
                    rgba(4, 14, 34, 0.78)
                ),
                url("<?php echo $background; ?>") center/cover no-repeat;
            will-change: transform;
            filter: saturate(0.9) contrast(1.06) brightness(0.9);
            animation: bgMove 20s ease-in-out infinite alternate;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-container::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 78%, rgba(250, 198, 95, 0.2), transparent 36%),
                radial-gradient(circle at 88% 18%, rgba(250, 198, 95, 0.16), transparent 38%),
                linear-gradient(180deg, rgba(10, 26, 52, 0.06), rgba(8, 20, 44, 0.45));
            z-index: 0;
        }

        @keyframes bgMove {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.05) translate(-2%, -2%); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: clamp(1.2rem, 4vw, 3rem) clamp(0.95rem, 2vw, 1.5rem) clamp(2.2rem, 8vw, 5rem);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy-panel {
            width: min(100%, 960px);
            margin-inline: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-content {
                padding-top: clamp(1.8rem, 4vw, 3.2rem);
                padding-bottom: clamp(3.2rem, 8vh, 5.6rem);
            }
        }

        /* ===== Título y subtítulo ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-ornaments {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-ornaments::before {
            content: "";
            position: absolute;
            top: 2.5%;
            right: max(3%, 24px);
            width: min(250px, 28vw);
            height: min(250px, 26vh);
            border-top: 2px solid rgba(247, 198, 93, 0.75);
            border-right: 2px solid rgba(247, 198, 93, 0.75);
            border-radius: 0 32px 0 0;
            opacity: 0.88;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-ornaments::after {
            content: "";
            position: absolute;
            left: max(5%, 20px);
            bottom: 8%;
            width: min(300px, 30vw);
            height: min(260px, 26vh);
            border-left: 2px solid rgba(247, 198, 93, 0.72);
            border-bottom: 2px solid rgba(247, 198, 93, 0.72);
            border-radius: 0 0 0 42px;
            opacity: 0.86;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line {
            position: absolute;
            background: rgba(247, 198, 93, 0.68);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--v-left {
            top: 11%;
            left: max(8%, 30px);
            width: 2px;
            height: min(130px, 14vh);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--diag-left {
            left: max(7%, 24px);
            top: 48%;
            width: min(190px, 21vw);
            height: 2px;
            transform: rotate(-38deg);
            transform-origin: left center;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--diag-bottom {
            right: max(22%, 110px);
            bottom: 15%;
            width: min(260px, 24vw);
            height: 2px;
            transform: rotate(-30deg);
            transform-origin: right center;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--h-bottom {
            right: max(10%, 50px);
            bottom: 9%;
            width: min(430px, 40vw);
            height: 2px;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #f9f2d2;
            box-shadow: 0 0 0 3px rgba(247, 198, 93, 0.24);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-dot--top-right {
            top: 2.2%;
            right: max(27%, 170px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-dot--left-mid {
            top: 52%;
            left: max(15%, 84px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-dot--bottom-center {
            bottom: 8.5%;
            right: max(50%, 240px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
            font-size: clamp(2rem, 10vw, 5.2rem);
            font-weight: 800;
            margin: 0 auto 0.95rem;
            text-shadow: 0 4px 20px rgba(3, 11, 24, 0.54);
            font-family: "Sora", var(--global-heading-font-family, "Helvetica Neue", sans-serif);
            line-height: 1.03;
            letter-spacing: -0.02em;
            text-wrap: balance;
            max-width: 14.5ch;
            opacity: 0;
            animation: slideDown 1.2s ease-out forwards;
            animation-delay: 0.4s;
            color: #ffffff;
        }

        @media (min-width: 576px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                font-size: clamp(2.4rem, 9.2vw, 5.6rem);
            }
        }

        @media (min-width: 768px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                margin-bottom: 1.15rem;
            }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
            font-size: clamp(1.04rem, 3.6vw, 2rem);
            margin: 0 auto 1.8rem;
            opacity: 0;
            font-family: "Manrope", var(--global-body-font-family, "Helvetica Neue", sans-serif);
            line-height: 1.45;
            max-width: 38ch;
            animation: fadeUp 1.3s ease-out forwards;
            animation-delay: 1s;
            color: rgba(246, 249, 255, 0.95);
            text-shadow: 0 3px 12px rgba(6, 19, 40, 0.52);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== Botones principales ===== */
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.82rem;
            align-items: center;
            justify-content: center;
            opacity: 0;
            animation: fadeInBtns 1.2s ease-out forwards;
            animation-delay: 1.5s;
            width: 100%;
        }

        @keyframes fadeInBtns {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
            min-width: clamp(190px, 22vw, 250px);
            text-transform: none;
            padding: 0.88rem 1.7rem;
            font-size: clamp(1rem, 2.2vw, 1.1rem);
            width: auto;
            max-width: none;
            border-width: 2px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 24px rgba(5, 15, 34, 0.3);
            backdrop-filter: blur(5px);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--primary {
            background: linear-gradient(120deg, rgba(26, 106, 70, 0.95), rgba(37, 133, 86, 0.92));
            border-color: rgba(68, 176, 120, 0.55);
            color: #f3fbf5;
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline {
            background: transparent;
            border-color: rgba(247, 198, 93, 0.95);
            color: rgba(247, 198, 93, 0.98);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--light {
            background: rgba(196, 225, 255, 0.2);
            border-color: rgba(220, 238, 255, 0.58);
            color: #f5f9ff;
            box-shadow: 0 12px 28px rgba(3, 12, 30, 0.35);
        }
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.48);
            color: rgba(255, 255, 255, 0.95);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn:focus-visible {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(4, 12, 28, 0.42);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline:focus-visible {
            background: rgba(247, 198, 93, 0.13);
            color: #ffecc5;
        }

        @media (max-width: 767.98px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-content {
                padding-bottom: clamp(1.6rem, 8vh, 3rem);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                max-width: 13.8ch;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                margin-bottom: 1.25rem;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
                width: min(100%, 340px);
                min-width: 0;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-ornaments::before,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-ornaments::after,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--diag-bottom,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-line--h-bottom,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .orn-dot--bottom-center {
                display: none;
            }
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
                <div class="flacso-hero-ornaments" aria-hidden="true">
                    <span class="orn-line orn-line--v-left"></span>
                    <span class="orn-line orn-line--diag-left"></span>
                    <span class="orn-line orn-line--diag-bottom"></span>
                    <span class="orn-line orn-line--h-bottom"></span>
                    <span class="orn-dot orn-dot--top-right"></span>
                    <span class="orn-dot orn-dot--left-mid"></span>
                    <span class="orn-dot orn-dot--bottom-center"></span>
                </div>
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


