<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FLACSO_Consultas_Admin' ) ) {
	/**
	 * Plataforma integral de Consultas e Inteligencia Analítica en wp-admin (page=flacso-consultas).
	 * Porta las 6 secciones del Editor (Histórico, Resumen por Oferta, Oferta y País,
	 * Comparación de Períodos, Campañas y Exportación CSV) conectadas a PostgreSQL.
	 */
	class FLACSO_Consultas_Admin {

		const PAGE_SLUG    = 'flacso-consultas';
		const PAGE_BANDEJA = 'flacso-consultas';
		const PAGE_RESUMEN = 'flacso-consultas-resumen';
		const PAGE_OFERTA_PAIS = 'flacso-consultas-oferta-pais';
		const PAGE_COMPARACION = 'flacso-consultas-comparacion';
		const PAGE_CAMPANAS = 'flacso-consultas-campanas';
		const PAGE_EXPORTAR = 'flacso-consultas-exportar';
		const NONCE_ACTION = 'flacso_consultas_admin_nonce';

		private static function can_view(): bool {
			return current_user_can( 'manage_options' ) || current_user_can( FLACSO_Academic_Assistant::VIEW_INQUIRIES );
		}

		private static function can_manage(): bool {
			return current_user_can( 'manage_options' ) || current_user_can( FLACSO_Academic_Assistant::MANAGE_INQUIRIES );
		}

		public static function init(): void {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 12 );
			add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_tab' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'wp_ajax_flacso_consultas_detail', array( __CLASS__, 'ajax_get_detail' ) );
			add_action( 'wp_ajax_flacso_consultas_retry_email', array( __CLASS__, 'ajax_retry_email' ) );
			add_action( 'wp_ajax_flacso_consultas_retry_mautic', array( __CLASS__, 'ajax_retry_mautic' ) );
			add_action( 'wp_ajax_flacso_consultas_trigger_followup', array( __CLASS__, 'ajax_trigger_followup' ) );
			add_action( 'wp_ajax_flacso_consultas_toggle_campaign', array( __CLASS__, 'ajax_toggle_campaign' ) );
			add_action( 'admin_post_flacso_consultas_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		}

		/**
		 * Un reenvío manual sólo es seguro cuando el envío anterior falló.
		 * Los estados aceptado y omitido no prueban entrega final.
		 * el correo, por lo que reenviarlos podría duplicarlo.
		 */
		public static function is_retryable_email_status( string $status ): bool {
			return in_array( strtolower( trim( $status ) ), array( 'failed', 'blocked' ), true );
		}

		public static function register_menu(): void {
			$parent_slug = class_exists( 'FLACSO_Admin_Panel' ) ? FLACSO_Admin_Panel::PAGE_SLUG : 'flacso-panel';

			$pages = array(
				self::PAGE_BANDEJA => array( __( 'Consultas', 'flacso-uruguay' ), 'render_page' ),
				self::PAGE_RESUMEN => array( __( 'Resumen por oferta', 'flacso-uruguay' ), 'render_resumen_page' ),
				self::PAGE_OFERTA_PAIS => array( __( 'Oferta y país', 'flacso-uruguay' ), 'render_oferta_pais_page' ),
				self::PAGE_COMPARACION => array( __( 'Comparación de períodos', 'flacso-uruguay' ), 'render_comparacion_page' ),
				self::PAGE_CAMPANAS => array( __( 'Campañas', 'flacso-uruguay' ), 'render_campanas_page' ),
				self::PAGE_EXPORTAR => array( __( 'Exportar CSV', 'flacso-uruguay' ), 'render_exportar_page' ),
			);
			foreach ( $pages as $slug => $page ) {
				add_submenu_page( $parent_slug, $page[0], $page[0], FLACSO_Academic_Assistant::VIEW_INQUIRIES, $slug, array( __CLASS__, $page[1] ) );
			}
		}

		public static function enqueue_admin_assets( string $hook ): void {
			if ( false === strpos( $hook, 'flacso-consultas' ) ) { return; }
			$path = FLACSO_CONSULTAS_MODULE_PATH . 'assets/css/consultas-admin.css';
			wp_enqueue_style( 'flacso-consultas-admin', plugins_url( 'assets/css/consultas-admin.css', dirname( __FILE__ ) ), array(), file_exists( $path ) ? (string) filemtime( $path ) : false );
		}

		private static function render_page_header( string $title, string $description, array $actions = array() ): void {
			echo '<header class="flacso-consultas-page__header"><div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></div>';
			if ( ! empty( $actions ) ) { echo '<div>' . implode( '', $actions ) . '</div>'; }
			echo '</header>';
		}

		private static function render_operational_notice(): void { call_user_func( array( __CLASS__, 'render_transactional_diagnostics' ) ); }

		public static function redirect_legacy_tab(): void {
			if ( ! is_admin() || ! isset( $_GET['page'], $_GET['tab'] ) || self::PAGE_BANDEJA !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
			$map = array( 'historico' => self::PAGE_BANDEJA, 'oferta' => self::PAGE_RESUMEN, 'oferta-pais' => self::PAGE_OFERTA_PAIS, 'comparacion' => self::PAGE_COMPARACION, 'campanas' => self::PAGE_CAMPANAS, 'exportar' => self::PAGE_EXPORTAR );
			$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			$target = $map[ $tab ] ?? self::PAGE_BANDEJA;
			if ( self::PAGE_EXPORTAR === $target && ! self::can_manage() ) { $target = self::PAGE_BANDEJA; }
			$args = array( 'page' => $target );
			foreach ( array( 'table', 'from', 'to', 'country', 'item_name', 'email_status', 'offer_status', 'q', 'mode', 'paged' ) as $key ) { if ( isset( $_GET[ $key ] ) ) { $args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); } }
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
		}

		/**
		 * Normaliza el nombre de tabla permitido.
		 */
		private static function resolve_table( string $raw ): string {
			if ( 'seminar' === $raw || 'seminar_inquiries' === $raw ) {
				return 'seminar_inquiries';
			}
			if ( 'general' === $raw || 'general_inquiries' === $raw ) {
				return 'general_inquiries';
			}
			return 'offer_inquiries';
		}

		/**
		 * Descarga directa de archivo CSV con BOM UTF-8 compatible con Excel y Google Sheets.
		 */
		public static function handle_export_csv(): void {
			if ( ! self::can_manage() ) {
				wp_die( esc_html__( 'No tienes permisos para exportar consultas.', 'flacso-uruguay' ) );
			}

			check_admin_referer( 'flacso_consultas_export_csv' );

			$table = isset( $_GET['table'] ) ? self::resolve_table( sanitize_key( wp_unslash( $_GET['table'] ) ) ) : 'offer_inquiries';
			$from  = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d', strtotime( '-90 days' ) );
			$to    = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d' );
			$mode  = isset( $_GET['export_mode'] ) && 'raw' === $_GET['export_mode'] ? 'raw' : 'dedup';

			$selected_items = array();
			if ( isset( $_GET['items'] ) && is_array( $_GET['items'] ) ) {
				foreach ( wp_unslash( $_GET['items'] ) as $item ) {
					$clean = trim( sanitize_text_field( (string) $item ) );
					if ( '' !== $clean ) {
						$selected_items[] = $clean;
					}
				}
			}

			$rows     = FLACSO_Inquiry_Analytics_Repository::get_export_rows( $table, $from, $to, $selected_items, 'dedup' === $mode );
			$item_col = FLACSO_Inquiry_Analytics_Repository::ALLOWED_TABLES[ $table ]['item_col'] ?? 'offerName';
			$filename = sprintf( 'flacso-consultas-%s-%s-a-%s-%s.csv', $table, $from, $to, $mode );

			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

			$out = fopen( 'php://output', 'w' );
			if ( false === $out ) {
				exit;
			}

			// UTF-8 BOM para Excel
			fwrite( $out, "\xEF\xBB\xBF" );

			fputcsv(
				$out,
				array(
					'Nombre',
					'Apellido',
					'Nombre Completo',
					'Correo',
					'Oferta / Seminario',
					'Tabla',
					'País',
					'Teléfono',
					'Estado Email',
					'Campaña / Fuente',
					'Fecha Consulta',
				)
			);

			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array(
						(string) ( $row['firstName'] ?? '' ),
						(string) ( $row['lastName'] ?? '' ),
						(string) ( $row['fullName'] ?? '' ),
						(string) ( $row['email'] ?? '' ),
						(string) ( $row[ $item_col ] ?? '' ),
						$table,
						(string) ( $row['country'] ?? '' ),
						(string) ( $row['phone'] ?? '' ),
						(string) ( $row['emailStatus'] ?? '' ),
						(string) ( $row['campaignName'] ?? $row['campaignSource'] ?? '' ),
						(string) ( $row['inquiryAt'] ?? $row['createdAt'] ?? '' ),
					)
				);
			}

			fclose( $out );
			exit;
		}

		/**
		 * AJAX: Detalle completo de una consulta.
		 */
		public static function ajax_get_detail(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! self::can_view() ) {
				wp_send_json_error( array( 'message' => 'No autorizado' ), 403 );
			}

			$table = isset( $_POST['table'] ) ? self::resolve_table( sanitize_key( wp_unslash( $_POST['table'] ) ) ) : 'offer_inquiries';
			$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

			if ( '' === $id ) {
				wp_send_json_error( array( 'message' => 'ID inválido' ), 400 );
			}

			$detail = FLACSO_Inquiry_Analytics_Repository::get_inquiry_detail( $table, $id );

			if ( ! $detail ) {
				wp_send_json_error( array( 'message' => 'Consulta no encontrada en PostgreSQL.' ), 404 );
			}

			$item_col            = FLACSO_Inquiry_Analytics_Repository::ALLOWED_TABLES[ $table ]['item_col'] ?? 'offerName';
			$detail['item_name'] = $detail[ $item_col ] ?? '';
			$detail['table']     = $table;

			wp_send_json_success( $detail );
		}

		/**
		 * AJAX: Reintentar una entrega transaccional cuyo resultado fue
		 * comprobado como no enviado (failed o blocked).
		 */
		public static function ajax_retry_email(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! self::can_manage() ) {
				wp_send_json_error( array( 'message' => 'No autorizado' ), 403 );
			}

			$table = isset( $_POST['table'] ) ? self::resolve_table( sanitize_key( wp_unslash( $_POST['table'] ) ) ) : 'offer_inquiries';
			$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

			if ( ! in_array( $table, array( 'offer_inquiries', 'seminar_inquiries' ), true ) ) {
				wp_send_json_error( array( 'message' => 'Este tipo de consulta no tiene correo transaccional reenviable.' ), 422 );
			}
			if ( '' === $id ) {
				wp_send_json_error( array( 'message' => 'ID inválido.' ), 400 );
			}

			$detail = FLACSO_Inquiry_Analytics_Repository::get_inquiry_detail( $table, $id );
			if ( ! $detail ) {
				wp_send_json_error( array( 'message' => 'No se encontró la consulta solicitada.' ), 404 );
			}

			$email_status = strtolower( trim( (string) ( $detail['emailStatus'] ?? '' ) ) );
			if ( ! self::is_retryable_email_status( $email_status ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							'El estado "%s" no admite reenvío manual. Sólo se reintentan failed o blocked; acceptance_unknown requiere conciliación para evitar duplicados.',
							$email_status !== '' ? $email_status : 'sin estado'
						),
					),
					409
				);
			}

			$consulta_id = trim( (string) ( $detail['consultaId'] ?? '' ) );
			if ( '' === $consulta_id ) {
				wp_send_json_error( array( 'message' => 'El registro no tiene consultaId válido.' ), 422 );
			}

			if ( ! class_exists( 'FLACSO_Inquiry_Delivery_Repository' )
				|| ! class_exists( 'FLACSO_Inquiry_Delivery_Service' )
				|| ! class_exists( 'FLACSO_Mautic_Contract_Validator' ) ) {
				wp_send_json_error( array( 'message' => 'El servicio de correo transaccional no está disponible.' ), 500 );
			}

			try {
				$delivery_repository = new FLACSO_Inquiry_Delivery_Repository();
				$delivery = $delivery_repository->find_by_consulta_id( $consulta_id );
			} catch ( Throwable $e ) {
				wp_send_json_error( array( 'message' => 'No fue posible leer la entrega transaccional: ' . $e->getMessage() ), 500 );
			}

			if ( ! is_array( $delivery ) || empty( $delivery['id'] ) ) {
				wp_send_json_error( array( 'message' => 'La consulta no tiene una entrega transaccional asociada.' ), 404 );
			}

			$delivery_id = (string) $delivery['id'];
			$delivery_state = strtolower( trim( (string) ( $delivery['state'] ?? '' ) ) );
			if ( ! in_array( $delivery_state, array( 'failed', 'blocked' ), true ) ) {
				wp_send_json_error(
					array( 'message' => 'La entrega ya cambió de estado a "' . $delivery_state . '" y no se reenviará.' ),
					409
				);
			}

			if ( method_exists( $delivery_repository, 'refresh_template_identity' ) ) {
				$refreshed = $delivery_repository->refresh_template_identity( $delivery_id );
				if ( is_array( $refreshed ) ) {
					$delivery = $refreshed;
				}
			}

			try {
				$contract = FLACSO_Mautic_Contract_Validator::validate(
					null,
					(int) ( $delivery['templateId'] ?? 0 )
				);
			} catch ( Throwable $e ) {
				wp_send_json_error( array( 'message' => 'No fue posible validar Mautic: ' . $e->getMessage() ), 500 );
			}

			if ( empty( $contract['ok'] ) ) {
				$reason = method_exists( 'FLACSO_Inquiry_Delivery_Service', 'contract_failure_message' )
					? FLACSO_Inquiry_Delivery_Service::contract_failure_message( $contract )
					: 'El contrato del correo transaccional sigue bloqueado.';
				wp_send_json_error(
					array(
						'status' => 'blocked',
						'message' => 'No se reenvió nada. ' . $reason,
						'contract' => $contract,
					),
					409
				);
			}

			if ( ! method_exists( $delivery_repository, 'claim_manual_retry' )
				|| ! $delivery_repository->claim_manual_retry( $delivery_id, 120 ) ) {
				wp_send_json_error(
					array( 'message' => 'La entrega ya fue reclamada, procesada o modificada por otro proceso.' ),
					409
				);
			}

			FLACSO_Inquiry_Delivery_Service::set_repository( $delivery_repository );
			try {
				$result = FLACSO_Inquiry_Delivery_Service::process( $delivery_id );
			} catch ( Throwable $e ) {
				$result = array(
					'ok' => false,
					'status' => 'failed',
					'error' => $e->getMessage(),
				);
			} finally {
				FLACSO_Inquiry_Delivery_Service::set_repository( null );
			}

			$status = strtolower( trim( (string) ( $result['status'] ?? 'failed' ) ) );
			if ( ! empty( $result['ok'] ) && 'accepted' === $status ) {
				wp_send_json_success(
					array(
						'status' => 'accepted',
						'sender' => 'mautic_transactional_queue',
						'message' => 'Mautic aceptó el correo transaccional.',
					)
				);
			}

			$current = $delivery_repository->find_by_delivery_id( $delivery_id );
			$last_error = is_array( $current ) ? trim( (string) ( $current['lastError'] ?? '' ) ) : '';
			$message = $last_error !== '' ? $last_error : trim( (string) ( $result['error'] ?? '' ) );

			if ( 'acceptance_unknown' === $status ) {
				$message = 'No se pudo confirmar si Mautic aceptó el correo. No se reintentará automáticamente para evitar duplicados.';
			} elseif ( 'retryable_failed' === $status ) {
				$message = 'Mautic devolvió un fallo reintentable. La cola volverá a intentarlo automáticamente.';
			} elseif ( $message === '' ) {
				$message = 'Mautic no aceptó el correo transaccional.';
			}

			wp_send_json_error(
				array(
					'status' => $status,
					'message' => $message,
				),
				502
			);
		}

		/**
		 * AJAX: Reintentar manualmente la sincronización con Mautic para una consulta de oferta.
		 */
		public static function ajax_retry_mautic(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! self::can_manage() ) {
				wp_send_json_error( array( 'message' => 'No autorizado' ), 403 );
			}

			$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			if ( '' === $id ) {
				wp_send_json_error( array( 'message' => 'ID inválido' ), 400 );
			}

			if ( ! class_exists( 'FLACSO_Inquiry_Marketing_Service' ) ) {
				wp_send_json_error( array( 'status' => 'failed', 'message' => 'Servicio de marketing no disponible' ), 500 );
			}

			$res = FLACSO_Inquiry_Marketing_Service::sync_inquiry( $id );

			if ( ! empty( $res['ok'] ) ) {
				wp_send_json_success(
					array(
						'status'     => $res['status'] ?? 'synced',
						'contact_id' => $res['contact_id'] ?? null,
						'message'    => 'Sincronizado con éxito',
					)
				);
			}

			wp_send_json_error(
				array(
					'status'  => 'failed',
					'message' => $res['error'] ?? 'Error',
				),
				500
			);
		}

		/**
		 * Renderiza el badge HTML correspondiente al estado de sincronización con Mautic.
		 *
		 * @param array $row Fila de consulta con mauticSyncStatus, mauticContactId, mauticSyncedAt, mauticLastError.
		 * @return string HTML seguro del badge.
		 */
		public static function render_mautic_badge( array $row ): string {
			$status = strtolower( trim( (string) ( $row['mauticSyncStatus'] ?? 'pending' ) ) );
			if ( '' === $status ) {
				$status = 'pending';
			}

			if ( 'synced' === $status ) {
				$cid   = ! empty( $row['mauticContactId'] ) ? (string) $row['mauticContactId'] : '';
				$label = '' !== $cid ? sprintf( '🟣 ID %s', $cid ) : '🟣 ' . __( 'Sincronizado', 'flacso-uruguay' );
				$title = ! empty( $row['mauticSyncedAt'] )
					? sprintf( __( 'Sincronizado con Mautic: %s', 'flacso-uruguay' ), $row['mauticSyncedAt'] )
					: __( 'Sincronizado con Mautic', 'flacso-uruguay' );
				return '<span class="flacso-badge mautic-synced" title="' . esc_attr( $title ) . '">' . esc_html( $label ) . '</span>';
			}

			if ( 'failed' === $status ) {
				$err = ! empty( $row['mauticLastError'] ) ? (string) $row['mauticLastError'] : __( 'Error al sincronizar con Mautic', 'flacso-uruguay' );
				return '<span class="flacso-badge mautic-failed" title="' . esc_attr( $err ) . '">🔴 ' . esc_html__( 'Error', 'flacso-uruguay' ) . '</span>';
			}

			if ( 'skipped' === $status ) {
				return '<span class="flacso-badge mautic-skipped" title="' . esc_attr__( 'Sincronización omitida (Mautic inactivo)', 'flacso-uruguay' ) . '">⚪ ' . esc_html__( 'Omitido', 'flacso-uruguay' ) . '</span>';
			}

			return '<span class="flacso-badge mautic-pending" title="' . esc_attr__( 'Pendiente de sincronización con Mautic', 'flacso-uruguay' ) . '">🟡 ' . esc_html__( 'Pendiente', 'flacso-uruguay' ) . '</span>';
		}

		/**
		 * Renderiza el badge HTML correspondiente al estado y motor de envío del correo.
		 *
		 * @param array $row Fila de consulta con emailStatus y emailSender.
		 * @return string HTML seguro del badge.
		 */
		public static function render_email_status_badge( array $row ): string {
			$status = strtolower( trim( (string) ( $row['emailStatus'] ?? 'skipped' ) ) );
			if ( '' === $status ) {
				$status = 'skipped';
			}
			$sender = strtolower( trim( (string) ( $row['emailSender'] ?? '' ) ) );

			if ( 'pending' === $status ) {
				return '<span class="flacso-badge pending" title="' . esc_attr__( 'Acuse persistido y pendiente de procesamiento', 'flacso-uruguay' ) . '">pending</span>';
			}
			if ( 'accepted' === $status ) {
				return '<span class="flacso-badge sent mautic" title="' . esc_attr__( 'Mautic aceptó la solicitud de envío; no implica entrega ni lectura', 'flacso-uruguay' ) . '">accepted (Mautic)</span>';
			}
			if ( 'retryable_failed' === $status ) {
				return '<span class="flacso-badge pending" title="' . esc_attr__( 'Fallo comprobado antes de aceptación; la cola puede reintentar dentro del límite', 'flacso-uruguay' ) . '">retryable</span>';
			}
			if ( 'acceptance_unknown' === $status ) {
				return '<span class="flacso-badge failed" title="' . esc_attr__( 'No se pudo determinar si Mautic aceptó el envío. Requiere conciliación manual y no se reintenta automáticamente.', 'flacso-uruguay' ) . '">acceptance unknown</span>';
			}
			if ( 'blocked' === $status ) {
				return '<span class="flacso-badge failed" title="' . esc_attr__( 'Envío bloqueado por contrato o configuración incompatible', 'flacso-uruguay' ) . '">blocked</span>';
			}

			if ( 'sent' === $status ) {
				if ( 'mautic' === $sender ) {
					return '<span class="flacso-badge sent mautic" title="' . esc_attr__( 'Enviado vía Mautic', 'flacso-uruguay' ) . '">sent (Mautic)</span>';
				}
				if ( 'mailjet_fallback' === $sender ) {
					return '<span class="flacso-badge sent fallback" title="' . esc_attr__( 'Enviado vía Mailjet por conmutación (fallback Mautic)', 'flacso-uruguay' ) . '">sent (Fallback)</span>';
				}
				return '<span class="flacso-badge sent" title="' . esc_attr__( 'Enviado vía Mailjet', 'flacso-uruguay' ) . '">sent</span>';
			}

			if ( 'failed' === $status ) {
				if ( 'mailjet_fallback' === $sender ) {
					return '<span class="flacso-badge failed fallback" title="' . esc_attr__( 'Falló el envío vía Mailjet tras conmutación desde Mautic', 'flacso-uruguay' ) . '">failed (Fallback)</span>';
				}
				if ( 'mautic' === $sender ) {
					return '<span class="flacso-badge failed mautic" title="' . esc_attr__( 'Falló el envío vía Mautic', 'flacso-uruguay' ) . '">failed (Mautic)</span>';
				}
				return '<span class="flacso-badge failed" title="' . esc_attr__( 'Envío fallido', 'flacso-uruguay' ) . '">failed</span>';
			}

			if ( 'skipped' === $status ) {
				return '<span class="flacso-badge skipped" title="' . esc_attr__( 'Envío omitido', 'flacso-uruguay' ) . '">skipped</span>';
			}

			return '<span class="flacso-badge ' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
		}

		/**
		 * Renderiza el badge HTML correspondiente al estado del seguimiento automático (+X días).
		 *
		 * @param array $row Fila de consulta con followupStatus, followupDueAt, followupSentAt, followupLastError.
		 * @return string HTML seguro del badge o cadena vacía si no aplica.
		 */
		public static function render_followup_status_badge( array $row ): string {
			$status = strtolower( trim( (string) ( $row['followupStatus'] ?? 'none' ) ) );

			if ( 'pending' === $status ) {
				$due   = ! empty( $row['followupDueAt'] ) ? (string) $row['followupDueAt'] : '';
				$title = '' !== $due
					? sprintf( __( 'Vencimiento: %s', 'flacso-uruguay' ), $due )
					: __( 'Seguimiento pendiente', 'flacso-uruguay' );
				return '<span class="flacso-badge followup-pending" title="' . esc_attr( $title ) . '">⏰ ' . esc_html__( 'Seg. pend.', 'flacso-uruguay' ) . '</span>';
			}

			if ( 'sent' === $status ) {
				$sent  = ! empty( $row['followupSentAt'] ) ? (string) $row['followupSentAt'] : '';
				$title = '' !== $sent
					? sprintf( __( 'Seguimiento enviado: %s', 'flacso-uruguay' ), $sent )
					: __( 'Seguimiento enviado', 'flacso-uruguay' );
				return '<span class="flacso-badge followup-sent" title="' . esc_attr( $title ) . '">✅ ' . esc_html__( 'Seg. enviado', 'flacso-uruguay' ) . '</span>';
			}

			if ( 'skipped' === $status ) {
				$err   = ! empty( $row['followupLastError'] ) ? (string) $row['followupLastError'] : __( 'Seguimiento omitido', 'flacso-uruguay' );
				$title = sprintf( __( 'Omitido: %s', 'flacso-uruguay' ), $err );
				return '<span class="flacso-badge followup-skipped" title="' . esc_attr( $title ) . '">⏭ ' . esc_html__( 'Seg. omitido', 'flacso-uruguay' ) . '</span>';
			}

			if ( 'failed' === $status ) {
				$err   = ! empty( $row['followupLastError'] ) ? (string) $row['followupLastError'] : __( 'Error en seguimiento', 'flacso-uruguay' );
				$title = sprintf( __( 'Error: %s', 'flacso-uruguay' ), $err );
				return '<span class="flacso-badge followup-failed" title="' . esc_attr( $title ) . '">❌ ' . esc_html__( 'Seg. falló', 'flacso-uruguay' ) . '</span>';
			}

			if ( 'processing' === $status ) {
				return '<span class="flacso-badge followup-processing" title="' . esc_attr__( 'Seguimiento en procesamiento', 'flacso-uruguay' ) . '">⏳ ' . esc_html__( 'Seg. procesando', 'flacso-uruguay' ) . '</span>';
			}

			return '';
		}

		/**
		 * AJAX: Disparo manual interactivo del seguimiento automático (+X días).
		 */
		public static function ajax_trigger_followup(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! self::can_manage() ) {
				wp_send_json_error( array( 'message' => 'No autorizado' ), 403 );
			}

			$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			if ( '' === $id ) {
				wp_send_json_error( array( 'message' => 'ID inválido' ), 400 );
			}

			if ( ! class_exists( 'FLACSO_Inquiry_Followup_Service' ) ) {
				wp_send_json_error( array( 'status' => 'failed', 'message' => 'Servicio de seguimiento no disponible' ), 500 );
			}

			$repo    = new FLACSO_Offer_Inquiry_Repository();
			$inquiry = $repo->find_by_id( $id );
			if ( ! $inquiry ) {
				wp_send_json_error( array( 'message' => 'Consulta no encontrada en PostgreSQL.' ), 404 );
			}

			try {
				$res = FLACSO_Inquiry_Followup_Service::process_single_followup( $inquiry, $repo );
			} catch ( \Throwable $e ) {
				error_log( '[FLACSO Consultas] Error inesperado en trigger_followup: ' . $e->getMessage() );
				wp_send_json_error(
					array(
						'status'  => 'failed',
						'message' => 'Error al procesar seguimiento: ' . $e->getMessage(),
					),
					500
				);
				return;
			}

			if ( ! empty( $res['ok'] ) ) {
				wp_send_json_success(
					array(
						'status'  => $res['status'] ?? 'sent',
						'message' => ! empty( $res['reason'] ) ? $res['reason'] : 'Seguimiento ejecutado con éxito',
						'result'  => $res,
					)
				);
			}

			wp_send_json_error(
				array(
					'status'  => $res['status'] ?? 'failed',
					'message' => $res['error'] ?? 'Error al ejecutar seguimiento',
					'result'  => $res,
				),
				500
			);
		}

		/**
		 * AJAX: Ocultar o restaurar campaña en el análisis de atribución.
		 */
		public static function ajax_toggle_campaign(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
			if ( ! self::can_manage() ) {
				wp_send_json_error( array( 'message' => 'No autorizado' ), 403 );
			}

			$key     = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
			$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $key;
			$exclude = ! empty( $_POST['exclude'] );

			if ( '' === $key ) {
				wp_send_json_error( array( 'message' => 'Clave de campaña vacía' ), 400 );
			}

			FLACSO_Inquiry_Analytics_Repository::set_campaign_exclusion( $key, $name, $exclude );
			wp_send_json_success( array( 'key' => $key, 'name' => $name, 'excluded' => $exclude ) );
		}

		/**
		 * Diagnóstico operativo sin PII ni payloads.
		 */
		private static function render_transactional_diagnostics(): void {
			$queue_enabled = function_exists( 'get_option' )
				&& '1' === (string) get_option( 'flacso_inquiry_delivery_queue_enabled', '0' );

			$contract_status = 'unavailable';
			if ( class_exists( 'FLACSO_Mautic_Contract_Validator' ) ) {
				try {
					$validation      = FLACSO_Mautic_Contract_Validator::validate();
					$contract_status = ! empty( $validation['ok'] ) ? 'valid' : 'blocked';
				} catch ( Throwable $e ) {
					$contract_status = 'blocked';
				}
			}

			$counts         = array();
			$reconciliation = 0;
			try {
				$pdo  = FLACSO_DB::connection();
				$stmt = $pdo->query( 'SELECT state, COUNT(*) AS total FROM inquiry_deliveries GROUP BY state ORDER BY state' );
				if ( $stmt ) {
					foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
						$counts[ (string) $row['state'] ] = (int) $row['total'];
					}
				}
				$reconciliation = (int) $pdo->query(
					"SELECT COUNT(*) FROM inquiry_deliveries WHERE state = 'acceptance_unknown'"
				)->fetchColumn();
			} catch ( Throwable $e ) {
				$counts = array();
			}

			$parts = array();
			foreach ( $counts as $state => $total ) {
				$parts[] = $state . ': ' . $total;
			}
			$summary = empty( $parts ) ? 'sin datos de cola' : implode( ' · ', $parts );

			echo '<div class="flacso-consultas-diagnostic flacso-transactional-diagnostics" role="status"><p>';
			echo '<strong>' . esc_html__( 'Acuses transaccionales', 'flacso-uruguay' ) . ':</strong> ';
			echo esc_html( $queue_enabled ? 'cola habilitada' : 'cola deshabilitada' );
			echo ' · ' . esc_html( 'contrato Mautic: ' . $contract_status );
			echo ' · ' . esc_html( $summary );
			echo ' · ' . esc_html( 'conciliación manual: ' . $reconciliation );
			echo '</p></div>';
		}

		/**
		 * Renderiza la plataforma principal de Consultas con 6 pestañas.
		 */
		public static function render_page(): void { self::render_page_for_route( self::PAGE_BANDEJA ); }
		public static function render_resumen_page(): void { self::render_page_for_route( self::PAGE_RESUMEN ); }
		public static function render_oferta_pais_page(): void { self::render_page_for_route( self::PAGE_OFERTA_PAIS ); }
		public static function render_comparacion_page(): void { self::render_page_for_route( self::PAGE_COMPARACION ); }
		public static function render_campanas_page(): void { self::render_page_for_route( self::PAGE_CAMPANAS ); }
		public static function render_exportar_page(): void { self::render_page_for_route( self::PAGE_EXPORTAR ); }

		public static function render_page_for_route( string $route ): void {
			if ( ! self::can_view() ) {
				return;
			}

			$route_tabs = array( self::PAGE_BANDEJA => 'historico', self::PAGE_RESUMEN => 'oferta', self::PAGE_OFERTA_PAIS => 'oferta-pais', self::PAGE_COMPARACION => 'comparacion', self::PAGE_CAMPANAS => 'campanas', self::PAGE_EXPORTAR => 'exportar' );
			$active_tab = $route_tabs[ $route ] ?? 'historico';
			$valid_tabs = array( 'historico', 'oferta', 'oferta-pais', 'comparacion', 'campanas', 'exportar' );
			$can_manage = self::can_manage();
			if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
				$active_tab = 'historico';
			}
			if ( ! $can_manage && 'exportar' === $active_tab ) { $active_tab = 'historico'; }

			$table      = isset( $_GET['table'] ) ? self::resolve_table( sanitize_key( wp_unslash( $_GET['table'] ) ) ) : 'offer_inquiries';
			$pg_ready   = class_exists( 'FLACSO_DB' ) && FLACSO_DB::is_configured();
			$default_to = gmdate( 'Y-m-d' );
			$default_fr = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

			$from  = isset( $_GET['from'] ) && '' !== $_GET['from'] ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_fr;
			$to    = isset( $_GET['to'] ) && '' !== $_GET['to'] ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $default_to;
			$nonce = wp_create_nonce( self::NONCE_ACTION );
			?>
			<div class="wrap flacso-consultas-platform">
				<div class="flacso-consultas-page">
				<style>
					#wpbody-content > .notice { margin: 0 0 16px; color: #1d2327; background: #fff; border-radius: 6px; box-shadow: 0 1px 2px rgba(15,23,42,.06); }
					#wpbody-content > .notice p { margin: .8em 1em; padding: 0; color: inherit; background: transparent; line-height: 1.5; }
					.flacso-consultas-notices { margin: 0 0 18px; }
					.flacso-consultas-notices .notice { margin: 0 0 10px; color: #1d2327; background: #fff; }
					.flacso-consultas-notices .notice p { margin: .8em 1em; padding: 0; color: inherit; background: transparent; line-height: 1.5; }
					.flacso-consultas-diagnostic { margin: 0 0 10px; padding: 9px 12px; border-left: 4px solid #3858e9; color: #1d2327; background: #fff; border-radius: 3px; box-shadow: 0 1px 2px rgba(15,23,42,.06); }
					.flacso-consultas-diagnostic p { margin: .5em 0; padding: 0; color: inherit; background: transparent; line-height: 1.5; }
					.flacso-cp-hero .notice p { color: #1d2327; background: transparent; }
					.flacso-consultas-platform { max-width: 1360px; margin-top: 18px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
					.flacso-cp-hero {
						background: linear-gradient(135deg, #0f172a 0%, #1d3a72 60%, #1e40af 100%);
						color: #fff; padding: 24px 30px; border-radius: 14px; margin-bottom: 20px;
						display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
						box-shadow: 0 10px 25px rgba(15, 23, 42, 0.14);
					}
					.flacso-cp-hero h1 { color: #fff; margin: 0 0 6px; font-size: 24px; font-weight: 700; }
					.flacso-cp-hero p { color: rgba(255,255,255,0.85); margin: 0; font-size: 13.5px; }
					.flacso-cp-tabs {
						display: flex; gap: 6px; border-bottom: 2px solid #e2e8f0; margin-bottom: 22px; flex-wrap: wrap;
					}
					.flacso-cp-tab {
						padding: 10px 16px; text-decoration: none; font-weight: 600; font-size: 13.5px; color: #475569;
						border-radius: 8px 8px 0 0; border: 1px solid transparent; border-bottom: none; transition: all .15s;
					}
					.flacso-cp-tab:hover { color: #1d3a72; background: #f1f5f9; }
					.flacso-cp-tab.is-active {
						background: #fff; color: #1d3a72; border-color: #e2e8f0; position: relative; top: 2px;
						border-bottom: 2px solid #fff;
					}
					.flacso-cp-card {
						background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px;
						box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); margin-bottom: 20px;
					}
					.flacso-cp-kpi-grid {
						display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 20px;
					}
					.flacso-cp-kpi {
						background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 18px;
					}
					.flacso-cp-kpi .kpi-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
					.flacso-cp-kpi .kpi-val { font-size: 26px; font-weight: 800; color: #0f172a; margin: 6px 0 4px; }
					.flacso-cp-kpi .kpi-sub { font-size: 12px; color: #64748b; }
					.flacso-cp-filters {
						display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 12px; padding: 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
					}
					.flacso-cp-filters .fg { display: flex; flex-direction: column; gap: 4px; }
					.flacso-cp-filters label { font-size: 12px; font-weight: 600; color: #334155; }
					.flacso-comparison-filters { align-items: stretch; }
					.flacso-comparison-period { display: flex; flex-wrap: wrap; gap: 10px; padding: 11px 12px; border: 1px solid; border-radius: 9px; }
					.flacso-comparison-period__title { width: 100%; margin: 0; font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
					.flacso-comparison-period--current { background: #eff6ff; border-color: #bfdbfe; }
					.flacso-comparison-period--current .flacso-comparison-period__title { color: #1d4ed8; }
					.flacso-comparison-period--base { background: #f5f3ff; border-color: #ddd6fe; }
					.flacso-comparison-period--base .flacso-comparison-period__title { color: #6d28d9; }
					.flacso-comparison-actions { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; }
					.flacso-comparison-actions .button { min-height: 36px; }
					.flacso-cp-kpi--comparison { position: relative; overflow: hidden; }
					.flacso-cp-kpi--comparison::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: #2563eb; }
					.flacso-cp-kpi--uruguay::before { background: #059669; }
					.flacso-cp-kpi--exterior::before { background: #7c3aed; }
					.flacso-comparison-values { display: flex; align-items: baseline; gap: 7px; }
					.flacso-comparison-current { color: #1d4ed8; }
					.flacso-comparison-base { color: #6b7280; font-size: 14px; font-weight: 700; }
					.flacso-comparison-delta { display: inline-flex; align-items: center; gap: 4px; margin-top: 7px; padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 750; }
					.flacso-comparison-delta.is-positive { background: #dcfce7; color: #166534; }
					.flacso-comparison-delta.is-negative { background: #fee2e2; color: #b91c1c; }
					.flacso-comparison-delta.is-neutral { background: #e2e8f0; color: #475569; }
					.flacso-comparison-table .flacso-comparison-value--current { color: #1d4ed8; font-weight: 750; }
					.flacso-comparison-table .flacso-comparison-value--base { color: #6b7280; }
					.flacso-comparison-table .flacso-comparison-split { color: #475569; font-variant-numeric: tabular-nums; }
					.flacso-comparison-table .flacso-comparison-delta { margin-top: 0; white-space: nowrap; }
					.flacso-comparison-table tr.is-positive td:first-child { box-shadow: inset 3px 0 0 #22c55e; }
					.flacso-comparison-table tr.is-negative td:first-child { box-shadow: inset 3px 0 0 #ef4444; }
					.flacso-cp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
						.flacso-cp-inbox-table { table-layout: fixed; }
						.flacso-cp-inbox-table th,
						.flacso-cp-inbox-table td { overflow-wrap: anywhere; }
						.flacso-cp-inbox-table th:nth-child(1) { width: 9%; }
						.flacso-cp-inbox-table th:nth-child(2) { width: 13%; }
						.flacso-cp-inbox-table th:nth-child(3) { width: 27%; }
						.flacso-cp-inbox-table th:nth-child(4) { width: 9%; }
						.flacso-cp-inbox-table th:nth-child(5) { width: 10%; }
						.flacso-cp-inbox-table th:nth-child(6) { width: 8%; }
						.flacso-cp-inbox-table th:nth-child(7) { width: 14%; }
						.flacso-cp-inbox-table th:nth-child(8) { width: 10%; }
						.flacso-cp-inbox-table td:nth-child(1),
						.flacso-cp-inbox-table td:nth-child(5),
						.flacso-cp-inbox-table td:nth-child(7),
						.flacso-cp-inbox-table td:nth-child(8) { font-size: 12px; }
						.flacso-cp-inbox-offer { min-width: 0; }
						.flacso-cp-inbox-offer__name { display: block; font-weight: 700; color: #1e293b; line-height: 1.35; }
						.flacso-cp-inbox-offer__cohort { display: block; margin-top: 5px; color: #64748b; font-size: 12px; }
						.flacso-cp-inbox-actions { white-space: normal; }
						.flacso-cp-inbox-actions .button { margin: 2px 2px 2px 0; }
						@media (max-width: 1200px) {
							.flacso-cp-inbox-table { min-width: 1060px; }
						}
						.flacso-cp-table-scroll { overflow-x: auto; }
					.flacso-cp-table th {
						text-align: left; padding: 11px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0;
						color: #334155; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
					}
					.flacso-cp-table td { padding: 11px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
					.flacso-cp-inbox-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:0 0 12px; color:#475569; font-size:13px; }
					.flacso-cp-filter-reset { color:#475569; text-decoration:none; font-weight:600; }
					.flacso-cp-filter-reset:hover { color:#1d3a72; }
					.flacso-cp-person strong { display:block; color:#0f172a; line-height:1.35; }
					.flacso-cp-person a { display:block; margin-top:4px; font-size:12px; overflow-wrap:anywhere; }
					.flacso-cp-delivery { min-width:120px; }
					.flacso-cp-delivery .flacso-badge { margin:2px 0; }
					.flacso-cp-pagination { margin-top:16px; display:flex; gap:6px; justify-content:flex-end; align-items:center; }
					.flacso-cp-table tr:hover td { background: #f8fafc; }
					.flacso-badge {
						display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px;
						font-size: 11.5px; font-weight: 600;
					}
					.flacso-badge.sent { background: #dcfce7; color: #166534; }
					.flacso-badge.sent.fallback { background: #ffedd5; color: #9a3412; }
					.flacso-badge.sent.mautic { background: #f3e8ff; color: #6b21a8; }
					.flacso-badge.failed { background: #fee2e2; color: #991b1b; }
					.flacso-badge.failed.fallback { background: #fee2e2; color: #991b1b; border: 1px dashed #ef4444; }
					.flacso-badge.skipped { background: #fef3c7; color: #92400e; }
					.flacso-badge.offer_inquiries { background: #dbeafe; color: #1e40af; }
					.flacso-badge.seminar_inquiries { background: #f3e8ff; color: #6b21a8; }
					.flacso-badge.general_inquiries { background: #e2e8f0; color: #334155; }
					.flacso-badge.status-abierta { background: #dcfce7; color: #166534; }
					.flacso-badge.status-cerrada { background: #f1f5f9; color: #475569; }
					.flacso-badge.status-sin_cohorte { background: #f8fafc; color: #64748b; border: 1px dashed #cbd5e1; }
					.flacso-badge.abbr { background: #eff6ff; color: #1d4ed8; font-size: 11px; text-transform: lowercase; font-weight: 700; }
					.flacso-badge.mautic-synced { background: #f3e8ff; color: #6b21a8; }
					.flacso-badge.mautic-failed { background: #fee2e2; color: #991b1b; }
					.flacso-badge.mautic-pending { background: #fef3c7; color: #92400e; }
					.flacso-badge.mautic-skipped { background: #f1f5f9; color: #64748b; }
					.flacso-badge.followup-pending { background: #e0f2fe; color: #0369a1; }
					.flacso-badge.followup-sent { background: #dcfce7; color: #15803d; }
					.flacso-badge.followup-skipped { background: #f1f5f9; color: #64748b; }
					.flacso-badge.followup-failed { background: #fee2e2; color: #b91c1c; }
					.flacso-badge.followup-processing { background: #fef3c7; color: #b45309; }
					.flacso-chip {
						display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px;
						border: 1px solid #cbd5e1; background: #f8fafc; font-size: 12.5px; cursor: pointer; user-select: none;
					}
					.flacso-chip.is-selected { background: #1d3a72; color: #fff; border-color: #1d3a72; }
					.flacso-modal-backdrop {
						display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); z-index: 100000;
						align-items: center; justify-content: center; padding: 20px;
					}
					.flacso-modal {
						background: #fff; border-radius: 12px; max-width: 780px; width: 100%; max-height: 85vh;
						overflow-y: auto; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.25);
					}
				</style>

				<div class="flacso-consultas-notices">
					<?php self::render_transactional_diagnostics(); ?>
				</div>

				<?php self::render_page_header( __( 'Consultas', 'flacso-uruguay' ), __( 'Bandeja y análisis de consultas académicas.', 'flacso-uruguay' ) ); ?>

				<div class="flacso-cp-hero">
					<div>
						<h1>📊 Plataforma de Consultas e Inteligencia Analítica</h1>
						<p>Gestión operativa de consultas en PostgreSQL, sincronización con Mautic, atribución de campañas y exportación unificada.</p>
					</div>
					<?php if ( $can_manage ) : ?><div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=flacso-correos' ) ); ?>" class="button button-secondary" style="margin-right:8px;">✉️ Comunicaciones Mautic</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=exportar&table=' . $table ) ); ?>" class="button button-primary">📥 Exportar CSV</a>
					</div><?php endif; ?>
				</div>

				<?php if ( ! $pg_ready ) : ?>
					<div class="notice notice-error">
						<p><strong>PostgreSQL no está configurado en este entorno.</strong> Verifique las constantes <code>FLACSO_PG_*</code> en <code>wp-config.php</code>.</p>
					</div>
				<?php endif; ?>

				<nav class="flacso-cp-tabs">
					<?php
					$tabs = array(
						'historico'   => '📋 1. Histórico y Bandeja',
						'oferta'      => '🎓 2. Resumen por Oferta',
						'oferta-pais' => '🌎 3. Oferta y País',
						'comparacion' => '⚖️ 4. Comparación de Períodos',
						'campanas'    => '📣 5. Atribución de Campañas',
						'exportar'    => '📥 6. Exportar CSV',
					);
					if ( ! $can_manage ) { unset( $tabs['exportar'] ); }
					foreach ( $tabs as $slug => $label ) :
						$url = add_query_arg(
							array(
								'page'  => self::PAGE_SLUG,
								'tab'   => $slug,
								'table' => $table,
								'from'  => $from,
								'to'    => $to,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="flacso-cp-tab <?php echo $active_tab === $slug ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php
				switch ( $active_tab ) {
					case 'oferta':
						self::render_tab_oferta( $table, $from, $to );
						break;
					case 'oferta-pais':
						self::render_tab_oferta_pais( $table, $from, $to );
						break;
					case 'comparacion':
						self::render_tab_comparacion( $table, $from, $to );
						break;
					case 'campanas':
						self::render_tab_campanas( $table, $from, $to, $nonce );
						break;
					case 'exportar':
						self::render_tab_exportar( $table, $from, $to );
						break;
					case 'historico':
					default:
						self::render_tab_historico( $table, $from, $to, $nonce );
						break;
				}
				?>
				</div>
			</div>
			<?php
		}

		/**
		 * Pestaña 1: Histórico operativo, filtros, modo agrupado/individual, modal de detalle y botón Reintentar Email.
		 */
		private static function render_tab_historico( string $table, string $from, string $to, string $nonce ): void {
			$view_mode    = isset( $_GET['mode'] ) && 'raw' === $_GET['mode'] ? 'raw' : 'grouped';
			$item_name    = isset( $_GET['item_name'] ) ? sanitize_text_field( wp_unslash( $_GET['item_name'] ) ) : '';
			$country      = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
			$email_status = isset( $_GET['email_status'] ) ? sanitize_key( wp_unslash( $_GET['email_status'] ) ) : '';
			$offer_status = isset( $_GET['offer_status'] ) ? sanitize_key( wp_unslash( $_GET['offer_status'] ) ) : '';
			$search       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
			$paged        = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

			$items     = FLACSO_Inquiry_Analytics_Repository::get_distinct_items( $table );
			$countries = FLACSO_Inquiry_Analytics_Repository::get_distinct_countries( $table );
			$result    = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(
				array(
					'table'        => $table,
					'mode'         => $view_mode,
					'item_name'    => $item_name,
					'country'      => $country,
					'email_status' => $email_status,
					'offer_status' => $offer_status,
					'search'       => $search,
					'desde'        => $from,
					'hasta'        => $to,
					'page'         => $paged,
					'page_size'    => 25,
				)
			);
			?>
			<div class="flacso-cp-card">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="flacso-cp-filters">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<input type="hidden" name="tab" value="historico">

					<div class="fg">
						<label>Base / Tabla</label>
						<select name="table">
							<option value="offer_inquiries" <?php selected( $table, 'offer_inquiries' ); ?>>Ofertas Académicas</option>
							<option value="seminar_inquiries" <?php selected( $table, 'seminar_inquiries' ); ?>>Seminarios</option>
							<option value="general_inquiries" <?php selected( $table, 'general_inquiries' ); ?>>Consultas Generales</option>
						</select>
					</div>

					<div class="fg">
						<label>Vista</label>
						<select name="mode">
							<option value="grouped" <?php selected( $view_mode, 'grouped' ); ?>>Agrupada (Correo + Oferta)</option>
							<option value="raw" <?php selected( $view_mode, 'raw' ); ?>>Individual (Cada evento)</option>
						</select>
					</div>

					<div class="fg">
						<label>Oferta / Seminario</label>
						<select name="item_name" style="max-width:230px;">
							<option value="">— Todas —</option>
							<?php foreach ( $items as $it ) : ?>
								<option value="<?php echo esc_attr( $it['nombre'] ); ?>" <?php selected( $item_name, $it['nombre'] ); ?>>
									<?php echo esc_html( $it['nombre'] . ' (' . $it['total'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="fg">
						<label>País</label>
						<select name="country" style="max-width:150px;">
							<option value="">— Todos —</option>
							<?php foreach ( $countries as $c ) : ?>
								<option value="<?php echo esc_attr( $c['pais'] ); ?>" <?php selected( $country, $c['pais'] ); ?>><?php echo esc_html( $c['pais'] . ' (' . $c['total'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="fg">
						<label>Estado Email</label>
						<select name="email_status">
							<option value="">— Todos —</option>
							<option value="sent" <?php selected( $email_status, 'sent' ); ?>>✅ Enviado (sent)</option>
							<option value="failed" <?php selected( $email_status, 'failed' ); ?>>❌ Fallido (failed)</option>
							<option value="skipped" <?php selected( $email_status, 'skipped' ); ?>>⏭ Omitido (skipped)</option>
						</select>
					</div>

					<?php if ( 'offer_inquiries' === $table ) : ?>
						<div class="fg">
							<label>Al consultar</label>
							<select name="offer_status">
								<option value="">— Todos —</option>
								<option value="abierta" <?php selected( $offer_status, 'abierta' ); ?>>🟢 Abierta</option>
								<option value="cerrada" <?php selected( $offer_status, 'cerrada' ); ?>>⚪ Cerrada</option>
								<option value="sin_cohorte" <?php selected( $offer_status, 'sin_cohorte' ); ?>>➖ Sin cohorte</option>
							</select>
						</div>
					<?php endif; ?>

					<div class="fg">
						<label>Desde</label>
						<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
					</div>

					<div class="fg">
						<label>Hasta</label>
						<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
					</div>

					<div class="fg" style="flex:1; min-width:180px;">
						<label>Buscar (nombre, correo, consultaId)</label>
						<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Ej: maria@ejemplo.com">
					</div>

					<div class="fg">
						<button type="submit" class="button button-primary">Filtrar</button>
					</div>
				</form>

				<div class="flacso-cp-inbox-toolbar">
					<span>
						Mostrando <strong><?php echo count( $result['items'] ); ?></strong> de <strong><?php echo (int) $result['pageInfo']['totalItems']; ?></strong> registros
						(Página <?php echo (int) $result['pageInfo']['page']; ?> de <?php echo (int) $result['pageInfo']['totalPages']; ?>)
					</span>
					<a class="flacso-cp-filter-reset" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=historico&table=' . $table ) ); ?>">Limpiar filtros</a>
				</div>

				<div class="flacso-cp-table-scroll">
				<table class="flacso-cp-table <?php echo 'offer_inquiries' === $table ? 'flacso-cp-inbox-table' : ''; ?>">
					<thead>
						<tr>
							<th>Fecha</th>
							<th>Persona</th>
							<?php if ( 'offer_inquiries' === $table ) : ?>
								<th>Oferta / Cohorte</th>
								<th>Estado de la consulta</th>
								<th>Mautic</th>
							<?php else : ?>
								<th>Oferta / Seminario</th>
							<?php endif; ?>
							<th>País</th>
							<th>Estado del correo</th>
							<th class="flacso-cp-inbox-actions">Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="<?php echo 'offer_inquiries' === $table ? 8 : 6; ?>" style="text-align:center; padding:26px; color:#64748b;">No se encontraron consultas con los filtros seleccionados.</td></tr>
						<?php else : ?>
							<?php foreach ( $result['items'] as $row ) :
								$count_val  = (int) ( $row['count'] ?? 1 );
								$date_val   = (string) ( $row['latestAt'] ?? $row['inquiryAt'] ?? '' );
								?>
								<tr>
									<td style="white-space:nowrap; font-size:12px; color:#475569;">
										<?php echo esc_html( substr( $date_val, 0, 16 ) ); ?>
									</td>
									<td class="flacso-cp-person">
										<strong><?php echo esc_html( (string) ( $row['fullName'] ?: ( ( $row['firstName'] ?? '' ) . ' ' . ( $row['lastName'] ?? '' ) ) ) ); ?></strong>
										<a href="mailto:<?php echo esc_attr( (string) $row['email'] ); ?>"><?php echo esc_html( (string) $row['email'] ); ?></a>
										<?php if ( $count_val > 1 ) : ?>
											<span class="flacso-badge offer_inquiries" title="Consultas acumuladas por esta persona para esta oferta"><?php echo (int) $count_val; ?>x</span>
										<?php endif; ?>
									</td>
									<?php if ( 'offer_inquiries' === $table ) : ?>
										<td>
											<div class="flacso-cp-inbox-offer">
												<span class="flacso-cp-inbox-offer__name"><?php echo esc_html( (string) $row['item_name'] ); ?></span>
												<?php if ( ! empty( $row['offerAbbreviation'] ) ) : ?>
													<span class="flacso-badge abbr" title="Abreviación canónica"><?php echo esc_html( (string) $row['offerAbbreviation'] ); ?></span>
												<?php endif; ?>
												<?php
												$cohort_num   = ! empty( $row['cohortNumber'] ) ? (int) $row['cohortNumber'] : 0;
												$cohort_label = $cohort_num > 0
													? sprintf( __( 'Cohorte %d', 'flacso-uruguay' ), $cohort_num )
													: ( ! empty( $row['cohortName'] ) ? (string) $row['cohortName'] : __( 'Sin cohorte', 'flacso-uruguay' ) );
												?>
												<span class="flacso-cp-inbox-offer__cohort"><?php echo esc_html( $cohort_label ); ?></span>
											</div>
										</td>
										<td>
											<?php
											$st = (string) ( $row['offerStatus'] ?? 'sin_cohorte' );
											if ( 'abierta' === $st ) {
												echo '<span class="flacso-badge status-abierta" title="' . esc_attr__( 'Inscripciones abiertas al consultar', 'flacso-uruguay' ) . '">🟢 ' . esc_html__( 'Abierta', 'flacso-uruguay' ) . '</span>';
											} elseif ( 'cerrada' === $st ) {
												echo '<span class="flacso-badge status-cerrada" title="' . esc_attr__( 'Inscripciones cerradas al consultar', 'flacso-uruguay' ) . '">⚪ ' . esc_html__( 'Cerrada', 'flacso-uruguay' ) . '</span>';
											} else {
												echo '<span class="flacso-badge status-sin_cohorte" title="' . esc_attr__( 'Sin cohorte activa al consultar', 'flacso-uruguay' ) . '">➖ ' . esc_html__( 'Sin cohorte', 'flacso-uruguay' ) . '</span>';
											}
											?>
										</td>
										<td class="flacso-mautic-cell" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
											<?php echo self::render_mautic_badge( $row ); ?>
										</td>
									<?php else : ?>
										<td>
											<span class="flacso-badge <?php echo esc_attr( $table ); ?>"><?php echo esc_html( FLACSO_Inquiry_Analytics_Repository::ALLOWED_TABLES[ $table ]['label'] ?? $table ); ?></span>
											<span style="font-weight:600; color:#1e293b;"><?php echo esc_html( (string) $row['item_name'] ); ?></span>
										</td>
									<?php endif; ?>
									<td style="font-size:12px;">
										<?php echo esc_html( (string) ( $row['country'] ?: '—' ) ); ?>
									</td>
									<td class="flacso-status-cell flacso-cp-delivery" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
										<?php echo self::render_email_status_badge( $row ); ?>
										<?php
										if ( 'offer_inquiries' === $table ) {
											$followup_badge = self::render_followup_status_badge( $row );
											if ( '' !== $followup_badge ) {
												echo ' ' . $followup_badge;
											}
										}
										?>
									</td>
									<td class="flacso-cp-inbox-actions">
										<button type="button" class="button button-small flacso-js-detail"
											data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
											data-table="<?php echo esc_attr( $table ); ?>">
											🔍 Detalle
										</button>
										<?php
										$email_status = strtolower( trim( (string) ( $row['emailStatus'] ?? '' ) ) );
										if ( in_array( $table, array( 'offer_inquiries', 'seminar_inquiries' ), true )
											&& self::is_retryable_email_status( $email_status ) ) :
											?>
											<button type="button" class="button button-small flacso-js-retry-email"
												data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
												data-table="<?php echo esc_attr( $table ); ?>"
												title="<?php esc_attr_e( 'Reintentar correo transaccional vía Mautic', 'flacso-uruguay' ); ?>">
												✉️ Reenviar
											</button>
										<?php endif; ?>
										<?php
										$m_status = strtolower( trim( (string) ( $row['mauticSyncStatus'] ?? 'pending' ) ) );
										if ( 'offer_inquiries' === $table && in_array( $m_status, array( 'failed', 'pending' ), true ) ) : ?>
											<button type="button" class="button button-small flacso-js-retry-mautic"
												data-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
												title="<?php esc_attr_e( 'Reintentar sincronización con Mautic', 'flacso-uruguay' ); ?>">
												🔄 Mautic
											</button>
										<?php endif; ?>
									</td>
									</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				</div>

				<?php if ( $result['pageInfo']['totalPages'] > 1 ) : ?>
					<div class="flacso-cp-pagination" aria-label="Paginación de consultas">
						<?php for ( $p = 1; $p <= min( 12, $result['pageInfo']['totalPages'] ); $p++ ) :
							$page_url = add_query_arg( 'paged', $p );
							?>
							<a href="<?php echo esc_url( $page_url ); ?>" class="button <?php echo $p === $result['pageInfo']['page'] ? 'button-primary' : ''; ?>">
								<?php echo (int) $p; ?>
							</a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Modal de Detalle -->
			<div id="flacso-detail-modal" class="flacso-modal-backdrop">
				<div class="flacso-modal">
					<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
						<h2 style="margin:0; font-size:18px;">Detalle de Consulta</h2>
						<button type="button" class="button" id="flacso-close-modal">✕ Cerrar</button>
					</div>
					<div id="flacso-detail-content">Cargando...</div>
				</div>
			</div>

			<script>
			(function(){
				const nonce = <?php echo wp_json_encode( $nonce ); ?>;
				const modal = document.getElementById('flacso-detail-modal');
				const content = document.getElementById('flacso-detail-content');
				const closeBtn = document.getElementById('flacso-close-modal');
				if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });

				document.querySelectorAll('.flacso-js-detail').forEach(btn => {
					btn.addEventListener('click', function(){
						const id = this.getAttribute('data-id');
						const tbl = this.getAttribute('data-table');
						modal.style.display = 'flex';
						content.innerHTML = '<p>Consultando PostgreSQL...</p>';
						const fd = new FormData();
						fd.append('action', 'flacso_consultas_detail');
						fd.append('nonce', nonce);
						fd.append('id', id);
						fd.append('table', tbl);
						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								if (!res.success) {
									content.innerHTML = '<div class="notice notice-error"><p>' + (res.data?.message || 'Error') + '</p></div>';
									return;
								}
								const d = res.data;
								let senderLabel = d.emailSender || '—';
								if (d.emailSender === 'mailjet_fallback') {
									senderLabel = 'Mailjet (Conmutación por fallo de Mautic)';
								} else if (d.emailSender === 'mautic') {
									senderLabel = 'Mautic';
								} else if (d.emailSender === 'mailjet') {
									senderLabel = 'Mailjet';
								}
								content.innerHTML = `
									<table class="widefat striped" style="margin-bottom:14px;">
										<tbody>
											<tr><th>ID / Consulta ID</th><td><code>${d.id}</code> / <code>${d.consultaId || ''}</code></td></tr>
											<tr><th>Nombre completo</th><td><strong>${d.fullName || ''}</strong></td></tr>
											<tr><th>Correo electrónico</th><td>${d.email || ''}</td></tr>
											<tr><th>Oferta / Programa</th><td>${d.item_name || ''}</td></tr>
											<tr><th>País / Teléfono</th><td>${d.country || '—'} / ${d.phone || '—'}</td></tr>
											<tr><th>Estado Email</th><td><strong>${d.emailStatus || ''}</strong> (Remitente: ${senderLabel})</td></tr>
											${d.mauticSyncStatus ? `<tr><th>Estado Mautic</th><td><strong>${d.mauticSyncStatus}</strong> (Contact ID: ${d.mauticContactId || '—'} | Sincronizado: ${d.mauticSyncedAt || '—'}${d.mauticLastError ? ' | Error: ' + d.mauticLastError : ''})</td></tr>` : ''}
											${(d.table === 'offer_inquiries' || tbl === 'offer_inquiries') ? `<tr><th>Seguimiento (+X días)</th><td><strong>${d.followupStatus || 'none'}</strong> (Vencimiento: ${d.followupDueAt || '—'} | Enviado: ${d.followupSentAt || '—'}${d.followupLastError ? ' | Nota: ' + d.followupLastError : ''})${d.followupStatus !== 'sent' ? '<div style="margin-top:8px;"><button type="button" class="button button-secondary flacso-js-trigger-followup" data-id="' + d.id + '">🚀 Enviar Seguimiento Ahora</button><span class="flacso-followup-msg" style="margin-left:10px;font-size:12px;font-weight:600;display:inline-block;vertical-align:middle;"></span></div>' : ''}</td></tr>` : ''}
											<tr><th>UTM / Campaña</th><td>Source: ${d.campaignSource || '—'} | Medium: ${d.campaignMedium || '—'} | Campaign: ${d.campaignName || '—'}</td></tr>
											<tr><th>Página Origen</th><td><a href="${d.pageUrl || '#'}" target="_blank">${d.pageUrl || '—'}</a></td></tr>
										</tbody>
									</table>
									<h4 style="margin:10px 0 6px;">Payload Original (JSON)</h4>
									<pre style="background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; overflow:auto; max-height:240px; font-size:12px;">${JSON.stringify(d.payload_decoded || {}, null, 2)}</pre>
								`;

								const triggerBtn = content.querySelector('.flacso-js-trigger-followup');
								if (triggerBtn) {
									triggerBtn.addEventListener('click', function(){
										const trigId = this.getAttribute('data-id');
										const origBtnText = this.textContent;
										const msgEl = this.parentNode ? this.parentNode.querySelector('.flacso-followup-msg') : null;
										if (msgEl) {
											msgEl.textContent = '';
											msgEl.style.color = '#64748b';
										}
										this.disabled = true;
										this.textContent = '⏳ Procesando...';
										const tfd = new FormData();
										tfd.append('action', 'flacso_consultas_trigger_followup');
										tfd.append('nonce', nonce);
										tfd.append('id', trigId);
										fetch(ajaxurl, { method: 'POST', body: tfd })
											.then(r => r.json())
											.then(res => {
												if (res.success) {
													this.textContent = '✅ Procesado';
													if (msgEl) {
														msgEl.style.color = '#15803d';
														msgEl.textContent = '✔ ' + (res.data?.message || 'Seguimiento ejecutado con éxito');
													}
													const cell = document.querySelector(`.flacso-status-cell[data-id="${trigId}"]`);
													if (cell && res.data?.status) {
														const st = res.data.status;
														const badgeClass = 'followup-' + st;
														let badgeLabel = 'Seg. ' + st;
														if (st === 'sent') badgeLabel = '✅ Seg. enviado';
														else if (st === 'skipped') badgeLabel = '⏭ Seg. omitido';
														else if (st === 'failed') badgeLabel = '❌ Seg. falló';
														else if (st === 'pending') badgeLabel = '⏰ Seg. pend.';
														let existingFollowup = cell.querySelector('.flacso-badge[class*="followup-"]');
														if (existingFollowup) {
															existingFollowup.className = 'flacso-badge ' + badgeClass;
															existingFollowup.textContent = badgeLabel;
														} else {
															const newBadge = document.createElement('span');
															newBadge.className = 'flacso-badge ' + badgeClass;
															newBadge.textContent = badgeLabel;
															cell.appendChild(document.createTextNode(' '));
															cell.appendChild(newBadge);
														}
													}
												} else {
													this.disabled = false;
													this.textContent = origBtnText;
													if (msgEl) {
														msgEl.style.color = '#b91c1c';
														msgEl.textContent = '✖ ' + (res.data?.message || 'Error al procesar seguimiento');
													}
												}
											})
											.catch(err => {
												this.disabled = false;
												this.textContent = origBtnText;
												if (msgEl) {
													msgEl.style.color = '#b91c1c';
													msgEl.textContent = '✖ Error de conexión: ' + err.message;
												}
											});
									});
								}
							});
					});
				});

				document.querySelectorAll('.flacso-js-retry-email').forEach(btn => {
					btn.addEventListener('click', function(){
						const id = this.getAttribute('data-id');
						const tbl = this.getAttribute('data-table');
						const origText = this.textContent;
						this.disabled = true;
						this.textContent = '⏳ Enviando...';
						const fd = new FormData();
						fd.append('action', 'flacso_consultas_retry_email');
						fd.append('nonce', nonce);
						fd.append('id', id);
						fd.append('table', tbl);
						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								this.disabled = false;
								if (res.success) {
									this.textContent = '✅ Aceptado';
									const cell = document.querySelector(`.flacso-status-cell[data-id="${id}"]`);
									if (cell) {
										cell.innerHTML = '<span class="flacso-badge sent mautic" title="Mautic aceptó la solicitud de envío">accepted (Mautic)</span>';
									}
									this.style.display = 'none';
								} else {
									this.textContent = origText;
									alert('Error al reenviar: ' + (res.data?.message || 'Mautic no aceptó el correo transaccional.'));
								}
							})
							.catch(err => {
								this.disabled = false;
								this.textContent = origText;
								alert('Error al reenviar: ' + err.message);
							});
					});
				});

				document.querySelectorAll('.flacso-js-retry-mautic').forEach(btn => {
					btn.addEventListener('click', function(){
						const id = this.getAttribute('data-id');
						const origText = this.textContent;
						this.disabled = true;
						this.textContent = '⏳ Sincronizando...';
						const fd = new FormData();
						fd.append('action', 'flacso_consultas_retry_mautic');
						fd.append('nonce', nonce);
						fd.append('id', id);
						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								if (res.success) {
									const cell = document.querySelector(`.flacso-mautic-cell[data-id="${id}"]`);
									if (cell) {
										const status = res.data?.status;
										if (status === 'skipped') {
											cell.innerHTML = '<span class="flacso-badge mautic-skipped" title="Sincronización desactivada u omitida">⚪ Omitido</span>';
										} else {
											const cid = res.data?.contact_id;
											const label = cid ? `🟣 ID ${cid}` : '🟣 Sincronizado';
											cell.innerHTML = `<span class="flacso-badge mautic-synced" title="Sincronizado con Mautic">${label}</span>`;
										}
									}
									this.style.display = 'none';
								} else {
									this.disabled = false;
									this.textContent = origText;
									alert('Error al sincronizar con Mautic: ' + (res.data?.message || 'Error'));
								}
							})
							.catch(err => {
								this.disabled = false;
								this.textContent = origText;
								alert('Error al conectar con Mautic: ' + err.message);
							});
					});
				});
			})();
			</script>
			<?php
		}

		/**
		 * Pestaña 2: Resumen por Oferta Académica (Serie temporal SVG con media móvil 7d + 4 KPIs de segmento + Tabla deduplicada).
		 */
		private static function render_tab_oferta( string $table, string $from, string $to ): void {
			$summary = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary( $from, $to, '', $table );
			$resumen = $summary['resumen'];
			$series  = $summary['serieTemporal'];

			$tot_dedup = (int) $resumen['total']['totalConsultas'];
			$uy_dedup  = (int) $resumen['uruguay']['totalConsultas'];
			$ext_dedup = (int) $resumen['exterior']['totalConsultas'];
			$intersec  = (int) $resumen['correosInterseccionUyExt'];
			?>
			<div class="flacso-cp-card">
				<?php self::render_date_range_form( 'oferta', $table, $from, $to ); ?>

				<div class="flacso-cp-kpi-grid">
					<div class="flacso-cp-kpi">
						<div class="kpi-label">Total Personas-Oferta (Deduplicado)</div>
						<div class="kpi-val"><?php echo (int) $tot_dedup; ?></div>
						<div class="kpi-sub"><?php echo (int) $resumen['total']['totalCorreosUnicos']; ?> correos únicos</div>
					</div>
					<div class="flacso-cp-kpi">
						<div class="kpi-label">🇺🇾 Uruguay</div>
						<div class="kpi-val" style="color:#1d4ed8;"><?php echo (int) $uy_dedup; ?></div>
						<div class="kpi-sub"><?php echo $tot_dedup > 0 ? round( ( $uy_dedup / $tot_dedup ) * 100, 1 ) : 0; ?>% del total deduplicado</div>
					</div>
					<div class="flacso-cp-kpi">
						<div class="kpi-label">🌎 Exterior</div>
						<div class="kpi-val" style="color:#047857;"><?php echo (int) $ext_dedup; ?></div>
						<div class="kpi-sub"><?php echo $tot_dedup > 0 ? round( ( $ext_dedup / $tot_dedup ) * 100, 1 ) : 0; ?>% del total deduplicado</div>
					</div>
					<div class="flacso-cp-kpi">
						<div class="kpi-label">🔄 Intersección UY ∩ EXT</div>
						<div class="kpi-val" style="color:#7c3aed;"><?php echo (int) $intersec; ?></div>
						<div class="kpi-sub">Correos con consultas tanto en UY como Exterior</div>
					</div>
				</div>

				<h3 style="margin:10px 0 12px;">Evolución Diaria y Media Móvil (7 días)</h3>
				<?php self::render_svg_time_series( $series ); ?>
			</div>

			<div class="flacso-cp-card">
				<h3 style="margin-top:0;">Tabla Consolidada por Oferta Académica / Seminario (Deduplicada por Correo + Oferta)</h3>
				<table class="flacso-cp-table">
					<thead>
						<tr>
							<th>Oferta Académica / Seminario</th>
							<th>Total (Únicos)</th>
							<th>🇺🇾 Uruguay</th>
							<th>🌎 Exterior</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $summary['consolidado'] as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['oferta'] ); ?></strong></td>
								<td><strong><?php echo (int) $row['total']; ?></strong></td>
								<td><?php echo (int) $row['uy']; ?></td>
								<td><?php echo (int) $row['ext']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Pestaña 3: Oferta y País (Desglose por país + selector de país con curva temporal).
		 */
		private static function render_tab_oferta_pais( string $table, string $from, string $to ): void {
			$selected_country = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
			$summary          = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary( $from, $to, '', $table );
			$countries        = FLACSO_Inquiry_Analytics_Repository::get_distinct_countries( $table );

			$active_series = $summary['serieTemporal'];
			if ( '' !== $selected_country ) {
				foreach ( $summary['serieTemporalPorPais'] as $sp ) {
					if ( strcasecmp( $sp['pais'], $selected_country ) === 0 ) {
						$active_series = $sp['serieTemporal'];
						break;
					}
				}
			}
			?>
			<div class="flacso-cp-card">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="flacso-cp-filters">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<input type="hidden" name="tab" value="oferta-pais">
					<input type="hidden" name="table" value="<?php echo esc_attr( $table ); ?>">
					<div class="fg">
						<label>Desde</label>
						<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
					</div>
					<div class="fg">
						<label>Hasta</label>
						<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
					</div>
					<div class="fg">
						<label>Filtrar Curva por País</label>
						<select name="country">
							<option value="">— Todos los Países —</option>
							<?php foreach ( $countries as $c ) : ?>
								<option value="<?php echo esc_attr( $c['pais'] ); ?>" <?php selected( $selected_country, $c['pais'] ); ?>><?php echo esc_html( $c['pais'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="fg">
						<button type="submit" class="button button-primary">Actualizar</button>
					</div>
				</form>

				<h3 style="margin:6px 0 12px;">
					Curva de Consultas <?php echo '' !== $selected_country ? '— País: ' . esc_html( $selected_country ) : '(Todos los países)'; ?>
				</h3>
				<?php self::render_svg_time_series( $active_series ); ?>
			</div>

			<div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:20px;">
				<div class="flacso-cp-card">
					<h3 style="margin-top:0;">Ranking por País</h3>
					<table class="flacso-cp-table">
						<thead>
							<tr><th>País</th><th>Consultas Únicas</th></tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $summary['serieTemporalPorPais'], 0, 25 ) as $c_row ) : ?>
								<tr>
									<td><?php echo esc_html( $c_row['pais'] ); ?></td>
									<td><strong><?php echo (int) $c_row['total']; ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="flacso-cp-card">
					<h3 style="margin-top:0;">Detalle Oferta × País</h3>
					<table class="flacso-cp-table">
						<thead>
							<tr><th>Oferta</th><th>País</th><th>Consultas</th></tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $summary['ofertaPais']['filas'], 0, 40 ) as $oc ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $oc['oferta'] ); ?></strong></td>
									<td><?php echo esc_html( $oc['pais'] ); ?></td>
									<td><strong><?php echo (int) $oc['total']; ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		/**
		 * Pestaña 4: Comparación de Períodos (Período 1 vs Período 2 + Deltas + Tarjetas comparativas por Oferta).
		 */
		private static function render_tab_comparacion( string $table, string $from, string $to ): void {
			$days_diff = max( 1, (int) round( ( strtotime( $to ) - strtotime( $from ) ) / 86400 ) + 1 );
			$def_p2_to = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
			$def_p2_fr = gmdate( 'Y-m-d', strtotime( $def_p2_to . ' -' . ( $days_diff - 1 ) . ' days' ) );

			$p2_from = isset( $_GET['p2_from'] ) && '' !== $_GET['p2_from'] ? sanitize_text_field( wp_unslash( $_GET['p2_from'] ) ) : $def_p2_fr;
			$p2_to   = isset( $_GET['p2_to'] ) && '' !== $_GET['p2_to'] ? sanitize_text_field( wp_unslash( $_GET['p2_to'] ) ) : $def_p2_to;

			$s1 = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary( $from, $to, '', $table );
			$s2 = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary( $p2_from, $p2_to, '', $table );

			$r1 = $s1['resumen'];
			$r2 = $s2['resumen'];

			$delta_fn = static function ( $curr, $prev ): array {
				$diff = $curr - $prev;
				$tone = $diff > 0 ? 'positive' : ( $diff < 0 ? 'negative' : 'neutral' );
				$icon = $diff > 0 ? '↑' : ( $diff < 0 ? '↓' : '→' );
				$sign = $diff > 0 ? '+' : '';
				if ( $prev <= 0 ) {
					return array( 'tone' => $tone, 'icon' => $icon, 'label' => sprintf( '%s%d (N/A %%)', $sign, $diff ) );
				}
				$pct = round( ( $diff / $prev ) * 100, 1 );
				return array( 'tone' => $tone, 'icon' => $icon, 'label' => sprintf( '%s%d (%s%s%%)', $sign, $diff, $sign, $pct ) );
			};
			$render_delta = static function ( $curr, $prev ) use ( $delta_fn ): string {
				$delta = $delta_fn( $curr, $prev );
				return sprintf(
					'<span class="flacso-comparison-delta is-%1$s"><span aria-hidden="true">%2$s</span>%3$s</span>',
					esc_attr( $delta['tone'] ),
					esc_html( $delta['icon'] ),
					esc_html( $delta['label'] )
				);
			};
			?>
			<div class="flacso-cp-card">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="flacso-cp-filters flacso-comparison-filters">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<input type="hidden" name="tab" value="comparacion">
					<input type="hidden" name="table" value="<?php echo esc_attr( $table ); ?>">
					<div class="flacso-comparison-period flacso-comparison-period--current">
						<p class="flacso-comparison-period__title">Período actual</p>
						<div class="fg"><label>Desde</label><input type="date" name="from" id="flacso-p1-from" value="<?php echo esc_attr( $from ); ?>"></div>
						<div class="fg"><label>Hasta</label><input type="date" name="to" id="flacso-p1-to" value="<?php echo esc_attr( $to ); ?>"></div>
					</div>
					<div class="flacso-comparison-period flacso-comparison-period--base">
						<p class="flacso-comparison-period__title">Período base</p>
						<div class="fg"><label>Desde</label><input type="date" name="p2_from" id="flacso-p2-from" value="<?php echo esc_attr( $p2_from ); ?>"></div>
						<div class="fg"><label>Hasta</label><input type="date" name="p2_to" id="flacso-p2-to" value="<?php echo esc_attr( $p2_to ); ?>"></div>
					</div>
					<div class="flacso-comparison-actions">
						<button type="button" class="button" id="flacso-auto-prev-period">⏮ Usar los <?php echo (int) $days_diff; ?> días anteriores</button>
						<button type="submit" class="button button-primary">Comparar Períodos</button>
					</div>
				</form>

				<div class="flacso-cp-kpi-grid">
					<div class="flacso-cp-kpi flacso-cp-kpi--comparison">
						<div class="kpi-label">Total Deduplicado (P1 vs P2)</div>
						<div class="kpi-val flacso-comparison-values"><span class="flacso-comparison-current"><?php echo (int) $r1['total']['totalConsultas']; ?></span><span class="flacso-comparison-base">vs <?php echo (int) $r2['total']['totalConsultas']; ?></span></div>
						<?php echo $render_delta( $r1['total']['totalConsultas'], $r2['total']['totalConsultas'] ); ?>
					</div>
					<div class="flacso-cp-kpi flacso-cp-kpi--comparison flacso-cp-kpi--uruguay">
						<div class="kpi-label">🇺🇾 Uruguay (P1 vs P2)</div>
						<div class="kpi-val flacso-comparison-values"><span class="flacso-comparison-current"><?php echo (int) $r1['uruguay']['totalConsultas']; ?></span><span class="flacso-comparison-base">vs <?php echo (int) $r2['uruguay']['totalConsultas']; ?></span></div>
						<?php echo $render_delta( $r1['uruguay']['totalConsultas'], $r2['uruguay']['totalConsultas'] ); ?>
					</div>
					<div class="flacso-cp-kpi flacso-cp-kpi--comparison flacso-cp-kpi--exterior">
						<div class="kpi-label">🌎 Exterior (P1 vs P2)</div>
						<div class="kpi-val flacso-comparison-values"><span class="flacso-comparison-current"><?php echo (int) $r1['exterior']['totalConsultas']; ?></span><span class="flacso-comparison-base">vs <?php echo (int) $r2['exterior']['totalConsultas']; ?></span></div>
						<?php echo $render_delta( $r1['exterior']['totalConsultas'], $r2['exterior']['totalConsultas'] ); ?>
					</div>
				</div>

				<h3>Comparación 1 a 1 por Oferta Académica</h3>
				<table class="flacso-cp-table flacso-comparison-table">
					<thead>
						<tr>
							<th>Oferta Académica</th>
							<th>P1 Total</th>
							<th>P2 Total</th>
							<th>Δ Variación</th>
							<th>P1 UY / EXT</th>
							<th>P2 UY / EXT</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$map2 = array();
						foreach ( $s2['consolidado'] as $o2 ) {
							$map2[ $o2['oferta'] ] = $o2;
						}
						foreach ( $s1['consolidado'] as $o1 ) :
							$o2 = $map2[ $o1['oferta'] ] ?? array( 'total' => 0, 'uy' => 0, 'ext' => 0 );
							?>
							<?php $delta = $delta_fn( $o1['total'], $o2['total'] ); ?>
							<tr class="is-<?php echo esc_attr( $delta['tone'] ); ?>">
								<td><strong><?php echo esc_html( $o1['oferta'] ); ?></strong></td>
								<td class="flacso-comparison-value--current"><?php echo (int) $o1['total']; ?></td>
								<td class="flacso-comparison-value--base"><?php echo (int) $o2['total']; ?></td>
								<td><?php echo $render_delta( $o1['total'], $o2['total'] ); ?></td>
								<td class="flacso-comparison-split"><?php echo (int) $o1['uy']; ?> / <?php echo (int) $o1['ext']; ?></td>
								<td class="flacso-comparison-split"><?php echo (int) $o2['uy']; ?> / <?php echo (int) $o2['ext']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<script>
			(function(){
				const btn = document.getElementById('flacso-auto-prev-period');
				if (!btn) return;
				btn.addEventListener('click', function(){
					const f1 = new Date(document.getElementById('flacso-p1-from').value);
					const t1 = new Date(document.getElementById('flacso-p1-to').value);
					if (isNaN(f1) || isNaN(t1)) return;
					const diffMs = t1.getTime() - f1.getTime();
					const p2To = new Date(f1.getTime() - 86400000);
					const p2From = new Date(p2To.getTime() - diffMs);
					document.getElementById('flacso-p2-from').value = p2From.toISOString().slice(0,10);
					document.getElementById('flacso-p2-to').value = p2To.toISOString().slice(0,10);
				});
			})();
			</script>
			<?php
		}

		/**
		 * Pestaña 5: Atribución de Campañas (Ranking UTM / Mailjet / Meta / Google y ocultación de campañas de prueba).
		 */
		private static function render_tab_campanas( string $table, string $from, string $to, string $nonce ): void {
			$summary   = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary( $from, $to, '', $table );
			$campanas  = $summary['campanas'];
			$campaigns = $campanas['filas'];
			$excluded  = $campanas['ocultas'];
			$pct       = $campanas['totalConsultas'] > 0 ? round( ( $campanas['totalConCampana'] / $campanas['totalConsultas'] ) * 100, 1 ) : 0;
			?>
			<div class="flacso-cp-card">
				<?php self::render_date_range_form( 'campanas', $table, $from, $to ); ?>

				<div class="flacso-cp-kpi-grid">
					<div class="flacso-cp-kpi">
						<div class="kpi-label">Consultas con Campaña</div>
						<div class="kpi-val"><?php echo (int) $campanas['totalConCampana']; ?></div>
						<div class="kpi-sub"><?php echo esc_html( (string) $pct ); ?>% sobre <?php echo (int) $campanas['totalConsultas']; ?> consultas del período</div>
					</div>
					<div class="flacso-cp-kpi">
						<div class="kpi-label">Campañas Activas Detectadas</div>
						<div class="kpi-val"><?php echo (int) $campanas['totalCampanas']; ?></div>
						<div class="kpi-sub"><?php echo count( $excluded ); ?> campañas ocultas/excluidas</div>
					</div>
				</div>

				<h3>Ranking de Campañas y Fuentes (UTM / Ads / Mailing)</h3>
				<table class="flacso-cp-table">
					<thead>
						<tr>
							<th>Campaña</th>
							<th>Proveedor / Fuente / Medio</th>
							<th>Consultas</th>
							<th>Personas Únicas</th>
							<th>Ofertas Consultadas</th>
							<th>Acción</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $campaigns ) ) : ?>
							<tr><td colspan="6" style="text-align:center; padding:24px; color:#64748b;">No se registraron campañas con atribución en este rango.</td></tr>
						<?php else : ?>
							<?php foreach ( $campaigns as $c ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $c['campana'] ); ?></strong></td>
									<td><code><?php echo esc_html( trim( $c['proveedor'] . ' ' . $c['fuente'] . ' / ' . $c['medio'] ) ); ?></code></td>
									<td><strong><?php echo (int) $c['consultas']; ?></strong></td>
									<td><?php echo (int) $c['correosUnicos']; ?></td>
									<td><?php echo (int) $c['ofertas']; ?></td>
									<td>
										<button type="button" class="button button-small flacso-js-toggle-camp"
											data-key="<?php echo esc_attr( $c['clave'] ); ?>"
											data-name="<?php echo esc_attr( $c['campana'] ); ?>"
											data-exclude="1">
											🙈 Ocultar
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( ! empty( $excluded ) ) : ?>
					<div style="margin-top:18px; padding-top:12px; border-top:1px solid #e2e8f0;">
						<strong>Campañas ocultas:</strong>
						<?php foreach ( $excluded as $ex_c ) : ?>
							<button type="button" class="button button-small flacso-js-toggle-camp" style="margin:4px;"
								data-key="<?php echo esc_attr( $ex_c['clave'] ); ?>"
								data-name="<?php echo esc_attr( $ex_c['campana'] ); ?>"
								data-exclude="0">
								👁 Restaurar "<?php echo esc_html( $ex_c['campana'] ); ?>"
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<script>
			(function(){
				const nonce = <?php echo wp_json_encode( $nonce ); ?>;
				document.querySelectorAll('.flacso-js-toggle-camp').forEach(btn => {
					btn.addEventListener('click', function(){
						const fd = new FormData();
						fd.append('action', 'flacso_consultas_toggle_campaign');
						fd.append('nonce', nonce);
						fd.append('key', this.getAttribute('data-key'));
						fd.append('name', this.getAttribute('data-name'));
						fd.append('exclude', this.getAttribute('data-exclude') === '1' ? '1' : '');
						fetch(ajaxurl, { method: 'POST', body: fd }).then(() => window.location.reload());
					});
				});
			})();
			</script>
			<?php
		}

		/**
		 * Pestaña 6: Constructor de Exportación CSV con chips de Ofertas/Seminarios y descarga directa.
		 */
		private static function render_tab_exportar( string $table, string $from, string $to ): void {
			$items = FLACSO_Inquiry_Analytics_Repository::get_distinct_items( $table );
			?>
			<div class="flacso-cp-card">
				<h2 style="margin-top:0;">📥 Exportar Consultas a CSV / Google Sheets</h2>
				<p style="color:#475569;">Seleccione el rango de fechas, el modo de deduplicación y las ofertas académicas o seminarios que desea incluir en el archivo CSV (codificación UTF-8 con BOM).</p>

				<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="flacso_consultas_export_csv">
					<?php wp_nonce_field( 'flacso_consultas_export_csv' ); ?>

					<div class="flacso-cp-filters" style="margin-top:16px;">
						<div class="fg">
							<label>Base / Tabla</label>
							<select name="table">
								<option value="offer_inquiries" <?php selected( $table, 'offer_inquiries' ); ?>>Ofertas Académicas</option>
								<option value="seminar_inquiries" <?php selected( $table, 'seminar_inquiries' ); ?>>Seminarios</option>
								<option value="general_inquiries" <?php selected( $table, 'general_inquiries' ); ?>>Consultas Generales</option>
							</select>
						</div>
						<div class="fg">
							<label>Fecha Desde</label>
							<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
						</div>
						<div class="fg">
							<label>Fecha Hasta</label>
							<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
						</div>
						<div class="fg">
							<label>Modo de Exportación</label>
							<select name="export_mode">
								<option value="dedup">Deduplicado (1 fila por Correo + Oferta)</option>
								<option value="raw">Bruto (Todas las consultas individuales)</option>
							</select>
						</div>
					</div>

					<div style="margin:18px 0 10px; display:flex; gap:8px; align-items:center;">
						<strong>Seleccionar Ofertas / Seminarios:</strong>
						<button type="button" class="button button-small" id="flacso-exp-all">Seleccionar Todas</button>
						<button type="button" class="button button-small" id="flacso-exp-none">Limpiar Selección</button>
						<span style="font-size:12px; color:#64748b;">(Si no selecciona ninguna, se exportarán todas las ofertas del período)</span>
					</div>

					<div style="display:flex; flex-wrap:wrap; gap:8px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; max-height:300px; overflow-y:auto;">
						<?php foreach ( $items as $it ) : ?>
							<label class="flacso-chip">
								<input type="checkbox" name="items[]" value="<?php echo esc_attr( $it['nombre'] ); ?>" class="flacso-exp-chk" style="margin:0;">
								<span><?php echo esc_html( $it['nombre'] ); ?> <strong>(<?php echo (int) $it['total']; ?>)</strong></span>
							</label>
						<?php endforeach; ?>
					</div>

					<div style="margin-top:20px;">
						<button type="submit" class="button button-primary button-hero">📥 Descargar Archivo CSV</button>
					</div>
				</form>
			</div>
			<script>
			(function(){
				const chks = document.querySelectorAll('.flacso-exp-chk');
				const syncChips = () => {
					chks.forEach(c => c.closest('.flacso-chip').classList.toggle('is-selected', c.checked));
				};
				chks.forEach(c => c.addEventListener('change', syncChips));
				const allBtn = document.getElementById('flacso-exp-all');
				const noneBtn = document.getElementById('flacso-exp-none');
				if (allBtn) allBtn.addEventListener('click', () => { chks.forEach(c => c.checked = true); syncChips(); });
				if (noneBtn) noneBtn.addEventListener('click', () => { chks.forEach(c => c.checked = false); syncChips(); });
			})();
			</script>
			<?php
		}

		private static function render_date_range_form( string $tab, string $table, string $from, string $to ): void {
			?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="flacso-cp-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<div class="fg">
					<label>Base / Tabla</label>
					<select name="table">
						<option value="offer_inquiries" <?php selected( $table, 'offer_inquiries' ); ?>>Ofertas Académicas</option>
						<option value="seminar_inquiries" <?php selected( $table, 'seminar_inquiries' ); ?>>Seminarios</option>
					</select>
				</div>
				<div class="fg">
					<label>Desde</label>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				</div>
				<div class="fg">
					<label>Hasta</label>
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				</div>
				<div class="fg">
					<button type="submit" class="button button-primary">Aplicar Rango</button>
				</div>
			</form>
			<?php
		}

		/**
		 * Renderiza gráfico SVG interactivo de barras diarias + curva de media móvil 7 días.
		 */
		private static function render_svg_time_series( array $series ): void {
			if ( empty( $series ) ) {
				echo '<p style="color:#64748b;">Sin datos para graficar en este rango.</p>';
				return;
			}

			$max_val = 1;
			foreach ( $series as $pt ) {
				$cnt = (int) ( $pt['consultas'] ?? 0 );
				if ( $cnt > $max_val ) {
					$max_val = $cnt;
				}
			}

			$width  = 960;
			$height = 220;
			$pad_x  = 36;
			$pad_y  = 24;
			$plot_w = $width - ( $pad_x * 2 );
			$plot_h = $height - ( $pad_y * 2 );
			$n      = count( $series );
			$step_x = $n > 1 ? $plot_w / ( $n - 1 ) : $plot_w;
			$bar_w  = max( 3, min( 18, floor( $plot_w / max( 1, $n * 1.5 ) ) ) );

			$ma_points = array();
			foreach ( $series as $i => $pt ) {
				$x   = $pad_x + ( $i * $step_x );
				$ma7 = (float) ( $pt['promedioMovil7'] ?? 0 );
				$y_m = $pad_y + $plot_h - ( ( $ma7 / $max_val ) * $plot_h );
				$ma_points[] = round( $x, 1 ) . ',' . round( $y_m, 1 );
			}
			?>
			<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; overflow-x:auto;">
				<svg viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" style="width:100%; height:auto; max-height:240px;">
					<line x1="<?php echo (int) $pad_x; ?>" y1="<?php echo (int) ( $pad_y + $plot_h ); ?>" x2="<?php echo (int) ( $width - $pad_x ); ?>" y2="<?php echo (int) ( $pad_y + $plot_h ); ?>" stroke="#cbd5e1" stroke-width="1"/>
					<?php foreach ( $series as $i => $pt ) :
						$cnt   = (int) ( $pt['consultas'] ?? 0 );
						$ma7   = (float) ( $pt['promedioMovil7'] ?? 0 );
						$fecha = (string) ( $pt['fecha'] ?? '' );
						$x     = $pad_x + ( $i * $step_x );
						$bar_h = ( $cnt / $max_val ) * $plot_h;
						$y     = $pad_y + $plot_h - $bar_h;
						?>
						<rect x="<?php echo esc_attr( (string) round( $x - ( $bar_w / 2 ), 1 ) ); ?>"
							y="<?php echo esc_attr( (string) round( $y, 1 ) ); ?>"
							width="<?php echo (int) $bar_w; ?>"
							height="<?php echo esc_attr( (string) max( 1, round( $bar_h, 1 ) ) ); ?>"
							fill="#93c5fd" rx="2">
							<title><?php echo esc_html( $fecha . ': ' . $cnt . ' consultas (Media 7d: ' . $ma7 . ')' ); ?></title>
						</rect>
					<?php endforeach; ?>
					<?php if ( count( $ma_points ) > 1 ) : ?>
						<polyline fill="none" stroke="#1d3a72" stroke-width="2.5" points="<?php echo esc_attr( implode( ' ', $ma_points ) ); ?>" />
					<?php endif; ?>
				</svg>
				<div style="display:flex; justify-content:space-between; font-size:11.5px; color:#64748b; margin-top:6px;">
					<span>📅 Inicio: <?php echo esc_html( (string) ( $series[0]['fecha'] ?? '' ) ); ?></span>
					<span>🟦 Barras: Consultas diarias | 📈 Línea azul oscuro: Media móvil 7 días</span>
					<span>📅 Fin: <?php echo esc_html( (string) ( $series[ count( $series ) - 1 ]['fecha'] ?? '' ) ); ?></span>
				</div>
			</div>
			<?php
		}
	}
}
