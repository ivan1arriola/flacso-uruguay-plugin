<?php
get_header();

$term = get_queried_object();
?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="site-container entry-content-wrap">
            <!-- Encabezado -->
            <div style="margin-bottom: 2rem;">
                <a href="<?php echo esc_url(home_url('/docentes/')); ?>" class="kb-button kb-btn-secondary kb-btn-sm" style="margin-bottom: 1rem;">
                    ← Volver a docentes
                </a>
                
                <h1 class="entry-title kb-margin-b-sm"><?php echo esc_html($term->name); ?></h1>
                
                <?php if ($term->description): ?>
                    <p style="font-size: 1.1rem; color: var(--global-palette5);"><?php echo esc_html($term->description); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Docentes del equipo -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php
                $args = [
                    'post_type' => 'docente',
                    'posts_per_page' => 12,
                    'tax_query' => [
                        [
                            'taxonomy' => 'equipo-docente',
                            'field' => 'term_id',
                            'terms' => $term->term_id
                        ]
                    ],
                    'orderby' => 'meta_value',
                    'meta_key' => 'apellido',
                    'order' => 'ASC'
                ];
                
                $query = new WP_Query($args);
                
                if ($query->have_posts()):
                    while ($query->have_posts()): $query->the_post();
                        $nombre_completo = dp_nombre_completo(get_the_ID());
                        $titulo = get_post_meta(get_the_ID(), 'titulo', true);
                        $imagen_id = get_post_thumbnail_id();
                ?>
                <div class="entry loop-entry">
                    <div class="entry-content-wrap">
                        <?php if ($imagen_id): ?>
                            <div style="height: 280px; overflow: hidden; margin-bottom: 1rem;">
                                <?php echo wp_get_attachment_image($imagen_id, 'medium', false, ['style' => 'width: 100%; height: 100%; object-fit: cover;']); ?>
                            </div>
                        <?php else: ?>
                            <div style="height: 280px; background: var(--global-palette8); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                                <span style="color: var(--global-palette5);">Sin imagen</span>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="entry-title" style="margin-bottom: 0.5rem;"><?php echo esc_html($nombre_completo); ?></h3>
                        
                        <?php if ($titulo): ?>
                            <p style="font-size: 0.95rem; color: var(--global-palette5); margin-bottom: 1rem;"><?php echo esc_html($titulo); ?></p>
                        <?php endif; ?>
                        
                        <a href="<?php the_permalink(); ?>" class="kb-button kb-btn-lg kb-btn-primary">
                            Ver Perfil
                        </a>
                    </div>
                </div>
                <?php
                    endwhile;
                    wp_reset_postdata();
                else:
                    echo '<p style="grid-column: 1/-1;">No hay docentes en este equipo.</p>';
                endif;
                ?>
            </div>
        </div>
    </main>
</div>

<?php
get_footer();
