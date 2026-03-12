<?php
get_header();

$equipos = get_terms([
    'taxonomy'   => 'equipo-docente',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="site-container entry-content-wrap">
            <!-- Hero Section -->
            <section style="margin-bottom: 2rem;">
                <div style="background: var(--global-palette1); color: white; padding: 2rem; border-radius: 8px;">
                    <p style="text-transform: uppercase; opacity: 0.7; font-size: 0.85rem; margin-bottom: 0.5rem;">Mapa de equipos académicos</p>
                    <h1 class="entry-title" style="color: white; margin-bottom: 1rem;">Equipos académicos y posgrados asociados</h1>
                    <p style="font-size: 1.1rem; opacity: 0.9; margin: 0;">
                        Cada tarjeta vincula una página de posgrado con su equipo académico. Usa el buscador para filtrar por nombre de programa o integrante.
                    </p>
                </div>
            </section>


    <?php if ($equipos && !is_wp_error($equipos)): ?>
        <!-- Search Section -->
        <section style="margin-bottom: 2rem; background: var(--global-palette8); padding: 1.5rem; border-radius: 8px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <label for="posgrado-search" style="margin-bottom: 0; font-weight: 600;">Buscar equipo:</label>
                <input 
                    type="search" 
                    id="posgrado-search" 
                    class="kb-button" 
                    placeholder="Buscar por nombre..." 
                    aria-controls="posgrado-grid"
                    style="flex: 1; padding: 0.75rem; border: 1px solid var(--global-palette3); border-radius: 4px;"
                >
            </div>
        </section>

        <!-- Grid de programas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 2rem;" id="posgrado-grid">
            <?php foreach ($equipos as $equipo):
                $color      = function_exists('get_equipo_color') ? get_equipo_color($equipo->term_id) : 'var(--global-palette1)';
                $page_data  = function_exists('dp_get_equipo_page_data') ? dp_get_equipo_page_data($equipo->term_id) : null;
                $page_title = $page_data ? $page_data['title'] : $equipo->name;
                $relacion_nombre = function_exists('dp_get_equipo_relacion_nombre') ? dp_get_equipo_relacion_nombre($equipo->term_id, $equipo->name) : $equipo->name;
                $page_excerpt_raw = $page_data ? $page_data['excerpt'] : ($equipo->description ?: __('Este posgrado aún no tiene descripción.', 'flacso-posgrados-docentes'));
                $page_excerpt = wp_trim_words(wp_strip_all_tags($page_excerpt_raw), 28, '...');
                $term_link   = get_term_link($equipo);
                if (is_wp_error($term_link)) {
                    $term_link = '';
                }
                $page_link   = $page_data ? $page_data['permalink'] : $term_link;
                $page_thumb  = ($page_data && !empty($page_data['thumbnail'])) ? $page_data['thumbnail'] : '';
                $docentes_preview = get_posts([
                    'post_type'      => 'docente',
                    'posts_per_page' => 4,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                    'tax_query'      => [[
                        'taxonomy' => 'equipo-docente',
                        'field'    => 'term_id',
                        'terms'    => $equipo->term_id,
                    ]],
                ]);
            ?>
                <article class="entry loop-entry" data-title="<?php echo esc_attr(strtolower($page_title . ' ' . $relacion_nombre)); ?>" style="display: flex; flex-direction: column; background: white; border: 1px solid var(--global-palette8); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: box-shadow 0.3s ease;">
                    <!-- Badge del equipo -->
                    <div style="background: rgba(29, 58, 114, 0.08); color: var(--global-palette1); padding: 0.75rem 1rem; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">
                        <?php echo esc_html($relacion_nombre); ?>
                    </div>
                    
                    <!-- Imagen o placeholder -->
                    <div style="height: 200px; background: <?php echo esc_attr($color ? $color : 'var(--global-palette8)'); ?>; background-size: cover; background-position: center; position: relative;">
                        <?php if ($page_thumb): ?>
                            <img src="<?php echo esc_url($page_thumb); ?>" alt="<?php echo esc_attr($page_title); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-weight: 600; text-align: center; padding: 1rem;">
                                <?php echo esc_html($page_title); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contenido -->
                    <div style="padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column;">
                        <h2 class="entry-title" style="margin-bottom: 0.5rem; font-size: 1.25rem;"><?php echo esc_html($page_title); ?></h2>
                        <p style="color: var(--global-palette5); margin-bottom: 1rem; flex-grow: 1;"><?php echo esc_html($page_excerpt); ?></p>
                        
                        <!-- Integrantes preview -->
                        <div style="margin-bottom: 1.5rem;">
                            <p style="text-transform: uppercase; font-size: 0.75rem; color: var(--global-palette5); margin-bottom: 0.5rem; font-weight: 600;">
                                Integrantes (<?php echo intval($equipo->count); ?>)
                            </p>
                            <?php if ($docentes_preview): ?>
                                <ul style="display: flex; gap: 0.5rem; list-style: none; margin: 0; padding: 0;">
                                    <?php foreach ($docentes_preview as $docente):
                                        $nombre_docente = dp_nombre_completo($docente->ID);
                                        $avatar = get_the_post_thumbnail($docente->ID, [40, 40], ['class' => 'kb-image', 'style' => 'border-radius: 50%; width: 40px; height: 40px; object-fit: cover;']);
                                        if (!$avatar) {
                                            $color_avatar = '#999';
                                            $initial = strtoupper(substr($nombre_docente, 0, 1));
                                            $avatar = '<div style="background:' . esc_attr($color_avatar) . '; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">' . esc_html($initial) . '</div>';
                                        }
                                    ?>
                                        <li>
                                            <a href="<?php echo esc_url(get_permalink($docente->ID)); ?>" title="<?php echo esc_attr($nombre_docente); ?>">
                                                <?php echo $avatar; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="color: var(--global-palette5); margin: 0;">Sin integrantes publicados</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Botones -->
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <?php if ($page_link): ?>
                                <a class="kb-button kb-btn-primary" href="<?php echo esc_url($page_link); ?>" target="_blank" rel="noopener">
                                    Ver posgrado
                                </a>
                            <?php endif; ?>
                            <a class="kb-button kb-btn-secondary" href="<?php echo esc_url($term_link); ?>">
                                Ver integrantes
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem 1.5rem; background: var(--global-palette8); border-radius: 8px;">
            <p style="color: var(--global-palette5); margin: 0;">No hay equipos académicos registrados.</p>
        </div>
    <?php endif; ?>
        </div>
    </main>
</div>
