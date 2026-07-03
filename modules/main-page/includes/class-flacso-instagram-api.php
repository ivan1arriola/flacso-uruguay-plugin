<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Instagram_API {
    private const TRANSIENT_KEY = 'flacso_instagram_feed_cache_v3';
    private const CACHE_TIME = HOUR_IN_SECONDS * 2; // 2 hours

    /**
     * Gets the latest media from the Instagram API.
     * Uses transient caching.
     *
     * @return array|WP_Error Array of media items or WP_Error on failure.
     */
    public static function get_feed(bool $include_children = false) {
        $settings = Flacso_Main_Page_Settings::get_section('instagram');
        $access_token = $settings['access_token'] ?? '';
        
        if (empty($access_token)) {
            return new WP_Error('no_token', 'No access token configured for Instagram API.');
        }

        $cache_key = self::TRANSIENT_KEY . ($include_children ? '_with_children' : '');
        $cached_feed = get_transient($cache_key);
        if ($cached_feed !== false) {
            return $cached_feed;
        }

        // For Basic Display API, the endpoint is graph.instagram.com/me/media
        // For Graph API, we would normally need the IG User ID, but for simplicity we'll assume basic display usage for now.
        // Or if they provided a Graph API token, they might be using a page token. 
        // We'll use graph.instagram.com which works for Instagram Basic Display API tokens.
        $endpoint = 'https://graph.instagram.com/me/media';
        
        $params = [
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
            'access_token' => $access_token,
            'limit' =>  40
        ];

        $url = add_query_arg($params, $endpoint);

        $response = wp_remote_get($url, [
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            set_transient(self::TRANSIENT_KEY, $response, 5 * MINUTE_IN_SECONDS);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data['data'])) {
            $error_message = $data['error']['message'] ?? 'Unknown error fetching Instagram feed.';
            $error = new WP_Error('api_error', $error_message);
            set_transient(self::TRANSIENT_KEY, $error, 5 * MINUTE_IN_SECONDS);
            return $error;
        }

        $feed = [];
        foreach ($data['data'] as $item) {
            // Only keep image, video, carousel
            if (!in_array($item['media_type'], ['IMAGE', 'VIDEO', 'CAROUSEL_ALBUM'])) {
                continue;
            }

            $children = [];
            if ($include_children && ($item['media_type'] ?? '') === 'CAROUSEL_ALBUM') {
                $children = self::fetch_carousel_children((string) ($item['id'] ?? ''), $access_token);
            }

            $normalized_item = self::normalize_media_item($item, $children);
            if ($normalized_item) {
                $feed[] = $normalized_item;
            }
        }

        // Cache the parsed feed
        set_transient($cache_key, $feed, self::CACHE_TIME);

        return $feed;
    }

    /**
     * Clear the cache, usually called when settings are saved.
     */
    public static function clear_cache() {
        delete_transient(self::TRANSIENT_KEY);
        delete_transient(self::TRANSIENT_KEY . '_with_children');
    }

    private static function fetch_carousel_children(string $media_id, string $access_token): array {
        if ($media_id === '' || $access_token === '') {
            return [];
        }

        $url = add_query_arg([
            'fields' => 'id,media_type,media_url,thumbnail_url,permalink',
            'access_token' => $access_token,
        ], 'https://graph.instagram.com/' . rawurlencode($media_id) . '/children');

        $response = wp_remote_get($url, [
            'timeout' => 8,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || empty($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $children = [];
        foreach ($data['data'] as $child) {
            $normalized_child = self::normalize_child_item((array) $child);
            if ($normalized_child) {
                $children[] = $normalized_child;
            }
        }

        return $children;
    }

    private static function normalize_media_item(array $item, array $children = []): array {
        $media_url = (string) ($item['media_url'] ?? '');
        $thumbnail_url = (string) ($item['thumbnail_url'] ?? $media_url);

        if ($media_url === '' && $children) {
            $media_url = (string) ($children[0]['media_url'] ?? '');
            $thumbnail_url = (string) ($children[0]['thumbnail_url'] ?? $media_url);
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'caption' => (string) ($item['caption'] ?? ''),
            'media_type' => (string) ($item['media_type'] ?? ''),
            'media_url' => $media_url,
            'thumbnail_url' => $thumbnail_url !== '' ? $thumbnail_url : $media_url,
            'permalink' => (string) ($item['permalink'] ?? ''),
            'timestamp' => (string) ($item['timestamp'] ?? ''),
            'children' => $children,
        ];
    }

    private static function normalize_child_item(array $item): array {
        $media_url = (string) ($item['media_url'] ?? '');
        $thumbnail_url = (string) ($item['thumbnail_url'] ?? $media_url);

        if ($media_url === '' && $thumbnail_url === '') {
            return [];
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'media_type' => (string) ($item['media_type'] ?? ''),
            'media_url' => $media_url,
            'thumbnail_url' => $thumbnail_url !== '' ? $thumbnail_url : $media_url,
            'permalink' => (string) ($item['permalink'] ?? ''),
        ];
    }
}
