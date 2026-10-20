<?php
/**
 * Contrato de independencia de los correos de la Oferta Académica.
 * Evita reutilizar silenciosamente el nuevo valor como correo operativo.
 */
$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
$offer = file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-academica.php');
$repo = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');
$cohort = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$inquiries = file_get_contents($root . '/modules/consultas/services/class-flacso-inquiry-context-service.php');

function offer_email_contract_assert(bool $ok, string $label): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
offer_email_contract_assert(strpos($admin, "'correo', 'correo_oferta'") !== false, 'ambos correos son campos independientes en datos generales');
offer_email_contract_assert(strpos($admin, "__('Correo de la Oferta Académica',") !== false, 'campo nuevo identificado claramente');
offer_email_contract_assert(strpos($admin, "'correo_oferta',") !== false, 'campo nuevo editable');
offer_email_contract_assert(strpos($admin, "Correo de contacto") !== false, 'correo histórico continúa visible');
offer_email_contract_assert(strpos($offer, "'correo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email']") !== false, 'se conserva el metadato de contacto');
offer_email_contract_assert(strpos($offer, "'correo_oferta' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email']") !== false, 'correo de oferta registrado');
offer_email_contract_assert(strpos($repo, "'abreviacion', 'correo', 'correo_oferta',") !== false, 'API expone ambas propiedades');
offer_email_contract_assert(strpos($repo, "array_key_exists('correo_oferta', \$payload)") !== false, 'API valida únicamente cuando recibe el nuevo campo');
offer_email_contract_assert(strpos($repo, "'invalid_offer_email'") !== false, 'API rechaza correo inválido');
offer_email_contract_assert(strpos($admin, "array_key_exists('correo_oferta', \$data)") !== false, 'formulario no borra el nuevo campo al no recibirlo');
offer_email_contract_assert(strpos($admin, "is_email(\$offer_email)") !== false, 'valida sintaxis del correo en el admin');
offer_email_contract_assert(strpos($admin, "update_post_meta(\$post_id, 'correo_oferta', \$offer_email)") !== false, 'guarda el correo nuevo por separado');
offer_email_contract_assert(strpos($admin, "delete_post_meta(\$post_id, 'correo_oferta')") !== false, 'se puede limpiar el campo opcional');
offer_email_contract_assert(strpos($admin, 'se conservó el valor anterior') !== false, 'avisos cuando es inválido');
offer_email_contract_assert(strpos($inquiries, "'replyToEmail'         => \$catalog_data['correo']") !== false, 'consulta continúa usando el correo anterior, sin alteraciones');
offer_email_contract_assert(strpos($cohort, "esc_html_e('Descripción de la cursada',") !== false, 'nuevo nombre de la etiqueta del editor de cohortes');
offer_email_contract_assert(strpos($cohort, 'name="modalidad_descripcion"') !== false, 'el editor conserva la clave interna y datos anteriores');
offer_email_contract_assert(strpos($cohort, "'modalidad_descripcion' => 'wp_kses_post'") !== false, 'el guardado de la descripción sigue vigente');
offer_email_contract_assert(strpos($cohort, "esc_html_e('Descripción de modalidad',") === false, 'etiqueta anterior no aparece en editor');

echo "OK: correo de oferta opcional, independiente y descripción de cursada\n";
