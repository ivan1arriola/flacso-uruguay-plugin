<?php
get_header();
?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="site-container entry-content-wrap">
            <h1 class="entry-title">Equipo Docente</h1>
            
            <!-- Filtro por equipo -->
            <?php
            $equipos = get_terms(['taxonomy' => 'equipo-docente', 'hide_empty' => false]);
            if (!empty($equipos) && !is_wp_error($equipos)):
            ?>
                <div class="kb-margin-b-md kb-padding-b-md" style="border-bottom: 1px solid var(--global-palette6);">
                    <p class="kb-margin-b-sm"><strong>Filtrar por equipo:</strong></p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <a href="<?php echo esc_url(get_post_type_archive_link('docente')); ?>" 
                           class="kb-button kb-btn-lg kb-btn-global-outline">Todos</a>
                        <?php foreach ($equipos as $equipo): ?>
                            <a href="<?php echo esc_url(get_term_link($equipo)); ?>" 
                               class="kb-button kb-btn-lg kb-btn-global-secondary">
                                <?php echo esc_html($equipo->name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Listado de docentes -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                <?php
                $args = [
                    'post_type' => 'docente',
                    'posts_per_page' => 12,
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
                        $equipos = get_the_terms(get_the_ID(), 'equipo-docente');
                ?>
                <div class="entry loop-entry">
                    <div class="entry-content-wrap">
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
                        
                        <?php if (!empty($equipos) && !is_wp_error($equipos)): ?>
                            <div style="margin-bottom: 1rem;">
                                <?php foreach ($equipos as $equipo): ?>
                                    <a href="<?php echo esc_url(get_term_link($equipo)); ?>" 
                                       style="display: inline-block; background: var(--global-palette1); color: var(--global-palette9); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; margin-right: 0.5rem; text-decoration: none;">
                                        <?php echo esc_html($equipo->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php the_permalink(); ?>" class="kb-button kb-btn-lg kb-btn-primary" style="margin-top: auto;">
                            Ver Perfil
                        </a>
                    </div>
                </div>
                <?php
                    endwhile;
                    wp_reset_postdata();
                else:
                    echo '<p style="grid-column: 1/-1;">No hay docentes disponibles.</p>';
                endif;
                ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($query->max_num_pages > 1): ?>
                <nav style="margin-top: 2rem; text-align: center;">
                    <?php
                    echo paginate_links([
                        'total' => $query->max_num_pages,
                        'prev_text' => '← Anterior',
                        'next_text' => 'Siguiente →',
                        'type' => 'plain'
                    ]);
                    ?>
                </nav>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
get_footer();
get_footer();
