import re

with open("modules/oferta-academica/includes/class-cpt-oferta-academica.php", "r") as f:
    content = f.read()

replacement = """        ];

        register_post_type('oferta-academica', $args);
        add_filter('post_type_link', [self::class, 'oferta_academica_permalink'], 10, 2);
    }

    /**
     * Reemplaza el tag %tipo-oferta-academica% en el permalink con el plural del término asociado.
     */
    public static function oferta_academica_permalink($post_link, $post) {
        if (is_object($post) && $post->post_type === 'oferta-academica') {
            if (strpos($post_link, '%tipo-oferta-academica%') !== false) {
                $terms = wp_get_object_terms($post->ID, 'tipo-oferta-academica');
                if (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) {
                    $slug = $terms[0]->slug;
                    
                    // Pluralizar el slug
                    $plural_slug = $slug;
                    if ($slug === 'maestria') {
                        $plural_slug = 'maestrias';
                    } elseif ($slug === 'especializacion') {
                        $plural_slug = 'especializaciones';
                    } elseif ($slug === 'diplomado') {
                        $plural_slug = 'diplomados';
                    } elseif ($slug === 'diploma') {
                        $plural_slug = 'diplomas';
                    } elseif (substr($slug, -1) === 'a' || substr($slug, -1) === 'o' || substr($slug, -1) === 'e') {
                        $plural_slug = $slug . 's';
                    } elseif (substr($slug, -1) === 'n') {
                        $plural_slug = $slug . 'es';
                    }
                    
                    $post_link = str_replace('%tipo-oferta-academica%', $plural_slug, $post_link);
                } else {
                    $post_link = str_replace('%tipo-oferta-academica%', 'otros', $post_link);
                }
            }
        }
        return $post_link;
    }
}
"""

content = re.sub(r"        \];\n\n        register_post_type\('oferta-academica', \$args\);\n    }\n}\n?", replacement, content)

with open("modules/oferta-academica/includes/class-cpt-oferta-academica.php", "w") as f:
    f.write(content)
