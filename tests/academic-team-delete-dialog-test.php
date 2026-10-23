<?php
/**
 * Contrato del diálogo de eliminación de equipos docentes.
 * Impide regresar a window.confirm() y protege la coordinación obligatoria.
 */
$editor = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-team-editor.php');
$script = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/assets/js/academic-team-editor.js');

function academic_team_dialog_assert(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: " . $message . "\n");
        exit(1);
    }
}

academic_team_dialog_assert(strpos($editor, 'window.confirm(') === false, 'el editor activo no usa alertas nativas de Chrome');
academic_team_dialog_assert(strpos($editor, 'window.alert(') === false, 'el editor no usa alert nativo');
academic_team_dialog_assert(strpos($script, 'data-flacso-team-delete-dialog') !== false, 'crea el diálogo visual cuando se carga el controlador');
academic_team_dialog_assert(strpos($script, 'role="alertdialog"') !== false, 'el diálogo tiene semántica accesible');
academic_team_dialog_assert(strpos($script, 'aria-modal="true"') !== false, 'se declara modal');
academic_team_dialog_assert(strpos($script, 'aria-labelledby="flacso-team-delete-title"') !== false, 'el diálogo tiene título');
academic_team_dialog_assert(strpos($script, 'aria-describedby="flacso-team-delete-description flacso-team-delete-note"') !== false, 'el diálogo anuncia consecuencias');
academic_team_dialog_assert(strpos($script, 'data-team-delete-name') !== false, 'muestra el nombre del grupo');
academic_team_dialog_assert(strpos($script, 'data-team-delete-cancel') !== false, 'cancelar es una acción explícita');
academic_team_dialog_assert(strpos($script, 'data-team-delete-confirm') !== false, 'confirmar es una acción explícita');
academic_team_dialog_assert(strpos($script, 'El cambio se aplicará cuando guardes la Oferta Académica o Cohorte.') !== false, 'no promete persistencia inmediata');
academic_team_dialog_assert(strpos($script, "team.getAttribute('data-team-type') === 'coordinacion_academica'") !== false, 'no elimina coordinación académica');
academic_team_dialog_assert(strpos($editor, 'if ($type !== self::TYPE_COORDINATION)') !== false, 'no hay botón de borrar coordinación');
academic_team_dialog_assert(strpos($script, "openTeamDeleteDialog(removeTeam.closest('[data-team-group]'), removeTeam)") !== false, 'ambos editores reutilizan confirmación');
academic_team_dialog_assert(strpos($script, 'pendingDelete.remove();') !== false, 'solo se elimina al confirmar');
academic_team_dialog_assert(strpos($script, 'closeTeamDeleteDialog();') !== false, 'cancelar deja el equipo intacto');
academic_team_dialog_assert(strpos($script, "event.key === 'Escape'") !== false, 'Escape cancela');
academic_team_dialog_assert(strpos($script, "event.key !== 'Tab'") !== false, 'Tab mantiene el foco en el diálogo');
academic_team_dialog_assert(strpos($script, 'returnFocus = trigger;') !== false, 'retorno de foco tras cancelar');
academic_team_dialog_assert(strpos($script, "document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'))") !== false, 'actualiza el estado del editor tras eliminar');
academic_team_dialog_assert(strpos($editor, 'data-flacso-team-editor="offer"') !== false, 'funciona en ofertas');
academic_team_dialog_assert(strpos($editor, 'data-flacso-team-editor="cohort"') !== false, 'funciona en cohortes');
academic_team_dialog_assert(strpos($editor, '@media(max-width:600px)') !== false, 'botones accesibles en móvil');
academic_team_dialog_assert(strpos($editor, '.flacso-team-delete-overlay[hidden]{display:none!important}') !== false, 'el cuadro permanece oculto hasta su apertura');
echo "OK: diálogo de eliminación de equipos docentes sin confirmación nativa\n";
