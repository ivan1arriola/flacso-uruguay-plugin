<?php
/**
 * Template Name: FLACSO - Formulario de Preinscripción
 * Description: Template para páginas de preinscripción de posgrados FLACSO
 */

if (!defined('ABSPATH')) { exit; }

// Usar header del tema
get_header();

// Obtener la instancia del plugin
$plugin = FLACSO_Formulario_Preinscripcion_Final::get_instance();

?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
        // Renderizar el contenido del formulario
        if (method_exists($plugin, 'render_template_preinscripcion')) {
            $plugin->render_template_preinscripcion();
        } else {
            echo '<div class="error"><p>Error: No se pudo cargar el formulario de preinscripción.</p></div>';
        }
        ?>
    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php
// Usar footer del tema
get_footer();
