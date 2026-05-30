<?php
/**
 * Template Name: FLACSO - Carta de Presentación
 * Description: Template dinámico para la página virtual de carta de presentación
 */

if (!defined('ABSPATH')) { exit; }

get_header();

global $post; // Página padre (oferta-academica)

$post_id = $post->ID;
$data = class_exists('Oferta_Data_Schema') ? Oferta_Data_Schema::get_schema($post_id) : [];

// 1. Obtener el contenido limpio de la Carta
// Primero intentamos leer el nuevo campo HTML desde el editor React
$child_content = !empty($data['carta_presentacion_html']) ? $data['carta_presentacion_html'] : '';

// Si no hay contenido en el nuevo campo, buscamos la subpágina legacy (WordPress)
if (empty($child_content)) {
    $child_page = get_page_by_path($post->post_name . '/carta', OBJECT, 'page');
    if ($child_page) {
        $child_content = apply_filters('the_content', $child_page->post_content);
    }
}

// 2. Determinar el Tipo de Oferta (Maestría, Diplomado, etc.)
$tipo_terms = get_the_terms($post_id, 'tipo-oferta-academica');
$tipo_oferta = '';
if ($tipo_terms && !is_wp_error($tipo_terms)) {
    $tipo_oferta = $tipo_terms[0]->name;
}

$is_maestria = stripos($tipo_oferta, 'maestr') !== false;
$is_diplomado = stripos($tipo_oferta, 'diplomado') !== false || stripos($tipo_oferta, 'diploma') !== false;

// 3. Extraer datos dinámicos para los shortcodes
$abreviacion = esc_attr($data['abreviacion'] ?? '');
$cohorte = esc_attr(get_post_meta($post_id, 'cohorte', true) ?: '');
$anio = date('Y');

$proximo_inicio_val = $data['proximo_inicio'] ?? '';
if (is_array($proximo_inicio_val)) {
    $proximo_inicio_val = !empty($proximo_inicio_val) ? $proximo_inicio_val[0] : '';
}

$proximo_inicio = 'A definir';
if (!empty($proximo_inicio_val)) {
    $proximo_inicio_str = (string) $proximo_inicio_val;
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('j \d\e F \d\e Y', strtotime($proximo_inicio_str));
    } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('F Y', strtotime($proximo_inicio_str . '-01'));
    } else {
        $proximo_inicio = esc_attr($proximo_inicio_str);
    }
}
$duracion = !empty($data['duracion_meses']) ? esc_attr($data['duracion_meses'] . ' meses') : '12 meses';
$modalidad = !empty($data['modalidad_resumen']) ? esc_attr($data['modalidad_resumen']) : 'Virtual';

// Menciones y Orientaciones
$menciones = is_array($data['menciones'] ?? null) ? implode('|', $data['menciones']) : ($data['menciones'] ?? '');
$orientaciones = is_array($data['orientaciones'] ?? null) ? implode('|', $data['orientaciones']) : ($data['orientaciones'] ?? '');

$es_especializacion = stripos($tipo_oferta, 'especializa') !== false;

// Reconocimientos (Leemos desde el CPT primero, sino usamos la regla por defecto)
$reconocido_mec = !empty($data['reconocido_mec']) ? 'true' : ($is_maestria ? 'true' : 'false');
$reconocimiento_int = !empty($data['reconocimiento_internacional']) ? 'true' : ($is_maestria || $post_id == 12316 ? 'true' : 'false');

// URLs de calendario y malla
$url_malla = esc_attr($data['malla_curricular'] ?? '');
$url_calendario = esc_attr($data['calendario'] ?? '');

// 4. Determinar Asistente Académico dinámicamente desde los equipos del programa
$asistente_slug = '';
$asistente_nombre = '';
$asistente_correo = 'inscripciones@flacso.edu.uy';
$asistente_titulo = 'Asistente Académica';

if (!empty($data['equipos']) && is_array($data['equipos'])) {
    foreach ($data['equipos'] as $eq) {
        if (stripos($eq['nombre'], 'asistente') !== false && !empty($eq['docentes'])) {
            $docente_id = $eq['docentes'][0];
            $asistente_post = get_post($docente_id);
            if ($asistente_post) {
                $asistente_slug = $asistente_post->post_name;
                $asistente_nombre = get_the_title($asistente_post->ID);
                $asistente_titulo = $eq['nombre'];
                $correo_meta = get_post_meta($docente_id, 'correo', true);
                if ($correo_meta) {
                    $asistente_correo = $correo_meta;
                }
            }
            break;
        }
    }
}

