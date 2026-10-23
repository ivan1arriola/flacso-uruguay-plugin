<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Organiza información de Ofertas sin acceder a WordPress directamente. */
final class FLACSO_Oferta_Editor_Controller implements FLACSO_Editor_Controller_Interface {
    private FLACSO_Editor_Repository_Interface $repository;

    public function __construct(FLACSO_Editor_Repository_Interface $repository) {
        $this->repository = $repository;
    }

    public function build_view_model(array $entity): array {
        if (($entity['type'] ?? '') !== 'oferta-academica') {
            throw new InvalidArgumentException('El controlador de Oferta recibió otra entidad.');
        }

        return [
            'entity' => $entity,
            'is_offer' => true,
            'cohorts' => $this->repository->cohorts_for_offer((int) $entity['id']),
            'parent' => null,
        ];
    }
}
