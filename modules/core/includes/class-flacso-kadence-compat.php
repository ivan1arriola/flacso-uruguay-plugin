<?php
/**
 * Compatibilidad con bloques Kadence.
 *
 * Evita warnings "Array to string conversion" al normalizar atributos de
 * tipografia que llegan con estructuras inesperadas en algunos bloques.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Kadence_Compat {

    public static function init(): void {
        add_filter('render_block_data', [__CLASS__, 'sanitize_kadence_block_data'], 8, 2);
    }

    /**
     * Normaliza atributos de bloques Kadence antes del render.
     *
     * @param array $parsed_block
     * @param array $source_block
     * @return array
     */
    public static function sanitize_kadence_block_data($parsed_block, $source_block): array {
        if (!is_array($parsed_block)) {
            return is_array($source_block) ? $source_block : [];
        }

        $block_name = isset($parsed_block['blockName']) ? (string) $parsed_block['blockName'] : '';
        if ($block_name === '' || strpos($block_name, 'kadence/') !== 0) {
            return $parsed_block;
        }

        if (!isset($parsed_block['attrs']) || !is_array($parsed_block['attrs'])) {
            return $parsed_block;
        }

        $parsed_block['attrs'] = self::sanitize_tree($parsed_block['attrs']);
        return $parsed_block;
    }

    /**
     * Recorre recursivamente attrs buscando configuraciones tipograficas.
     *
     * @param array $data
     * @return array
     */
    private static function sanitize_tree(array $data): array {
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (self::looks_like_typography($value)) {
                $value = self::sanitize_typography($value);
            }

            $data[$key] = self::sanitize_tree($value);
        }

        return $data;
    }

    /**
     * Detecta estructura tipografica usada por Kadence.
     *
     * @param array $value
     * @return bool
     */
    private static function looks_like_typography(array $value): bool {
        if (!array_key_exists('size', $value)) {
            return false;
        }

        return isset($value['sizeType'])
            || isset($value['lineHeight'])
            || isset($value['letterSpacing'])
            || isset($value['family'])
            || isset($value['weight']);
    }

    /**
     * Sanea configuracion tipografica puntual.
     *
     * @param array $typography
     * @return array
     */
    private static function sanitize_typography(array $typography): array {
        if (isset($typography['size'])) {
            $typography['size'] = self::normalize_size($typography['size']);
        }

        if (isset($typography['sizeType']) && is_array($typography['sizeType'])) {
            $typography['sizeType'] = self::pick_scalar($typography['sizeType'], 'px');
        }

        return $typography;
    }

    /**
     * Fuerza formato de size a estructura esperada por Kadence [0,1,2].
     *
     * @param mixed $size
     * @return mixed
     */
    private static function normalize_size($size) {
        if (!is_array($size)) {
            return $size;
        }

        // Algunos bloques guardan size como ['desktop' => x, 'tablet' => y, 'mobile' => z].
        if (isset($size['desktop']) || isset($size['tablet']) || isset($size['mobile'])) {
            $size = [
                0 => self::pick_scalar($size['desktop'] ?? '', ''),
                1 => self::pick_scalar($size['tablet'] ?? '', ''),
                2 => self::pick_scalar($size['mobile'] ?? '', ''),
            ];
        }

        // Si cada breakpoint trae un array interno, lo aplana a un escalar.
        foreach ([0, 1, 2] as $index) {
            if (isset($size[$index]) && is_array($size[$index])) {
                $size[$index] = self::pick_scalar($size[$index], '');
            }
        }

        return $size;
    }

    /**
     * Extrae un valor escalar util desde una estructura anidada.
     *
     * @param mixed  $value
     * @param string $default
     * @return mixed
     */
    private static function pick_scalar($value, $default = '') {
        if (is_scalar($value)) {
            return (string) $value !== '' ? $value : $default;
        }

        if (!is_array($value)) {
            return $default;
        }

        $priority_keys = ['desktop', 'tablet', 'mobile', 'value', 'size', 'fontSize', 'slug', 0, 1, 2];
        foreach ($priority_keys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }
            if (!is_scalar($value[$key])) {
                continue;
            }
            if ((string) $value[$key] === '') {
                continue;
            }
            return $value[$key];
        }

        foreach ($value as $item) {
            if (is_scalar($item) && (string) $item !== '') {
                return $item;
            }
            if (is_array($item)) {
                $nested = self::pick_scalar($item, '');
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return $default;
    }
}
