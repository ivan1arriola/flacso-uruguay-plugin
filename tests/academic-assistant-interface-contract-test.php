<?php

$root = dirname(__DIR__);
$assistant = file_get_contents($root . '/includes/core/class-flacso-academic-assistant.php');
$panel = file_get_contents($root . '/includes/core/class-flacso-admin-panel.php');
$styles = file_get_contents($root . '/includes/assets/flacso-academic-assistant.css');

function assistant_interface_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

assistant_interface_assert(
    strpos($panel, 'FLACSO_Academic_Assistant::ACCESS') !== false,
    'el Panel FLACSO debe autorizarse con la capability académica'
);
assistant_interface_assert(
    strpos($panel, 'FLACSO_Academic_Assistant::is_assistant()') !== false,
    'el Panel FLACSO debe tener una vista específica para asistentes'
);
assistant_interface_assert(
    strpos($panel, 'render_assistant_view') !== false,
    'el panel debe separar la vista operativa de asistentes'
);
assistant_interface_assert(
    strpos($panel, 'FLACSO_Academic_Assistant::sala_virtual_url()') !== false,
    'la vista de asistentes debe ofrecer Sala Virtual'
);
assistant_interface_assert(
    strpos($panel, 'edit.php?post_type=docente') !== false,
    'la vista de asistentes debe enlazar a Docentes mediante su pantalla nativa'
);
assistant_interface_assert(
    strpos($assistant, 'assistant_admin_bar_items') !== false,
    'el admin bar debe tener una navegación reducida para asistentes'
);
assistant_interface_assert(
    strpos($panel, 'flacso-academic-assistant__quick-links') !== false,
    'la vista del panel debe contar con accesos rápidos'
);
assistant_interface_assert(
    strpos($panel, 'flacso-academic-assistant__quick-action') !== false,
    'los accesos rápidos deben indicar la acción que ejecutan'
);
assistant_interface_assert(
    strpos($panel, 'Gestionar ofertas') !== false && strpos($panel, 'Gestionar seminarios') !== false,
    'el encabezado debe ofrecer acciones principales con verbos claros'
);
assistant_interface_assert(
    strpos($styles, 'flacso-academic-assistant__quick-links') !== false,
    'los accesos rápidos deben tener estilos propios'
);
assistant_interface_assert(
    strpos($styles, 'focus-visible') !== false && strpos($styles, 'max-width: 782px') !== false,
    'los controles deben contemplar foco visible y pantallas angostas'
);

fwrite(STDOUT, "OK academic assistant interface contract\n");
