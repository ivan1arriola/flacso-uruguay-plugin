<?php

// ==================== SHORTCODE HERO DEL PROGRAMA (con banner INN-OEA dentro del bloque azul para id=12302) ====================
/**
 * USO: [programa_hero id="123" abreviacion="MDP" reconocido_mec="true" reconocimiento_internacional="true" anio="2026" cohorte="Cohorte 15" menciones_en="Gestión Educativa|Políticas Públicas" orientaciones="Profesional|Académica" mensaje_bienvenida=""]
 * Notas:
 *  - Bloque final de dos títulos intermedios (Maestría en Género) si id=12343.
 *  - Bloque final Diploma + Especialización (texto para MG) si id=12316.
 *  - Bloque final Diploma + Especialización también para IDs 12278, 14444, 12282 (diplomados de género).
 *  - Pastilla de convenio INN-OEA SOLO para id=12302 (adentro del div azul).
 */
add_shortcode('programa_hero', 'programa_hero_shortcode_mobile');
function programa_hero_shortcode_mobile($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'abreviacion' => '',
        'reconocido_mec' => 'false',
        'reconocimiento_internacional' => 'false',
        'anio' => '2026',
        'cohorte' => '',
        'menciones_en' => '',
        'orientaciones' => '',
        'mensaje_bienvenida' => 'Es un gusto darte la bienvenida y compartir información sobre nuestro posgrado.'
    ), $atts, 'programa_hero');

    if (empty($atts['id']) || empty($atts['abreviacion']) || empty($atts['cohorte'])) {
        return '<p style="color: var(--global-palette4); padding: 1rem; text-align: center; background: var(--global-palette8); border:1px solid var(--global-palette6); border-radius:.5rem">Error: Faltan parámetros obligatorios (id, abreviacion, cohorte)</p>';
    }

    $shortcode_id = 'gc-hero-mobile-' . wp_rand(1000, 9999);

    $pagina_data = function_exists('gc_obtener_datos_pagina') ? gc_obtener_datos_pagina($atts['id']) : array();
    $titulo = isset($pagina_data['titulo']) && !empty($pagina_data['titulo']) ? $pagina_data['titulo'] : get_the_title($atts['id']);

    $imagen = '';
    $imagen_id = get_post_thumbnail_id($atts['id']);
    if ($imagen_id) {
        $imagen_array = wp_get_attachment_image_src($imagen_id, 'large');
        if ($imagen_array && !empty($imagen_array[0])) { $imagen = $imagen_array[0]; }
    }

    $menciones = !empty($atts['menciones_en']) ? array_map('trim', explode('|', $atts['menciones_en'])) : array();
    $orientaciones = !empty($atts['orientaciones']) ? array_map('trim', explode('|', $atts['orientaciones'])) : array();

    $breadcrumb_items = function_exists('gc_obtener_breadcrumb_items') ? gc_obtener_breadcrumb_items(get_the_ID()) : array();

    // IDs de diplos de género que deben mostrar el bloque final tipo 12316
    $ids_diplos_genero = array(12278, 14444, 12282);

    // Mostrar pastilla INN-OEA solo si id=12302
    $mostrar_banner_convenio = (intval($atts['id']) === 12302);
    
    // Mostra titulo intermedio DEVNNA
    $mostrar_titulo_DEVNNA = (intval($atts['id']) === 12288);

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-hero-mobile-wrapper">
        <!-- BREADCRUMB -->
        <div class="gc-hero-breadcrumb-container">
            <nav aria-label="breadcrumb" class="gc-breadcrumb-nav">
                <ol class="breadcrumb">
                    <?php if (!empty($breadcrumb_items)) : foreach ($breadcrumb_items as $index => $item): ?>
                        <?php if ($index === count($breadcrumb_items) - 1): ?>
                            <li class="breadcrumb-item active" aria-current="page">Carta de Presentación</li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo esc_url($item['url']); ?>" class="gc-breadcrumb-link"><?php echo esc_html($item['title']); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; endif; ?>
                </ol>
            </nav>
        </div>

        <div class="gc-hero-mobile-section">
            <!-- INTRO UNIFICADA -->
            <div class="gc-intro-unificada" role="note" aria-label="Introducción">
                <div class="gc-intro-inner">
                    <?php if (!empty($atts['mensaje_bienvenida'])): ?>
                    <p class="gc-bienvenida"><span><?php echo esc_html($atts['mensaje_bienvenida']); ?></span></p>
                    <?php endif; ?>
                    <p class="gc-tagline"><em>Formación de excelencia, estés donde estés.</em></p>
                </div>
            </div>

            <!-- PASTILLA DE CONVENIO (solo id=12302, DENTRO DEL AZUL) -->
            <?php if ( $mostrar_banner_convenio ): ?>
            <div class="gc-convenio-inside">
                <div class="gc-convenio-pill">
                    <i class="bi bi-handshake" aria-hidden="true"></i>
                    <span>En convenio con el <strong>Instituto Interamericano de la Niña, el Niño y Adolescentes  (INN-OEA)</strong></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- CONTENIDO PRINCIPAL (zona azul) -->
            <div class="gc-hero-mobile-content">
                <?php if ($imagen): ?>
                <div class="gc-portada-mobile-container">
                    <div class="gc-image-mobile-wrapper">
                        <img src="<?php echo esc_url($imagen); ?>" alt="<?php echo esc_attr($titulo); ?>" class="gc-hero-mobile-image" loading="eager" decoding="async">
                    </div>
                </div>
                <?php endif; ?>

                <div class="gc-hero-mobile-text">
                    <div class="gc-title-mobile-container"><h1 class="gc-hero-mobile-title"><?php echo esc_html($titulo); ?></h1></div>

                    <!-- FILA 100%: abreviación + cohorte -->
                    <div class="gc-program-mobile-info" aria-label="Información del programa">
                        <?php if (!empty($atts['abreviacion'])): ?>
                        <span class="gc-program-mobile-abbreviation" aria-hidden="false"><?php echo esc_html($atts['abreviacion']); ?> <?php echo esc_html($atts['anio']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($atts['cohorte'])): ?>
                        <span class="gc-cohorte-mobile-badge" aria-hidden="false"><?php echo esc_html($atts['cohorte']); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($atts['reconocido_mec'] === 'true' || $atts['reconocimiento_internacional'] === 'true'): ?>
                    <!-- FILA 100%: certificaciones -->
                    <div class="gc-certificaciones-mobile-row">
                        <?php if ($atts['reconocido_mec'] === 'true'): ?>
                        <div class="gc-certificacion-mobile-item">
                            <i class="bi bi-award" aria-hidden="true"></i><span>Reconocida por el M.E.C</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($atts['reconocimiento_internacional'] === 'true'): ?>
                        <div class="gc-certificacion-mobile-item">
                            <i class="bi bi-globe" aria-hidden="true"></i><span>Titulación con apostillado de La Haya</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($menciones)): ?>
                    <!-- FILA 100%: menciones -->
                    <div class="gc-menciones-mobile-section">
                        <p class="gc-menciones-mobile-title">Con mención en:</p>
                        <div class="gc-menciones-mobile-grid">
                            <?php foreach ($menciones as $mencion): ?>
                                <div class="gc-mencion-mobile-item"><span><?php echo esc_html($mencion); ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($orientaciones)): ?>
                    <!-- FILA 100%: orientaciones -->
                    <div class="gc-orientaciones-mobile-section">
                        <p class="gc-orientaciones-mobile-title">Orientaciones metodológicas:</p>
                        <div class="gc-orientaciones-mobile-grid">
                            <?php foreach ($orientaciones as $orientacion): ?>
                                <div class="gc-orientacion-mobile-item"><span><?php echo esc_html($orientacion); ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ====== BLOQUES FINALES ====== -->
            <?php if (intval($atts['id']) === 12343): ?>
            <div class="gc-mg-info" aria-label="Títulos intermedios de la Maestría en Género">
                <div class="gc-mg-inner">
                    <p class="gc-mg-lead">Durante la cursada del programa se obtendrán <strong>dos títulos intermedios</strong>:</p>
                    <div class="gc-mg-chips" role="list">
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diploma en Género</span>
                                <span class="gc-mg-chip-sub">Tres meses de duración</span>
                            </div>
                        </div>
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diplomado de Especialización</span>
                                <span class="gc-mg-chip-sub">Un año de duración</span>
                            </div>
                        </div>
                    </div>
                    <p class="gc-mg-tesis">El título de <strong>Magíster</strong> será obtenido una vez se aprueben los créditos académicos y se defienda la tesis.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (intval($atts['id']) === 12316 || in_array(intval($atts['id']), $ids_diplos_genero, true)): ?>
            <div class="gc-mg-info" aria-label="Diploma y Especialización">
                <div class="gc-mg-inner">
                    <p class="gc-mg-lead">Durante la cursada del programa se obtendrá un título&nbsp;intermedio:</p>
                    <div class="gc-mg-chips" role="list">
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diploma en Género</span>
                                <span class="gc-mg-chip-sub">Tres meses de duración</span>
                            </div>
                        </div>
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diplomado de Especialización</span>
                                <span class="gc-mg-chip-sub">Un año de duración (se otorga al cumplir los requisitos del posgrado)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
                <?php if ($mostrar_titulo_DEVNNA): ?>
            <div class="gc-mg-info" aria-label="Diploma y Especialización">
                <div class="gc-mg-inner">
                    <p class="gc-mg-lead">Durante la cursada del programa se obtendrá un título&nbsp;intermedio:</p>
                    <div class="gc-mg-chips" role="list">
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diploma en Abordaje de las Violencias hacia las Infancias y Adolescencias</span>
                                <span class="gc-mg-chip-sub">Tres meses de duración</span>
                            </div>
                        </div>
                        <div class="gc-mg-chip" role="listitem">
                            <div class="gc-mg-chip-icon" aria-hidden="true"><i class="bi bi-mortarboard"></i></div>
                            <div class="gc-mg-chip-text">
                                <span class="gc-mg-chip-title">Diplomado de Especialización</span>
                                <span class="gc-mg-chip-sub">Un año de duración (se otorga al cumplir los requisitos del posgrado)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- ====== /BLOQUES FINALES ====== -->
        </div>
    </div>

    <style>
    /* ===== VARIABLES LOCALES ===== */
    #<?php echo esc_attr($shortcode_id); ?> { --hero-bg: var(--global-palette1); --tx-prim: var(--global-palette3); --tx-sec: var(--global-palette4); --chip-bg: var(--global-palette7); --chip-bd: var(--global-palette6); --chip-tx: var(--global-palette3); }

    /* Breadcrumb */
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-breadcrumb-container{margin-bottom:.75rem;padding:0 .5rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-nav{font-size:.875rem;font-family:var(--global-body-font-family)}
    #<?php echo esc_attr($shortcode_id); ?> .breadcrumb{padding:.75rem 1rem;margin:0;background:transparent;border:none;box-shadow:none}
    #<?php echo esc_attr($shortcode_id); ?> .breadcrumb-item{color:var(--global-palette5)}
    #<?php echo esc_attr($shortcode_id); ?> .breadcrumb-item+.breadcrumb-item::before{content:"/";color:var(--global-palette6);padding:0 .5rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-link{color:var(--global-palette1);text-decoration:none;font-weight:600}
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-link:hover{text-decoration:underline}

    /* HERO */
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-section{background:var(--global-palette1);color:var(--global-palette9);border-radius:.875rem;box-shadow:0 8px 28px rgba(15,26,45,.22);overflow:hidden;margin:.5rem;font-family:var(--global-body-font-family)}

    /* INTRO */
    #<?php echo esc_attr($shortcode_id); ?> .gc-intro-unificada{background:linear-gradient(180deg,var(--global-palette8) 0%, var(--global-palette7) 100%);border-top:1px solid var(--global-palette6);border-bottom:1px solid var(--global-palette6)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-intro-inner{max-width:var(--global-content-width);margin:0 auto;padding:1rem var(--global-content-edge-padding);display:flex;flex-direction:column;gap:.35rem;align-items:center;justify-content:center}
    #<?php echo esc_attr($shortcode_id); ?> .gc-bienvenida{margin:0;font-weight:800;color:var(--tx-prim);font-size:1.2rem;text-align:center;letter-spacing:.2px}
    #<?php echo esc_attr($shortcode_id); ?> .gc-tagline{margin:0;color:var(--tx-sec);font-weight:600;text-align:center}
    /* PASTILLA DE CONVENIO (adentro del azul) */
    #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-inside{
      display:flex; justify-content:center;
      background:var(--global-palette1);
      padding:.6rem 1rem 0;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-pill{
      display:inline-flex; align-items:center; gap:.55rem;
      background: var(--global-palette2, #fed222);
      color: var(--global-palette1, #1d3a72);
      padding:.55rem .95rem;
      border-radius:9999px; border:1px solid rgba(15,26,45,.12);
      box-shadow:0 2px 10px rgba(15,26,45,.18);
      font-weight:600; line-height:1.25; text-transform:none;
      max-width:92%; white-space:normal; text-align:center;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-pill i{font-size:1rem; color:var(--global-palette1,#1d3a72)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-pill strong{font-weight:800}

    /* CONTENIDO (zona azul) */
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-content{padding:1.25rem 1rem 1.5rem;display:flex;flex-direction:column;gap:1.5rem}

    /* IMAGEN */
    #<?php echo esc_attr($shortcode_id); ?> .gc-portada-mobile-container{display:flex;justify-content:center;order:1;width:100%}
    #<?php echo esc_attr($shortcode_id); ?> .gc-image-mobile-wrapper{position:relative;border-radius:.875rem;overflow:hidden;box-shadow:0 12px 32px rgba(0,0,0,.35);width:100%;max-width:300px;height:300px;border:3px solid var(--global-palette6)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-image{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}

    /* TÍTULO Y CHIPS */
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-text{text-align:center;display:flex;flex-direction:column;gap:1.25rem;order:2}
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-title{margin:0;line-height:1.1;color:var(--global-palette9);font-size:1.9rem;font-family:var(--global-heading-font-family);letter-spacing:-.5px}

    #<?php echo esc_attr($shortcode_id); ?> .gc-program-mobile-info{display:grid;grid-template-columns:1fr;gap:.75rem;width:100%}
    #<?php echo esc_attr($shortcode_id); ?> .gc-program-mobile-abbreviation,
    #<?php echo esc_attr($shortcode_id); ?> .gc-cohorte-mobile-badge{color:var(--chip-tx);background:var(--chip-bg);padding:.9rem 1.25rem;border-radius:.65rem;border:1px solid var(--chip-bd);font-weight:700;font-size:1rem;text-align:center;width:100%;box-sizing:border-box;box-shadow:0 2px 8px rgba(15,26,45,.08);pointer-events:none;user-select:none}

    #<?php echo esc_attr($shortcode_id); ?> .gc-certificaciones-mobile-row{display:grid;grid-template-columns:1fr;gap:.75rem;width:100%}
    #<?php echo esc_attr($shortcode_id); ?> .gc-certificacion-mobile-item{background:var(--chip-bg);color:var(--chip-tx);padding:1rem;border-radius:.65rem;border:1px solid var(--chip-bd);font-weight:600;text-align:center;display:flex;align-items:center;justify-content:center;gap:.6rem;font-size:.95rem;width:100%;box-sizing:border-box;pointer-events:none;user-select:none}
    #<?php echo esc_attr($shortcode_id); ?> .gc-certificacion-mobile-item i{color:var(--global-palette1);font-size:1.05rem}

    #<?php echo esc_attr($shortcode_id); ?> .gc-menciones-mobile-section,
    #<?php echo esc_attr($shortcode_id); ?> .gc-orientaciones-mobile-section{padding:1rem 0;border-top:1px solid rgba(255,255,255,.15);width:100%}
    #<?php echo esc_attr($shortcode_id); ?> .gc-menciones-mobile-title,
    #<?php echo esc_attr($shortcode_id); ?> .gc-orientaciones-mobile-title{font-weight:700;margin-bottom:1rem;text-align:center;font-size:1.05rem;color:var(--global-palette9)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-menciones-mobile-grid,
    #<?php echo esc_attr($shortcode_id); ?> .gc-orientaciones-mobile-grid{display:grid;grid-template-columns:1fr;gap:.75rem;width:100%}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mencion-mobile-item,
    #<?php echo esc_attr($shortcode_id); ?> .gc-orientacion-mobile-item{width:100%;color:var(--chip-tx);padding:1rem;border-radius:.65rem;font-weight:600;text-align:center;display:flex;align-items:center;justify-content:center;gap:0;background:var(--chip-bg);border:1px solid var(--chip-bd);box-sizing:border-box;pointer-events:none;user-select:none}

    /* BLOQUES FINALES */
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-info{background:var(--global-palette1);padding:1.5rem 0;border-top:1px solid rgba(0,0,0,.12)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-inner{max-width:var(--global-content-width);margin:0 auto;padding:0 var(--global-content-edge-padding)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-lead{margin:0 0 1rem 0;color:var(--global-palette9);font-weight:800;text-align:center}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chips{display:grid;grid-template-columns:1fr;gap:1rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip{display:flex;align-items:center;gap:.75rem;background:var(--global-palette8);color:var(--chip-tx);border:1px solid var(--chip-bd);padding:1rem 1.25rem;border-radius:14px;box-shadow:0 1px 6px rgba(15,26,45,.10);width:100%;justify-content:center}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--global-palette7);border:1px solid var(--chip-bd)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip-icon i{color:var(--global-palette1);font-size:1.05rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip-text{display:flex;flex-direction:column;align-items:flex-start}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip-title{font-weight:800;line-height:1.25;color:var(--chip-tx)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chip-sub{font-weight:600;line-height:1.25;color:var(--tx-sec)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mg-tesis{margin:1rem 0 0 0;color:var(--global-palette9);font-weight:600;text-align:center}

    /* TABLET */
    @media (min-width:768px){
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-breadcrumb-container{margin-bottom:1rem;padding:0 1rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-section{margin:1rem;border-radius:1rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-intro-inner{padding:1.25rem 2rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-bienvenida{font-size:1.35rem}

      #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-inside{padding:.8rem 2rem 0}
      #<?php echo esc_attr($shortcode_id); ?> .gc-convenio-pill{max-width:var(--global-content-width);}

      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-content{padding:2rem;gap:2rem;flex-direction:row;align-items:flex-start;text-align:left}
      #<?php echo esc_attr($shortcode_id); ?> .gc-portada-mobile-container{order:1;flex:0 0 40%;display:flex;justify-content:center}
      #<?php echo esc_attr($shortcode_id); ?> .gc-image-mobile-wrapper{width:100%;max-width:320px;height:320px}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-text{order:2;text-align:left;flex:1;gap:1.5rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-title{font-size:2.25rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-program-mobile-info{grid-template-columns:1fr 1fr}
      #<?php echo esc_attr($shortcode_id); ?> .gc-certificaciones-mobile-row{grid-template-columns:1fr 1fr}
      #<?php echo esc_attr($shortcode_id); ?> .gc-menciones-mobile-grid,#<?php echo esc_attr($shortcode_id); ?> .gc-orientaciones-mobile-grid{grid-template-columns:repeat(2, 1fr)}
      #<?php echo esc_attr($shortcode_id); ?> .gc-mg-chips{grid-template-columns:1fr 1fr}
    }

    /* DESKTOP */
    @media (min-width:1024px){
      #<?php echo esc_attr($shortcode_id); ?> .breadcrumb{padding:.75rem 1rem;max-width:var(--global-content-width);margin:0 auto}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-section{margin:1rem auto;max-width:var(--global-content-width);border-radius:1.125rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-content{padding:2.5rem;gap:2.5rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-portada-mobile-container{flex:0 0 38%}
      #<?php echo esc_attr($shortcode_id); ?> .gc-image-mobile-wrapper{max-width:340px;height:340px}
      #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-title{font-size:2.5rem}
      #<?php echo esc_attr($shortcode_id); ?> .gc-menciones-mobile-grid,#<?php echo esc_attr($shortcode_id); ?> .gc-orientaciones-mobile-grid{grid-template-columns:repeat(2, 1fr)}
    }

    /* Accesibilidad */
    #<?php echo esc_attr($shortcode_id); ?> .gc-hero-mobile-title:focus,#<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-link:focus{outline:2px solid var(--chip-bd);outline-offset:2px}
    #<?php echo esc_attr($shortcode_id); ?> .gc-mencion-mobile-item, #<?php echo esc_attr($shortcode_id); ?> .gc-orientacion-mobile-item { gap: 0; }
    </style>
    <script>
    (function() {
        if (typeof window.fbq !== 'function') {
            return;
        }
        try {
            window.fbq('track', 'ViewContent', {
                content_name: <?php echo wp_json_encode((string) $titulo); ?>,
                content_ids: [<?php echo (int) $atts['id']; ?>],
                content_type: 'oferta_academica'
            });
        } catch (e) {
            if (window.console && typeof window.console.warn === 'function') {
                console.warn('[Shortcode Hero] Error enviando ViewContent:', e);
            }
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
// ==================== FUNCIÓN AUXILIAR PARA OBTENER ITEMS DEL BREADCRUMB ====================
/**
 * Obtiene los items del breadcrumb para una página
 * 
 * @param int $page_id ID de la página ACTUAL
 * @return array Array de items con 'title' y 'url'
 */
function gc_obtener_breadcrumb_items($page_id) {
    $items = array();

    // Agregar la página de inicio
    $items[] = array(
        'title' => 'Inicio',
        'url' => home_url('/')
    );

    // Obtener los ancestros (páginas padre) de la página ACTUAL
    $ancestors = get_post_ancestors($page_id);
    
    if ($ancestors) {
        // Invertir el array para tener el orden correcto (de abajo hacia arriba)
        $ancestors = array_reverse($ancestors);
        
        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_post($ancestor_id);
            if ($ancestor) {
                $items[] = array(
                    'title' => $ancestor->post_title,
                    'url' => get_permalink($ancestor_id)
                );
            }
        }
    }
    
    // Agregar la página actual (sin URL) - El título será reemplazado por "Carta de Presentación" en el template
    $pagina_actual = get_post($page_id);
    if ($pagina_actual) {
        $items[] = array(
            'title' => $pagina_actual->post_title,
            'url' => ''
        );
    }
    
    return $items;
}
// ==================== SHORTCODE GRID DE INFO CLAVE ====================
/**
 * USO: [programa_info_clave proximo_inicio="Marzo 2026" duracion="30 meses"]
 * 
 * ATRIBUTOS:
 * - proximo_inicio: Fecha de próximo inicio
 * - duracion: Duración del programa
 * - modalidad_cursada: ELIMINADO - Ahora está hardcodeado como "Virtual con clases sincrónicas"
 */
add_shortcode('programa_info_clave', 'programa_info_clave_shortcode');
function programa_info_clave_shortcode($atts) {
    $atts = shortcode_atts(array(
        'proximo_inicio' => 'Marzo 2026',
        'duracion' => '30 meses'
    ), $atts, 'programa_info_clave');
    
    $shortcode_id = 'gc-info-clave-' . wp_rand(1000, 9999);
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-info-clave-wrapper">
        <div class="gc-info-grid">
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div class="gc-info-content">
                    <h3 class="gc-info-title">Próximo inicio</h3>
                    <p class="gc-info-value"><?php echo esc_html($atts['proximo_inicio']); ?></p>
                </div>
            </div>
            
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="gc-info-content">
                    <h3 class="gc-info-title">Duración</h3>
                    <p class="gc-info-value"><?php echo esc_html($atts['duracion']); ?></p>
                </div>
            </div>
            
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-laptop"></i>
                </div>
                <div class="gc-info-content">
                    <h3 class="gc-info-title">Modalidad</h3>
                    <p class="gc-info-value">Virtual con clases sincrónicas</p>
                </div>
            </div>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> {
        margin: 1.5rem 0;
        padding: 0 0.5rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        align-items: stretch;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
        background: var(--global-palette9);
        border: 2px solid var(--global-palette7);
        border-radius: 1rem;
        padding: 1.5rem 1rem;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 140px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        position: relative;
        overflow: hidden;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-item:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--global-palette1), var(--global-palette3));
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(29, 58, 114, 0.15);
        border-color: var(--global-palette1);
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        background: var(--global-palette7);
        border-radius: 50%;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
        font-size: 1.5rem;
        color: var(--global-palette1);
        line-height: 1;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 100%;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-title {
        color: var(--global-palette1);
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
        line-height: 1.3;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-value {
        color: var(--global-palette3);
        margin: 0;
        line-height: 1.4;
        font-size: 0.95rem;
        font-weight: 500;
    }

    @media only screen and (min-width: 640px) {
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 2rem 0;
            padding: 0 1rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-info-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
            padding: 1.75rem 1.25rem;
            min-height: 160px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
            margin-bottom: 1rem;
            width: 70px;
            height: 70px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
            font-size: 1.75rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-title {
            font-size: 1.05rem;
            margin-bottom: 0.75rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-value {
            font-size: 1rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item:nth-child(3) {
            grid-column: 1 / -1;
            justify-self: center;
            max-width: 400px;
            min-height: 150px;
        }
    }

    @media only screen and (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 2.5rem 0;
            padding: 0;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-info-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
            padding: 2rem 1.5rem;
            min-height: 180px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item:nth-child(3) {
            grid-column: auto;
            justify-self: stretch;
            max-width: none;
            min-height: 180px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
            margin-bottom: 1.2rem;
            width: 80px;
            height: 80px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
            font-size: 2rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-title {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-value {
            font-size: 1.05rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(29, 58, 114, 0.2);
        }
    }

    @media only screen and (min-width: 1280px) {
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-grid {
            gap: 2rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
            padding: 2.5rem 2rem;
            min-height: 200px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
            width: 90px;
            height: 90px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
            font-size: 2.25rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-title {
            font-size: 1.15rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-value {
            font-size: 1.1rem;
        }
    }

    @media only screen and (max-width: 360px) {
        #<?php echo esc_attr($shortcode_id); ?> {
            padding: 0 0.25rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-grid {
            gap: 0.75rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
            padding: 1.25rem 0.75rem;
            min-height: 130px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
            margin-bottom: 0.5rem;
            width: 50px;
            height: 50px;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
            font-size: 1.25rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-title {
            font-size: 0.95rem;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-value {
            font-size: 0.9rem;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}

// ==================== SHORTCODE INFORMACIÓN IMPORTANTE - DISEÑO UNIFICADO (sin atributos) ====================
/**
 * USO: [programa_info_importante]
 */
add_shortcode('programa_info_importante', 'programa_info_importante_shortcode');
function programa_info_importante_shortcode($atts = array()) {
    $APOSTILLADOS = array(12330, 12336, 12343, 12310, 12316);

    $DIPLOMAS_DIPLOMADOS = array(12278, 14444, 12282, 12288, 13202, 12295, 12299, 20668, 12302, 14657, 12304, 13185);

    $current_id = intval(get_the_ID());
    $parent_id  = intval(wp_get_post_parent_id($current_id));
    $context_id = $parent_id > 0 ? $parent_id : $current_id;

    $show_expedicion = in_array($context_id, $APOSTILLADOS, true);
    $show_envios     = in_array($context_id, $DIPLOMAS_DIPLOMADOS, true);

    $shortcode_id = 'gc-info-importante-' . wp_rand(1000, 9999);

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-info-importante-wrapper">
        <h2 class="gc-section-title">Información importante</h2>

        <div class="gc-info-list">
            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="gc-info-text">
                    Para pagos en <strong>dólares</strong>, las cuotas se mantendrán fijas durante toda la cursada.
                </div>
            </div>
            
            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-currency-exchange"></i>
                </div>
                <div class="gc-info-text">
                    Para pagos en <strong>pesos</strong>, las cuotas permanecerán fijas durante todo 2026, y a partir de 2027 se ajustarán 
                    en enero de cada año, por debajo de la inflación interanual.
                </div>
            </div>
            
            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="gc-info-text">
                    Las cuotas vencen el día <strong>15 de cada mes</strong>.
                </div>
            </div>

            <?php if ($show_expedicion): ?>
            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-award"></i>
                </div>
                <div class="gc-info-text">
                    El título se expide en el exterior, y tiene un costo asociado de <strong>USD 150</strong> que incluye el envío y el trámite de Apostilla de La Haya.
                </div>
            </div>
            <?php endif; ?>

            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-envelope"></i>
                </div>
                <div class="gc-info-text">
                    <strong>Consultá por otros planes de financiación en</strong> 
                    <a href="mailto:inscripciones@flacso.edu.uy" class="gc-info-link">inscripciones@flacso.edu.uy</a>
                </div>
            </div>

            <?php if ($show_envios): ?>
            <div class="gc-info-list-item">
                <div class="gc-info-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="gc-info-text">
                    <strong>Los certificados tienen costos de envío al exterior:</strong>
                    <ul class="gc-envios-list">
                        <li><span>USD 30</span>  envío a Argentina</li>
                        <li><span>USD 50</span>  envío al resto de América Latina</li>
                        <li><span>USD 70</span>  envío a Europa</li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="gc-action-buttons">
            <a href="https://flacso.edu.uy/convenios/" target="_blank" class="gc-action-button">
                <i class="bi bi-file-earmark-text"></i>
                Ver convenios
            </a>
            
            <a href="https://flacso.edu.uy/formas-de-pago/" target="_blank" class="gc-action-button">
                <i class="bi bi-credit-card"></i>
                Ver formas de pago
            </a>
        </div>
    </div>
    <style>
    #<?php echo esc_attr($shortcode_id); ?> { margin: 2rem 0; padding: 0 1rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-list-item { display: flex; align-items: flex-start; gap: 1rem; background: var(--global-palette8); border-radius: 0.5rem; padding: .875rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon { width: 40px; height: 40px; background: var(--global-palette1); color: var(--global-palette9); flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 0.25rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i { font-size: 1.1rem; line-height: 1; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-text { color: var(--global-palette4); line-height: 1.6; margin: 0; flex: 1; font-size: 1rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-text strong { color: var(--global-palette1); font-weight: 700; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-link { font-weight: 600; text-decoration: none; color: var(--global-palette1); border-bottom: 1px solid transparent; transition: all 0.3s ease; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-link:hover { border-bottom-color: var(--global-palette1); }

    #<?php echo esc_attr($shortcode_id); ?> .gc-envios-list { list-style: disc; margin: .5rem 0 0 1.25rem; padding: 0; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-envios-list li { margin: .15rem 0; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-envios-list li span { font-weight: 700; color: var(--global-palette1); }

    #<?php echo esc_attr($shortcode_id); ?> .gc-action-buttons { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: var(--global-palette-btn-bg); color: var(--global-palette9); padding: 1rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; border: 2px solid var(--global-palette-btn-bg); text-align: center; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button:hover { background: transparent; color: var(--global-palette-btn-bg-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(29, 58, 114, 0.2); }

    @media (min-width: 768px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 2.5rem 0; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { text-align: left; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-action-buttons { grid-template-columns: 1fr 1fr; }
    }

    @media (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 3rem 0; padding: 0; }
    }
    </style>
    <?php
    return ob_get_clean();
}
// ==================== SHORTCODE REQUISITOS ADMISIÓN - ESTILO UNIFICADO (detección por página padre) ====================
/**
 * USO: [requisitos_admision]
 */
add_shortcode('requisitos_admision', 'requisitos_admision_shortcode');
function requisitos_admision_shortcode($atts = array()) {
    $MAESTRIAS_PARENT_IDS = array(12330, 12336, 12343);

    $current_id = intval(get_the_ID());
    $parent_id  = intval(wp_get_post_parent_id($current_id));
    $es_maestria = $parent_id && in_array($parent_id, $MAESTRIAS_PARENT_IDS, true);

    $tipo = $es_maestria ? 'maestria' : 'postgrado';

    $shortcode_id = 'gc-requisitos-' . $tipo . '-' . wp_rand(1000, 9999);

    $requisitos = [
        'postgrado' => [
            'titulo' => 'Requisitos de postulación y admisión',
            'items' => [
                [
                    'icono' => 'bi-pencil-square',
                    'texto' => 'Completar el formulario de preinscripción'
                ],
                [
                    'icono' => 'bi-paperclip',
                    'texto' => 'Añadir en el formulario, de forma escaneada:',
                    'subitems' => [
                        'Documento de identidad vigente',
                        'Documento que acredite estudios universitarios y/o terciarios no universitarios de 4 años o más de duración, con copia legalizada por las oficinas competentes (consulte en el caso de no contar con título de grado)',
                        'Carta que exprese la motivación'
                    ]
                ],
                [
                    'icono' => 'bi-credit-card',
                    'texto' => 'Abonar los aranceles correspondientes'
                ],
                [
                    'icono' => 'bi-award',
                    'texto' => 'La admisión está sujeta a cupos y selección académica'
                ]
            ]
        ],
        'maestria' => [
            'titulo' => 'Requisitos de Postulación y Admisión',
            'items' => [
                [
                    'icono' => 'bi-pencil-square',
                    'texto' => 'Completar el formulario de preinscripción'
                ],
                [
                    'icono' => 'bi-paperclip',
                    'texto' => 'Añadir en el formulario, de forma escaneada:',
                    'subitems' => [
                        'Documento que acredite estudios universitarios y/o terciarios no universitarios de 4 años o más de duración con copia legalizada por las oficinas competentes',
                        'Documento de identidad vigente',
                        'Curriculum Vitae',
                        'Carta de motivación que exprese las expectativas',
                        'Dos cartas de referencia sobre su desempeño académico o profesional. Las cartas deben proceder de personas académicas o responsables de política, planeamiento, investigación o asistencia técnica que hayan tenido contacto directo con quien se candidatea'
                    ]
                ],
                [
                    'icono' => 'bi-people',
                    'texto' => 'Asistir a una entrevista de admisión con la coordinación académica'
                ],
                [
                    'icono' => 'bi-credit-card',
                    'texto' => 'Abonar los aranceles correspondientes'
                ]
            ]
        ]
    ];

    $config = $requisitos[$tipo];

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-requisitos-wrapper">
        <h2 class="gc-section-title gc-h2-override"><?php echo esc_html($config['titulo']); ?></h2>

        <div class="gc-requisitos-list">
            <?php foreach ($config['items'] as $item): ?>
            <div class="gc-requisitos-list-item">
                <div class="gc-requisitos-icon">
                    <i class="<?php echo esc_attr($item['icono']); ?>"></i>
                </div>
                <div class="gc-requisitos-text">
                    <?php if (isset($item['subitems']) && !empty($item['subitems'])): ?>
                        <div class="gc-requisitos-main-text"><?php echo esc_html($item['texto']); ?></div>
                        <div class="gc-requisitos-subitems">
                            <?php foreach ($item['subitems'] as $subitem): ?>
                            <div class="gc-requisitos-subitem">
                                <span class="gc-subitem-bullet"></span>
                                <span class="gc-subitem-text"><?php echo esc_html($subitem); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php echo esc_html($item['texto']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="gc-important-notice">
            <div class="gc-important-content">
                <h3 class="gc-important-title">Atención</h3>
                <p class="gc-important-text">
                    Todos los documentos deben adjuntarse en el formulario de preinscripción.<br>
                    El proceso de admisión está sujeto a cupos y selección académica.
                </p>
            </div>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> { margin: 2rem 0; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { font-weight: 700; margin: 0 0 1.5rem 0; color: var(--global-palette1); font-size: 1.75rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-list-item { display: flex; align-items: flex-start; gap: 1rem; background: var(--global-palette8); border-radius: 0.5rem; padding: .875rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-icon { width: 40px; height: 40px; background: var(--global-palette1); color: var(--global-palette9); flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 0.25rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-icon i { font-size: 1.1rem; line-height: 1; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-text { color: var(--global-palette4); line-height: 1.6; margin: 0; flex: 1; font-size: 1rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-main-text { margin-bottom: 0.75rem; font-weight: 500; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-subitems { display: flex; flex-direction: column; gap: 0.5rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-requisitos-subitem { display: flex; align-items: flex-start; gap: 0.5rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-subitem-bullet { color: var(--global-palette1); font-weight: bold; flex-shrink: 0; margin-top: 0.125rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-subitem-text { color: var(--global-palette4); line-height: 1.5; flex: 1; font-size: 1rem; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-important-notice { background: var(--global-palette1); border-radius: 0.5rem; padding: 1.5rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-important-title { color: var(--global-palette9); font-weight: 700; margin: 0 0 0.5rem 0; font-size: 1.1rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-important-text { color: var(--global-palette9); margin: 0; line-height: 1.5; opacity: 0.9; font-size: 1rem; }

    @media (min-width: 768px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 2.5rem 0; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { text-align: left; }
    }

    @media (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 3rem 0; padding: 0; }
    }
    </style>
    <?php
    return ob_get_clean();
}
// ==================== SHORTCODE MÁS INFORMACIÓN FLACSO - SIN CARDS ====================
add_shortcode('mas_info_flacso', 'mas_info_flacso_shortcode');
function mas_info_flacso_shortcode($atts) {
    $shortcode_id = 'gc-mas-info-' . wp_rand(1000, 9999);
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-mas-info-wrapper">
        <h2 class="gc-section-title">Más Información</h2>
        
        <div class="gc-info-content">
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-award"></i>
                </div>
                <div class="gc-info-text">
                    <h3 class="gc-info-subtitle">Trayectoria Académica</h3>
                    <p>Nuestra <strong>Facultad de Posgrados</strong> busca formar a sus estudiantes a nivel <strong>académico, profesional y laboral</strong>. Nos distinguen <strong>19 años de trayectoria</strong> a nivel nacional y más de <strong>65 años a nivel internacional</strong>. Además, más de <strong>7000 personas egresadas</strong> de FLACSO Uruguay trabajan en el ámbito público y privado.</p>
                </div>
            </div>
            
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-gear"></i>
                </div>
                <div class="gc-info-text">
                    <h3 class="gc-info-subtitle">Gestión Académica</h3>
                    <p>Nos distingue un sistema de <strong>gestión académica eficiente y cercano</strong>, que acompaña de forma <strong>personalizada</strong> a cada estudiante y garantiza <strong>altos niveles de egreso, superiores al 90%</strong>.</p>
                </div>
            </div>
            
            <div class="gc-info-item">
                <div class="gc-info-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="gc-info-text">
                    <h3 class="gc-info-subtitle">Financiamiento Flexible</h3>
                    <p>Puedes abonar el posgrado en <strong>cuotas sin recargo</strong> a lo largo de la cursada. Contamos con <strong>múltiples convenios, descuentos de hasta el 25%</strong> y la posibilidad de acceder a <strong>becas</strong>. Intentamos contemplar cada caso de manera particular para que puedas comenzar y finalizar tu formación académica.</p>
                </div>
            </div>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> {
        margin: 2rem 0;
        padding: 0 1rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-section-title {
        font-weight: 700;
        margin: 0 0 1.5rem 0;
        text-align: center;
        color: var(--global-palette1);
        font-size: 1.75rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-content {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        background: var(--global-palette8);
        border-radius: 0.5rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon {
        width: 50px;
        height: 50px;
        background: var(--global-palette1);
        color: var(--global-palette9);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-icon i {
        font-size: 1.25rem;
        line-height: 1;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-text {
        flex: 1;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-subtitle {
        color: var(--global-palette1);
        font-weight: 700;
        margin: 0 0 0.75rem 0;
        font-size: 1.25rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-text p {
        color: var(--global-palette4);
        line-height: 1.6;
        margin: 0;
        font-size: 1rem;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-text strong {
        color: var(--global-palette1);
        font-weight: 700;
    }

    @media only screen and (min-width: 768px) {
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 2.5rem 0;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title {
            text-align: left;
        }
        
        #<?php echo esc_attr($shortcode_id); ?> .gc-info-item {
            gap: 1.5rem;
        }
    }

    @media only screen and (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 3rem 0;
            padding: 0;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}
// ==================== SHORTCODE ASISTENTE ACADÉMICO ====================
add_shortcode('asistente_academico', 'asistente_academico_shortcode');
function asistente_academico_shortcode($atts) {
    $atts = shortcode_atts(array(
        'slug'   => '',
        'correo' => 'inscripciones@flacso.edu.uy',
        'titulo' => 'Asistente Académico/a'
    ), $atts, 'asistente_academico');

    if (empty($atts['slug'])) {
        if (current_user_can('edit_posts')) {
            return '<div class="notice notice-error"><p><strong>[asistente_academico]</strong>: El atributo <code>slug</code> es obligatorio.</p></div>';
        }
        return '';
    }

    if (!function_exists('gc_determinante_del_o_de_la')) {
        function gc_determinante_del_o_de_la($titulo) {
            $primera = strtolower(trim(strtok($titulo, ' ')));
            $femeninas = array('maestría','maestria','especialización','especializacion','licenciatura');
            return in_array($primera, $femeninas, true) ? 'de la' : 'del';
        }
    }

    $shortcode_id = 'gc-asistente-' . wp_rand(1000, 9999);
    $slug   = sanitize_title($atts['slug']);
    $correo = sanitize_email($atts['correo']);
    $docente = get_page_by_path($slug, OBJECT, 'docente');

    if (!$docente) {
        if (current_user_can('edit_posts')) {
            return '<div class="notice notice-warning"><p><strong>[asistente_academico]</strong>: No encontré ningún docente con el slug <code>' . esc_html($slug) . '</code>.</p></div>';
        }
        return '';
    }

    $docente_id = $docente->ID;
    $meta = get_post_meta($docente_id);
    $prefijo = !empty($meta['prefijo_abrev'][0]) ? esc_html($meta['prefijo_abrev'][0]) . ' ' : '';
    $nombre_completo = $prefijo . esc_html(get_the_title($docente_id));
    $nombre_comun = esc_html(get_the_title($docente_id));

    global $post;
    $programa_nombre = '';
    if ($post instanceof WP_Post) {
        $padre_id = intval($post->post_parent);
        if ($padre_id > 0) {
            $programa_nombre = get_the_title($padre_id);
        } else {
            $programa_nombre = get_the_title($post->ID);
        }
    }
    if (empty($programa_nombre)) {
        $programa_nombre = __('el programa', 'flacso');
    }

    $prep_posgrado = gc_determinante_del_o_de_la($programa_nombre);

    $cv_html = !empty($meta['cv'][0]) ? wp_kses_post($meta['cv'][0]) : '';
    $formacion_academica = !empty($meta['prefijo_full'][0]) ? esc_html($meta['prefijo_full'][0]) : '';

    $correo_antispam = antispambot($correo);
    $mailto = 'mailto:' . $correo_antispam;
    $enlace_correo = '<a href="' . esc_url($mailto) . '" class="gc-correo-enlace">' . esc_html($correo_antispam) . '</a>';

    $presentacion_final = sprintf(
        'Mi nombre es %1$s y soy %2$s %3$s %4$s, si tienes dudas o consultas puedes contactarme al mail %5$s',
        $nombre_comun,
        esc_html($atts['titulo']),
        esc_html($prep_posgrado),
        esc_html($programa_nombre),
        $enlace_correo
    );

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-asistente-wrapper">
        <div class="gc-asistente-compacto">
            <div class="container">
                <div class="row align-items-start">
                    <div class="col-lg-4 col-md-5 col-12 mb-4 mb-lg-0">
                        <div class="gc-compacto-avatar">
                            <?php
                            $imagen_url = get_the_post_thumbnail_url($docente_id, 'medium');
                            if ($imagen_url) {
                                echo '<img src="' . esc_url($imagen_url) . '" alt="' . esc_attr($nombre_completo) . '" class="gc-compacto-image">';
                            } else {
                                $partes = explode(' ', trim(get_the_title($docente_id)));
                                $iniciales = '';
                                foreach ($partes as $p) { $iniciales .= strtoupper(mb_substr($p, 0, 1)); }
                                echo '<div class="gc-compacto-iniciales">' . esc_html($iniciales) . '</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="col-lg-8 col-md-7 col-12">
                        <div class="gc-compacto-info">
                            <div class="gc-compacto-titulo mb-3">
                                <h3 class="gc-compacto-nombre mb-2"><?php echo $nombre_completo; ?></h3>
                                <span class="gc-compacto-rol"><?php echo esc_html($atts['titulo']); ?></span>
                            </div>

                            <div class="gc-compacto-mensaje mb-4"><?php echo $presentacion_final; ?></div>

                            <?php if (!empty($cv_html) || !empty($formacion_academica)) : ?>
                            <div class="gc-compacto-acciones">
                                <button type="button" class="gc-action-button gc-cv-toggle" aria-expanded="false">
                                    <i class="bi bi-file-earmark-person me-2" aria-hidden="true"></i>
                                    <span class="gc-cv-texto">Ver perfil</span>
                                    <i class="bi bi-chevron-down gc-cv-chevron ms-2" aria-hidden="true"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($cv_html) || !empty($formacion_academica)) : ?>
            <div class="gc-cv-contenido" hidden>
                <div class="container">
                    <?php if (!empty($formacion_academica)) : ?>
                    <section class="gc-info-card">
                        <header class="gc-info-card-header">
                            <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                            <h4>Formación Académica</h4>
                        </header>
                        <div class="gc-info-card-body">
                            <p class="mb-0"><?php echo esc_html($formacion_academica); ?></p>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($cv_html)) : ?>
                    <section class="gc-info-card">
                        <header class="gc-info-card-header">
                            <i class="bi bi-briefcase-fill" aria-hidden="true"></i>
                            <h4>Currículum</h4>
                        </header>
                        <div class="gc-info-card-body gc-cv-detalles"><?php echo $cv_html; ?></div>
                    </section>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> { margin: 2rem 0; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-asistente-compacto{
        background: var(--global-palette9);
        border-radius: .75rem;
        border: 1px solid var(--global-palette7);
        border-top: 6px solid var(--global-palette1);
        overflow: hidden;
        padding: 1.75rem 0;
        box-shadow: 0 6px 18px rgba(15,26,45,.08);
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-avatar{padding:0 1rem;display:flex;justify-content:flex-start}
    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-image{
        width: 200px; height: 200px; object-fit: cover;
        border-radius: .5rem; border: 1px solid var(--global-palette6);
        box-shadow: 0 4px 12px rgba(15,26,45,.10);
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-iniciales{
        width:200px;height:200px;background:var(--global-palette1);color:var(--global-palette9);
        display:flex;align-items:center;justify-content:center;font-weight:700;border-radius:.5rem;font-size:2.5rem;
        box-shadow:0 4px 12px rgba(15,26,45,.10);
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-info{padding:0 1rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-nombre{
        font-weight:800;font-size:2rem;color:var(--global-palette1);
        font-family:var(--global-heading-font-family);margin:0;line-height:1.2;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-rol{
        display:inline-block;font-size:.9rem;padding:.4rem .9rem;background:var(--global-palette7);
        color:var(--global-palette3);border:1px solid var(--global-palette6);border-radius:.375rem;font-weight:600;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-mensaje{line-height:1.65;color:var(--global-palette4);font-size:1.08rem;margin-top:.75rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-correo-enlace{color:var(--global-palette1);font-weight:600;text-decoration:none}
    #<?php echo esc_attr($shortcode_id); ?> .gc-correo-enlace:hover{text-decoration:underline}

    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button{
        display:inline-flex;align-items:center;justify-content:center;
        background:var(--global-palette-btn-bg);color:var(--global-palette-btn);
        border:2px solid var(--global-palette-btn-bg);font-weight:700;
        padding:.55rem 1.2rem;border-radius:.5rem;text-decoration:none;transition:all .2s ease;font-size:.9rem;
        gap:.5rem;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button:hover{
        background:var(--global-palette-btn-bg-hover);border-color:var(--global-palette-btn-bg-hover);
        color:var(--global-palette-btn-hover); transform: translateY(-1px);
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-cv-texto{font-size:.9rem}

    #<?php echo esc_attr($shortcode_id); ?> .gc-cv-contenido{background:var(--global-palette8);padding:1.25rem 0;border-top:1px solid var(--global-palette7)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-cv-contenido[hidden]{display:none}

    #<?php echo esc_attr($shortcode_id); ?> .gc-info-card{
        background:var(--global-palette9);
        border:1px solid var(--global-palette6);
        border-radius:.65rem;
        box-shadow:0 2px 10px rgba(15,26,45,.06);
        margin:0 0 1rem 0;
        overflow:hidden;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-card-header{
        display:flex;align-items:center;gap:.6rem;
        background:var(--global-palette7);
        color:var(--global-palette3);
        border-bottom:1px solid var(--global-palette6);
        padding:.75rem 1rem;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-card-header i{font-size:1.1rem;color:var(--global-palette1)}
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-card-header h4{margin:0;font-weight:800;font-size:1.05rem}
    #<?php echo esc_attr($shortcode_id); ?> .gc-info-card-body{padding:1rem 1rem;color:var(--global-palette4);line-height:1.65}

    #<?php echo esc_attr($shortcode_id); ?> .gc-cv-detalles ul{padding-left:1.25rem;margin-bottom:0}
    #<?php echo esc_attr($shortcode_id); ?> .gc-cv-detalles li{margin-bottom:.25rem}

    @media (max-width: 768px){
        #<?php echo esc_attr($shortcode_id); ?> .gc-asistente-compacto{padding:1.25rem 0}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-avatar{justify-content:center;padding:0 1rem 1.25rem}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-image{width:160px;height:160px}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-iniciales{width:160px;height:160px;font-size:2rem}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-nombre{text-align:center;font-size:1.6rem}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-rol{display:block;margin:.35rem auto 0 auto;text-align:center}
        #<?php echo esc_attr($shortcode_id); ?> .gc-compacto-mensaje{text-align:center;font-size:1rem}
        #<?php echo esc_attr($shortcode_id); ?> .gc-action-button{display:flex;margin:0 auto}
    }
    @media (min-width:1024px){ #<?php echo esc_attr($shortcode_id); ?>{margin:3rem 0} }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const rootSel = '#<?php echo esc_attr($shortcode_id); ?>';
        const toggleButton = document.querySelector(rootSel + ' .gc-cv-toggle');
        const cvContent    = document.querySelector(rootSel + ' .gc-cv-contenido');
        if (!toggleButton || !cvContent) return;

        const labelSpan = toggleButton.querySelector('.gc-cv-texto');
        const chevron   = toggleButton.querySelector('.gc-cv-chevron');

        function setOpen(open){
            toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open){ cvContent.removeAttribute('hidden'); labelSpan.textContent = 'Cerrar perfil'; chevron?.classList.replace('bi-chevron-down','bi-chevron-up'); }
            else     { cvContent.setAttribute('hidden','');  labelSpan.textContent = 'Ver perfil';    chevron?.classList.replace('bi-chevron-up','bi-chevron-down'); }
        }
        toggleButton.addEventListener('click', () => setOpen(toggleButton.getAttribute('aria-expanded') !== 'true'));
    });
    </script>
    <?php
    return ob_get_clean();
}
// ==================== SHORTCODE PREINSCRIPCIONES ====================
/**
 * USO: [programa_preinscripciones]
 */
add_shortcode('programa_preinscripciones', 'programa_preinscripciones_shortcode');
function programa_preinscripciones_shortcode($atts = array()) {
    $current_id = get_the_ID();
    $parent_id  = wp_get_post_parent_id($current_id);

    $shortcode_id = 'gc-preinscripciones-' . wp_rand(1000, 9999);

    if (!$parent_id || !get_post_status($parent_id)) {
        if (current_user_can('edit_posts')) {
            return '<div class="notice notice-error"><p><strong>[programa_preinscripciones]</strong> Esta página no tiene padre válido.</p></div>';
        }
        return '';
    }

    $base_url = get_permalink($parent_id);
    if (!$base_url) {
        if (current_user_can('edit_posts')) {
            return '<div class="notice notice-warning"><p><strong>[programa_preinscripciones]</strong> No pude obtener el permalink del padre.</p></div>';
        }
        return '';
    }

    $url_inscripcion = trailingslashit($base_url) . 'preinscripcion';

    ob_start(); ?>
    <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-preinscripciones-wrapper">
        <div class="gc-preinscripciones-main">
            <div class="gc-preinscripciones-content">
                <h2 class="gc-section-title">Formulario de Preinscripciones 2026</h2>

                <p class="gc-preinscripciones-desc">
                    Comenzá el año cursando un posgrado en FLACSO Uruguay. <strong>Formación 100% a distancia</strong>.
                </p>

                <div class="gc-button-container">
                    <a href="<?php echo esc_url($url_inscripcion); ?>" class="gc-action-button" aria-label="Ir al Formulario de Preinscripción">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        Formulario de Preinscripción
                    </a>
                </div>
            </div>
        </div>

        <div class="gc-preinscripciones-floating" id="floating-<?php echo esc_attr($shortcode_id); ?>">
            <a href="<?php echo esc_url($url_inscripcion); ?>" class="gc-action-button gc-floating-btn" aria-label="Abrir Formulario de Preinscripción">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                <span class="gc-floating-text">Formulario de Preinscripción</span>
            </a>
        </div>
    </div>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> { margin: 2rem 0; padding: 0 1rem; font-family: var(--global-body-font-family); }

    #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-main {
        background: var(--global-palette8);
        border: 1px solid var(--global-palette7);
        border-top: 4px solid var(--global-palette1);
        border-radius: .75rem;
        padding: 2rem 1.5rem;
        box-shadow: 0 8px 24px rgba(15,26,45,.08);
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-section-title {
        font-family: var(--global-heading-font-family) !important;
        font-weight: 800 !important;
        color: var(--global-palette1) !important;
        margin: 0 0 .75rem 0 !important;
        padding: 0 !important;
        border: 0 !important;
        position: static !important;
        text-align: center;
        letter-spacing: -.2px;
        font-size: 1.8rem;
        line-height: 1.2;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-section-title::after { content: none !important; }

    #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-desc {
        margin: 0 0 2rem 0;
        text-align: center;
        color: var(--global-palette4);
        font-size: 1.05rem;
        line-height: 1.55;
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-button-container { display: flex; justify-content: center; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button {
        display: inline-flex; align-items: center; gap: .6rem;
        background: var(--global-palette-btn-bg); color: var(--global-palette-btn);
        border: 2px solid var(--global-palette-btn-bg);
        padding: .95rem 1.75rem; border-radius: .6rem; text-decoration: none;
        font-weight: 700; font-size: 1rem; min-height: 56px; transition: all .25s ease;
        box-shadow: 0 6px 18px rgba(36,129,56,.18);
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-action-button:hover {
        background: var(--global-palette-btn-bg-hover); color: var(--global-palette-btn-hover);
        border-color: var(--global-palette-btn-bg-hover); transform: translateY(-1px);
        box-shadow: 0 8px 22px rgba(27,109,43,.22);
    }

    #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-floating {
        position: fixed; bottom: 1rem; right: 1rem; z-index: 10000;
        opacity: 0; transform: translateY(100px) scale(.9);
        transition: all .35s cubic-bezier(.4,0,.2,1); pointer-events: none;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-floating.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-floating-btn {
        border-radius: 999px; padding: .9rem 1.25rem;
        box-shadow: 0 10px 26px rgba(36,129,56,.28); backdrop-filter: blur(8px);
        min-width: 220px; animation: gc-float-bounce 3s ease-in-out infinite;
    }
    #<?php echo esc_attr($shortcode_id); ?> .gc-floating-text { white-space: nowrap; }

    @keyframes gc-float-bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }

    @media (min-width: 720px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 2.5rem 0; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-main { padding: 3rem 2rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { font-size: 2rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-desc { font-size: 1.1rem; margin-bottom: 2.25rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-floating { bottom: 2rem; right: 2rem; }
    }

    @media (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> { margin: 3rem 0; padding: 0; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-main { padding: 3.25rem 3rem; border-radius: .9rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { font-size: 2.15rem; }
    }

    @media (max-width: 480px) {
        #<?php echo esc_attr($shortcode_id); ?> { padding: 0 .5rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-main { padding: 1.4rem 1rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-section-title { font-size: 1.55rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-desc { font-size: .98rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-floating-btn { min-width: 180px; padding: .85rem 1.1rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-floating-text { white-space: normal; text-align: center; }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mainSection = document.querySelector('#<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-main');
        const floatingBtn = document.querySelector('#<?php echo esc_attr($shortcode_id); ?> .gc-preinscripciones-floating');
        if (!mainSection || !floatingBtn) return;

        function checkVisibility() {
            const rect = mainSection.getBoundingClientRect();
            const h = window.innerHeight || document.documentElement.clientHeight;
            const visible = (Math.min(rect.bottom, h) - Math.max(rect.top, 0)) / rect.height >= 0.5;
            floatingBtn.classList.toggle('show', !visible);
        }

        setTimeout(checkVisibility, 100);
        let t;
        window.addEventListener('scroll', () => { clearTimeout(t); t = setTimeout(checkVisibility, 25); });
        window.addEventListener('resize', checkVisibility);
    });
    </script>
    <?php
    return ob_get_clean();
}
// ==================== SHORTCODE BREADCRUMB ====================
/**
 * USO: [programa_volver_pagina_principal]
 */
add_shortcode('programa_volver_pagina_principal', 'programa_breadcrumb_shortcode');
function programa_breadcrumb_shortcode($atts = array()) {
    if (!function_exists('gc_obtener_breadcrumb_items')) {
        if (current_user_can('edit_posts')) {
            return '<div class="notice notice-error"><p><strong>[programa_volver_pagina_principal]</strong> Falta la función gc_obtener_breadcrumb_items().</p></div>';
        }
        return '';
    }

    $shortcode_id = 'gc-breadcrumb-' . wp_rand(1000, 9999);
    $current_id   = get_the_ID();
    $items        = gc_obtener_breadcrumb_items($current_id);

    ob_start(); ?>
    <nav id="<?php echo esc_attr($shortcode_id); ?>" class="gc-breadcrumb" aria-label="breadcrumb de programa">
        <ol class="gc-breadcrumb-list">
            <?php if (!empty($items)) : foreach ($items as $index => $item): ?>
                <?php if ($index === count($items) - 1): ?>
                    <li class="gc-breadcrumb-item active" aria-current="page">Página Actual</li>
                <?php else: ?>
                    <li class="gc-breadcrumb-item"><a class="gc-breadcrumb-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a></li>
                <?php endif; ?>
            <?php endforeach; endif; ?>
        </ol>
    </nav>

    <style>
    #<?php echo esc_attr($shortcode_id); ?> { margin: 1rem 0 1.25rem; padding: 0 0.5rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-list { list-style: none; padding: .75rem 1rem; margin: 0; display: flex; flex-wrap: wrap; gap: .25rem .5rem; background: transparent; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-item { color: var(--global-palette5); font-size: .95rem; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-item + .gc-breadcrumb-item::before { content: "/"; color: var(--global-palette6); margin: 0 .5rem 0 0; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-link { color: var(--global-palette1); text-decoration: none; font-weight: 600; }
    #<?php echo esc_attr($shortcode_id); ?> .gc-breadcrumb-link:hover { text-decoration: underline; }

    @media (min-width: 1024px) {
        #<?php echo esc_attr($shortcode_id); ?> { max-width: var(--global-content-width); margin: 1rem auto; }
    }
    </style>
    <?php
    return ob_get_clean();
}
// ==================== FUNCIONES AUXILIARES ====================
function gc_obtener_datos_pagina($page_id) {
    $page = get_post($page_id);
    
    if (!$page) {
        return array();
    }
    
    $imagen = '';
    if (has_post_thumbnail($page_id)) {
        $imagen = get_the_post_thumbnail_url($page_id, 'medium');
    }
    
    return array(
        'titulo' => $page->post_title,
        'imagen' => $imagen
    );
}
