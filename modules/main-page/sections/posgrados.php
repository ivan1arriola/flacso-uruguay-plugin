<?php
/**
 * Seccion: Carrusel 3D de Oferta Educativa
 * Archivo: modules/main-page/sections/posgrados.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_section_oferta_educativa_normalize_card')) {
    /**
     * @param array<string,mixed> $card
     * @return array<string,string>
     */
    function flacso_section_oferta_educativa_normalize_card(array $card): array
    {
        $title = trim(wp_strip_all_tags((string) ($card['title'] ?? '')));
        $desc = (string) ($card['desc'] ?? '');
        $image = esc_url((string) ($card['image'] ?? ''));

        $url = '';
        if (class_exists('Flacso_Main_Page_Settings')) {
            $url = Flacso_Main_Page_Settings::normalize_url_output((string) ($card['url'] ?? ''));
        }
        if ($url === '') {
            $url = esc_url((string) ($card['url'] ?? ''));
        }
        if ($url === '') {
            $url = '#';
        }

        return [
            'title' => $title,
            'desc' => $desc,
            'image' => $image,
            'url' => $url,
        ];
    }
}

if (!function_exists('flacso_section_oferta_educativa_get_cards')) {
    /**
     * @return array<int,array<string,string>>
     */
    function flacso_section_oferta_educativa_get_cards(): array
    {
        $section = class_exists('Flacso_Main_Page_Settings')
            ? Flacso_Main_Page_Settings::get_section('posgrados')
            : [];

        $cards = [];
        if (isset($section['cards']) && is_array($section['cards'])) {
            foreach ($section['cards'] as $card) {
                if (!is_array($card)) {
                    continue;
                }

                $normalized = flacso_section_oferta_educativa_normalize_card($card);
                if ($normalized['title'] === '') {
                    continue;
                }

                $cards[] = $normalized;
            }
        }

        if (!empty($cards)) {
            return $cards;
        }

        $fallback = [
            [
                'title' => 'Maestrías',
                'url' => '/formacion/maestrias/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-9.png',
                'desc' => 'Una maestría es una oportunidad de crecimiento profesional y académico. Todas las maestrías tienen un mínimo de 18 meses de cursada y terminan en un trabajo de investigación. Una maestría es un paso necesario para cursar un doctorado.',
            ],
            [
                'title' => 'Especializaciones',
                'url' => '/formacion/especializaciones/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-1.png',
                'desc' => 'La especialización es el grado académico previo a la maestría. Es una oportunidad de formación que permite la profundización y actualización de marcos teóricos, la incorporación de metodologías y herramientas en menor tiempo.',
            ],
            [
                'title' => 'Diplomas',
                'url' => '/formacion/diplomas/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-5-1024x1024.png',
                'desc' => 'Los diplomas representan propuestas de formación que funcionan como salidas intermedias hacia programas de mayor grado, combinando análisis temático y habilidades prácticas.',
            ],
            [
                'title' => 'Diplomados',
                'url' => '/formacion/diplomados/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-3.png',
                'desc' => 'Grado académico similar al de la especialización, expedido por la unidad académica. Prepara a cursantes para continuar hacia maestrías mediante seminarios y talleres.',
            ],
            [
                'title' => 'Seminarios',
                'url' => '/formacion/seminarios/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-2.png',
                'desc' => 'Espacios de formación intensiva con enfoque práctico, actualización temática y acompañamiento docente especializado.',
            ],
        ];

        $normalized_fallback = [];
        foreach ($fallback as $card) {
            $normalized_fallback[] = flacso_section_oferta_educativa_normalize_card($card);
        }

        return $normalized_fallback;
    }
}

