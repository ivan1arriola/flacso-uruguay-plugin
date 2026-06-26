<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h1 class="mb-3" style="color: var(--global-palette1);">¡Gracias por solicitar información!</h1>
<div class="flacso-gracias-mensaje mb-3">
    <?php if ( $intro ) : ?>
        <p class="lead mb-2">
            <?php printf( 'Hemos recibido tu solicitud de información sobre %s.', esc_html( $intro ) ); ?>
        </p>
    <?php else : ?>
        <p class="lead mb-2">Hemos recibido tu solicitud de información correctamente.</p>
    <?php endif; ?>
    <p class="mb-2">A la brevedad te estaremos enviando el programa y los detalles de cursada al correo electrónico que ingresaste.</p>
    <p class="mb-0">
        Si tenés dudas, escribinos a
        <a href="mailto:<?php echo esc_attr( FLACSO_EMAIL_CONTACTO ); ?>" class="fw-semibold">
            <?php echo esc_html( FLACSO_EMAIL_CONTACTO ); ?>
        </a>.
    </p>
</div>
