<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_section_hero_render')) {
    function flacso_section_hero_render()
    {
        $settings = Flacso_Main_Page_Settings::get_section('hero');
        $defaults = Flacso_Main_Page_Settings::get_defaults();
        $hero_defaults = isset($defaults['hero']) && is_array($defaults['hero']) ? $defaults['hero'] : [];
        $unique_id = 'hero_' . wp_generate_password(6, false);

        $background_raw = (string) ($settings['background_image'] ?? ($hero_defaults['background_image'] ?? ''));
        $background = esc_url($background_raw);
        if ($background === '') {
            $background = 'https://flacso.edu.uy/wp-content/uploads/2025/11/primer-plano-de-ejecutivos-de-negocios-en-la-oficina-scaled.jpg';
        }

        $title_plain = trim(wp_strip_all_tags((string) ($settings['title'] ?? ($hero_defaults['title'] ?? ''))));
        if ($title_plain === '') {
            $title_plain = 'Inscripciones abiertas';
        }

        $subtitle_plain = trim(wp_strip_all_tags((string) ($settings['subtitle'] ?? ($hero_defaults['subtitle'] ?? ''))));
        if ($subtitle_plain === '') {
            $subtitle_plain = 'Sumate a los posgrados de FLACSO Uruguay.';
        }

        $year_match = [];
        $hero_year = '';
        if (preg_match('/\b(20\d{2})\b/', $title_plain, $year_match)) {
            $hero_year = $year_match[1];
        }

        $title_markup = esc_html($title_plain);
        if ($hero_year !== '') {
            $title_markup = preg_replace(
                '/\b' . preg_quote($hero_year, '/') . '\b/',
                '<span class="flacso-hero-year">$0</span>',
                $title_markup,
                1
            );
        }
        if ($hero_year !== '') {
            $kicker_text = sprintf(
                esc_html__('Postulaciones %s - FLACSO Uruguay', 'flacso-main-page'),
                $hero_year
            );
        } else {
            $kicker_text = esc_html__('Postulaciones abiertas - FLACSO Uruguay', 'flacso-main-page');
        }

        $buttons_config = isset($settings['buttons']) && is_array($settings['buttons']) ? $settings['buttons'] : [];
        $allowed_button_styles = ['primary', 'outline', 'light', 'ghost'];
        $hero_buttons = [];
        foreach ($buttons_config as $button_item) {
            $button_label = trim((string) ($button_item['label'] ?? ''));
            $button_url = Flacso_Main_Page_Settings::normalize_url_output($button_item['url'] ?? '');
            if (empty($button_item['enabled']) || $button_label === '' || $button_url === '') {
                continue;
            }
            $style = in_array(($button_item['style'] ?? ''), $allowed_button_styles, true)
                ? $button_item['style']
                : 'primary';
            $hero_buttons[] = [
                'label' => esc_html($button_label),
                'url' => $button_url,
                'style' => $style,
            ];
        }

        if (empty($hero_buttons)) {
            $legacy_primary_label = trim((string) ($settings['primary_label'] ?? ($hero_defaults['primary_label'] ?? '')));
            $legacy_primary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['primary_url'] ?? ($hero_defaults['primary_url'] ?? ''));
            $legacy_secondary_label = trim((string) ($settings['secondary_label'] ?? ($hero_defaults['secondary_label'] ?? '')));
            $legacy_secondary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['secondary_url'] ?? ($hero_defaults['secondary_url'] ?? ''));

            if ($legacy_primary_label !== '' && $legacy_primary_url !== '') {
                $hero_buttons[] = [
                    'label' => esc_html($legacy_primary_label),
                    'url' => $legacy_primary_url,
                    'style' => 'primary',
                ];
            }
            if ($legacy_secondary_label !== '' && $legacy_secondary_url !== '') {
                $hero_buttons[] = [
                    'label' => esc_html($legacy_secondary_label),
                    'url' => $legacy_secondary_url,
                    'style' => 'outline',
                ];
            }
        }

        $hero_buttons = array_slice($hero_buttons, 0, 3);
        $show_hero_buttons = !empty($settings['show_buttons']) && !empty($hero_buttons);

        $bubble_primary_label = trim((string) ($settings['bubble_primary_label'] ?? ($hero_defaults['bubble_primary_label'] ?? '')));
        $bubble_primary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['bubble_primary_url'] ?? ($hero_defaults['bubble_primary_url'] ?? ''));
        $bubble_secondary_label = trim((string) ($settings['bubble_secondary_label'] ?? ($hero_defaults['bubble_secondary_label'] ?? '')));
        $bubble_secondary_url = Flacso_Main_Page_Settings::normalize_url_output($settings['bubble_secondary_url'] ?? ($hero_defaults['bubble_secondary_url'] ?? ''));

        $bubble_primary_enabled = !empty($settings['bubble_primary_enabled']);
        $bubble_secondary_enabled = !empty($settings['bubble_secondary_enabled']);
        $bubble_primary_style = (string) ($settings['bubble_primary_style'] ?? ($hero_defaults['bubble_primary_style'] ?? 'primary'));
        $bubble_secondary_style = (string) ($settings['bubble_secondary_style'] ?? ($hero_defaults['bubble_secondary_style'] ?? 'outline'));

        $primary_button = $hero_buttons[0] ?? null;
        $secondary_button = $hero_buttons[1] ?? null;

        if ($bubble_primary_enabled && $bubble_primary_label === '' && $primary_button) {
            $bubble_primary_label = $primary_button['label'];
        }
        if ($bubble_primary_enabled && $bubble_primary_url === '' && $primary_button) {
            $bubble_primary_url = $primary_button['url'];
        }

        if ($bubble_secondary_enabled && $bubble_secondary_label === '' && $secondary_button) {
            $bubble_secondary_label = $secondary_button['label'];
        }
        if ($bubble_secondary_enabled && $bubble_secondary_url === '' && $secondary_button) {
            $bubble_secondary_url = $secondary_button['url'];
        }

        $fab_style_map = [
            'primary' => 'fab-btn--primary',
            'outline' => 'fab-btn--outline',
            'light' => 'fab-btn--light',
            'ghost' => 'fab-btn--ghost',
        ];
        $bubble_primary_class = $fab_style_map[$bubble_primary_style] ?? $fab_style_map['primary'];
        $bubble_secondary_class = $fab_style_map[$bubble_secondary_style] ?? $fab_style_map['outline'];
        $globe_svg_id = sanitize_html_class('flacso-hero-globe-' . $unique_id);
        $globe_glow_id = sanitize_html_class('flacso-hero-glow-' . $unique_id);

        ob_start();
        ?>
        <style>
        .flacso-hero-<?php echo esc_attr($unique_id); ?> {
            position: relative;
            width: 100%;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell {
            position: relative;
            display: flex;
            align-items: center;
            min-height: clamp(420px, 68svh, 700px);
            width: 100%;
            overflow: hidden;
            isolation: isolate;
            background:
                linear-gradient(90deg, rgba(9, 35, 89, 0.95) 0%, rgba(12, 49, 117, 0.90) 42%, rgba(11, 48, 117, 0.72) 62%, rgba(11, 48, 117, 0.66) 100%),
                url("<?php echo esc_url($background); ?>") center center / cover no-repeat;
            box-shadow: 0 28px 54px rgba(6, 17, 39, 0.3);
        }

        @supports (height: 100svh) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell {
                min-height: clamp(420px, 68svh, 700px);
            }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 18% 20%, rgba(255, 255, 255, 0.09) 0, rgba(255, 255, 255, 0) 32%),
                radial-gradient(circle at 80% 26%, rgba(255, 255, 255, 0.1) 0, rgba(255, 255, 255, 0) 24%);
            z-index: -1;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(7, 26, 68, 0.12) 0%, rgba(7, 26, 68, 0.08) 25%, rgba(7, 26, 68, 0.3) 100%);
            z-index: -1;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: clamp(1.2rem, 3vw, 2.4rem);
            align-items: center;
            width: 100%;
            padding-block: clamp(2.2rem, 8svh, 4.8rem);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy {
            position: relative;
            z-index: 2;
            max-width: 56rem;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-kicker {
            margin: 0 0 clamp(0.7rem, 1.2vw, 1rem);
            color: rgba(255, 255, 255, 0.9);
            font-size: clamp(0.88rem, 1.15vw, 1.04rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.4;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
            margin: 0;
            max-width: 11.5ch;
            color: #ffffff;
            font-family: "Sora", var(--global-heading-font-family, "Helvetica Neue", sans-serif);
            font-size: clamp(2.35rem, 11.2vw, 5.8rem);
            line-height: 0.94;
            font-weight: 800;
            letter-spacing: -0.055em;
            text-wrap: balance;
            word-break: normal;
            overflow-wrap: normal;
            hyphens: none;
            text-shadow: 0 8px 26px rgba(0, 0, 0, 0.33);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-year {
            color: #f0b44b;
            text-shadow: 0 0 20px rgba(240, 180, 75, 0.38);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
            margin: clamp(0.95rem, 2.2vw, 1.55rem) 0 0;
            max-width: 38ch;
            color: rgba(244, 247, 253, 0.92);
            font-size: clamp(1.02rem, 2.4vw, 1.75rem);
            font-weight: 500;
            line-height: 1.36;
            letter-spacing: -0.012em;
            text-shadow: 0 5px 16px rgba(0, 0, 0, 0.32);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
            margin-top: clamp(1.1rem, 2.6vw, 2rem);
            display: flex;
            flex-wrap: wrap;
            gap: 0.66rem;
            align-items: center;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.64rem 1.32rem;
            text-decoration: none;
            font-size: 0.97rem;
            font-weight: 700;
            line-height: 1.18;
            border: 1.5px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            white-space: nowrap;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--primary {
            background: #e39a18;
            border-color: #e39a18;
            color: #17233d;
            box-shadow: 0 12px 28px rgba(227, 154, 24, 0.32);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.34);
            color: #ffffff;
            backdrop-filter: blur(8px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--light {
            background: rgba(248, 252, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.42);
            color: #f5f9ff;
            backdrop-filter: blur(8px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost {
            background: rgba(10, 25, 61, 0.35);
            border-color: rgba(184, 202, 240, 0.45);
            color: #f2f6ff;
            backdrop-filter: blur(8px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.24);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--primary:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--primary:focus-visible {
            background: #f0aa2f;
            border-color: #f0aa2f;
            color: #17233d;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--outline:focus-visible,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--light:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--light:focus-visible,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn--ghost:focus-visible {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.54);
            color: #ffffff;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-visual {
            position: relative;
            display: none;
            justify-content: center;
            align-items: center;
            perspective: 1600px;
            min-height: 0;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-3d {
            position: relative;
            width: min(100%, 540px);
            aspect-ratio: 1;
            transform-style: preserve-3d;
            animation: flacsoHeroGlobeFloat 9s ease-in-out infinite;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-backglow {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 50%, rgba(20, 61, 146, 0.2) 0%, rgba(20, 61, 146, 0.11) 36%, rgba(20, 61, 146, 0) 70%);
            filter: blur(26px);
            transform: translateZ(-80px) scale(1.08);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-stage {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            filter: drop-shadow(0 18px 40px rgba(0, 0, 0, 0.22));
            animation: flacsoHeroGlobeTilt 14s ease-in-out infinite;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transform: translateZ(1px);
            pointer-events: none;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-highlight {
            position: absolute;
            inset: 6%;
            border-radius: 50%;
            background: radial-gradient(circle at 28% 22%, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0.1) 10%, rgba(255, 255, 255, 0) 32%);
            transform: translateZ(36px);
            pointer-events: none;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-shadow {
            position: absolute;
            left: 50%;
            bottom: -1.2rem;
            width: 66%;
            height: 2.4rem;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.24);
            filter: blur(24px);
            transform: translateX(-50%) translateZ(-110px);
            animation: flacsoHeroGlobeShadow 9s ease-in-out infinite;
            pointer-events: none;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-svg {
            display: block;
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        @keyframes flacsoHeroGlobeFloat {
            0%,
            100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes flacsoHeroGlobeTilt {
            0%,
            100% {
                transform: rotateY(-10deg) rotateX(5deg);
            }
            25% {
                transform: rotateY(-4deg) rotateX(1deg);
            }
            50% {
                transform: rotateY(8deg) rotateX(6deg);
            }
            75% {
                transform: rotateY(2deg) rotateX(2deg);
            }
        }

        @keyframes flacsoHeroGlobeShadow {
            0%,
            100% {
                transform: translateX(-50%) translateZ(-110px) scaleX(1);
                opacity: 0.34;
            }
            50% {
                transform: translateX(-50%) translateZ(-110px) scaleX(0.92);
                opacity: 0.22;
            }
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-fab {
            position: fixed;
            right: clamp(10px, 2.2vw, 28px);
            bottom: calc(10px + env(safe-area-inset-bottom));
            z-index: 1100;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.54rem;
            max-width: min(92vw, 360px);
            opacity: 0;
            transform: translateY(12px);
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-fab.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.46rem;
            text-decoration: none;
            min-height: 44px;
            padding: 0.58rem 1rem;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.1;
            border: 1.6px solid transparent;
            box-shadow: 0 8px 22px rgba(8, 16, 36, 0.24);
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link {
            width: min(92vw, 300px);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link:focus-visible,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top:hover,
        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 11px 28px rgba(6, 13, 30, 0.34);
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--primary {
            background: #f2c221;
            border-color: #f2c221;
            color: #0f1a2d;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--outline {
            background: rgba(255, 255, 255, 0.96);
            border-color: #1d3a72;
            color: #1d3a72;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--light {
            background: rgba(245, 249, 255, 0.95);
            border-color: rgba(255, 255, 255, 0.95);
            color: #10213f;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-btn--ghost {
            background: rgba(11, 34, 81, 0.9);
            border-color: rgba(176, 203, 246, 0.7);
            color: #ffffff;
        }

        .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top {
            width: 46px;
            min-width: 46px;
            height: 46px;
            padding: 0;
            border-radius: 999px;
            border-color: #1d3a72;
            background: #1d3a72;
            color: #ffffff;
        }

        @media (max-width: 991.98px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell {
                min-height: clamp(360px, 62svh, 560px);
                background-position: 60% center;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                max-width: 16ch;
                letter-spacing: -0.045em;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                max-width: 44ch;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
                width: 100%;
                min-width: 0;
                justify-content: center;
                white-space: normal;
            }
        }

        @media (min-width: 1024px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell {
                min-height: clamp(300px, 44svh, 470px);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-grid {
                grid-template-columns: minmax(0, 1.18fr) minmax(280px, 0.82fr);
                gap: clamp(0.9rem, 1.8vw, 1.6rem);
                padding-block: clamp(0.85rem, 1.9svh, 1.55rem);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy {
                max-width: 60rem;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                max-width: 13.6ch;
                font-size: clamp(2.55rem, 3.95vw, 4.2rem);
                line-height: 0.92;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                max-width: 48ch;
                font-size: clamp(0.98rem, 1.25vw, 1.3rem);
                line-height: 1.34;
                margin-top: clamp(0.65rem, 1.25vw, 1.1rem);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
                max-width: 60rem;
                margin-top: clamp(0.85rem, 1.5vw, 1.2rem);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-visual {
                display: flex;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-3d {
                width: min(100%, 470px);
            }
        }

        @media (min-width: 1280px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-3d {
                width: min(100%, 520px);
            }
        }

        @media (max-width: 1023.98px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-visual {
                display: none !important;
            }
        }

        @media (max-width: 767.98px) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-shell {
                min-height: clamp(340px, 58svh, 520px);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-copy {
                margin-inline: auto;
                text-align: center;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-kicker {
                font-size: 0.82rem;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-title {
                font-size: clamp(1.85rem, 8.9vw, 2.9rem);
                line-height: 1.02;
                letter-spacing: -0.024em;
                max-width: 18ch;
                margin-inline: auto;
                text-wrap: pretty;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-subtitle {
                font-size: clamp(0.94rem, 4.1vw, 1.08rem);
                line-height: 1.42;
                max-width: 33ch;
                margin-inline: auto;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-buttons {
                align-items: center;
                justify-content: center;
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn {
                width: min(100%, 340px);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-fab {
                right: 10px;
                bottom: calc(10px + env(safe-area-inset-bottom));
                max-width: calc(100vw - 20px);
            }

            .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link {
                width: min(100%, 300px);
                max-width: calc(100vw - 20px);
                font-size: 0.8rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .hero-btn,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-link,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .fab-top,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-fab,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-3d,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-stage,
            .flacso-hero-<?php echo esc_attr($unique_id); ?> .flacso-hero-globe-shadow {
                transition: none !important;
                animation: none !important;
            }
        }
        </style>

        <div class="flacso-hero-<?php echo esc_attr($unique_id); ?>">
            <section class="flacso-hero-shell" id="hero-<?php echo esc_attr($unique_id); ?>">
                <div class="flacso-content-shell">
                    <div class="flacso-hero-grid">
                        <header class="flacso-hero-copy">
                            <p class="flacso-hero-kicker"><?php echo esc_html($kicker_text); ?></p>
                            <h1 class="flacso-hero-title"><?php echo $title_markup; ?></h1>
                            <p class="flacso-hero-subtitle"><?php echo esc_html($subtitle_plain); ?></p>

                            <?php if ($show_hero_buttons) : ?>
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
                                        <a class="flacso-btn hero-btn <?php echo esc_attr($style_class); ?>" href="<?php echo esc_url($button_data['url']); ?>">
                                            <?php echo esc_html($button_data['label']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </header>

                        <div class="flacso-hero-visual" aria-hidden="true">
                            <div class="flacso-hero-globe-3d">
                                <div class="flacso-hero-globe-backglow"></div>
                                <div class="flacso-hero-globe-stage">
                                    <div class="flacso-hero-globe-ring"></div>
                                    <svg id="<?php echo esc_attr($globe_svg_id); ?>" class="flacso-hero-globe-svg" data-flacso-hero-globe viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid meet"></svg>
                                    <div class="flacso-hero-globe-highlight"></div>
                                    <div class="flacso-hero-globe-shadow"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <div class="flacso-hero-fab" data-flacso-hero-fab>
                <?php if ($bubble_primary_enabled && $bubble_primary_url !== '' && $bubble_primary_label !== '') : ?>
                    <a class="fab-link <?php echo esc_attr($bubble_primary_class); ?>" href="<?php echo esc_url($bubble_primary_url); ?>">
                        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                        <span><?php echo esc_html($bubble_primary_label); ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($bubble_secondary_enabled && $bubble_secondary_url !== '' && $bubble_secondary_label !== '') : ?>
                    <a class="fab-link <?php echo esc_attr($bubble_secondary_class); ?>" href="<?php echo esc_url($bubble_secondary_url); ?>">
                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                        <span><?php echo esc_html($bubble_secondary_label); ?></span>
                    </a>
                <?php endif; ?>

                <button class="fab-top" type="button" data-flacso-hero-top aria-label="<?php esc_attr_e('Ir arriba', 'flacso-main-page'); ?>">
                    <i class="bi bi-arrow-up-short" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <script>
        (function () {
            const root = document.querySelector('.flacso-hero-<?php echo esc_js($unique_id); ?>');
            if (!root) {
                return;
            }

            const hero = root.querySelector('#hero-<?php echo esc_js($unique_id); ?>');
            const fab = root.querySelector('[data-flacso-hero-fab]');
            const topBtn = root.querySelector('[data-flacso-hero-top]');
            const globeSvg = root.querySelector('#<?php echo esc_js($globe_svg_id); ?>');
            const globeGlowId = '<?php echo esc_js($globe_glow_id); ?>';
            const worldAtlasUrl = 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json';

            const initFab = function () {
                if (topBtn && hero) {
                    topBtn.addEventListener('click', function () {
                        hero.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }

                if (!fab || !hero) {
                    return;
                }

                const controls = fab.querySelectorAll('.fab-link, [data-flacso-hero-top]');
                if (!controls.length) {
                    return;
                }

                const setVisible = function (visible) {
                    fab.classList.toggle('is-visible', !!visible);
                };

                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            setVisible(!entry.isIntersecting);
                        });
                    }, { threshold: 0.24 });
                    observer.observe(hero);
                    return;
                }

                setVisible(true);
            };

            const loadScriptOnce = function (src, globalKey) {
                const registry = window.__flacsoHeroScriptRegistry || (window.__flacsoHeroScriptRegistry = {});
                if (registry[globalKey]) {
                    return registry[globalKey];
                }

                registry[globalKey] = new Promise(function (resolve, reject) {
                    if (window[globalKey]) {
                        resolve(window[globalKey]);
                        return;
                    }

                    const existing = document.querySelector('script[src="' + src + '"]');
                    if (existing) {
                        existing.addEventListener('load', function () { resolve(window[globalKey]); });
                        existing.addEventListener('error', function () { reject(new Error('No se pudo cargar ' + src)); });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    script.onload = function () { resolve(window[globalKey]); };
                    script.onerror = function () { reject(new Error('No se pudo cargar ' + src)); };
                    document.head.appendChild(script);
                });

                return registry[globalKey];
            };

            const initGlobe = async function () {
                if (!globeSvg || globeSvg.dataset.initialized === 'true') {
                    return;
                }
                if (window.matchMedia && window.matchMedia('(max-width: 1023.98px)').matches) {
                    return;
                }

                try {
                    await loadScriptOnce('https://cdn.jsdelivr.net/npm/d3@7', 'd3');
                    await loadScriptOnce('https://cdn.jsdelivr.net/npm/topojson-client@3', 'topojson');
                    if (!window.d3 || !window.topojson) {
                        return;
                    }

                    const d3 = window.d3;
                    const topojson = window.topojson;
                    const width = 1000;
                    const height = 1000;
                    const projection = d3.geoOrthographic()
                        .rotate([56, 32.5, 0])
                        .scale(300)
                        .translate([width / 2, height / 2]);
                    const path = d3.geoPath(projection);
                    const graticule = d3.geoGraticule10();
                    const svg = d3.select(globeSvg);
                    svg.selectAll('*').remove();

                    svg.append('path')
                        .datum({ type: 'Sphere' })
                        .attr('d', path)
                        .attr('fill', '#143d92')
                        .attr('stroke', 'rgba(255,255,255,0.16)')
                        .attr('stroke-width', 1.5);

                    svg.append('path')
                        .datum(graticule)
                        .attr('d', path)
                        .attr('fill', 'none')
                        .attr('stroke', 'rgba(255,255,255,0.18)')
                        .attr('stroke-width', 0.8);

                    const response = await fetch(worldAtlasUrl, { credentials: 'omit' });
                    if (!response.ok) {
                        return;
                    }
                    const topo = await response.json();
                    if (!topo || !topo.objects || !topo.objects.countries) {
                        return;
                    }

                    const countries = topojson.feature(topo, topo.objects.countries).features || [];

                    svg.append('g')
                        .selectAll('path')
                        .data(countries)
                        .join('path')
                        .attr('d', path)
                        .attr('fill', '#f3f4f6')
                        .attr('stroke', 'rgba(110,116,130,0.55)')
                        .attr('stroke-width', 0.45);

                    const uruguay = countries.filter(function (country) {
                        const props = country && country.properties ? country.properties : {};
                        const name = String(props.name || props.NAME || props.NAME_LONG || props.ADMIN || '');
                        const iso = String(props.iso_a3 || props.ISO_A3 || props.adm0_a3 || '').toUpperCase();
                        const numericId = String(country && country.id ? country.id : '');
                        return iso === 'URY' || /uruguay/i.test(name) || numericId === '858';
                    });

                    if (uruguay.length) {
                        const defs = svg.append('defs');
                        const filter = defs.append('filter')
                            .attr('id', globeGlowId)
                            .attr('x', '-50%')
                            .attr('y', '-50%')
                            .attr('width', '200%')
                            .attr('height', '200%');

                        filter.append('feDropShadow')
                            .attr('dx', 0)
                            .attr('dy', 0)
                            .attr('stdDeviation', 4)
                            .attr('flood-color', '#e58c00')
                            .attr('flood-opacity', 0.95);

                        svg.append('g')
                            .selectAll('path')
                            .data(uruguay)
                            .join('path')
                            .attr('d', path)
                            .attr('fill', '#e58c00')
                            .attr('stroke', '#8a5300')
                            .attr('stroke-width', 2.4)
                            .attr('filter', 'url(#' + globeGlowId + ')');
                    }

                    globeSvg.dataset.initialized = 'true';
                } catch (error) {
                    if (window.console && typeof window.console.warn === 'function') {
                        window.console.warn('FLACSO hero globe error:', error);
                    }
                }
            };

            initFab();
            initGlobe();

            const desktopQuery = window.matchMedia ? window.matchMedia('(min-width: 1024px)') : null;
            if (desktopQuery) {
                const onDesktopChange = function (event) {
                    if (event.matches) {
                        initGlobe();
                    }
                };
                if (typeof desktopQuery.addEventListener === 'function') {
                    desktopQuery.addEventListener('change', onDesktopChange);
                } else if (typeof desktopQuery.addListener === 'function') {
                    desktopQuery.addListener(onDesktopChange);
                }
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
