<?php
/**
 * Contrato responsive del formulario de Cohorte en WordPress admin.
 * Verifica las causas del desbordamiento (intrinsic sizing), no lo oculta.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$shared = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-admin-ui.php');

function cohort_responsive_assert(bool $condition, string $description): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $description . "\n");
        exit(1);
    }
}

cohort_responsive_assert(strpos($source, "add_meta_box(\n            'flacso_cohorte_meta',") !== false, 'el metabox real usa el ID cubierto por los estilos');
cohort_responsive_assert(strpos($source, 'flacso-academic-editor flacso-cohort-editor') !== false, 'se conserva el contenedor institucional');
cohort_responsive_assert(strpos($source, '#flacso_cohorte_meta .flacso-cohort-editor') !== false, 'el CSS se restringe a Cohortes');
cohort_responsive_assert(strpos($source, 'grid-template-columns:minmax(0,1fr)') !== false, 'el grid principal no depende de anchos min-content');
cohort_responsive_assert(strpos($source, '#flacso_cohorte_meta .flacso-academic-workspace h3') !== false, 'el título largo se adapta al ancho');
cohort_responsive_assert(strpos($source, 'overflow-wrap:anywhere') !== false, 'texto de ofertas con nombres largos puede cortar línea');
cohort_responsive_assert(strpos($source, 'white-space:normal') !== false, 'pills y botones admiten varias líneas');
cohort_responsive_assert(strpos($source, '#flacso_cohorte_meta .flacso-academic-field select') !== false, 'selectores de títulos extensos respetan el contenedor');
cohort_responsive_assert(strpos($source, 'box-sizing:border-box') !== false, 'el padding se incluye en el ancho');
cohort_responsive_assert(strpos($source, '@media(max-width:782px)') !== false, 'estilos específicos para móvil');
cohort_responsive_assert(strpos($source, 'grid-template-columns:36px minmax(0,1fr)') !== false, 'encabezados de sección con dos columnas adaptables');
cohort_responsive_assert(strpos($source, 'grid-row:2;justify-self:start') !== false, 'estado pasa a segunda línea móvil');
cohort_responsive_assert(strpos($source, 'overflow-x:hidden') === false, 'no tapa el desbordamiento ocultando contenido');
cohort_responsive_assert(strpos($source, 'name="oferta_academica_id"') !== false, 'se conserva Oferta padre');
cohort_responsive_assert(strpos($source, 'name="numero_cohorte"') !== false, 'se conserva número de Cohorte');
cohort_responsive_assert(strpos($source, 'name="tabla_precio_id"') !== false, 'se conserva Tabla de Aranceles');
cohort_responsive_assert(strpos($shared, "self::is_static_editor() ? '</div></section>'") !== false, 'las secciones continúan permanentes, sin acordeones');
echo "OK: cohort responsive admin contract\n";
