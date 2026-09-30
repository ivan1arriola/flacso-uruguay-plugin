<?php
/**
 * Validador de solo lectura del contrato Mautic.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Mautic_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mautic-client.php';
}
if (!class_exists('FLACSO_Mautic_Contract_Manifest')) {
    require_once __DIR__ . '/class-flacso-mautic-contract-manifest.php';
}

final class FLACSO_Mautic_Contract_Validator {
    public static function validate(?array $manifest = null): array {
        $manifest = $manifest ?? FLACSO_Mautic_Contract_Manifest::definition();
        $requirements = [];
        $ok = true;

        if (!FLACSO_Mautic_Client::is_configured()) {
            return [
                'ok' => false,
                'status' => 'blocked',
                'manifest_version' => (string) ($manifest['version'] ?? ''),
                'requirements' => [[
                    'key' => 'mautic_configuration',
                    'ok' => false,
                    'message' => 'Mautic no está configurado o está deshabilitado.',
                ]],
            ];
        }

        $fields_response = FLACSO_Mautic_Client::get_contact_fields();
        if (empty($fields_response['ok'])) {
            $ok = false;
            $requirements[] = [
                'key' => 'contact_fields',
                'ok' => false,
                'message' => 'No fue posible leer los campos de contacto.',
            ];
        } else {
            $available = self::index_fields($fields_response['fields'] ?? []);
            foreach (($manifest['contact_fields'] ?? []) as $alias => $expected) {
                $actual = $available[$alias] ?? null;
                $field_ok = is_array($actual) && strtolower((string) ($actual['type'] ?? '')) === strtolower((string) ($expected['type'] ?? ''));
                if ($field_ok && !empty($expected['options'])) {
                    $field_ok = self::contains_options($actual, (array) $expected['options']);
                }
                $requirements[] = [
                    'key' => 'field:' . $alias,
                    'ok' => $field_ok,
                    'message' => $field_ok ? 'Campo compatible.' : 'Campo ausente o incompatible.',
                ];
                $ok = $ok && $field_ok;
            }
        }

        $template = is_array($manifest['template'] ?? null) ? $manifest['template'] : [];
        $template_id = (int) ($template['id'] ?? 0);
        $expected_sha = strtolower(trim((string) ($template['content_sha256'] ?? '')));
        $identity_ok = $template_id > 0
            && trim((string) ($template['functional_version'] ?? '')) !== ''
            && preg_match('/^[a-f0-9]{64}$/', $expected_sha) === 1;

        $requirements[] = [
            'key' => 'template_identity',
            'ok' => $identity_ok,
            'message' => $identity_ok ? 'Identidad de plantilla versionada.' : 'Falta ID, versión funcional o SHA-256 aprobado.',
        ];
        $ok = $ok && $identity_ok;

        if ($template_id > 0) {
            $email_response = FLACSO_Mautic_Client::get_email_template($template_id);
            if (empty($email_response['ok'])) {
                $ok = false;
                $requirements[] = [
                    'key' => 'template_read',
                    'ok' => false,
                    'message' => 'No fue posible leer la plantilla transaccional.',
                ];
            } else {
                $email = is_array($email_response['email'] ?? null) ? $email_response['email'] : [];
                $published = self::is_published($email);
                $requirements[] = [
                    'key' => 'template_published',
                    'ok' => $published,
                    'message' => $published ? 'Plantilla publicada.' : 'La plantilla no está publicada.',
                ];
                $ok = $ok && $published;

                $actual_sha = hash('sha256', self::normalized_template_content($email));
                $hash_ok = $identity_ok && hash_equals($expected_sha, $actual_sha);
                $requirements[] = [
                    'key' => 'template_sha256',
                    'ok' => $hash_ok,
                    'message' => $hash_ok ? 'Contenido aprobado.' : 'La huella de plantilla no coincide o no está aprobada.',
                ];
                $ok = $ok && $hash_ok;
            }
        }

        return [
            'ok' => $ok,
            'status' => $ok ? 'valid' : 'blocked',
            'manifest_version' => (string) ($manifest['version'] ?? ''),
            'requirements' => $requirements,
        ];
    }

    public static function normalized_template_content(array $email): string {
        $subject = trim((string) ($email['subject'] ?? ''));
        $html = (string) ($email['customHtml'] ?? $email['content'] ?? $email['body'] ?? '');
        $html = preg_replace('/\r\n?/', "\n", $html);
        $html = preg_replace('/[ \t]+\n/', "\n", (string) $html);
        return $subject . "\n---\n" . trim((string) $html);
    }

    private static function index_fields(array $fields): array {
        $indexed = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $alias = trim((string) ($field['alias'] ?? ''));
            if ($alias !== '') {
                $indexed[$alias] = $field;
            }
        }
        return $indexed;
    }

    private static function contains_options(array $field, array $expected): bool {
        $raw = $field['properties']['list'] ?? $field['properties']['options'] ?? $field['options'] ?? [];
        if (!is_array($raw)) {
            return false;
        }

        $actual = [];
        foreach ($raw as $key => $item) {
            if (is_array($item)) {
                $value = $item['value'] ?? $item['label'] ?? null;
            } else {
                $value = is_string($key) && !is_numeric($key) ? $key : $item;
            }
            if (is_scalar($value)) {
                $actual[] = strtolower(trim((string) $value));
            }
        }

        foreach ($expected as $value) {
            if (!in_array(strtolower(trim((string) $value)), $actual, true)) {
                return false;
            }
        }
        return true;
    }

    private static function is_published(array $email): bool {
        foreach (['isPublished', 'published', 'active'] as $key) {
            if (array_key_exists($key, $email)) {
                return $email[$key] === true || $email[$key] === 1 || $email[$key] === '1';
            }
        }
        return false;
    }
}
