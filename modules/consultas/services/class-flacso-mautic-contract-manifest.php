<?php
/**
 * Contrato versionado WordPress -> Mautic para consultas.
 *
 * La huella de la plantilla se completa únicamente después de la prueba
 * controlada documentada. Mientras permanezca vacía, el validador bloquea la
 * cola de forma deliberada.
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Mautic_Contract_Manifest {
    public const VERSION = '1.0.0';

    public static function definition(): array {
        return [
            'version' => self::VERSION,
            'contact_fields' => [
                'flacso_origen' => ['type' => 'text'],
                'flacso_pais' => ['type' => 'text'],
                'flacso_nivel_academico' => ['type' => 'text'],
                'flacso_profesion' => ['type' => 'text'],
            ],
            'tags' => [
                'interes-{codigo}',
                '{codigo}-c{numero}',
                'origen-web-consultas',
            ],
            'template' => [
                'id' => 4,
                'functional_version' => 'ack-v1',
                'content_sha256' => '3b17e9f2353f20492bbb05e0ec2291aec0d200d7b8846be03b37a80f4244e717',
            ],
            'marketing_campaign' => [
                'id' => 3,
                'required_for_acknowledgement' => false,
            ],
        ];
    }
}
