<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cada entidad del editor tiene su presentador independiente.
 * No recibe WP_Post, sólo datos del repositorio.
 */
interface FLACSO_Editor_Controller_Interface {
    /** @param array<string,mixed> $entity @return array<string,mixed> */
    public function build_view_model(array $entity): array;
}
