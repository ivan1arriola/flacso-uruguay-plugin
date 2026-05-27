import re

with open("modules/oferta-academica/templates/single-oferta-academica.php", "r") as f:
    content = f.read()

# Replace banner
banner_replacement = """                                <!-- Banner Superior -->
                                <div class="wp-block-kadence-column mb-5">
                                    <div class="kt-inside-inner-col">
                                        <?php 
                                        if (class_exists('Flacso_Inscripciones_Banner_Block')) {
                                            echo Flacso_Inscripciones_Banner_Block::init()->render_block([]); 
                                        } else {
                                        ?>
                                            <div class="flacso-inscripciones-banner">
                                                <?php if ($thumbnail_url) : ?>
                                                    <img decoding="async" class="flacso-inscripciones-banner__img" src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
                                                <?php endif; ?>
                                                <div class="flacso-inscripciones-banner__overlay">
                                                    <div class="flacso-inscripciones-banner__top">
                                                        <div class="flacso-inscripciones-banner__tag">
                                                            <?php echo !empty($data['inscripciones_abiertas']) ? __('Inscripciones 2026', 'flacso-uruguay') : __('Próximamente', 'flacso-uruguay'); ?>
                                                        </div>
                                                        <?php
                                                        $logo_url = 'https://flacso.edu.uy/wp-content/uploads/2026/05/logo_flacso_uruguay_20anos_blanco.png';
                                                        ?>
                                                        <img decoding="async" src="<?php echo esc_url($logo_url); ?>" alt="FLACSO Uruguay" class="flacso-inscripciones-banner__logo">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>"""

content = re.sub(r"                                <!-- Banner Superior -->\n                                <div class=\"wp-block-kadence-column mb-5\">\n                                    <div class=\"kt-inside-inner-col\">\n                                        <div class=\"flacso-inscripciones-banner\">.*?</div>\n                                        </div>\n                                    </div>\n                                </div>", banner_replacement, content, flags=re.DOTALL)

# Replace Docentes loop
docentes_replacement = """                                <!-- Equipo Académico -->
                                <div class="kb-row-layout-wrap wp-block-kadence-rowlayout alignfull" style="background-color: #f8fafc; padding: 80px 0;">
                                    <div class="site-container">
                                        <h2 class="wp-block-heading text-center mb-5" style="text-transform:uppercase; font-weight: 800; color: #163970;"><?php _e('Equipo Académico', 'flacso-uruguay'); ?></h2>
                                        
                                        <?php if (!empty($data['coordinacion_academica'])) : ?>
                                            <?php foreach ($data['coordinacion_academica'] as $coord) : ?>
                                                <div class="mb-5">
                                                    <div class="row justify-content-center">
                                                        <?php foreach ($coord['docentes'] as $docente_id) : ?>
                                                            <div class="col-12 col-lg-10 mb-4">
                                                                <?php 
                                                                if (function_exists('dp_docente_destacado')) {
                                                                    echo dp_docentes_wrap_output(dp_docente_destacado(['docId' => $docente_id, 'rol' => $coord['rol']]));
                                                                }
                                                                ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($data['equipos'])) : ?>
                                            <?php foreach ($data['equipos'] as $grupo) : ?>
                                                <div class="mb-5">
                                                    <h3 class="wp-block-heading text-center mb-4" style="font-size: 1.4rem; color: #163970; border-bottom: 2px solid #fcd116; display: inline-block; padding-bottom: 5px; margin-left: 50%; transform: translateX(-50%);"><?php echo esc_html($grupo['nombre']); ?></h3>
                                                    <div class="row justify-content-center">
                                                        <div class="col-12 col-lg-10">
                                                            <div class="flacso-docentes-scope">
                                                                <div class="docentes-lista-completa">
                                                                    <?php foreach ($grupo['docentes'] as $docente_id) : ?>
                                                                        <?php
                                                                        // Fallback manual si no existe dp_render_docente_lista
                                                                        $doc_avatar = get_the_post_thumbnail_url($docente_id, 'medium');
                                                                        $doc_prefijo = get_post_meta($docente_id, '_docente_prefijo', true);
                                                                        $doc_cv = get_the_excerpt($docente_id);
                                                                        $doc_nombre = get_the_title($docente_id);
                                                                        ?>
                                                                        <div class="card docentes-lista-card hover-lift">
                                                                            <div class="card-body">
                                                                                <div class="d-sm-flex align-items-sm-center">
                                                                                    <?php if ($doc_avatar) : ?>
                                                                                        <img src="<?php echo esc_url($doc_avatar); ?>" class="rounded-circle mb-3 mb-sm-0 me-sm-4 docente-avatar" alt="<?php echo esc_attr($doc_nombre); ?>" style="width: 100px; height: 100px;">
                                                                                    <?php endif; ?>
                                                                                    <div>
                                                                                        <?php if ($doc_prefijo) : ?>
                                                                                            <p class="docentes-lista-card__prefix"><?php echo esc_html($doc_prefijo); ?></p>
                                                                                        <?php endif; ?>
                                                                                        <h3 class="docentes-lista-card__name mb-2"><?php echo esc_html($doc_nombre); ?></h3>
                                                                                        <div class="docentes-lista-card__cv">
                                                                                            <p><?php echo esc_html($doc_cv); ?></p>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>"""

content = re.sub(r"                                <!-- Equipo Académico -->\n                                <div class=\"kb-row-layout-wrap wp-block-kadence-rowlayout alignfull\" style=\"background-color: #f8fafc; padding: 80px 0;\">\n                                    <div class=\"site-container\">\n                                        <h2 class=\"wp-block-heading text-center mb-5\" style=\"text-transform:uppercase; font-weight: 800; color: #163970;\"><\?php _e\('Equipo Académico', 'flacso-uruguay'\); \?></h2>\n                                        \n                                        <\?php if \(!empty\(\$data\['coordinacion_academica'\]\)\) :\?>\n                                            <\?php foreach \(\$data\['coordinacion_academica'\] as \$coord\) :\?>.*?<\?php endif; \?>", docentes_replacement, content, flags=re.DOTALL)

with open("modules/oferta-academica/templates/single-oferta-academica.php", "w") as f:
    f.write(content)
