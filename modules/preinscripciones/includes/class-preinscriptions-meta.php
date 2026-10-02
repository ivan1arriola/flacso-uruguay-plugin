<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Meta {
    public const FORM_META = 'preinscripcion_formulario';
    public const ORIENTATIONS_META = 'preinscripcion_orientaciones';

    public static function init(): void {
        foreach (['cohorte', 'edicion'] as $post_type) {
            register_post_meta($post_type, self::FORM_META, self::definition([
                FLACSO_Preinscriptions_Config::class,
                'sanitize_inputs',
            ]));
            register_post_meta($post_type, self::ORIENTATIONS_META, self::definition([
                FLACSO_Preinscriptions_Config::class,
                'sanitize_orientations',
            ]));
        }
    }

    private static function definition(callable $sanitize_callback): array {
        return [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => $sanitize_callback,
            'auth_callback'     => static function (): bool {
                return current_user_can('manage_options');
            },
        ];
    }
}
