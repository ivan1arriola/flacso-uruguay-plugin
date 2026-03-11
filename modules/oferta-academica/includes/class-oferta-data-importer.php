<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Importa datos de ofertas académicas desde tabla de datos
 */
class Oferta_Data_Importer {

    public static function import_data(): void {
        $ofertas = self::get_ofertas_data();

        foreach ($ofertas as $oferta) {
            self::create_oferta($oferta);
        }
    }

    private static function get_ofertas_data(): array {
        return [
            // Maestrías
            [
                'id' => 12330,
                'abrev' => 'EDUTIC',
                'tipo' => 'Maestría',
                'nombre' => 'Maestría en Educación, Innovación y Tecnologías',
                'correo' => 'edutic@flacso.edu.uy',
            ],
            [
                'id' => 12336,
                'abrev' => 'MESYP',
                'tipo' => 'Maestría',
                'nombre' => 'Maestría en Educación, Sociedad y Política',
                'correo' => 'mesyp@flacso.edu.uy',
            ],
            [
                'id' => 12343,
                'abrev' => 'MG',
                'tipo' => 'Maestría',
                'nombre' => 'Maestría en Género',
                'correo' => 'maestriagenero@flacso.edu.uy',
            ],
            // Especializaciones
            [
                'id' => 12310,
                'abrev' => 'EAPET',
                'tipo' => 'Especialización',
                'nombre' => 'Especialización en Análisis, Producción y Edición de Textos',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 12316,
                'abrev' => 'EGCCD',
                'tipo' => 'Especialización',
                'nombre' => 'Especialización en Género, Cambio Climático y Desastres',
                'correo' => 'genero@flacso.edu.uy',
            ],
            // Diplomados
            [
                'id' => 12278,
                'abrev' => 'DEPPI',
                'tipo' => 'Diplomado',
                'nombre' => 'Diplomado de Especialización en Género con Orientación en Políticas Públicas Integrales',
                'correo' => 'genero@flacso.edu.uy',
            ],
            [
                'id' => 14444,
                'abrev' => 'DESI',
                'tipo' => 'Diplomado',
                'nombre' => 'Diplomado de Especialización en Género con Orientación en Salud Integral',
                'correo' => 'genero@flacso.edu.uy',
            ],
            [
                'id' => 12282,
                'abrev' => 'DEVBG',
                'tipo' => 'Diplomado',
                'nombre' => 'Diplomado de Especialización en Género con Orientación en Violencia Basada en Género',
                'correo' => 'genero@flacso.edu.uy',
            ],
            [
                'id' => 12288,
                'abrev' => 'DEVNNA',
                'tipo' => 'Diplomado',
                'nombre' => 'Diplomado de Especialización sobre Violencias Hacia Niñas, Niños y Adolescentes',
                'correo' => 'dsvnna@flacso.edu.uy',
            ],
            // Diplomas
            [
                'id' => 13202,
                'abrev' => 'DCCH',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma Comprendiendo China: Cultura, Filosofía y Construcción Histórica del Gigante Asiático',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 12295,
                'abrev' => 'DAVIA',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en Abordaje de las Violencias Hacia las Infancias y Adolescencias',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 12299,
                'abrev' => 'DG',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en Género',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 20668,
                'abrev' => 'IAPE',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en IA y Prácticas de Enseñanza',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 12302,
                'abrev' => 'DIDYP',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en Infancias, Derechos y Políticas Públicas',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 14657,
                'abrev' => 'DSMSYT',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en Salud Mental, Subjetividad y Trabajo',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 12304,
                'abrev' => 'DMIC',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma en Metodología de la Investigación Cualitativa',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'id' => 13185,
                'abrev' => 'DIAMHU',
                'tipo' => 'Diploma',
                'nombre' => 'Diploma Infancias y Adolescencias en Contexto de Movilidad Humana',
                'correo' => 'inscripciones@flacso.edu.uy',
            ],
        ];
    }

    private static function create_oferta(array $data): int {
        // Verificar si ya existe
        $existing = get_posts([
            'post_type' => 'oferta-academica',
            'meta_key' => '_oferta_abrev',
            'meta_value' => $data['abrev'],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            return $existing[0];
        }

        // Crear post
        $post_id = wp_insert_post([
            'post_type' => 'oferta-academica',
            'post_title' => $data['nombre'],
            'post_status' => 'publish',
            'post_content' => '',
        ]);

        if (is_wp_error($post_id)) {
            return 0;
        }

        // Asignar taxonomía tipo-oferta-academica
        wp_set_object_terms($post_id, $data['tipo'], 'tipo-oferta-academica');

        // Guardar meta
        update_post_meta($post_id, '_oferta_abrev', $data['abrev']);
        update_post_meta($post_id, '_oferta_correo', $data['correo']);
        update_post_meta($post_id, 'abreviacion', $data['abrev']);
        update_post_meta($post_id, 'correo', $data['correo']);
        update_post_meta($post_id, 'inscripciones_abiertas', 0);

        // Asociar página
        if (!empty($data['id'])) {
            update_post_meta($post_id, '_oferta_page_id', intval($data['id']));
        }

        return $post_id;
    }
}
