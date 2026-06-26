<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Instagram_API {
    private const TRANSIENT_KEY = 'flacso_instagram_feed_cache_v2';
    private const CACHE_TIME = HOUR_IN_SECONDS * 2; // 2 hours

    /**
     * Gets the latest media from the Instagram API.
     * Uses transient caching.
     *
     * @return array|WP_Error Array of media items or WP_Error on failure.
     */
    public static function get_feed() {
        $settings = Flacso_Main_Page_Settings::get_section('instagram');
        $access_token = $settings['access_token'] ?? '';
        
        if (empty($access_token)) {
            return new WP_Error('no_token', 'No access token configured for Instagram API.');
        }

        $cached_feed = get_transient(self::TRANSIENT_KEY);
        if ($cached_feed !== false) {
            return $cached_feed;
        }

        $count = intval($settings['count'] ?? 6);
        $api_type = $settings['api_type'] ?? 'basic';
        
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
            
                        $feed[] = [
                'id' => $item['id'],
                'caption' => $item['caption'] ?? '',
                'media_type' => $item['media_type'],
                'media_url' => $item['media_url'],
                'thumbnail_url' => $item['thumbnail_url'] ?? $item['media_url'],
                'permalink' => $item['permalink'],
                'timestamp' => $item['timestamp'],
            ];
        }

        // Cache the parsed feed
        set_transient(self::TRANSIENT_KEY, $feed, self::CACHE_TIME);

        return $feed;
    }

    /**
     * Clear the cache, usually called when settings are saved.
     */
    public static function clear_cache() {
        delete_transient(self::TRANSIENT_KEY);
    }
}
