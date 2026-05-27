import re

with open("modules/main-page/includes/blocks/inscripciones-banner/includes/class-flacso-inscripciones-banner-block.php", "r") as f:
    content = f.read()

replacement = """        if ($post instanceof WP_Post) {
            $post_title = get_the_title($post);
            if (has_post_thumbnail($post->ID)) {
                $featured_url = (string) get_the_post_thumbnail_url($post->ID, 'full');
            }
            
            // Si es un CPT oferta-academica, forzar datos dinámicos
            if ($post->post_type === 'oferta-academica') {
                $abiertas = get_post_meta($post->ID, 'inscripciones_abiertas', true);
                $is_abiertas = ($abiertas === '1' || $abiertas === 'true' || $abiertas === true || $abiertas === 1);
                $tag_text = $is_abiertas ? 'Inscripciones 2026' : 'Próximamente';
                
                // Generar URL de preinscripción dinámicamente
                if ($is_abiertas) {
                    $preinsc_url = trailingslashit(get_permalink($post->ID)) . 'preinscripcion/';
                    $cta_text = 'Descuentos especiales disponibles. <a href="'. esc_url($preinsc_url) .'" style="color:white; text-decoration:underline;">Solicitá información e inscribite hoy.</a>';
                } else {
                    $cta_text = 'Mantente atento a nuestras próximas aperturas.';
                }
            }
        }"""

content = re.sub(r"        if \(\$post instanceof WP_Post\) \{\n            \$post_title = get_the_title\(\$post\);\n            if \(has_post_thumbnail\(\$post->ID\)\) \{\n                \$featured_url = \(string\) get_the_post_thumbnail_url\(\$post->ID, 'full'\);\n            \}\n        \}", replacement, content)

with open("modules/main-page/includes/blocks/inscripciones-banner/includes/class-flacso-inscripciones-banner-block.php", "w") as f:
    f.write(content)