// Fallbacks de asistentes conocidos si no se configuran en equipos
if (!$asistente_slug) {
    if ($is_maestria && stripos($post->post_name, 'educacion-innovacion-tecnologias') !== false) {
        $asistente_slug = 'analia-bombau';
        $asistente_nombre = 'Analía Bombau';
        $asistente_correo = 'edutic@flacso.edu.uy';
    } elseif ($is_diplomado && stripos($post->post_name, 'genero') !== false) {
        $asistente_slug = 'florencia-quartino';
        $asistente_nombre = 'Florencia Quartino';
        $asistente_correo = 'genero@flacso.edu.uy';
    }
}
?>

<div id="primary" class="content-area flacso-carta-virtual">
    <main id="main" class="site-main">
        
        <?php 
        // HERO (Reemplazo nativo)
        $hero_id = 'gc-hero-' . wp_rand(1000, 9999);
        $titulo = get_the_title($post_id);
        $imagen = '';
        $imagen_id = get_post_thumbnail_id($post_id);
        if ($imagen_id) {
            $imagen_array = wp_get_attachment_image_src($imagen_id, 'large');
            if ($imagen_array && !empty($imagen_array[0])) { $imagen = $imagen_array[0]; }
        }

        $arr_menciones = !empty($menciones) ? array_map('trim', explode('|', $menciones)) : array();
        $arr_orientaciones = !empty($orientaciones) ? array_map('trim', explode('|', $orientaciones)) : array();
        $mostrar_banner_convenio = !empty($data['convenio_iin_oea']);
        
        echo '<div id="' . esc_attr($hero_id) . '" class="gc-hero-mobile-wrapper">';
        echo '<div class="gc-hero-mobile-section" style="background:var(--global-palette1);color:var(--global-palette9);border-radius:.875rem;box-shadow:0 8px 28px rgba(15,26,45,.22);overflow:hidden;margin:.5rem;">';
        
        echo '<div class="gc-intro-unificada" style="background:linear-gradient(180deg,var(--global-palette8) 0%, var(--global-palette7) 100%);padding:1rem;text-align:center;">';
        echo '<p class="gc-tagline" style="margin:0;color:var(--global-palette4);font-weight:600;"><em>Formación de excelencia, estés donde estés.</em></p>';
        echo '</div>';

        if ($mostrar_banner_convenio) {
            echo '<div class="gc-convenio-inside" style="display:flex;justify-content:center;background:var(--global-palette1);padding:.6rem 1rem 0;">';
            echo '<div class="gc-convenio-pill" style="display:inline-flex;align-items:center;gap:.55rem;background:#fed222;color:#1d3a72;padding:.55rem .95rem;border-radius:9999px;font-weight:600;"><i class="bi bi-handshake"></i><span>En convenio con el <strong>Instituto Interamericano de la Niña, el Niño y Adolescentes (IIN-OEA)</strong></span></div>';
            echo '</div>';
        }

        echo '<div class="gc-hero-mobile-content" style="padding:1.5rem;display:flex;flex-wrap:wrap;gap:2rem;">';
        if ($imagen) {
            echo '<div class="gc-portada-mobile-container" style="flex:1;min-width:300px;display:flex;justify-content:center;"><img src="' . esc_url($imagen) . '" alt="' . esc_attr($titulo) . '" style="width:100%;max-width:320px;border-radius:.875rem;border:3px solid var(--global-palette6);object-fit:cover;"></div>';
        }

        echo '<div class="gc-hero-mobile-text" style="flex:2;min-width:300px;display:flex;flex-direction:column;gap:1.5rem;">';
        echo '<h1 class="gc-hero-mobile-title" style="margin:0;font-size:2.25rem;color:var(--global-palette9);line-height:1.1;">' . esc_html($titulo) . '</h1>';

        echo '<div class="gc-program-mobile-info" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">';
        if (!empty($abreviacion)) {
            echo '<span class="gc-program-mobile-abbreviation" style="background:var(--global-palette7);color:var(--global-palette3);padding:.9rem;border-radius:.65rem;text-align:center;font-weight:700;">' . esc_html($abreviacion) . ' ' . esc_html($anio) . '</span>';
        }
        if (!empty($cohorte)) {
            echo '<span class="gc-cohorte-mobile-badge" style="background:var(--global-palette7);color:var(--global-palette3);padding:.9rem;border-radius:.65rem;text-align:center;font-weight:700;">' . esc_html($cohorte) . '</span>';
        }
        echo '</div>';

        if ($reconocido_mec === 'true' || $reconocimiento_int === 'true') {
            echo '<div class="gc-certificaciones-mobile-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">';
            if ($reconocido_mec === 'true') {
                echo '<div class="gc-certificacion-mobile-item" style="background:var(--global-palette7);color:var(--global-palette3);padding:1rem;border-radius:.65rem;text-align:center;font-weight:600;display:flex;align-items:center;justify-content:center;"><i class="bi bi-award" style="color:var(--global-palette1);font-size:1.05rem;margin-right:0.5rem;"></i>Reconocida por el M.E.C</div>';
            }
            if ($reconocimiento_int === 'true') {
                echo '<div class="gc-certificacion-mobile-item" style="background:var(--global-palette7);color:var(--global-palette3);padding:1rem;border-radius:.65rem;text-align:center;font-weight:600;display:flex;align-items:center;justify-content:center;"><i class="bi bi-globe" style="color:var(--global-palette1);font-size:1.05rem;margin-right:0.5rem;"></i>Apostillado de La Haya</div>';
            }
            echo '</div>';
        }

        if (!empty($arr_menciones)) {
            echo '<div class="gc-menciones-mobile-section"><p class="gc-menciones-mobile-title" style="font-weight:700;color:var(--global-palette9);margin-bottom:1rem;">Con mención en:</p><div class="gc-menciones-mobile-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">';
            foreach ($arr_menciones as $mencion) {
                echo '<div class="gc-mencion-mobile-item" style="background:var(--global-palette7);color:var(--global-palette3);padding:1rem;border-radius:.65rem;text-align:center;font-weight:600;"><span>' . esc_html($mencion) . '</span></div>';
            }
            echo '</div></div>';
        }

        if (!empty($arr_orientaciones)) {
            echo '<div class="gc-orientaciones-mobile-section"><p class="gc-orientaciones-mobile-title" style="font-weight:700;color:var(--global-palette9);margin-bottom:1rem;">Orientaciones metodológicas:</p><div class="gc-orientaciones-mobile-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">';
            foreach ($arr_orientaciones as $orien) {
                echo '<div class="gc-orientacion-mobile-item" style="background:var(--global-palette7);color:var(--global-palette3);padding:1rem;border-radius:.65rem;text-align:center;font-weight:600;"><span>' . esc_html($orien) . '</span></div>';
            }
            echo '</div></div>';
        }

        echo '</div>'; // End mobile-text
        echo '</div>'; // End mobile-content

        // Bloque final de Títulos Intermedios!!
        $titulos_intermedios_ids = !empty($data['titulos_intermedios']) && is_array($data['titulos_intermedios']) ? $data['titulos_intermedios'] : [];
        if (!empty($titulos_intermedios_ids)) {
            echo '<div class="gc-mg-info" style="background:var(--global-palette1);padding:1.5rem;border-top:1px solid rgba(0,0,0,.12);">';
            echo '<p style="color:var(--global-palette9);font-weight:800;text-align:center;margin-bottom:1rem;">Durante la cursada del programa se obtendrán <strong>títulos intermedios</strong>:</p>';
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem;">';
            foreach ($titulos_intermedios_ids as $tid) {
                $t_post = get_post($tid);
                if ($t_post) {
                    $t_duracion = get_post_meta($tid, 'duracion_html', true);
                    echo '<div style="display:flex;align-items:center;gap:.75rem;background:var(--global-palette8);color:var(--global-palette3);padding:1rem;border-radius:14px;justify-content:center;">';
                    echo '<div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--global-palette7);flex-shrink:0;"><i class="bi bi-mortarboard" style="color:var(--global-palette1);"></i></div>';
                    echo '<div style="display:flex;flex-direction:column;text-align:left;">';
                    echo '<span style="font-weight:800;line-height:1.2;">' . esc_html($t_post->post_title) . '</span>';
                    if (!empty($t_duracion)) {
                        echo '<span style="font-weight:600;font-size:0.9rem;color:var(--global-palette4);">' . esc_html($t_duracion) . '</span>';
                    }
                    echo '</div></div>';
                }
            }
            echo '</div>'; 
            if ($is_maestria) {
                echo '<p style="margin:1rem 0 0 0;color:var(--global-palette9);font-weight:600;text-align:center;">El título de <strong>Magíster</strong> será obtenido una vez se aprueben los créditos académicos y se defienda la tesis.</p>';
            }
            echo '</div>';
        }
        
        echo '</div></div>'; // End section, wrapper 
        ?>
        
        <div class="container" style="max-width: 900px; margin-top: 3rem; margin-bottom: 3rem;">
            
            <?php 
            // INFO CLAVE (Reemplazo nativo)
            $info_clave_id = 'gc-info-clave-' . wp_rand(1000, 9999);
            echo '<div id="' . esc_attr($info_clave_id) . '" class="gc-info-clave-wrapper" style="margin:2rem 0;">';
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;">';
            // Inicio
            echo '<div style="background:var(--global-palette9);border:2px solid var(--global-palette7);border-top:4px solid var(--global-palette1);border-radius:1rem;padding:1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.08);">';
            echo '<div style="width:60px;height:60px;background:var(--global-palette7);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;"><i class="bi bi-calendar-event" style="font-size:1.5rem;color:var(--global-palette1);"></i></div>';
            echo '<h3 style="color:var(--global-palette1);font-weight:700;margin:0 0 .5rem 0;font-size:1.05rem;">Próximo inicio</h3><p style="color:var(--global-palette3);margin:0;font-weight:600;">' . esc_html(wp_strip_all_tags($proximo_inicio)) . '</p></div>';
            // Duracion
            echo '<div style="background:var(--global-palette9);border:2px solid var(--global-palette7);border-top:4px solid var(--global-palette1);border-radius:1rem;padding:1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.08);">';
            echo '<div style="width:60px;height:60px;background:var(--global-palette7);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;"><i class="bi bi-clock" style="font-size:1.5rem;color:var(--global-palette1);"></i></div>';
            echo '<h3 style="color:var(--global-palette1);font-weight:700;margin:0 0 .5rem 0;font-size:1.05rem;">Duración</h3><p style="color:var(--global-palette3);margin:0;font-weight:600;">' . esc_html(wp_strip_all_tags($duracion)) . '</p></div>';
            // Modalidad
            echo '<div style="background:var(--global-palette9);border:2px solid var(--global-palette7);border-top:4px solid var(--global-palette1);border-radius:1rem;padding:1.5rem;text-align:center;display:flex;flex-direction:column;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.08);">';
            echo '<div style="width:60px;height:60px;background:var(--global-palette7);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;"><i class="bi bi-laptop" style="font-size:1.5rem;color:var(--global-palette1);"></i></div>';
            echo '<h3 style="color:var(--global-palette1);font-weight:700;margin:0 0 .5rem 0;font-size:1.05rem;">Modalidad</h3><p style="color:var(--global-palette3);margin:0;font-weight:600;">' . esc_html(wp_strip_all_tags($modalidad)) . '</p></div>';
            echo '</div></div>';
            ?>

            <!-- CONTENIDO DE LA CARTA (Párrafos y Bloques nativos de WordPress) -->
            <div class="carta-content-wrapper" style="margin: 3rem 0; font-size: 1.1rem; line-height: 1.7; color: var(--global-palette4);">
                <?php 
                $mensaje_bienvenida = get_option('flacso_mensaje_bienvenida', '');
                if (!empty($mensaje_bienvenida)) {
                    echo '<div class="flacso-mensaje-bienvenida" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8fafc; border-left: 4px solid var(--global-palette1); border-radius: 4px;">';
                    echo wp_kses_post($mensaje_bienvenida);
                    echo '</div>';
                }
                $bloques_html = [
                    'descripcion_html' => 'Información General',
                    'acreditaciones_html' => 'Acreditaciones y Validez Oficial',
                    'perfil_ingreso_html' => 'Perfil de Ingreso',
                    'objetivos_html' => 'Objetivos del Programa',
                    'modalidad_html' => 'Modalidad de Cursada',
                    'duracion_html' => 'Duración y Carga Horaria',
                    'perfil_egreso_html' => 'Perfil del Egresado',
                    'requisitos_egreso_html' => 'Requisitos para Titulación',
                    'titulos_certificaciones_html' => 'Títulos y Certificaciones',
                ];

                foreach ($bloques_html as $meta_key => $titulo_bloque) {
                    if (!empty($data[$meta_key])) {
                        echo '<div class="gc-carta-bloque-rico" style="margin-bottom: 2.5rem;">';
                        echo '<h3 style="color:var(--global-palette1);font-weight:700;margin-top:0;margin-bottom:1rem;font-size:1.5rem;">' . esc_html($titulo_bloque) . '</h3>';
                        echo wp_kses_post($data[$meta_key]);
                        echo '</div>';
                    }
                }
                echo $child_content; 
                ?>
            </div>
            
            <?php 
            // CALENDARIO Y MALLA CURRICULAR (Diseño exacto de Oferta Académica)
            if (class_exists('Oferta_Blocks') && strpos($child_content, 'flacso-uruguay/dato-malla-curricular') === false) {
                $calendario_html = Oferta_Blocks::render_dato_calendario(['ofertaId' => $post_id]);
                $malla_html = Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $post_id]);
                
                if (trim($calendario_html) !== '' || trim($malla_html) !== '') {
                    echo '<div style="margin:2.5rem 0;">';
                    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">';
                    if (trim($calendario_html) !== '') {
                        echo $calendario_html;
                    }
                    if (trim($malla_html) !== '') {
                        echo $malla_html;
                    }
                    echo '</div></div>';
                }
            }
            ?>

            <?php 
            // REQUISITOS DE ADMISIÓN
            if (!empty($data['requisitos_ingreso_html'])) {
                echo '<div style="margin:3rem 0;">';
                echo '<h2 style="color:var(--global-palette1);font-weight:700;margin-bottom:1.5rem;font-size:1.75rem;">Requisitos de Postulación y Admisión</h2>';
                echo '<div class="gc-carta-bloque-rico" style="font-size: 1.1rem; line-height: 1.7; color: var(--global-palette4); margin-bottom: 2rem;">';
                echo wp_kses_post($data['requisitos_ingreso_html']);
                echo '</div></div>';
            } else {
                echo '<div style="margin:3rem 0;">';
                echo '<h2 style="color:var(--global-palette1);font-weight:700;margin-bottom:1.5rem;font-size:1.75rem;">Requisitos de Postulación y Admisión</h2>';
                echo '<div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:2rem;">';
                
                echo '<div style="display:flex;gap:1rem;background:var(--global-palette8);border-radius:0.5rem;padding:1rem;align-items:center;"><div style="width:40px;height:40px;background:var(--global-palette1);color:white;border-radius:0.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-pencil-square"></i></div><div style="color:var(--global-palette4);font-weight:500;">Completar el formulario de preinscripción online</div></div>';
                
                if ($is_maestria) {
                    echo '<div style="display:flex;gap:1rem;background:var(--global-palette8);border-radius:0.5rem;padding:1rem;align-items:flex-start;"><div style="width:40px;height:40px;background:var(--global-palette1);color:white;border-radius:0.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-paperclip"></i></div><div style="color:var(--global-palette4);"><div style="font-weight:500;margin-bottom:0.5rem;">Añadir en el formulario, de forma escaneada:</div><ul style="margin:0;padding-left:1.5rem;"><li>Documento que acredite estudios de grado con copia legalizada</li><li>Documento de identidad vigente</li><li>Curriculum Vitae</li><li>Carta de motivación</li><li>Dos cartas de referencia</li></ul></div></div>';
                    echo '<div style="display:flex;gap:1rem;background:var(--global-palette8);border-radius:0.5rem;padding:1rem;align-items:center;"><div style="width:40px;height:40px;background:var(--global-palette1);color:white;border-radius:0.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-people"></i></div><div style="color:var(--global-palette4);font-weight:500;">Asistir a una entrevista de admisión con la coordinación académica</div></div>';
                } else {
                    echo '<div style="display:flex;gap:1rem;background:var(--global-palette8);border-radius:0.5rem;padding:1rem;align-items:flex-start;"><div style="width:40px;height:40px;background:var(--global-palette1);color:white;border-radius:0.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-paperclip"></i></div><div style="color:var(--global-palette4);"><div style="font-weight:500;margin-bottom:0.5rem;">Añadir en el formulario, de forma escaneada:</div><ul style="margin:0;padding-left:1.5rem;"><li>Documento que acredite estudios previos</li><li>Documento de identidad vigente</li><li>Carta de motivación</li></ul></div></div>';
                }
                
                echo '<div style="display:flex;gap:1rem;background:var(--global-palette8);border-radius:0.5rem;padding:1rem;align-items:center;"><div style="width:40px;height:40px;background:var(--global-palette1);color:white;border-radius:0.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-award"></i></div><div style="color:var(--global-palette4);font-weight:500;">La admisión está sujeta a cupos y selección académica</div></div>';
                echo '</div>';
                
                echo '<div style="background:var(--global-palette1);border-radius:0.5rem;padding:1.5rem;color:white;"><h3 style="color:white;font-weight:700;margin:0 0 0.5rem 0;font-size:1.1rem;">Atención</h3><p style="margin:0;opacity:0.9;">Todos los documentos deben adjuntarse en el formulario de preinscripción.</p></div>';
                echo '</div>';
            }
            
            // PRECIOS
            $precios_filas = !empty($data['precios_filas']) ? json_decode($data['precios_filas'], true) : [];
            $precios_nota = !empty($data['precios_nota']) ? $data['precios_nota'] : 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>';
            $precios_id = 'carta-precios-' . wp_rand(1000, 9999);

            if (!empty($precios_filas) && is_array($precios_filas)) {
                // Renderizar tabla de precios personalizada del CPT
                echo '<div id="' . esc_attr($precios_id) . '" class="gc-precios-wrapper" style="margin-top: 3rem;">';
                echo '<div class="gc-content-card">';
                echo '<h2 class="gc-precios-titulo">Inversión, beneficios y descuentos acumulables</h2>';
                echo '<table class="gc-pricing-table">';
                echo '<thead><tr><th>Concepto</th><th>Valor en $ (residentes en Uruguay)</th><th>Valor en U$S (residentes en el exterior)</th></tr></thead>';
                echo '<tbody>';
                foreach ($precios_filas as $row) {
                    $highlight = !empty($row['highlight']) ? ' class="highlighted"' : '';
                    echo '<tr' . $highlight . '>';
                    echo '<td>' . wp_kses_post($row['concept'] ?? '') . '</td>';
                    echo '<td>' . wp_kses_post($row['uy'] ?? '') . '</td>';
                    echo '<td>' . wp_kses_post($row['us'] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                if (!empty($precios_nota)) {
                    echo '<p class="gc-precios-beca">' . wp_kses_post($precios_nota) . '</p>';
                }
                echo '</div></div>';
            } else {
                echo '<div class="gc-precios-wrapper" style="margin-top: 3rem; text-align: center; padding: 2.5rem; background: var(--global-palette8); border: 1px dashed var(--global-palette6); border-radius: 0.75rem;">';
                echo '<p style="margin:0; color: var(--global-palette4); font-size: 1.1rem;">Para consultar los costos, planes de financiación y políticas de becas, por favor ponte en contacto con <a href="mailto:inscripciones@flacso.edu.uy" style="color: var(--global-palette1); font-weight: bold; text-decoration: none;">inscripciones@flacso.edu.uy</a>.</p>';
                echo '</div>';
            }
            
            // INFO IMPORTANTE (Reemplazo nativo)
            $mostrar_expedicion = false;
            if (isset($data['mostrar_expedicion_titulo']) && $data['mostrar_expedicion_titulo'] === '1') {
                $mostrar_expedicion = true;
            } elseif (isset($data['mostrar_expedicion_titulo']) && $data['mostrar_expedicion_titulo'] === '') {
                $mostrar_expedicion = ($is_maestria || $es_especializacion);
            } else {
                $mostrar_expedicion = ($is_maestria || $es_especializacion);
            }

            $mostrar_costos_envio = !empty($data['mostrar_costos_envio']);
            $mostrar_convenio_iin = !empty($data['convenio_iin_oea']);

            $info_id = 'gc-info-importante-' . wp_rand(1000, 9999);
            echo '<div id="' . esc_attr($info_id) . '" class="gc-info-importante-wrapper">';
            echo '<h2 class="gc-section-title">Información importante</h2>';
            echo '<div class="gc-info-list">';
            echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-currency-dollar"></i></div><div class="gc-info-text">Para pagos en <strong>dólares</strong>, las cuotas se mantendrán fijas durante toda la cursada.</div></div>';
            echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-currency-exchange"></i></div><div class="gc-info-text">Para pagos en <strong>pesos</strong>, las cuotas permanecerán fijas durante todo 2026, y a partir de 2027 se ajustarán en enero de cada año, por debajo de la inflación interanual.</div></div>';
            echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-calendar-check"></i></div><div class="gc-info-text">Las cuotas vencen el día <strong>15 de cada mes</strong>.</div></div>';
            
            if ($mostrar_expedicion) {
                echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-award"></i></div><div class="gc-info-text">El título se expide en el exterior, y tiene un costo asociado de <strong>USD 150</strong> que incluye el envío y el trámite de Apostilla de La Haya.</div></div>';
            }
            if ($mostrar_costos_envio) {
                echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-truck"></i></div><div class="gc-info-text"><strong>Los certificados tienen costos de envío al exterior:</strong><ul class="gc-envios-list"><li><span>USD 30</span> – envío a Argentina</li><li><span>USD 50</span> – envío al resto de América Latina</li><li><span>USD 70</span> – envío a Europa</li></ul></div></div>';
            }
            if ($mostrar_convenio_iin) {
                echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-globe"></i></div><div class="gc-info-text"><strong>Convenio IIN-OEA:</strong> Los funcionarios o referentes de los Estados Miembros que cuenten con aval institucional, acceden a bonificaciones especiales para cursar este programa.</div></div>';
            }

            echo '<div class="gc-info-list-item"><div class="gc-info-icon"><i class="bi bi-envelope"></i></div><div class="gc-info-text"><strong>Consultá por otros planes de financiación en</strong> <a href="mailto:inscripciones@flacso.edu.uy" class="gc-info-link">inscripciones@flacso.edu.uy</a></div></div>';
            echo '</div>'; 
            echo '<div class="gc-action-buttons"><a href="https://flacso.edu.uy/convenios/" target="_blank" class="gc-action-button"><i class="bi bi-file-earmark-text"></i>Ver convenios</a><a href="https://flacso.edu.uy/formas-de-pago/" target="_blank" class="gc-action-button"><i class="bi bi-credit-card"></i>Ver formas de pago</a></div>';
            echo '</div>';
            
            // MAS INFO FLACSO (Reemplazo nativo)
            $mas_info_id = 'gc-mas-info-' . wp_rand(1000, 9999);
            echo '<div id="' . esc_attr($mas_info_id) . '" class="gc-mas-info-wrapper">';
            echo '<h2 class="gc-section-title">Más Información</h2>';
            echo '<div class="gc-info-content">';
            echo '<div class="gc-info-item"><div class="gc-info-icon"><i class="bi bi-award"></i></div><div class="gc-info-text"><h3 class="gc-info-subtitle">Trayectoria Académica</h3><p>Nuestra <strong>Facultad de Posgrados</strong> busca formar a sus estudiantes a nivel <strong>académico, profesional y laboral</strong>. Nos distinguen <strong>19 años de trayectoria</strong> a nivel nacional y más de <strong>65 años a nivel internacional</strong>. Además, más de <strong>7000 personas egresadas</strong> de FLACSO Uruguay trabajan en el ámbito público y privado.</p></div></div>';
            echo '<div class="gc-info-item"><div class="gc-info-icon"><i class="bi bi-gear"></i></div><div class="gc-info-text"><h3 class="gc-info-subtitle">Gestión Académica</h3><p>Nos distingue un sistema de <strong>gestión académica eficiente y cercano</strong>, que acompaña de forma <strong>personalizada</strong> a cada estudiante y garantiza <strong>altos niveles de egreso, superiores al 90%</strong>.</p></div></div>';
            echo '<div class="gc-info-item"><div class="gc-info-icon"><i class="bi bi-currency-dollar"></i></div><div class="gc-info-text"><h3 class="gc-info-subtitle">Financiamiento Flexible</h3><p>Puedes abonar el posgrado en <strong>cuotas sin recargo</strong> a lo largo de la cursada. Contamos con <strong>múltiples convenios, descuentos de hasta el 25%</strong> y la posibilidad de acceder a <strong>becas</strong>. Intentamos contemplar cada caso de manera particular para que puedas comenzar y finalizar tu formación académica.</p></div></div>';
            echo '</div></div>';
            
            // ASISTENTE ACADÉMICO (Reemplazo nativo)
            if ($asistente_slug) {
                $asist_docente = get_page_by_path($asistente_slug, OBJECT, 'docente');
                if ($asist_docente) {
                    $asist_meta = get_post_meta($asist_docente->ID);
                    $asist_prefijo = !empty($asist_meta['prefijo_abrev'][0]) ? esc_html($asist_meta['prefijo_abrev'][0]) . ' ' : '';
                    $asist_nombre_completo = $asist_prefijo . esc_html(get_the_title($asist_docente->ID));
                    $asist_imagen_url = get_the_post_thumbnail_url($asist_docente->ID, 'medium');
                    
                    if (!function_exists('gc_determinante_del_o_de_la_nativo')) {
                        function gc_determinante_del_o_de_la_nativo($tit) {
                            $primera = strtolower(trim(strtok($tit, ' ')));
                            return in_array($primera, ['maestría','maestria','especialización','especializacion','licenciatura'], true) ? 'de la' : 'del';
                        }
                    }
                    $prep_posgrado = gc_determinante_del_o_de_la_nativo(get_the_title($post_id));
                    $enlace_correo = '<a href="mailto:' . esc_attr($asistente_correo) . '" style="color:var(--global-palette1);font-weight:600;text-decoration:none;">' . esc_html($asistente_correo) . '</a>';
                    
                    $presentacion_final = sprintf('Mi nombre es %s y soy %s %s %s, si tienes dudas o consultas puedes contactarme al mail %s', $asist_nombre_completo, esc_html($asistente_titulo), esc_html($prep_posgrado), esc_html(get_the_title($post_id)), $enlace_correo);
                    
                    echo '<div style="margin:3rem 0;background:var(--global-palette9);border:1px solid var(--global-palette7);border-top:6px solid var(--global-palette1);border-radius:0.75rem;padding:1.75rem;box-shadow:0 6px 18px rgba(15,26,45,.08);">';
                    echo '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:2rem;">';
                    echo '<div style="flex:0 0 auto;text-align:center;width:100%;max-width:200px;margin:0 auto;">';
                    if ($asist_imagen_url) {
                        echo '<img src="' . esc_url($asist_imagen_url) . '" alt="' . esc_attr($asist_nombre_completo) . '" style="width:160px;height:160px;object-fit:cover;border-radius:0.5rem;box-shadow:0 4px 12px rgba(15,26,45,.10);">';
                    } else {
                        echo '<div style="width:160px;height:160px;background:var(--global-palette1);color:var(--global-palette9);display:flex;align-items:center;justify-content:center;font-weight:700;border-radius:0.5rem;font-size:2.5rem;margin:0 auto;">' . strtoupper(substr($asist_nombre_completo,0,1)) . '</div>';
                    }
                    echo '</div>';
                    echo '<div style="flex:1;min-width:250px;">';
                    echo '<h3 style="color:var(--global-palette1);font-weight:800;font-size:1.8rem;margin:0 0 0.5rem 0;line-height:1.2;">' . $asist_nombre_completo . '</h3>';
                    echo '<span style="display:inline-block;padding:0.4rem 0.9rem;background:var(--global-palette7);color:var(--global-palette3);border-radius:0.375rem;font-weight:600;font-size:0.9rem;margin-bottom:1rem;">' . esc_html($asistente_titulo) . '</span>';
                    echo '<p style="color:var(--global-palette4);font-size:1.08rem;line-height:1.65;margin:0;">' . $presentacion_final . '</p>';
                    echo '</div></div></div>';
                }
            }
            
            // PREINSCRIPCIONES (Reemplazo nativo)
            $url_inscripcion = get_permalink($post_id) . 'preinscripcion';
            echo '<div style="margin:3rem 0;background:var(--global-palette8);border:1px solid var(--global-palette7);border-top:4px solid var(--global-palette1);border-radius:0.75rem;padding:2rem;text-align:center;box-shadow:0 8px 24px rgba(15,26,45,.08);">';
            echo '<h2 style="color:var(--global-palette1);font-weight:700;margin-bottom:1rem;font-size:1.75rem;">Formulario de Preinscripciones ' . esc_html($anio) . '</h2>';
            echo '<p style="color:var(--global-palette4);font-size:1.1rem;margin-bottom:1.5rem;">Comenzá el año cursando un posgrado en FLACSO Uruguay. <strong>Formación 100% a distancia</strong>.</p>';
            echo '<a href="' . esc_url($url_inscripcion) . '" style="display:inline-flex;align-items:center;gap:0.5rem;background:#27823b;color:white;padding:1rem 1.5rem;border-radius:0.5rem;font-weight:700;text-decoration:none;transition:all 0.2s;"><i class="bi bi-pencil-square"></i>Formulario de Preinscripción</a>';
            echo '</div>';
            
            // NOTA: Se eliminó explícitamente el shortcode de Breadcrumb por solicitud.
            ?>

        </div>
    </main>
</div>

<style>
.flacso-carta-virtual .carta-content-wrapper h2 {
    color: var(--global-palette1);
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
    font-weight: 800;
}
.flacso-carta-virtual .carta-content-wrapper h3 {
    color: var(--global-palette1);
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 700;
}
.flacso-carta-virtual .carta-content-wrapper ul {
    margin-left: 1.5rem;
    margin-bottom: 1.5rem;
}
.flacso-carta-virtual .carta-content-wrapper li {
    margin-bottom: 0.5rem;
}
</style>

<?php
get_footer();
