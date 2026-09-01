<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente HTTP para la API privada de Django Preinscripciones.
 *
 * WordPress envía PUT a Django para declarar qué Cohorte o Edición
 * tiene preinscripciones abiertas. Django devuelve la URL canónica.
 *
 * Configuración requerida en wp-config.php:
 *   define('FLACSO_DJANGO_API_URL',   'https://preinscripciones.flacso.edu.uy/api/v1/');
 *   define('FLACSO_DJANGO_API_TOKEN', 'el-token-secreto');
 */
final class FLACSO_Django_API_Client {

    private static function api_url(): string {
        return defined('FLACSO_DJANGO_API_URL')
            ? rtrim(FLACSO_DJANGO_API_URL, '/') . '/'
            : '';
    }

    private static function api_token(): string {
        return defined('FLACSO_DJANGO_API_TOKEN') ? FLACSO_DJANGO_API_TOKEN : '';
    }

    private static function is_configured(): bool {
        return self::api_url() !== '' && self::api_token() !== '';
    }

    /**
     * Hace PUT a Django y devuelve el array JSON de respuesta o WP_Error.
     */
    private static function put(string $endpoint, array $body) {
        if (!self::is_configured()) {
            return new WP_Error(
                'django_api_not_configured',
                'FLACSO_DJANGO_API_URL y FLACSO_DJANGO_API_TOKEN deben estar definidos en wp-config.php.'
            );
        }

        $url = self::api_url() . ltrim($endpoint, '/');
        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . self::api_token(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $message = is_array($data) && isset($data['error'])
                ? $data['error']
                : sprintf('Django respondió con HTTP %d.', $code);
            return new WP_Error('django_api_error', $message, ['status' => $code, 'body' => $raw]);
        }

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'django_api_invalid_response',
                'Django devolvió una respuesta JSON inválida.'
            );
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Ofertas Académicas / Cohortes
    // -------------------------------------------------------------------------

    /**
     * Abre la preinscripción de una Cohorte en Django.
     * Cierra automáticamente cualquier otra cohorte abierta de la misma Oferta.
     *
     * @return array{slug:string,url_preinscripcion:string,cohorte_activa:array}|WP_Error
     */
    public static function abrir_cohorte(int $oferta_id, int $cohorte_id) {
        $slug      = get_post_field('post_name', $oferta_id);
        $titulo    = get_the_title($oferta_id);
        $tipo      = FLACSO_Oferta_Academica::get_tipo($oferta_id);
        $url_info  = function_exists('get_permalink') ? get_permalink($oferta_id) : '';

        $numero      = absint(get_post_meta($cohorte_id, 'numero', true));
        $anio        = absint(get_post_meta($cohorte_id, 'anio_inicio', true));
        $fecha_inicio = (string) get_post_meta($cohorte_id, 'fecha_inicio', true);
        $fecha_fin    = (string) get_post_meta($cohorte_id, 'fecha_fin', true);
        $nombre_cohorte = (string) get_post_meta($cohorte_id, 'nombre', true)
            ?: FLACSO_Cohorte::display_name($numero);

        return self::put('ofertas/' . rawurlencode($slug) . '/', [
            'wordpress_id'   => $oferta_id,
            'titulo'         => $titulo,
            'tipo'           => $tipo,
            'url_informacion' => $url_info,
            'cohorte_activa' => [
                'wordpress_id' => $cohorte_id,
                'nombre'       => $nombre_cohorte,
                'numero'       => $numero,
                'anio_inicio'  => $anio ?: (int) substr($fecha_inicio, 0, 4),
                'fecha_inicio' => $fecha_inicio ?: null,
                'fecha_fin'    => $fecha_fin ?: null,
            ],
        ]);
    }

    /**
     * Cierra todas las preinscripciones abiertas de una Oferta.
     *
     * @return array|WP_Error
     */
    public static function cerrar_cohortes(int $oferta_id) {
        $slug   = get_post_field('post_name', $oferta_id);
        $titulo = get_the_title($oferta_id);
        $tipo   = FLACSO_Oferta_Academica::get_tipo($oferta_id);
        $url_info = function_exists('get_permalink') ? get_permalink($oferta_id) : '';

        return self::put('ofertas/' . rawurlencode($slug) . '/', [
            'wordpress_id'   => $oferta_id,
            'titulo'         => $titulo,
            'tipo'           => $tipo,
            'url_informacion' => $url_info,
            'cohorte_activa' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Seminarios / Ediciones
    // -------------------------------------------------------------------------

    /**
     * Abre la preinscripción de una Edición de Seminario en Django.
     *
     * @return array{slug:string,url_preinscripcion:string,edicion_activa:array}|WP_Error
     */
    public static function abrir_edicion(int $seminario_id, int $edicion_id) {
        $slug     = get_post_field('post_name', $seminario_id);
        $titulo   = get_the_title($seminario_id);
        $url_info = function_exists('get_permalink') ? get_permalink($seminario_id) : '';

        $anio         = absint(get_post_meta($edicion_id, 'anio', true));
        $nombre_ed    = (string) get_post_meta($edicion_id, 'nombre', true) ?: "Edición {$anio}";
        $fecha_inicio = (string) get_post_meta($edicion_id, 'fecha_inicio', true);
        $fecha_fin    = (string) get_post_meta($edicion_id, 'fecha_fin', true);

        return self::put('seminarios/' . rawurlencode($slug) . '/', [
            'wordpress_id'   => $seminario_id,
            'titulo'         => $titulo,
            'url_informacion' => $url_info,
            'edicion_activa' => [
                'wordpress_id' => $edicion_id,
                'anio'         => $anio,
                'nombre'       => $nombre_ed,
                'fecha_inicio' => $fecha_inicio ?: null,
                'fecha_fin'    => $fecha_fin ?: null,
            ],
        ]);
    }

    /**
     * Cierra todas las preinscripciones abiertas de un Seminario.
     *
     * @return array|WP_Error
     */
    public static function cerrar_ediciones(int $seminario_id) {
        $slug   = get_post_field('post_name', $seminario_id);
        $titulo = get_the_title($seminario_id);
        $url_info = function_exists('get_permalink') ? get_permalink($seminario_id) : '';

        return self::put('seminarios/' . rawurlencode($slug) . '/', [
            'wordpress_id'  => $seminario_id,
            'titulo'        => $titulo,
            'url_informacion' => $url_info,
            'edicion_activa' => null,
        ]);
    }

    /**
     * Devuelve la URL pública de preinscripción para una OfertaAcademica.
     * No depende de que Django esté disponible — se deriva del slug localmente.
     */
    public static function url_preinscripcion_oferta(int $oferta_id): string {
        $base = defined('FLACSO_DJANGO_API_URL')
            ? preg_replace('#/api/v1/?$#', '', FLACSO_DJANGO_API_URL)
            : '';
        $slug = get_post_field('post_name', $oferta_id);
        return $base ? rtrim($base, '/') . "/oferta/{$slug}/" : '';
    }

    /**
     * Devuelve la URL pública de preinscripción para un Seminario.
     */
    public static function url_preinscripcion_seminario(int $seminario_id): string {
        $base = defined('FLACSO_DJANGO_API_URL')
            ? preg_replace('#/api/v1/?$#', '', FLACSO_DJANGO_API_URL)
            : '';
        $slug = get_post_field('post_name', $seminario_id);
        return $base ? rtrim($base, '/') . "/seminario/{$slug}/" : '';
    }
}
