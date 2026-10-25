<?php
/**
 * Campañas Mautic por oferta académica.
 *
 * Con el interruptor encendido, cada consulta de oferta con consentimiento
 * válido deja de recibir el acuse transaccional: su entrega hace el alta en la
 * campaña de la oferta (POST /api/flacso/inquiries del plugin de Mautic), que
 * envía el acuse según el estado de preinscripción y luego el seguimiento.
 *
 * El consentimiento lo genera siempre el servidor, en las vías que muestran el
 * texto (formulario «Más información») o que lo obtienen de forma verificable
 * (bot de Meta, cuando se implemente). Nunca se toma del navegador.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Offer_Campaigns {
    public const OPTION_ENABLED = 'flacso_mautic_offer_campaigns_enabled';

    /** Texto visible junto al botón del formulario. Al cambiarlo, subir la versión. */
    public const CONSENT_TEXT = 'Al enviar, aceptás recibir información de FLACSO Uruguay sobre este programa.';
    public const CONSENT_TEXT_VERSION = '2026-10-25';

    public static function is_enabled(): bool {
        return function_exists('get_option') && (string) get_option(self::OPTION_ENABLED, '0') === '1';
    }

    /**
     * Evidencia de consentimiento del formulario «Más información». Sólo se
     * registra con el interruptor encendido: antes no hay campaña que la use.
     */
    public static function form_consent(int $offer_wp_id, string $accepted_at): array {
        if (!self::is_enabled() || $offer_wp_id <= 0) {
            return [];
        }

        return [
            'granted' => true,
            'acceptedAt' => $accepted_at,
            'source' => 'mas-informacion-' . $offer_wp_id,
            'textVersion' => self::CONSENT_TEXT_VERSION,
        ];
    }

    public static function valid_consent(array $consent): bool {
        $accepted_at = trim((string) ($consent['acceptedAt'] ?? ''));

        return ($consent['granted'] ?? false) === true
            && $accepted_at !== ''
            && strtotime($accepted_at) !== false
            && trim((string) ($consent['source'] ?? '')) !== ''
            && trim((string) ($consent['textVersion'] ?? '')) !== '';
    }

    /** ¿Esta entrega va a la campaña de la oferta en lugar del acuse transaccional? */
    public static function routes_to_campaign(array $delivery, array $payload): bool {
        return self::is_enabled()
            && ($delivery['inquiryType'] ?? '') === 'offer'
            && ($delivery['assignedProvider'] ?? 'mautic') === 'mautic'
            && self::valid_consent(is_array($payload['marketingConsent'] ?? null) ? $payload['marketingConsent'] : []);
    }

    /**
     * Cuerpo de POST /api/flacso/inquiries a partir del snapshot inmutable.
     *
     * @throws InvalidArgumentException si el snapshot no identifica la oferta
     */
    public static function enrollment_payload(array $snapshot, array $consent): array {
        $offer_wp_id = (int) ($snapshot['academic']['wpId'] ?? 0);
        if ($offer_wp_id <= 0) {
            throw new InvalidArgumentException('El snapshot no identifica la oferta (academic.wpId).');
        }

        $built = FLACSO_Mautic_Payload_Builder::build($snapshot);
        $fields = $built['fields'];

        return [
            'consultaId' => (string) $snapshot['consultaId'],
            'offerWpId' => $offer_wp_id,
            'contact' => [
                'email' => $fields['email'],
                'firstname' => $fields['firstname'],
                'lastname' => $fields['lastname'],
                'pais' => $fields['flacso_pais'],
                'nivelAcademico' => $fields['flacso_nivel_academico'],
                'profesion' => $fields['flacso_profesion'],
            ],
            'consent' => [
                'granted' => true,
                'acceptedAt' => (string) $consent['acceptedAt'],
                'source' => (string) $consent['source'],
                'textVersion' => (string) $consent['textVersion'],
            ],
            'tags' => array_values($built['tags']),
        ];
    }
}
