<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post;

$programa_id = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
if ($programa_id <= 0 && $post instanceof WP_Post) {
    $programa_id = (int) $post->ID;
}

$programa = $programa_id > 0 ? get_the_title($programa_id) : '';
$programa_url = $programa_id > 0 ? get_permalink($programa_id) : home_url('/formacion/');

status_header(200);
nocache_headers();
header('X-Robots-Tag: noindex, nofollow', true);

get_header();
?>
<main class="flacso-preinscripcion-gracias" aria-labelledby="flacso-preinscripcion-gracias-title">
    <div class="flacso-preinscripcion-gracias__shell">
        <section class="flacso-preinscripcion-gracias__card">
            <div class="flacso-preinscripcion-gracias__status" aria-hidden="true">
                <svg viewBox="0 0 64 64" role="img">
                    <circle cx="32" cy="32" r="29"></circle>
                    <path d="M19 33.5 28 42l18-20"></path>
                </svg>
            </div>

            <p class="flacso-preinscripcion-gracias__eyebrow">Preinscripción recibida</p>
            <h1 id="flacso-preinscripcion-gracias-title">¡Gracias por dar este paso!</h1>

            <p class="flacso-preinscripcion-gracias__intro">
                Recibimos correctamente tu postulación<?php if ($programa !== '') : ?>
                    para <strong><?php echo esc_html($programa); ?></strong><?php endif; ?>.
            </p>

            <div class="flacso-preinscripcion-gracias__next">
                <h2>¿Qué sucede ahora?</h2>
                <ol>
                    <li>
                        <span>1</span>
                        <div>
                            <strong>Revisaremos la información</strong>
                            <p>Nuestro equipo verificará los datos y la documentación enviada.</p>
                        </div>
                    </li>
                    <li>
                        <span>2</span>
                        <div>
                            <strong>Te escribiremos por correo</strong>
                            <p>Recibirás la confirmación y los próximos pasos en tu correo electrónico.</p>
                        </div>
                    </li>
                </ol>
            </div>

            <p class="flacso-preinscripcion-gracias__note">
                Revisá también las carpetas de spam y promociones. Si necesitás ayuda, escribinos a
                <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a>.
            </p>

            <div class="flacso-preinscripcion-gracias__actions">
                <a class="flacso-preinscripcion-gracias__button flacso-preinscripcion-gracias__button--primary"
                   href="<?php echo esc_url($programa_url); ?>">
                    Volver al programa
                </a>
                <a class="flacso-preinscripcion-gracias__button flacso-preinscripcion-gracias__button--secondary"
                   href="<?php echo esc_url(home_url('/formacion/')); ?>">
                    Ver la oferta académica
                </a>
            </div>
        </section>
    </div>
</main>
<?php
get_footer();
