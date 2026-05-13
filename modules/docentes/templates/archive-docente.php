<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$docentes_query = new WP_Query([
    'post_type' => 'docente',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => [
        'meta_value' => 'ASC',
        'title' => 'ASC',
    ],
    'meta_key' => 'apellido',
    'no_found_rows' => true,
]);

$total_docentes = (int) $docentes_query->post_count;
?>

<div class="flacso-docentes-archive">
    <!-- Hero Section con buscador -->
    <header class="archive-hero">
        <div class="site-container">
            <div class="hero-content">
                <span class="hero-kicker">Comunidad Académica</span>
                <h1 class="hero-title">Nuestros Docentes</h1>
                <p class="hero-desc">Conoce a los profesionales y académicos que integran el cuerpo docente de FLACSO Uruguay.</p>
                
                <div class="search-container">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="archive-search" placeholder="Buscar por nombre o especialidad..." aria-label="Buscar docentes">
                    </div>
                    <div class="search-stats" id="search-stats">
                        Mostrando <?php echo $total_docentes; ?> perfiles
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="archive-main">
        <div class="site-container">
            <div id="docentes-grid" class="docentes-grid">
                <?php if ($docentes_query->have_posts()) : ?>
                    <?php while ($docentes_query->have_posts()) : $docentes_query->the_post(); ?>
                        <?php
                        $id = get_the_ID();
                        $nombre = (string) get_post_meta($id, 'nombre', true);
                        $apellido = (string) get_post_meta($id, 'apellido', true);
                        $nombre_completo = get_the_title();
                        $titulo_academico = (string) get_post_meta($id, 'titulo_academico', true);
                        $prefijo_abrev = (string) get_post_meta($id, 'prefijo_abrev', true);
                        
                        $cv_raw = (string) get_post_meta($id, 'cv', true);
                        $resumen = wp_trim_words(wp_strip_all_tags($cv_raw), 22);

                        $iniciales = function_exists('dp_iniciales') ? dp_iniciales($nombre, $apellido, 'D') : 'FL';
                        ?>

                        <article class="docente-card-mini docente-item" data-search="<?php echo esc_attr(strtolower($nombre_completo . ' ' . $titulo_academico)); ?>">
                            <div class="card-inner">
                                <div class="card-avatar">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <?php the_post_thumbnail('medium', ['class' => 'avatar-img']); ?>
                                    <?php else : ?>
                                        <div class="avatar-fallback"><?php echo esc_html($iniciales); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="card-meta">
                                        <?php if ($prefijo_abrev) : ?>
                                            <span class="prefijo"><?php echo esc_html($prefijo_abrev); ?></span>
                                        <?php endif; ?>
                                        <h2 class="nombre"><?php echo esc_html($nombre_completo); ?></h2>
                                        <?php if ($titulo_academico) : ?>
                                            <p class="titulo"><?php echo esc_html($titulo_academico); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($resumen) : ?>
                                        <p class="resumen"><?php echo esc_html($resumen); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="<?php the_permalink(); ?>" class="btn-profile">Ver Perfil Académico</a>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="empty-results">
                        <p>No se encontraron docentes publicados en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('archive-search');
    const items = document.querySelectorAll('.docente-item');
    const stats = document.getElementById('search-stats');

    if (!searchInput) return;

    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase().trim();
        let visibleCount = 0;

        items.forEach(item => {
            const content = item.getAttribute('data-search');
            if (content.includes(term)) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        stats.textContent = `Mostrando ${visibleCount} ${visibleCount === 1 ? 'perfil' : 'perfiles'}`;
    });
});
</script>

<style>
    .flacso-docentes-archive {
        --primary: #1d3a72;
        --primary-dark: #0f172a;
        --accent: #fed222;
        --text-muted: #64748b;
        --bg-soft: #f8fafc;
        --border-soft: #e2e8f0;
        
        background-color: var(--bg-soft);
        min-height: 100vh;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* Hero */
    .archive-hero {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        padding: 5rem 0 7rem;
        color: #fff;
        text-align: center;
        clip-path: ellipse(150% 100% at 50% 0%);
        margin-bottom: -3rem;
    }
    .hero-kicker {
        display: inline-block;
        color: var(--accent);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.1em;
        margin-bottom: 1rem;
    }
    .hero-title { font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin: 0 0 1rem; line-height: 1; }
    .hero-desc { font-size: 1.25rem; opacity: 0.85; max-width: 600px; margin: 0 auto 3rem; }

    /* Search Bar */
    .search-container { max-width: 700px; margin: 0 auto; position: relative; z-index: 10; }
    .search-box {
        background: #fff;
        border-radius: 20px;
        padding: 0.5rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }
    .search-icon { font-size: 1.2rem; }
    .search-box input {
        border: none;
        width: 100%;
        padding: 1rem 0;
        font-size: 1.1rem;
        color: var(--primary-dark);
        outline: none;
    }
    .search-stats { margin-top: 1.5rem; font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.7); }

    /* Grid */
    .archive-main { padding: 4rem 0 6rem; }
    .docentes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 2rem;
    }

    /* Cards */
    .docente-card-mini { height: 100%; }
    .card-inner {
        background: #fff;
        border-radius: 24px;
        border: 1px solid var(--border-soft);
        padding: 2rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: center;
    }
    .card-inner:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        border-color: var(--primary);
    }

    .card-avatar {
        width: 100px;
        height: 100px;
        border-radius: 24px;
        margin: 0 auto 1.5rem;
        overflow: hidden;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        border: 3px solid #fff;
    }
    .avatar-img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-fallback {
        width: 100%; height: 100%; background: var(--primary);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 2rem;
    }

    .prefijo {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .nombre { font-size: 1.35rem; font-weight: 800; color: var(--primary-dark); margin: 0 0 0.5rem; line-height: 1.2; }
    .titulo { font-size: 0.95rem; color: var(--primary); font-weight: 600; margin-bottom: 1rem; }
    .resumen { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem; flex-grow: 1; }

    .btn-profile {
        display: block;
        padding: 0.75rem 1rem;
        background: var(--bg-soft);
        color: var(--primary);
        border-radius: 12px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.2s ease;
        border: 1px solid var(--border-soft);
    }
    .btn-profile:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

    .empty-results { grid-column: 1 / -1; text-align: center; padding: 4rem; color: var(--text-muted); }

    .site-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }

    @media (max-width: 768px) {
        .archive-hero { padding: 4rem 0 6rem; }
        .hero-title { font-size: 2.5rem; }
        .docentes-grid { grid-template-columns: 1fr; }
    }
</style>
<?php
get_footer();
