<?php
/**
 * Funciones auxiliares del plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parseo básico de navegador/SO.
 */
function fc_parse_user_agent_simple( $ua ) {
	$ua = strtolower( (string) $ua );
	$browser = 'Desconocido';
	$os = 'Desconocido';

	if ( strpos( $ua, 'windows nt 10' ) !== false || strpos( $ua, 'windows nt 11' ) !== false ) {
		$os = 'Windows 10/11';
	} elseif ( strpos( $ua, 'windows nt 6.1' ) !== false ) {
		$os = 'Windows 7';
	} elseif ( strpos( $ua, 'mac os x' ) !== false ) {
		$os = 'macOS';
	} elseif ( strpos( $ua, 'android' ) !== false ) {
		$os = 'Android';
	} elseif ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
		$os = 'iOS';
	} elseif ( strpos( $ua, 'linux' ) !== false ) {
		$os = 'Linux';
	}

	if ( strpos( $ua, 'edg' ) !== false ) {
		$browser = 'Edge';
	} elseif ( strpos( $ua, 'chrome' ) !== false && strpos( $ua, 'chromium' ) === false ) {
		$browser = 'Chrome';
	} elseif ( strpos( $ua, 'firefox' ) !== false ) {
		$browser = 'Firefox';
	} elseif ( strpos( $ua, 'safari' ) !== false && strpos( $ua, 'chrome' ) === false ) {
		$browser = 'Safari';
	} elseif ( strpos( $ua, 'opr' ) !== false ) {
		$browser = 'Opera';
	}

	return [
		'browser' => $browser,
		'os'      => $os,
	];
}

/**
 * Resuelve el endpoint destino para solicitudes de informacion.
 *
 * Prioridad:
 * 1) opcion dedicada de oferta
 * 2) opcion de webhook de consultas (fallback de menu)
 */
function fc_get_info_request_webhook_url() {
    $candidate = trim( (string) get_option( 'fc_oferta_webhook_url', '' ) );
    if ( '' !== $candidate ) {
        return esc_url_raw( $candidate );
    }

    $candidate = trim( (string) get_option( 'fc_consultas_webhook_url', '' ) );
    if ( '' !== $candidate ) {
        return esc_url_raw( $candidate );
    }

    return '';
}

function fc_get_info_request_webhook_token() {
    $token = trim( (string) get_option( 'flacso_webhook_token', '' ) );
    if ( '' === $token ) {
        $token = trim( (string) get_option( 'fc_consultas_webhook_token', '' ) );
    }
    return sanitize_text_field( $token );
}

function fc_build_info_request_webhook_headers() {
    $headers = [ 'Content-Type' => 'application/json' ];
    $token   = fc_get_info_request_webhook_token();

    if ( '' !== $token ) {
        $headers['Authorization']          = 'Bearer ' . $token;
        $headers['X-FLACSO-Webhook-Token'] = $token;
    }

    return $headers;
}

function fc_dispatch_info_request_webhook( array $payload, array $extra_headers = [], $target_override = '' ) {
    $target = is_string( $target_override ) && '' !== trim( $target_override )
        ? esc_url_raw( $target_override )
        : fc_get_info_request_webhook_url();
    if ( '' === $target ) {
        return [
            'ok'      => false,
            'target'  => '',
            'code'    => 0,
            'body'    => '',
            'error'   => 'No hay endpoint configurado para solicitud de informacion.',
            'message' => '',
        ];
    }

    $headers = array_merge( fc_build_info_request_webhook_headers(), $extra_headers );
    $args    = [
        'body'        => wp_json_encode( $payload ),
        'headers'     => $headers,
        'timeout'     => defined( 'FLACSO_WEBHOOK_TIMEOUT' ) ? (int) FLACSO_WEBHOOK_TIMEOUT : 25,
        'redirection' => 3,
        'blocking'    => true,
        'httpversion' => '1.1',
        'data_format' => 'body',
    ];

    $response = wp_remote_post( $target, $args );
    if ( is_wp_error( $response ) ) {
        return [
            'ok'      => false,
            'target'  => $target,
            'code'    => 0,
            'body'    => '',
            'error'   => $response->get_error_message(),
            'message' => '',
        ];
    }

    $code    = (int) wp_remote_retrieve_response_code( $response );
    $body    = (string) wp_remote_retrieve_body( $response );
    $message = '';
    $decoded = json_decode( $body, true );

    if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
        $message = sanitize_text_field( (string) $decoded['message'] );
    } elseif ( is_array( $decoded ) && isset( $decoded['data']['message'] ) ) {
        $message = sanitize_text_field( (string) $decoded['data']['message'] );
    } elseif ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
        $message = sanitize_text_field( (string) $decoded['error']['message'] );
    }

    $ok = $code >= 200 && $code < 300;
    if ( is_array( $decoded ) && isset( $decoded['ok'] ) && false === $decoded['ok'] ) {
        $ok = false;
    }
    if ( is_array( $decoded ) && isset( $decoded['success'] ) && false === $decoded['success'] ) {
        $ok = false;
    }

    return [
        'ok'      => $ok,
        'target'  => $target,
        'code'    => $code,
        'body'    => $body,
        'error'   => $ok ? '' : 'HTTP ' . $code,
        'message' => $message,
    ];
}

function fc_get_montevideo_timezone(): DateTimeZone {
    try {
        return new DateTimeZone( 'America/Montevideo' );
    } catch ( Exception $e ) {
        return new DateTimeZone( '-03:00' );
    }
}

