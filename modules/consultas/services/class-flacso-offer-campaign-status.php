<?php
/**
 * Recorrido real de las consultas de oferta que están en una campaña de Mautic.
 *
 * La bandeja guarda sólo que la consulta entró a su campaña; si el acuse ya salió
 * o cuándo sale el seguimiento lo sabe Mautic. Este servicio se lo pregunta en
 * lote y guarda la respuesta unos minutos. Si Mautic no responde, devuelve vacío
 * y la bandeja muestra lo que ya sabe: nunca bloquea ni falla por esto.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Mautic_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mautic-client.php';
}

final class FLACSO_Offer_Campaign_Status {
    /** Mismo tope que el endpoint del plugin de Mautic. */
    private const CHUNK_SIZE = 50;

    private const CACHE_PREFIX = 'flacso_campaign_status_';

    /** Estados que ya no cambian: se guardan mucho más tiempo. */
    private const FINAL_FOLLOW_UP = ['sent', 'cancelled', 'completed', 'not_applicable', 'failed'];

    public const TTL_LIVE = 300;
    public const TTL_FINAL = 86400;

    /**
     * @param array<int, array<string, mixed>> $rows Filas de la bandeja (offer_inquiries).
     * @return array<string, array> consultaId => estado; sólo las consultas que Mautic pudo resolver.
     */
    public static function for_rows(array $rows): array {
        $statuses = [];
        $pending = [];

        foreach ($rows as $row) {
            $item = self::item_from_row($row);
            if ($item === null) {
                continue;
            }
            $cached = self::cache_get($item['consultaId']);
            if ($cached !== null) {
                $statuses[$item['consultaId']] = $cached;
                continue;
            }
            $pending[$item['consultaId']] = $item;
        }

        foreach (array_chunk(array_values($pending), self::CHUNK_SIZE) as $chunk) {
            $response = FLACSO_Mautic_Client::fetch_inquiry_statuses($chunk);
            if (empty($response['ok'])) {
                // Mautic no responde: no insistir con el resto de las páginas de esta carga.
                break;
            }
            foreach ($response['statuses'] as $consulta_id => $status) {
                if (!is_array($status) || empty($status['found'])) {
                    continue;
                }
                $consulta_id = (string) $consulta_id;
                $statuses[$consulta_id] = $status;
                self::cache_set($consulta_id, $status);
            }
        }

        return $statuses;
    }

    /**
     * Pedido a Mautic para una fila, o null si la consulta no está en una campaña.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function item_from_row(array $row): ?array {
        if (strtolower(trim((string) ($row['mauticCampaignStatus'] ?? ''))) !== 'joined') {
            return null;
        }

        $consulta_id = trim((string) ($row['consultaId'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $campaign_id = (int) ($row['mauticCampaignId'] ?? 0);
        $offer_id = (int) ($row['item_wp_id'] ?? $row['offerWpId'] ?? 0);
        // createdAt está en UTC; inquiryAt es hora local de Montevideo y sin zona.
        $since = trim((string) ($row['createdAt'] ?? ''));
        if ($consulta_id === '' || $email === '' || $campaign_id <= 0 || $offer_id <= 0 || $since === '') {
            return null;
        }

        $item = [
            'consultaId' => $consulta_id,
            'email' => $email,
            'campaignId' => $campaign_id,
            'offerWpId' => $offer_id,
            'since' => self::iso($since),
        ];
        $until = trim((string) ($row['nextInquiryAt'] ?? ''));
        if ($until !== '') {
            $item['until'] = self::iso($until);
        }

        return $item;
    }

    /**
     * Fechas de la base (createdAt, UTC y sin zona) a ISO 8601.
     */
    private static function iso(string $value): string {
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
        } catch (Throwable $e) {
            return $value;
        }
    }

    /** @return array|null */
    private static function cache_get(string $consulta_id): ?array {
        if (!function_exists('get_transient')) {
            return null;
        }
        $cached = get_transient(self::CACHE_PREFIX . md5($consulta_id));

        return is_array($cached) ? $cached : null;
    }

    private static function cache_set(string $consulta_id, array $status): void {
        if (!function_exists('set_transient')) {
            return;
        }
        $final = in_array((string) ($status['followUp']['state'] ?? ''), self::FINAL_FOLLOW_UP, true)
            && in_array((string) ($status['acuse']['state'] ?? ''), ['sent', 'failed'], true);
        set_transient(self::CACHE_PREFIX . md5($consulta_id), $status, $final ? self::TTL_FINAL : self::TTL_LIVE);
    }
}
