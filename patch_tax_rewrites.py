import re

with open("modules/oferta-academica/includes/class-oferta-taxonomies.php", "r") as f:
    content = f.read()

replacement = """    public static function register_rewrite_rules(): void {
        $tipos = [
            'maestrias' => 'maestria',
            'especializaciones' => 'especializacion',
            'diplomados' => 'diplomado',
            'diplomas' => 'diploma'
        ];
        
        foreach ($tipos as $plural => $singular) {
            // Regla para paginación
            add_rewrite_rule(
                '^formacion/' . $plural . '/page/?([0-9]{1,})/?$',
                'index.php?tipo-oferta-academica=' . $singular . '&paged=$matches[1]',
                'top'
            );
            // Regla principal
            add_rewrite_rule(
                '^formacion/' . $plural . '/?$',
                'index.php?tipo-oferta-academica=' . $singular,
                'top'
            );
        }
    }"""

content = re.sub(r"    public static function register_rewrite_rules\(\): void \{\n        add_rewrite_rule\('\^formacion/maestrias/\?\$', 'index\.php\?tipo-oferta-academica=maestria', 'top'\);\n        add_rewrite_rule\('\^formacion/especializaciones/\?\$', 'index\.php\?tipo-oferta-academica=especializacion', 'top'\);\n        add_rewrite_rule\('\^formacion/diplomados/\?\$', 'index\.php\?tipo-oferta-academica=diplomado', 'top'\);\n        add_rewrite_rule\('\^formacion/diplomas/\?\$', 'index\.php\?tipo-oferta-academica=diploma', 'top'\);\n    \}", replacement, content)

with open("modules/oferta-academica/includes/class-oferta-taxonomies.php", "w") as f:
    f.write(content)
