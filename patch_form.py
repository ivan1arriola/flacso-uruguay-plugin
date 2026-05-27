import re

with open("modules/main-page/includes/flacso-consultas.php", "r") as f:
    content = f.read()

replacement = """	$mostrar_pre     = wp_validate_boolean( $atts['mostrar_preinscripcion'] );

	// ADAPTACIÓN CPT: Solo mostrar si las inscripciones están abiertas
	if ( $mostrar_pre && get_post_type($id_pagina) === 'oferta-academica' ) {
		$abiertas = get_post_meta($id_pagina, 'inscripciones_abiertas', true);
		$mostrar_pre = ($abiertas === '1' || $abiertas === 'true' || $abiertas === true || $abiertas === 1);
	}

	if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {"""

content = re.sub(r"	\$mostrar_pre     = wp_validate_boolean\( \$atts\['mostrar_preinscripcion'\] \);\n\n	if \( ! wp_script_is\( 'jquery', 'enqueued' \) \) \{", replacement, content)

with open("modules/main-page/includes/flacso-consultas.php", "w") as f:
    f.write(content)
