<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Campaign source and resolver for the reversible home v2 rollout. */
final class FLACSO_Home_Campaign {
    public const POST_TYPE = 'flacso_home_campaign';
    public const META_KICKER = '_flacso_home_kicker';
    public const META_DESKTOP_IMAGE = '_flacso_home_desktop_image_id';
    public const META_MOBILE_IMAGE = '_flacso_home_mobile_image_id';
    public const META_START = '_flacso_home_start_at_utc';
    public const META_END = '_flacso_home_end_at_exclusive_utc';
    public const META_PRIORITY = '_flacso_home_priority';
    public const META_VARIANT = '_flacso_home_variant';
    public const OPTION_ENABLED = 'flacso_home_v2_enabled';

    public static function is_enabled(): bool {
        return (bool) get_option(self::OPTION_ENABLED, false);
    }

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Campañas de portada', 'flacso-uruguay'),
                'singular_name' => __('Campaña de portada', 'flacso-uruguay'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title', 'excerpt', 'thumbnail', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
        foreach ([self::META_KICKER, self::META_DESKTOP_IMAGE, self::META_MOBILE_IMAGE, self::META_START, self::META_END, self::META_PRIORITY, self::META_VARIANT, '_flacso_home_cta_primary_label', '_flacso_home_cta_primary_url', '_flacso_home_cta_secondary_label', '_flacso_home_cta_secondary_url'] as $key) {
            register_post_meta(self::POST_TYPE, $key, ['show_in_rest' => false, 'single' => true, 'type' => 'string', 'auth_callback' => static function () { return current_user_can('edit_posts'); }]);
        }
    }

    public static function fallback(): array {
        return [
            'id' => 0,
            'source' => 'fallback',
            'kicker' => 'EXCELENCIA ACADÉMICA. SIN FRONTERAS.',
            'title' => 'Posgrados y especializaciones de FLACSO Uruguay.',
            'description' => '100% online, estés donde estés.',
            'desktop_image_id' => 0,
            'mobile_image_id' => 0,
            'cta_primary' => ['label' => 'Conocé nuestra propuesta académica.', 'url' => '/formacion/'],
            'cta_secondary' => null,
            'variant' => 'institutional',
            'start_at' => null,
            'end_at' => null,
        ];
    }

    public static function sanitize_url(string $url): string {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (is_array($parts) && isset($parts['scheme']) && !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        if (isset($parts['host']) && strtolower((string) $parts['scheme']) !== 'https' && strpos((string) $parts['host'], 'flacso.edu.uy') !== false) {
            return '';
        }
        return esc_url_raw($url, ['http', 'https']);
    }

    public static function from_post($post): array {
        if (!$post || (int) $post->ID < 1) {
            return [];
        }
        $primary_url = self::sanitize_url((string) get_post_meta($post->ID, '_flacso_home_cta_primary_url', true));
        $primary_label = trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_flacso_home_cta_primary_label', true)));
        $secondary_url = self::sanitize_url((string) get_post_meta($post->ID, '_flacso_home_cta_secondary_url', true));
        $secondary_label = trim(wp_strip_all_tags((string) get_post_meta($post->ID, '_flacso_home_cta_secondary_label', true)));
        $variant = sanitize_key((string) get_post_meta($post->ID, self::META_VARIANT, true));
        if (!in_array($variant, ['institutional', 'enrollment', 'event', 'call'], true)) {
            $variant = 'institutional';
        }
        return [
            'id' => (int) $post->ID,
            'source' => 'campaign',
            'kicker' => trim(wp_strip_all_tags((string) get_post_meta($post->ID, self::META_KICKER, true))),
            'title' => trim(wp_strip_all_tags((string) $post->post_title)),
            'description' => trim(wp_strip_all_tags((string) $post->post_excerpt)),
            'desktop_image_id' => absint(get_post_meta($post->ID, self::META_DESKTOP_IMAGE, true)),
            'mobile_image_id' => absint(get_post_meta($post->ID, self::META_MOBILE_IMAGE, true)),
            'cta_primary' => $primary_url !== '' && $primary_label !== '' ? ['label' => $primary_label, 'url' => $primary_url] : null,
            'cta_secondary' => $secondary_url !== '' && $secondary_label !== '' ? ['label' => $secondary_label, 'url' => $secondary_url] : null,
            'variant' => $variant,
            'start_at' => self::iso((int) get_post_meta($post->ID, self::META_START, true)),
            'end_at' => self::iso((int) get_post_meta($post->ID, self::META_END, true)),
        ];
    }

    private static function iso(int $timestamp): ?string {
        return $timestamp > 0 ? gmdate('c', $timestamp) : null;
    }

    public static function active(?DateTimeImmutable $at = null): array {
        $at = $at ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $at->getTimestamp();
        $posts = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'no_found_rows' => true]);
        $eligible = [];
        foreach ($posts as $post) {
            $start = (int) get_post_meta($post->ID, self::META_START, true);
            $end = (int) get_post_meta($post->ID, self::META_END, true);
            $priority = (int) get_post_meta($post->ID, self::META_PRIORITY, true);
            if ($start <= $now && (!$end || $now < $end) && $priority >= 0 && $priority <= 10) {
                $eligible[] = ['post' => $post, 'priority' => $priority, 'start' => $start];
            }
        }
        usort($eligible, static function (array $a, array $b): int {
            return ($b['priority'] <=> $a['priority']) ?: (($b['start'] <=> $a['start']) ?: ((int) $b['post']->ID <=> (int) $a['post']->ID));
        });
        return !empty($eligible) ? self::from_post($eligible[0]['post']) : self::fallback();
    }

    public static function view_model(?DateTimeImmutable $at = null): array {
        return ['campaign' => self::active($at), 'mode' => self::is_enabled() ? 'v2' : 'legacy'];
    }
}
