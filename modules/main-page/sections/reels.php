<?php
// ==================================================
// SECCION REELS INSTAGRAM - FLACSO URUGUAY
// ==================================================

if (!function_exists('flacso_section_reels_render')) {
function flacso_section_reels_render() {
    $feed = class_exists('Flacso_Instagram_API') ? Flacso_Instagram_API::get_feed() : new WP_Error('no_class', 'API class not found');
    
    // Si no hay datos, o hay error, no renderizamos nada para los reels
    if (is_wp_error($feed) || empty($feed)) {
        return '';
    }
    
    $settings = Flacso_Main_Page_Settings::get_settings();
    $selected_ids = $settings['reels']['selected_ids'] ?? [];
    if (!is_array($selected_ids)) $selected_ids = [];

    // Filtrar solo videos
    $reels = array_filter($feed, function($item) {
        return $item['media_type'] === 'VIDEO';
    });

    if (!empty($selected_ids)) {
        // Filtrar reels manualmente seleccionados y ordenarlos según el orden de selección (opcional, aquí los preservamos según la API o podríamos ordenarlos)
        $reels = array_filter($reels, function($item) use ($selected_ids) {
            return in_array($item['id'], $selected_ids, true);
        });
        
        // Reordenar para que coincidan con el orden de los selected_ids si se quiere, pero array_values es suficiente
        $reels = array_values($reels);
    } else {
        // Fallback automático: solo los más recientes
        $reels = array_slice($reels, 0, 10);
    }

    if (empty($reels)) {
        return '';
    }

    $title = (string) apply_filters('flacso_main_page_reels_title', $settings['reels']['title'] ?? 'Contenido Audiovisual');
    $section_id = 'flacso-reels-' . wp_generate_password(6, false);

    ob_start();
    ?>
    <section class="flacso-home-block flacso-reels-section" aria-labelledby="<?php echo esc_attr($section_id); ?>">
        <div class="flacso-content-shell">
            <header class="text-center mb-5">
                <h2 id="<?php echo esc_attr($section_id); ?>"><?php echo esc_html($title); ?></h2>
            </header>
            
            <div id="<?php echo esc_attr($section_id); ?>-container" class="flacso-reels-dynamic-container">
                <div class="flacso-reels-loading">
                    <p><?php esc_html_e('Clasificando videos...', 'flacso-main-page'); ?></p>
                </div>
            </div>
            
            <script type="application/json" id="<?php echo esc_attr($section_id); ?>-data">
                <?php echo wp_json_encode(array_values($reels)); ?>
            </script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const dataEl = document.getElementById('<?php echo esc_js($section_id); ?>-data');
                const container = document.getElementById('<?php echo esc_js($section_id); ?>-container');
                if (!dataEl || !container) return;
                
                try {
                    const reels = JSON.parse(dataEl.textContent);
                    const vertical = [];
                    const horizontal = [];
                    
                    let pending = reels.length;
                    if (pending === 0) {
                        container.innerHTML = '';
                        return;
                    }
                    
                    // Función para renderizar cuando todos han sido medidos (o suficientes)
                    const checkAndRender = () => {
                        pending--;
                        // Renderizamos cuando terminamos de medir todos los videos (o al menos logramos 3 y 3)
                        if (pending === 0 || (vertical.length >= 3 && horizontal.length >= 3)) {
                            // Evitar doble render
                            if (container.dataset.rendered) return;
                            container.dataset.rendered = 'true';
                            
                            const finalVertical = vertical.slice(0, 3);
                            const finalHorizontal = horizontal.slice(0, 3);
                            
                            let html = '';
                            
                            const renderItem = (reel) => {
                                const caption = reel.caption ? `<div class="flacso-reel-caption"><p>${reel.caption.substring(0, 100)}...</p><a href="${reel.permalink}" target="_blank" rel="noopener noreferrer" class="flacso-reel-link">Ver en Instagram <i class="bi bi-box-arrow-up-right"></i></a></div>` : '';
                                return `
                                    <div class="flacso-reel-item">
                                        <video controls preload="metadata" poster="${reel.thumbnail_url}" class="flacso-reel-video">
                                            <source src="${reel.media_url}" type="video/mp4">
                                        </video>
                                        ${caption}
                                    </div>
                                `;
                            };
                            
                            if (finalVertical.length > 0) {
                                html += `<h3 class="flacso-reels-subtitle">Reels Destacados</h3><div class="flacso-reels-grid flacso-reels-grid--vertical">`;
                                html += finalVertical.map(renderItem).join('');
                                html += `</div>`;
                            }
                            
                            if (finalHorizontal.length > 0) {
                                html += `<h3 class="flacso-reels-subtitle mt-5">Videos y Entrevistas</h3><div class="flacso-reels-grid flacso-reels-grid--horizontal">`;
                                html += finalHorizontal.map(renderItem).join('');
                                html += `</div>`;
                            }
                            
                            container.innerHTML = html;
                        }
                    };
                    
                    // Medir imágenes en paralelo
                    reels.forEach(reel => {
                        const img = new Image();
                        img.onload = function() {
                            if (img.naturalHeight > img.naturalWidth) {
                                vertical.push(reel);
                            } else {
                                horizontal.push(reel);
                            }
                            checkAndRender();
                        };
                        img.onerror = function() {
                            // Si falla, asumimos vertical por defecto
                            vertical.push(reel);
                            checkAndRender();
                        };
                        img.src = reel.thumbnail_url;
                    });
                    
                } catch (e) {
                    console.error('Error parsing reels data', e);
                    container.innerHTML = '';
                }
            });
            </script>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
}
