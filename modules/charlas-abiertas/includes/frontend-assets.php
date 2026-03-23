<?php

if (!defined('ABSPATH')) {
    exit;
}

function flacso_charlas_abiertas_enqueue_front_assets() {
    wp_enqueue_style(
        'intl-tel-input',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@26.5.1/build/css/intlTelInput.css',
        [],
        '26.5.1'
    );

    wp_enqueue_style(
        'flacso-charlas-abiertas-frontend',
        FLACSO_CHARLAS_ABIERTAS_URL . 'assets/css/frontend.css',
        ['intl-tel-input'],
        FLACSO_CHARLAS_ABIERTAS_VERSION
    );

    wp_enqueue_script(
        'intl-tel-input',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@26.5.1/build/js/intlTelInput.min.js',
        [],
        '26.5.1',
        true
    );

    wp_enqueue_script(
        'flacso-charlas-abiertas-frontend',
        FLACSO_CHARLAS_ABIERTAS_URL . 'assets/js/frontend.js',
        ['intl-tel-input'],
        FLACSO_CHARLAS_ABIERTAS_VERSION,
        true
    );
}
