<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h1 class="mb-3" style="color: var(--global-palette1);">Gracias por tu preinscripción</h1>
<div class="flacso-gracias-mensaje mb-3">
    <p class="lead mb-2">Recibimos tus datos correctamente.</p>
    <p class="mb-2">El equipo de FLACSO Uruguay revisará la información y se comunicará contigo por correo electrónico.</p>
    <p class="mb-2">Revisá también tu carpeta de spam o promociones.</p>
    <?php if ( ! empty( $titulo_programa ) ) : ?>
        <p class="mb-0 fw-semibold">Seminario consultado: <?php echo esc_html( $titulo_programa ); ?></p>
    <?php endif; ?>
</div>
