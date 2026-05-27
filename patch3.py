import re

with open("modules/preinscripcion/includes/trait-templates.php", "r") as f:
    content = f.read()

replacement1 = """        // Verificar si es una página u oferta con preinscripción activa.
        if (is_singular('page') && !$this->es_pagina_preinscripcion_activa($post->ID)) return;
        if (is_singular('oferta-academica')) {
            $abiertas = get_post_meta($post->ID, 'inscripciones_abiertas', true);
            if ($abiertas !== '1' && $abiertas !== 'true' && $abiertas !== true && $abiertas !== 1) {
                return;
            }
        }"""
content = re.sub(r"        // Verificar si es una página con preinscripción activa\.\n        if \(!\$this->es_pagina_preinscripcion_activa\(\$post->ID\)\) return;", replacement1, content)


replacement2 = """        if ($es_preinscripcion && (is_singular('page') || is_singular('oferta-academica')) && $post) {
            // Verificar si esta página tiene preinscripción activa
            $activa = false;
            if (is_singular('page')) {
                $activa = $this->es_pagina_preinscripcion_activa($post->ID);
            } else {
                $abiertas = get_post_meta($post->ID, 'inscripciones_abiertas', true);
                $activa = ($abiertas === '1' || $abiertas === 'true' || $abiertas === true || $abiertas === 1);
            }

            if ($activa) {"""
content = re.sub(r"        if \(\$es_preinscripcion && is_singular\('page'\) && \$post\) \{\n            // Verificar si esta página tiene preinscripción activa\n            if \(\$this->es_pagina_preinscripcion_activa\(\$post->ID\)\) \{", replacement2, content)

with open("modules/preinscripcion/includes/trait-templates.php", "w") as f:
    f.write(content)