if (!function_exists('flacso_section_oferta_educativa_render')) {
    /**
     * @param array<string,mixed> $atts
     */
    function flacso_section_oferta_educativa_render($atts = []): string
    {
        $section = class_exists('Flacso_Main_Page_Settings')
            ? Flacso_Main_Page_Settings::get_section('posgrados')
            : [];

        $show_title = !array_key_exists('show_title', $section) || !empty($section['show_title']);
        $title = trim(wp_strip_all_tags((string) ($section['title'] ?? 'Nuestra Oferta Educativa')));
        if ($title === '' || strcasecmp($title, 'NUESTROS POSGRADOS') === 0 || strcasecmp($title, 'Nuestros Posgrados') === 0) {
            $title = 'Nuestra Oferta Educativa';
        }
        $intro = (string) ($section['intro'] ?? '');
        $cards = flacso_section_oferta_educativa_get_cards();

        if (empty($cards)) {
            return '';
        }

        $instance_id = function_exists('wp_unique_id')
            ? wp_unique_id('flacso-oferta-educativa-')
            : ('flacso-oferta-educativa-' . wp_rand(1000, 9999));

        ob_start();
        ?>
<section class="nuestra-oferta-educativa nuestra-oferta-educativa-3d" data-oferta-educativa-3d>
  <div class="oferta-educativa-container flacso-content-shell">
    <?php if ($show_title && $title !== '') : ?>
      <h2 class="oferta-educativa-titulo"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro !== '') : ?>
      <div class="oferta-educativa-descripcion"><?php echo wp_kses_post($intro); ?></div>
    <?php endif; ?>

    <div class="oferta-educativa-3d-stage">
      <div class="oferta-educativa-3d-viewport" tabindex="0" aria-label="Carrusel de oferta educativa">
        <div class="oferta-educativa-3d-track">
          <?php foreach ($cards as $index => $card) : ?>
            <?php
            $card_title_id = $instance_id . '-card-' . $index;
            $card_desc_id = $card_title_id . '-description';
            $image_style = $card['image'] !== ''
                ? sprintf("background-image: url('%s');", esc_url($card['image']))
                : '';
            ?>
            <a class="oferta-item oferta-item--action" href="<?php echo esc_url($card['url']); ?>" aria-labelledby="<?php echo esc_attr($card_title_id); ?>" aria-describedby="<?php echo esc_attr($card_desc_id); ?>">
              <div class="oferta-imagen"<?php echo $image_style !== '' ? ' style="' . esc_attr($image_style) . '"' : ''; ?>></div>
              <div class="oferta-contenido">
                <h3 class="oferta-titulo-card" id="<?php echo esc_attr($card_title_id); ?>"><?php echo esc_html($card['title']); ?></h3>
                <p class="oferta-descripcion-card" id="<?php echo esc_attr($card_desc_id); ?>"><?php echo wp_kses_post($card['desc']); ?></p>
                <span class="visually-hidden">Toca para abrir la información del posgrado.</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="oferta-educativa-3d-controls" role="group" aria-label="Navegacion del carrusel de oferta educativa">
      <button type="button" class="oferta-educativa-3d-arrow" data-oferta-prev aria-label="Oferta anterior">&#8249;</button>
      <span class="oferta-educativa-3d-counter" data-oferta-counter aria-live="polite"><?php echo esc_html('1 / ' . count($cards)); ?></span>
      <button type="button" class="oferta-educativa-3d-arrow" data-oferta-next aria-label="Oferta siguiente">&#8250;</button>
    </div>
    <div class="oferta-educativa-3d-dots" aria-label="Navegación del carrusel"></div>
    <p class="visually-hidden" data-oferta-status aria-live="polite" aria-atomic="true"></p>
  </div>
</section>

<style>
  .nuestra-oferta-educativa-3d {
    position: relative;
    overflow: hidden;
    padding-block: var(--flacso-section-vertical-space, clamp(2rem, 3.5vw, 3.5rem));
    background:
      radial-gradient(circle at top center, rgba(254, 210, 34, 0.14), transparent 30%),
      linear-gradient(180deg, rgba(233, 237, 242, 0.55) 0%, rgba(255, 255, 255, 1) 100%);
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-container {
    position: relative;
    isolation: isolate;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-titulo {
    margin: 0 0 1rem;
    text-align: center;
    color: var(--global-palette1, #1d3a72);
    font-size: clamp(1.9rem, 1.45rem + 1.6vw, 3rem);
    line-height: 1.05;
    letter-spacing: 0.03em;
    font-weight: 800;
    position: relative;
    z-index: 4;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-descripcion {
    max-width: 980px;
    margin: 0 auto 1.15rem;
    text-align: center;
    color: var(--global-palette4, #2e2f34);
    font-size: clamp(1rem, 0.95rem + 0.2vw, 1.1rem);
    line-height: 1.7;
    position: relative;
    z-index: 4;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-stage {
    position: relative;
    z-index: 1;
    margin-top: clamp(0.45rem, 1.3vw, 1rem);
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-viewport {
    position: relative;
    min-height: clamp(620px, 64vw, 700px);
    height: clamp(620px, 64vw, 700px);
    perspective: 1800px;
    perspective-origin: center center;
    overflow: visible;
    padding-block: 0.6rem;
    touch-action: pan-y;
    cursor: grab;
    user-select: none;
    outline: none;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-viewport.is-dragging {
    cursor: grabbing;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-track {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
  }

  .nuestra-oferta-educativa-3d .oferta-item {
    --card-width: min(360px, 60vw);
    position: absolute;
    top: 53%;
    left: 50%;
    width: var(--card-width);
    display: flex;
    flex-direction: column;
    border-radius: 28px;
    overflow: hidden;
    text-decoration: none;
    background: #fff;
    border: 1px solid rgba(29, 58, 114, 0.12);
    box-shadow:
      0 30px 70px rgba(13, 27, 55, 0.22),
      0 10px 30px rgba(13, 27, 55, 0.1);
    transform-style: preserve-3d;
    backface-visibility: hidden;
    transform-origin: center center;
    transition:
      transform 700ms cubic-bezier(.2, .8, .2, 1),
      opacity 450ms ease,
      filter 450ms ease,
      box-shadow 450ms ease;
    will-change: transform, opacity;
    cursor: pointer;
  }

  .nuestra-oferta-educativa-3d .oferta-item:focus-visible {
    outline: 3px solid var(--global-palette2, #fed222);
    outline-offset: 4px;
  }

  .nuestra-oferta-educativa-3d .oferta-imagen {
    position: relative;
    flex: 0 0 auto;
    aspect-ratio: 1 / 1;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    overflow: hidden;
  }

  .nuestra-oferta-educativa-3d .oferta-imagen::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(15, 26, 45, 0.08) 0%, rgba(15, 26, 45, 0.34) 100%);
  }

  .nuestra-oferta-educativa-3d .oferta-contenido {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    flex-direction: column;
    gap: 0.9rem;
    padding: 1.4rem 1.35rem 1.5rem;
    background:
      linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  }

  .nuestra-oferta-educativa-3d .oferta-titulo-card {
    margin: 0;
    color: var(--global-palette1, #1d3a72);
    font-size: clamp(1.3rem, 1.1rem + 0.4vw, 1.65rem);
    line-height: 1.15;
    font-weight: 800;
    text-wrap: balance;
  }

  .nuestra-oferta-educativa-3d .oferta-descripcion-card {
    margin: 0;
    color: var(--global-palette4, #2e2f34);
    font-size: clamp(0.98rem, 0.95rem + 0.15vw, 1.05rem);
    line-height: 1.65;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.62rem;
    margin-top: 1.05rem;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-arrow {
    width: 2.25rem;
    height: 2.25rem;
    border: 0;
    border-radius: 999px;
    background: #ffffff;
    color: var(--global-palette1, #1d3a72);
    font-size: 1.45rem;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(15, 26, 45, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 200ms ease, box-shadow 200ms ease;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-arrow:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(15, 26, 45, 0.15);
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-arrow:focus-visible {
    outline: 2px solid var(--global-palette1, #1d3a72);
    outline-offset: 2px;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-counter {
    min-width: 4.2rem;
    text-align: center;
    font-weight: 700;
    color: var(--global-palette1, #1d3a72);
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-dots {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-top: 0.78rem;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-dot {
    display: block;
    min-width: 0;
    min-height: 0;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: 0;
    padding: 0;
    line-height: 0;
    font-size: 0;
    appearance: none;
    -webkit-appearance: none;
    box-shadow: none;
    cursor: pointer;
    background: rgba(29, 58, 114, 0.22);
    transition: transform 200ms ease, background-color 200ms ease;
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-dot.is-active {
    background: var(--global-palette2, #fed222);
    transform: scale(1.3);
  }

  .nuestra-oferta-educativa-3d .oferta-educativa-3d-dot:focus-visible {
    outline: 2px solid var(--global-palette1, #1d3a72);
    outline-offset: 2px;
  }

  .nuestra-oferta-educativa-3d .visually-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    margin: -1px !important;
    padding: 0 !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    border: 0 !important;
    white-space: nowrap !important;
  }

  @media (max-width: 991.98px) {
    .nuestra-oferta-educativa-3d .oferta-educativa-3d-viewport {
      min-height: 620px;
      height: 620px;
    }

    .nuestra-oferta-educativa-3d .oferta-item {
      --card-width: min(340px, 72vw);
      top: 54%;
    }
  }

  @media (max-width: 767.98px) {
    .nuestra-oferta-educativa-3d .oferta-educativa-3d-viewport {
      min-height: 560px;
      height: 560px;
      padding-block: 0.5rem;
    }

    .nuestra-oferta-educativa-3d .oferta-item {
      --card-width: min(308px, 82vw);
      top: 55%;
      border-radius: 24px;
    }

    .nuestra-oferta-educativa-3d .oferta-contenido {
      padding: 1.15rem 1rem 1.2rem;
      gap: 0.75rem;
    }

    .nuestra-oferta-educativa-3d .oferta-educativa-3d-controls {
      margin-top: 0.85rem;
    }

    .nuestra-oferta-educativa-3d .oferta-educativa-3d-arrow {
      width: 2.1rem;
      height: 2.1rem;
      font-size: 1.3rem;
    }

    .nuestra-oferta-educativa-3d .oferta-descripcion-card {
      line-height: 1.55;
      font-size: 0.96rem;
    }
  }

  @media (max-width: 575.98px) {
    .nuestra-oferta-educativa-3d .oferta-educativa-descripcion {
      text-align: left;
      margin-bottom: 1.4rem;
    }

    .nuestra-oferta-educativa-3d .oferta-educativa-3d-viewport {
      min-height: 520px;
      height: 520px;
    }

    .nuestra-oferta-educativa-3d .oferta-item {
      --card-width: min(100%, 318px);
      top: 55%;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .nuestra-oferta-educativa-3d .oferta-item,
    .nuestra-oferta-educativa-3d .oferta-educativa-3d-dot {
      transition: none !important;
    }
  }
</style>

<script>
  (function () {
    const roots = document.querySelectorAll('[data-oferta-educativa-3d]');
    if (!roots.length) return;

    roots.forEach((root) => {
      const cards = Array.from(root.querySelectorAll('.oferta-item'));
      const dotsWrap = root.querySelector('.oferta-educativa-3d-dots');
      const viewport = root.querySelector('.oferta-educativa-3d-viewport');
      const prevBtn = root.querySelector('[data-oferta-prev]');
      const nextBtn = root.querySelector('[data-oferta-next]');
      const counterEl = root.querySelector('[data-oferta-counter]');
      const statusEl = root.querySelector('[data-oferta-status]');
      const controlsWrap = root.querySelector('.oferta-educativa-3d-controls');

      if (!cards.length || !viewport) return;

      let current = 0;
      let startX = 0;
      let currentX = 0;
      let isDragging = false;
      let moved = false;
      let suppressClickUntil = 0;

      if (dotsWrap) {
        dotsWrap.innerHTML = '';
      }

      cards.forEach((card, index) => {
        if (dotsWrap) {
          const dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'oferta-educativa-3d-dot';
          dot.setAttribute('aria-label', 'Ir a la oferta ' + (index + 1));
          dot.addEventListener('click', () => goTo(index));
          dotsWrap.appendChild(dot);
        }

        card.dataset.index = String(index);
      });

      const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.oferta-educativa-3d-dot')) : [];

      function getBaseViewportHeight() {
        const w = window.innerWidth;
        if (w <= 575) return 520;
        if (w <= 767) return 560;
        if (w <= 991) return 620;
        return 700;
      }

      function syncViewportHeight() {
        const maxCardHeight = cards.reduce((maxHeight, card) => {
          const cardHeight = card.offsetHeight || 0;
          return cardHeight > maxHeight ? cardHeight : maxHeight;
        }, 0);

        const w = window.innerWidth;
        const buffer = w <= 575 ? 110 : (w <= 767 ? 120 : (w <= 991 ? 140 : 170));
        const targetHeight = Math.max(getBaseViewportHeight(), maxCardHeight + buffer);

        viewport.style.minHeight = targetHeight + 'px';
        viewport.style.height = targetHeight + 'px';
      }

      function getConfig() {
        const w = window.innerWidth;

        if (w <= 575) {
          return {
            translateX: 78,
            translateZ: 70,
            rotateY: 0,
            scaleMain: 1,
            scaleSide: 0.92,
            opacityFar: 0,
            blurFar: 4
          };
        }

        if (w <= 767) {
          return {
            translateX: 120,
            translateZ: 120,
            rotateY: 20,
            scaleMain: 1,
            scaleSide: 0.9,
            opacityFar: 0,
            blurFar: 4
          };
        }

        if (w <= 991) {
          return {
            translateX: 180,
            translateZ: 150,
            rotateY: 26,
            scaleMain: 1,
            scaleSide: 0.88,
            opacityFar: 0.1,
            blurFar: 4
          };
        }

        return {
          translateX: 260,
          translateZ: 190,
          rotateY: 32,
          scaleMain: 1,
          scaleSide: 0.86,
          opacityFar: 0.08,
          blurFar: 5
        };
      }

      function normalizeDistance(index, active, total) {
        let diff = index - active;
        const half = Math.floor(total / 2);

        if (diff > half) diff -= total;
        if (diff < -half) diff += total;

        return diff;
      }

      function render() {
        syncViewportHeight();

        const cfg = getConfig();

        cards.forEach((card, index) => {
          const diff = normalizeDistance(index, current, cards.length);
          const abs = Math.abs(diff);

          let transform = '';
          let opacity = '0';
          let zIndex = 1;
          let pointerEvents = 'none';
          let filter = 'blur(0px)';

          if (diff === 0) {
            transform = 'translate3d(-50%, -50%, 0px) rotateY(0deg) scale(' + cfg.scaleMain + ')';
            opacity = '1';
            zIndex = 30;
            pointerEvents = 'auto';
            filter = 'blur(0px)';
          } else if (abs === 1) {
            const direction = diff > 0 ? 1 : -1;
            transform =
              'translate3d(calc(-50% + ' + (direction * cfg.translateX) + 'px), -50%, ' + (-cfg.translateZ) + 'px) ' +
              'rotateY(' + (-direction * cfg.rotateY) + 'deg) ' +
              'scale(' + cfg.scaleSide + ')';
            opacity = '0.72';
            zIndex = 20;
            pointerEvents = 'auto';
            filter = 'blur(0.4px)';
          } else if (abs === 2) {
            const direction = diff > 0 ? 1 : -1;
            transform =
              'translate3d(calc(-50% + ' + (direction * (cfg.translateX * 1.72)) + 'px), -50%, ' + (-cfg.translateZ * 2.1) + 'px) ' +
              'rotateY(' + (-direction * (cfg.rotateY + 8)) + 'deg) ' +
              'scale(0.78)';
            opacity = String(cfg.opacityFar);
            zIndex = 10;
            pointerEvents = 'auto';
            filter = 'blur(' + cfg.blurFar + 'px)';
          } else {
            transform =
              'translate3d(-50%, -50%, ' + (-cfg.translateZ * 3) + 'px) rotateY(0deg) scale(0.72)';
            opacity = '0';
            zIndex = 1;
            pointerEvents = 'none';
            filter = 'blur(6px)';
          }

          card.style.transform = transform;
          card.style.opacity = opacity;
          card.style.zIndex = zIndex;
          card.style.pointerEvents = pointerEvents;
          card.style.filter = filter;
          card.setAttribute('aria-hidden', diff === 0 ? 'false' : 'true');
          card.tabIndex = 0;
          card.dataset.active = diff === 0 ? 'true' : 'false';
        });

        dots.forEach((dot, index) => {
          dot.classList.toggle('is-active', index === current);
        });

        if (counterEl) {
          counterEl.textContent = String(current + 1) + ' / ' + String(cards.length);
        }

        if (statusEl) {
          const activeCard = cards[current];
          const titleEl = activeCard ? activeCard.querySelector('.oferta-titulo-card') : null;
          const label = titleEl ? titleEl.textContent.trim() : ('Oferta ' + String(current + 1));
          statusEl.textContent = 'Oferta ' + String(current + 1) + ' de ' + String(cards.length) + ': ' + label;
        }
      }

      function goTo(index) {
        current = (index + cards.length) % cards.length;
        render();
      }

      function next() {
        goTo(current + 1);
      }

      function prev() {
        goTo(current - 1);
      }

      if (prevBtn) {
        prevBtn.addEventListener('click', prev);
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', next);
      }

      viewport.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          prev();
        }
        if (event.key === 'ArrowRight') {
          event.preventDefault();
          next();
        }
        if (event.key === 'Home') {
          event.preventDefault();
          goTo(0);
        }
        if (event.key === 'End') {
          event.preventDefault();
          goTo(cards.length - 1);
        }
        if (event.key === 'PageUp') {
          event.preventDefault();
          prev();
        }
        if (event.key === 'PageDown') {
          event.preventDefault();
          next();
        }
      });

      viewport.addEventListener('pointerdown', (event) => {
        isDragging = true;
        moved = false;
        startX = event.clientX;
        currentX = event.clientX;
        viewport.classList.add('is-dragging');
      });

      viewport.addEventListener('pointermove', (event) => {
        if (!isDragging) return;
        currentX = event.clientX;
        if (Math.abs(currentX - startX) > 6) moved = true;
      });

      function finishDrag() {
        if (!isDragging) return;
        const delta = currentX - startX;
        isDragging = false;
        viewport.classList.remove('is-dragging');

        if (Math.abs(delta) > 45) {
          if (delta < 0) next();
          else prev();
          suppressClickUntil = Date.now() + 260;
        }
      }

      viewport.addEventListener('pointerup', finishDrag);
      viewport.addEventListener('pointercancel', finishDrag);
      viewport.addEventListener('pointerleave', finishDrag);

      cards.forEach((card, index) => {
        card.addEventListener('click', (event) => {
          if (Date.now() < suppressClickUntil) {
            event.preventDefault();
            event.stopPropagation();
            return;
          }

          const isActive = card.dataset.active === 'true';

          if (!isActive) {
            event.preventDefault();
            event.stopPropagation();
            goTo(index);
          }
        });

        card.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' && event.key !== ' ') return;

          const isActive = card.dataset.active === 'true';

          if (!isActive) {
            event.preventDefault();
            goTo(index);
          }
        });
      });

      window.addEventListener('resize', render);

      if (cards.length <= 1) {
        if (dotsWrap) {
          dotsWrap.style.display = 'none';
        }
        if (controlsWrap) {
          controlsWrap.style.display = 'none';
        }
      }

      render();
    });
  })();
</script>
        <?php

        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_section_posgrados_normalize_card')) {
    /**
     * Compatibilidad hacia atras.
     *
     * @param array<string,mixed> $card
     * @return array<string,string>
     */
    function flacso_section_posgrados_normalize_card(array $card): array
    {
        return flacso_section_oferta_educativa_normalize_card($card);
    }
}

if (!function_exists('flacso_section_posgrados_get_cards')) {
    /**
     * Compatibilidad hacia atras.
     *
     * @return array<int,array<string,string>>
     */
    function flacso_section_posgrados_get_cards(): array
    {
        return flacso_section_oferta_educativa_get_cards();
    }
}

if (!function_exists('flacso_section_posgrados_render')) {
    /**
     * Compatibilidad hacia atras.
     *
     * @param array<string,mixed> $atts
     */
    function flacso_section_posgrados_render($atts = []): string
    {
        return flacso_section_oferta_educativa_render($atts);
    }
}
