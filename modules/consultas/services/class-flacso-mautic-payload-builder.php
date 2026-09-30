<?php

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Inquiry_Snapshot')) {
    require_once __DIR__ . '/class-flacso-inquiry-snapshot.php';
}
if (!class_exists('FLACSO_Inquiry_Tag_Factory')) {
    require_once __DIR__ . '/class-flacso-inquiry-tag-factory.php';
}

/**
 * Adapta un InquirySnapshot al contrato permitido de Mautic.
 *
 * Los campos de una consulta concreta no se escriben en el contacto. El
 * acuse utiliza tokens por entrega derivados del snapshot inmutable.
 */
final class FLACSO_Mautic_Payload_Builder {
    public static function build(array $snapshot): array {
        FLACSO_Inquiry_Snapshot::assert_snapshot($snapshot);

        $recipient = $snapshot['recipient'];
        $profile = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];

        $fields = [
            'email'                  => trim((string) ($recipient['email'] ?? '')),
            'firstname'              => trim((string) ($recipient['firstName'] ?? '')),
            'lastname'               => trim((string) ($recipient['lastName'] ?? '')),
            'flacso_origen'          => 'web-consultas',
            'flacso_pais'            => trim((string) ($profile['country'] ?? '')),
            'flacso_nivel_academico' => trim((string) ($profile['educationLevel'] ?? '')),
            'flacso_profesion'       => trim((string) ($profile['profession'] ?? '')),
        ];

        return [
            'fields' => $fields,
            'tags'   => FLACSO_Inquiry_Tag_Factory::from_snapshot($snapshot),
            'tokens' => FLACSO_Inquiry_Snapshot::delivery_tokens($snapshot),
        ];
    }
}
