<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_REST {
    public const NAMESPACE = 'flacso/v1';
    public const ROUTE     = '/preinscripciones';

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [self::class, 'register_routes']);
        }
    }

    public static function register_routes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => 'GET',
            'callback'            => [self::class, 'index'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function index($request): WP_REST_Response {
        $targets = FLACSO_Preinscriptions_Serializer::all_targets();

        $payload = [
            'version' => 1,
            'targets' => $targets,
        ];

        $response = new WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'public, max-age=60');

        return $response;
    }
}
