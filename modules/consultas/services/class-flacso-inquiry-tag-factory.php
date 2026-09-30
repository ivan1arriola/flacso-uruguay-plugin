<?php
/**
 * Única fábrica de etiquetas para consultas académicas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Inquiry_Tag_Factory {
    public static function from_snapshot(array $snapshot): array {
        $academic = is_array($snapshot['academic'] ?? null) ? $snapshot['academic'] : [];
        $code = self::slug((string) ($academic['code'] ?? ''));
        $cohort = isset($academic['cohortNumber']) && $academic['cohortNumber'] !== ''
            ? (int) $academic['cohortNumber']
            : 0;

        $tags = [];
        if ($code !== '') {
            $tags[] = 'interes-' . $code;
            if ($cohort > 0) {
                $tags[] = $code . '-c' . $cohort;
            }
        }
        $tags[] = 'origen-web-consultas';

        return array_values(array_unique($tags));
    }

    public static function slug(string $value): string {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n','Ü'=>'u',
        ]);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }
}
