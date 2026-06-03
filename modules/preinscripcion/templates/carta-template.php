<?php
/**
 * Template Name: FLACSO - Carta de Presentación
 * Description: Template dinámico para la página virtual de carta de presentación
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;

$post_id = $post->ID;
$data = class_exists('Oferta_Data_Schema') ? Oferta_Data_Schema::get_schema($post_id) : [];

if (!function_exists('flacso_carta_bool')) {
    function flacso_carta_bool($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'si', 'sí', 'yes'], true);
        }

        return !empty($value);
    }
}

if (!function_exists('flacso_carta_array_from_value')) {
    function flacso_carta_array_from_value($value): array {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }
}

if (!function_exists('flacso_carta_render_feature_card')) {
    function flacso_carta_render_feature_card(string $icon, string $title, string $value): void {
        ?>
        <article class="fc-feature-card">
            <div class="fc-feature-icon">
                <i class="bi <?php echo esc_attr($icon); ?>"></i>
            </div>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html(wp_strip_all_tags($value)); ?></p>
        </article>
        <?php
    }
}

if (!function_exists('flacso_carta_render_requirement')) {
    function flacso_carta_render_requirement(string $icon, string $content, bool $allow_html = false): void {
        ?>
        <div class="fc-requirement">
            <div class="fc-requirement-icon">
                <i class="bi <?php echo esc_attr($icon); ?>"></i>
            </div>
            <div class="fc-requirement-content">
                <?php
                if ($allow_html) {
                    echo wp_kses_post($content);
                } else {
                    echo esc_html($content);
                }
                ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('flacso_carta_determinante')) {
    function flacso_carta_determinante(string $titulo): string {
        $primera = strtolower(trim(strtok($titulo, ' ')));
        return in_array($primera, ['maestría', 'maestria', 'especialización', 'especializacion', 'licenciatura'], true) ? 'de la' : 'del';
    }
}

$child_content = !empty($data['carta_presentacion_html']) ? $data['carta_presentacion_html'] : '';

if (empty($child_content)) {
    $child_page = get_page_by_path($post->post_name . '/carta', OBJECT, 'page');

    if ($child_page) {
        $child_content = apply_filters('the_content', $child_page->post_content);
    }
}

$tipo_terms = get_the_terms($post_id, 'tipo-oferta-academica');
$tipo_oferta = '';

if ($tipo_terms && !is_wp_error($tipo_terms)) {
    $tipo_oferta = $tipo_terms[0]->name;
}

$is_maestria = stripos($tipo_oferta, 'maestr') !== false;
$is_diplomado = stripos($tipo_oferta, 'diplomado') !== false || stripos($tipo_oferta, 'diploma') !== false;
$es_especializacion = stripos($tipo_oferta, 'especializa') !== false;

$titulo = get_the_title($post_id);
$abreviacion = $data['abreviacion'] ?? '';
$cohorte = get_post_meta($post_id, 'cohorte', true) ?: '';
$anio = date('Y');
$carta_cta_titulo = !empty($data['carta_cta_titulo'])
    ? $data['carta_cta_titulo']
    : 'Comenzá el año cursando un posgrado en FLACSO Uruguay';

$proximo_inicio_val = $data['proximo_inicio'] ?? '';

if (is_array($proximo_inicio_val)) {
    $proximo_inicio_val = !empty($proximo_inicio_val) ? reset($proximo_inicio_val) : '';
}

$proximo_inicio = 'A definir';

if (!empty($proximo_inicio_val)) {
    $proximo_inicio_str = (string) $proximo_inicio_val;

    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('j \d\e F \d\e Y', strtotime($proximo_inicio_str));
    } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('F Y', strtotime($proximo_inicio_str . '-01'));
    } else {
        $proximo_inicio = $proximo_inicio_str;
    }
}

$duracion = !empty($data['duracion_meses']) ? $data['duracion_meses'] . ' meses' : '12 meses';
$modalidad = !empty($data['modalidad_resumen']) ? $data['modalidad_resumen'] : 'Virtual';

$menciones = is_array($data['menciones'] ?? null) ? implode('|', $data['menciones']) : ($data['menciones'] ?? '');
$orientaciones = is_array($data['orientaciones'] ?? null) ? implode('|', $data['orientaciones']) : ($data['orientaciones'] ?? '');

$arr_menciones = flacso_carta_array_from_value($menciones);
$arr_orientaciones = flacso_carta_array_from_value($orientaciones);

$reconocido_mec = isset($data['reconocido_mec'])
    ? flacso_carta_bool($data['reconocido_mec'])
    : $is_maestria;

$reconocimiento_int = isset($data['reconocimiento_internacional'])
    ? flacso_carta_bool($data['reconocimiento_internacional'])
    : ($is_maestria || (int) $post_id === 12316);

$mostrar_banner_convenio = !empty($data['convenio_iin_oea']);

$imagen = '';
$imagen_id = get_post_thumbnail_id($post_id);

if ($imagen_id) {
    $imagen_array = wp_get_attachment_image_src($imagen_id, 'large');

    if ($imagen_array && !empty($imagen_array[0])) {
        $imagen = $imagen_array[0];
    }
}

$asistente_slug = '';
$asistente_nombre = '';
$asistente_correo = 'inscripciones@flacso.edu.uy';
$asistente_titulo = 'Asistente Académica';

$override_docente_id = !empty($data['asistente_academica_docente_id']) ? (int) $data['asistente_academica_docente_id'] : 0;
$override_rol = !empty($data['asistente_academica_rol']) ? $data['asistente_academica_rol'] : '';
$override_correo = !empty($data['asistente_academica_correo']) ? $data['asistente_academica_correo'] : '';

if ($override_docente_id > 0) {
    $asistente_post = get_post($override_docente_id);

    if ($asistente_post) {
        $asistente_slug = $asistente_post->post_name;
        $asistente_nombre = get_the_title($asistente_post->ID);
        $asistente_titulo = $override_rol ?: 'Asistente Académica';

        $correo_meta = get_post_meta($override_docente_id, 'correo', true);
        $asistente_correo = $override_correo ?: ($correo_meta ?: 'inscripciones@flacso.edu.uy');
    }
}

if (!$asistente_slug && !empty($data['equipos']) && is_array($data['equipos'])) {
    foreach ($data['equipos'] as $eq) {
        if (!empty($eq['nombre']) && stripos($eq['nombre'], 'asistente') !== false && !empty($eq['docentes'])) {
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

$titulos_intermedios_ids = !empty($data['titulos_intermedios']) && is_array($data['titulos_intermedios'])
    ? $data['titulos_intermedios']
    : [];

$precios_filas = [];

if (!empty($data['precios_filas'])) {
    if (is_string($data['precios_filas'])) {
        $decoded = json_decode($data['precios_filas'], true);
        $precios_filas = is_array($decoded) ? $decoded : [];
    } elseif (is_array($data['precios_filas'])) {
        $precios_filas = $data['precios_filas'];
    }
}

$precios_nota = !empty($data['precios_nota'])
    ? $data['precios_nota']
    : 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>';

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
$url_inscripcion = trailingslashit(get_permalink($post_id)) . 'preinscripcion';
?>

<div id="primary" class="content-area flacso-carta-virtual">
    <main id="main" class="site-main">

        <section class="fc-hero">
            <div class="fc-hero-inner">

                <div class="fc-hero-copy">
                    <div class="fc-eyebrow">
                        <i class="bi bi-stars"></i>
                        <span>Formación de excelencia, estés donde estés</span>
                    </div>

                    <h1><?php echo esc_html($titulo); ?></h1>

                    <div class="fc-hero-meta">
                        <?php if (!empty($tipo_oferta)) : ?>
                            <span><?php echo esc_html($tipo_oferta); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($abreviacion)) : ?>
                            <span><?php echo esc_html($abreviacion . ' ' . $anio); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($cohorte)) : ?>
                            <span><?php echo esc_html($cohorte); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($reconocido_mec || $reconocimiento_int) : ?>
                        <div class="fc-certifications">
                            <?php if ($reconocido_mec) : ?>
                                <div class="fc-certification">
                                    <i class="bi bi-award"></i>
                                    <span>Reconocida por el M.E.C</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($reconocimiento_int) : ?>
                                <div class="fc-certification">
                                    <i class="bi bi-globe-americas"></i>
                                    <span>Apostillado de La Haya</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($arr_menciones)) : ?>
                        <div class="fc-chip-block">
                            <p>Con mención en:</p>
                            <div class="fc-chip-grid">
                                <?php foreach ($arr_menciones as $mencion) : ?>
                                    <span><?php echo esc_html($mencion); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($arr_orientaciones)) : ?>
                        <div class="fc-chip-block">
                            <p>Orientaciones metodológicas:</p>
                            <div class="fc-chip-grid">
                                <?php foreach ($arr_orientaciones as $orientacion) : ?>
                                    <span><?php echo esc_html($orientacion); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="fc-hero-actions">
                        <a class="fc-primary-button" href="<?php echo esc_url($url_inscripcion); ?>">
                            <i class="bi bi-pencil-square"></i>
                            Preinscribirme
                        </a>

                        <a class="fc-secondary-button" href="#fc-info-clave">
                            <i class="bi bi-arrow-down-circle"></i>
                            Ver información
                        </a>
                    </div>
                </div>

                <div class="fc-hero-visual">
                    <?php if ($mostrar_banner_convenio) : ?>
                        <div class="fc-convenio-pill">
                            <i class="bi bi-handshake"></i>
                            <span>En convenio con el <strong>IIN-OEA</strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($imagen) : ?>
                        <div class="fc-cover-card">
                            <img src="<?php echo esc_url($imagen); ?>" alt="<?php echo esc_attr($titulo); ?>">
                        </div>
                    <?php else : ?>
                        <div class="fc-cover-card fc-cover-placeholder">
                            <i class="bi bi-mortarboard"></i>
                            <span><?php echo esc_html($tipo_oferta ?: 'Posgrado'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (!empty($titulos_intermedios_ids)) : ?>
                <div class="fc-intermediate-strip">
                    <p>Durante la cursada del programa se obtendrán <strong>títulos intermedios</strong>:</p>

                    <div class="fc-intermediate-grid">
                        <?php foreach ($titulos_intermedios_ids as $tid) : ?>
                            <?php
                            $t_post = get_post($tid);

                            if (!$t_post) {
                                continue;
                            }

                            $t_duracion = get_post_meta($tid, 'duracion_html', true);
                            ?>
                            <article class="fc-intermediate-card">
                                <div class="fc-intermediate-icon">
                                    <i class="bi bi-mortarboard"></i>
                                </div>

                                <div>
                                    <h3><?php echo esc_html($t_post->post_title); ?></h3>

                                    <?php if (!empty($t_duracion)) : ?>
                                        <span><?php echo esc_html(wp_strip_all_tags($t_duracion)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_maestria) : ?>
                        <p class="fc-intermediate-note">
                            El título de <strong>Magíster</strong> será obtenido una vez se aprueben los créditos académicos y se defienda la tesis.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="fc-page-container">

            <section id="fc-info-clave" class="fc-feature-grid">
                <?php
                flacso_carta_render_feature_card('bi-calendar-event', 'Próximo inicio', $proximo_inicio);
                flacso_carta_render_feature_card('bi-clock', 'Duración', $duracion);
                flacso_carta_render_feature_card('bi-laptop', 'Modalidad', $modalidad);
                ?>
            </section>

            <section class="fc-content-wrapper">
                <?php
                $mensaje_bienvenida = get_option('flacso_mensaje_bienvenida', '');

                if (!empty($mensaje_bienvenida)) {
                    ?>
                    <div class="fc-message-box">
                        <?php echo wp_kses_post($mensaje_bienvenida); ?>
                    </div>
                    <?php
                }

                foreach ($bloques_html as $meta_key => $titulo_bloque) {
                    if (!empty($data[$meta_key])) {
                        ?>
                        <article class="fc-rich-block">
                            <h2><?php echo esc_html($titulo_bloque); ?></h2>
                            <?php echo wp_kses_post($data[$meta_key]); ?>
                        </article>
                        <?php
                    }
                }

                echo wp_kses_post($child_content);
                ?>
            </section>

            <?php
            if (class_exists('Oferta_Blocks') && strpos($child_content, 'flacso-uruguay/dato-malla-curricular') === false) {
                $calendario_html = Oferta_Blocks::render_dato_calendario(['ofertaId' => $post_id]);
                $malla_html = Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $post_id]);

                if (trim($calendario_html) !== '' || trim($malla_html) !== '') {
                    ?>
                    <section class="fc-native-blocks">
                        <?php
                        if (trim($calendario_html) !== '') {
                            echo $calendario_html;
                        }

                        if (trim($malla_html) !== '') {
                            echo $malla_html;
                        }
                        ?>
                    </section>
                    <?php
                }
            }
            ?>

            <section class="fc-section">
                <h2 class="fc-section-title">Requisitos de Postulación y Admisión</h2>

                <?php if (!empty($data['requisitos_ingreso_html'])) : ?>
                    <div class="fc-content-wrapper fc-content-wrapper-compact">
                        <?php echo wp_kses_post($data['requisitos_ingreso_html']); ?>
                    </div>
                <?php else : ?>
                    <div class="fc-requirements-list">
                        <?php
                        flacso_carta_render_requirement('bi-pencil-square', 'Completar el formulario de preinscripción online');

                        if ($is_maestria) {
                            flacso_carta_render_requirement(
                                'bi-paperclip',
                                '<strong>Añadir en el formulario, de forma escaneada:</strong><ul><li>Documento que acredite estudios de grado con copia legalizada</li><li>Documento de identidad vigente</li><li>Curriculum Vitae</li><li>Carta de motivación</li><li>Dos cartas de referencia</li></ul>',
                                true
                            );

                            flacso_carta_render_requirement('bi-people', 'Asistir a una entrevista de admisión con la coordinación académica');
                        } else {
                            flacso_carta_render_requirement(
                                'bi-paperclip',
                                '<strong>Añadir en el formulario, de forma escaneada:</strong><ul><li>Documento que acredite estudios previos</li><li>Documento de identidad vigente</li><li>Carta de motivación</li></ul>',
                                true
                            );
                        }

                        flacso_carta_render_requirement('bi-award', 'La admisión está sujeta a cupos y selección académica');
                        ?>
                    </div>

                    <div class="fc-alert-box">
                        <h3>Atención</h3>
                        <p>Todos los documentos deben adjuntarse en el formulario de preinscripción.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fc-section">
                <?php if (!empty($precios_filas) && is_array($precios_filas)) : ?>
                    <div class="fc-card">
                        <div class="fc-card-header">
                            <span class="fc-card-kicker">Inversión</span>
                            <h2>Beneficios y descuentos acumulables</h2>
                        </div>

                        <div class="fc-table-wrap">
                            <table class="fc-pricing-table">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Valor en $ <span>residentes en Uruguay</span></th>
                                        <th>Valor en U$S <span>residentes en el exterior</span></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($precios_filas as $row) : ?>
                                        <tr class="<?php echo !empty($row['highlight']) ? 'is-highlighted' : ''; ?>">
                                            <td><?php echo wp_kses_post($row['concept'] ?? ''); ?></td>
                                            <td><?php echo wp_kses_post($row['uy'] ?? ''); ?></td>
                                            <td><?php echo wp_kses_post($row['us'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($precios_nota)) : ?>
                            <div class="fc-pricing-note">
                                <?php echo wp_kses_post($precios_nota); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="fc-empty-pricing">
                        <i class="bi bi-currency-dollar"></i>
                        <p>
                            Para consultar los costos, planes de financiación y políticas de becas, ponte en contacto con
                            <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a>.
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fc-section fc-info-important">
                <h2 class="fc-section-title">Información importante</h2>

                <div class="fc-info-list">
                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-currency-dollar"></i></div>
                        <div>Para pagos en <strong>dólares</strong>, las cuotas se mantendrán fijas durante toda la cursada.</div>
                    </div>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-currency-exchange"></i></div>
                        <div>Para pagos en <strong>pesos</strong>, las cuotas permanecerán fijas durante todo 2026, y a partir de 2027 se ajustarán en enero de cada año, por debajo de la inflación interanual.</div>
                    </div>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-calendar-check"></i></div>
                        <div>Las cuotas vencen el día <strong>15 de cada mes</strong>.</div>
                    </div>

                    <?php if ($mostrar_expedicion) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-award"></i></div>
                            <div>El título se expide en el exterior y tiene un costo asociado de <strong>USD 150</strong>, que incluye el envío y el trámite de Apostilla de La Haya.</div>
                        </div>
                    <?php endif; ?>

                    <?php if ($mostrar_costos_envio) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-truck"></i></div>
                            <div>
                                <strong>Los certificados tienen costos de envío al exterior:</strong>
                                <ul class="fc-envios-list">
                                    <li><span>USD 30</span> – envío a Argentina</li>
                                    <li><span>USD 50</span> – envío al resto de América Latina</li>
                                    <li><span>USD 70</span> – envío a Europa</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($mostrar_convenio_iin) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-globe"></i></div>
                            <div><strong>Convenio IIN-OEA:</strong> los funcionarios o referentes de los Estados Miembros que cuenten con aval institucional acceden a bonificaciones especiales para cursar este programa.</div>
                        </div>
                    <?php endif; ?>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <strong>Consultá por otros planes de financiación en</strong>
                            <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a>
                        </div>
                    </div>
                </div>

                <div class="fc-action-buttons">
                    <a href="https://flacso.edu.uy/convenios/" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text"></i>
                        Ver convenios
                    </a>

                    <a href="https://flacso.edu.uy/formas-de-pago/" target="_blank" rel="noopener">
                        <i class="bi bi-credit-card"></i>
                        Ver formas de pago
                    </a>
                </div>
            </section>

            <section class="fc-section">
                <h2 class="fc-section-title">Más información</h2>

                <div class="fc-more-grid">
                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-award"></i></div>
                        <h3>Trayectoria Académica</h3>
                        <p>Nuestra <strong>Facultad de Posgrados</strong> busca formar a sus estudiantes a nivel <strong>académico, profesional y laboral</strong>. Nos distinguen <strong>19 años de trayectoria</strong> a nivel nacional y más de <strong>65 años a nivel internacional</strong>. Además, más de <strong>7000 personas egresadas</strong> de FLACSO Uruguay trabajan en el ámbito público y privado.</p>
                    </article>

                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-gear"></i></div>
                        <h3>Gestión Académica</h3>
                        <p>Nos distingue un sistema de <strong>gestión académica eficiente y cercano</strong>, que acompaña de forma <strong>personalizada</strong> a cada estudiante y garantiza <strong>altos niveles de egreso, superiores al 90%</strong>.</p>
                    </article>

                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-currency-dollar"></i></div>
                        <h3>Financiamiento Flexible</h3>
                        <p>Puedes abonar el posgrado en <strong>cuotas sin recargo</strong> a lo largo de la cursada. Contamos con <strong>múltiples convenios, descuentos de hasta el 25%</strong> y la posibilidad de acceder a <strong>becas</strong>.</p>
                    </article>
                </div>
            </section>

            <?php
            if ($asistente_slug) {
                $asist_docente = get_page_by_path($asistente_slug, OBJECT, 'docente');

                if ($asist_docente) {
                    $asist_meta = get_post_meta($asist_docente->ID);
                    $asist_prefijo = !empty($asist_meta['prefijo_abrev'][0]) ? esc_html($asist_meta['prefijo_abrev'][0]) . ' ' : '';
                    $asist_nombre_completo = $asist_prefijo . esc_html(get_the_title($asist_docente->ID));
                    $asist_imagen_url = get_the_post_thumbnail_url($asist_docente->ID, 'medium');
                    $prep_posgrado = flacso_carta_determinante(get_the_title($post_id));
                    $enlace_correo = '<a href="mailto:' . esc_attr($asistente_correo) . '">' . esc_html($asistente_correo) . '</a>';

                    $presentacion_final = sprintf(
                        'Mi nombre es %s y soy %s %s %s. Si tienes dudas o consultas puedes contactarme al mail %s.',
                        $asist_nombre_completo,
                        esc_html($asistente_titulo),
                        esc_html($prep_posgrado),
                        esc_html(get_the_title($post_id)),
                        $enlace_correo
                    );
                    ?>
                    <section class="fc-assistant-card">
                        <div class="fc-assistant-photo">
                            <?php if ($asist_imagen_url) : ?>
                                <img src="<?php echo esc_url($asist_imagen_url); ?>" alt="<?php echo esc_attr(wp_strip_all_tags($asist_nombre_completo)); ?>">
                            <?php else : ?>
                                <div class="fc-assistant-placeholder">
                                    <?php echo esc_html(strtoupper(substr(wp_strip_all_tags($asist_nombre_completo), 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="fc-assistant-copy">
                            <span class="fc-assistant-label">Contacto académico</span>
                            <h2><?php echo $asist_nombre_completo; ?></h2>
                            <p class="fc-assistant-role"><?php echo esc_html($asistente_titulo); ?></p>
                            <p><?php echo wp_kses_post($presentacion_final); ?></p>
                        </div>
                    </section>
                    <?php
                }
            }
            ?>

            <section class="fc-final-cta">
                <div>
                    <span>Preinscripciones <?php echo esc_html($anio); ?></span>
                    <h2><?php echo esc_html($carta_cta_titulo); ?></h2>
                    <p><strong>Formación 100% a distancia</strong></p>
                </div>

                <a href="<?php echo esc_url($url_inscripcion); ?>">
                    <i class="bi bi-pencil-square"></i>
                    Formulario de Preinscripción
                </a>
            </section>

        </div>
    </main>
</div>

<style>
.flacso-carta-virtual {
    --fc-primary: var(--global-palette1, #16396f);
    --fc-primary-dark: #102b56;
    --fc-secondary: #e9e50f;
    --fc-text: var(--global-palette3, #172033);
    --fc-muted: var(--global-palette4, #4b5b72);
    --fc-soft: var(--global-palette8, #edf2f6);
    --fc-soft-2: var(--global-palette7, #dce6ef);
    --fc-white: var(--global-palette9, #ffffff);
    --fc-border: rgba(22, 57, 111, .14);
    --fc-shadow: 0 18px 45px rgba(15, 26, 45, .12);
    --fc-shadow-soft: 0 8px 24px rgba(15, 26, 45, .08);
    color: var(--fc-text);
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 42%, #ffffff 100%);
}

.flacso-carta-virtual * {
    box-sizing: border-box;
}

.flacso-carta-virtual a {
    transition: all .2s ease;
}

.fc-hero {
    max-width: 1240px;
    margin: 1rem auto 0;
    padding: 0 1rem;
}

.fc-hero-inner {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
    gap: 2rem;
    align-items: center;
    overflow: hidden;
    border-radius: 1.5rem;
    padding: clamp(1.5rem, 4vw, 3.25rem);
    background:
        radial-gradient(circle at 12% 18%, rgba(233, 229, 15, .28), transparent 24rem),
        radial-gradient(circle at 88% 10%, rgba(255, 255, 255, .18), transparent 18rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-hero-copy {
    position: relative;
    z-index: 2;
}

.fc-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    width: fit-content;
    margin-bottom: 1.15rem;
    padding: .55rem .85rem;
    border: 1px solid rgba(255, 255, 255, .22);
    border-radius: 999px;
    background: rgba(255, 255, 255, .12);
    color: var(--fc-white);
    font-size: .92rem;
    font-weight: 700;
    backdrop-filter: blur(8px);
}

.fc-eyebrow i {
    color: var(--fc-secondary);
}

.fc-hero h1 {
    max-width: 780px;
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(2.15rem, 5vw, 4.2rem);
    line-height: .98;
    letter-spacing: -.045em;
    font-weight: 850;
}

.fc-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
    margin-top: 1.4rem;
}

.fc-hero-meta span,
.fc-chip-grid span {
    display: inline-flex;
    align-items: center;
    min-height: 2.45rem;
    padding: .65rem .9rem;
    border-radius: .8rem;
    background: rgba(255, 255, 255, .13);
    border: 1px solid rgba(255, 255, 255, .18);
    color: var(--fc-white);
    font-weight: 750;
    line-height: 1.25;
}

.fc-certifications {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: .75rem;
    max-width: 640px;
    margin-top: 1.4rem;
}

.fc-certification {
    display: flex;
    align-items: center;
    gap: .7rem;
    padding: .85rem 1rem;
    border-radius: .95rem;
    background: rgba(255, 255, 255, .95);
    color: var(--fc-primary);
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}

.fc-certification i {
    color: var(--fc-primary);
    font-size: 1.25rem;
}

.fc-chip-block {
    margin-top: 1.4rem;
}

.fc-chip-block p {
    margin: 0 0 .65rem;
    color: rgba(255, 255, 255, .86);
    font-weight: 800;
}

.fc-chip-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
}

.fc-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .8rem;
    margin-top: 1.8rem;
}

.fc-primary-button,
.fc-secondary-button,
.fc-final-cta a,
.fc-action-buttons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    border-radius: .85rem;
    padding: .9rem 1.15rem;
    font-weight: 850;
    text-decoration: none;
    line-height: 1.15;
}

.fc-primary-button {
    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
}

.fc-primary-button:hover {
    transform: translateY(-2px);
    color: var(--fc-primary-dark);
    filter: brightness(.98);
}

.fc-secondary-button {
    color: var(--fc-white);
    border: 1px solid rgba(255, 255, 255, .28);
    background: rgba(255, 255, 255, .10);
}

.fc-secondary-button:hover {
    color: var(--fc-white);
    background: rgba(255, 255, 255, .16);
}

.fc-hero-visual {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.fc-cover-card {
    width: min(100%, 380px);
    aspect-ratio: 4 / 5;
    padding: .65rem;
    border-radius: 1.35rem;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .22);
    box-shadow: 0 24px 60px rgba(0, 0, 0, .25);
    transform: rotate(1.5deg);
}

.fc-cover-card img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    border-radius: 1rem;
}

.fc-cover-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    color: var(--fc-white);
    background: rgba(255, 255, 255, .10);
}

.fc-cover-placeholder i {
    font-size: 4rem;
    color: var(--fc-secondary);
}

.fc-cover-placeholder span {
    font-size: 1.2rem;
    font-weight: 800;
}

.fc-convenio-pill {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    max-width: 100%;
    padding: .65rem .9rem;
    border-radius: 999px;
    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    font-size: .9rem;
    font-weight: 800;
    box-shadow: 0 12px 30px rgba(0, 0, 0, .16);
}

.fc-intermediate-strip {
    margin-top: 1rem;
    padding: clamp(1rem, 3vw, 1.5rem);
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-intermediate-strip > p {
    margin: 0 0 1rem;
    text-align: center;
    color: var(--fc-primary);
    font-size: 1.05rem;
    font-weight: 750;
}

.fc-intermediate-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: .9rem;
}

.fc-intermediate-card {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: 1rem;
    border-radius: 1rem;
    background: var(--fc-soft);
    border: 1px solid var(--fc-border);
}

.fc-intermediate-icon,
.fc-feature-icon,
.fc-info-icon,
.fc-more-icon,
.fc-requirement-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: var(--fc-primary);
    background: rgba(22, 57, 111, .10);
}

.fc-intermediate-icon {
    width: 42px;
    height: 42px;
    border-radius: 999px;
}

.fc-intermediate-card h3 {
    margin: 0;
    color: var(--fc-primary);
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.2;
}

.fc-intermediate-card span {
    display: block;
    margin-top: .25rem;
    color: var(--fc-muted);
    font-size: .92rem;
    font-weight: 650;
}

.fc-intermediate-note {
    margin-top: 1rem !important;
    margin-bottom: 0 !important;
    color: var(--fc-muted) !important;
}

.fc-page-container {
    width: min(100% - 2rem, 1060px);
    margin: 0 auto;
    padding: clamp(2rem, 5vw, 3.5rem) 0;
}

.fc-feature-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 2.5rem;
}

.fc-feature-card {
    position: relative;
    overflow: hidden;
    min-height: 190px;
    padding: 1.35rem;
    border: 1px solid var(--fc-border);
    border-radius: 1.15rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-feature-card::after {
    content: "";
    position: absolute;
    inset: auto -2.5rem -2.5rem auto;
    width: 8rem;
    height: 8rem;
    border-radius: 999px;
    background: rgba(22, 57, 111, .06);
}

.fc-feature-icon {
    width: 58px;
    height: 58px;
    margin-bottom: 1rem;
    border-radius: 1rem;
    font-size: 1.45rem;
}

.fc-feature-card h3 {
    margin: 0 0 .45rem;
    color: var(--fc-primary);
    font-size: 1.05rem;
    font-weight: 850;
}

.fc-feature-card p {
    margin: 0;
    color: var(--fc-text);
    font-size: 1.1rem;
    font-weight: 750;
    line-height: 1.35;
}

.fc-content-wrapper {
    width: 100%;
    margin: 0 0 2.75rem;
    padding: clamp(1.25rem, 3vw, 2rem);
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, .86);
    box-shadow: var(--fc-shadow-soft);
    color: var(--fc-muted);
    font-size: 1.08rem;
    line-height: 1.75;
}

.fc-content-wrapper-compact {
    margin-bottom: 0;
}

.fc-content-wrapper h2,
.fc-rich-block h2 {
    margin: 2.25rem 0 1rem;
    color: var(--fc-primary);
    font-size: clamp(1.45rem, 3vw, 2rem);
    line-height: 1.15;
    letter-spacing: -.025em;
    font-weight: 850;
}

.fc-content-wrapper h2:first-child,
.fc-rich-block:first-child h2 {
    margin-top: 0;
}

.fc-content-wrapper h3 {
    margin: 1.75rem 0 .85rem;
    color: var(--fc-primary);
    font-size: 1.35rem;
    font-weight: 800;
}

.fc-content-wrapper p {
    margin: 0 0 1.15rem;
}

.fc-content-wrapper ul,
.fc-content-wrapper ol {
    margin: 0 0 1.35rem 1.25rem;
    padding-left: 1.25rem;
}

.fc-content-wrapper li {
    margin-bottom: .55rem;
}

.fc-content-wrapper a {
    color: var(--fc-primary);
    font-weight: 800;
    text-decoration-thickness: 2px;
    text-underline-offset: 3px;
}

.fc-message-box {
    margin-bottom: 1.75rem;
    padding: 1.25rem 1.35rem;
    border-left: 5px solid var(--fc-primary);
    border-radius: 1rem;
    background: var(--fc-soft);
    color: var(--fc-text);
}

.fc-rich-block {
    margin-bottom: 2rem;
}

.fc-native-blocks {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin: 2.5rem 0;
}

.fc-section {
    margin: 3rem 0;
}

.fc-section-title {
    margin: 0 0 1.25rem;
    color: var(--fc-primary);
    font-size: clamp(1.65rem, 4vw, 2.35rem);
    line-height: 1.05;
    letter-spacing: -.035em;
    font-weight: 900;
}

.fc-requirements-list {
    display: grid;
    gap: .9rem;
}

.fc-requirement {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    border: 1px solid var(--fc-border);
    border-radius: 1rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-requirement-icon {
    width: 44px;
    height: 44px;
    border-radius: .85rem;
    font-size: 1.15rem;
}

.fc-requirement-content {
    color: var(--fc-muted);
    font-size: 1.02rem;
    line-height: 1.55;
}

.fc-requirement-content strong {
    color: var(--fc-text);
}

.fc-requirement-content ul {
    margin: .5rem 0 0 1.1rem;
    padding-left: 1rem;
}

.fc-alert-box {
    margin-top: 1rem;
    padding: 1.15rem 1.25rem;
    border-radius: 1rem;
    background: var(--fc-primary);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-alert-box h3 {
    margin: 0 0 .35rem;
    color: var(--fc-white);
    font-size: 1.1rem;
    font-weight: 850;
}

.fc-alert-box p {
    margin: 0;
    color: rgba(255, 255, 255, .88);
}

.fc-card {
    overflow: hidden;
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-card-header {
    padding: 1.4rem 1.5rem;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .22), transparent 16rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
}

.fc-card-kicker {
    display: inline-flex;
    margin-bottom: .55rem;
    color: var(--fc-secondary);
    font-size: .9rem;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.fc-card-header h2 {
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(1.45rem, 3vw, 2.15rem);
    line-height: 1.1;
    font-weight: 900;
}

.fc-table-wrap {
    overflow-x: auto;
}

.fc-pricing-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
}

.fc-pricing-table th,
.fc-pricing-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--fc-border);
    text-align: left;
    vertical-align: top;
}

.fc-pricing-table th {
    color: var(--fc-primary);
    background: #f7f9fc;
    font-size: .92rem;
    font-weight: 900;
}

.fc-pricing-table th span {
    display: block;
    margin-top: .2rem;
    color: var(--fc-muted);
    font-size: .78rem;
    font-weight: 650;
}

.fc-pricing-table td {
    color: var(--fc-muted);
    font-size: .98rem;
    line-height: 1.45;
}

.fc-pricing-table td:first-child {
    color: var(--fc-text);
    font-weight: 750;
}

.fc-pricing-table tr.is-highlighted td {
    background: rgba(233, 229, 15, .18);
}

.fc-pricing-note {
    padding: 1.15rem 1.5rem;
    background: var(--fc-soft);
    color: var(--fc-muted);
    font-size: .98rem;
    line-height: 1.55;
}

.fc-pricing-note p {
    margin: 0;
}

.fc-pricing-note a {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-empty-pricing {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1.4rem;
    border: 1px dashed rgba(22, 57, 111, .28);
    border-radius: 1.15rem;
    background: var(--fc-soft);
}

.fc-empty-pricing i {
    flex: 0 0 auto;
    width: 52px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: var(--fc-white);
    color: var(--fc-primary);
    font-size: 1.35rem;
}

.fc-empty-pricing p {
    margin: 0;
    color: var(--fc-muted);
    font-size: 1.04rem;
    line-height: 1.5;
}

.fc-empty-pricing a {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-info-important {
    padding: clamp(1.25rem, 3vw, 2rem);
    border-radius: 1.25rem;
    background: var(--fc-white);
    border: 1px solid var(--fc-border);
    box-shadow: var(--fc-shadow-soft);
}

.fc-info-list {
    display: grid;
    gap: .9rem;
}

.fc-info-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    color: var(--fc-muted);
    line-height: 1.55;
}

.fc-info-row strong {
    color: var(--fc-text);
}

.fc-info-row a {
    color: var(--fc-primary);
    font-weight: 850;
    text-decoration: none;
}

.fc-info-row a:hover {
    text-decoration: underline;
}

.fc-info-icon {
    width: 42px;
    height: 42px;
    border-radius: .8rem;
    font-size: 1.05rem;
}

.fc-envios-list {
    margin: .55rem 0 0 1rem;
    padding-left: 1rem;
}

.fc-envios-list li {
    margin-bottom: .35rem;
}

.fc-envios-list span {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    margin-top: 1.25rem;
}

.fc-action-buttons a {
    color: var(--fc-primary);
    background: var(--fc-soft);
    border: 1px solid var(--fc-border);
}

.fc-action-buttons a:hover {
    transform: translateY(-2px);
    color: var(--fc-primary);
    background: var(--fc-soft-2);
}

.fc-more-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.fc-more-card {
    padding: 1.25rem;
    border: 1px solid var(--fc-border);
    border-radius: 1.15rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-more-icon {
    width: 50px;
    height: 50px;
    margin-bottom: 1rem;
    border-radius: 1rem;
    font-size: 1.25rem;
}

.fc-more-card h3 {
    margin: 0 0 .65rem;
    color: var(--fc-primary);
    font-size: 1.18rem;
    font-weight: 850;
}

.fc-more-card p {
    margin: 0;
    color: var(--fc-muted);
    line-height: 1.62;
}

.fc-assistant-card {
    display: grid;
    grid-template-columns: 190px minmax(0, 1fr);
    gap: 1.5rem;
    align-items: center;
    margin: 3rem 0;
    padding: clamp(1.25rem, 3vw, 1.75rem);
    border: 1px solid var(--fc-border);
    border-top: 6px solid var(--fc-primary);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-assistant-photo img,
.fc-assistant-placeholder {
    width: 170px;
    height: 170px;
    border-radius: 1.15rem;
    object-fit: cover;
    box-shadow: var(--fc-shadow-soft);
}

.fc-assistant-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--fc-primary);
    color: var(--fc-white);
    font-size: 3rem;
    font-weight: 900;
}

.fc-assistant-label {
    display: inline-flex;
    margin-bottom: .55rem;
    padding: .35rem .7rem;
    border-radius: 999px;
    background: var(--fc-soft);
    color: var(--fc-primary);
    font-size: .85rem;
    font-weight: 850;
}

.fc-assistant-copy h2 {
    margin: 0 0 .3rem;
    color: var(--fc-primary);
    font-size: clamp(1.55rem, 3vw, 2.2rem);
    line-height: 1.1;
    font-weight: 900;
}

.fc-assistant-role {
    display: inline-block;
    margin: 0 0 .85rem !important;
    color: var(--fc-text) !important;
    font-weight: 800;
}

.fc-assistant-copy p {
    margin: 0;
    color: var(--fc-muted);
    font-size: 1.05rem;
    line-height: 1.65;
}

.fc-assistant-copy a {
    color: var(--fc-primary);
    font-weight: 850;
    text-decoration: none;
}

.fc-assistant-copy a:hover {
    text-decoration: underline;
}

.fc-final-cta {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1.5rem;
    align-items: center;
    margin-top: 3rem;
    padding: clamp(1.5rem, 4vw, 2.4rem);
    border-radius: 1.35rem;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .30), transparent 18rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-final-cta span {
    display: inline-flex;
    margin-bottom: .5rem;
    color: var(--fc-secondary);
    font-size: .9rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.fc-final-cta h2 {
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(1.6rem, 4vw, 2.5rem);
    line-height: 1.05;
    letter-spacing: -.035em;
    font-weight: 900;
}

.fc-final-cta p {
    max-width: 680px;
    margin: .7rem 0 0;
    color: rgba(255, 255, 255, .84);
    font-size: 1.05rem;
    line-height: 1.55;
}

.fc-final-cta a {
    white-space: nowrap;
    color: var(--fc-primary-dark);
    background: var(--fc-secondary);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
}

.fc-final-cta a:hover {
    transform: translateY(-2px);
    color: var(--fc-primary-dark);
}

@media (max-width: 980px) {
    .fc-hero-inner {
        grid-template-columns: 1fr;
    }

    .fc-hero-visual {
        order: -1;
    }

    .fc-cover-card {
        width: min(100%, 340px);
        transform: none;
    }

    .fc-feature-grid,
    .fc-more-grid {
        grid-template-columns: 1fr;
    }

    .fc-final-cta {
        grid-template-columns: 1fr;
    }

    .fc-final-cta a {
        width: fit-content;
    }
}

@media (max-width: 700px) {
    .fc-hero {
        padding: 0 .75rem;
    }

    .fc-hero-inner {
        border-radius: 1rem;
        padding: 1.25rem;
    }

    .fc-page-container {
        width: min(100% - 1.5rem, 1060px);
        padding-top: 2rem;
    }

    .fc-hero-meta span,
    .fc-chip-grid span {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .fc-certifications {
        grid-template-columns: 1fr;
    }

    .fc-hero-actions {
        flex-direction: column;
    }

    .fc-primary-button,
    .fc-secondary-button,
    .fc-final-cta a,
    .fc-action-buttons a {
        width: 100%;
    }

    .fc-feature-card {
        min-height: auto;
    }

    .fc-content-wrapper {
        padding: 1.1rem;
        border-radius: 1rem;
        font-size: 1rem;
    }

    .fc-requirement,
    .fc-info-row,
    .fc-empty-pricing {
        align-items: flex-start;
    }

    .fc-assistant-card {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .fc-assistant-photo {
        display: flex;
        justify-content: center;
    }

    .fc-action-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .fc-hero h1 {
        font-size: 2rem;
    }

    .fc-eyebrow {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .fc-cover-card {
        aspect-ratio: 16 / 11;
    }

    .fc-intermediate-card,
    .fc-requirement {
        flex-direction: column;
    }

    .fc-info-row {
        gap: .75rem;
    }

    .fc-table-wrap {
        margin-left: -1rem;
        margin-right: -1rem;
    }
}
</style>

<?php
get_footer();
