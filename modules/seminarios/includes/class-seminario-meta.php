<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Meta
{
    public static function definitions()
    {
        return array(
            'nombre' => array('type' => 'string'),
            'periodo_inicio' => array('type' => 'string'),
            'periodo_fin' => array('type' => 'string'),
            'creditos' => array('type' => 'number'),
            'carga_horaria' => array('type' => 'integer'),
            'acredita_maestria' => array('type' => 'boolean'),
            'acredita_doctorado' => array('type' => 'boolean'),
            'forma_aprobacion' => array('type' => 'string'),
            'modalidad' => array('type' => 'string'),
            'objetivo_general' => array('type' => 'string'),
            'presentacion_seminario' => array('type' => 'string'),
            'encuentros_sincronicos' => array(
                'type' => 'array',
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'fecha' => array('type' => 'string'),
                                'hora_inicio' => array('type' => 'string'),
                                'hora_fin' => array('type' => 'string'),
                                'plataforma' => array('type' => 'string'),
                                'plataforma_otro' => array('type' => 'string'),
                            ),
                        ),
                    ),
                ),
            ),
            'objetivos_especificos' => array(
                'type' => 'array',
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
            ),
            'unidades_academicas' => array(
                'type' => 'array',
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'titulo' => array('type' => 'string'),
                                'contenido' => array('type' => 'string'),
                            ),
                        ),
                    ),
                ),
            ),
            'docentes' => array(
                'type' => 'array',
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array('type' => 'integer'),
                    ),
                ),
            ),
        );
    }

    public static function register()
    {
        foreach (self::definitions() as $key => $definition) {
            $args = array(
                'type' => $definition['type'],
                'single' => true,
                'show_in_rest' => isset($definition['show_in_rest']) ? $definition['show_in_rest'] : true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_value'),
                'auth_callback' => array('Seminario_Helpers', 'permissions_write'),
            );

            register_post_meta('seminario', '_seminario_' . $key, $args);
        }
    }

    public static function sanitize_value($value, $meta_key)
    {
        $key = str_replace('_seminario_', '', $meta_key);

        if ($key === 'encuentros_sincronicos') {
            if (!is_array($value)) {
                return array();
            }

            $clean = array();
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $fecha = isset($row['fecha']) ? sanitize_text_field($row['fecha']) : '';
                $hora_inicio = isset($row['hora_inicio']) ? sanitize_text_field($row['hora_inicio']) : '';
                $hora_fin = isset($row['hora_fin']) ? sanitize_text_field($row['hora_fin']) : '';
                $plataforma = isset($row['plataforma']) ? sanitize_text_field($row['plataforma']) : 'zoom';
                $plataforma_otro = isset($row['plataforma_otro']) ? sanitize_text_field($row['plataforma_otro']) : '';

                if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $fecha = '';
                }
                if ($hora_inicio !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora_inicio)) {
                    $hora_inicio = '';
                }
                if ($hora_fin !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora_fin)) {
                    $hora_fin = '';
                }

                if ($fecha === '' && $hora_inicio === '' && $hora_fin === '') {
                    continue;
                }

                $clean[] = array(
                    'fecha' => $fecha,
                    'hora_inicio' => $hora_inicio,
                    'hora_fin' => $hora_fin,
                    'plataforma' => $plataforma,
                    'plataforma_otro' => $plataforma_otro,
                );
            }

            return $clean;
        }

        if ($key === 'objetivos_especificos') {
            if (!is_array($value)) {
                return array();
            }

            $clean = array();
            foreach ($value as $item) {
                $text = wp_kses_post($item);
                if ($text !== '') {
                    $clean[] = $text;
                }
            }

            return $clean;
        }

        if ($key === 'unidades_academicas') {
            if (!is_array($value)) {
                return array();
            }

            $clean = array();
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $titulo = isset($row['titulo']) ? sanitize_text_field($row['titulo']) : '';
                $contenido = isset($row['contenido']) ? wp_kses_post($row['contenido']) : '';

                if ($titulo === '' && $contenido === '') {
                    continue;
                }

                $clean[] = array(
                    'titulo' => $titulo,
                    'contenido' => $contenido,
                );
            }

            return $clean;
        }

        if ($key === 'docentes') {
            if (!is_array($value)) {
                return array();
            }

            $clean = array();
            foreach ($value as $item) {
                $id = absint($item);
                if ($id > 0) {
                    $clean[] = $id;
                }
            }

            return array_values(array_unique($clean));
        }

        if ($key === 'acredita_maestria' || $key === 'acredita_doctorado') {
            return (bool) $value;
        }

        if ($key === 'creditos') {
            $value = str_replace(',', '.', (string) $value);
            return preg_match('/^\d+(\.\d)?$/', $value) ? (float) $value : '';
        }

        if ($key === 'carga_horaria') {
            return is_numeric($value) ? (int) $value : '';
        }

        if ($key === 'periodo_inicio' || $key === 'periodo_fin') {
            $value = sanitize_text_field($value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
        }

        if (in_array($key, array('forma_aprobacion', 'objetivo_general', 'presentacion_seminario', 'modalidad', 'nombre'), true)) {
            $sanitized = wp_kses_post($value);
            if ($key === 'presentacion_seminario') {
                return wp_trim_words($sanitized, 250, '');
            }
            return $sanitized;
        }

        return sanitize_text_field($value);
    }

    public static function get_meta($post_id)
    {
        $meta = array();
        foreach (Seminario_Helpers::meta_keys() as $key) {
            $meta[$key] = get_post_meta($post_id, '_seminario_' . $key, true);
        }
        return $meta;
    }

    public static function update_from_request($post_id, $meta)
    {
        if (!is_array($meta)) {
            return;
        }

        foreach (Seminario_Helpers::meta_keys() as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $meta_key = '_seminario_' . $key;
            $value = self::sanitize_value($meta[$key], $meta_key);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
