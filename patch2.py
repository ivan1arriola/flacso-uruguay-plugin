import re

with open("modules/preinscripcion/includes/class-formulario-preinscripcion.php", "r") as f:
    content = f.read()

replacement = """    /**
     * Registra rewrite rules para páginas virtuales de preinscripción
     */
    public function registrar_rewrite_rules() {
        // Captura /formacion/tipo/slug/preinscripcion/ para el CPT
        add_rewrite_rule(
            '^formacion/[^/]+/([^/]+)/preinscripcion/?$',
            'index.php?oferta-academica=$matches[1]&es_preinscripcion=1',
            'top'
        );
        
        // Captura /formacion/tipo/slug/carta/ para el CPT
        add_rewrite_rule(
            '^formacion/[^/]+/([^/]+)/carta/?$',
            'index.php?oferta-academica=$matches[1]&es_carta=1',
            'top'
        );

        // Captura /cualquier-pagina/preinscripcion/ como página virtual (Legacy)
        add_rewrite_rule(
            '^(.+?)/preinscripcion/?$',
            'index.php?pagename=$matches[1]&es_preinscripcion=1',
            'top'
        );
        
        // Captura /cualquier-pagina/carta/ como página virtual (Legacy)
        add_rewrite_rule(
            '^(.+?)/carta/?$',
            'index.php?pagename=$matches[1]&es_carta=1',
            'top'
        );
    }

    /**
     * Agrega variables de consulta personalizadas
     */
    public function agregar_query_vars($vars) {
        $vars[] = 'es_preinscripcion';
        $vars[] = 'es_carta';
        return $vars;
    }"""

content = re.sub(r"    /\*\*\n     \* Registra rewrite rules para páginas virtuales de preinscripción\n     \*/\n    public function registrar_rewrite_rules\(\) \{\n        // Captura /cualquier-pagina/preinscripcion/ como página virtual\n        add_rewrite_rule\(\n            '\^\(\.\+\?\)/preinscripcion/\?\$',\n            'index\.php\?pagename=\$matches\[1\]&es_preinscripcion=1',\n            'top'\n        \);\n    \}\n\n    /\*\*\n     \* Agrega variables de consulta personalizadas\n     \*/\n    public function agregar_query_vars\(\$vars\) \{\n        \$vars\[\] = 'es_preinscripcion';\n        return \$vars;\n    \}", replacement, content)

with open("modules/preinscripcion/includes/class-formulario-preinscripcion.php", "w") as f:
    f.write(content)
