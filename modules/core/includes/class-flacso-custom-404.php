<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página 404 personalizada para todo el sitio (Kadence-friendly).
 * - Íconos de Bootstrap Icons.
 * - Sugerencias por slug similar (LIKE + SOUNDEX + levenshtein).
 * - Diseño autoincluido (CSS inline) para no depender del theme.
 */
class Flacso_Custom_404 {
	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ] );
	}

	public static function maybe_render(): void {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		status_header( 404 );
		nocache_headers();
		$GLOBALS['wp_query']->set_404();

		self::enqueue_assets();

		$requested_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path           = trim( (string) parse_url( $requested_path, PHP_URL_PATH ), '/' );
		$slug           = $path ? basename( $path ) : '';

		$suggestions = self::get_suggestions( $slug );

		get_header();
		self::render_template( $path, $slug, $suggestions );
		get_footer();
		exit;
	}

	private static function enqueue_assets(): void {
		if ( ! wp_style_is( 'bootstrap-icons', 'enqueued' ) ) {
			wp_enqueue_style(
				'bootstrap-icons',
				'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
				[],
				'1.11.3'
			);
		}
	}

	/**
	 * Obtiene sugerencias de páginas/entradas con slug similar.
	 *
	 * @return array<WP_Post>
	 */
	private static function get_suggestions( string $slug ): array {
		global $wpdb;

		if ( '' === $slug ) {
			return [];
		}

		$like = '%' . $wpdb->esc_like( $slug ) . '%';

		$sql = "
			SELECT ID, post_title, post_name, post_type
			FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			  AND post_type IN ('page','post')
			  AND (
					post_name LIKE %s
				 OR SOUNDEX(post_name) = SOUNDEX(%s)
				 OR LOCATE(%s, post_name) > 0
			  )
			LIMIT 20
		";

		$raw_results = $wpdb->get_results(
			$wpdb->prepare( $sql, $like, $slug, $slug )
		);

		if ( empty( $raw_results ) ) {
			return [];
		}

		$scored = [];
		foreach ( $raw_results as $r ) {
			$distance   = levenshtein( strtolower( $slug ), strtolower( $r->post_name ) );
			$length     = max( strlen( $slug ), strlen( $r->post_name ) );
			$similarity = 1 - ( $distance / max( 1, $length ) ); // 0 a 1

			$scored[] = [
				'post'       => $r,
				'similarity' => $similarity,
			];
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a['similarity'] === $b['similarity'] ) {
					return 0;
				}
				return ( $a['similarity'] > $b['similarity'] ) ? -1 : 1;
			}
		);

		$suggestions = [];
		foreach ( $scored as $s ) {
			if ( $s['similarity'] >= 0.5 ) {
				$suggestions[] = $s['post'];
			}
			if ( count( $suggestions ) >= 5 ) {
				break;
			}
		}

		return $suggestions;
	}

	private static function render_template( string $path, string $slug, array $suggestions ): void {
		?>
		<main id="primary" class="site-main flacso-404-wrapper">
			<div class="flacso-404-bg-orbit"></div>
			<div class="flacso-404-bg-blur"></div>

			<div class="flacso-404-inner">
				<header class="flacso-404-header">
					<div class="flacso-404-icon-badge" aria-hidden="true">
						<span class="flacso-404-icon-circle"></span>
						<i class="bi bi-compass"></i>
					</div>

					<p class="flacso-404-eyebrow">
						<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
						<?php esc_html_e( 'Error 404 – Página no encontrada', 'flacso-uruguay' ); ?>
					</p>

					<h1 class="flacso-404-title">
						<?php esc_html_e( 'Ups, parece que este camino no existe', 'flacso-uruguay' ); ?>
					</h1>

					<?php if ( $path ) : ?>
						<p class="flacso-404-path">
							<?php esc_html_e( 'Intentaste acceder a:', 'flacso-uruguay' ); ?>
							<code>/<?php echo esc_html( $path ); ?></code>
						</p>
					<?php endif; ?>

					<p class="flacso-404-text">
						<?php esc_html_e( 'El enlace puede estar desactualizado o la página fue movida. Te dejamos algunos atajos para seguir navegando.', 'flacso-uruguay' ); ?>
					</p>
				</header>

				<div class="flacso-404-actions">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flacso-404-btn flacso-404-btn-primary">
						<i class="bi bi-house-door" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Volver al inicio', 'flacso-uruguay' ); ?></span>
					</a>

					<a href="<?php echo esc_url( home_url( '/?s=' . urlencode( str_replace( '-', ' ', $slug ) ) ) ); ?>" class="flacso-404-btn flacso-404-btn-secondary">
						<i class="bi bi-search" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Buscar algo similar', 'flacso-uruguay' ); ?></span>
					</a>
				</div>

				<div class="flacso-404-layout">
					<section class="flacso-404-panel flacso-404-panel-suggestions">
						<h2>
							<i class="bi bi-link-45deg" aria-hidden="true"></i>
							<?php esc_html_e( '¿Quizás buscabas…?', 'flacso-uruguay' ); ?>
						</h2>

						<?php if ( ! empty( $suggestions ) ) : ?>
							<ul class="flacso-404-suggestions-list">
								<?php foreach ( $suggestions as $post ) : ?>
									<li class="flacso-404-suggestion-item">
										<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
											<span class="flacso-404-suggestion-icon" aria-hidden="true">
												<i class="bi bi-arrow-right-circle"></i>
											</span>
											<span class="flacso-404-suggestion-content">
												<span class="flacso-404-suggestion-title">
													<?php echo esc_html( $post->post_title ); ?>
												</span>
												<span class="flacso-404-suggestion-meta">
													/<?php echo esc_html( $post->post_name ); ?>
													<?php echo ( 'page' === $post->post_type ) ? ' · ' . esc_html__( 'Página', 'flacso-uruguay' ) : ' · ' . esc_html__( 'Entrada', 'flacso-uruguay' ); ?>
												</span>
											</span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="flacso-404-empty">
								<?php esc_html_e( 'No encontramos otras páginas con un nombre parecido, pero podés usar el buscador o el menú principal.', 'flacso-uruguay' ); ?>
							</p>
						<?php endif; ?>
					</section>

					<aside class="flacso-404-panel flacso-404-panel-help">
						<h2>
							<i class="bi bi-info-circle" aria-hidden="true"></i>
							<?php esc_html_e( '¿Creés que es un error?', 'flacso-uruguay' ); ?>
						</h2>
						<p><?php esc_html_e( 'Si llegaste aquí desde un enlace dentro de nuestro sitio, puede que haya quedado desactualizado.', 'flacso-uruguay' ); ?></p>
						<p class="flacso-404-tip">
							<i class="bi bi-emoji-smile" aria-hidden="true"></i>
							<?php printf(
								/* translators: %s: email */
								esc_html__( 'Podés avisarnos copiando la URL y enviándola a %s.', 'flacso-uruguay' ),
								'<a href="mailto:web@flacso.edu.uy">web@flacso.edu.uy</a>'
							); ?>
						</p>
					</aside>
				</div>
			</div>
		</main>

		<style>
			.flacso-404-wrapper {
				position: relative;
				min-height: 70vh;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 64px 20px;
				overflow: hidden;
				background: radial-gradient(circle at top left, rgba(255, 210, 34, 0.14), transparent 55%),
					radial-gradient(circle at bottom right, rgba(29, 58, 114, 0.18), transparent 55%),
					var(--global-palette8);
			}
			.flacso-404-bg-orbit,
			.flacso-404-bg-blur {
				position: absolute;
				border-radius: 999px;
				pointer-events: none;
				opacity: 0.7;
			}
			.flacso-404-bg-orbit {
				width: 520px;
				height: 520px;
				border: 1px solid rgba(255, 255, 255, 0.2);
				top: -120px;
				right: -120px;
				animation: flacso404-orbit 16s linear infinite;
			}
			.flacso-404-bg-blur {
				width: 260px;
				height: 260px;
				background: radial-gradient(circle, rgba(254, 210, 34, 0.7), transparent 65%);
				bottom: -80px;
				left: -40px;
				filter: blur(4px);
				animation: flacso404-floating 14s ease-in-out infinite alternate;
			}
			.flacso-404-inner {
				position: relative;
				max-width: 980px;
				width: 100%;
				background: rgba(248, 249, 252, 0.94);
				border-radius: 24px;
				padding: 32px 28px 28px;
				box-shadow: 0 18px 60px rgba(15, 26, 45, 0.22), 0 0 0 1px rgba(201, 210, 222, 0.8);
				backdrop-filter: blur(10px);
				z-index: 1;
				overflow: hidden;
			}
			@media (prefers-reduced-motion: no-preference) {
				.flacso-404-inner {
					animation: flacso404-fadeInUp 0.6s ease-out;
				}
			}
			.flacso-404-header {
				text-align: left;
				margin-bottom: 24px;
				position: relative;
				padding-left: 72px;
			}
			.flacso-404-icon-badge {
				position: absolute;
				left: 0;
				top: 50%;
				transform: translateY(-50%);
				width: 56px;
				height: 56px;
				border-radius: 16px;
				background: radial-gradient(circle at 30% 20%, var(--global-palette2), var(--global-palette1));
				display: flex;
				align-items: center;
				justify-content: center;
				color: var(--global-palette3);
				box-shadow: 0 10px 28px rgba(15, 26, 45, 0.35);
				overflow: hidden;
			}
			.flacso-404-icon-circle {
				position: absolute;
				width: 130%;
				height: 130%;
				border-radius: 999px;
				border: 1px solid rgba(248, 249, 252, 0.5);
				opacity: 0.7;
				animation: flacso404-pulse 3s ease-out infinite;
			}
			.flacso-404-icon-badge i {
				position: relative;
				font-size: 1.6rem;
				color: #fff;
			}
			.flacso-404-eyebrow {
				text-transform: uppercase;
				letter-spacing: 0.16em;
				font-size: 0.78rem;
				margin-bottom: 0.5rem;
				color: var(--global-palette5);
				display: inline-flex;
				align-items: center;
				gap: 0.4rem;
			}
			.flacso-404-eyebrow i { font-size: 1rem; color: var(--global-palette2); }
			.flacso-404-title {
				font-size: clamp(2.1rem, 3.2vw, 2.6rem);
				margin: 0 0 0.6rem;
				color: var(--global-palette1);
				line-height: 1.2;
			}
			.flacso-404-path { font-size: 0.9rem; color: var(--global-palette5); margin: 0 0 0.75rem; }
			.flacso-404-path code {
				background: var(--global-palette7);
				padding: 0.12rem 0.4rem;
				border-radius: 4px;
				font-size: 0.86rem;
			}
			.flacso-404-text {
				font-size: 1rem;
				line-height: 1.7;
				color: var(--global-palette4);
				max-width: 640px;
				margin: 0;
			}
			.flacso-404-actions {
				margin-top: 20px;
				margin-bottom: 24px;
				display: flex;
				flex-wrap: wrap;
				gap: 0.75rem;
			}
			.flacso-404-btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 0.45rem;
				padding: 0.8rem 1.7rem;
				border-radius: 999px;
				text-decoration: none;
				font-weight: 600;
				font-size: 0.95rem;
				transition: transform 0.15s ease-out, box-shadow 0.15s ease-out, background-color 0.15s ease-out, color 0.15s ease-out;
				cursor: pointer;
			}
			.flacso-404-btn-primary {
				background: linear-gradient(135deg, var(--global-palette2), #ffe676);
				color: var(--global-palette3);
				box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
			}
			.flacso-404-btn-primary i { font-size: 1.1rem; }
			.flacso-404-btn-secondary {
				background: var(--global-palette8);
				color: var(--global-palette3);
				border: 1px solid var(--global-palette6);
			}
			.flacso-404-btn-secondary i { font-size: 1.1rem; color: var(--global-palette1); }
			.flacso-404-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 30px rgba(15, 26, 45, 0.2); }
			.flacso-404-btn:active { transform: translateY(0); box-shadow: 0 6px 16px rgba(15, 26, 45, 0.15); }
			.flacso-404-layout {
				display: grid;
				grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
				gap: 20px;
				margin-top: 10px;
			}
			.flacso-404-panel {
				background: #fff;
				border-radius: 16px;
				padding: 18px 18px 16px;
				border: 1px solid rgba(201, 210, 222, 0.8);
				box-shadow: 0 8px 24px rgba(15, 26, 45, 0.08);
				position: relative;
				overflow: hidden;
			}
			.flacso-404-panel:before {
				content: "";
				position: absolute;
				inset: 0;
				background: linear-gradient(135deg, rgba(254, 210, 34, 0.16), rgba(29, 58, 114, 0.05));
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.25s ease-out;
			}
			.flacso-404-panel:hover:before { opacity: 1; }
			.flacso-404-panel h2 {
				font-size: 1rem;
				margin: 0 0 0.6rem;
				color: var(--global-palette3);
				display: flex;
				align-items: center;
				gap: 0.45rem;
			}
			.flacso-404-panel h2 i { color: var(--global-palette1); font-size: 1.25rem; }
			.flacso-404-panel p { margin: 0 0 0.5rem; font-size: 0.92rem; color: var(--global-palette4); }
			.flacso-404-tip {
				margin-top: 0.5rem !important;
				padding: 0.5rem 0.6rem;
				border-radius: 10px;
				background: var(--global-palette7);
				font-size: 0.9rem;
				display: flex;
				align-items: flex-start;
				gap: 0.45rem;
			}
			.flacso-404-tip i { margin-top: 0.1rem; color: var(--global-palette2); }
			.flacso-404-suggestions-list { list-style: none; padding: 0; margin: 0; }
			.flacso-404-suggestion-item + .flacso-404-suggestion-item { margin-top: 0.4rem; }
			.flacso-404-suggestion-item a {
				display: flex;
				align-items: flex-start;
				gap: 0.5rem;
				text-decoration: none;
				padding: 0.45rem 0.55rem;
				border-radius: 10px;
				transition: background-color 0.18s ease-out, transform 0.18s ease-out;
			}
			.flacso-404-suggestion-item a:hover { background: var(--global-palette7); transform: translateY(-1px); }
			.flacso-404-suggestion-icon { margin-top: 0.1rem; color: var(--global-palette1); }
			.flacso-404-suggestion-icon i { font-size: 1.1rem; }
			.flacso-404-suggestion-title { font-weight: 600; color: var(--global-palette3); display: block; }
			.flacso-404-suggestion-meta { font-size: 0.82rem; color: var(--global-palette5); }
			.flacso-404-empty { font-size: 0.9rem; color: var(--global-palette5); }
			@keyframes flacso404-fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
			@keyframes flacso404-orbit { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
			@keyframes flacso404-floating { from { transform: translate3d(0, 0, 0); } to { transform: translate3d(20px, -10px, 0); } }
			@keyframes flacso404-pulse { 0% { transform: scale(0.8); opacity: 0.9; } 70% { transform: scale(1.05); opacity: 0; } 100% { transform: scale(1.05); opacity: 0; } }
			@media (max-width: 780px) {
				.flacso-404-inner { padding: 22px 18px 18px; border-radius: 20px; }
				.flacso-404-header { padding-left: 0; padding-top: 64px; }
				.flacso-404-icon-badge { left: 50%; top: 0; transform: translate(-50%, -30%); }
				.flacso-404-header, .flacso-404-header p, .flacso-404-header h1 { text-align: center; }
				.flacso-404-layout { grid-template-columns: minmax(0, 1fr); }
				.flacso-404-actions { justify-content: center; }
			}
			@media (max-width: 520px) {
				.flacso-404-wrapper { padding: 48px 12px; }
				.flacso-404-btn { width: 100%; justify-content: center; }
			}
		</style>
		<?php
	}
}
