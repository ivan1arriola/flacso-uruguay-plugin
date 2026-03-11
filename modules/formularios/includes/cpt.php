<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fc_register_cpt() {
    $labels = [
        'name'               => _x( 'Consultas', 'post type general name', 'flacso-flacso-formulario-consultas' ),
        'singular_name'      => _x( 'Consulta', 'post type singular name', 'flacso-flacso-formulario-consultas' ),
        'menu_name'          => _x( 'Consultas', 'admin menu', 'flacso-flacso-formulario-consultas' ),
        'name_admin_bar'     => _x( 'Consulta', 'add new on admin bar', 'flacso-flacso-formulario-consultas' ),
        'add_new'            => _x( 'Añadir nueva', 'consulta', 'flacso-flacso-formulario-consultas' ),
        'add_new_item'       => __( 'Añadir nueva consulta', 'flacso-flacso-formulario-consultas' ),
        'new_item'           => __( 'Nueva consulta', 'flacso-flacso-formulario-consultas' ),
        'edit_item'          => __( 'Editar consulta', 'flacso-flacso-formulario-consultas' ),
        'view_item'          => __( 'Ver consulta', 'flacso-flacso-formulario-consultas' ),
        'all_items'          => __( 'Todas las consultas', 'flacso-flacso-formulario-consultas' ),
        'search_items'       => __( 'Buscar consultas', 'flacso-flacso-formulario-consultas' ),
        'not_found'          => __( 'No se encontraron consultas.', 'flacso-flacso-formulario-consultas' ),
        'not_found_in_trash' => __( 'No hay consultas en la papelera.', 'flacso-flacso-formulario-consultas' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-email',
        'supports'           => [ 'title', 'editor', 'custom-fields' ],
    ];

    register_post_type( 'fc_consulta', $args );
}

add_action( 'init', 'fc_register_cpt' );

/**
 * CPT para Solicitudes de Información (oferta académica).
 */
function fc_register_cpt_info_request() {
    $labels = [
        'name'               => _x( 'Solicitudes de Información', 'post type general name', 'flacso-flacso-formulario-consultas' ),
        'singular_name'      => _x( 'Solicitud de Información', 'post type singular name', 'flacso-flacso-formulario-consultas' ),
        'menu_name'          => _x( 'Solicitudes de Información', 'admin menu', 'flacso-flacso-formulario-consultas' ),
        'name_admin_bar'     => _x( 'Solicitud', 'add new on admin bar', 'flacso-flacso-formulario-consultas' ),
        'add_new'            => _x( 'Añadir nueva', 'solicitud', 'flacso-flacso-formulario-consultas' ),
        'add_new_item'       => __( 'Añadir nueva solicitud', 'flacso-flacso-formulario-consultas' ),
        'new_item'           => __( 'Nueva solicitud', 'flacso-flacso-formulario-consultas' ),
        'edit_item'          => __( 'Editar solicitud', 'flacso-flacso-formulario-consultas' ),
        'view_item'          => __( 'Ver solicitud', 'flacso-flacso-formulario-consultas' ),
        'all_items'          => __( 'Todas las solicitudes', 'flacso-flacso-formulario-consultas' ),
        'search_items'       => __( 'Buscar solicitudes', 'flacso-flacso-formulario-consultas' ),
        'not_found'          => __( 'No se encontraron solicitudes.', 'flacso-flacso-formulario-consultas' ),
        'not_found_in_trash' => __( 'No hay solicitudes en la papelera.', 'flacso-flacso-formulario-consultas' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-format-chat',
        'supports'           => [ 'title', 'custom-fields' ],
    ];

    register_post_type( 'fc_info_request', $args );
}
add_action( 'init', 'fc_register_cpt_info_request' );

/**
 * Columnas personalizadas en el listado de consultas
 */
function fc_consulta_columns( $columns ) {
	$new = [];
	// Mantener checkbox y título
	$new['cb'] = $columns['cb'];
	$new['title'] = __( 'Asunto', 'flacso-flacso-formulario-consultas' );
	$new['fc_nombre'] = __( 'Nombre', 'flacso-flacso-formulario-consultas' );
	$new['fc_apellido'] = __( 'Apellido', 'flacso-flacso-formulario-consultas' );
	$new['fc_email'] = __( 'Email', 'flacso-flacso-formulario-consultas' );
	$new['fc_telefono'] = __( 'Teléfono', 'flacso-flacso-formulario-consultas' );
	$new['fc_programa'] = __( 'Oferta consultada', 'flacso-flacso-formulario-consultas' );
	$new['fc_fecha'] = __( 'Fecha envío', 'flacso-flacso-formulario-consultas' );
	$new['fc_meta_hidden'] = '';
	$new['date'] = $columns['date'];
	return $new;
}
add_filter( 'manage_fc_consulta_posts_columns', 'fc_consulta_columns' );

function fc_consulta_columns_content( $column, $post_id ) {
	switch ( $column ) {
		case 'fc_nombre':
			echo esc_html( get_post_meta( $post_id, 'fc_nombre', true ) );
			break;
        case 'fc_apellido':
            echo esc_html( get_post_meta( $post_id, 'fc_apellido', true ) );
            break;
        case 'fc_email':
            $email = get_post_meta( $post_id, 'fc_email', true );
            if ( $email ) {
                echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
            }
            break;
		case 'fc_telefono':
			echo esc_html( get_post_meta( $post_id, 'fc_telefono', true ) );
			break;
		case 'fc_programa':
			$title = get_post_meta( $post_id, 'fc_programa_titulo', true );
			$id    = (int) get_post_meta( $post_id, 'fc_programa_id', true );
			if ( $id && get_post_status( $id ) ) {
				echo '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $title ?: $id ) . '</a>';
			} else {
				echo esc_html( $title ?: ( $id ? '#' . $id : '' ) );
			}
			break;
		case 'fc_fecha':
			$fecha = get_post_meta( $post_id, 'fc_fecha', true );
			$hora  = get_post_meta( $post_id, 'fc_hora', true );
			echo esc_html( trim( $fecha . ( $hora ? ' · ' . $hora : '' ) ) );
			break;
		case 'fc_meta_hidden':
			$browser = get_post_meta( $post_id, 'fc_navegador', true );
			$os      = get_post_meta( $post_id, 'fc_sistema_operativo', true );
			echo '<span class="fc-full-content" style="display:none;">' . esc_html( get_post_field( 'post_content', $post_id ) ) . '</span>';
			echo '<span class="fc-hidden-browser" style="display:none;">' . esc_html( $browser ) . '</span>';
			echo '<span class="fc-hidden-os" style="display:none;">' . esc_html( $os ) . '</span>';
			$control = get_post_meta( $post_id, 'fc_control_number', true );
			echo '<span class="fc-hidden-control" style="display:none;">' . esc_html( $control ) . '</span>';
			break;
	}
}
add_action( 'manage_fc_consulta_posts_custom_column', 'fc_consulta_columns_content', 10, 2 );

/**
 * Columnas para Solicitudes de Información.
 */
function fc_info_columns( $columns ) {
    $new = [];
    $new['cb']           = $columns['cb'];
    $new['title']        = __( 'Solicitud', 'flacso-flacso-formulario-consultas' );
    $new['fc_nombre']    = __( 'Nombre', 'flacso-flacso-formulario-consultas' );
    $new['fc_email']     = __( 'Email', 'flacso-flacso-formulario-consultas' );
    $new['fc_programa']  = __( 'Oferta consultada', 'flacso-flacso-formulario-consultas' );
    $new['fc_fecha']     = __( 'Fecha envío', 'flacso-flacso-formulario-consultas' );
    $new['date']         = $columns['date'];
    return $new;
}
add_filter( 'manage_fc_info_request_posts_columns', 'fc_info_columns' );

function fc_info_columns_content( $column, $post_id ) {
    switch ( $column ) {
        case 'fc_nombre':
            $nombre = trim( get_post_meta( $post_id, 'fc_nombre', true ) . ' ' . get_post_meta( $post_id, 'fc_apellido', true ) );
            echo esc_html( $nombre );
            break;
        case 'fc_email':
            $email = get_post_meta( $post_id, 'fc_email', true );
            if ( $email ) {
                echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
            }
            break;
        case 'fc_programa':
            $title = get_post_meta( $post_id, 'fc_programa_titulo', true );
            $id    = (int) get_post_meta( $post_id, 'fc_programa_id', true );
            if ( $id && get_post_status( $id ) ) {
                echo '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $title ?: $id ) . '</a>';
            } else {
                echo esc_html( $title ?: ( $id ? '#' . $id : '' ) );
            }
            break;
        case 'fc_fecha':
            $fecha = get_post_meta( $post_id, 'fc_fecha', true );
            $hora  = get_post_meta( $post_id, 'fc_hora', true );
            echo esc_html( trim( $fecha . ( $hora ? ' · ' . $hora : '' ) ) );
            break;
    }
}
add_action( 'manage_fc_info_request_posts_custom_column', 'fc_info_columns_content', 10, 2 );

/**
 * Metabox con detalles de la consulta
 */
function fc_consulta_add_metabox() {
    add_meta_box(
        'fc_consulta_detalles',
        __( 'Detalles de la consulta', 'flacso-flacso-formulario-consultas' ),
        'fc_consulta_metabox_render',
        'fc_consulta',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'fc_consulta_add_metabox' );

/**
 * Metabox para Solicitudes de Información.
 */
function fc_info_add_metabox() {
    add_meta_box(
        'fc_info_detalles',
        __( 'Detalles de la solicitud de información', 'flacso-flacso-formulario-consultas' ),
        'fc_info_metabox_render',
        'fc_info_request',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'fc_info_add_metabox' );

function fc_info_metabox_render( $post ) {
    $nombre   = get_post_meta( $post->ID, 'fc_nombre', true );
    $apellido = get_post_meta( $post->ID, 'fc_apellido', true );
    $email    = get_post_meta( $post->ID, 'fc_email', true );
    $pais     = get_post_meta( $post->ID, 'fc_pais', true );
    $nivel    = get_post_meta( $post->ID, 'fc_nivel_academico', true );
    $prof     = get_post_meta( $post->ID, 'fc_profesion', true );
    $programa = get_post_meta( $post->ID, 'fc_programa_titulo', true );
    $programa_id = (int) get_post_meta( $post->ID, 'fc_programa_id', true );
    $ip       = get_post_meta( $post->ID, 'fc_ip', true );
    $ua       = get_post_meta( $post->ID, 'fc_user_agent', true );
    $fecha    = get_post_meta( $post->ID, 'fc_fecha', true );
    $hora     = get_post_meta( $post->ID, 'fc_hora', true );
    $control  = get_post_meta( $post->ID, 'fc_control_number', true );
    echo '<table class="widefat striped"><tbody>';
    echo '<tr><th style="width:200px">' . esc_html__( 'Nombre', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( trim( $nombre . ' ' . $apellido ) ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Email', 'flacso-flacso-formulario-consultas' ) . '</th><td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td></tr>';
    echo '<tr><th>' . esc_html__( 'País', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $pais ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Nivel educativo', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $nivel ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Profesión', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $prof ) . '</td></tr>';
    if ( $programa || $programa_id ) {
        $label = esc_html( $programa ?: ( $programa_id ? '#' . $programa_id : '' ) );
        $link  = $programa_id && get_post_status( $programa_id ) ? '<a href="' . esc_url( get_permalink( $programa_id ) ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>' : $label;
        echo '<tr><th>' . esc_html__( 'Oferta consultada', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . $link . '</td></tr>';
    }
    if ( $fecha || $hora ) {
        echo '<tr><th>' . esc_html__( 'Enviado', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( trim( $fecha . ( $hora ? ' · ' . $hora : '' ) ) ) . '</td></tr>';
    }
    if ( $control ) {
        echo '<tr><th>' . esc_html__( 'Número de control', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $control ) . '</td></tr>';
    }
    echo '<tr><th>' . esc_html__( 'IP', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $ip ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'User Agent', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $ua ) . '</td></tr>';
    echo '</tbody></table>';
}
function fc_consulta_metabox_render( $post ) {
    $asunto   = get_the_title( $post );
    $nombre   = get_post_meta( $post->ID, 'fc_nombre', true );
    $apellido = get_post_meta( $post->ID, 'fc_apellido', true );
    $email    = get_post_meta( $post->ID, 'fc_email', true );
    $telefono = get_post_meta( $post->ID, 'fc_telefono', true );
    $programa = get_post_meta( $post->ID, 'fc_programa_titulo', true );
    $programa_id = (int) get_post_meta( $post->ID, 'fc_programa_id', true );
    $ip       = get_post_meta( $post->ID, 'fc_ip', true );
    $ua       = get_post_meta( $post->ID, 'fc_user_agent', true );
    $fecha    = get_post_meta( $post->ID, 'fc_fecha', true );
    $hora     = get_post_meta( $post->ID, 'fc_hora', true );
    echo '<table class="widefat striped">';
    echo '<tbody>';
    echo '<tr><th style="width:200px">' . esc_html__( 'Asunto', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $asunto ) . '</td></tr>';
    echo '<tr><th style="width:200px">' . esc_html__( 'Nombre', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $nombre ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Apellido', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $apellido ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'Email', 'flacso-flacso-formulario-consultas' ) . '</th><td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td></tr>';
    echo '<tr><th>' . esc_html__( 'Teléfono', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $telefono ) . '</td></tr>';
    if ( $programa || $programa_id ) {
        $label = esc_html( $programa ?: ( $programa_id ? '#' . $programa_id : '' ) );
        $link  = $programa_id && get_post_status( $programa_id ) ? '<a href="' . esc_url( get_permalink( $programa_id ) ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>' : $label;
        echo '<tr><th>' . esc_html__( 'Oferta consultada', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . $link . '</td></tr>';
    }
    if ( $fecha || $hora ) {
        echo '<tr><th>' . esc_html__( 'Enviado', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( trim( $fecha . ( $hora ? ' · ' . $hora : '' ) ) ) . '</td></tr>';
    }
		$control = get_post_meta( $post->ID, 'fc_control_number', true );
		if ( $control ) {
			echo '<tr><th>' . esc_html__( 'Número de control', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $control ) . '</td></tr>';
		}
    echo '<tr><th>' . esc_html__( 'IP', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $ip ) . '</td></tr>';
    echo '<tr><th>' . esc_html__( 'User Agent', 'flacso-flacso-formulario-consultas' ) . '</th><td>' . esc_html( $ua ) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
}

/**
 * Placeholder del título adaptado a "Asunto" en el editor
 */
function fc_consulta_enter_title_here( $title, $post ) {
    if ( 'fc_consulta' === $post->post_type ) {
        $title = __( 'Asunto', 'flacso-flacso-formulario-consultas' );
    }
    return $title;
}
add_filter( 'enter_title_here', 'fc_consulta_enter_title_here', 10, 2 );

/**
 * Filtro por email en lista de consultas.
 */
function fc_consulta_filter_by_email() {
	if ( empty( $_GET['post_type'] ) || 'fc_consulta' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$current = isset( $_GET['fc_email_filter'] ) ? sanitize_email( wp_unslash( $_GET['fc_email_filter'] ) ) : '';
	?>
	<label for="fc-email-filter" class="screen-reader-text"><?php esc_html_e( 'Filtrar por correo', 'flacso-flacso-formulario-consultas' ); ?></label>
	<input type="search" id="fc-email-filter" name="fc_email_filter" value="<?php echo esc_attr( $current ); ?>" placeholder="<?php esc_attr_e( 'Correo electrónico', 'flacso-flacso-formulario-consultas' ); ?>" />
	<?php
}
add_action( 'restrict_manage_posts', 'fc_consulta_filter_by_email' );

function fc_consulta_pre_get_posts( $query ) {
	if ( is_admin() && $query->is_main_query() && isset( $_GET['post_type'] ) && 'fc_consulta' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email = isset( $_GET['fc_email_filter'] ) ? sanitize_email( wp_unslash( $_GET['fc_email_filter'] ) ) : '';
		if ( $email ) {
			$query->set(
				'meta_query',
				[
					[
						'key'     => 'fc_email',
						'value'   => $email,
						'compare' => 'LIKE',
					],
				]
			);
		}

		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'fc_consulta_pre_get_posts' );

/**
 * Vista en tarjetas para la lista de consultas en el admin.
 */
function fc_consulta_admin_cards_styles() {
	if ( empty( $_GET['post_type'] ) || 'fc_consulta' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	?>
	<style>
		.fc-cards-container {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
			gap: 16px;
			margin-top: 16px;
		}
		.fc-card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 6px;
			padding: 12px 14px;
			box-shadow: 0 1px 2px rgba(0,0,0,0.04);
		}
		.fc-card h3 {
			margin: 0 0 8px;
			font-size: 16px;
			line-height: 1.3;
		}
		.fc-card .fc-message {
			margin: 10px 0;
			font-size: 13px;
			color: #1d2327;
		}
		.fc-card .fc-actions {
			margin-top: 8px;
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}
		.fc-card .fc-actions a.button-link-delete {
			color: #b32d2e;
		}
		.fc-card .fc-meta {
			margin: 0;
			padding: 0;
			list-style: none;
			color: #50575e;
			font-size: 13px;
			line-height: 1.45;
		}
		.fc-card .fc-meta li + li {
			margin-top: 4px;
		}
		.fc-card .fc-badge {
			display: inline-block;
			padding: 2px 6px;
			background: #f0f6ff;
			color: #1d4ed8;
			border: 1px solid #dbeafe;
			border-radius: 4px;
			font-size: 12px;
			margin-right: 6px;
		}
		.fc-card a {
			text-decoration: none;
		}
		.fc-hidden-table {
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'admin_head-edit.php', 'fc_consulta_admin_cards_styles' );

function fc_consulta_admin_cards_script() {
	if ( empty( $_GET['post_type'] ) || 'fc_consulta' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	?>
	<script>
	( function() {
		const table = document.querySelector('.wp-list-table.posts');
		if ( ! table ) { return; }
		const tbody = table.querySelector('tbody');
		if ( ! tbody ) { return; }

		const rows = Array.from( tbody.querySelectorAll('tr') );
		const container = document.createElement('div');
		container.className = 'fc-cards-container';

		rows.forEach( ( row ) => {
			const titleLink = row.querySelector('.row-title');
			const nombre = row.querySelector('.column-fc_nombre');
			const apellido = row.querySelector('.column-fc_apellido');
			const email = row.querySelector('.column-fc_email');
			const telefono = row.querySelector('.column-fc_telefono');
			const date = row.querySelector('.column-date');
			const content = row.querySelector('.fc-full-content');
			const browser = row.querySelector('.fc-hidden-browser')?.textContent || '';
			const os = row.querySelector('.fc-hidden-os')?.textContent || '';
			const control = row.querySelector('.fc-hidden-control')?.textContent || '';

			const card = document.createElement('div');
			card.className = 'fc-card';

			const h3 = document.createElement('h3');
			if ( titleLink ) {
				const a = titleLink.cloneNode(true);
				a.classList.remove('row-title');
				h3.appendChild(a);
			} else {
				h3.textContent = row.querySelector('strong')?.textContent || '';
			}
			card.appendChild(h3);

			const meta = document.createElement('ul');
			meta.className = 'fc-meta';

			const addMeta = ( label, value ) => {
				if ( ! value ) { return; }
				const li = document.createElement('li');
				li.innerHTML = '<strong>' + label + ':</strong> ' + value;
				meta.appendChild(li);
			};

			addMeta( '<?php echo esc_js( __( 'Nombre', 'flacso-flacso-formulario-consultas' ) ); ?>', nombre?.textContent || '' );
			addMeta( '<?php echo esc_js( __( 'Apellido', 'flacso-flacso-formulario-consultas' ) ); ?>', apellido?.textContent || '' );
			addMeta( '<?php echo esc_js( __( 'Email', 'flacso-flacso-formulario-consultas' ) ); ?>', email?.textContent || '' );
			addMeta( '<?php echo esc_js( __( 'Teléfono', 'flacso-flacso-formulario-consultas' ) ); ?>', telefono?.textContent || '' );
			addMeta( '<?php echo esc_js( __( 'Fecha', 'flacso-flacso-formulario-consultas' ) ); ?>', date?.textContent?.trim() || '' );
			addMeta( '<?php echo esc_js( __( 'Navegador', 'flacso-flacso-formulario-consultas' ) ); ?>', browser );
			addMeta( '<?php echo esc_js( __( 'Sistema operativo', 'flacso-flacso-formulario-consultas' ) ); ?>', os );
			addMeta( '<?php echo esc_js( __( 'Número de control', 'flacso-flacso-formulario-consultas' ) ); ?>', control );

			if ( content ) {
				const msg = document.createElement('div');
				msg.className = 'fc-message';
				msg.textContent = content.textContent.trim();
				card.appendChild(msg);
			}

			const actions = row.querySelector('.row-actions');
			if ( actions ) {
				const actionsDiv = document.createElement('div');
				actionsDiv.className = 'fc-actions';
				actionsDiv.innerHTML = actions.innerHTML;
				card.appendChild(actionsDiv);
			}

			card.appendChild(meta);
			container.appendChild(card);
		} );

		table.parentNode.insertBefore( container, table );
		table.classList.add('fc-hidden-table');
	} )();
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'fc_consulta_admin_cards_script' );

/**
 * Añade una página de administración personalizada para ver y eliminar consultas.
 */
function fc_add_consultas_overview_submenu() {
	$parent = 'edit.php?post_type=fc_consulta';
	add_submenu_page(
		$parent,
		__( 'Ver Consultas', 'flacso-flacso-formulario-consultas' ),
		__( 'Ver Consultas', 'flacso-flacso-formulario-consultas' ),
		'edit_posts',
		'fc_consultas_overview',
		'fc_render_consultas_overview'
	);
}
add_action( 'admin_menu', 'fc_add_consultas_overview_submenu' );

/**
 * Submenú y vista para solicitudes de información (overview rápida).
 */
function fc_add_info_overview_submenu() {
	$parent = 'edit.php?post_type=fc_info_request';
	add_submenu_page(
		$parent,
		__( 'Ver Solicitudes', 'flacso-flacso-formulario-consultas' ),
		__( 'Ver Solicitudes', 'flacso-flacso-formulario-consultas' ),
		'edit_posts',
		'fc_info_overview',
		'fc_render_info_overview'
	);
}
add_action( 'admin_menu', 'fc_add_info_overview_submenu' );

function fc_render_info_overview() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes para ver esta página.', 'flacso-flacso-formulario-consultas' ) );
	}

	$per_page = 200;
	$args = [
		'post_type'      => 'fc_info_request',
		'posts_per_page' => $per_page,
		'orderby'        => 'date',
		'order'          => 'DESC',
	];
	$posts = get_posts( $args );
	$total = wp_count_posts( 'fc_info_request' );
	$total_count = isset( $total->publish ) ? (int) $total->publish : 0;

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Solicitudes de Información', 'flacso-flacso-formulario-consultas' ); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fc_info_request' ) ); ?>"><?php esc_html_e( 'Ver todas las solicitudes', 'flacso-flacso-formulario-consultas' ); ?></a></p>
		<p class="description"><?php echo esc_html( sprintf( __( 'Mostrando las últimas %1$d solicitudes. Total publicadas: %2$d.', 'flacso-flacso-formulario-consultas' ), count( $posts ), $total_count ) ); ?></p>
		<?php if ( empty( $posts ) ) : ?>
			<p><?php esc_html_e( 'No se encontraron solicitudes.', 'flacso-flacso-formulario-consultas' ); ?></p>
		<?php else : ?>
			<div class="fc-consultas-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;margin-top:16px;">
				<?php foreach ( $posts as $p ) :
					$nombre    = get_post_meta( $p->ID, 'fc_nombre', true );
					$apellido  = get_post_meta( $p->ID, 'fc_apellido', true );
					$email     = get_post_meta( $p->ID, 'fc_email', true );
					$pais      = get_post_meta( $p->ID, 'fc_pais', true );
					$nivel     = get_post_meta( $p->ID, 'fc_nivel_academico', true );
					$programa  = get_post_meta( $p->ID, 'fc_programa_titulo', true );
					$programa_id = (int) get_post_meta( $p->ID, 'fc_programa_id', true );
					$date      = get_post_meta( $p->ID, 'fc_fecha', true ) ?: get_the_date( '', $p );
					?>
					<div class="fc-card" style="background:#fff;border:1px solid #e1e5e8;border-radius:6px;padding:12px;">
						<h3 style="margin:0 0 8px;font-size:1.05rem;"><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
						<div style="font-size:13px;color:#5b6268;margin-bottom:8px;">
							<strong><?php esc_html_e( 'Nombre', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( trim( $nombre . ' ' . $apellido ) ); ?><br>
							<strong><?php esc_html_e( 'Email', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><br>
							<strong><?php esc_html_e( 'País', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( $pais ); ?><br>
							<strong><?php esc_html_e( 'Nivel', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( $nivel ); ?><br>
							<strong><?php esc_html_e( 'Oferta', 'flacso-flacso-formulario-consultas' ); ?>:</strong>
							<?php
							$label = $programa ?: ( $programa_id ? '#' . $programa_id : '' );
							if ( $programa_id && get_post_status( $programa_id ) ) {
								echo '<a href="' . esc_url( get_permalink( $programa_id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
							} else {
								echo esc_html( $label );
							}
							?><br>
							<strong><?php esc_html_e( 'Fecha', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( $date ); ?>
						</div>
						<div class="fc-actions" style="display:flex;gap:8px;align-items:center;">
							<a class="button button-secondary" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( 'Abrir', 'flacso-flacso-formulario-consultas' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $total_count > $per_page ) : ?>
				<p style="margin-top:12px;color:#6b7280;"><?php echo esc_html( sprintf( __( 'Hay %d solicitudes adicionales. Usa la lista completa para verlas todas.', 'flacso-flacso-formulario-consultas' ), $total_count - $per_page ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renderiza la página personalizada con lista cronológica de consultas y botones para eliminar.
 */
function fc_render_consultas_overview() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes para ver esta página.', 'flacso-flacso-formulario-consultas' ) );
	}

	$per_page = 200; // límite para evitar sobrecarga; si hay más indicarlo
	$args = [
		'post_type' => 'fc_consulta',
		'posts_per_page' => $per_page,
		'orderby' => 'date',
		'order' => 'DESC',
	];
	$posts = get_posts( $args );
	$total = wp_count_posts( 'fc_consulta' );
	$total_count = isset( $total->publish ) ? (int) $total->publish : 0;

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Consultas recibidas', 'flacso-flacso-formulario-consultas' ); ?></h1>
		<p class="description"><?php echo esc_html( sprintf( __( 'Mostrando las últimas %1$d consultas. Total publicadas: %2$d. Usa la lista completa si necesitas más acciones.', 'flacso-flacso-formulario-consultas' ), count( $posts ), $total_count ) ); ?></p>
		<?php if ( empty( $posts ) ) : ?>
			<p><?php esc_html_e( 'No se encontraron consultas.', 'flacso-flacso-formulario-consultas' ); ?></p>
		<?php else : ?>
			<div class="fc-consultas-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;margin-top:16px;">
				<?php foreach ( $posts as $p ) :
					$nombre = get_post_meta( $p->ID, 'fc_nombre', true );
					$apellido = get_post_meta( $p->ID, 'fc_apellido', true );
					$email = get_post_meta( $p->ID, 'fc_email', true );
					$telefono = get_post_meta( $p->ID, 'fc_telefono', true );
					$date = get_the_date( '', $p );
					$excerpt = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $p->ID ) ), 30 );
					?>
					<div class="fc-card" style="background:#fff;border:1px solid #e1e5e8;border-radius:6px;padding:12px;">
						<h3 style="margin:0 0 8px;font-size:1.05rem;"><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
						<div style="font-size:13px;color:#5b6268;margin-bottom:8px;">
							<strong><?php esc_html_e( 'De', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( trim( $nombre . ' ' . $apellido ) ); ?>
							<br>
							<strong><?php esc_html_e( 'Email', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							<br>
							<strong><?php esc_html_e( 'Teléfono', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( $telefono ); ?>
							<br>
							<strong><?php esc_html_e( 'Fecha', 'flacso-flacso-formulario-consultas' ); ?>:</strong> <?php echo esc_html( $date ); ?>
						</div>
						<div style="margin-bottom:10px;color:#1f2937;"><?php echo esc_html( $excerpt ); ?></div>
						<div class="fc-actions" style="display:flex;gap:8px;align-items:center;">
							<a class="button button-secondary" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( 'Abrir', 'flacso-flacso-formulario-consultas' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Confirmas eliminar esta consulta? Esta acción moverá la consulta a la papelera.', 'flacso-flacso-formulario-consultas' ) ); ?>');" style="display:inline;">
								<?php wp_nonce_field( 'fc_delete_consulta_' . $p->ID, 'fc_delete_consulta_nonce' ); ?>
								<input type="hidden" name="action" value="fc_delete_consulta" />
								<input type="hidden" name="post_id" value="<?php echo esc_attr( $p->ID ); ?>" />
								<button type="submit" class="button button-danger" style="color:#fff;background:#b32d2e;border-color:#b32d2e;"><?php esc_html_e( 'Eliminar', 'flacso-flacso-formulario-consultas' ); ?></button>
							</form>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $total_count > $per_page ) : ?>
				<p style="margin-top:12px;color:#6b7280;"><?php echo esc_html( sprintf( __( 'Hay %d consultas adicionales. Usa la lista completa de Consultas para verlas todas.', 'flacso-flacso-formulario-consultas' ), $total_count - $per_page ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}


/**
 * Manejador para eliminar (mover a papelera) una consulta desde la página personalizada.
 */
function fc_handle_delete_consulta() {
	if ( ! isset( $_POST['post_id'] ) ) {
		wp_die( esc_html__( 'Falta el ID de la consulta.', 'flacso-flacso-formulario-consultas' ) );
	}
	$post_id = (int) $_POST['post_id'];
	if ( ! wp_verify_nonce( wp_unslash( $_POST['fc_delete_consulta_nonce'] ?? '' ), 'fc_delete_consulta_' . $post_id ) ) {
		wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
	}
	if ( ! current_user_can( 'delete_post', $post_id ) ) {
		wp_die( esc_html__( 'No tienes permisos para eliminar esta consulta.', 'flacso-flacso-formulario-consultas' ) );
	}

	$res = wp_trash_post( $post_id );

	$referer = wp_get_referer() ?: admin_url( 'edit.php?post_type=fc_consulta&page=fc_consultas_overview' );
	$redirect = add_query_arg( 'fc_deleted', $res ? '1' : '0', $referer );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_fc_delete_consulta', 'fc_handle_delete_consulta' );
