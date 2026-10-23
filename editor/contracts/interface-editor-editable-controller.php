<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Contrato de edición: no todas las entidades deben implementarlo al mismo tiempo. */
interface FLACSO_Editor_Editable_Controller_Interface extends FLACSO_Editor_Controller_Interface {
    /** Renderiza un formulario completo, sin decidir cómo se guardan los datos. */
    public function render_form(int $id): void;

    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    public function save(int $id, array $data): array;
}
