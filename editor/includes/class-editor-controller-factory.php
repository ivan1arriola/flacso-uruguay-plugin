<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory central y explícita: no mezcla reglas de negocio y no usa
 * contenedor global. Incorporar otra entidad requiere registrar aquí
 * su controlador, no modificar las vistas ni el router.
 */
final class FLACSO_Editor_Controller_Factory {
    private FLACSO_Editor_Repository_Interface $repository;
    private FLACSO_Editor_Offer_Form_Interface $offer_form;

    public function __construct(FLACSO_Editor_Repository_Interface $repository, FLACSO_Editor_Offer_Form_Interface $offer_form) {
        $this->repository = $repository;
        $this->offer_form = $offer_form;
    }

    public function for_type(string $post_type): FLACSO_Editor_Controller_Interface {
        switch ($post_type) {
            case 'oferta-academica':
                return new FLACSO_Oferta_Editor_Controller($this->repository, $this->offer_form);
            case 'cohorte':
                return new FLACSO_Cohorte_Editor_Controller($this->repository);
            default:
                throw new InvalidArgumentException('Tipo académico no soportado por el editor.');
        }
    }
}
