<?php
/**
 * Template takeover para la categoría Novedades.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Asegurarse de que el renderizador esté disponible
if (!function_exists('flacso_section_novedades_render')) {
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/novedades-section.php';
}
?>

<main id="main" class="site-main flacso-novedades-archive" role="main">
    
    <!-- Premium Hero Section -->
    <header class="flacso-novedades-archive-hero">
        <div class="flacso-content-shell">
            <div class="flacso-novedades-archive-hero-content">
                <nav class="flacso-breadcrumbs" aria-label="Breadcrumb">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a></li>
                        <li aria-current="page">Novedades</li>
                    </ol>
                </nav>
                <h1 class="flacso-novedades-archive-title">Todas las Novedades</h1>
                <p class="flacso-novedades-archive-subtitle">
                    Entérate de las últimas noticias, comunicados y artículos de interés de FLACSO Uruguay.
                </p>
            </div>
        </div>
    </header>

    <!-- Novedades AJAX Grid (Reused from homepage) -->
    <div class="flacso-novedades-archive-content pt-5 pb-5">
        <?php 
            // Llamamos a la función de portada pero le pasamos `false` 
            // para ocultar el carrusel 3D de destacadas en esta vista
            echo flacso_section_novedades_render(false); 
        ?>
    </div>

</main>

<style>
/* Estilos para el Hero de Archivo */
.flacso-novedades-archive-hero {
    background: linear-gradient(135deg, var(--global-palette1, #1d3a72) 0%, #152b55 100%);
    color: #ffffff;
    padding: 6rem 0 4rem;
    position: relative;
    overflow: hidden;
}

.flacso-novedades-archive-hero::after {
    content: '';
    position: absolute;
    bottom: -20px;
    right: -5%;
    width: 40%;
    height: 120%;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.flacso-novedades-archive-hero-content {
    max-width: 800px;
    position: relative;
    z-index: 2;
}

.flacso-novedades-archive-title {
    color: #ffffff;
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    margin: 1rem 0;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.flacso-novedades-archive-subtitle {
    font-size: clamp(1.1rem, 2vw, 1.25rem);
    color: rgba(255,255,255,0.85);
    margin: 0;
    line-height: 1.6;
}

/* Breadcrumbs */
.flacso-breadcrumbs ol {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    opacity: 0.8;
}

.flacso-breadcrumbs a {
    color: #ffffff;
    text-decoration: none;
    transition: opacity 0.2s ease;
}

.flacso-breadcrumbs a:hover {
    opacity: 0.7;
}

.flacso-breadcrumbs li:not(:last-child)::after {
    content: '/';
    margin-left: 0.5rem;
    opacity: 0.5;
}

/* Sobrescribir el estilo del módulo de portada para que se adapte al archivo */
.flacso-novedades-archive .flacso-novedades-section {
    padding-top: 0 !important;
}

.flacso-novedades-archive .flacso-novedades-header {
    display: none; /* Ocultamos el título "Novedades" porque ya tenemos el Hero h1 */
}
</style>

<?php
get_footer();
