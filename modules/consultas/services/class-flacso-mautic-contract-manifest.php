<?php
/**
 * Contrato versionado WordPress -> Mautic para consultas.
 *
 * Los IDs corresponden a correos estándar de Mautic. El envío inicial usa
 * una plantilla distinta según el estado de inscripciones de la oferta.
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Mautic_Contract_Manifest {
    public const VERSION = '1.1.0';

    public const EMAIL_OPEN = 4;
    public const EMAIL_CLOSED = 5;
    public const EMAIL_FOLLOWUP = 6;

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
            'templates' => [
                'initial_open' => [
                    'id' => self::EMAIL_OPEN,
                    'name' => 'F1 — Consulta — Inscripciones abiertas',
                    'functional_version' => 'f1-open-v1',
                    // Huella conocida del correo #4. Se conserva como control
                    // de auditoría, pero una diferencia no bloquea el envío.
                    'content_sha256' => '3b17e9f2353f20492bbb05e0ec2291aec0d200d7b8846be03b37a80f4244e717',
                    'required' => true,
                ],
                'initial_closed' => [
                    'id' => self::EMAIL_CLOSED,
                    'name' => 'F1 — Consulta — Inscripciones cerradas',
                    'functional_version' => 'f1-closed-v1',
                    'content_sha256' => '',
                    'required' => true,
                ],
                'followup' => [
                    'id' => self::EMAIL_FOLLOWUP,
                    'name' => 'F2 — Seguimiento — Interés en la propuesta',
                    'functional_version' => 'f2-followup-v1',
                    'content_sha256' => '',
                    'required' => false,
                ],
            ],
            // Compatibilidad con código anterior que esperaba una sola plantilla.
            'template' => [
                'id' => self::EMAIL_OPEN,
                'name' => 'F1 — Consulta — Inscripciones abiertas',
                'functional_version' => 'f1-open-v1',
                'content_sha256' => '3b17e9f2353f20492bbb05e0ec2291aec0d200d7b8846be03b37a80f4244e717',
                'required' => true,
            ],
        ];
    }

    public static function initial_template_for_snapshot(array $snapshot): array {
        $definition = self::definition();
        $templates = is_array($definition['templates'] ?? null) ? $definition['templates'] : [];
        $type = strtolower(trim((string) ($snapshot['inquiryType'] ?? '')));
        $status = strtolower(trim((string) ($snapshot['academic']['status'] ?? '')));

        // Seminarios conservan el comportamiento previo (#4) hasta que exista
        // un correo transaccional específico para ese tipo de consulta.
        if ($type !== 'offer') {
            return is_array($templates['initial_open'] ?? null) ? $templates['initial_open'] : [];
        }

        $key = $status === 'abierta' ? 'initial_open' : 'initial_closed';
        return is_array($templates[$key] ?? null) ? $templates[$key] : [];
    }

    public static function template_by_id(int $template_id, ?array $definition = null): array {
        $definition = $definition ?? self::definition();
        $templates = is_array($definition['templates'] ?? null) ? $definition['templates'] : [];

        foreach ($templates as $key => $template) {
            if (!is_array($template) || (int) ($template['id'] ?? 0) !== $template_id) {
                continue;
            }
            $template['key'] = (string) $key;
            return $template;
        }

        $legacy = is_array($definition['template'] ?? null) ? $definition['template'] : [];
        if ((int) ($legacy['id'] ?? 0) === $template_id) {
            $legacy['key'] = 'template';
            return $legacy;
        }

        return [];
    }
}
