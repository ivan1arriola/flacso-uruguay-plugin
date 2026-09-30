<?php

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Mautic_Integration_Log {
    public static function write(array $event): void {
        $allowed = [
            'consulta_id', 'contact_id', 'campaign_id', 'operation', 'result',
            'http_code', 'error_class', 'occurred_at',
        ];
        $safe = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $event)) {
                $safe[$key] = $event[$key];
            }
        }
        $email = trim((string) ($event['email'] ?? ''));
        if ($email !== '') {
            $safe['email_hash'] = hash('sha256', strtolower($email));
        }
        $safe['occurred_at'] = $safe['occurred_at'] ?? gmdate('c');
        error_log('[FLACSO Mautic] ' . json_encode($safe, JSON_UNESCAPED_SLASHES));
    }
}
