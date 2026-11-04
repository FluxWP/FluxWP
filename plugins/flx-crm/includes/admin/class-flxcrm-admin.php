<?php
/**
 * Yönetim: "CRM" menüsü (Genel bakış, Kişiler, Segmentler, Ayarlar), form işleyicileri ve ortak parçalar.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

require_once FLXCRM_DIR . 'includes/admin/page-overview.php';
require_once FLXCRM_DIR . 'includes/admin/page-contacts.php';
require_once FLXCRM_DIR . 'includes/admin/page-contact.php';
require_once FLXCRM_DIR . 'includes/admin/page-segments.php';
require_once FLXCRM_DIR . 'includes/admin/page-settings.php';

final class FLXCRM_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 55 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'contact_save', 'contact_delete', 'event_add', 'event_delete', 'tag_add', 'tag_remove', 'segment_save', 'segment_delete', 'settings_save', 'csv', 'bulk' ) as $a ) {
			add_action( 'admin_post_flxcrm_' . $a, array( __CLASS__, 'post_' . $a ) );
		}
		add_action( 'wp_ajax_flxcrm_score', array( __CLASS__, 'ajax_score' ) );
	}

	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2v2"/><path d="M7 22v-2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><path d="M8 2v2"/><circle cx="12" cy="11" r="3"/><rect x="3" y="4" width="18" height="18" rx="2"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function cap_view() {
		return current_user_can( 'manage_options' ) ? 'manage_options' : FLXCRM_CAP_VIEW;
	}

	public static function cap_manage() {
		return current_user_can( 'manage_options' ) ? 'manage_options' : FLXCRM_CAP_MANAGE;
	}

	public static function menu() {
		$view = self::cap_view();
		add_menu_page( __( 'CRM', 'flx-crm' ), __( 'CRM', 'flx-crm' ), $view, 'flx-crm', array( 'FLXCRM_Page_Overview', 'render' ), self::menu_icon(), 57 );
		add_submenu_page( 'flx-crm', __( 'Genel bakış', 'flx-crm' ), __( 'Genel bakış', 'flx-crm' ), $view, 'flx-crm', array( 'FLXCRM_Page_Overview', 'render' ) );
		add_submenu_page( 'flx-crm', __( 'Kişiler', 'flx-crm' ), __( 'Kişiler', 'flx-crm' ), $view, 'flx-crm-contacts', array( __CLASS__, 'route_contacts' ) );
		add_submenu_page( 'flx-crm', __( 'Segmentler', 'flx-crm' ), __( 'Segmentler', 'flx-crm' ), $view, 'flx-crm-segments', array( 'FLXCRM_Page_Segments', 'render' ) );
		add_submenu_page( 'flx-crm', __( 'CRM ayarları', 'flx-crm' ), __( 'Ayarlar', 'flx-crm' ), self::cap_manage(), 'flx-crm-settings', array( 'FLXCRM_Page_Settings', 'render' ) );
	}

	public static function route_contacts() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['id'] ) || ( isset( $_GET['action'] ) && 'new' === $_GET['action'] ) ) {
			FLXCRM_Page_Contact::render();
			return;
		}
		FLXCRM_Page_Contacts::render();
	}

	public static function is_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'flx-crm' );
	}

	public static function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxcrm-admin', FLXCRM_URL . 'assets/admin.css', array(), FLXCRM_VERSION );
		wp_enqueue_script( 'flxcrm-admin', FLXCRM_URL . 'assets/admin.js', array(), FLXCRM_VERSION, true );
		wp_localize_script(
			'flxcrm-admin',
			'flxcrmAdmin',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'rest'      => esc_url_raw( rest_url( FLXCRM_REST::NS ) ),
				'nonce'     => wp_create_nonce( 'flxcrm_admin' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'working'  => __( '%1$s: %2$s / %3$s', 'flx-crm' ),
					'done'     => __( 'Bitti. Sayfa yenileniyor…', 'flx-crm' ),
					'fail'     => __( 'Bir şey ters gitti. Tekrar deneyin.', 'flx-crm' ),
					'confirm'  => __( 'Emin misiniz? Bu işlem geri alınamaz.', 'flx-crm' ),
					'matches'  => __( '%s kişi uyuyor', 'flx-crm' ),
					'counting' => __( 'Sayılıyor…', 'flx-crm' ),
					'scoring'  => __( 'Hesaplanıyor…', 'flx-crm' ),
					'scored'   => __( '%s kişi skorlandı. Sayfa yenileniyor…', 'flx-crm' ),
					'selected' => __( '%s kişi seçildi', 'flx-crm' ),
					'remove'   => __( 'Kuralı kaldır', 'flx-crm' ),
					'and'      => __( 've', 'flx-crm' ),
					'or'       => __( 'veya', 'flx-crm' ),
					'days'     => __( 'gün', 'flx-crm' ),
					'empty'    => __( 'Kural yok: tüm kişiler', 'flx-crm' ),
					'none'     => __( 'Uyan kişi yok.', 'flx-crm' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Form işleyicileri                                                 */
	/* ---------------------------------------------------------------- */

	private static function guard( $action ) {
		check_admin_referer( 'flxcrm_' . $action );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
	}

	private static function back( $url, $msg = '', $err = '' ) {
		$args = array();
		if ( '' !== $msg ) {
			$args['flxcrm_msg'] = $msg;
		}
		if ( '' !== $err ) {
			$args['flxcrm_err'] = rawurlencode( $err );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
	}

	public static function post_contact_save() {
		self::guard( 'contact_save' );
		$id   = absint( self::post( 'id', 0 ) );
		$data = array(
			'name'     => (string) self::post( 'name' ),
			'email'    => (string) self::post( 'email' ),
			'phone'    => (string) self::post( 'phone' ),
			'company'  => (string) self::post( 'company' ),
			'city'     => (string) self::post( 'city' ),
			'note'     => (string) self::post( 'note' ),
			'owner_id' => absint( self::post( 'owner_id', 0 ) ),
			'roles'    => array_map( 'sanitize_key', (array) self::post( 'roles', array() ) ),
		);
		foreach ( FLXCRM_Contacts::CONSENTS as $k ) {
			$data[ 'consent_' . $k ] = self::post( 'consent_' . $k ) ? 1 : 0;
		}
		if ( ! $id ) {
			$email = flxcrm_email( $data['email'] );
			$phone = flxcrm_phone( $data['phone'] );
			if ( '' === $email && '' === $phone ) {
				self::back( flxcrm_admin_url( 'contacts', array( 'action' => 'new' ) ), '', __( 'E-posta ya da telefon gerekli.', 'flx-crm' ) );
			}
			$existing = FLXCRM_Contacts::find( array( 'email' => $email, 'phone' => $phone ) );
			if ( $existing ) {
				self::back( flxcrm_contact_url( $existing ), 'exists' );
			}
			$id = FLXCRM_Contacts::upsert( array_merge( $data, array( 'source' => 'manual', 'roles' => array() ) ) );
			if ( ! $id ) {
				self::back( flxcrm_admin_url( 'contacts', array( 'action' => 'new' ) ), '', __( 'Kişi kaydedilemedi.', 'flx-crm' ) );
			}
			FLXCRM_Contacts::set_roles( $id, $data['roles'] );
			$tags = array_filter( array_map( 'trim', explode( ',', (string) self::post( 'tags' ) ) ) );
			foreach ( $tags as $tag ) {
				FLXCRM_Tags::attach( $id, $tag );
			}
			FLXCRM_Contacts::flush_stats();
			self::back( flxcrm_contact_url( $id ), 'created' );
		}
		$res = FLXCRM_Contacts::update( $id, $data );
		FLXCRM_Contacts::flush_stats();
		self::back( flxcrm_contact_url( $id ), is_wp_error( $res ) ? '' : 'saved', is_wp_error( $res ) ? $res->get_error_message() : '' );
	}

	public static function post_contact_delete() {
		self::guard( 'contact_delete' );
		FLXCRM_Contacts::delete( absint( self::post( 'id', 0 ) ) );
		FLXCRM_Contacts::flush_stats();
		self::back( flxcrm_admin_url( 'contacts' ), 'deleted' );
	}

	public static function post_event_add() {
		self::guard( 'event_add' );
		$id   = absint( self::post( 'id', 0 ) );
		$type = sanitize_key( (string) self::post( 'type', 'note' ) );
		$text = sanitize_textarea_field( (string) self::post( 'body' ) );
		if ( FLXCRM_Contacts::get( $id ) && '' !== trim( $text ) ) {
			FLXCRM_REST::manual_event( $id, in_array( $type, flxcrm_manual_event_types(), true ) ? $type : 'note', $text );
		}
		self::back( flxcrm_contact_url( $id ) . '#timeline', 'noted' );
	}

	public static function post_event_delete() {
		self::guard( 'event_delete' );
		FLXCRM_Events::delete( absint( self::post( 'event', 0 ) ) );
		self::back( flxcrm_contact_url( absint( self::post( 'id', 0 ) ) ) . '#timeline' );
	}

	public static function post_tag_add() {
		self::guard( 'tag_add' );
		$id = absint( self::post( 'id', 0 ) );
		if ( FLXCRM_Contacts::get( $id ) ) {
			foreach ( array_filter( array_map( 'trim', explode( ',', (string) self::post( 'tag' ) ) ) ) as $tag ) {
				FLXCRM_Tags::attach( $id, $tag );
			}
		}
		self::back( flxcrm_contact_url( $id ) );
	}

	public static function post_tag_remove() {
		self::guard( 'tag_remove' );
		$id = absint( self::post( 'id', 0 ) );
		FLXCRM_Tags::detach( $id, absint( self::post( 'tag', 0 ) ) );
		self::back( flxcrm_contact_url( $id ) );
	}

	public static function post_segment_save() {
		self::guard( 'segment_save' );
		$id    = absint( self::post( 'id', 0 ) );
		$rules = json_decode( (string) self::post( 'rules_json' ), true );
		$res   = FLXCRM_Segments::save(
			array(
				'name'        => (string) self::post( 'name' ),
				'description' => (string) self::post( 'description' ),
				'rules'       => is_array( $rules ) ? $rules : array(),
			),
			$id
		);
		if ( is_wp_error( $res ) ) {
			self::back( flxcrm_admin_url( 'segments', array( 'edit' => $id ? $id : 'new' ) ), '', $res->get_error_message() );
		}
		self::back( flxcrm_admin_url( 'segments' ), 'saved' );
	}

	public static function post_segment_delete() {
		self::guard( 'segment_delete' );
		FLXCRM_Segments::delete( absint( self::post( 'id', 0 ) ) );
		self::back( flxcrm_admin_url( 'segments' ), 'deleted' );
	}

	public static function post_settings_save() {
		self::guard( 'settings_save' );
		$v = array();
		foreach ( array( 'src_donate', 'src_commerce', 'src_chat', 'src_forms', 'src_users', 'chat_events' ) as $k ) {
			$v[ $k ] = self::post( $k ) ? 1 : 0;
		}
		$known = flxcrm_roles();
		foreach ( array( 'role_donate', 'role_commerce', 'role_forms', 'role_chat', 'role_users' ) as $k ) {
			$r       = sanitize_key( (string) self::post( $k ) );
			$v[ $k ] = isset( $known[ $r ] ) ? $r : '';
		}
		$v['risk_threshold'] = min( 100, max( 10, absint( self::post( 'risk_threshold', 70 ) ) ) );
		$v['active_days']    = min( 730, max( 7, absint( self::post( 'active_days', 90 ) ) ) );
		$cur                 = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) self::post( 'base_currency' ) ) );
		$v['base_currency']  = 3 === strlen( $cur ) ? $cur : '';
		/* Roller: yerleşiklerin adı/rengi değişebilir; yeni rol eklenebilir; kendi rolü silinebilir. */
		$roles = array();
		$in    = (array) self::post( 'roles', array() );
		foreach ( $in as $k => $r ) {
			$key   = sanitize_key( is_array( $r ) && ! empty( $r['key'] ) ? $r['key'] : $k );
			$label = is_array( $r ) ? trim( sanitize_text_field( (string) ( $r['label'] ?? '' ) ) ) : '';
			if ( '' === $key || ! empty( $r['delete'] ) || ( '' === $label && ! isset( $known[ $key ] ) ) ) {
				continue;
			}
			$color          = sanitize_hex_color( (string) ( $r['color'] ?? '' ) );
			$roles[ $key ] = array( 'label' => mb_substr( $label, 0, 40 ), 'color' => $color ? $color : '#64748b' );
		}
		$new_label = trim( sanitize_text_field( (string) self::post( 'new_role_label' ) ) );
		if ( '' !== $new_label ) {
			$key = sanitize_key( remove_accents( $new_label ) );
			$key = '' !== $key ? substr( str_replace( '-', '_', $key ), 0, 24 ) : 'role' . wp_rand( 100, 999 );
			if ( ! isset( $roles[ $key ] ) && ! isset( $known[ $key ] ) ) {
				$color         = sanitize_hex_color( (string) self::post( 'new_role_color' ) );
				$roles[ $key ] = array( 'label' => mb_substr( $new_label, 0, 40 ), 'color' => $color ? $color : '#64748b' );
			}
		}
		$v['roles'] = $roles;
		flxcrm_save_settings( $v );
		FLXCRM_Contacts::flush_stats();
		self::back( flxcrm_admin_url( 'settings' ), 'saved' );
	}

	/** Seçili kişilere toplu işlem: etiket ekle, rol ekle, arşivle, sil. */
	public static function post_bulk() {
		self::guard( 'bulk' );
		$ids    = array_filter( array_map( 'absint', (array) self::post( 'ids', array() ) ) );
		$action = sanitize_key( (string) self::post( 'bulk_action' ) );
		$value  = (string) self::post( 'bulk_value' );
		$back   = esc_url_raw( (string) self::post( 'back', flxcrm_admin_url( 'contacts' ) ) );
		$n      = 0;
		foreach ( $ids as $id ) {
			if ( ! FLXCRM_Contacts::get( $id ) ) {
				continue;
			}
			switch ( $action ) {
				case 'tag':
					if ( '' !== trim( $value ) ) {
						FLXCRM_Tags::attach( $id, $value );
						++$n;
					}
					break;
				case 'role':
					FLXCRM_Contacts::add_roles( $id, array( sanitize_key( $value ) ) );
					++$n;
					break;
				case 'archive':
					FLXCRM_Contacts::update( $id, array( 'status' => 'archived' ) );
					++$n;
					break;
				case 'restore':
					FLXCRM_Contacts::update( $id, array( 'status' => 'active' ) );
					++$n;
					break;
				case 'delete':
					FLXCRM_Contacts::delete( $id );
					++$n;
					break;
			}
		}
		FLXCRM_Contacts::flush_stats();
		self::back( $back, 'bulk' . $n );
	}

	/** CSV: kişi listesi (filtre ya da segment). */
	public static function post_csv() {
		check_admin_referer( 'flxcrm_csv' );
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$f   = FLXCRM_Page_Contacts::filters_from( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sid = absint( self::post( 'segment_id', 0 ) );
		if ( $sid && ( $s = FLXCRM_Segments::get( $sid ) ) ) { // phpcs:ignore
			$f['rules'] = $s['rules'];
		}
		$ids = FLXCRM_Contacts::ids( $f );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=crm-' . gmdate( 'Ymd-His' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'id', __( 'Ad', 'flx-crm' ), __( 'E-posta', 'flx-crm' ), __( 'Telefon', 'flx-crm' ), __( 'Roller', 'flx-crm' ), __( 'Segment', 'flx-crm' ), __( 'Toplam değer', 'flx-crm' ), __( 'Bağış', 'flx-crm' ), __( 'Sipariş', 'flx-crm' ), __( 'Son ödeme', 'flx-crm' ), __( 'Kayıp riski', 'flx-crm' ), __( 'Şehir', 'flx-crm' ), __( 'E-posta izni', 'flx-crm' ), __( 'SMS izni', 'flx-crm' ), __( 'WhatsApp izni', 'flx-crm' ), __( 'Etiketler', 'flx-crm' ) ) );
		global $wpdb;
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . flxcrm_table( 'contacts' ) . ' WHERE id IN (' . implode( ',', $chunk ) . ') ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
			$tags = FLXCRM_Tags::of_many( $chunk );
			foreach ( (array) $rows as $r ) {
				$roles = array_map( 'flxcrm_role_label', flxcrm_unpack_roles( $r['roles'] ) );
				fputcsv(
					$out,
					array(
						$r['id'],
						self::csv_safe( $r['name'] ),
						self::csv_safe( $r['email'] ),
						'' !== $r['phone'] ? '+' . $r['phone'] : '',
						implode( ', ', $roles ),
						'' !== $r['segment'] ? flxcrm_segment_label( $r['segment'] ) : '',
						$r['ltv'],
						$r['donations'],
						$r['orders'],
						$r['last_paid_at'] ? get_date_from_gmt( $r['last_paid_at'], 'Y-m-d' ) : '',
						$r['churn'],
						self::csv_safe( $r['city'] ),
						$r['consent_email'],
						$r['consent_sms'],
						$r['consent_whatsapp'],
						self::csv_safe( implode( ', ', wp_list_pluck( $tags[ (int) $r['id'] ] ?? array(), 'name' ) ) ),
					)
				);
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Excel formül enjeksiyonuna karşı. */
	private static function csv_safe( $v ) {
		$v = (string) $v;
		return '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ? "'" . $v : $v;
	}

	public static function ajax_score() {
		check_ajax_referer( 'flxcrm_admin' );
		if ( ! flxcrm_can( true ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( FLXCRM_Scoring::run() );
	}

	/* ---------------------------------------------------------------- */
	/* Ortak parçalar                                                    */
	/* ---------------------------------------------------------------- */

	public static function open( $title, $lead = '', $actions = '', $tabs = true ) {
		echo '<div class="wrap flxcrm">';
		echo '<div class="flxcrm-head"><div><h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== $lead ) {
			echo '<p class="flxcrm-lead">' . esc_html( $lead ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $actions ) {
			echo '<div class="flxcrm-head__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		if ( $tabs ) {
			self::tabs();
		}
		self::notices();
	}

	public static function close() {
		echo '</div>';
	}

	public static function tabs() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'flx-crm'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'flx-crm'          => __( 'Genel bakış', 'flx-crm' ),
			'flx-crm-contacts' => __( 'Kişiler', 'flx-crm' ),
			'flx-crm-segments' => __( 'Segmentler', 'flx-crm' ),
		);
		if ( flxcrm_can( true ) ) {
			$tabs['flx-crm-settings'] = __( 'Ayarlar', 'flx-crm' );
		}
		echo '<nav class="flxcrm-tabs" aria-label="' . esc_attr__( 'CRM', 'flx-crm' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$on = $slug === $page;
			echo '<a class="flxcrm-tab' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/** Sekmesiz sayfalarda (kişi kartı) yalnız bildirimler. */
	public static function open_notices_only() {
		self::notices();
	}

	private static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification
		$msg = isset( $_GET['flxcrm_msg'] ) ? sanitize_key( $_GET['flxcrm_msg'] ) : '';
		$err = isset( $_GET['flxcrm_err'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['flxcrm_err'] ) ) ) : '';
		// phpcs:enable
		$map = array(
			'saved'   => __( 'Kaydedildi.', 'flx-crm' ),
			'created' => __( 'Kişi eklendi.', 'flx-crm' ),
			'deleted' => __( 'Silindi.', 'flx-crm' ),
			'noted'   => __( 'Zaman tüneline eklendi.', 'flx-crm' ),
			'exists'  => __( 'Bu e-posta ya da telefonla kayıtlı kişi zaten var; kartı açıldı.', 'flx-crm' ),
		);
		if ( 0 === strpos( $msg, 'bulk' ) ) {
			/* translators: %s: number of contacts */
			$map[ $msg ] = sprintf( __( '%s kişi güncellendi.', 'flx-crm' ), number_format_i18n( (int) substr( $msg, 4 ) ) );
		}
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="flxcrm-notice is-ok" role="status">' . flxcrm_icon( 'check', 16 ) . esc_html( $map[ $msg ] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( '' !== $err ) {
			echo '<div class="flxcrm-notice is-bad" role="alert">' . flxcrm_icon( 'alert', 16 ) . esc_html( $err ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function kpi( $icon, $label, $value, $hint = '', $tone = '' ) {
		echo '<div class="flxcrm-kpi' . ( $tone ? ' is-' . esc_attr( $tone ) : '' ) . '"><span class="flxcrm-kpi__icon">' . flxcrm_icon( $icon, 18 ) . '</span><div class="flxcrm-kpi__body"><span class="flxcrm-kpi__label">' . esc_html( $label ) . '</span><strong class="flxcrm-kpi__value">' . esc_html( $value ) . '</strong>' . ( '' !== $hint ? '<span class="flxcrm-kpi__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function role_pills( $packed ) {
		$roles = flxcrm_roles();
		$out   = '';
		foreach ( flxcrm_unpack_roles( $packed ) as $r ) {
			$c    = $roles[ $r ]['color'] ?? '#64748b';
			$out .= '<span class="flxcrm-role" style="--rc:' . esc_attr( $c ) . '">' . esc_html( $roles[ $r ]['label'] ?? $r ) . '</span>';
		}
		return $out;
	}

	public static function segment_badge( $seg ) {
		if ( '' === (string) $seg ) {
			return '<span class="flxcrm-badge">' . esc_html__( 'Ödemesi yok', 'flx-crm' ) . '</span>';
		}
		$s    = flxcrm_rfm_segments();
		$tone = $s[ $seg ][2] ?? '';
		return '<span class="flxcrm-badge is-' . esc_attr( $tone ) . '" title="' . esc_attr( $s[ $seg ][1] ?? '' ) . '">' . esc_html( flxcrm_segment_label( $seg ) ) . '</span>';
	}

	public static function churn_meter( $churn, $has_paid ) {
		if ( ! $has_paid ) {
			return '<span class="flxcrm-muted">—</span>';
		}
		$tone = $churn >= (int) flxcrm_setting( 'risk_threshold' ) ? 'bad' : ( $churn >= 40 ? 'warn' : 'ok' );
		return '<span class="flxcrm-meter is-' . $tone . '" title="' . esc_attr( sprintf( /* translators: %s: percent */ __( 'Kayıp riski %%%s', 'flx-crm' ), $churn ) ) . '"><span style="width:' . (int) $churn . '%"></span></span><span class="flxcrm-meter__val">%' . (int) $churn . '</span>';
	}

	public static function avatar( array $c, $size = '' ) {
		$name = flxcrm_display_name( $c );
		$hue  = (int) ( crc32( (string) $c['id'] ) % 360 );
		return '<span class="flxcrm-avatar' . ( $size ? ' is-' . esc_attr( $size ) : '' ) . '" style="--h:' . $hue . '" aria-hidden="true">' . esc_html( flxcrm_initials( $name ) ) . '</span>';
	}

	/** Olay URL'si: göreli yol admin'e bağlanır. */
	public static function event_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return '';
		}
		return preg_match( '#^https?://#', $url ) ? $url : admin_url( $url );
	}

	/** Basit sayfalama (Flux Admin varsa onun). */
	public static function pagination( $total, $per, $paged ) {
		if ( function_exists( 'flxa_list_pagination' ) ) {
			flxa_list_pagination( $total, $per, $paged );
			return;
		}
		$pages = (int) ceil( $total / max( 1, $per ) );
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="flxcrm-pager">' . wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		) . '</div>';
	}
}