function fc_normalize_program_start_for_webhook( string $value, string $precision = '' ): array {
    $value     = trim( $value );
    $precision = strtolower( trim( $precision ) );

    if ( $value === '' ) {
        return [];
    }

    if ( $precision === '' ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) || preg_match( '/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value ) ) {
            $precision = 'day';
        } elseif ( preg_match( '/^\d{4}-\d{2}$/', $value ) ) {
            $precision = 'month';
        } elseif ( preg_match( '/^\d{4}$/', $value ) ) {
            $precision = 'year';
        }
    }

    $timezone = fc_get_montevideo_timezone();
    $result   = [
        'proximo_inicio'            => $value,
        'proximo_inicio_precision'  => $precision,
        'proximo_inicio_timezone'   => 'America/Montevideo',
        'proximo_inicio_utc_offset' => '-03:00',
    ];

    if ( $precision !== 'day' ) {
        return $result;
    }

    $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
    if ( ! $date ) {
        $date = DateTimeImmutable::createFromFormat( '!d/m/Y', $value, $timezone );
    }
    if ( ! $date ) {
        $date = DateTimeImmutable::createFromFormat( '!j/m/Y', $value, $timezone );
    }

    if ( $date instanceof DateTimeImmutable ) {
        $date = $date->setTime( 0, 0, 0 );
        $result['proximo_inicio_fecha'] = $date->format( 'Y-m-d' );
        $result['proximo_inicio_at']    = $date->format( 'Y-m-d\TH:i:sP' );
        $result['program_start_date']   = $result['proximo_inicio_fecha'];
        $result['program_start_at']     = $result['proximo_inicio_at'];
    }

    return $result;
}

/**
 * Completa el contexto del programa usando el ID del CPT como fuente de verdad.
 *
 * @param array $data Datos del formulario.
 * @return array
 */
function fc_enrich_info_request_program_context( array $data ) {
    $offer_id = 0;
    if ( isset( $data['id_pagina'] ) ) {
        $offer_id = absint( $data['id_pagina'] );
    } elseif ( isset( $data['programa_id'] ) ) {
        $offer_id = absint( $data['programa_id'] );
    }

    $resolved_title = '';
    $resolved_url   = '';

    if ( $offer_id > 0 ) {
        $resolved_title = (string) get_the_title( $offer_id );
        $resolved_url   = (string) get_permalink( $offer_id );

        if ( 'oferta-academica' === get_post_type( $offer_id ) ) {
            $start_value     = (string) get_post_meta( $offer_id, 'proximo_inicio', true );
            $start_precision = (string) get_post_meta( $offer_id, 'proximo_inicio_precision', true );
            if ( $start_value !== '' ) {
                $data = array_merge(
                    $data,
                    fc_normalize_program_start_for_webhook( $start_value, $start_precision )
                );
            }
        }
    }

    if ( '' === $resolved_title && isset( $data['titulo_posgrado'] ) ) {
        $resolved_title = sanitize_text_field( (string) $data['titulo_posgrado'] );
    }
    if ( '' === $resolved_title && isset( $data['programa_titulo'] ) ) {
        $resolved_title = sanitize_text_field( (string) $data['programa_titulo'] );
    }

    if ( '' === $resolved_url && isset( $data['url_base'] ) ) {
        $resolved_url = esc_url_raw( (string) $data['url_base'] );
    }

    $data['id_pagina']       = $offer_id;
    $data['programa_id']     = $offer_id;
    $data['titulo_posgrado'] = $resolved_title;
    $data['programa_titulo'] = $resolved_title;
    $data['url_base']        = $resolved_url;

    return $data;
}

function fc_resolve_info_request_offer_id( array $data ) {
    $candidate_ids = [];

    foreach ( [ 'offer_id', 'id_pagina', 'programa_id', 'post_id', 'oferta_id' ] as $field ) {
        if ( ! isset( $data[ $field ] ) ) {
            continue;
        }

        $candidate = absint( $data[ $field ] );
        if ( $candidate > 0 ) {
            $candidate_ids[] = $candidate;
        }
    }

    $candidate_ids = array_values( array_unique( $candidate_ids ) );

    foreach ( $candidate_ids as $candidate_id ) {
        if ( 'oferta-academica' === get_post_type( $candidate_id ) ) {
            return $candidate_id;
        }

        $related = get_posts(
            [
                'post_type'      => 'oferta-academica',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_oferta_page_id',
                        'value' => $candidate_id,
                    ],
                ],
            ]
        );

        if ( ! empty( $related ) ) {
            return (int) $related[0];
        }
    }

    return 0;
}

