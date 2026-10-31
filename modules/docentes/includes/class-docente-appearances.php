<?php
if (!defined('ABSPATH')) exit;

/**
 * Índice de apariciones de cada persona en el sitio publicado.
 *
 * Una aparición es un contenido público donde la persona figura: oferta, cohorte,
 * edición de seminario o autoridades. Cada contenido cuenta una vez por persona,
 * aunque figure en varios equipos de ese contenido.
 */
final class Docente_Appearances {
    public const KIND_OFERTA = 'oferta';
    public const KIND_COHORTE = 'cohorte';
    public const KIND_EDICION = 'edicion';
    public const KIND_AUTORIDADES = 'autoridades';

    /** @var array<int,array<string,mixed>>|null */
    private static $cache = null;

    /** @return array<string,array{0:string,1:string}> tipo => [singular, plural] */
    public static function kinds(): array {
        return [
            self::KIND_OFERTA => [__('oferta', 'flacso-uruguay'), __('ofertas', 'flacso-uruguay')],
            self::KIND_COHORTE => [__('cohorte', 'flacso-uruguay'), __('cohortes', 'flacso-uruguay')],
            self::KIND_EDICION => [__('edición', 'flacso-uruguay'), __('ediciones', 'flacso-uruguay')],
            self::KIND_AUTORIDADES => [__('página de autoridades', 'flacso-uruguay'), __('páginas de autoridades', 'flacso-uruguay')],
        ];
    }

    /**
     * Cuenta apariciones por persona a partir de contenidos ya leídos.
     *
     * @param array<int,array{kind:string,title:string,people:array<int,mixed>}> $contents
     * @return array<int,array{total:int,kinds:array<string,int>,titles:array<int,string>}>
     */
    public static function tally(array $contents): array {
        $index = [];
        foreach ($contents as $content) {
            $kind = (string) ($content['kind'] ?? '');
            $title = trim((string) ($content['title'] ?? ''));
            $people = array_unique(array_filter(array_map('absint', (array) ($content['people'] ?? []))));
            foreach ($people as $person_id) {
                if (!isset($index[$person_id])) {
                    $index[$person_id] = ['total' => 0, 'kinds' => [], 'titles' => []];
                }
                $index[$person_id]['total']++;
                $index[$person_id]['kinds'][$kind] = ($index[$person_id]['kinds'][$kind] ?? 0) + 1;
                if ($title !== '') {
                    $index[$person_id]['titles'][] = $title;
                }
            }
        }
        return $index;
    }

    /**
     * Identificadores de persona dentro de equipos con la forma
     * [grupo => ['docentes' => [['id' => N], ...]]].
     *
     * @param mixed $groups
     * @return int[]
     */
    public static function team_member_ids($groups): array {
        $ids = [];
        foreach ((array) $groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ((array) ($group['docentes'] ?? []) as $member) {
                $id = is_array($member) ? absint($member['id'] ?? 0) : absint($member);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /**
     * @param mixed $authorities Opción flacso_autoridades_data.
     * @return int[]
     */
    public static function authority_ids($authorities): array {
        $ids = [];
        if (is_array($authorities)) {
            array_walk_recursive($authorities, static function ($value, $key) use (&$ids): void {
                if ($key === 'docente_id' && absint($value) > 0) {
                    $ids[] = absint($value);
                }
            });
        }
        return $ids;
    }

    /** @return array<int,array{total:int,kinds:array<string,int>,titles:array<int,string>}> */
    public static function index(): array {
        if (self::$cache === null) {
            self::$cache = self::tally(self::published_contents());
        }
        return self::$cache;
    }

    /** @return array{total:int,kinds:array<string,int>,titles:array<int,string>} */
    public static function for_person(int $person_id): array {
        $index = self::index();
        return $index[$person_id] ?? ['total' => 0, 'kinds' => [], 'titles' => []];
    }

    /** @return array<int,array{kind:string,title:string,people:int[]}> */
    private static function published_contents(): array {
        $contents = [];
        $published = static function (string $type): array {
            return get_posts([
                'post_type' => $type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);
        };

        $offers = array_map('intval', $published('oferta-academica'));
        foreach ($offers as $offer_id) {
            $people = self::team_member_ids(get_post_meta($offer_id, 'equipo_academico', true));
            $people[] = absint(get_post_meta($offer_id, 'asistente_academica_docente_id', true));
            $people[] = absint(get_post_meta($offer_id, 'carta_contacto_docente_id', true));
            $contents[] = ['kind' => self::KIND_OFERTA, 'title' => get_the_title($offer_id), 'people' => $people];
        }

        foreach ($published('cohorte') as $cohort_id) {
            $parent = absint(get_post_meta($cohort_id, 'oferta_academica_id', true));
            if (!in_array($parent, $offers, true)) {
                continue;
            }
            $contents[] = [
                'kind' => self::KIND_COHORTE,
                'title' => trim(get_the_title($parent) . ' · ' . get_the_title($cohort_id)),
                'people' => self::team_member_ids(get_post_meta($cohort_id, 'equipos', true)),
            ];
        }

        $seminars = array_map('intval', $published('seminario'));
        foreach ($published('edicion') as $edition_id) {
            $parent = absint(get_post_meta($edition_id, 'seminario_id', true));
            if (!in_array($parent, $seminars, true)) {
                continue;
            }
            $docentes = get_post_meta($edition_id, 'docentes', true);
            $contents[] = [
                'kind' => self::KIND_EDICION,
                'title' => trim(get_the_title($parent) . ' · ' . get_the_title($edition_id)),
                'people' => is_array($docentes) ? $docentes : [],
            ];
        }

        $authorities = self::authority_ids(get_option('flacso_autoridades_data', []));
        if ($authorities) {
            $contents[] = ['kind' => self::KIND_AUTORIDADES, 'title' => __('Página de autoridades', 'flacso-uruguay'), 'people' => $authorities];
        }

        return $contents;
    }
}
