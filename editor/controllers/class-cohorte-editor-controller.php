<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Organiza la Cohorte y su relación con la Oferta padre. */
final class FLACSO_Cohorte_Editor_Controller implements FLACSO_Editor_Controller_Interface {
    private FLACSO_Editor_Repository_Interface $repository;

    public function __construct(FLACSO_Editor_Repository_Interface $repository) {
        $this->repository = $repository;
    }

    public function build_view_model(array $entity): array {
        if (($entity['type'] ?? '') !== 'cohorte') {
            throw new InvalidArgumentException('El controlador de Cohorte recibió otra entidad.');
        }

        $parent_id = (int) ($entity['fields']['parent_id'] ?? 0);
        $parent = null;
        if ($parent_id > 0 && $this->repository->can_edit($parent_id)) {
            $candidate = $this->repository->find($parent_id);
            if (is_array($candidate) && ($candidate['type'] ?? '') === 'oferta-academica') {
                $parent = $candidate;
            }
        }

        return [
            'entity' => $entity,
            'is_offer' => false,
            'cohorts' => [],
            'parent' => $parent,
        ];
    }
}
