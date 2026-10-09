<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Cache {
    private const CACHE_KEY = 'flacso_preinscriptions_catalog_v1';
    private const CACHE_GROUP = 'flacso_preinscriptions';
    private const TTL = 60;

    private static ?array $memory = null;
    private static bool $hooks_registered = false;

    public static function init(): void {
        if (self::$hooks_registered || !function_exists('add_action')) {
            return;
        }

        self::$hooks_registered = true;

        foreach (['cohorte', 'edicion', 'oferta-academica'] as $post_type) {
            add_action('save_post_' . $post_type, [self::class, 'handle_post_save'], 99, 1);
        }

        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'handle_meta_change'], 99, 4);
        }
    }

    public static function get_catalog(): array {
        if (is_array(self::$memory)) {
            return self::$memory;
        }

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
            if (is_array($cached)) {
                self::$memory = $cached;
                return $cached;
            }
        }

        if (function_exists('get_transient')) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                self::$memory = $cached;
                return $cached;
            }
        }

        $payload = [
            'version' => 1,
            'targets' => FLACSO_Preinscriptions_Serializer::all_targets(),
        ];

        self::$memory = $payload;

        if (function_exists('wp_cache_set')) {
            wp_cache_set(self::CACHE_KEY, $payload, self::CACHE_GROUP, self::TTL);
        }
        if (function_exists('set_transient')) {
            set_transient(self::CACHE_KEY, $payload, self::TTL);
        }

        return $payload;
    }

    public static function invalidate_for_post(int $post_id, ?string $meta_key = null): void {
        if ($post_id <= 0 || !function_exists('get_post_type')) {
            return;
        }

        $post_type = get_post_type($post_id);
        if (!in_array($post_type, ['cohorte', 'edicion', 'oferta-academica'], true)) {
            return;
        }

        self::clear();
    }

    public static function handle_post_save(int $post_id): void {
        self::invalidate_for_post($post_id);
    }

    public static function handle_meta_change($meta_id, int $post_id, string $meta_key, $meta_value): void {
        self::invalidate_for_post($post_id, $meta_key);
    }

    public static function clear(): void {
        self::$memory = null;

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
        }
        if (function_exists('delete_transient')) {
            delete_transient(self::CACHE_KEY);
        }
    }
}
