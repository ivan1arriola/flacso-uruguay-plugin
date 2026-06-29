<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Flacso_Nav_Announcement')) {
    class Flacso_Nav_Announcement {
        private const STYLE_HANDLE = 'flacso-nav-announcement';

        private const OPTION_ENABLED = 'flacso_nav_announcement_enabled';
        private const OPTION_URL = 'flacso_nav_announcement_url';
        private const OPTION_KICKER = 'flacso_nav_announcement_kicker';
        private const OPTION_MESSAGE = 'flacso_nav_announcement_message';
        private const OPTION_CTA = 'flacso_nav_announcement_cta';
        private const OPTION_ARIA = 'flacso_nav_announcement_aria';
        private const OPTION_HIDE_FORMACION = 'flacso_nav_announcement_hide_formacion';

        public static function init(): void {
            if (is_admin()) {
                return;
            }

            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
            add_action('kadence_after_header', [__CLASS__, 'render'], 15);
        }

        public static function enqueue_assets(): void {
            if (!self::should_render(self::get_settings())) {
                return;
            }

            wp_register_style(self::STYLE_HANDLE, false, [], '1.0.0');
            wp_enqueue_style(self::STYLE_HANDLE);
            wp_add_inline_style(self::STYLE_HANDLE, self::get_css());
        }

        public static function render(): void {
            $settings = self::get_settings();
            if (!self::should_render($settings)) {
                return;
            }

            $aria_label = $settings['aria'] !== ''
                ? $settings['aria']
                : self::build_aria_label($settings);
            ?>
            <div class="flacso-banner-full-clickable">
                <a
                    href="<?php echo esc_url($settings['url']); ?>"
                    class="banner-link-wrapper"
                    aria-label="<?php echo esc_attr($aria_label); ?>">
                    <div class="ticker-wrap">
                        <div class="ticker-move">
                            <?php for ($index = 0; $index < 4; $index++): ?>
                                <?php self::render_message($settings); ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </a>
            </div>
            <?php
        }

        private static function render_message(array $settings): void {
            ?>
            <div class="ticker-content">
                <?php if ($settings['kicker'] !== ''): ?>
                    <span class="ticker-kicker"><?php echo esc_html($settings['kicker']); ?></span>
                <?php endif; ?>

                <span class="ticker-msg"><?php echo esc_html($settings['message']); ?></span>

                <?php if ($settings['cta'] !== ''): ?>
                    <span class="ticker-msg btn-tag"><?php echo esc_html($settings['cta']); ?></span>
                <?php endif; ?>
            </div>
            <?php
        }

        private static function should_render(array $settings): bool {
            if (is_admin() || wp_doing_ajax() || is_feed() || is_embed()) {
                return false;
            }

            if (function_exists('wp_is_json_request') && wp_is_json_request()) {
                return false;
            }

            if (!$settings['enabled']) {
                return false;
            }

            if ($settings['url'] === '' || $settings['message'] === '') {
                return false;
            }

            if ($settings['hide_formacion'] && self::is_formacion_request()) {
                return false;
            }

            return true;
        }

        private static function is_formacion_request(): bool {
            if (function_exists('is_page') && is_page('formacion')) {
                return true;
            }

            $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
            $path = is_string($request_uri) ? (string) parse_url($request_uri, PHP_URL_PATH) : '';
            $path = trim($path, '/');

            return strtolower($path) === 'formacion';
        }

        private static function build_aria_label(array $settings): string {
            $parts = array_filter([
                $settings['kicker'],
                $settings['message'],
                $settings['cta'],
            ]);

            if (!empty($parts)) {
                return implode(' - ', $parts);
            }

            return __('Anuncio destacado de FLACSO Uruguay', 'flacso-uruguay');
        }

        private static function get_settings(): array {
            return [
                'enabled' => (bool) get_option(self::OPTION_ENABLED, 0),
                'url' => trim((string) get_option(self::OPTION_URL, '')),
                'kicker' => trim((string) get_option(self::OPTION_KICKER, 'Próxima apertura')),
                'message' => trim((string) get_option(self::OPTION_MESSAGE, 'Diplomas 2026 · Segundo semestre')),
                'cta' => trim((string) get_option(self::OPTION_CTA, 'Postúlate ahora')),
                'aria' => trim((string) get_option(self::OPTION_ARIA, '')),
                'hide_formacion' => (bool) get_option(self::OPTION_HIDE_FORMACION, 1),
            ];
        }

        private static function get_css(): string {
            return <<<'CSS'
.flacso-banner-full-clickable {
    width: 100% !important;
    height: 2.65rem;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    position: relative;
    background: linear-gradient(90deg, #0f1a2d 0%, #1d3a72 45%, #0f1a2d 100%);
    border-top: 1px solid rgba(254, 210, 34, 0.35);
    border-bottom: 1px solid rgba(254, 210, 34, 0.55);
    box-shadow: 0 4px 14px rgba(15, 26, 45, 0.18);
    z-index: 5;
}

.flacso-banner-full-clickable::before,
.flacso-banner-full-clickable::after {
    content: "";
    position: absolute;
    top: 0;
    width: 5rem;
    height: 100%;
    z-index: 2;
    pointer-events: none;
}

.flacso-banner-full-clickable::before {
    left: 0;
    background: linear-gradient(90deg, #0f1a2d 0%, rgba(15, 26, 45, 0) 100%);
}

.flacso-banner-full-clickable::after {
    right: 0;
    background: linear-gradient(270deg, #0f1a2d 0%, rgba(15, 26, 45, 0) 100%);
}

.banner-link-wrapper {
    display: flex;
    align-items: center;
    width: 100%;
    height: 100%;
    text-decoration: none !important;
    cursor: pointer;
}

.ticker-wrap {
    width: 100%;
    overflow: hidden;
}

.ticker-move {
    display: flex;
    width: max-content;
    animation: flacso-nav-announcement-slide 46s linear infinite;
    will-change: transform;
}

.ticker-content {
    display: inline-flex;
    align-items: center;
    gap: 0.85rem;
    white-space: nowrap;
    padding-right: 3.25rem;
}

.ticker-kicker {
    display: inline-flex;
    align-items: center;
    color: #0f1a2d;
    background: #fed222;
    border-radius: 999px;
    padding: 0.22rem 0.7rem;
    font-family: 'Montserrat', Arial, Helvetica, sans-serif;
    font-size: clamp(0.68rem, 1.5vw, 0.78rem);
    font-weight: 800;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ticker-msg {
    color: #ffffff;
    font-family: 'Montserrat', Arial, Helvetica, sans-serif;
    font-size: clamp(0.72rem, 1.5vw, 0.82rem);
    font-weight: 600;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.btn-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 1.65rem;
    padding: 0.28rem 0.85rem;
    color: #ffffff;
    background: #248138;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 999px;
    font-weight: 800;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22), 0 2px 8px rgba(0, 0, 0, 0.18);
    transition: background-color 0.25s ease, color 0.25s ease, transform 0.25s ease;
}

.btn-tag::after {
    content: "->";
    margin-left: 0.45rem;
    font-weight: 900;
}

.banner-link-wrapper:hover .btn-tag,
.banner-link-wrapper:focus-visible .btn-tag {
    background: #fed222;
    color: #0f1a2d;
    transform: translateY(-1px);
}

@keyframes flacso-nav-announcement-slide {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-25%);
    }
}

@media (max-width: 768px) {
    .flacso-banner-full-clickable {
        height: 2.4rem;
    }

    .flacso-banner-full-clickable::before,
    .flacso-banner-full-clickable::after {
        width: 2.5rem;
    }

    .ticker-content {
        gap: 0.6rem;
        padding-right: 2rem;
    }

    .ticker-kicker {
        padding: 0.18rem 0.58rem;
    }

    .btn-tag {
        min-height: 1.45rem;
        padding: 0.22rem 0.65rem;
    }

    .ticker-move {
        animation-duration: 34s;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ticker-move {
        animation-duration: 90s;
    }
}
CSS;
        }
    }
}
