<?php
/**
 * Validación y detección de spam
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Detecta contenido spam en las consultas.
 *
 * @param string|null $reason Motivo del descarte ('palabra', 'dominio' o 'escritura_no_latina'); no lleva datos personales.
 */
function fc_is_spam_content( $nombre, $apellido, $email, $asunto, $mensaje, &$reason = null ) {
    // Lista de palabras/frases spam comunes
    $spam_keywords = [
        'guest post', 'guest posts', 'guest posting', 'backlink', 'backlinks', 'seo service', 'seo services', 'link building',
        'crypto', 'bitcoin', 'ethereum', 'usdt', 'nft', 'trading',
        'casino', 'casinos', 'poker', 'slot', 'slots', 'betting',
        'viagra', 'cialis', 'pharmacy',
        'loan', 'loans', 'insurance', 'mortgage',
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

    $combined_text = $nombre . ' ' . $apellido . ' ' . $asunto . ' ' . $mensaje;

    // Palabra completa, no subcadena: "Sloane" no es "loan" ni "hola amigos" es "hola amigo". Los plurales de spam van en la lista.
    $keywords_pattern = '/\b(?:' . implode( '|', array_map( function ( $keyword ) {
        return preg_quote( $keyword, '/' );
    }, $spam_keywords ) ) . ')\b/iu';
    if ( preg_match( $keywords_pattern, $combined_text ) ) {
        $reason = 'palabra';
        return true;
    }

    // Dominio sospechoso: solo como terminación del dominio del correo ("ana.ruiz@gmail.com" no es ".ru").
    $email  = (string) $email;
    $at     = strrpos( $email, '@' );
    $domain = strtolower( false === $at ? $email : substr( $email, $at + 1 ) );
    foreach ( $spam_domains as $suspicious ) {
        if ( '' !== $domain && substr( $domain, -strlen( $suspicious ) ) === $suspicious ) {
            $reason = 'dominio';
            return true;
        }
    }

    // Escrituras no latinas habituales en spam. Signos como ¿ ¡ € « » o las comillas tipográficas son texto normal.
    // shortcut: el griego y las tildes vietnamitas no se bloquean por escritura; el vietnamita queda cubierto por "xin chào".
    if ( preg_match( '/[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Hebrew}\p{Thai}\p{Devanagari}]/u', $combined_text ) ) {
        $reason = 'escritura_no_latina';
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
