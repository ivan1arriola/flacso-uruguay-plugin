<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Flacso_Webhook_Forms {
	const POST_TYPE  = 'flacso_hook_form';
	const META_FIELDS = '_flacso_hook_fields';
	const META_AUTO_EMAIL = '_flacso_hook_auto_email';
	const META_THANK_YOU = '_flacso_hook_thank_you';
	const META_SHOW_ON_HOME = '_flacso_hook_show_on_home';
	const NONCE       = 'flacso_hook_form_submit';
	const MAX_FILE_SIZE = 10485760;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_form' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_public_assets' ] );
		add_filter( 'the_content', [ __CLASS__, 'render_single_content' ] );
		add_shortcode( 'flacso_formulario_webhook', [ __CLASS__, 'shortcode' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
		add_action( 'admin_post_nopriv_flacso_hook_form_submit', [ __CLASS__, 'handle_submit' ] );
		add_action( 'admin_post_flacso_hook_form_submit', [ __CLASS__, 'handle_submit' ] );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrites' ], 20 );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy_url' ], 1 );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_filter( 'document_title_parts', [ __CLASS__, 'thank_you_document_title' ] );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels' => [
					'name'                  => __( 'Formularios', 'flacso-uruguay' ),
					'singular_name'         => __( 'Formulario', 'flacso-uruguay' ),
					'add_new'               => __( 'Agregar nuevo', 'flacso-uruguay' ),
					'add_new_item'          => __( 'Crear formulario', 'flacso-uruguay' ),
					'edit_item'             => __( 'Editar formulario', 'flacso-uruguay' ),
					'new_item'              => __( 'Nuevo formulario', 'flacso-uruguay' ),
					'view_item'             => __( 'Ver formulario', 'flacso-uruguay' ),
					'view_items'            => __( 'Ver formularios', 'flacso-uruguay' ),
					'search_items'          => __( 'Buscar formularios', 'flacso-uruguay' ),
					'not_found'             => __( 'No se encontraron formularios.', 'flacso-uruguay' ),
					'not_found_in_trash'    => __( 'No hay formularios en la papelera.', 'flacso-uruguay' ),
					'all_items'             => __( 'Todos los formularios', 'flacso-uruguay' ),
					'archives'              => __( 'Formularios', 'flacso-uruguay' ),
					'attributes'            => __( 'Atributos del formulario', 'flacso-uruguay' ),
					'featured_image'        => __( 'Imagen destacada', 'flacso-uruguay' ),
					'set_featured_image'    => __( 'Seleccionar imagen destacada', 'flacso-uruguay' ),
					'remove_featured_image' => __( 'Quitar imagen destacada', 'flacso-uruguay' ),
					'use_featured_image'    => __( 'Usar como imagen destacada', 'flacso-uruguay' ),
					'menu_name'             => __( 'Formularios', 'flacso-uruguay' ),
					'name_admin_bar'        => __( 'Formulario', 'flacso-uruguay' ),
				],
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'formulario', 'with_front' => false ],
				'menu_icon'    => 'dashicons-forms',
				'menu_position'=> 28,
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
				'rest_base'    => 'formularios-webhook',
			]
		);
		add_rewrite_rule(
			'^formulario/([^/]+)/gracias/?$',
			'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]&flacso_form_thanks=1',
			'top'
		);
	}

	public static function maybe_flush_rewrites() {
		$version = '3';
		if ( get_option( 'flacso_hook_forms_rewrite_version' ) === $version ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'flacso_hook_forms_rewrite_version', $version, false );
	}

	public static function register_query_vars( $vars ) {
		$vars[] = 'flacso_form_thanks';
		return $vars;
	}

	public static function thank_you_document_title( $parts ) {
		if ( is_singular( self::POST_TYPE ) && get_query_var( 'flacso_form_thanks' ) ) {
			$config = self::get_thank_you( get_queried_object_id() );
			$parts['title'] = $config['title'];
		}
		return $parts;
	}

	public static function redirect_legacy_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! preg_match( '#/formularios-webhook/([^/]+)/?$#', $path, $matches ) ) {
			return;
		}
		$slug = sanitize_title( rawurldecode( $matches[1] ) );
		if ( '' === $slug ) {
			return;
		}
		$form = get_page_by_path( $slug, OBJECT, self::POST_TYPE );
		if ( $form && 'publish' === $form->post_status ) {
			wp_safe_redirect( get_permalink( $form ), 301 );
			exit;
		}
	}

	public static function add_meta_boxes() {
		add_meta_box( 'flacso-hook-settings', __( 'Entrega al webhook', 'flacso-uruguay' ), [ __CLASS__, 'settings_box' ], self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'flacso-hook-fields', __( 'Campos del formulario', 'flacso-uruguay' ), [ __CLASS__, 'fields_box' ], self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'flacso-hook-embed', __( 'Publicación', 'flacso-uruguay' ), [ __CLASS__, 'embed_box' ], self::POST_TYPE, 'side', 'default' );
	}

	public static function settings_box( $post ) {
		wp_nonce_field( 'flacso_hook_form_save', 'flacso_hook_form_nonce' );
		?>
		<p><?php esc_html_e( 'La app usa la carpeta global exclusiva de formularios y crea automáticamente una subcarpeta para este formulario.', 'flacso-uruguay' ); ?></p>
		<p class="description"><?php esc_html_e( 'La autenticación usa automáticamente el token global compartido por todos los formularios de FLACSO.', 'flacso-uruguay' ); ?></p>
		<hr>
		<label>
			<input type="checkbox" name="flacso_hook_show_on_home" value="1" <?php checked( '1', get_post_meta( $post->ID, self::META_SHOW_ON_HOME, true ) ); ?>>
			<?php esc_html_e( 'Mostrar junto a las novedades de la página principal', 'flacso-uruguay' ); ?>
		</label>
		<?php
	}

	public static function field_types() {
		return [
			'nombre_completo' => __( 'Nombre completo', 'flacso-uruguay' ),
			'nombre'    => __( 'Nombre', 'flacso-uruguay' ),
			'apellido'  => __( 'Apellido', 'flacso-uruguay' ),
			'email'     => __( 'Correo electrónico', 'flacso-uruguay' ),
			'pais'      => __( 'País', 'flacso-uruguay' ),
			'telefono'  => __( 'Teléfono', 'flacso-uruguay' ),
			'select'    => __( 'Selección', 'flacso-uruguay' ),
			'booleano'  => __( 'Sí/No', 'flacso-uruguay' ),
			'opcion_multiple' => __( 'Opción múltiple', 'flacso-uruguay' ),
			'casillas' => __( 'Casillas múltiples', 'flacso-uruguay' ),
			'escala' => __( 'Escala lineal', 'flacso-uruguay' ),
			'valoracion' => __( 'Valoración', 'flacso-uruguay' ),
			'cuadricula_opcion' => __( 'Cuadrícula de opción múltiple', 'flacso-uruguay' ),
			'cuadricula_casillas' => __( 'Cuadrícula de casillas', 'flacso-uruguay' ),
			'fecha' => __( 'Fecha', 'flacso-uruguay' ),
			'hora' => __( 'Hora', 'flacso-uruguay' ),
			'seccion' => __( 'Sección', 'flacso-uruguay' ),
			'documento' => __( 'Documento de identidad (UY/extranjero)', 'flacso-uruguay' ),
			'texto'     => __( 'Texto corto', 'flacso-uruguay' ),
			'textarea'  => __( 'Texto largo', 'flacso-uruguay' ),
			'archivo'   => __( 'Archivo (imagen o PDF)', 'flacso-uruguay' ),
		];
	}

	public static function get_fields( $post_id ) {
		$fields = get_post_meta( $post_id, self::META_FIELDS, true );
		return is_array( $fields ) ? $fields : [];
	}

	public static function fields_box( $post ) {
		$fields = self::get_fields( $post->ID );
		?>
		<div id="flacso-hook-fields-list">
			<?php foreach ( $fields as $index => $field ) : ?>
				<?php self::field_row( $index, $field ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button button-secondary" id="flacso-hook-add-field"><?php esc_html_e( 'Agregar campo', 'flacso-uruguay' ); ?></button></p>
		<script type="text/html" id="tmpl-flacso-hook-field"><?php self::field_row( '{{data.index}}', [] ); ?></script>
		<p class="description"><?php esc_html_e( 'Arrastrá los campos desde el asa de la izquierda para cambiar su orden.', 'flacso-uruguay' ); ?></p>
		<?php
	}

	private static function field_row( $index, $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'texto';
		?>
		<div class="flacso-hook-field">
			<span class="dashicons dashicons-move flacso-hook-field-handle" aria-hidden="true"></span>
			<p>
				<label><?php esc_html_e( 'Tipo', 'flacso-uruguay' ); ?>
					<select name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][type]">
						<?php foreach ( self::field_types() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Etiqueta', 'flacso-uruguay' ); ?>
					<input type="text" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>" required>
				</label>
				<label><?php esc_html_e( 'Nombre interno', 'flacso-uruguay' ); ?>
					<input type="text" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : '' ); ?>" placeholder="ej: motivacion">
				</label>
			</p>
			<p>
				<label class="flacso-hook-grow"><?php esc_html_e( 'Texto de ayuda', 'flacso-uruguay' ); ?>
					<input class="widefat" type="text" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][help]" value="<?php echo esc_attr( isset( $field['help'] ) ? $field['help'] : '' ); ?>">
				</label>
				<label class="flacso-hook-grow"><?php esc_html_e( 'Opciones (una por línea, sólo selección)', 'flacso-uruguay' ); ?>
					<textarea class="widefat" rows="3" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][options]"><?php echo esc_textarea( isset( $field['options'] ) && is_array( $field['options'] ) ? implode( "\n", $field['options'] ) : '' ); ?></textarea>
				</label>
				<label class="flacso-hook-grow"><?php esc_html_e( 'Filas (cuadrículas)', 'flacso-uruguay' ); ?>
					<textarea class="widefat" rows="3" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][rows]"><?php echo esc_textarea( isset( $field['rows'] ) && is_array( $field['rows'] ) ? implode( "\n", $field['rows'] ) : '' ); ?></textarea>
				</label>
				<label class="flacso-hook-grow"><?php esc_html_e( 'Columnas (cuadrículas)', 'flacso-uruguay' ); ?>
					<textarea class="widefat" rows="3" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][columns]"><?php echo esc_textarea( isset( $field['columns'] ) && is_array( $field['columns'] ) ? implode( "\n", $field['columns'] ) : '' ); ?></textarea>
				</label>
				<label><?php esc_html_e( 'Escala mínima', 'flacso-uruguay' ); ?>
					<select name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][scale_min]"><option value="0" <?php selected( (int) ( $field['scale_min'] ?? 1 ), 0 ); ?>>0</option><option value="1" <?php selected( (int) ( $field['scale_min'] ?? 1 ), 1 ); ?>>1</option></select>
				</label>
				<label><?php esc_html_e( 'Escala máxima', 'flacso-uruguay' ); ?>
					<input type="number" min="2" max="10" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][scale_max]" value="<?php echo esc_attr( $field['scale_max'] ?? 5 ); ?>">
				</label>
				<label><input type="checkbox" name="flacso_hook_fields[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php esc_html_e( 'Obligatorio', 'flacso-uruguay' ); ?></label>
				<button type="button" class="button-link-delete flacso-hook-remove-field"><?php esc_html_e( 'Eliminar', 'flacso-uruguay' ); ?></button>
			</p>
		</div>
		<?php
	}

	public static function embed_box( $post ) {
		?>
		<p><?php esc_html_e( 'El formulario tiene una página pública propia al publicarlo.', 'flacso-uruguay' ); ?></p>
		<p><code>[flacso_formulario_webhook id="<?php echo esc_attr( $post->ID ); ?>"]</code></p>
		<?php if ( 'publish' === $post->post_status ) : ?>
			<p><a class="button" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver formulario', 'flacso-uruguay' ); ?></a></p>
		<?php endif; ?>
		<?php
	}

	public static function save_form( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['flacso_hook_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flacso_hook_form_nonce'] ) ), 'flacso_hook_form_save' ) ) {
			return;
		}


		$raw_fields = isset( $_POST['flacso_hook_fields'] ) && is_array( $_POST['flacso_hook_fields'] ) ? wp_unslash( $_POST['flacso_hook_fields'] ) : [];
		update_post_meta( $post_id, self::META_FIELDS, self::normalize_fields( $raw_fields ) );
		update_post_meta( $post_id, self::META_SHOW_ON_HOME, empty( $_POST['flacso_hook_show_on_home'] ) ? '0' : '1' );
	}

	private static function normalize_fields( $raw_fields ) {
		$fields = [];
		$used_names = [];
		foreach ( $raw_fields as $position => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$type = isset( $raw['type'] ) && array_key_exists( $raw['type'], self::field_types() ) ? $raw['type'] : 'texto';
			$label = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$name = isset( $raw['name'] ) ? sanitize_key( $raw['name'] ) : '';
			if ( '' === $name ) {
				$name = sanitize_key( remove_accents( $label ) );
			}
			if ( '' === $name ) {
				$name = 'campo_' . ( (int) $position + 1 );
			}
			$base = $name;
			$suffix = 2;
			while ( isset( $used_names[ $name ] ) ) {
				$name = $base . '_' . $suffix++;
			}
			$used_names[ $name ] = true;
			$fields[] = [
				'type'     => $type,
				'label'    => $label,
				'name'     => $name,
				'help'     => isset( $raw['help'] ) ? sanitize_text_field( $raw['help'] ) : '',
				'required' => ! empty( $raw['required'] ),
				'options'  => self::normalize_options( isset( $raw['options'] ) ? $raw['options'] : [] ),
				'rows'     => self::normalize_options( isset( $raw['rows'] ) ? $raw['rows'] : [] ),
				'columns'  => self::normalize_options( isset( $raw['columns'] ) ? $raw['columns'] : [] ),
				'scale_min'=> isset( $raw['scale_min'] ) && 0 === (int) $raw['scale_min'] ? 0 : 1,
				'scale_max'=> isset( $raw['scale_max'] ) ? min( 10, max( 2, (int) $raw['scale_max'] ) ) : 5,
			];
		}
		return $fields;
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'flacso-hook-forms-admin', FLACSO_WEBHOOK_FORMS_URL . 'assets/admin.js', [ 'jquery', 'jquery-ui-sortable', 'wp-util' ], FLACSO_URUGUAY_VERSION, true );
		wp_enqueue_style( 'flacso-hook-forms-admin', FLACSO_WEBHOOK_FORMS_URL . 'assets/admin.css', [], FLACSO_URUGUAY_VERSION );
	}

	public static function register_public_assets() {
		wp_register_style( 'flacso-hook-intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/css/intlTelInput.css', [], '25.12.4' );
		wp_register_script( 'flacso-hook-intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/js/intlTelInput.min.js', [], '25.12.4', true );
		wp_register_style( 'flacso-hook-forms', FLACSO_WEBHOOK_FORMS_URL . 'assets/form.css', [ 'flacso-hook-intl-tel-input' ], FLACSO_URUGUAY_VERSION );
		wp_register_script( 'flacso-hook-forms', FLACSO_WEBHOOK_FORMS_URL . 'assets/form.js', [ 'flacso-hook-intl-tel-input' ], FLACSO_URUGUAY_VERSION, true );
	}

	public static function render_single_content( $content ) {
		if ( is_singular( self::POST_TYPE ) && in_the_loop() && is_main_query() ) {
			if ( get_query_var( 'flacso_form_thanks' ) ) {
				$config = self::get_thank_you( get_the_ID() );
				wp_enqueue_style( 'flacso-hook-forms' );
				return '<section class="flacso-hook-thanks"><h1>' . esc_html( $config['title'] ) . '</h1><div>' . wp_kses_post( $config['body'] ) . '</div></section>';
			}
			return '<div class="flacso-hook-intro">' . $content . '</div>' . self::render_form( get_the_ID(), false );
		}
		return $content;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts );
		return self::render_form( absint( $atts['id'] ) );
	}

	public static function render_form( $form_id, $show_title = true ) {
		if ( self::POST_TYPE !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'El formulario no existe o todavía no está publicado.', 'flacso-uruguay' ) . '</p>' : '';
		}
		$fields = self::get_fields( $form_id );
		wp_enqueue_style( 'flacso-hook-forms' );
		wp_enqueue_script( 'flacso-hook-forms' );
		$status = isset( $_GET['formulario_estado'] ) ? sanitize_key( wp_unslash( $_GET['formulario_estado'] ) ) : '';
		$error_code = isset( $_GET['formulario_error'] ) ? sanitize_key( wp_unslash( $_GET['formulario_error'] ) ) : '';
		$error_field = isset( $_GET['formulario_campo'] ) ? sanitize_text_field( wp_unslash( $_GET['formulario_campo'] ) ) : '';
		ob_start();
		?>
		<section class="flacso-hook-form-wrap">
			<?php if ( $show_title ) : ?><h1><?php echo esc_html( get_the_title( $form_id ) ); ?></h1><?php endif; ?>
			<?php if ( 'enviado' === $status ) : ?>
				<div class="flacso-hook-notice success" role="status"><?php esc_html_e( 'El formulario fue enviado correctamente.', 'flacso-uruguay' ); ?></div>
			<?php elseif ( 'error' === $status ) : ?>
				<div class="flacso-hook-notice error" role="alert"><?php echo esc_html( self::public_error_message( $error_code, $error_field ) ); ?></div>
			<?php endif; ?>
			<form class="flacso-hook-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="flacso_hook_form_submit">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<input type="hidden" name="started_at" value="<?php echo esc_attr( time() ); ?>">
				<input class="flacso-hook-hp" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">
				<?php wp_nonce_field( self::NONCE . '_' . $form_id, 'form_nonce' ); ?>
				<?php foreach ( $fields as $field ) : ?>
					<?php self::render_field( $field ); ?>
				<?php endforeach; ?>
				<button type="submit"><span class="flacso-hook-submit-label"><?php esc_html_e( 'Enviar', 'flacso-uruguay' ); ?></span></button>
				<p class="flacso-hook-form-status screen-reader-text" aria-live="polite"></p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function register_rest_routes() {
		register_rest_route(
			'flacso/v1',
			'/webhook-forms',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'rest_list_forms' ],
					'permission_callback' => [ __CLASS__, 'rest_can_create_forms' ],
					'args'                => [
						'status'   => [ 'type' => 'string', 'default' => 'any' ],
						'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
						'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'rest_create_form' ],
					'permission_callback' => [ __CLASS__, 'rest_can_create_forms' ],
				],
			]
		);
		register_rest_route(
			'flacso/v1',
			'/webhook-forms/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'rest_update_form' ],
				'permission_callback' => [ __CLASS__, 'rest_can_edit_form' ],
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			]
		);
		register_rest_route(
			'flacso/v1',
			'/webhook-forms/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'rest_get_form' ],
				'permission_callback' => [ __CLASS__, 'rest_can_edit_form' ],
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			]
		);
	}

	public static function rest_can_create_forms() {
		return current_user_can( 'edit_posts' );
	}

	public static function rest_can_edit_form( $request ) {
		$id = absint( $request['id'] );
		return self::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	public static function rest_list_forms( $request ) {
		$status = sanitize_key( $request->get_param( 'status' ) );
		$allowed_statuses = [ 'any', 'publish', 'draft', 'pending', 'private' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'any';
		}
		$query = new WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => $status,
				'posts_per_page' => absint( $request->get_param( 'per_page' ) ),
				'paged'          => absint( $request->get_param( 'page' ) ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);
		$items = array_map( [ __CLASS__, 'rest_prepare_form' ], $query->posts );
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
		return $response;
	}

	public static function rest_get_form( $request ) {
		return rest_ensure_response( self::rest_prepare_form( get_post( absint( $request['id'] ) ) ) );
	}

	public static function rest_create_form( $request ) {
		return self::rest_save_form( $request, 0 );
	}

	public static function rest_update_form( $request ) {
		return self::rest_save_form( $request, absint( $request['id'] ) );
	}

	private static function rest_save_form( $request, $post_id ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_params();
		}
		$post_data = [
			'post_type' => self::POST_TYPE,
		];
		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['content'] );
		}
		if ( isset( $data['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}
		if ( isset( $data['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $data['slug'] );
		} elseif ( ! $post_id && ! empty( $post_data['post_title'] ) ) {
			$post_data['post_name'] = sanitize_title( $post_data['post_title'] );
		}
		if ( isset( $data['status'] ) ) {
			$status = sanitize_key( $data['status'] );
			if ( ! in_array( $status, [ 'draft', 'pending', 'publish', 'private' ], true ) ) {
				return new WP_Error( 'invalid_status', __( 'Estado de formulario inválido.', 'flacso-uruguay' ), [ 'status' => 400 ] );
			}
			if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
				return new WP_Error( 'cannot_publish', __( 'No tenés permisos para publicar formularios.', 'flacso-uruguay' ), [ 'status' => 403 ] );
			}
			$post_data['post_status'] = $status;
		} elseif ( ! $post_id ) {
			$post_data['post_status'] = 'draft';
		}

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$result = wp_update_post( wp_slash( $post_data ), true );
		} else {
			if ( empty( $post_data['post_title'] ) ) {
				return new WP_Error( 'missing_title', __( 'El título es obligatorio.', 'flacso-uruguay' ), [ 'status' => 400 ] );
			}
			$result = wp_insert_post( wp_slash( $post_data ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;

		if ( isset( $data['fields'] ) ) {
			if ( ! is_array( $data['fields'] ) ) {
				return new WP_Error( 'invalid_fields', __( 'fields debe ser una lista.', 'flacso-uruguay' ), [ 'status' => 400 ] );
			}
			update_post_meta( $post_id, self::META_FIELDS, self::normalize_fields( $data['fields'] ) );
		}
		if ( isset( $data['auto_email'] ) && is_array( $data['auto_email'] ) ) {
			update_post_meta( $post_id, self::META_AUTO_EMAIL, self::normalize_auto_email( $data['auto_email'] ) );
		}
		if ( isset( $data['thank_you'] ) && is_array( $data['thank_you'] ) ) {
			update_post_meta( $post_id, self::META_THANK_YOU, self::normalize_thank_you( $data['thank_you'] ) );
		}
		if ( array_key_exists( 'show_on_home', $data ) ) {
			update_post_meta( $post_id, self::META_SHOW_ON_HOME, rest_sanitize_boolean( $data['show_on_home'] ) ? '1' : '0' );
		}
		if ( isset( $data['featured_media'] ) ) {
			$attachment_id = absint( $data['featured_media'] );
			if ( $attachment_id && ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'invalid_featured_media', __( 'La imagen destacada debe ser un adjunto de imagen válido.', 'flacso-uruguay' ), [ 'status' => 400 ] );
			}
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}
		return new WP_REST_Response( self::rest_prepare_form( get_post( $post_id ) ), $post_id === absint( $request['id'] ) ? 200 : 201 );
	}

	public static function rest_prepare_form( $post ) {
		return [
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'slug'           => $post->post_name,
			'status'         => $post->post_status,
			'link'           => get_permalink( $post ),
			'featured_media' => (int) get_post_thumbnail_id( $post ),
			'featured_media_url' => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'show_on_home'   => '1' === get_post_meta( $post->ID, self::META_SHOW_ON_HOME, true ),
			'fields'         => self::get_fields( $post->ID ),
			'auto_email'     => self::get_auto_email( $post->ID ),
			'thank_you'      => self::get_thank_you( $post->ID ),
			'uses_global_webhook_token' => true,
			'modified_gmt'   => get_post_modified_time( 'c', true, $post ),
		];
	}

	private static function render_field( $field ) {
		$id = 'flacso-field-' . $field['name'];
		$required = ! empty( $field['required'] );
		?>
		<div class="flacso-hook-control">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php if ( $required ) : ?> <span class="flacso-hook-required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( ' obligatorio', 'flacso-uruguay' ); ?></span><?php endif; ?></label>
			<?php if ( 'textarea' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" rows="6" placeholder="<?php esc_attr_e( 'Escribí tu respuesta', 'flacso-uruguay' ); ?>" <?php echo $required ? 'required' : ''; ?>></textarea>
			<?php elseif ( 'documento' === $field['type'] ) : ?>
				<div class="flacso-hook-document">
					<select name="document_types[<?php echo esc_attr( $field['name'] ); ?>]" aria-label="<?php esc_attr_e( 'Tipo de documento', 'flacso-uruguay' ); ?>">
						<option value="uy"><?php esc_html_e( 'Cédula uruguaya', 'flacso-uruguay' ); ?></option>
						<option value="ext"><?php esc_html_e( 'Documento extranjero', 'flacso-uruguay' ); ?></option>
					</select>
					<input type="text" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" inputmode="numeric" <?php echo $required ? 'required' : ''; ?>>
				</div>
			<?php elseif ( 'archivo' === $field['type'] ) : ?>
				<input type="file" id="<?php echo esc_attr( $id ); ?>" name="files[<?php echo esc_attr( $field['name'] ); ?>]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?php echo $required ? 'required' : ''; ?>>
				<small><?php esc_html_e( 'PDF, JPG, PNG o WebP. Máximo 10 MB.', 'flacso-uruguay' ); ?></small>
			<?php elseif ( 'email' === $field['type'] ) : ?>
				<input type="email" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" maxlength="254" autocomplete="email" inputmode="email" placeholder="ejemplo@correo.com" aria-describedby="<?php echo esc_attr( $id ); ?>-error" <?php echo $required ? 'required' : ''; ?>>
				<small class="flacso-hook-field-error" id="<?php echo esc_attr( $id ); ?>-error" aria-live="polite"><?php esc_html_e( 'Ingresá un correo electrónico válido (por ejemplo, nombre@dominio.com).', 'flacso-uruguay' ); ?></small>
			<?php elseif ( 'pais' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" autocomplete="country-name" <?php echo $required ? 'required' : ''; ?>>
					<option value=""><?php esc_html_e( 'Seleccioná un país', 'flacso-uruguay' ); ?></option>
					<?php foreach ( self::countries() as $country ) : ?>
						<option value="<?php echo esc_attr( $country ); ?>"><?php echo esc_html( $country ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'telefono' === $field['type'] ) : ?>
				<input class="flacso-hook-phone" type="tel" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" maxlength="30" inputmode="tel" autocomplete="tel" <?php echo $required ? 'required' : ''; ?>>
				<small class="flacso-hook-phone-help"><?php esc_html_e( 'Seleccioná el país y escribí el número en formato nacional.', 'flacso-uruguay' ); ?></small>
			<?php elseif ( 'select' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" <?php echo $required ? 'required' : ''; ?>>
					<option value=""><?php esc_html_e( 'Seleccioná una opción', 'flacso-uruguay' ); ?></option>
					<?php foreach ( self::normalize_options( isset( $field['options'] ) ? $field['options'] : [] ) as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'booleano' === $field['type'] ) : ?>
				<label class="flacso-hook-checkbox">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" value="Sí" <?php echo $required ? 'required' : ''; ?>>
					<span><?php esc_html_e( 'Sí', 'flacso-uruguay' ); ?></span>
				</label>
			<?php elseif ( 'opcion_multiple' === $field['type'] ) : ?>
				<?php foreach ( self::normalize_options( $field['options'] ?? [] ) as $option_index => $option ) : ?>
					<label class="flacso-hook-checkbox"><input type="radio" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" value="<?php echo esc_attr( $option ); ?>" <?php echo $required && 0 === $option_index ? 'required' : ''; ?>> <span><?php echo esc_html( $option ); ?></span></label>
				<?php endforeach; ?>
			<?php elseif ( 'casillas' === $field['type'] ) : ?>
				<?php foreach ( self::normalize_options( $field['options'] ?? [] ) as $option ) : ?>
					<label class="flacso-hook-checkbox"><input type="checkbox" name="fields[<?php echo esc_attr( $field['name'] ); ?>][]" value="<?php echo esc_attr( $option ); ?>"> <span><?php echo esc_html( $option ); ?></span></label>
				<?php endforeach; ?>
			<?php elseif ( 'escala' === $field['type'] || 'valoracion' === $field['type'] ) : ?>
				<div class="flacso-hook-scale">
					<?php for ( $number = (int) ( $field['scale_min'] ?? 1 ); $number <= (int) ( $field['scale_max'] ?? 5 ); $number++ ) : ?>
						<label><input type="radio" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" value="<?php echo esc_attr( $number ); ?>" <?php echo $required && $number === (int) ( $field['scale_min'] ?? 1 ) ? 'required' : ''; ?>> <span><?php echo 'valoracion' === $field['type'] ? '★ ' : ''; ?><?php echo esc_html( $number ); ?></span></label>
					<?php endfor; ?>
				</div>
			<?php elseif ( 'cuadricula_opcion' === $field['type'] || 'cuadricula_casillas' === $field['type'] ) : ?>
				<div class="flacso-hook-grid">
					<?php foreach ( self::normalize_options( $field['rows'] ?? [] ) as $row ) : ?>
						<fieldset><legend><?php echo esc_html( $row ); ?></legend>
							<?php foreach ( self::normalize_options( $field['columns'] ?? [] ) as $column_index => $column ) : ?>
								<label class="flacso-hook-checkbox"><input type="<?php echo 'cuadricula_opcion' === $field['type'] ? 'radio' : 'checkbox'; ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>][<?php echo esc_attr( $row ); ?>]<?php echo 'cuadricula_casillas' === $field['type'] ? '[]' : ''; ?>" value="<?php echo esc_attr( $column ); ?>" <?php echo $required && 0 === $column_index ? 'required' : ''; ?>> <span><?php echo esc_html( $column ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; ?>
				</div>
			<?php elseif ( 'fecha' === $field['type'] ) : ?>
				<input type="date" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" <?php echo $required ? 'required' : ''; ?>>
			<?php elseif ( 'hora' === $field['type'] ) : ?>
				<input type="time" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" <?php echo $required ? 'required' : ''; ?>>
			<?php elseif ( 'seccion' === $field['type'] ) : ?>
				<div class="flacso-hook-section" role="separator"></div>
			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="fields[<?php echo esc_attr( $field['name'] ); ?>]" maxlength="250" autocomplete="<?php echo esc_attr( 'nombre' === $field['type'] ? 'given-name' : ( 'apellido' === $field['type'] ? 'family-name' : ( 'nombre_completo' === $field['type'] ? 'name' : 'off' ) ) ); ?>" placeholder="<?php echo esc_attr( $field['label'] ); ?>" <?php echo $required ? 'required' : ''; ?>>
			<?php endif; ?>
			<?php if ( ! empty( $field['help'] ) ) : ?><small><?php echo esc_html( $field['help'] ); ?></small><?php endif; ?>
		</div>
		<?php
	}

	public static function handle_submit() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$redirect = $form_id ? get_permalink( $form_id ) : home_url( '/' );
		if ( ! $form_id || self::POST_TYPE !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			self::redirect_error( $redirect, 'form_unavailable' );
		}
		if ( empty( $_POST['form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['form_nonce'] ) ), self::NONCE . '_' . $form_id ) ) {
			self::redirect_error( $redirect, 'session_expired' );
		}
		if ( ! empty( $_POST['company_website'] ) || empty( $_POST['started_at'] ) || time() - absint( $_POST['started_at'] ) < 2 ) {
			self::redirect_error( $redirect, 'submission_rejected' );
		}

		$fields = self::get_fields( $form_id );
		$posted = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : [];
		$document_types = isset( $_POST['document_types'] ) && is_array( $_POST['document_types'] ) ? wp_unslash( $_POST['document_types'] ) : [];
		$values = [];
		$files = [];
		foreach ( $fields as $field ) {
			$name = $field['name'];
			if ( 'seccion' === $field['type'] ) {
				continue;
			}
			if ( 'archivo' === $field['type'] ) {
				$file = self::validated_file( $name, ! empty( $field['required'] ) );
				if ( is_wp_error( $file ) ) {
					self::redirect_error( $redirect, $file->get_error_code(), $field['label'] );
				}
				if ( $file ) {
					$files[ $name ] = $file;
				}
				continue;
			}
			if ( 'casillas' === $field['type'] ) {
				$selected = isset( $posted[ $name ] ) && is_array( $posted[ $name ] ) ? array_map( 'sanitize_text_field', $posted[ $name ] ) : [];
				$selected = array_values( array_intersect( $selected, self::normalize_options( $field['options'] ?? [] ) ) );
				if ( ! empty( $field['required'] ) && empty( $selected ) ) {
					self::redirect_error( $redirect, 'required', $field['label'] );
				}
				$values[ $name ] = implode( ', ', $selected );
				continue;
			}
			if ( 'cuadricula_opcion' === $field['type'] || 'cuadricula_casillas' === $field['type'] ) {
				$grid_posted = isset( $posted[ $name ] ) && is_array( $posted[ $name ] ) ? $posted[ $name ] : [];
				$grid_value = [];
				$columns = self::normalize_options( $field['columns'] ?? [] );
				foreach ( self::normalize_options( $field['rows'] ?? [] ) as $row ) {
					$answer = $grid_posted[ $row ] ?? ( 'cuadricula_casillas' === $field['type'] ? [] : '' );
					if ( 'cuadricula_casillas' === $field['type'] ) {
						$answer = is_array( $answer ) ? array_values( array_intersect( array_map( 'sanitize_text_field', $answer ), $columns ) ) : [];
					} else {
						$answer = is_scalar( $answer ) ? sanitize_text_field( (string) $answer ) : '';
						$answer = in_array( $answer, $columns, true ) ? $answer : '';
					}
					if ( ! empty( $field['required'] ) && empty( $answer ) ) {
						self::redirect_error( $redirect, 'required', $field['label'] . ': ' . $row );
					}
					$grid_value[ $row ] = $answer;
				}
				$values[ $name ] = wp_json_encode( $grid_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				continue;
			}
			$raw_value = isset( $posted[ $name ] ) && is_scalar( $posted[ $name ] ) ? (string) $posted[ $name ] : '';
			if ( 'booleano' === $field['type'] ) {
				$raw_value = 'Sí' === $raw_value ? 'Sí' : 'No';
			}
			$value = 'email' === $field['type']
				? sanitize_email( $raw_value )
				: trim( sanitize_textarea_field( $raw_value ) );
			if ( ! empty( $field['required'] ) && '' === $value ) {
				self::redirect_error( $redirect, 'required', $field['label'] );
			}
			if ( 'email' === $field['type'] && '' !== trim( $raw_value ) && ! is_email( $value ) ) {
				self::redirect_error( $redirect, 'invalid_email', $field['label'] );
			}
			if ( 'pais' === $field['type'] && '' !== $value && ! in_array( $value, self::countries(), true ) ) {
				self::redirect_error( $redirect, 'invalid_option', $field['label'] );
			}
			if ( 'telefono' === $field['type'] && '' !== $value && ! preg_match( '/^[+0-9 ()-]{6,30}$/', $value ) ) {
				self::redirect_error( $redirect, 'invalid_phone', $field['label'] );
			}
			if ( 'select' === $field['type'] && '' !== $value && ! in_array( $value, self::normalize_options( isset( $field['options'] ) ? $field['options'] : [] ), true ) ) {
				self::redirect_error( $redirect, 'invalid_option', $field['label'] );
			}
			if ( 'opcion_multiple' === $field['type'] && '' !== $value && ! in_array( $value, self::normalize_options( $field['options'] ?? [] ), true ) ) {
				self::redirect_error( $redirect, 'invalid_option', $field['label'] );
			}
			if ( ( 'escala' === $field['type'] || 'valoracion' === $field['type'] ) && '' !== $value ) {
				$number = (int) $value;
				if ( (string) $number !== $value || $number < (int) ( $field['scale_min'] ?? 1 ) || $number > (int) ( $field['scale_max'] ?? 5 ) ) {
					self::redirect_error( $redirect, 'invalid_option', $field['label'] );
				}
			}
			if ( 'fecha' === $field['type'] && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				self::redirect_error( $redirect, 'invalid_date', $field['label'] );
			}
			if ( 'hora' === $field['type'] && '' !== $value && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
				self::redirect_error( $redirect, 'invalid_time', $field['label'] );
			}
			if ( 'booleano' === $field['type'] && ! empty( $field['required'] ) && 'Sí' !== $value ) {
				self::redirect_error( $redirect, 'required_acceptance', $field['label'] );
			}
			if ( 'documento' === $field['type'] && '' !== $value ) {
				$doc_type = isset( $document_types[ $name ] ) && is_scalar( $document_types[ $name ] ) && 'ext' === $document_types[ $name ] ? 'ext' : 'uy';
				if ( ( 'uy' === $doc_type && ! self::valid_uy_document( $value ) ) || ( 'ext' === $doc_type && ( strlen( $value ) < 3 || strlen( $value ) > 40 ) ) ) {
					self::redirect_error( $redirect, 'invalid_document', $field['label'] );
				}
				$values[ $name . '_tipo' ] = $doc_type;
			}
			$values[ $name ] = $value;
		}
		$total_file_size = array_sum( array_map( static function( $file ) {
			return isset( $file['size'] ) ? (int) $file['size'] : 0;
		}, $files ) );
		if ( $total_file_size > 12 * 1024 * 1024 ) {
			self::redirect_error( $redirect, 'files_too_large' );
		}

		$editor_base_url = trim( (string) get_option( 'flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app' ) );
		if ( '' === $editor_base_url ) {
			$editor_base_url = 'https://editor-flacso-uy.vercel.app';
		}
		$url = untrailingslashit( $editor_base_url ) . '/api/formularios/respuestas';
		if ( ! wp_http_validate_url( $url ) ) {
			self::notify_delivery_error( $form_id, 'URL del servicio receptor inválida.' );
			self::redirect_error( $redirect, 'service_unavailable' );
		}
		$field_labels = [];
		foreach ( $fields as $field ) {
			$field_labels[ $field['name'] ] = $field['label'];
			if ( 'documento' === $field['type'] ) {
				$field_labels[ $field['name'] . '_tipo' ] = $field['label'] . ' - tipo';
			}
		}
		$payload = [
			'submission_id'=> wp_generate_uuid4(),
			'form_id'      => (string) $form_id,
			'form_title'   => get_the_title( $form_id ),
			'submitted_at' => gmdate( 'c' ),
			'source_url'   => $redirect,
			'fields'       => wp_json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'field_labels' => wp_json_encode( $field_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		];
		$auto_email = self::get_auto_email( $form_id );
		if ( ! empty( $auto_email['enabled'] ) ) {
			$payload['auto_email'] = wp_json_encode( $auto_email, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		foreach ( $values as $key => $value ) {
			$payload[ 'field_' . $key ] = $value;
		}
		$boundary = '--------------------------' . wp_generate_password( 24, false, false );
		$body = self::multipart_body( $payload, $files, $boundary );
		$headers = [ 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ];
		$token = (string) get_option( 'flacso_webhook_token', '' );
		if ( '' !== $token ) {
			$headers['X-FLACSO-Webhook-Token'] = $token;
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$response = wp_safe_remote_post(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => $body,
				'data_format' => 'body',
			]
		);
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
			$delivery_error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
			error_log( '[FLACSO webhook forms] Error de entrega del formulario ' . $form_id . ': ' . $delivery_error );
			self::notify_delivery_error( $form_id, $delivery_error );
			self::redirect_error( $redirect, 'service_unavailable' );
		}
		wp_safe_redirect( trailingslashit( $redirect ) . 'gracias/' );
		exit;
	}

	private static function validated_file( $name, $required ) {
		if (
			empty( $_FILES['files']['name'][ $name ] )
			|| ! isset(
				$_FILES['files']['type'][ $name ],
				$_FILES['files']['tmp_name'][ $name ],
				$_FILES['files']['error'][ $name ],
				$_FILES['files']['size'][ $name ]
			)
		) {
			return $required ? new WP_Error( 'required_file' ) : null;
		}
		$file = [
			'name'     => sanitize_file_name( wp_unslash( $_FILES['files']['name'][ $name ] ) ),
			'type'     => sanitize_mime_type( $_FILES['files']['type'][ $name ] ),
			'tmp_name' => $_FILES['files']['tmp_name'][ $name ],
			'error'    => (int) $_FILES['files']['error'][ $name ],
			'size'     => (int) $_FILES['files']['size'][ $name ],
		];
		if ( in_array( $file['error'], [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) || $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'file_too_large' );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_file' );
		}
		$allowed = [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf' ];
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
			return new WP_Error( 'invalid_file_type' );
		}
		$file['type'] = $checked['type'];
		return $file;
	}

	private static function multipart_body( $payload, $files, $boundary ) {
		$eol = "\r\n";
		$body = '';
		foreach ( $payload as $name => $value ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="' . str_replace( '"', '', $name ) . '"' . $eol . $eol;
			$body .= (string) $value . $eol;
		}
		foreach ( $files as $name => $file ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="files[' . str_replace( '"', '', $name ) . ']"; filename="' . str_replace( [ '"', "\r", "\n" ], '', $file['name'] ) . '"' . $eol;
			$body .= 'Content-Type: ' . $file['type'] . $eol . $eol;
			$body .= file_get_contents( $file['tmp_name'] ) . $eol; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		return $body . '--' . $boundary . '--' . $eol;
	}

	private static function valid_uy_document( $value ) {
		$digits = preg_replace( '/\D+/', '', $value );
		if ( 7 === strlen( $digits ) ) {
			$digits = '0' . $digits;
		}
		if ( 8 !== strlen( $digits ) ) {
			return false;
		}
		$factors = [ 2, 9, 8, 7, 6, 3, 4 ];
		$sum = 0;
		for ( $i = 0; $i < 7; $i++ ) {
			$sum += (int) $digits[ $i ] * $factors[ $i ];
		}
		return ( ( 10 - ( $sum % 10 ) ) % 10 ) === (int) $digits[7];
	}

	private static function countries() {
		return [
			'Uruguay', 'Argentina', 'Bolivia', 'Brasil', 'Chile', 'Colombia',
			'Costa Rica', 'Cuba', 'Ecuador', 'El Salvador', 'Guatemala', 'Haití',
			'Honduras', 'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú',
			'República Dominicana', 'Venezuela', 'Otro',
		];
	}

	private static function get_auto_email( $post_id ) {
		$config = get_post_meta( (int) $post_id, self::META_AUTO_EMAIL, true );
		return self::normalize_auto_email( is_array( $config ) ? $config : [] );
	}

	private static function normalize_auto_email( $config ) {
		return [
			'enabled'     => ! empty( $config['enabled'] ),
			'email_field' => isset( $config['email_field'] ) ? sanitize_key( $config['email_field'] ) : 'email',
			'subject'     => isset( $config['subject'] ) ? sanitize_text_field( $config['subject'] ) : '',
			'body'        => isset( $config['body'] ) ? wp_kses_post( $config['body'] ) : '',
		];
	}

	private static function normalize_options( $options ) {
		if ( is_string( $options ) ) {
			$options = preg_split( '/\r\n|\r|\n/', $options );
		}
		if ( ! is_array( $options ) ) {
			return [];
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $options ) ) ) );
	}

	private static function get_thank_you( $post_id ) {
		$config = get_post_meta( (int) $post_id, self::META_THANK_YOU, true );
		return self::normalize_thank_you( is_array( $config ) ? $config : [] );
	}

	private static function normalize_thank_you( $config ) {
		return [
			'title' => ! empty( $config['title'] ) ? sanitize_text_field( $config['title'] ) : __( '¡Gracias!', 'flacso-uruguay' ),
			'body'  => ! empty( $config['body'] ) ? wp_kses_post( $config['body'] ) : '<p>' . esc_html__( 'Recibimos correctamente tu información.', 'flacso-uruguay' ) . '</p>',
		];
	}

	private static function public_error_message( $code, $field = '' ) {
		$field = trim( (string) $field );
		$field_suffix = '' !== $field ? ' en «' . $field . '»' : '';
		$messages = [
			'required'            => 'Completá el campo obligatorio' . $field_suffix . '.',
			'required_file'       => 'Adjuntá el archivo obligatorio' . $field_suffix . '.',
			'invalid_email'       => 'Ingresá un correo electrónico válido' . $field_suffix . ' (por ejemplo, nombre@dominio.com).',
			'invalid_phone'       => 'Ingresá un teléfono válido' . $field_suffix . ', incluyendo el código de país.',
			'invalid_document'    => 'Revisá el número de documento' . $field_suffix . '. Si es una cédula uruguaya, verificá el dígito final.',
			'invalid_option'      => 'Seleccioná una opción válida' . $field_suffix . '.',
			'invalid_date'        => 'Ingresá una fecha válida' . $field_suffix . '.',
			'invalid_time'        => 'Ingresá una hora válida' . $field_suffix . '.',
			'required_acceptance' => 'Para enviar el formulario tenés que aceptar' . $field_suffix . '.',
			'invalid_file'        => 'No pudimos leer el archivo' . $field_suffix . '. Volvé a seleccionarlo y comprobá que no esté vacío.',
			'file_too_large'      => 'El archivo' . $field_suffix . ' supera los 10 MB. Reducí su tamaño y volvé a adjuntarlo.',
			'invalid_file_type'   => 'El archivo' . $field_suffix . ' debe ser PDF, JPG, PNG o WebP.',
			'files_too_large'     => 'Los archivos adjuntos superan los 12 MB en total. Reducí su tamaño o adjuntá menos archivos.',
			'session_expired'     => 'La sesión del formulario venció. Recargá la página y volvé a completarlo.',
			'submission_rejected' => 'No pudimos validar el envío. Esperá unos segundos y volvé a intentarlo.',
			'form_unavailable'    => 'Este formulario ya no está disponible.',
			'service_unavailable' => 'Tus datos son válidos, pero el servicio de recepción no está disponible. Intentá nuevamente en unos minutos.',
		];
		return $messages[ $code ] ?? 'No pudimos completar el envío. Recargá la página e intentá nuevamente.';
	}

	private static function notify_delivery_error( $form_id, $detail ) {
		if ( function_exists( 'fc_notify_form_admin_error' ) ) {
			fc_notify_form_admin_error(
				[
					'form'   => 'CPT Formulario: ' . get_the_title( $form_id ),
					'stage'  => 'entrega de respuesta',
					'detail' => (string) $detail,
					'url'    => get_permalink( $form_id ),
				]
			);
		}
	}

	private static function redirect_error( $url, $code, $field = '' ) {
		$args = [
			'formulario_estado' => 'error',
			'formulario_error'  => sanitize_key( $code ),
		];
		if ( '' !== trim( (string) $field ) ) {
			$args['formulario_campo'] = sanitize_text_field( $field );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	private static function redirect( $url, $status ) {
		wp_safe_redirect( add_query_arg( 'formulario_estado', $status, $url ) );
		exit;
	}
}
