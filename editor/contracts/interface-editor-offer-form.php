<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Puerto del formulario de Oferta.
 * Implementaciones dentro del mismo proceso PHP; nunca HTTP / REST.
 */
interface FLACSO_Editor_Offer_Form_Interface {
    public function render(int $id): void;

    /**
     * @param array<string,mixed> $data Valores básicos saneados por el adaptador.
     * @return array{success:bool,message:string}
     */
    public function save(int $id, array $data): array;

    public function enqueue_assets(): void;
    public function render_head(): void;
    public function render_footer(int $id): void;
}
