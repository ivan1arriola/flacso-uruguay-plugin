<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Organiza información de Ofertas sin acceder a WordPress directamente. */
final class FLACSO_Oferta_Editor_Controller implements FLACSO_Editor_Editable_Controller_Interface {
    private FLACSO_Editor_Repository_Interface $repository;
    private FLACSO_Editor_Offer_Form_Interface $form;

    public function __construct(FLACSO_Editor_Repository_Interface $repository, FLACSO_Editor_Offer_Form_Interface $form) {
        $this->repository = $repository;
        $this->form = $form;
    }

    public function render_form(int $id): void {
        $this->form->render($id);
    }

    public function save(int $id, array $data): array {
        if (!$this->repository->can_edit($id)) {
            return ['success' => false, 'message' => 'No tenés permisos para editar la oferta.'];
        }
        return $this->form->save($id, $data);
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
