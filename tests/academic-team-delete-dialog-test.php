<?php
/**
 * Contrato del diálogo de eliminación de equipos docentes.
 * Impide regresar a window.confirm() y protege la coordinación obligatoria.
 */
$editor = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-team-editor.php');

function academic_team_dialog_assert(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: " . $message . "\n");
        exit(1);
    }
}

academic_team_dialog_assert(strpos($editor, 'window.confirm(') === false, 'el editor activo no usa alertas nativas de Chrome');
academic_team_dialog_assert(strpos($editor, 'window.alert(') === false, 'el editor no usa alert nativo');
academic_team_dialog_assert(strpos($editor, 'data-flacso-team-delete-dialog') !== false, 'existe un diálogo visual propio');
academic_team_dialog_assert(strpos($editor, 'role="alertdialog"') !== false, 'el diálogo tiene semántica accesible');
academic_team_dialog_assert(strpos($editor, 'aria-modal="true"') !== false, 'se declara modal');
academic_team_dialog_assert(strpos($editor, 'aria-labelledby="flacso-team-delete-title"') !== false, 'el diálogo tiene título');
academic_team_dialog_assert(strpos($editor, 'aria-describedby="flacso-team-delete-description flacso-team-delete-note"') !== false, 'el diálogo anuncia consecuencias');
academic_team_dialog_assert(strpos($editor, 'data-team-delete-name') !== false, 'muestra el nombre del grupo');
academic_team_dialog_assert(strpos($editor, 'data-team-delete-cancel') !== false, 'cancelar es una acción explícita');
academic_team_dialog_assert(strpos($editor, 'data-team-delete-confirm') !== false, 'confirmar es una acción explícita');
academic_team_dialog_assert(strpos($editor, 'El cambio se aplicará cuando guardes la Oferta Académica o Cohorte.') !== false, 'no promete persistencia inmediata');
academic_team_dialog_assert(strpos($editor, 'team.getAttribute(\'data-team-type\')===\'coordinacion_academica\'') !== false, 'no elimina coordinación académica');
academic_team_dialog_assert(strpos($editor, 'if ($type !== self::TYPE_COORDINATION)') !== false, 'no hay botón de borrar coordinación');
academic_team_dialog_assert(strpos($editor, 'openTeamDeleteDialog(removeTeam.closest(\'[data-team-group]\'),removeTeam)') !== false, 'ambos editores reutilizan confirmación');
academic_team_dialog_assert(strpos($editor, 'pendingDelete.remove();') !== false, 'solo se elimina al confirmar');
academic_team_dialog_assert(strpos($editor, 'closeTeamDeleteDialog();') !== false, 'cancelar deja el equipo intacto');
academic_team_dialog_assert(strpos($editor, "event.key==='Escape'") !== false, 'Escape cancela');
academic_team_dialog_assert(strpos($editor, "event.key!=='Tab'") !== false, 'Tab mantiene el foco en el diálogo');
academic_team_dialog_assert(strpos($editor, 'returnFocus=trigger;') !== false, 'retorno de foco tras cancelar');
academic_team_dialog_assert(strpos($editor, "document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'))") !== false, 'actualiza el estado del editor tras eliminar');
academic_team_dialog_assert(strpos($editor, 'data-flacso-team-editor="offer"') !== false, 'funciona en ofertas');
academic_team_dialog_assert(strpos($editor, 'data-flacso-team-editor="cohort"') !== false, 'funciona en cohortes');
academic_team_dialog_assert(strpos($editor, '@media(max-width:600px)') !== false, 'botones accesibles en móvil');
academic_team_dialog_assert(strpos($editor, '.flacso-team-delete-overlay[hidden]{display:none!important}') !== false, 'el cuadro permanece oculto hasta su apertura');
echo "OK: diálogo de eliminación de equipos docentes sin confirmación nativa\n";
