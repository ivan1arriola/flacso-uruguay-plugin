<?php

$admin_file = dirname(__DIR__) . '/modules/consultas/includes/class-flacso-consultas-admin.php';
$admin = file_get_contents($admin_file);

if ($admin === false) {
    fwrite(STDERR, "FAIL: no se pudo leer la vista administrativa de consultas\n");
    exit(1);
}

function inbox_view_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

inbox_view_assert(strpos($admin, 'flacso-cp-inbox-table') !== false, 'la bandeja debe usar la clase de tabla compacta');
inbox_view_assert(strpos($admin, '<th>Oferta / Cohorte</th>') !== false, 'oferta y cohorte deben compartir columna');
inbox_view_assert(strpos($admin, '<th>Estado de la consulta</th>') !== false, 'la columna de estado debe tener un nombre claro');
inbox_view_assert(strpos($admin, '<th>Cohorte</th>') === false, 'no debe existir una columna Cohorte independiente');
inbox_view_assert(strpos($admin, '<th>Al consultar</th>') === false, 'no debe existir el encabezado ambiguo Al consultar');
inbox_view_assert(strpos($admin, 'flacso-cp-inbox-offer') !== false, 'la oferta debe tener una presentación compacta');
inbox_view_assert(strpos($admin, 'flacso-cp-inbox-actions') !== false, 'las acciones deben tener una columna identificable');

inbox_view_assert(strpos($admin, "wp_ajax_flacso_consultas_retry_email") !== false, 'el botón Reenviar debe tener endpoint AJAX registrado');
inbox_view_assert(strpos($admin, "wp_ajax_flacso_consultas_trigger_followup") !== false, 'el seguimiento manual debe tener endpoint AJAX registrado');
inbox_view_assert(strpos($admin, "array( 'failed', 'blocked' )") !== false, 'Reenviar debe limitarse a estados failed o blocked');
inbox_view_assert(strpos($admin, "claim_manual_retry") !== false, 'Reenviar debe reservar la entrega transaccional antes de procesarla');
inbox_view_assert(strpos($admin, "Mautic aceptó el correo transaccional") !== false, 'Reenviar debe informar aceptación transaccional de Mautic');

echo "OK consultas-inbox-view-contract-test\n";
