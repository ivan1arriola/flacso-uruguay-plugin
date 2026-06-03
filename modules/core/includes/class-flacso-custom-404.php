<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página 404 personalizada para todo el sitio (Kadence-friendly).
 *
 * Mejoras incluidas:
 * - Diseño más institucional y responsive.
 * - Buscador visible con consulta prellenada a partir del slug solicitado.
 * - Sugerencias por slug y título similar.
 * - Aviso especial para URLs antiguas con /web/.
 * - Enlaces rápidos a secciones relevantes.
 * - CSS autoincluido con variables y fallbacks.
 */
if ( ! class_exists( 'Flacso_Custom_404' ) ) {
	class Flacso_Custom_404 {
		public static function init(): void {
			add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 99 );
		}

		public static function maybe_render(): void {
			if ( is_admin() || ! is_404() ) {
				return;
			}

			status_header( 404 );
			nocache_headers();

			if ( isset( $GLOBALS['wp_query'] ) ) {
				$GLOBALS['wp_query']->set_404();
			}

			self::enqueue_assets();

			$requested_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
			$path           = trim( (string) parse_url( $requested_path, PHP_URL_PATH ), '/' );
			$slug           = $path ? basename( $path ) : '';
			$search_query   = self::get_search_query_from_path( $path, $slug );
			$alternative    = self::get_path_alternative( $path );
			$suggestions    = self::get_suggestions( $slug, $path );

			get_header();
			self::render_template( $path, $slug, $search_query, $suggestions, $alternative );
			get_footer();

			exit;
		}

		private static function enqueue_assets(): void {
			if ( ! wp_style_is( 'bootstrap-icons', 'enqueued' ) && ! wp_style_is( 'bootstrap-icons', 'done' ) ) {
				wp_enqueue_style(
					'bootstrap-icons',
					'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
					[],
					'1.11.3'
				);
			}
		}

		private static function get_search_query_from_path( string $path, string $slug ): string {
			$base = $slug !== '' ? $slug : $path;

			$base = preg_replace( '/\.(html|htm|php|asp|aspx)$/i', '', $base );
			$base = str_replace( [ '-', '_', '/', '+' ], ' ', (string) $base );
			$base = preg_replace( '/\s+/', ' ', $base );

			return trim( (string) $base );
		}

		/**
		 * Detecta correcciones habituales para rutas antiguas.
		 *
		 * @return array{label:string,url:string,exists:bool}|null
		 */
		private static function get_path_alternative( string $path ): ?array {
			if ( '' === $path ) {
				return null;
			}

			$normalized_path = trim( preg_replace( '#/+#', '/', $path ), '/' );
			$variants        = [];

			if ( false !== strpos( '/' . $normalized_path . '/', '/web/' ) ) {
				$without_web = preg_replace( '#(^|/)web/#', '$1', $normalized_path );
				$without_web = trim( (string) $without_web, '/' );

				if ( $without_web && $without_web !== $normalized_path ) {
					$variants[] = [
						'label' => __( 'Probar la misma dirección sin /web/', 'flacso-uruguay' ),
						'path'  => $without_web,
					];
				}
			}

			$without_old = preg_replace( '/(?:-|_)old$/i', '', $normalized_path );
			if ( $without_old && $without_old !== $normalized_path ) {
				$variants[] = [
					'label' => __( 'Probar la versión actual de esta página', 'flacso-uruguay' ),
					'path'  => $without_old,
				];
			}

			$without_extension = preg_replace( '/\.(html|htm|php)$/i', '', $normalized_path );
			if ( $without_extension && $without_extension !== $normalized_path ) {
				$variants[] = [
					'label' => __( 'Probar la dirección sin extensión de archivo', 'flacso-uruguay' ),
					'path'  => $without_extension,
				];
			}

			foreach ( $variants as $variant ) {
				$url    = home_url( '/' . ltrim( $variant['path'], '/' ) . '/' );
				$exists = (bool) url_to_postid( $url );

				return [
					'label'  => $variant['label'],
					'url'    => $url,
					'exists' => $exists,
				];
			}

			return null;
		}

		/**
		 * Obtiene sugerencias de páginas/entradas con slug o título similar.
		 *
		 * @return WP_Post[]
		 */
		private static function get_suggestions( string $slug, string $path = '' ): array {
			global $wpdb;

			$source = trim( $slug . ' ' . str_replace( '/', ' ', $path ) );
			$source = preg_replace( '/\.(html|htm|php)$/i', '', $source );

			if ( '' === trim( (string) $source ) ) {
				return [];
			}

			$post_types = apply_filters(
				'flacso_404_suggestion_post_types',
				[ 'page', 'post', 'oferta-academica', 'seminario', 'docente' ]
			);

			$post_types = array_values(
				array_filter(
					array_map( 'sanitize_key', (array) $post_types )
				)
			);

			if ( empty( $post_types ) ) {
				return [];
			}

			$normalized_slug = sanitize_title( $slug );
			$readable_slug   = trim( str_replace( [ '-', '_' ], ' ', $slug ) );
			$tokens          = preg_split( '/[\s\-_\+\/]+/', strtolower( remove_accents( $source ) ) );
			$tokens          = array_values(
				array_unique(
					array_filter(
						array_map( 'trim', (array) $tokens ),
						static function ( $token ) {
							return strlen( $token ) >= 4;
						}
					)
				)
			);

			$conditions = [];
			$params     = [];

			if ( $normalized_slug !== '' ) {
				$conditions[] = 'post_name LIKE %s';
				$params[]     = '%' . $wpdb->esc_like( $normalized_slug ) . '%';

				$conditions[] = 'SOUNDEX(post_name) = SOUNDEX(%s)';
				$params[]     = $normalized_slug;
			}

			if ( $readable_slug !== '' ) {
				$conditions[] = 'post_title LIKE %s';
				$params[]     = '%' . $wpdb->esc_like( $readable_slug ) . '%';
			}

			foreach ( array_slice( $tokens, 0, 8 ) as $token ) {
				$conditions[] = 'post_name LIKE %s';
				$params[]     = '%' . $wpdb->esc_like( sanitize_title( $token ) ) . '%';

				$conditions[] = 'post_title LIKE %s';
				$params[]     = '%' . $wpdb->esc_like( $token ) . '%';
			}

			if ( empty( $conditions ) ) {
				return [];
			}

			$post_type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

			$sql = "
				SELECT ID, post_title, post_name, post_type
				FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				  AND post_password = ''
				  AND post_type IN ({$post_type_placeholders})
				  AND (" . implode( ' OR ', $conditions ) . ')
				LIMIT 80
			';

			$raw_results = $wpdb->get_results(
				$wpdb->prepare(
					$sql,
					array_merge( $post_types, $params )
				)
			);

			if ( empty( $raw_results ) ) {
				return [];
			}

			$scored = [];

			foreach ( $raw_results as $result ) {
				$title_normalized = strtolower( remove_accents( (string) $result->post_title ) );
				$name_normalized  = strtolower( remove_accents( (string) $result->post_name ) );
				$slug_normalized  = strtolower( remove_accents( $normalized_slug ) );

				$score = 0.0;

				if ( $slug_normalized !== '' ) {
					$distance = levenshtein( $slug_normalized, $name_normalized );
					$length   = max( strlen( $slug_normalized ), strlen( $name_normalized ), 1 );
					$score   += 1 - ( $distance / $length );

					if ( false !== strpos( $name_normalized, $slug_normalized ) || false !== strpos( $slug_normalized, $name_normalized ) ) {
						$score += 0.35;
					}
				}

				foreach ( $tokens as $token ) {
					if ( false !== strpos( $name_normalized, $token ) ) {
						$score += 0.18;
					}

					if ( false !== strpos( $title_normalized, $token ) ) {
						$score += 0.22;
					}
				}

				if ( 'page' === $result->post_type ) {
					$score += 0.04;
				} elseif ( 'oferta-academica' === $result->post_type ) {
					$score += 0.08;
				} elseif ( 'seminario' === $result->post_type ) {
					$score += 0.06;
				}

				$scored[] = [
					'post'  => get_post( (int) $result->ID ),
					'score' => $score,
				];
			}

			usort(
				$scored,
				static function ( $a, $b ) {
					if ( $a['score'] === $b['score'] ) {
						return 0;
					}

					return ( $a['score'] > $b['score'] ) ? -1 : 1;
				}
			);

			$suggestions = [];

			foreach ( $scored as $item ) {
				if ( ! $item['post'] instanceof WP_Post ) {
					continue;
				}

				if ( $item['score'] < 0.42 ) {
					continue;
				}

				$suggestions[] = $item['post'];

				if ( count( $suggestions ) >= 6 ) {
					break;
				}
			}

			return $suggestions;
		}

		private static function get_post_type_label( string $post_type ): string {
			$labels = [
				'page'             => __( 'Página', 'flacso-uruguay' ),
				'post'             => __( 'Entrada', 'flacso-uruguay' ),
				'oferta-academica' => __( 'Oferta académica', 'flacso-uruguay' ),
				'seminario'        => __( 'Seminario', 'flacso-uruguay' ),
				'docente'          => __( 'Docente', 'flacso-uruguay' ),
			];

			return $labels[ $post_type ] ?? __( 'Contenido', 'flacso-uruguay' );
		}

		/**
		 * @return array<int,array{label:string,url:string,icon:string,description:string}>
		 */
		private static function get_quick_links(): array {
			return apply_filters(
				'flacso_404_quick_links',
				[
					[
						'label'       => __( 'Oferta académica', 'flacso-uruguay' ),
						'url'         => home_url( '/formacion/' ),
						'icon'        => 'bi-mortarboard',
						'description' => __( 'Posgrados, diplomas, especializaciones y seminarios.', 'flacso-uruguay' ),
					],
					[
						'label'       => __( 'Seminarios', 'flacso-uruguay' ),
						'url'         => home_url( '/formacion/seminarios/' ),
						'icon'        => 'bi-calendar-event',
						'description' => __( 'Actividades abiertas y propuestas de cursada breve.', 'flacso-uruguay' ),
					],
					[
						'label'       => __( 'Investigación', 'flacso-uruguay' ),
						'url'         => home_url( '/investigacion/' ),
						'icon'        => 'bi-journal-text',
						'description' => __( 'Líneas, proyectos y producción académica institucional.', 'flacso-uruguay' ),
					],
					[
						'label'       => __( 'Contacto', 'flacso-uruguay' ),
						'url'         => home_url( '/contacto/' ),
						'icon'        => 'bi-envelope',
						'description' => __( 'Canales de comunicación de FLACSO Uruguay.', 'flacso-uruguay' ),
					],
				]
			);
		}

		private static function render_template( string $path, string $slug, string $search_query, array $suggestions, ?array $alternative ): void {
			$search_url  = add_query_arg( 's', rawurlencode( $search_query ), home_url( '/' ) );
			$quick_links = self::get_quick_links();
			?>
			<main id="primary" class="site-main flacso-404-page" aria-labelledby="flacso-404-title">
				<div class="flacso-404-page__background" aria-hidden="true">
					<span class="flacso-404-page__orb flacso-404-page__orb--one"></span>
					<span class="flacso-404-page__orb flacso-404-page__orb--two"></span>
					<span class="flacso-404-page__line flacso-404-page__line--one"></span>
					<span class="flacso-404-page__line flacso-404-page__line--two"></span>
				</div>

				<div class="flacso-404-shell">
					<section class="flacso-404-hero">
						<div class="flacso-404-hero__content">
							<p class="flacso-404-eyebrow">
								<i class="bi bi-exclamation-circle" aria-hidden="true"></i>
								<?php esc_html_e( 'Error', 'flacso-uruguay' ); ?>
							</p>

							<h1 id="flacso-404-title" class="flacso-404-title">
								<?php esc_html_e( 'No encontramos esta página', 'flacso-uruguay' ); ?>
							</h1>

							<p class="flacso-404-lead">
								<?php esc_html_e( 'Es posible que el enlace haya cambiado, que la dirección tenga un error o que el contenido ya no esté disponible.', 'flacso-uruguay' ); ?>
							</p>

							<?php if ( $path ) : ?>
								<p class="flacso-404-path">
									<span><?php esc_html_e( 'Dirección solicitada:', 'flacso-uruguay' ); ?></span>
									<code>/<?php echo esc_html( $path ); ?></code>
								</p>
							<?php endif; ?>

							<form class="flacso-404-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
								<label class="screen-reader-text" for="flacso-404-search-field">
									<?php esc_html_e( 'Buscar en el sitio', 'flacso-uruguay' ); ?>
								</label>

								<div class="flacso-404-search__control">
									<i class="bi bi-search" aria-hidden="true"></i>
									<input
										id="flacso-404-search-field"
										type="search"
										name="s"
										value="<?php echo esc_attr( $search_query ); ?>"
										placeholder="<?php esc_attr_e( 'Buscar en FLACSO Uruguay', 'flacso-uruguay' ); ?>">
									<button type="submit">
										<?php esc_html_e( 'Buscar', 'flacso-uruguay' ); ?>
									</button>
								</div>
							</form>

							<div class="flacso-404-actions" aria-label="<?php esc_attr_e( 'Acciones principales', 'flacso-uruguay' ); ?>">
								<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flacso-404-button flacso-404-button--primary">
									<i class="bi bi-house-door" aria-hidden="true"></i>
									<span><?php esc_html_e( 'Volver al inicio', 'flacso-uruguay' ); ?></span>
								</a>

								<a href="<?php echo esc_url( $search_url ); ?>" class="flacso-404-button flacso-404-button--secondary">
									<i class="bi bi-search" aria-hidden="true"></i>
									<span><?php esc_html_e( 'Buscar algo similar', 'flacso-uruguay' ); ?></span>
								</a>
							</div>
						</div>

						<div class="flacso-404-hero__visual" aria-hidden="true">
							<div class="flacso-404-compass">
								<i class="bi bi-compass"></i>
							</div>
						</div>
					</section>

					<?php if ( ! empty( $alternative ) ) : ?>
						<section class="flacso-404-alert" aria-label="<?php esc_attr_e( 'Ruta alternativa detectada', 'flacso-uruguay' ); ?>">
							<div class="flacso-404-alert__icon" aria-hidden="true">
								<i class="bi bi-signpost-split"></i>
							</div>
							<div class="flacso-404-alert__content">
								<h2><?php esc_html_e( 'Encontramos una posible corrección', 'flacso-uruguay' ); ?></h2>
								<p>
									<?php
									if ( ! empty( $alternative['exists'] ) ) {
										esc_html_e( 'La dirección parece corresponder a una ruta anterior. Podés probar esta versión corregida:', 'flacso-uruguay' );
									} else {
										esc_html_e( 'La dirección parece tener un formato antiguo. Podés intentar con esta versión:', 'flacso-uruguay' );
									}
									?>
								</p>
							</div>
							<a class="flacso-404-alert__link" href="<?php echo esc_url( $alternative['url'] ); ?>">
								<span><?php echo esc_html( $alternative['label'] ); ?></span>
								<i class="bi bi-arrow-right" aria-hidden="true"></i>
							</a>
						</section>
					<?php endif; ?>

					<div class="flacso-404-grid">
						<section class="flacso-404-card flacso-404-card--suggestions">
							<div class="flacso-404-card__header">
								<div>
									<p class="flacso-404-card__kicker"><?php esc_html_e( 'Sugerencias', 'flacso-uruguay' ); ?></p>
									<h2><?php esc_html_e( 'Quizás buscabas', 'flacso-uruguay' ); ?></h2>
								</div>
								<i class="bi bi-link-45deg" aria-hidden="true"></i>
							</div>

							<?php if ( ! empty( $suggestions ) ) : ?>
								<ul class="flacso-404-suggestions">
									<?php foreach ( $suggestions as $post ) : ?>
										<li>
											<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
												<span class="flacso-404-suggestions__icon" aria-hidden="true">
													<i class="bi bi-arrow-up-right"></i>
												</span>
												<span class="flacso-404-suggestions__text">
													<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
													<small>
														<?php echo esc_html( self::get_post_type_label( $post->post_type ) ); ?>
														<?php if ( ! empty( $post->post_name ) ) : ?>
															<span aria-hidden="true"> · </span>/<?php echo esc_html( $post->post_name ); ?>
														<?php endif; ?>
													</small>
												</span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<div class="flacso-404-empty">
									<i class="bi bi-search-heart" aria-hidden="true"></i>
									<p><?php esc_html_e( 'No encontramos páginas con un nombre suficientemente parecido. Probá con el buscador o usá los accesos rápidos.', 'flacso-uruguay' ); ?></p>
								</div>
							<?php endif; ?>
						</section>

						<section class="flacso-404-card flacso-404-card--links">
							<div class="flacso-404-card__header">
								<div>
									<p class="flacso-404-card__kicker"><?php esc_html_e( 'Atajos', 'flacso-uruguay' ); ?></p>
									<h2><?php esc_html_e( 'Seguir navegando', 'flacso-uruguay' ); ?></h2>
								</div>
								<i class="bi bi-grid-1x2" aria-hidden="true"></i>
							</div>

							<ul class="flacso-404-quicklinks">
								<?php foreach ( $quick_links as $link ) : ?>
									<li>
										<a href="<?php echo esc_url( $link['url'] ); ?>">
											<span class="flacso-404-quicklinks__icon" aria-hidden="true">
												<i class="bi <?php echo esc_attr( $link['icon'] ); ?>"></i>
											</span>
											<span>
												<strong><?php echo esc_html( $link['label'] ); ?></strong>
												<small><?php echo esc_html( $link['description'] ); ?></small>
											</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
					</div>

					<section class="flacso-404-support">
						<div>
							<h2>
								<i class="bi bi-info-circle" aria-hidden="true"></i>
								<?php esc_html_e( '¿El enlace debería funcionar?', 'flacso-uruguay' ); ?>
							</h2>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: email link */
										__( 'Si llegaste desde una página de FLACSO Uruguay, podés avisarnos escribiendo a %s e incluyendo la dirección que no funcionó.', 'flacso-uruguay' ),
										'<a href="mailto:web@flacso.edu.uy">web@flacso.edu.uy</a>'
									),
									[
										'a' => [
											'href' => [],
										],
									]
								);
								?>
							</p>
						</div>
					</section>
				</div>
			</main>

			<style>
				.flacso-404-page {
					--flacso-404-blue-dark: var(--global-palette3, #051938);
					--flacso-404-blue: var(--global-palette1, #163970);
					--flacso-404-blue-soft: #244f94;
					--flacso-404-yellow: var(--global-palette2, #fcd116);
					--flacso-404-bg: var(--global-palette8, #f8fafc);
					--flacso-404-surface: #ffffff;
					--flacso-404-text: var(--global-palette4, #334155);
					--flacso-404-muted: var(--global-palette5, #64748b);
					--flacso-404-border: rgba(15, 23, 42, 0.11);
					--flacso-404-shadow-sm: 0 10px 26px rgba(15, 23, 42, 0.08);
					--flacso-404-shadow-md: 0 22px 70px rgba(15, 23, 42, 0.16);
					position: relative;
					isolation: isolate;
					min-height: min(900px, 78vh);
					padding: clamp(42px, 7vw, 92px) 16px;
					overflow: hidden;
					background:
						radial-gradient(circle at 10% 6%, rgba(252, 209, 22, 0.22), transparent 34%),
						radial-gradient(circle at 92% 0%, rgba(22, 57, 112, 0.18), transparent 36%),
						linear-gradient(180deg, #ffffff 0%, var(--flacso-404-bg) 100%);
					color: var(--flacso-404-text);
				}

				.flacso-404-page,
				.flacso-404-page * {
					box-sizing: border-box;
				}

				.flacso-404-page__background {
					position: absolute;
					inset: 0;
					z-index: -1;
					pointer-events: none;
					overflow: hidden;
				}

				.flacso-404-page__orb {
					position: absolute;
					display: block;
					border-radius: 999px;
					filter: blur(0.2px);
				}

				.flacso-404-page__orb--one {
					width: 420px;
					height: 420px;
					top: -150px;
					right: -110px;
					border: 1px solid rgba(22, 57, 112, 0.12);
					background: radial-gradient(circle, rgba(22, 57, 112, 0.10), transparent 62%);
				}

				.flacso-404-page__orb--two {
					width: 230px;
					height: 230px;
					bottom: -80px;
					left: -50px;
					background: radial-gradient(circle, rgba(252, 209, 22, 0.35), transparent 68%);
				}

				.flacso-404-page__line {
					position: absolute;
					display: block;
					width: 1px;
					height: 520px;
					background: linear-gradient(180deg, transparent, rgba(22, 57, 112, 0.12), transparent);
					transform: rotate(35deg);
				}

				.flacso-404-page__line--one {
					top: -100px;
					left: 16%;
				}

				.flacso-404-page__line--two {
					right: 18%;
					bottom: -160px;
				}

				.flacso-404-shell {
					width: min(100%, 1120px);
					margin-inline: auto;
				}

				.flacso-404-hero {
					display: grid;
					grid-template-columns: minmax(0, 1.35fr) minmax(260px, 0.65fr);
					gap: clamp(24px, 5vw, 64px);
					align-items: center;
					padding: clamp(26px, 5vw, 56px);
					border: 1px solid var(--flacso-404-border);
					border-radius: 34px;
					background:
						linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.82)),
						radial-gradient(circle at 100% 0%, rgba(252, 209, 22, 0.26), transparent 35%);
					box-shadow: var(--flacso-404-shadow-md);
					backdrop-filter: blur(12px);
					overflow: hidden;
				}

				.flacso-404-eyebrow {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					margin: 0 0 14px;
					color: var(--flacso-404-blue);
					font-size: 0.82rem;
					font-weight: 900;
					letter-spacing: 0.12em;
					line-height: 1.25;
					text-transform: uppercase;
				}

				.flacso-404-eyebrow i {
					color: var(--flacso-404-yellow);
					font-size: 1.05rem;
				}

				.flacso-404-title {
					margin: 0;
					color: var(--flacso-404-blue-dark);
					font-size: clamp(2.25rem, 5.5vw, 5.1rem);
					font-weight: 950;
					letter-spacing: -0.065em;
					line-height: 0.96;
					text-wrap: balance;
				}

				.flacso-404-lead {
					max-width: 720px;
					margin: clamp(16px, 2.5vw, 24px) 0 0;
					color: var(--flacso-404-text);
					font-size: clamp(1rem, 1.55vw, 1.2rem);
					line-height: 1.65;
					text-wrap: balance;
				}

				.flacso-404-path {
					display: flex;
					flex-wrap: wrap;
					align-items: center;
					gap: 8px;
					width: fit-content;
					max-width: 100%;
					margin: 20px 0 0;
					padding: 10px 12px;
					border: 1px solid rgba(15, 23, 42, 0.08);
					border-radius: 14px;
					background: rgba(248, 250, 252, 0.9);
					color: var(--flacso-404-muted);
					font-size: 0.92rem;
					line-height: 1.35;
				}

				.flacso-404-path code {
					max-width: 100%;
					padding: 3px 7px;
					border-radius: 8px;
					background: #eef2f7;
					color: var(--flacso-404-blue-dark);
					font-size: 0.88rem;
					white-space: normal;
					overflow-wrap: anywhere;
				}

				.flacso-404-search {
					margin-top: clamp(22px, 3vw, 30px);
				}

				.flacso-404-search__control {
					display: grid;
					grid-template-columns: auto minmax(0, 1fr) auto;
					align-items: center;
					gap: 10px;
					width: min(100%, 720px);
					padding: 8px;
					border: 1px solid var(--flacso-404-border);
					border-radius: 999px;
					background: #ffffff;
					box-shadow: var(--flacso-404-shadow-sm);
				}

				.flacso-404-search__control > i {
					padding-left: 12px;
					color: var(--flacso-404-blue);
					font-size: 1.08rem;
				}

				.flacso-404-search input {
					width: 100%;
					min-height: 44px;
					border: 0;
					outline: 0;
					background: transparent;
					color: var(--flacso-404-blue-dark);
					font-size: 1rem;
					line-height: 1.3;
				}

				.flacso-404-search input::placeholder {
					color: #94a3b8;
				}

				.flacso-404-search button,
				.flacso-404-button,
				.flacso-404-alert__link {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					gap: 8px;
					min-height: 44px;
					border: 0;
					border-radius: 999px;
					font-size: 0.9rem;
					font-weight: 900;
					line-height: 1.2;
					letter-spacing: 0.035em;
					text-align: center;
					text-decoration: none;
					text-transform: uppercase;
					cursor: pointer;
					transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
				}

				.flacso-404-search button {
					padding: 12px 20px;
					background: var(--flacso-404-blue);
					color: #ffffff;
					box-shadow: 0 10px 20px rgba(22, 57, 112, 0.16);
				}

				.flacso-404-search button:hover,
				.flacso-404-button--primary:hover,
				.flacso-404-alert__link:hover {
					transform: translateY(-1px);
					background: var(--flacso-404-blue-dark);
					color: #ffffff;
					box-shadow: 0 14px 28px rgba(15, 23, 42, 0.18);
				}

				.flacso-404-actions {
					display: flex;
					flex-wrap: wrap;
					gap: 12px;
					margin-top: 20px;
				}

				.flacso-404-button {
					padding: 12px 18px;
				}

				.flacso-404-button--primary {
					background: var(--flacso-404-yellow);
					color: var(--flacso-404-blue-dark);
					box-shadow: 0 10px 22px rgba(252, 209, 22, 0.28);
				}

				.flacso-404-button--secondary {
					border: 1px solid var(--flacso-404-border);
					background: #ffffff;
					color: var(--flacso-404-blue);
				}

				.flacso-404-button--secondary:hover {
					border-color: var(--flacso-404-blue);
					background: var(--flacso-404-blue);
					color: #ffffff;
					transform: translateY(-1px);
				}

				.flacso-404-hero__visual {
					position: relative;
					display: grid;
					place-items: center;
					min-height: 260px;
					border-radius: 30px;
					overflow: hidden;
					background:
						radial-gradient(circle at 70% 22%, rgba(252, 209, 22, 0.34), transparent 34%),
						linear-gradient(135deg, var(--flacso-404-blue), var(--flacso-404-blue-dark));
					color: #ffffff;
					box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12), 0 20px 50px rgba(5, 25, 56, 0.18);
				}

				.flacso-404-number {
					position: relative;
					z-index: 1;
					color: rgba(255, 255, 255, 0.92);
					font-size: clamp(4.6rem, 12vw, 8rem);
					font-weight: 950;
					letter-spacing: -0.08em;
					line-height: 1;
					text-shadow: 0 12px 32px rgba(0, 0, 0, 0.22);
				}

				.flacso-404-compass {
					position: absolute;
					right: 24px;
					bottom: 22px;
					width: 58px;
					height: 58px;
					display: grid;
					place-items: center;
					border-radius: 20px;
					background: rgba(255, 255, 255, 0.12);
					color: var(--flacso-404-yellow);
					font-size: 1.8rem;
					backdrop-filter: blur(8px);
				}

				.flacso-404-alert {
					display: grid;
					grid-template-columns: auto minmax(0, 1fr) auto;
					gap: 16px;
					align-items: center;
					margin-top: 18px;
					padding: 18px;
					border: 1px solid rgba(252, 209, 22, 0.55);
					border-radius: 24px;
					background: linear-gradient(135deg, rgba(252, 209, 22, 0.18), rgba(255, 255, 255, 0.96));
					box-shadow: var(--flacso-404-shadow-sm);
				}

				.flacso-404-alert__icon {
					width: 48px;
					height: 48px;
					display: grid;
					place-items: center;
					border-radius: 16px;
					background: var(--flacso-404-yellow);
					color: var(--flacso-404-blue-dark);
					font-size: 1.35rem;
				}

				.flacso-404-alert h2 {
					margin: 0 0 4px;
					color: var(--flacso-404-blue-dark);
					font-size: 1.05rem;
					font-weight: 900;
					line-height: 1.25;
				}

				.flacso-404-alert p {
					margin: 0;
					color: var(--flacso-404-text);
					font-size: 0.95rem;
					line-height: 1.45;
				}

				.flacso-404-alert__link {
					padding: 12px 16px;
					background: var(--flacso-404-blue);
					color: #ffffff;
				}

				.flacso-404-grid {
					display: grid;
					grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.75fr);
					gap: 18px;
					margin-top: 18px;
				}

				.flacso-404-card,
				.flacso-404-support {
					border: 1px solid var(--flacso-404-border);
					border-radius: 26px;
					background: rgba(255, 255, 255, 0.96);
					box-shadow: var(--flacso-404-shadow-sm);
				}

				.flacso-404-card {
					padding: clamp(20px, 3vw, 28px);
				}

				.flacso-404-card__header {
					display: flex;
					align-items: flex-start;
					justify-content: space-between;
					gap: 16px;
					margin-bottom: 16px;
				}

				.flacso-404-card__header > i {
					width: 46px;
					height: 46px;
					display: grid;
					place-items: center;
					flex: 0 0 auto;
					border-radius: 16px;
					background: rgba(22, 57, 112, 0.08);
					color: var(--flacso-404-blue);
					font-size: 1.35rem;
				}

				.flacso-404-card__kicker {
					margin: 0 0 5px;
					color: var(--flacso-404-muted);
					font-size: 0.76rem;
					font-weight: 900;
					letter-spacing: 0.11em;
					line-height: 1.2;
					text-transform: uppercase;
				}

				.flacso-404-card h2,
				.flacso-404-support h2 {
					margin: 0;
					color: var(--flacso-404-blue-dark);
					font-size: clamp(1.2rem, 2vw, 1.5rem);
					font-weight: 900;
					letter-spacing: -0.025em;
					line-height: 1.15;
				}

				.flacso-404-suggestions,
				.flacso-404-quicklinks {
					list-style: none;
					margin: 0;
					padding: 0;
				}

				.flacso-404-suggestions li + li,
				.flacso-404-quicklinks li + li {
					margin-top: 10px;
				}

				.flacso-404-suggestions a,
				.flacso-404-quicklinks a {
					display: flex;
					align-items: flex-start;
					gap: 12px;
					padding: 12px;
					border: 1px solid rgba(15, 23, 42, 0.07);
					border-radius: 18px;
					background: #ffffff;
					text-decoration: none;
					transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
				}

				.flacso-404-suggestions a:hover,
				.flacso-404-quicklinks a:hover {
					transform: translateY(-1px);
					border-color: rgba(22, 57, 112, 0.24);
					background: #f8fafc;
					box-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
				}

				.flacso-404-suggestions__icon,
				.flacso-404-quicklinks__icon {
					width: 38px;
					height: 38px;
					display: grid;
					place-items: center;
					flex: 0 0 auto;
					border-radius: 14px;
					background: rgba(252, 209, 22, 0.22);
					color: var(--flacso-404-blue);
					font-size: 1rem;
				}

				.flacso-404-suggestions__text,
				.flacso-404-quicklinks a > span:last-child {
					min-width: 0;
				}

				.flacso-404-suggestions strong,
				.flacso-404-quicklinks strong {
					display: block;
					color: var(--flacso-404-blue-dark);
					font-size: 1rem;
					font-weight: 850;
					line-height: 1.25;
				}

				.flacso-404-suggestions small,
				.flacso-404-quicklinks small {
					display: block;
					margin-top: 4px;
					color: var(--flacso-404-muted);
					font-size: 0.86rem;
					line-height: 1.4;
					overflow-wrap: anywhere;
				}

				.flacso-404-empty {
					display: flex;
					align-items: flex-start;
					gap: 12px;
					padding: 16px;
					border-radius: 18px;
					background: #f8fafc;
					color: var(--flacso-404-muted);
				}

				.flacso-404-empty i {
					color: var(--flacso-404-blue);
					font-size: 1.25rem;
				}

				.flacso-404-empty p {
					margin: 0;
					font-size: 0.95rem;
					line-height: 1.55;
				}

				.flacso-404-support {
					margin-top: 18px;
					padding: 20px clamp(20px, 3vw, 28px);
					background:
						linear-gradient(90deg, rgba(22, 57, 112, 0.07), transparent 55%),
						#ffffff;
				}

				.flacso-404-support h2 {
					display: flex;
					align-items: center;
					gap: 10px;
					margin-bottom: 8px;
					font-size: 1.12rem;
				}

				.flacso-404-support h2 i {
					color: var(--flacso-404-blue);
				}

				.flacso-404-support p {
					margin: 0;
					color: var(--flacso-404-text);
					font-size: 0.96rem;
					line-height: 1.62;
				}

				.flacso-404-support a {
					color: var(--flacso-404-blue);
					font-weight: 850;
					text-decoration: underline;
					text-decoration-thickness: 2px;
					text-underline-offset: 4px;
				}

				.flacso-404-search button:focus-visible,
				.flacso-404-button:focus-visible,
				.flacso-404-alert__link:focus-visible,
				.flacso-404-suggestions a:focus-visible,
				.flacso-404-quicklinks a:focus-visible,
				.flacso-404-support a:focus-visible,
				.flacso-404-search input:focus-visible {
					outline: 3px solid rgba(252, 209, 22, 0.8);
					outline-offset: 3px;
				}

				@media (prefers-reduced-motion: no-preference) {
					.flacso-404-hero,
					.flacso-404-alert,
					.flacso-404-card,
					.flacso-404-support {
						animation: flacso404-fade-up 0.55s ease both;
					}

					.flacso-404-alert {
						animation-delay: 0.04s;
					}

					.flacso-404-card {
						animation-delay: 0.08s;
					}

					.flacso-404-support {
						animation-delay: 0.12s;
					}

					.flacso-404-compass {
						animation: flacso404-float 5s ease-in-out infinite alternate;
					}
				}

				@keyframes flacso404-fade-up {
					from {
						opacity: 0;
						transform: translateY(12px);
					}
					to {
						opacity: 1;
						transform: translateY(0);
					}
				}

				@keyframes flacso404-float {
					from {
						transform: translateY(0) rotate(-4deg);
					}
					to {
						transform: translateY(-8px) rotate(4deg);
					}
				}

				@media (max-width: 920px) {
					.flacso-404-hero,
					.flacso-404-grid {
						grid-template-columns: minmax(0, 1fr);
					}

					.flacso-404-hero__visual {
						min-height: 210px;
					}

					.flacso-404-alert {
						grid-template-columns: auto minmax(0, 1fr);
					}

					.flacso-404-alert__link {
						grid-column: 1 / -1;
						width: 100%;
					}
				}

				@media (max-width: 640px) {
					.flacso-404-page {
						padding: 28px 12px;
					}

					.flacso-404-hero {
						padding: 22px;
						border-radius: 24px;
					}

					.flacso-404-title {
						letter-spacing: -0.045em;
					}

					.flacso-404-search__control {
						grid-template-columns: auto minmax(0, 1fr);
						border-radius: 22px;
					}

					.flacso-404-search button {
						grid-column: 1 / -1;
						width: 100%;
					}

					.flacso-404-actions {
						display: grid;
						grid-template-columns: minmax(0, 1fr);
					}

					.flacso-404-button {
						width: 100%;
					}

					.flacso-404-alert {
						grid-template-columns: minmax(0, 1fr);
						text-align: left;
					}

					.flacso-404-card,
					.flacso-404-support,
					.flacso-404-alert {
						border-radius: 20px;
					}
				}
			</style>
			<?php
		}
	}
}

Flacso_Custom_404::init();