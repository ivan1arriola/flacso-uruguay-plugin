<?php
/**
 * FLACSO – Docentes (cards consistentes + CV siempre visible)
 * Requisitos:
 * - Igual en móvil y escritorio (no cambia layout)
 * - Sin “Ver CV” (no details/summary)
 * - CV visible por defecto
 * - Sin descripción del posgrado (solo docentes)
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('FLACSO_Docentes_Consistente')):
class FLACSO_Docentes_Consistente {

    public static function init() {
        add_action('after_setup_theme', [__CLASS__, 'setup_images']);
        add_action('wp_head', [__CLASS__, 'print_scoped_css'], 50);
        add_shortcode('dp_docentes_grid',      [__CLASS__, 'sc_docentes_grid']);
        add_shortcode('dp_docente_destacado', [__CLASS__, 'sc_docente_destacado']);
        // Legacy shortcode intentionally disabled. Use block flacso/docentes-grupo.
    }

    public static function setup_images() {
        add_theme_support('post-thumbnails');
        add_image_size('docente_grid', 160, 160, true);
        add_image_size('docente_destacado', 220, 220, true);
    }

    /* ================= HELPERS ================= */

    private static function nombre_completo($post_id) {
        $nombre = (string)get_post_meta($post_id, 'nombre', true);
        $apellido = (string)get_post_meta($post_id, 'apellido', true);
        if ($nombre && $apellido) return trim($nombre . ' ' . $apellido);

        $titulo = get_the_title($post_id);
        return $titulo ? $titulo : (string)get_post_field('post_title', $post_id);
    }

    private static function safe_cv_html($html) {
        $allowed = [
            'p' => [], 'br' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'strong' => [], 'em' => [], 'b' => [], 'i' => [],
            'h3' => [], 'h4' => [], 'h5' => [],
            'a' => ['href' => [], 'target' => [], 'rel' => []],
        ];
        return wp_kses($html, $allowed);
    }

    private static function get_cv_full($post_id) {
        $cv_raw = (string)get_post_meta($post_id, 'cv', true);
        if (!$cv_raw) return '';
        $full = wpautop(trim($cv_raw));
        return self::safe_cv_html($full);
    }

    private static function initials_from_name($post_id, $fallback_title) {
        $nombre = (string)get_post_meta($post_id, 'nombre', true);
        $apellido = (string)get_post_meta($post_id, 'apellido', true);

        $inic = 'FL';
        if ($nombre && $apellido) {
            $inic = mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1);
        } elseif ($nombre) {
            $inic = mb_substr($nombre, 0, 2);
        } else {
            $words = preg_split('/\s+/', trim((string)$fallback_title), 3);
            if (count($words) >= 2) $inic = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
            else $inic = mb_substr((string)$fallback_title, 0, 2);
        }
        return strtoupper($inic);
    }

    private static function avatar_markup($post_id, $titulo, $size_px = 88, $variant = 'grid') {
        $size = ($variant === 'destacado') ? 'docente_destacado' : 'docente_grid';

        if (has_post_thumbnail($post_id)) {
            $img_id = get_post_thumbnail_id($post_id);
            return wp_get_attachment_image($img_id, $size, false, [
                'class' => 'fdc-avatar-img',
                'alt' => esc_attr($titulo),
                'loading' => 'lazy',
                'decoding' => 'async',
                'style' => "width: {$size_px}px; height: {$size_px}px;"
            ]);
        }

        $inic = self::initials_from_name($post_id, $titulo);
        return '<div class="fdc-avatar-fallback" aria-hidden="true" style="width:' . intval($size_px) . 'px;height:' . intval($size_px) . 'px;">'
            . esc_html($inic) .
        '</div>';
    }

    private static function build_card($id, $atts) {
        $nombre = self::nombre_completo($id);
        $prefijo = (string)get_post_meta($id, 'prefijo_full', true);
        $cv_full = self::get_cv_full($id);

        $label_id = 'fdc-doc-' . $id . '-' . wp_rand(1000, 9999);

        ob_start(); ?>
            <article class="fdc-card" aria-labelledby="<?php echo esc_attr($label_id); ?>">
                <div class="fdc-top">
                    <div class="fdc-avatar">
                        <?php echo self::avatar_markup($id, $nombre, 88, 'grid'); ?>
                    </div>

                    <div class="fdc-meta">
                        <h3 class="fdc-name" id="<?php echo esc_attr($label_id); ?>">
                            <?php echo esc_html($nombre); ?>
                        </h3>

                        <?php if ($prefijo): ?>
                            <div class="fdc-role"><?php echo esc_html($prefijo); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($cv_full): ?>
                    <div class="fdc-cv">
                        <?php echo $cv_full; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php
        return ob_get_clean();
    }

    /* ================= CSS ================= */

    public static function print_scoped_css() { ?>
        <style id="flacso-docentes-consistente-css">
            .flacso-doc-consistente{
                --p1: var(--global-palette1, #1d3a72);
                --p2: var(--global-palette2, #fed222);
                --p4: var(--global-palette4, #2e2f34);
                --p5: var(--global-palette5, #7a8696);
                --p7: var(--global-palette7, #e9edf2);
                --p8: var(--global-palette8, #f6f8fb);
                --p9: var(--global-palette9, #ffffff);

                --radius: 16px;
                --shadow: 0 14px 40px rgba(16, 24, 40, 0.10);

                font-family: var(--global-body-font-family, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif);
                color: var(--p4);
                line-height: 1.6;
            }
            .flacso-doc-consistente *{ box-sizing:border-box; }

            .flacso-doc-consistente .fdc-wrap{
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 18px;
            }

            /* NOTA: SIN HEADER/descripcion por diseño (para no “descripción del posgrado”) */
            .flacso-doc-consistente .fdc-grid{
                display: grid;
                grid-template-columns: repeat(12, 1fr);
                gap: 18px;
                margin: 18px 0 42px;
            }

            .flacso-doc-consistente .fdc-grid > .fdc-card{ grid-column: span 6; }
            @media (max-width: 960px){ .flacso-doc-consistente .fdc-grid > .fdc-card{ grid-column: span 12; } }

            .flacso-doc-consistente .fdc-card{
                background: var(--p9);
                border: 1px solid rgba(233,237,242,1);
                border-radius: var(--radius);
                padding: 16px;
                position: relative;
                overflow: hidden;
                box-shadow: 0 0 0 rgba(0,0,0,0);
                transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            }
            .flacso-doc-consistente .fdc-card::before{
                content:"";
                position:absolute;
                inset:0 0 auto 0;
                height: 3px;
                background: linear-gradient(90deg, rgba(254,210,34,.95), rgba(254,210,34,0));
            }
            .flacso-doc-consistente .fdc-card:hover{
                transform: translateY(-2px);
                box-shadow: var(--shadow);
                border-color: rgba(29,58,114,.22);
            }

            /* CLAVE: mismo layout siempre. No hay “modo móvil” distinto. */
            .flacso-doc-consistente .fdc-top{
                display: grid;
                grid-template-columns: 88px 1fr;
                gap: 14px;
                align-items: center;
                margin-top: 6px;
            }

            .flacso-doc-consistente .fdc-avatar{
                width: 88px;
                height: 88px;
                border-radius: 999px;
            }
            .flacso-doc-consistente .fdc-avatar-img{
                width: 88px;
                height: 88px;
                border-radius: 999px;
                object-fit: cover;
                display:block;
                background: var(--p8);
                border: 2px solid rgba(233,237,242,.95);
            }
            .flacso-doc-consistente .fdc-avatar-fallback{
                width: 88px;
                height: 88px;
                border-radius: 999px;
                display:grid;
                place-items:center;
                background:
                    radial-gradient(120px 120px at 30% 30%, rgba(254,210,34,.30), rgba(29,58,114,.10)),
                    linear-gradient(135deg, rgba(29,58,114,.95), rgba(17,89,175,.90));
                color: var(--p9);
                font-weight: 850;
                font-size: 1.5rem;
                border: 2px solid rgba(233,237,242,.95);
            }

            .flacso-doc-consistente .fdc-meta{ min-width:0; }

            .flacso-doc-consistente .fdc-name{
                margin: 0;
                font-family: var(--global-heading-font-family, inherit);
                font-weight: 820;
                letter-spacing: -0.015em;
                color: var(--p1);
                font-size: 1.15rem;
                line-height: 1.2;
                text-wrap: balance;
                hyphens: auto;
                overflow-wrap: anywhere;
            }

            .flacso-doc-consistente .fdc-role{
                margin-top: 6px;
                color: var(--p5);
                font-weight: 650;
                font-size: .96rem;
                line-height: 1.35;
            }

            /* CV SIEMPRE VISIBLE */
            .flacso-doc-consistente .fdc-cv{
                margin-top: 14px;
                padding: 14px 14px;
                border-radius: 12px;
                background: rgba(246,248,251,.70);
                border: 1px solid rgba(233,237,242,1);
                color: var(--p4);
                font-size: .98rem;
            }
            .flacso-doc-consistente .fdc-cv p{ margin: 0 0 12px; }
            .flacso-doc-consistente .fdc-cv ul,
            .flacso-doc-consistente .fdc-cv ol{ margin: 0 0 12px 20px; padding: 0; }
            .flacso-doc-consistente .fdc-cv li{ margin: 0 0 6px; }
            .flacso-doc-consistente .fdc-cv strong{ color: var(--p1); font-weight: 750; }

            /* Opcional: si querés 1 columna en mobile sí o sí, descomentá:
            @media (max-width: 960px){ .flacso-doc-consistente .fdc-grid > .fdc-card{ grid-column: span 12; } }
            */
        </style>
    <?php }

    /* ================= SHORTCODES ================= */

    // Importante: NO imprimimos titulo/descripcion aquí (para que no salga "descripción del posgrado").
    public static function sc_docentes_grid($atts) {
        $atts = shortcode_atts([
            'equipo' => '',
            'cantidad' => -1,
            'orden' => 'nombre',
        ], $atts);

        $args = [
            'post_type' => 'docente',
            'posts_per_page' => intval($atts['cantidad']),
            'no_found_rows' => true,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if ($atts['orden'] === 'apellido') {
            $args['meta_key'] = 'apellido';
            $args['orderby'] = 'meta_value';
        }

        if (!empty($atts['equipo'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'equipo-docente',
                'field' => 'slug',
                'terms' => sanitize_title($atts['equipo']),
            ]];
        }

        $q = new WP_Query($args);

        if (!$q->have_posts()) {
            return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="padding:22px 18px;text-align:center;color:#666;">No se encontraron docentes.</div></div>';
        }

        ob_start(); ?>
            <section class="flacso-doc-consistente" aria-label="Docentes">
                <div class="fdc-wrap">
                    <div class="fdc-grid">
                        <?php while ($q->have_posts()): $q->the_post();
                            echo self::build_card(get_the_ID(), ['mostrar_cv' => 'completo']);
                        endwhile; ?>
                    </div>
                </div>
            </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function sc_docente_destacado($atts) {
        $atts = shortcode_atts([
            'slug' => '',
        ], $atts);

        $slug = sanitize_title($atts['slug']);
        if (!$slug) return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="padding:18px;color:#b82105;text-align:center;">⚠️ Falta el slug del docente.</div></div>';

        $post = get_page_by_path($slug, OBJECT, 'docente');
        if (!$post) return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="padding:18px;color:#b82105;text-align:center;">❌ Docente no encontrado.</div></div>';

        // Destacado: reutiliza card (mismo look)
        return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="margin:18px auto 42px;">'
            . self::build_card((int)$post->ID, ['mostrar_cv' => 'completo'])
            . '</div></div>';
    }

    public static function sc_docentes_equipo($atts) {
        $atts = shortcode_atts([
            'slug' => '',
            'cantidad' => -1,
            'orden' => 'nombre',
        ], $atts);

        $slug = sanitize_title($atts['slug']);
        if (!$slug) return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="padding:18px;color:#b82105;text-align:center;">⚠️ Falta el slug del equipo.</div></div>';

        $term = get_term_by('slug', $slug, 'equipo-docente');
        if (!$term || is_wp_error($term)) return '<div class="flacso-doc-consistente"><div class="fdc-wrap" style="padding:18px;color:#b82105;text-align:center;">❌ Equipo no encontrado.</div></div>';

        return self::sc_docentes_grid([
            'equipo' => $slug,
            'cantidad' => $atts['cantidad'],
            'orden' => $atts['orden'],
        ]);
    }
}

FLACSO_Docentes_Consistente::init();
endif;