function fc_info_request_sanitize_text_value( array $data, string $key ): string {
    if ( ! isset( $data[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( (string) $data[ $key ] );
}

function fc_get_current_request_url(): string {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
    $request_uri = '/' . ltrim( $request_uri, '/' );

    return esc_url_raw( home_url( $request_uri ) );
}

function fc_get_url_query_value( string $url, string $key ): string {
    if ( '' === $url ) {
        return '';
    }

    $parts = wp_parse_url( $url );
    if ( empty( $parts['query'] ) ) {
        return '';
    }

    parse_str( $parts['query'], $query_args );
    if ( ! isset( $query_args[ $key ] ) || is_array( $query_args[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( (string) $query_args[ $key ] );
}

function fc_get_campaign_attribution_field_names(): array {
    return [
        'campaign_provider',
        'campaign_source',
        'campaign_medium',
        'campaign_name',
        'campaign_external_id',
        'campaign_content',
        'campaign_term',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'utm_content',
        'utm_term',
        'landing_url',
        'referrer_url',
    ];
}

function fc_pick_campaign_value( array $sources, array $keys ): string {
    foreach ( $sources as $source ) {
        if ( ! is_array( $source ) ) {
            continue;
        }

        foreach ( $keys as $key ) {
            if ( isset( $source[ $key ] ) && ! is_array( $source[ $key ] ) ) {
                $value = sanitize_text_field( (string) $source[ $key ] );
                if ( '' !== $value ) {
                    return $value;
                }
            }
        }
    }

    return '';
}

function fc_detect_campaign_provider( array $attribution, string $source = '' ): string {
    if ( ! empty( $attribution['provider'] ) ) {
        return sanitize_text_field( (string) $attribution['provider'] );
    }

    $haystack = strtolower( implode( ' ', array_filter( [ $source, $attribution['source'] ?? '', $attribution['external_id'] ?? '' ] ) ) );
    if ( false !== strpos( $haystack, 'meta' ) || false !== strpos( $haystack, 'facebook' ) || false !== strpos( $haystack, 'instagram' ) ) {
        return 'meta';
    }
    if ( false !== strpos( $haystack, 'mailjet' ) || false !== strpos( $haystack, 'mj-' ) ) {
        return 'mailjet';
    }
    if ( ! empty( $attribution['source'] ) || ! empty( $attribution['medium'] ) || ! empty( $attribution['name'] ) ) {
        return 'utm';
    }

    return '';
}

function fc_get_campaign_attribution_from_request( string $landing_url = '', string $referer_url = '' ): array {
    $request = [];
    foreach ( $_GET as $key => $value ) {
        if ( is_array( $value ) ) {
            continue;
        }
        $request[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
    }

    $candidate_url = $landing_url ?: fc_get_current_request_url();
    $url_query = [];
    foreach ( [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'utm_content',
        'utm_term',
        'campaign_provider',
        'campaign_source',
        'campaign_medium',
        'campaign_name',
        'campaign_external_id',
        'campaign_content',
        'campaign_term',
    ] as $key ) {
        $url_query[ $key ] = fc_get_url_query_value( $candidate_url, $key );
    }

    $referer_query = [];
    foreach ( array_keys( $url_query ) as $key ) {
        $referer_query[ $key ] = fc_get_url_query_value( $referer_url, $key );
    }

    $sources = [ $request, $url_query, $referer_query ];
    $attribution = [
        'provider'    => fc_pick_campaign_value( $sources, [ 'campaign_provider', 'provider', 'attribution_provider' ] ),
        'source'      => fc_pick_campaign_value( $sources, [ 'campaign_source', 'acquisition_source', 'fuente', 'utm_source' ] ),
        'medium'      => fc_pick_campaign_value( $sources, [ 'campaign_medium', 'medium', 'utm_medium' ] ),
        'name'        => fc_pick_campaign_value( $sources, [ 'campaign_name', 'campana', 'utm_campaign', 'campaign' ] ),
        'external_id' => fc_pick_campaign_value( $sources, [ 'campaign_external_id', 'campaign_id', 'campana_id', 'utm_id', 'meta_campaign_id', 'mailjet_campaign_id', 'mj_campaign_id' ] ),
        'content'     => fc_pick_campaign_value( $sources, [ 'campaign_content', 'content', 'utm_content', 'meta_ad_name' ] ),
        'term'        => fc_pick_campaign_value( $sources, [ 'campaign_term', 'term', 'utm_term', 'meta_adset_name' ] ),
    ];

    $attribution['provider'] = fc_detect_campaign_provider( $attribution );

    return [
        'campaign_provider'    => $attribution['provider'],
        'campaign_source'      => $attribution['source'],
        'campaign_medium'      => $attribution['medium'],
        'campaign_name'        => $attribution['name'],
        'campaign_external_id' => $attribution['external_id'],
        'campaign_content'     => $attribution['content'],
        'campaign_term'        => $attribution['term'],
        'utm_source'           => fc_pick_campaign_value( $sources, [ 'utm_source' ] ),
        'utm_medium'           => fc_pick_campaign_value( $sources, [ 'utm_medium' ] ),
        'utm_campaign'         => fc_pick_campaign_value( $sources, [ 'utm_campaign' ] ),
        'utm_id'               => fc_pick_campaign_value( $sources, [ 'utm_id' ] ),
        'utm_content'          => fc_pick_campaign_value( $sources, [ 'utm_content' ] ),
        'utm_term'             => fc_pick_campaign_value( $sources, [ 'utm_term' ] ),
        'landing_url'          => esc_url_raw( $candidate_url ),
        'referrer_url'         => esc_url_raw( $referer_url ?: ( wp_get_referer() ?: '' ) ),
    ];
}

function fc_render_campaign_attribution_hidden_fields( string $landing_url = '', string $referer_url = '' ): void {
    $fields = fc_get_campaign_attribution_from_request( $landing_url, $referer_url );

    foreach ( $fields as $name => $value ) {
        printf(
            '<input type="hidden" name="%1$s" value="%2$s">' . "\n",
            esc_attr( $name ),
            esc_attr( $value )
        );
    }
    ?>
    <script>
    (function() {
        var script = document.currentScript;
        var form = script && script.closest ? script.closest('form') : null;
        if (!form || !window.URL || !window.location) return;

        var currentUrl = window.location.href;
        var referrerUrl = document.referrer || '';
        var current;
        var referrer;

        try { current = new URL(currentUrl); } catch (e) { current = null; }
        try { referrer = referrerUrl ? new URL(referrerUrl) : null; } catch (e) { referrer = null; }

        function param(name) {
            var value = current ? (current.searchParams.get(name) || '') : '';
            if (!value && referrer) {
                value = referrer.searchParams.get(name) || '';
            }
            return value.trim();
        }

        function setField(name, value) {
            var field = form.querySelector('[name="' + name + '"]');
            if (field) field.value = value || '';
        }

        function first() {
            for (var i = 0; i < arguments.length; i++) {
                var value = (arguments[i] || '').trim();
                if (value) return value;
            }
            return '';
        }

        setField('landing_url', currentUrl);
        setField('referrer_url', referrerUrl);
        setField('utm_source', param('utm_source'));
        setField('utm_medium', param('utm_medium'));
        setField('utm_campaign', param('utm_campaign'));
        setField('utm_id', param('utm_id'));
        setField('utm_content', param('utm_content'));
        setField('utm_term', param('utm_term'));
        setField('campaign_source', first(param('campaign_source'), param('acquisition_source'), param('fuente'), param('utm_source')));
        setField('campaign_medium', first(param('campaign_medium'), param('medium'), param('utm_medium')));
        setField('campaign_name', first(param('campaign_name'), param('campana'), param('campaign'), param('utm_campaign')));
        setField('campaign_external_id', first(param('campaign_external_id'), param('campaign_id'), param('campana_id'), param('utm_id'), param('meta_campaign_id'), param('mailjet_campaign_id'), param('mj_campaign_id')));
        setField('campaign_content', first(param('campaign_content'), param('content'), param('utm_content'), param('meta_ad_name')));
        setField('campaign_term', first(param('campaign_term'), param('term'), param('utm_term'), param('meta_adset_name')));

        var provider = first(param('campaign_provider'), param('provider'), param('attribution_provider'));
        var providerSource = [
            provider,
            param('campaign_source'),
            param('utm_source'),
            param('campaign_external_id'),
            param('utm_id')
        ].join(' ').toLowerCase();
        if (!provider && (providerSource.indexOf('meta') !== -1 || providerSource.indexOf('facebook') !== -1 || providerSource.indexOf('instagram') !== -1)) provider = 'meta';
        if (!provider && (providerSource.indexOf('mailjet') !== -1 || providerSource.indexOf('mj-') !== -1 || providerSource.indexOf('mj.nl') !== -1)) provider = 'mailjet';
        if (!provider && (param('utm_source') || param('utm_medium') || param('utm_campaign'))) provider = 'utm';
        setField('campaign_provider', provider);
    })();
    </script>
    <?php
}

function fc_sanitize_campaign_attribution_payload( array $source, string $landing_url = '', string $referer_url = '' ): array {
    $fallback = fc_get_campaign_attribution_from_request( $landing_url, $referer_url );
    $fields   = [];

    foreach ( fc_get_campaign_attribution_field_names() as $name ) {
        $raw_value = $source[ $name ] ?? ( $fallback[ $name ] ?? '' );

        if ( is_array( $raw_value ) ) {
            $raw_value = '';
        }

        if ( in_array( $name, [ 'landing_url', 'referrer_url' ], true ) ) {
            $fields[ $name ] = esc_url_raw( (string) $raw_value );
        } else {
            $fields[ $name ] = sanitize_text_field( (string) $raw_value );
        }
    }

    $attribution = [
        'provider'    => $fields['campaign_provider'],
        'source'      => $fields['campaign_source'] ?: $fields['utm_source'],
        'medium'      => $fields['campaign_medium'] ?: $fields['utm_medium'],
        'name'        => $fields['campaign_name'] ?: $fields['utm_campaign'],
        'external_id' => $fields['campaign_external_id'] ?: $fields['utm_id'],
        'content'     => $fields['campaign_content'] ?: $fields['utm_content'],
        'term'        => $fields['campaign_term'] ?: $fields['utm_term'],
    ];

    $fields['campaign_provider'] = fc_detect_campaign_provider( $attribution );

    if ( '' === $fields['campaign_source'] ) {
        $fields['campaign_source'] = $fields['utm_source'];
    }
    if ( '' === $fields['campaign_medium'] ) {
        $fields['campaign_medium'] = $fields['utm_medium'];
    }
    if ( '' === $fields['campaign_name'] ) {
        $fields['campaign_name'] = $fields['utm_campaign'];
    }
    if ( '' === $fields['campaign_external_id'] ) {
        $fields['campaign_external_id'] = $fields['utm_id'];
    }
    if ( '' === $fields['campaign_content'] ) {
        $fields['campaign_content'] = $fields['utm_content'];
    }
    if ( '' === $fields['campaign_term'] ) {
        $fields['campaign_term'] = $fields['utm_term'];
    }

    return $fields;
}

/**
 * Normaliza el payload de Solicitud de Informacion para la API del panel.
 * Mantiene tambien los campos legacy para compatibilidad.
 *
 * @param array $data Datos sanitizados desde el formulario.
 * @return array
 */
function fc_build_info_request_webhook_payload( array $data ) {
    $data     = fc_enrich_info_request_program_context( $data );
    $offer_id = fc_resolve_info_request_offer_id( $data );
    if ( $offer_id <= 0 ) {
        $offer_id = isset( $data['id_pagina'] ) ? absint( $data['id_pagina'] ) : 0;
    }
    $offer_id = $offer_id > 0 ? (string) $offer_id : '';

    $inquiry_at = isset( $data['fecha_envio'] ) ? sanitize_text_field( (string) $data['fecha_envio'] ) : '';
    if ( '' === $inquiry_at ) {
        $inquiry_at = current_time( 'c' );
    }

    $source = isset( $data['source'] ) ? sanitize_text_field( (string) $data['source'] ) : '';
    if ( '' === $source && isset( $data['origen'] ) ) {
        $source = sanitize_text_field( (string) $data['origen'] );
    }
    if ( '' === $source ) {
        $source = 'Web';
    }

    $campaign_provider    = fc_info_request_sanitize_text_value( $data, 'campaign_provider' );
    $campaign_source      = fc_info_request_sanitize_text_value( $data, 'campaign_source' );
    $campaign_medium      = fc_info_request_sanitize_text_value( $data, 'campaign_medium' );
    $campaign_name        = fc_info_request_sanitize_text_value( $data, 'campaign_name' );
    $campaign_external_id = fc_info_request_sanitize_text_value( $data, 'campaign_external_id' );
    $campaign_content     = fc_info_request_sanitize_text_value( $data, 'campaign_content' );
    $campaign_term        = fc_info_request_sanitize_text_value( $data, 'campaign_term' );

    if ( '' === $campaign_source ) {
        $campaign_source = fc_info_request_sanitize_text_value( $data, 'utm_source' );
    }
    if ( '' === $campaign_medium ) {
        $campaign_medium = fc_info_request_sanitize_text_value( $data, 'utm_medium' );
    }
    if ( '' === $campaign_name ) {
        $campaign_name = fc_info_request_sanitize_text_value( $data, 'utm_campaign' );
    }
    if ( '' === $campaign_external_id ) {
        $campaign_external_id = fc_info_request_sanitize_text_value( $data, 'utm_id' );
    }
    if ( '' === $campaign_content ) {
        $campaign_content = fc_info_request_sanitize_text_value( $data, 'utm_content' );
    }
    if ( '' === $campaign_term ) {
        $campaign_term = fc_info_request_sanitize_text_value( $data, 'utm_term' );
    }

    $attribution = [
        'provider'    => $campaign_provider,
        'source'      => $campaign_source,
        'medium'      => $campaign_medium,
        'name'        => $campaign_name,
        'external_id' => $campaign_external_id,
        'content'     => $campaign_content,
        'term'        => $campaign_term,
    ];
    $campaign_provider = fc_detect_campaign_provider( $attribution, $source );
    $attribution['provider'] = $campaign_provider;

    return [
        // Campos canónicos (PanelFLACSOConsultas /api/consultas)
        'email'           => isset( $data['correo'] ) ? sanitize_email( $data['correo'] ) : '',
        'first_name'      => isset( $data['nombre'] ) ? sanitize_text_field( $data['nombre'] ) : '',
        'last_name'       => isset( $data['apellido'] ) ? sanitize_text_field( $data['apellido'] ) : '',
        'country'         => isset( $data['pais'] ) ? sanitize_text_field( $data['pais'] ) : '',
        'profession'      => isset( $data['profesion'] ) ? sanitize_text_field( $data['profesion'] ) : '',
        'education_level' => isset( $data['nivel_academico'] ) ? sanitize_text_field( $data['nivel_academico'] ) : '',
        'offer_id'        => $offer_id,
        'source'          => $source,
        'campaign_provider' => $campaign_provider,
        'campaign_source'   => $campaign_source,
        'campaign_medium'   => $campaign_medium,
        'campaign_name'     => $campaign_name,
        'campaign_external_id' => $campaign_external_id,
        'campaign_content'  => $campaign_content,
        'campaign_term'     => $campaign_term,
        'utm_source'        => fc_info_request_sanitize_text_value( $data, 'utm_source' ),
        'utm_medium'        => fc_info_request_sanitize_text_value( $data, 'utm_medium' ),
        'utm_campaign'      => fc_info_request_sanitize_text_value( $data, 'utm_campaign' ),
        'utm_id'            => fc_info_request_sanitize_text_value( $data, 'utm_id' ),
        'utm_content'       => fc_info_request_sanitize_text_value( $data, 'utm_content' ),
        'utm_term'          => fc_info_request_sanitize_text_value( $data, 'utm_term' ),
        'landing_url'       => isset( $data['landing_url'] ) ? esc_url_raw( $data['landing_url'] ) : '',
        'referrer_url'      => isset( $data['referrer_url'] ) ? esc_url_raw( $data['referrer_url'] ) : '',
        'attribution'       => array_filter( $attribution ),
        'url_referer'     => isset( $data['url_referer'] ) ? esc_url_raw( $data['url_referer'] ) : '',
        'inquiry_at'      => $inquiry_at,
        'ip_address'      => isset( $data['ip_usuario'] ) ? sanitize_text_field( $data['ip_usuario'] ) : '',
        'user_agent'      => isset( $data['user_agent'] ) ? sanitize_text_field( $data['user_agent'] ) : '',
        'program_start_date' => isset( $data['program_start_date'] ) ? sanitize_text_field( $data['program_start_date'] ) : '',
        'program_start_at'   => isset( $data['program_start_at'] ) ? sanitize_text_field( $data['program_start_at'] ) : '',
        'program_start_timezone' => isset( $data['proximo_inicio_timezone'] ) ? sanitize_text_field( $data['proximo_inicio_timezone'] ) : '',
        'program_start_utc_offset' => isset( $data['proximo_inicio_utc_offset'] ) ? sanitize_text_field( $data['proximo_inicio_utc_offset'] ) : '',
        'fecha_inicio'     => isset( $data['proximo_inicio_fecha'] ) ? sanitize_text_field( $data['proximo_inicio_fecha'] ) : '',
        'fecha_inicio_at'  => isset( $data['proximo_inicio_at'] ) ? sanitize_text_field( $data['proximo_inicio_at'] ) : '',
        'fecha_inicio_timezone' => isset( $data['proximo_inicio_timezone'] ) ? sanitize_text_field( $data['proximo_inicio_timezone'] ) : '',
        'fecha_inicio_utc_offset' => isset( $data['proximo_inicio_utc_offset'] ) ? sanitize_text_field( $data['proximo_inicio_utc_offset'] ) : '',

        // Alias útiles para compatibilidad con el payload legacy
        'nombre'          => isset( $data['nombre'] ) ? sanitize_text_field( $data['nombre'] ) : '',
        'apellido'        => isset( $data['apellido'] ) ? sanitize_text_field( $data['apellido'] ) : '',
        'correo'          => isset( $data['correo'] ) ? sanitize_email( $data['correo'] ) : '',
        'pais'            => isset( $data['pais'] ) ? sanitize_text_field( $data['pais'] ) : '',
        'profesion'       => isset( $data['profesion'] ) ? sanitize_text_field( $data['profesion'] ) : '',
        'nivel_academico' => isset( $data['nivel_academico'] ) ? sanitize_text_field( $data['nivel_academico'] ) : '',
        'nivel_educativo' => isset( $data['nivel_academico'] ) ? sanitize_text_field( $data['nivel_academico'] ) : '',
        'id_pagina'       => $offer_id,
        'post_id'         => $offer_id,
        'origen'          => $source,
        'fecha_envio'     => $inquiry_at,
        'ip_usuario'      => isset( $data['ip_usuario'] ) ? sanitize_text_field( $data['ip_usuario'] ) : '',
        'proximo_inicio'  => isset( $data['proximo_inicio'] ) ? sanitize_text_field( $data['proximo_inicio'] ) : '',
        'proximo_inicio_precision' => isset( $data['proximo_inicio_precision'] ) ? sanitize_text_field( $data['proximo_inicio_precision'] ) : '',
        'proximo_inicio_fecha' => isset( $data['proximo_inicio_fecha'] ) ? sanitize_text_field( $data['proximo_inicio_fecha'] ) : '',
        'proximo_inicio_at' => isset( $data['proximo_inicio_at'] ) ? sanitize_text_field( $data['proximo_inicio_at'] ) : '',
        'proximo_inicio_timezone' => isset( $data['proximo_inicio_timezone'] ) ? sanitize_text_field( $data['proximo_inicio_timezone'] ) : '',
        'proximo_inicio_utc_offset' => isset( $data['proximo_inicio_utc_offset'] ) ? sanitize_text_field( $data['proximo_inicio_utc_offset'] ) : '',
    ];
}

/**
 * Envia la solicitud de informacion al endpoint externo.
 *
 * @param array $data Datos sanitizados del formulario.
 * @return array { ok, target, code, body, error }
 */
function fc_send_info_request_webhook( array $data ) {
    $payload = fc_build_info_request_webhook_payload( $data );
    return fc_dispatch_info_request_webhook( $payload );
}

function fc_send_info_request_webhook_test() {
    return fc_dispatch_info_request_webhook(
        [
            'test'         => true,
            'source'       => 'wordpress_admin',
            'requested_at' => current_time( 'c' ),
        ],
        [ 'X-FLACSO-Webhook-Test' => '1' ]
    );
}

/**
 * Calcula la URL /confirmacion-consulta/ basada en la página que contiene el formulario.
 */
function fc_get_gracias_url_from_referer() {
    $referer = wp_get_referer();
    if ( ! $referer ) {
        return home_url( '/confirmacion-consulta/' );
    }

    $parts = wp_parse_url( $referer );
    if ( empty( $parts['host'] ) ) {
        return home_url( '/confirmacion-consulta/' );
    }

    $scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//';
    $host   = $parts['host'];
    $path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';

    // Si el path es raíz, usar /confirmacion-consulta/, si no, añadir segmento.
    if ( '' === $path || '/' === $path ) {
        $confirmacion_path = '/confirmacion-consulta/';
    } else {
        $confirmacion_path = trailingslashit( $path ) . 'confirmacion-consulta/';
    }

    return $scheme . $host . $confirmacion_path;
}

/**
 * Registra una consulta en la base de datos (CPT) y retorna los identificadores generados.
 *
 * @param array $payload Datos de la consulta (campos clave).
 * @return array {
 *     @type int    $post_id        ID del post insertado (0 on failure).
 *     @type string $control_number Número de control asignado (vacío si no se pudo guardar).
 *     @type string $error          Mensaje de error cuando falla la inserción.
 * }
 */
function fc_record_consulta_entry( array $payload ) {
    if ( ! post_type_exists( 'fc_consulta' ) && function_exists( 'fc_register_cpt' ) ) {
        fc_register_cpt();
    }

    $defaults = [
        'nombre'            => '',
        'apellido'          => '',
        'email'             => '',
        'telefono'          => '',
        'asunto'            => '',
        'mensaje'           => '',
        'pais'              => '',
        'nivel_academico'   => '',
        'profesion'         => '',
        'url_base'          => '',
        'url_referer'       => '',
        'page_id'           => 0,
        'titulo_posgrado'   => '',
        'ip'                => '',
        'user_agent'        => '',
        'fecha_envio'       => '',
    ];

    $data = wp_parse_args( $payload, $defaults );
    $nombre   = sanitize_text_field( $data['nombre'] );
    $apellido = sanitize_text_field( $data['apellido'] );
    $email    = sanitize_email( $data['email'] );
    $telefono = sanitize_text_field( $data['telefono'] );
    $asunto   = sanitize_text_field( $data['asunto'] );
    $mensaje  = isset( $data['mensaje'] ) ? wp_kses_post( $data['mensaje'] ) : '';
    $pais     = sanitize_text_field( $data['pais'] );
    $nivel    = sanitize_text_field( $data['nivel_academico'] );
    $profesion = sanitize_text_field( $data['profesion'] );
    $url_base = esc_url_raw( $data['url_base'] );
    $url_referer = esc_url_raw( $data['url_referer'] );
    $page_id = absint( $data['page_id'] );
    $titulo_posgrado = sanitize_text_field( $data['titulo_posgrado'] );
    $user_agent = sanitize_text_field( $data['user_agent'] );
    $ip_address = sanitize_text_field( $data['ip'] );

    $post_date = current_time( 'mysql' );
    $ts_local  = current_time( 'timestamp' );
    if ( ! empty( $data['fecha_envio'] ) ) {
        $parsed = strtotime( $data['fecha_envio'] );
        if ( $parsed !== false ) {
            // Mantener la fecha original para el post_date y meta legibles
            $post_date = gmdate( 'Y-m-d H:i:s', $parsed );
            $ts_local  = $parsed;
        }
    }

    $title = $asunto ? $asunto : sprintf(
        __( 'Consulta de %1$s %2$s', 'flacso-flacso-formulario-consultas' ),
        $nombre,
        $apellido
    );

    $post_args = [
        'post_type'    => 'fc_consulta',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $mensaje,
        'post_author'  => 0,
        'post_date'    => $post_date,
    ];

    $post_id = wp_insert_post( $post_args, true );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        $message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned falsy value';
        return [
            'post_id' => 0,
            'control_number' => '',
            'error' => $message,
        ];
    }

    update_post_meta( $post_id, 'fc_nombre', $nombre );
    update_post_meta( $post_id, 'fc_apellido', $apellido );
    update_post_meta( $post_id, 'fc_email', $email );
    update_post_meta( $post_id, 'fc_telefono', $telefono );
    update_post_meta( $post_id, 'fc_asunto', $asunto );
    update_post_meta( $post_id, 'fc_mensaje', $mensaje );
    update_post_meta( $post_id, 'fc_pais', $pais );
    update_post_meta( $post_id, 'fc_nivel_academico', $nivel );
    update_post_meta( $post_id, 'fc_profesion', $profesion );
    update_post_meta( $post_id, 'fc_url_base', $url_base );
    update_post_meta( $post_id, 'fc_url_referer', $url_referer );
    update_post_meta( $post_id, 'fc_programa_id', $page_id );
    update_post_meta( $post_id, 'fc_programa_titulo', $titulo_posgrado );
    update_post_meta( $post_id, 'fc_ip', $ip_address );
    update_post_meta( $post_id, 'fc_user_agent', $user_agent );
    $ua_info = fc_parse_user_agent_simple( $user_agent );
    update_post_meta( $post_id, 'fc_navegador', $ua_info['browser'] );
    update_post_meta( $post_id, 'fc_sistema_operativo', $ua_info['os'] );

    // Guardar fecha y hora legibles (locale de WP)
    $fecha_legible = date_i18n( 'l, d \\d\\e F \\d\\e Y', $ts_local );
    $hora_legible  = date_i18n( 'g:i a', $ts_local );
    update_post_meta( $post_id, 'fc_fecha', $fecha_legible );
    update_post_meta( $post_id, 'fc_hora', $hora_legible );

    $last_control = (int) get_option( 'fc_last_control_number', 0 );
    $next_control = $last_control + 1;
    update_option( 'fc_last_control_number', $next_control );
    $control_number = sprintf( 'FC-%06d', $next_control );
    update_post_meta( $post_id, 'fc_control_number', $control_number );

    update_post_meta( $post_id, 'fc_fecha_envio', $post_date );

    return [
        'post_id' => $post_id,
        'control_number' => $control_number,
        'error' => '',
    ];
}

/**
 * Registra una solicitud de información (oferta académica) en CPT dedicado.
 *
 * @param array $payload Datos del formulario de oferta.
 * @return array { post_id, control_number, error }
 */
function fc_record_info_request_entry( array $payload ) {
    // CPT fc_info_request is decommissioned and deleted.
    // We return a simulated successful response to preserve 100% backward compatibility
    // with calling modules (e.g. flacso-consultas.php and class-flacso-posgrados-consultas-form.php)
    // without executing database write operations.
    return [
        'post_id'        => 1,
        'control_number' => 'FI-DECOMMISSIONED',
        'error'          => '',
    ];
}

/**
 * Agrega la query var 'fc_confirmacion_consulta' para el sistema de rewrite
 */
function fc_add_confirmacion_consulta_query_var( $vars ) {
    $vars[] = 'fc_confirmacion_consulta';
    return $vars;
}
add_filter( 'query_vars', 'fc_add_confirmacion_consulta_query_var' );

/**
 * REST API: importar solicitudes de información (lotes desde planillas).
 * Endpoint: POST /wp-json/flacso/v1/info-requests/import
 */
function fc_register_info_request_import_route() {
    register_rest_route(
        'flacso/v1',
        '/info-requests/import',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => 'fc_api_import_info_requests',
            'args'                => [
                'items' => [
                    'type'     => 'array',
                    'required' => true,
                ],
            ],
        ]
    );
}
// Decommissioned: POST /wp-json/flacso/v1/info-requests/import is no longer available since CPT is deleted
// add_action( 'rest_api_init', 'fc_register_info_request_import_route' );

function fc_api_import_info_requests( WP_REST_Request $request ) {
    $items = $request->get_param( 'items' );
    if ( ! is_array( $items ) ) {
        return new WP_REST_Response( [ 'error' => 'items must be an array' ], 400 );
    }

    $results = [];
    foreach ( $items as $index => $row ) {
        if ( ! is_array( $row ) ) {
            $results[] = [ 'index' => $index, 'status' => 'error', 'message' => 'row must be object/array' ];
            continue;
        }
        $stored = fc_record_info_request_entry(
            [
                'nombre'          => $row['nombre'] ?? '',
                'apellido'        => $row['apellido'] ?? '',
                'correo'          => $row['correo'] ?? '',
                'pais'            => $row['pais'] ?? '',
                'nivel_academico' => $row['nivel_academico'] ?? '',
                'profesion'       => $row['profesion'] ?? '',
                'programa_id'     => $row['programa_id'] ?? 0,
                'programa_titulo' => $row['programa_titulo'] ?? '',
                'url_base'        => $row['url_base'] ?? '',
                'url_referer'     => $row['url_referer'] ?? '',
                'fecha_envio'     => $row['fecha_envio'] ?? '',
                'ip'              => $row['ip'] ?? '',
                'user_agent'      => $row['user_agent'] ?? '',
            ]
        );
        $results[] = [
            'index'    => $index,
            'status'   => empty( $stored['error'] ) ? 'ok' : 'error',
            'post_id'  => $stored['post_id'],
            'control'  => $stored['control_number'],
            'message'  => $stored['error'],
        ];
    }

    return new WP_REST_Response(
        [
            'imported' => array_sum( array_map( fn( $r ) => $r['status'] === 'ok' ? 1 : 0, $results ) ),
            'results'  => $results,
        ],
        200
    );
}

/**
 * REST API: exportar todas las consultas (cpt fc_consulta) para la migración.
 * Endpoint: GET /wp-json/flacso/v1/consultas/export
 */
function fc_register_consultas_export_route() {
    register_rest_route(
        'flacso/v1',
        '/consultas/export',
        [
            'methods'             => 'GET',
            'permission_callback' => function ( WP_REST_Request $request ) {
                if ( current_user_can( 'manage_options' ) ) {
                    return true;
                }
                
                $expected_token = fc_get_info_request_webhook_token();
                $auth_header = $request->get_header( 'Authorization' );
                $provided_token = '';
                if ( ! empty( $auth_header ) && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
                    $provided_token = trim( $matches[1] );
                }
                if ( empty( $provided_token ) ) {
                    $provided_token = $request->get_header( 'X-FLACSO-Webhook-Token' );
                }
                
                return ! empty( $expected_token ) && $provided_token === $expected_token;
            },
            'callback'            => 'fc_api_export_consultas',
        ]
    );
}
add_action( 'rest_api_init', 'fc_register_consultas_export_route' );

function fc_api_export_consultas() {
    if ( function_exists( 'set_time_limit' ) ) {
        set_time_limit( 300 );
    }
    
    $query_args = [
        'post_type'      => 'fc_consulta',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'orderby'        => 'ID',
        'order'          => 'ASC'
    ];

    $query = new WP_Query( $query_args );
    $posts = $query->posts;

    $exported_data = [];

    foreach ( $posts as $post ) {
        $post_id = $post->ID;
        
        $control_number   = get_post_meta( $post_id, 'fc_control_number', true );
        $nombre           = get_post_meta( $post_id, 'fc_nombre', true );
        $apellido         = get_post_meta( $post_id, 'fc_apellido', true );
        $email            = get_post_meta( $post_id, 'fc_email', true );
        $telefono         = get_post_meta( $post_id, 'fc_telefono', true );
        $asunto           = get_post_meta( $post_id, 'fc_asunto', true );
        $mensaje          = get_post_meta( $post_id, 'fc_mensaje', true );
        if ( empty( $mensaje ) ) {
            $mensaje = $post->post_content;
        }
        
        $url_referer       = get_post_meta( $post_id, 'fc_url_referer', true );
        $ip                = get_post_meta( $post_id, 'fc_ip', true );
        $user_agent        = get_post_meta( $post_id, 'fc_user_agent', true );
        $navegador         = get_post_meta( $post_id, 'fc_navegador', true );
        $sistema_operativo = get_post_meta( $post_id, 'fc_sistema_operativo', true );
        $fecha_envio       = get_post_meta( $post_id, 'fc_fecha_envio', true );
        if ( empty( $fecha_envio ) ) {
            $fecha_envio = $post->post_date_gmt && $post->post_date_gmt !== '0000-00-00 00:00:00' ? $post->post_date_gmt : $post->post_date;
        }
        
        $exported_data[] = [
            'wordpress_id'      => $post_id,
            'control_number'    => $control_number,
            'nombre'            => $nombre ?: 'Sin nombre',
            'apellido'          => $apellido ?: 'Sin apellido',
            'email'             => $email ?: 'sin-email@flacso.edu.uy',
            'telefono'          => $telefono ?: null,
            'asunto'            => $asunto ?: 'Consulta sin asunto',
            'mensaje'           => $mensaje ?: '',
            'url_referer'       => $url_referer ?: null,
            'ip'                => $ip ?: null,
            'user_agent'        => $user_agent ?: null,
            'navegador'         => $navegador ?: null,
            'sistema_operativo' => $sistema_operativo ?: null,
            'created_at'        => date( 'c', strtotime( $fecha_envio ) )
        ];
    }

    return new WP_REST_Response( $exported_data, 200 );
}
