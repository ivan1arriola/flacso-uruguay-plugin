<?php
/**
 * Procesador de una entrega transaccional de consulta.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Inquiry_Delivery_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
}
if (!class_exists('FLACSO_Mautic_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mautic-client.php';
}
if (!class_exists('FLACSO_Mautic_Contract_Validator')) {
    require_once __DIR__ . '/class-flacso-mautic-contract-validator.php';
}

final class FLACSO_Inquiry_Delivery_Service {
    private static ?FLACSO_Inquiry_Delivery_Repository $repository = null;

    /** @var callable|null */
    private static $contract_validator = null;

    public static function set_repository(?FLACSO_Inquiry_Delivery_Repository $repository): void {
        self::$repository = $repository;
    }

    public static function set_contract_validator(?callable $validator): void {
        self::$contract_validator = $validator;
    }

    public static function contract_failure_message(array $contract): string {
        $messages = [];
        $requirements = is_array($contract['requirements'] ?? null) ? $contract['requirements'] : [];

        foreach ($requirements as $requirement) {
            if (!is_array($requirement) || !empty($requirement['ok']) || ($requirement['blocking'] ?? true) === false) {
                continue;
            }
            $message = trim((string) ($requirement['message'] ?? ''));
            if ($message !== '') {
                $messages[] = $message;
            }
            if (count($messages) >= 3) {
                break;
            }
        }

        return !empty($messages)
            ? implode(' ', $messages)
            : 'El contrato Mautic no superó la validación.';
    }

    public static function process(string $delivery_id): array {
        $repository = self::$repository ?? new FLACSO_Inquiry_Delivery_Repository();
        $delivery = $repository->find_by_delivery_id(trim($delivery_id));

        if ($delivery === null) {
            return ['ok' => false, 'status' => 'not_found'];
        }

        $state = (string) ($delivery['state'] ?? '');
        if (in_array($state, ['accepted', 'acceptance_unknown', 'failed', 'blocked'], true)) {
            return ['ok' => $state === 'accepted', 'status' => $state, 'terminal' => true];
        }
        if ($state !== 'processing') {
            return ['ok' => false, 'status' => 'not_claimed'];
        }

        $contract = is_callable(self::$contract_validator)
            ? call_user_func(self::$contract_validator)
            : FLACSO_Mautic_Contract_Validator::validate(
                null,
                (int) ($delivery['templateId'] ?? 0)
            );

        if (empty($contract['ok'])) {
            $detail = self::contract_failure_message($contract);
            $repository->mark_blocked($delivery_id, 'mautic_contract', $detail);
            self::notify_failure($delivery_id, 'blocked', 'mautic_contract', $detail);
            return ['ok' => false, 'status' => 'blocked', 'contract' => $contract, 'error' => $detail];
        }

        $snapshot = json_decode((string) ($delivery['snapshotJson'] ?? ''), true);
        $payload = json_decode((string) ($delivery['payloadJson'] ?? ''), true);
        if (!is_array($snapshot) || !is_array($payload) || !is_array($payload['tokens'] ?? null)) {
            $repository->mark_failed($delivery_id, null, 'invalid_snapshot', 'Snapshot o payload transaccional inválido.');
            self::notify_failure($delivery_id, 'failed', 'invalid_snapshot');
            return ['ok' => false, 'status' => 'failed'];
        }

        $recipient = is_array($snapshot['recipient'] ?? null) ? $snapshot['recipient'] : [];
        $recipient_result = FLACSO_Mautic_Client::ensure_delivery_recipient(
            (string) ($recipient['email'] ?? ''),
            (string) ($recipient['firstName'] ?? ''),
            (string) ($recipient['lastName'] ?? '')
        );

        if (empty($recipient_result['ok'])) {
            if (!empty($recipient_result['acceptance_unknown'])) {
                $repository->mark_acceptance_unknown(
                    $delivery_id,
                    'recipient_creation_unknown',
                    'No fue posible conciliar la creación del destinatario.'
                );
                self::notify_failure($delivery_id, 'acceptance_unknown', 'recipient_creation_unknown');
                return ['ok' => false, 'status' => 'acceptance_unknown'];
            }

            $repository->mark_retryable_failure(
                $delivery_id,
                null,
                'recipient_unavailable',
                'No fue posible asegurar el destinatario mínimo.',
                300
            );
            self::notify_failure($delivery_id, 'retryable_failed', 'recipient_unavailable');
            return ['ok' => false, 'status' => 'retryable_failed'];
        }

        $contact_id = (int) ($recipient_result['contact_id'] ?? 0);
        $template_id = (int) ($delivery['templateId'] ?? 0);
        if ($contact_id <= 0 || $template_id <= 0) {
            $repository->mark_blocked($delivery_id, 'delivery_identity', 'Falta destinatario o plantilla versionada.');
            self::notify_failure($delivery_id, 'blocked', 'delivery_identity');
            return ['ok' => false, 'status' => 'blocked'];
        }

        $repository->set_contact_id($delivery_id, $contact_id);

        $attempt_id = bin2hex(random_bytes(16));
        $repository->record_attempt_start($delivery_id, $attempt_id);

        try {
            $send = FLACSO_Mautic_Client::send_transactional_email_to_contact(
                $template_id,
                $contact_id,
                $payload['tokens']
            );
        } catch (Throwable $e) {
            $repository->finish_attempt($attempt_id, 'acceptance_unknown', null, 'transport_exception', $e->getMessage());
            $repository->mark_acceptance_unknown($delivery_id, 'transport_exception', $e->getMessage());
            self::notify_failure($delivery_id, 'acceptance_unknown', 'transport_exception');
            return ['ok' => false, 'status' => 'acceptance_unknown'];
        }

        $http_code = isset($send['http_code']) ? (int) $send['http_code'] : null;

        if (!empty($send['ok'])) {
            $repository->finish_attempt($attempt_id, 'accepted', $http_code);
            $repository->mark_accepted($delivery_id, $http_code);
            return [
                'ok' => true,
                'status' => 'accepted',
                'http_code' => $http_code,
                'attempt_id' => $attempt_id,
            ];
        }

        if (!empty($send['acceptance_unknown']) || ($send['status'] ?? '') === 'acceptance_unknown') {
            $repository->finish_attempt($attempt_id, 'acceptance_unknown', $http_code, 'response_lost', $send['error'] ?? null);
            $repository->mark_acceptance_unknown($delivery_id, 'response_lost', $send['error'] ?? null);
            self::notify_failure($delivery_id, 'acceptance_unknown', 'response_lost');
            return ['ok' => false, 'status' => 'acceptance_unknown', 'attempt_id' => $attempt_id];
        }

        $attempts_after_start = ((int) ($delivery['attempts'] ?? 0)) + 1;
        if (($send['status'] ?? '') === 'retryable_failed' && $attempts_after_start < 3) {
            $repository->finish_attempt($attempt_id, 'retryable_failed', $http_code, 'mautic_http', $send['error'] ?? null);
            $repository->mark_retryable_failure($delivery_id, $http_code, 'mautic_http', $send['error'] ?? null, 300);
            self::notify_failure($delivery_id, 'retryable_failed', 'mautic_http');
            return ['ok' => false, 'status' => 'retryable_failed', 'attempt_id' => $attempt_id];
        }

        $repository->finish_attempt($attempt_id, 'failed', $http_code, 'mautic_http', $send['error'] ?? null);
        $repository->mark_failed($delivery_id, $http_code, 'mautic_http', $send['error'] ?? null);
        self::notify_failure($delivery_id, 'failed', 'mautic_http');
        return ['ok' => false, 'status' => 'failed', 'attempt_id' => $attempt_id];
    }

    private static function notify_failure(string $delivery_id, string $state, string $error_class, string $detail = ''): void {
        if (!class_exists('FLACSO_Error_Notifier')) {
            return;
        }

        if (function_exists('get_option')
            && (string) get_option('flacso_mautic_error_alerts_enabled', '1') !== '1') {
            return;
        }

        $message = sprintf(
            'Entrega transaccional de consulta %s: estado=%s clase=%s%s',
            $delivery_id,
            $state,
            $error_class,
            trim($detail) !== '' ? ' detalle=' . trim($detail) : ''
        );

        try {
            FLACSO_Error_Notifier::report($message, __FILE__, __LINE__, 'consultas_delivery');
        } catch (Throwable $e) {
            // Una alerta nunca debe cambiar el estado de la entrega.
        }
    }
}
