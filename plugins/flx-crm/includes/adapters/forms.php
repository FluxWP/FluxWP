<?php
/**
 * Flux Forms → kişi + "Form" olayı. Formu dolduran e-posta ya da telefonla kişiye bağlanır; yoksa yeni kişi
 * (varsayılan rol "Aday") açılır. Form yanıtının kendisi Flux Forms'ta kalır.
 * Kanca: `efb_after_form_integration` (gönderim kaydedildikten sonra, Flux Forms otomasyonlarıyla aynı yer).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Forms {

	const KEY = 'forms';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_forms' ) && ( defined( 'EMSFB_PLUGIN_DIRECTORY' ) || class_exists( 'FLXF_Automations' ) );
	}

	public static function label() {
		return 'Flux Forms';
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'efb_after_form_integration', array( __CLASS__, 'on_submit' ), 30, 1 );
	}

	/**
	 * Yanıt satırlarından ad, e-posta, telefon çıkarır.
	 *
	 * @param array $rows [ {id_, name, type, value} ]
	 */
	public static function extract( array $rows ) {
		$out    = array( 'name' => '', 'surname' => '', 'email' => '', 'phone' => '', 'city' => '', 'consent' => false );
		$fields = array();
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) ) {
				continue;
			}
			$val = $r['value'] ?? '';
			if ( is_array( $val ) ) {
				$val = implode( ', ', array_filter( $val, 'is_scalar' ) );
			}
			$val   = trim( wp_strip_all_tags( (string) $val ) );
			$type  = (string) ( $r['type'] ?? '' );
			$label = mb_strtolower( wp_strip_all_tags( (string) ( $r['name'] ?? '' ) ) );
			$fields[] = array( 'id' => (string) ( $r['id_'] ?? '' ), 'label' => $label, 'type' => $type, 'value' => $val );
			if ( '' === $out['email'] && ( 'email' === $type || is_email( $val ) ) ) {
				$out['email'] = $val;
			} elseif ( '' === $out['phone'] && in_array( $type, array( 'mobile', 'tel' ), true ) ) {
				$out['phone'] = $val;
			} elseif ( '' === $out['city'] && preg_match( '/\b(şehir|il|city)\b/u', $label ) && 'text' === $type ) {
				$out['city'] = $val;
			} elseif ( in_array( $type, array( 'checkbox', 'switch', 'yesNo' ), true ) && preg_match( '/(izin|onay|kvkk|bülten|newsletter|consent|subscribe)/u', $label ) && '' !== $val && ! preg_match( '/^(0|no|hayır|false)$/iu', $val ) ) {
				$out['consent'] = true;
			}
		}
		if ( class_exists( 'FLXF_Automations' ) && method_exists( 'FLXF_Automations', 'guess_mapping' ) ) {
			$m    = FLXF_Automations::guess_mapping( $fields );
			$vals = array();
			foreach ( $fields as $f ) {
				$vals[ $f['id'] ] = $f['value'];
			}
			$out['name']    = $m['name_field'] && isset( $vals[ $m['name_field'] ] ) ? $vals[ $m['name_field'] ] : '';
			$out['surname'] = $m['surname_field'] && isset( $vals[ $m['surname_field'] ] ) ? $vals[ $m['surname_field'] ] : '';
		} else {
			foreach ( $fields as $f ) {
				if ( 'text' === $f['type'] && preg_match( '/(ad soyad|isim|name|\bad\b)/u', $f['label'] ) ) {
					$out['name'] = $f['value'];
					break;
				}
			}
		}
		if ( '' !== $out['surname'] && false === mb_stripos( $out['name'], $out['surname'] ) ) {
			$out['name'] = trim( $out['name'] . ' ' . $out['surname'] );
		}
		return $out;
	}

	public static function on_submit( $ctx ) {
		if ( ! is_array( $ctx ) || 'form_submit' !== ( $ctx['event_type'] ?? '' ) ) {
			return;
		}
		try {
			$rows = is_array( $ctx['submitted_values'] ?? null ) ? $ctx['submitted_values'] : array();
			$title = '';
			if ( isset( $ctx['form_fields'][0]['formName'] ) ) {
				$title = wp_strip_all_tags( (string) $ctx['form_fields'][0]['formName'] );
			}
			global $wpdb;
			$track  = (string) ( $ctx['track_code'] ?? '' );
			$msg_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT msg_id FROM ' . $wpdb->prefix . 'emsfb_msg_ WHERE track = %s ORDER BY msg_id DESC LIMIT 1', $track ) ); // phpcs:ignore WordPress.DB
			self::record( (int) ( $ctx['form_id'] ?? 0 ), $track, $title, $rows, flxcrm_now(), (string) ( $ctx['page_url'] ?? '' ), $msg_id );
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm forms: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function record( $form_id, $track, $title, array $rows, $at, $page_url = '', $msg_id = 0 ) {
		if ( '' === $track ) {
			return 0;
		}
		$x = self::extract( $rows );
		if ( '' === flxcrm_email( $x['email'] ) && '' === flxcrm_phone( $x['phone'] ) ) {
			return 0;
		}
		$role  = (string) flxcrm_setting( 'role_forms' );
		$parts = wp_parse_url( $page_url );
		$q     = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
		}
		$cid = FLXCRM_Contacts::upsert(
			array(
				'name'          => $x['name'],
				'email'         => $x['email'],
				'phone'         => $x['phone'],
				'city'          => $x['city'],
				'roles'         => '' !== $role ? array( $role ) : array(),
				'source'        => self::KEY,
				'consent_email' => $x['consent'],
				'utm_source'    => isset( $q['utm_source'] ) ? (string) $q['utm_source'] : '',
				'utm_campaign'  => isset( $q['utm_campaign'] ) ? (string) $q['utm_campaign'] : '',
				'at'            => $at,
			)
		);
		if ( ! $cid ) {
			return 0;
		}
		$answers = array();
		foreach ( array_slice( $rows, 0, 30 ) as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) || in_array( (string) ( $r['type'] ?? '' ), array( 'payment', 'w_link', 'file', 'dadfile', 'esign' ), true ) ) {
				continue;
			}
			$v = $r['value'] ?? '';
			$v = is_array( $v ) ? implode( ', ', array_filter( $v, 'is_scalar' ) ) : (string) $v;
			if ( '' !== trim( $v ) ) {
				$answers[] = array( mb_substr( wp_strip_all_tags( (string) ( $r['name'] ?? '' ) ), 0, 80 ), mb_substr( wp_strip_all_tags( $v ), 0, 300 ) );
			}
		}
		FLXCRM_Events::add(
			$cid,
			'form',
			array(
				'source' => self::KEY,
				'uniq'   => 'forms:' . sanitize_key( $track ),
				'title'  => '' !== $title ? $title : sprintf( /* translators: %d: form id */ __( 'Form #%d', 'flx-crm' ), $form_id ),
				'url'    => $msg_id ? 'admin.php?page=Emsfb&flxf_form=' . (int) $form_id . '&flxf_msg=' . (int) $msg_id : '',
				'data'   => array( 'form_id' => $form_id, 'track' => $track, 'answers' => $answers ),
				'at'     => $at,
			)
		);
		return $cid;
	}

	public static function backfill( $page, $per ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_msg_';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) { // phpcs:ignore WordPress.DB
			return array( 'done' => true, 'scanned' => 0, 'total' => 0 );
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT msg_id, form_id, track, form_title_x, content, date FROM $t ORDER BY msg_id LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$content = json_decode( (string) $r['content'], true );
			if ( ! is_array( $content ) ) {
				$content = json_decode( str_replace( '\\', '', (string) $r['content'] ), true );
			}
			if ( is_array( $content ) ) {
				$at = $r['date'] ? get_gmt_from_date( $r['date'] ) : flxcrm_now();
				self::record( (int) $r['form_id'], (string) $r['track'], (string) $r['form_title_x'], $content, $at, '', (int) $r['msg_id'] );
			}
		}
		return array( 'done' => count( (array) $rows ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
