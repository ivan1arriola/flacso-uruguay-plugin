<?php
/**
 * Validación y detección de spam
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Detecta contenido spam en las consultas
 */
function fc_is_spam_content( $nombre, $apellido, $email, $asunto, $mensaje ) {
    // Lista de palabras/frases spam comunes
    $spam_keywords = [
        'guest post', 'guest posting', 'backlink', 'seo service', 'link building',
        'crypto', 'bitcoin', 'ethereum', 'usdt', 'nft', 'trading',
        'casino', 'poker', 'slot', 'betting',
        'viagra', 'cialis', 'pharmacy',
        'loan', 'insurance', 'mortgage',
        'pricing', 'what\'s your pricing', 'how much do you charge',
        'xin chào', 'hola amigo', 'dear webmaster',
        'increase traffic', 'rank higher', 'google ranking',
    ];

    // Dominios de correo sospechosos
    $spam_domains = [
        'reachout2me.pro',
        'gmail.com.', // con punto extra
        '.ru', '.xyz', '.top', '.club',
    ];

    $combined_text = strtolower( $nombre . ' ' . $apellido . ' ' . $asunto . ' ' . $mensaje );
    
    // Verificar palabras spam
    foreach ( $spam_keywords as $keyword ) {
        if ( stripos( $combined_text, $keyword ) !== false ) {
            return true;
        }
    }

    // Verificar dominios sospechosos
    foreach ( $spam_domains as $domain ) {
        if ( stripos( $email, $domain ) !== false ) {
            return true;
        }
    }

    // Detectar caracteres no latinos (cirílico, chino, etc.) excepto acentos españoles
    $has_non_latin = preg_match('/[^\x00-\x7F\xC0-\xFF]/u', $combined_text);
    if ( $has_non_latin ) {
        return true;
    }

    return false;
}

/**
 * Verifica el token de reCAPTCHA v3 con Google
 */
function fc_verify_recaptcha( $token ) {
    $secret_key = get_option( 'fc_recaptcha_secret_key', '' );
    if ( empty( $secret_key ) ) {
        return false;
    }

    $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => $secret_key,
            'response' => $token,
            'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
        ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    // Verificar éxito y score (mínimo 0.5 para v3)
    if ( isset( $body['success'] ) && $body['success'] === true ) {
        $score = isset( $body['score'] ) ? (float) $body['score'] : 0;
        // Score 0.0 = muy sospechoso, 1.0 = muy confiable
        // Umbral recomendado: 0.5
        return $score >= 0.5;
    }

    return false;
}
