<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h1 class="mb-3" style="color: var(--global-palette1);">¡Tu consulta fue enviada!</h1>
<div class="flacso-gracias-mensaje mb-3">
    <?php if ( $intro ) : ?>
        <p class="lead mb-2">
            <?php printf( 'Gracias por tu interés en %s de FLACSO Uruguay.', esc_html( $intro ) ); ?>
        </p>
    <?php else : ?>
        <p class="lead mb-2">Hemos recibido tu consulta correctamente.</p>
    <?php endif; ?>

    <p class="mb-2">En breve recibirás un correo con la respuesta a tu consulta.</p>
    <p class="mb-0">
        Si precisas enviar más detalles, podés escribirnos a
        <a href="mailto:<?php echo esc_attr( FLACSO_EMAIL_CONTACTO ); ?>" class="fw-semibold">
            <?php echo esc_html( FLACSO_EMAIL_CONTACTO ); ?>
        </a>.
    </p>
</div>
