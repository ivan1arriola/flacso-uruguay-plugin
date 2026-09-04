<?php

$root = dirname(__DIR__);
$cpt_file = $root . '/modules/oferta-academica/includes/class-cpt-tabla-precio.php';
$admin_mode_file = $root . '/includes/core/class-flacso-editor-admin-mode.php';

function price_table_editor_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

price_table_editor_assert(file_exists($cpt_file), 'debe existir el CPT de tablas de precios');
price_table_editor_assert(file_exists($admin_mode_file), 'debe existir la capa de modo editor');

$cpt = (string) file_get_contents($cpt_file);
$admin_mode = (string) file_get_contents($admin_mode_file);

price_table_editor_assert(strpos($cpt, 'filter_edit_post_link') === false, 'editar una tabla no debe reemplazar el enlace de WordPress');
price_table_editor_assert(strpos($cpt, 'redirect_add_new') === false, 'crear una tabla no debe redirigir al editor externo');
price_table_editor_assert(strpos($cpt, 'flacso_external_editor_url') === false, 'las tablas no deben depender del editor externo');
price_table_editor_assert(strpos($cpt, "'supports' => ['title', 'revisions']") !== false, 'el CPT debe ocultar custom-fields crudos');

price_table_editor_assert(strpos($cpt, "add_action('add_meta_boxes'") !== false, 'debe registrar una interfaz de edición propia');
price_table_editor_assert(strpos($cpt, 'render_editor_box') !== false, 'debe renderizar el editor de precios');
price_table_editor_assert(strpos($cpt, 'save_price_table') !== false, 'debe guardar desde WordPress');
price_table_editor_assert(strpos($cpt, 'wp_nonce_field') !== false && strpos($cpt, 'wp_verify_nonce') !== false, 'el guardado debe estar protegido por nonce');
price_table_editor_assert(strpos($cpt, "current_user_can('edit_post'") !== false, 'el guardado debe validar permisos');

foreach (['tabla_precios_tipo', 'precios_filas', 'precios_nota', 'mostrar_precios_dolares'] as $field) {
    price_table_editor_assert(strpos($cpt, $field) !== false, "el editor debe gestionar {$field}");
}

price_table_editor_assert(strpos($cpt, 'data-add-price-row') !== false, 'debe permitir añadir filas');
price_table_editor_assert(strpos($cpt, 'data-remove-price-row') !== false, 'debe permitir eliminar filas');
price_table_editor_assert(strpos($cpt, 'data-move-row="up"') !== false && strpos($cpt, 'data-move-row="down"') !== false, 'debe permitir reordenar filas');
price_table_editor_assert(strpos($cpt, 'flacso_price_featured') !== false, 'debe permitir elegir una fila principal');
price_table_editor_assert(strpos($cpt, 'data-usd-toggle') !== false, 'debe permitir mostrar u ocultar USD sin borrar datos');
price_table_editor_assert(strpos($cpt, '@media (max-width: 782px)') !== false, 'el editor debe ser usable en móvil');
price_table_editor_assert(strpos($cpt, 'FLACSO_Price_Table_Repository::linked_uses') !== false, 'debe mostrar y proteger los usos de la tabla');

preg_match('/private const MANAGED_POST_TYPES = \[(.*?)\];/s', $admin_mode, $managed_match);
$managed_types = $managed_match[1] ?? '';
price_table_editor_assert(strpos($managed_types, "'tabla-precio'") === false, 'el modo editor externo no debe ocultar tabla-precio en wp-admin');

 echo "OK price-table-wordpress-editor-contract-test\n";
