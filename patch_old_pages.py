import re

with open("modules/oferta-academica/includes/class-cpt-oferta-academica.php", "r") as f:
    content = f.read()

replacement = """        register_post_type('oferta-academica', $args);
        add_filter('post_type_link', [self::class, 'oferta_academica_permalink'], 10, 2);
        
        // REGLA PARA PÁGINAS LEGACY (_old)
        // Evita que el CPT secuestre las páginas de WordPress que terminan en _old
        add_action('init', function() {
            add_rewrite_rule(
                '^formacion/([^/]+)/([^/]+_old)/?$',
                'index.php?pagename=formacion/$matches[1]/$matches[2]',
                'top'
            );
        }, 11);
    }"""

content = re.sub(r"        register_post_type\('oferta-academica', \$args\);\n        add_filter\('post_type_link', \[self::class, 'oferta_academica_permalink'\], 10, 2\);\n    \}", replacement, content)

with open("modules/oferta-academica/includes/class-cpt-oferta-academica.php", "w") as f:
    f.write(content)
