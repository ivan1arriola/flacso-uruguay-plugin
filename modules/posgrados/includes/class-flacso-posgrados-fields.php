<?php

if (!class_exists('FLACSO_Posgrados_Fields')) {
    class FLACSO_Posgrados_Fields {
        public const POST_TYPE   = 'page';
        public const CAPABILITY  = 'edit_pages';

        private static $fields_cache = null;

        public static function register_meta_and_support(): void {
            add_post_type_support(self::POST_TYPE, 'excerpt');

            self::register_meta_field('tipo_posgrado', [
                'sanitize_callback' => [__CLASS__, 'sanitize_tipo'],
            ]);

            self::register_meta_field('fecha_inicio', [
                'sanitize_callback' => [__CLASS__, 'sanitize_date'],
            ]);

            self::register_meta_field('proximo_inicio', [
                'sanitize_callback' => [__CLASS__, 'sanitize_date'],
            ]);

            self::register_meta_field('calendario_anio', [
                'sanitize_callback' => [__CLASS__, 'sanitize_year'],
            ]);

            self::register_meta_field('calendario_link', [
                'sanitize_callback' => [__CLASS__, 'sanitize_url'],
            ]);

            self::register_meta_field('malla_curricular_link', [
                'sanitize_callback' => [__CLASS__, 'sanitize_url'],
            ]);

            self::register_meta_field('imagen_promocional', [
                'type'              => 'integer',
                'sanitize_callback' => [__CLASS__, 'sanitize_media_id'],
            ]);

            self::register_meta_field('posgrado_activo', [
                'type'              => 'boolean',
                'sanitize_callback' => fn($value) => (bool) $value,
            ]);

            self::register_meta_field('abreviacion', [
                'sanitize_callback' => [__CLASS__, 'sanitize_abreviacion'],
            ]);

            self::register_meta_field('duracion', [
                'sanitize_callback' => [__CLASS__, 'sanitize_duracion'],
            ]);

            self::register_meta_field('link', [
                'sanitize_callback' => [__CLASS__, 'sanitize_url'],
            ]);
        }

        private static function register_meta_field(string $key, array $args): void {
            $defaults = [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> fn() => current_user_can(self::CAPABILITY),
            ];

            register_post_meta(
                self::POST_TYPE,
                $key,
                array_merge($defaults, $args)
            );
        }

        public static function allowed_tipos(): array {
            $tipos = ['Maestria', 'Especializacion', 'Diplomado', 'Diploma'];
            return apply_filters('flacso_pos_allowed_tipos', $tipos);
        }

        public static function get_fields(): array {
            if (self::$fields_cache !== null) {
                return self::$fields_cache;
            }

            $fields = [
                'posgrado_activo' => [
                    'label'    => 'Activo',
                    'type'     => 'checkbox',
                    'source'   => 'meta',
                    'sanitize' => fn($value) => (bool) $value,
                ],
                'imagen_promocional' => [
                    'label'    => 'Imagen promocional',
                    'type'     => 'media',
                    'source'   => 'meta',
                    'sanitize' => [__CLASS__, 'sanitize_media_id'],
                ],
                'tipo_posgrado' => [
                    'label'    => 'Tipo',
                    'type'     => 'select',
                    'source'   => 'meta',
                    'options'  => self::get_tipo_options(),
                    'sanitize' => [__CLASS__, 'sanitize_tipo'],
                    'readonly' => true,
                ],
                'proximo_inicio' => [
                    'label'    => 'Proximo inicio',
                    'type'     => 'date',
                    'source'   => 'meta',
                    'sanitize' => [__CLASS__, 'sanitize_date'],
                ],
                'calendario_anio' => [
                    'label'       => 'Calendario (Anio)',
                    'type'        => 'number',
                    'source'      => 'meta',
                    'placeholder' => '2025',
                    'sanitize'    => [__CLASS__, 'sanitize_year'],
                    'group'       => 'calendario',
                    'group_label' => 'Calendario',
                ],
                'calendario_link' => [
                    'label'       => 'Calendario (Link)',
                    'type'        => 'url',
                    'source'      => 'meta',
                    'placeholder' => 'https://...',
                    'sanitize'    => [__CLASS__, 'sanitize_url'],
                    'group'       => 'calendario',
                    'group_label' => 'Calendario',
                ],
                'malla_curricular_link' => [
                    'label'       => 'Malla Curricular (Link)',
                    'type'        => 'url',
                    'source'      => 'meta',
                    'placeholder' => 'https://...',
                    'sanitize'    => [__CLASS__, 'sanitize_url'],
                ],
                'abreviacion' => [
                    'label'       => 'Abreviacion',
                    'type'        => 'text',
                    'source'      => 'meta',
                    'placeholder' => 'EDUTIC / MESYP / IAPE...',
                    'sanitize'    => [__CLASS__, 'sanitize_abreviacion'],
                ],
                'duracion' => [
                    'label'       => 'Duracion',
                    'type'        => 'text',
                    'source'      => 'meta',
                    'placeholder' => '2 anos / 10 meses / 480 horas',
                    'sanitize'    => [__CLASS__, 'sanitize_duracion'],
                ],
                'post_excerpt' => [
                    'label'    => 'Resumen (Extracto)',
                    'type'     => 'textarea',
                    'source'   => 'post',
                    'sanitize' => fn($value) => wp_kses_post(trim((string) $value)),
                ],
            ];

            self::$fields_cache = apply_filters('flacso_pos_fields', $fields);

            return self::$fields_cache;
        }

        private static function get_tipo_options(): array {
            $options = ['' => '- Seleccionar -'];
            foreach (self::allowed_tipos() as $tipo) {
                $options[$tipo] = $tipo;
            }

            return $options;
        }

        public static function sanitize_date($value): string {
            $value = trim((string) $value);
            return ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : '';
        }

        public static function sanitize_tipo($value): string {
            $value = (string) $value;
            return in_array($value, self::allowed_tipos(), true) ? $value : '';
        }

        public static function sanitize_abreviacion($value): string {
            $value = strtoupper(trim((string) $value));
            return preg_replace('/[^A-Z0-9\-]/', '', $value);
        }

        public static function sanitize_duracion($value): string {
            return wp_kses_post(trim((string) $value));
        }

        public static function sanitize_year($value): string {
            $value = intval($value);
            if ($value < 1900 || $value > 2100) {
                return '';
            }
            return (string) $value;
        }

        public static function sanitize_url($value): string {
            $value = trim((string) $value);
            return esc_url_raw($value);
        }

        public static function sanitize_media_id($value): int {
            $id = absint($value);
            if (!$id) {
                return 0;
            }
            return get_post($id) ? $id : 0;
        }
    }
}
