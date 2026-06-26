<?php
/**
 * Template para el archivo de Taxonomía: Tipo de Oferta Académica.
 * Replicando fielmente el diseño de flacso.edu.uy
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_oferta_archive_uppercase_label')) {
    function flacso_oferta_archive_uppercase_label(string $label): string {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($label, 'UTF-8')
            : strtoupper($label);
    }
}

if (!function_exists('flacso_oferta_archive_get_start_data')) {
    function flacso_oferta_archive_get_start_data(int $post_id): array {
        $raw = get_post_meta($post_id, 'proximo_inicio', true);
        if (is_array($raw)) {
            $raw = reset($raw);
        }

        $raw = trim((string) $raw);
        $precision = strtolower(trim((string) get_post_meta($post_id, 'proximo_inicio_precision', true)));
        $label = $raw;

        if ($raw !== '' && class_exists('Oferta_Renderer')) {
            $formatted = Oferta_Renderer::format_proximo_inicio_text($raw, $precision);
            if ($formatted !== '') {
                $label = $formatted;
            }
        }

        $result = [
            'raw' => $raw,
            'precision' => $precision,
            'label' => $label,
            'iso' => '',
            'sort_timestamp' => PHP_INT_MAX,
            'group_sort' => PHP_INT_MAX,
            'group_key' => 'sin-fecha',
            'group_label' => __('Sin fecha de inicio confirmada', 'flacso-oferta-academica'),
        ];

        if ($raw === '') {
            return $result;
        }

        $timezone = wp_timezone();

        if (
            preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $raw, $matches)
            || preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $matches)
        ) {
            if (strlen($matches[1]) === 4) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } else {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
            }

            if (checkdate($month, $day, $year)) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d|', sprintf('%04d-%02d-%02d', $year, $month, $day), $timezone);
                if ($date instanceof DateTimeImmutable) {
                    $month_anchor = $date->modify('first day of this month');
                    $group_sort = $month_anchor->getTimestamp();

                    $result['iso'] = $date->format('Y-m-d');
                    $result['sort_timestamp'] = $date->getTimestamp();
                    $result['group_sort'] = $group_sort;
                    $result['group_key'] = $month_anchor->format('Y-m');
                    $result['group_label'] = flacso_oferta_archive_uppercase_label(
                        wp_date('F Y', $group_sort, $timezone)
                    );

                    return $result;
                }
            }
        }

        if (
            preg_match('/^(\d{4})[-\/](\d{1,2})$/', $raw, $matches)
            || preg_match('/^(\d{1,2})[-\/](\d{4})$/', $raw, $matches)
        ) {
            if (strlen($matches[1]) === 4) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
            } else {
                $month = (int) $matches[1];
                $year = (int) $matches[2];
            }

            if ($month >= 1 && $month <= 12) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d|', sprintf('%04d-%02d-01', $year, $month), $timezone);
                if ($date instanceof DateTimeImmutable) {
                    $group_sort = $date->getTimestamp();

                    $result['sort_timestamp'] = $group_sort;
                    $result['group_sort'] = $group_sort;
                    $result['group_key'] = $date->format('Y-m');
                    $result['group_label'] = flacso_oferta_archive_uppercase_label(
                        wp_date('F Y', $group_sort, $timezone)
                    );

                    return $result;
                }
            }
        }

        if (preg_match('/^\d{4}$/', $raw)) {
            $year = (int) $raw;
            $date = DateTimeImmutable::createFromFormat('Y-m-d|', sprintf('%04d-01-01', $year), $timezone);
            if ($date instanceof DateTimeImmutable) {
                $year_sort = $date->getTimestamp();

                $result['sort_timestamp'] = $year_sort;
                $result['group_sort'] = $year_sort;
                $result['group_key'] = 'year-' . $year;
                $result['group_label'] = sprintf(
                    __('%s (mes a confirmar)', 'flacso-oferta-academica'),
                    $year
                );
            }
        }

        return $result;
    }
}

$term = get_queried_object();
$taxonomy_name = $term->name;
if ($term->slug === 'maestria') {
    $taxonomy_name = 'Maestrías';
} elseif ($term->slug === 'especializacion') {
    $taxonomy_name = 'Especializaciones';
} elseif ($term->slug === 'diplomado') {
    $taxonomy_name = 'Diplomados';
} elseif ($term->slug === 'diploma') {
    $taxonomy_name = 'Diplomas';
}

$term_image_data = null;
if (class_exists('Oferta_Taxonomies') && method_exists('Oferta_Taxonomies', 'get_term_featured_image_data')) {
    $term_image_data = Oferta_Taxonomies::get_term_featured_image_data($term);
}

if (class_exists('Oferta_Renderer')) {
    Oferta_Renderer::enqueue_styles();
}

$archive_args = [
    'post_type' => 'oferta-academica',
    'post_status' => current_user_can('manage_options') ? ['publish', 'private'] : ['publish'],
    'posts_per_page' => -1,
    'no_found_rows' => true,
    'has_password' => false,
    'tax_query' => [
        [
            'taxonomy' => 'tipo-oferta-academica',
            'field' => 'term_id',
            'terms' => (int) $term->term_id,
        ],
    ],
];

$ofertas_query = new WP_Query($archive_args);
$offers_grouped = [];

if ($ofertas_query->have_posts()) {
    while ($ofertas_query->have_posts()) {
        $ofertas_query->the_post();

        $post_id = get_the_ID();
        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium_large');
        $inscripciones_abiertas = get_post_meta($post_id, 'inscripciones_abiertas', true);
        $is_open = ($inscripciones_abiertas === '1' || $inscripciones_abiertas === 'true' || $inscripciones_abiertas === true || $inscripciones_abiertas === 1);

        $data = class_exists('Oferta_Data_Schema') ? Oferta_Data_Schema::get_schema($post_id) : [];
        $duracion = !empty($data['duracion_meses']) && class_exists('Oferta_Renderer')
            ? Oferta_Renderer::format_duration_months((string) $data['duracion_meses'], 'flacso-uruguay')
            : '';
        $start_data = flacso_oferta_archive_get_start_data($post_id);
        $group_key = (string) $start_data['group_key'];

        if (!isset($offers_grouped[$group_key])) {
            $offers_grouped[$group_key] = [
                'label' => (string) $start_data['group_label'],
                'sort' => (int) $start_data['group_sort'],
                'items' => [],
            ];
        }

        $offers_grouped[$group_key]['items'][] = [
            'title' => get_the_title($post_id),
            'permalink' => get_permalink($post_id),
            'thumbnail_url' => $thumbnail_url,
            'is_open' => $is_open,
            'duracion' => $duracion,
            'excerpt' => wp_trim_words(get_the_excerpt($post_id), 18, '...'),
            'start_label' => (string) $start_data['label'],
            'start_iso' => (string) $start_data['iso'],
            'sort_timestamp' => (int) $start_data['sort_timestamp'],
        ];
    }
}

wp_reset_postdata();

if (!empty($offers_grouped)) {
    uasort($offers_grouped, static function (array $a, array $b): int {
        if ($a['sort'] === $b['sort']) {
            return strcasecmp((string) $a['label'], (string) $b['label']);
        }

        return ((int) $a['sort'] < (int) $b['sort']) ? -1 : 1;
    });

    foreach ($offers_grouped as &$group) {
        usort($group['items'], static function (array $a, array $b): int {
            $a_open = !empty($a['is_open']) ? 0 : 1;
            $b_open = !empty($b['is_open']) ? 0 : 1;

            if ($a_open !== $b_open) {
                return $a_open <=> $b_open;
            }

            $a_sort = (int) ($a['sort_timestamp'] ?? PHP_INT_MAX);
            $b_sort = (int) ($b['sort_timestamp'] ?? PHP_INT_MAX);

            if ($a_sort === $b_sort) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }

            return ($a_sort < $b_sort) ? -1 : 1;
        });
    }
    unset($group);
}

$total_ofertas = 0;
$tiene_abiertas = false;
foreach ($offers_grouped as $group_count) {
    foreach ($group_count['items'] as $item) {
        $total_ofertas++;
        if (!empty($item['is_open'])) {
            $tiene_abiertas = true;
        }
    }
}

get_header();
?>

<div id="inner-wrap" class="wrap kt-clear flacso-oferta-academica-premium">
    <div id="primary" class="content-area">
        <div class="content-container site-container" style="padding-top: 60px; padding-bottom: 60px;">
            <header class="entry-header page-title flacso-taxonomy-hero" style="margin-bottom: 40px;">
                <div class="flacso-taxonomy-hero__content">
                    <h1 class="entry-title" style="color: var(--flacso-blue-dark); font-weight: 800; font-size: clamp(2.5rem, 5vw, 3.5rem); margin-bottom: 1rem;"><?php echo esc_html($taxonomy_name); ?></h1>
                    
                    <div class="taxonomy-description" style="font-size: 1.15rem; line-height: 1.6; max-width: 900px; color: #475569;">
                        <p>Conocé las propuestas de <?php echo esc_html($taxonomy_name_lower); ?> disponibles. Revisá la duración, modalidad, fecha de inicio y requisitos de inscripción de cada programa.</p>
                    </div>

                    <?php if ($total_ofertas > 0) : ?>
                        <div class="flacso-hero-actions">
                            <span class="flacso-hero-pill"><?php echo esc_html($hero_badge); ?></span>

                            <p class="flacso-hero-lead">
                                <?php echo esc_html($hero_title); ?>.<br>
                                <span style="font-size: 0.9em; font-weight: 500; opacity: 0.9;"><?php echo esc_html($hero_text); ?></span>
                            </p>

                            <a href="#flacso-listado-ofertas" class="flacso-hero-button">
                                <?php echo esc_html($hero_button); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($term_image_data['large']) || !empty($term_image_data['url'])) : ?>
                    <div class="flacso-taxonomy-hero__media">
                        <img
                            src="<?php echo esc_url((string) ($term_image_data['large'] ?? $term_image_data['url'] ?? '')); ?>"
                            alt="<?php echo esc_attr((string) ($term_image_data['alt'] ?? $taxonomy_name)); ?>"
                            class="flacso-taxonomy-hero__image"
                        />
                    </div>
                <?php endif; ?>
            </header>

            <div id="flacso-listado-ofertas"></div>
            <?php if (!empty($offers_grouped)) : ?>
                <?php foreach ($offers_grouped as $group) : ?>
                    <?php $group_id = 'grupo-' . sanitize_title((string) $group['label']); ?>
                    <section class="flacso-ofertas-group" aria-labelledby="<?php echo esc_attr($group_id); ?>">
                        <h2 id="<?php echo esc_attr($group_id); ?>" class="flacso-ofertas-group__title">
                            <?php echo esc_html((string) $group['label']); ?>
                        </h2>

                        <div class="flacso-ofertas-grid">
                            <?php foreach ($group['items'] as $item) : ?>
                                <article class="grid-item-wrap">
                                    <a
                                        href="<?php echo esc_url((string) $item['permalink']); ?>"
                                        class="flacso-premium-card flacso-premium-card--link h-100 w-100"
                                        aria-label="<?php echo esc_attr('Ver información de ' . (string) $item['title']); ?>"
                                        data-flacso-offer-click="1"
                                        data-offer-title="<?php echo esc_attr((string) $item['title']); ?>"
                                        data-offer-status="<?php echo !empty($item['is_open']) ? 'inscripciones_abiertas' : 'proxima_apertura'; ?>"
                                    >
                                        <div class="flacso-premium-card__image-wrap">
                                            <?php if (!empty($item['thumbnail_url'])) : ?>
                                                <img src="<?php echo esc_url((string) $item['thumbnail_url']); ?>" class="flacso-premium-card__img" alt="<?php echo esc_attr((string) $item['title']); ?>">
                                            <?php else : ?>
                                                <div class="flacso-premium-card__img-placeholder">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-mortarboard" viewBox="0 0 16 16">
                                                        <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a2 2 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5ZM8 8.46 1.758 5.965 8 3.052l6.242 2.913L8 8.46Z"/>
                                                        <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Zm-.068 1.873.22-.748 3.496 1.311a.5.5 0 0 0 .352 0l3.496-1.311.22.748L8 12.46l-3.892-1.555Z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>

                                            <div class="flacso-premium-card__badges">
                                                <?php if (!empty($item['is_open'])) : ?>
                                                    <span class="flacso-badge flacso-badge--open">Inscripciones abiertas</span>
                                                <?php else : ?>
                                                    <span class="flacso-badge flacso-badge--soon">Próxima apertura</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="flacso-premium-card__body">
                                            <div class="flacso-premium-card__meta">
                                                <?php if (!empty($item['duracion'])) : ?>
                                                    <span class="flacso-meta-item">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>
                                                        <?php echo esc_html((string) $item['duracion']); ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($item['start_label'])) : ?>
                                                    <span class="flacso-meta-item flacso-meta-item--start">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v1H0V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5ZM16 14a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V5h16v9ZM4.5 7a.5.5 0 0 0 0 1h1a.5.5 0 0 0 0-1h-1Z"/></svg>
                                                        <?php if (!empty($item['start_iso'])) : ?>
                                                            <time datetime="<?php echo esc_attr((string) $item['start_iso']); ?>"><?php echo esc_html((string) $item['start_label']); ?></time>
                                                        <?php else : ?>
                                                            <span><?php echo esc_html((string) $item['start_label']); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <h3 class="flacso-premium-card__title">
                                                <?php echo esc_html((string) $item['title']); ?>
                                            </h3>

                                            <div class="flacso-premium-card__excerpt">
                                                <?php echo esc_html((string) $item['excerpt']); ?>
                                            </div>

                                            <div class="flacso-premium-card__footer">
                                                <?php
$card_cta = !empty($item['is_open'])
    ? 'Ver información e inscribirme'
    : 'Ver información del programa';
?>
<span class="flacso-premium-card__cta <?php echo !empty($item['is_open']) ? 'flacso-premium-card__cta--open' : ''; ?>">
                                                    <?php echo esc_html($card_cta); ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                        <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="col-12 text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="text-muted mb-3 d-block mx-auto" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>
                    <h3 class="fw-bold" style="color: var(--flacso-blue-dark);">Próximamente</h3>
                    <p class="text-muted">En este momento no hay propuestas académicas listadas en esta categoría. Te invitamos a estar atento/a a nuestras próximas aperturas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
:root {
    --flacso-blue-dark: #051938;
    --flacso-blue-light: #163970;
    --flacso-yellow: #fcd116;
    --flacso-gray-bg: #f8fafc;
}

.flacso-ofertas-group + .flacso-ofertas-group {
    margin-top: 1rem;
}

.flacso-taxonomy-hero {
    display: grid;
    gap: 1.75rem;
    align-items: center;
}

.flacso-taxonomy-hero__content {
    min-width: 0;
}

.flacso-taxonomy-hero__media {
    max-width: 420px;
    width: 100%;
    justify-self: start;
}

.flacso-taxonomy-hero__image {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 10;
    object-fit: cover;
    border-radius: 20px;
    box-shadow: 0 18px 44px rgba(5, 25, 56, 0.14);
}

@media (min-width: 980px) {
    .flacso-taxonomy-hero {
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr);
    }

    .flacso-taxonomy-hero__media {
        justify-self: end;
    }
}

.flacso-ofertas-group__title {
    margin: 0 0 1.4rem;
    padding: 0.7rem 0.9rem;
    border-left: 4px solid var(--flacso-yellow);
    border-radius: 6px;
    background: var(--flacso-gray-bg);
    color: var(--flacso-blue-dark);
    font-size: clamp(1.2rem, 2vw, 1.5rem);
    font-weight: 800;
    letter-spacing: 0.04em;
}

.flacso-ofertas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
    gap: 2rem;
    padding-bottom: 2rem;
}

.flacso-ofertas-grid .grid-item-wrap {
    width: 100%;
}

.flacso-premium-card {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    position: relative;
    transform: translateY(0);
}

.flacso-premium-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(5, 25, 56, 0.12);
}

.flacso-premium-card__image-wrap {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #f1f5f9;
}

.flacso-premium-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.flacso-premium-card:hover .flacso-premium-card__img {
    transform: scale(1.05);
}

.flacso-premium-card__img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
}

.flacso-premium-card__badges {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.flacso-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.flacso-badge--open {
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
}

.flacso-premium-card__body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.flacso-premium-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 12px;
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}

.flacso-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.flacso-meta-item svg {
    color: var(--flacso-blue-light);
    flex: 0 0 auto;
}

.flacso-premium-card__title {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.35;
    margin-bottom: 12px;
    color: var(--flacso-blue-dark);
}

.flacso-premium-card__title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

.flacso-premium-card:hover .flacso-premium-card__title a {
    color: var(--flacso-blue-light);
}

.flacso-premium-card__excerpt {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #475569;
    margin-bottom: 24px;
    flex-grow: 1;
}

.flacso-premium-card__footer {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}

.flacso-premium-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--flacso-blue-light);
    transition: gap 0.3s ease;
}

.flacso-premium-card:hover .flacso-premium-card__cta {
    gap: 12px;
    color: var(--flacso-yellow);
}

.flacso-premium-card:hover .flacso-premium-card__cta svg {
    fill: var(--flacso-yellow);
}

.flacso-hero-actions {
    margin-top: 1.4rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.85rem;
}

.flacso-hero-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    background: var(--flacso-yellow);
    color: var(--flacso-blue-dark);
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.flacso-hero-lead {
    margin: 0;
    max-width: 620px;
    color: var(--flacso-blue-dark);
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.45;
}

.flacso-hero-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0.85rem 1.25rem;
    border-radius: 999px;
    background: #248138;
    color: #ffffff;
    font-weight: 900;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(36, 129, 56, 0.22);
}

.flacso-hero-button:hover,
.flacso-hero-button:focus {
    background: #1b6d2b;
    color: #ffffff;
    text-decoration: none;
}

.flacso-taxonomy-hero__media {
    position: relative;
}

.flacso-taxonomy-hero__media::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(22, 57, 112, 0.22), rgba(5, 25, 56, 0.08));
    pointer-events: none;
}

.flacso-premium-card__cta {
    width: 100%;
    justify-content: center;
    min-height: 46px;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--flacso-blue-light);
    color: #ffffff;
    font-weight: 900;
    text-align: center;
}

.flacso-premium-card__cta svg {
    fill: #ffffff;
}

.flacso-premium-card:hover .flacso-premium-card__cta {
    gap: 8px;
    color: #ffffff;
    background: var(--flacso-blue-dark);
}

.flacso-premium-card:hover .flacso-premium-card__cta svg {
    fill: #ffffff;
}

@media (max-width: 767px) {
    .flacso-oferta-academica-premium .content-container {
        padding-top: 22px !important;
        padding-bottom: 90px !important;
    }

    .flacso-taxonomy-hero {
        margin-bottom: 24px !important;
        gap: 1rem;
    }

    .flacso-taxonomy-hero .entry-title {
        font-size: 2rem !important;
        line-height: 1.05;
        margin-bottom: 0.65rem !important;
    }

    .taxonomy-description {
        font-size: 0.98rem !important;
        line-height: 1.45 !important;
    }

    .flacso-hero-actions {
        margin-top: 1.1rem;
    }

    .flacso-hero-button {
        width: 100%;
        min-height: 52px;
    }
    .flacso-taxonomy-hero__media {
        display: none;
    }

    .flacso-ofertas-group__title {
        position: sticky;
        top: 0;
        z-index: 3;
        margin-bottom: 1rem;
        font-size: 1rem;
        background: #ffffff;
        box-shadow: 0 6px 16px rgba(5, 25, 56, 0.08);
    }

    .flacso-ofertas-grid {
        gap: 1rem;
        grid-template-columns: 1fr;
    }

    .flacso-premium-card {
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(5, 25, 56, 0.08);
    }

    .flacso-premium-card__image-wrap {
        height: 145px;
    }

    .flacso-premium-card__body {
        padding: 16px;
    }

    .flacso-premium-card__meta {
        gap: 8px;
        margin-bottom: 10px;
        font-size: 0.78rem;
    }

    .flacso-premium-card__title {
        font-size: 1.08rem;
        margin-bottom: 8px;
    }

    .flacso-premium-card__excerpt {
        display: none;
    }

    .flacso-premium-card__footer {
        padding-top: 12px;
    }

    .flacso-badge {
        font-size: 0.68rem;
        padding: 5px 9px;
    }
}
.flacso-badge--soon {
    background: #e9edf2;
    color: var(--flacso-blue-dark);
}
.flacso-premium-card--link {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
}

.flacso-premium-card--link:hover,
.flacso-premium-card--link:focus {
    color: inherit;
    text-decoration: none;
}

.flacso-premium-card--link:focus-visible {
    outline: 3px solid var(--flacso-yellow);
    outline-offset: 4px;
}

.flacso-premium-card__title {
    transition: color 0.2s ease;
}

.flacso-premium-card:hover .flacso-premium-card__title {
    color: var(--flacso-blue-light);
}

.flacso-premium-card__cta--open {
    background: #248138;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(36, 129, 56, 0.22);
}

.flacso-premium-card:hover .flacso-premium-card__cta--open {
    background: #1b6d2b;
    color: #ffffff;
}

.flacso-premium-card:hover .flacso-premium-card__cta--open svg {
    fill: #ffffff;
}

@media (max-width: 767px) {
    .flacso-premium-card__cta {
        min-height: 50px;
        font-size: 0.94rem;
    }

    .flacso-premium-card__meta {
        row-gap: 6px;
    }

    .flacso-premium-card__badges {
        top: 10px;
        right: 10px;
    }
}
</style>

<script>
(function() {
    var termTitle = <?php echo wp_json_encode((string) single_term_title('', false)); ?>;

    if (typeof window.fbq === 'function') {
        try {
            window.fbq('track', 'ViewContent', {
                content_name: 'Listado: ' + termTitle,
                content_category: 'listado_ofertas',
                flacso_stage: 'listado_tipo_oferta'
            });
        } catch (e) {}
    }

    document.addEventListener('click', function(event) {
        var link = event.target.closest ? event.target.closest('a[data-flacso-offer-click]') : null;
        if (!link) return;

        var offerTitle = link.getAttribute('data-offer-title') || '';
        var offerStatus = link.getAttribute('data-offer-status') || '';
        var destination = link.href || '';

        if (typeof window.gtag === 'function') {
            try {
                window.gtag('event', 'oferta_academica_click', {
                    event_category: 'oferta_academica',
                    event_label: offerTitle,
                    oferta_nombre: offerTitle,
                    oferta_estado: offerStatus,
                    tipo_oferta: termTitle,
                    destination_url: destination
                });
            } catch (e) {}
        }

        if (typeof window.fbq === 'function') {
            try {
                window.fbq('trackCustom', 'OfertaAcademicaClick', {
                    content_name: offerTitle,
                    content_category: termTitle,
                    flacso_offer_status: offerStatus,
                    flacso_stage: 'click_desde_listado'
                });
            } catch (e) {}
        }
    }, true);
})();
</script>
<?php
get_footer();

