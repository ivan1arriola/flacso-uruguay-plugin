<?php
/**
 * Registro declarativo de módulos de FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Uruguay_Module_Registry {
    public static function definitions(): array {
        return [
            'site' => [
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
                // La portada funciona aunque falle una integración opcional;
                // el registry simplemente omite la sección correspondiente.
                'depends' => [],
                'optional_depends' => ['eventos', 'seminarios', 'convenios', 'oferta-academica', 'mailing'],
                'required' => true,
                'legacy' => false,
            ],
            'preinscripcion' => [
                'depends' => ['seminarios', 'oferta-academica', 'formularios'],
                'required' => true,
                'legacy' => false,
            ],

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
