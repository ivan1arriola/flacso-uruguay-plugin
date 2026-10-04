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
    public static function validate(?array $manifest = null, ?int $only_template_id = null): array {
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
                    'blocking' => true,
                    'message' => 'Mautic no está configurado o está deshabilitado.',
                ]],
            ];
        }

        // Los campos flacso_* son útiles para CRM/segmentación, pero el correo
        // transaccional se construye desde el snapshot y sus tokens. Por eso
        // estas comprobaciones son diagnósticas y nunca bloquean el envío.
        $fields_response = FLACSO_Mautic_Client::get_contact_fields();
        if (empty($fields_response['ok'])) {
            $requirements[] = [
                'key' => 'contact_fields',
                'ok' => false,
                'blocking' => false,
                'message' => 'No fue posible leer los campos de contacto; esto no bloquea el correo transaccional.',
            ];
        } else {
            $available = self::index_fields($fields_response['fields'] ?? []);
            foreach (($manifest['contact_fields'] ?? []) as $alias => $expected) {
                $actual = $available[$alias] ?? null;
                $field_ok = is_array($actual)
                    && strtolower((string) ($actual['type'] ?? '')) === strtolower((string) ($expected['type'] ?? ''));
                if ($field_ok && !empty($expected['options'])) {
                    $field_ok = self::contains_options($actual, (array) $expected['options']);
                }
                $requirements[] = [
                    'key' => 'field:' . $alias,
                    'ok' => $field_ok,
                    'blocking' => false,
                    'message' => $field_ok
                        ? 'Campo compatible.'
                        : 'Campo ausente o incompatible; no es requisito del correo transaccional.',
                ];
            }
        }

        $templates = self::templates_to_validate($manifest, $only_template_id);
        if (empty($templates)) {
            $requirements[] = [
                'key' => 'template_manifest',
                'ok' => false,
                'blocking' => true,
                'message' => $only_template_id !== null && $only_template_id > 0
                    ? 'La plantilla #' . $only_template_id . ' no está registrada en el contrato transaccional.'
                    : 'No hay plantillas transaccionales obligatorias configuradas.',
            ];
            $ok = false;
        }

        foreach ($templates as $key => $template) {
            $template_id = (int) ($template['id'] ?? 0);
            $name = trim((string) ($template['name'] ?? ''));
            $version = trim((string) ($template['functional_version'] ?? ''));
            $expected_sha = strtolower(trim((string) ($template['content_sha256'] ?? '')));
            $label = $name !== '' ? $name : ('plantilla #' . $template_id);

            $identity_ok = $template_id > 0 && $version !== '';
            $requirements[] = [
                'key' => 'template_identity:' . $key,
                'ok' => $identity_ok,
                'blocking' => true,
                'message' => $identity_ok
                    ? sprintf('%s: identidad configurada.', $label)
                    : sprintf('%s: falta ID o versión funcional.', $label),
            ];
            $ok = $ok && $identity_ok;
            if (!$identity_ok) {
                continue;
            }

            $email_response = FLACSO_Mautic_Client::get_email_template($template_id);
            if (empty($email_response['ok'])) {
                $requirements[] = [
                    'key' => 'template_read:' . $key,
                    'ok' => false,
                    'blocking' => true,
                    'message' => sprintf('%s (#%d): no fue posible leer el correo en Mautic.', $label, $template_id),
                ];
                $ok = false;
                continue;
            }

            $email = is_array($email_response['email'] ?? null) ? $email_response['email'] : [];
            $published = self::is_published($email);
            $requirements[] = [
                'key' => 'template_published:' . $key,
                'ok' => $published,
                'blocking' => true,
                'message' => $published
                    ? sprintf('%s (#%d): publicado.', $label, $template_id)
                    : sprintf('%s (#%d): no está publicado.', $label, $template_id),
            ];
            $ok = $ok && $published;

            // La huella queda como señal de auditoría. Mautic puede normalizar
            // HTML o el equipo puede editar el correo sin que eso deba dejar
            // toda la cola operativa en blocked.
            if (preg_match('/^[a-f0-9]{64}$/', $expected_sha) === 1) {
                $actual_sha = hash('sha256', self::normalized_template_content($email));
                $hash_ok = hash_equals($expected_sha, $actual_sha);
                $requirements[] = [
                    'key' => 'template_sha256:' . $key,
                    'ok' => $hash_ok,
                    'blocking' => false,
                    'message' => $hash_ok
                        ? sprintf('%s (#%d): huella de contenido coincide.', $label, $template_id)
                        : sprintf('%s (#%d): la huella cambió; revisar contenido, pero no se bloquea el envío.', $label, $template_id),
                ];
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

    private static function templates_to_validate(array $manifest, ?int $only_template_id): array {
        $templates = is_array($manifest['templates'] ?? null) ? $manifest['templates'] : [];

        if (empty($templates) && is_array($manifest['template'] ?? null)) {
            $templates = ['template' => $manifest['template']];
        }

        if ($only_template_id !== null && $only_template_id > 0) {
            foreach ($templates as $key => $template) {
                if (is_array($template) && (int) ($template['id'] ?? 0) === $only_template_id) {
                    return [(string) $key => $template];
                }
            }
            return [];
        }

        $required = [];
        foreach ($templates as $key => $template) {
            if (!is_array($template)) {
                continue;
            }
            if (array_key_exists('required', $template) && empty($template['required'])) {
                continue;
            }
            $required[(string) $key] = $template;
        }

        return $required;
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
