<?php
/**
 * Registro declarativo de módulos de FLACSO Uruguay.
 *
 * Este archivo es la única fuente de verdad para dependencias y carácter
 * obligatorio/legacy de los módulos. El orden real lo resuelve el loader.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Uruguay_Module_Registry {
    /**
     * Definiciones canónicas de módulos.
     *
     * `path` permite mantener temporalmente carpetas históricas sin convertir
     * su nombre en parte del dominio actual (por ejemplo core/posgrados/shortcodes).
     */
    public static function definitions(): array {
        return [
            'site' => [
                // La clave canónica ya es `site`; la carpeta `core` queda como
                // detalle físico transitorio hasta completar su movimiento.
                'path' => 'core',
                'depends' => [],
                'required' => true,
                'legacy' => false,
            ],
            'docentes' => [
                'depends' => [],
                'required' => true,
                'legacy' => false,
            ],
            'autoridades' => [
                'depends' => ['docentes'],
                'required' => false,
                'legacy' => false,
            ],
            'seminarios' => [
                'depends' => ['docentes'],
                'required' => true,
                'legacy' => false,
            ],
            'eventos' => [
                'depends' => [],
                'required' => true,
                'legacy' => false,
            ],
            'convenios' => [
                'depends' => [],
                'required' => false,
                'legacy' => false,
            ],
            'oferta-academica' => [
                'depends' => ['docentes', 'seminarios'],
                'required' => true,
                'legacy' => false,
            ],
            'formularios' => [
                'depends' => [],
                'required' => true,
                'legacy' => false,
            ],
            'formularios-webhook' => [
                'depends' => [],
                'required' => false,
                'legacy' => false,
            ],
            'charlas-abiertas' => [
                'depends' => ['eventos'],
                'required' => false,
                'legacy' => false,
            ],
            'mailing' => [
                'depends' => [],
                'required' => false,
                'legacy' => false,
            ],
            'main-page' => [
                'depends' => ['eventos', 'seminarios', 'convenios', 'oferta-academica', 'mailing'],
                'required' => true,
                'legacy' => false,
            ],
            'preinscripcion' => [
                'depends' => ['seminarios', 'oferta-academica', 'formularios'],
                'required' => true,
                'legacy' => false,
            ],

            // Compatibilidad histórica. Estos módulos no representan dominios
            // actuales y deben poder retirarse cuando ya no tengan consumidores.
            'legacy-posgrados' => [
                'path' => 'posgrados',
                'depends' => ['docentes', 'oferta-academica'],
                'required' => false,
                'legacy' => true,
            ],
            'legacy-shortcodes' => [
                'path' => 'shortcodes',
                'depends' => ['oferta-academica'],
                'required' => false,
                'legacy' => true,
            ],
        ];
    }

    public static function boot(FLACSO_Uruguay_Loader $loader): bool {
        return $loader->load_registered_modules(self::definitions());
    }

    public static function active_module_keys(): array {
        return array_keys(array_filter(
            self::definitions(),
            static fn(array $definition): bool => empty($definition['legacy'])
        ));
    }

    public static function legacy_module_keys(): array {
        return array_keys(array_filter(
            self::definitions(),
            static fn(array $definition): bool => !empty($definition['legacy'])
        ));
    }
}
