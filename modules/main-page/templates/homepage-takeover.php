<?php
/**
 * Template takeover para la portada FLACSO.
 * Renderiza el builder fuera del contenedor de contenido del tema.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) {
    the_post();
}
?>
<main id="main" class="site-main flacso-homepage-takeover-main" role="main">
    <?php
    if (function_exists('flacso_homepage_builder_render')) {
        echo flacso_homepage_builder_render();
    } else {
        the_content();
    }
    ?>
</main>
<?php
get_footer();

