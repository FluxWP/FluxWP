<?php
/**
 * Flux Donate 2.11: extra detail tabs.
 *
 * A tab (schema flxb-detail-tab/1) adds a group of fields to a tracked record's page or a donation's
 * page, optionally only for some donation types. Values live in the row's meta under ext.<tab id>.
 *
 * Sources, later wins on the same id: filter flxb_detail_tabs (other plugins: id => array or .json
 * path), then option flxb_detail_tabs (Settings › Extra tabs; preset importer type flxb_detail_tab).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Detail_Tabs {

	const SCHEMA = 'flxb-detail-tab/1';
	const OPTION = 'flxb_detail_tabs';

	private static $cache = null;

	public static function init() {
		add_action( 'admin_post_flxb_tab_save', array( __CLASS__, 'handle_save' ) );
	}

	public static function field_types() {
		return array( 'text', 'textarea', 'number', 'date', 'select', 'checkbox', 'url', 'phone', 'email' );
	}

	public static function clean( $t, array &$errors = array() ) {
		if ( ! is_array( $t ) ) {
			$errors[] = __( 'Each tab must be a JSON object.', 'flx-donate' );
			return null;
		}
		$id = sanitize_key( (string) ( $t['id'] ?? '' ) );
		if ( '' === $id ) {
			$errors[] = __( 'A tab needs an "id".', 'flx-donate' );
			return null;
		}
		$on = (string) ( $t['on'] ?? 'item' );
		if ( ! in_array( $on, array( 'item', 'donation' ), true ) ) {
			/* translators: 1: tab id, 2: value */
			$errors[] = sprintf( __( 'Tab%1$s: "on" must be item or donation, not%2$s.', 'flx-donate' ), $id, $on );
			$on       = 'item';
		}
		$fields = array();
		foreach ( (array) ( $t['fields'] ?? array() ) as $f ) {
			$k = sanitize_key( (string) ( $f['key'] ?? '' ) );
			if ( '' === $k ) {
				/* translators: %s: tab id */
				$errors[] = sprintf( __( 'Tab%s: every field needs a "key".', 'flx-donate' ), $id );
				continue;
			}
			$type = (string) ( $f['type'] ?? 'text' );
			if ( ! in_array( $type, self::field_types(), true ) ) {
				/* translators: 1: tab id, 2: field key, 3: type */
				$errors[] = sprintf( __( 'Tab%1$s, field%2$s: unknown type%3$s.', 'flx-donate' ), $id, $k, $type );
				$type     = 'text';
			}
			$opts = array();
			foreach ( (array) ( $f['options'] ?? array() ) as $ok => $ov ) {
				if ( is_int( $ok ) && is_scalar( $ov ) ) {
					$ok = (string) $ov;
				}
				$opts[ sanitize_text_field( (string) $ok ) ] = $ov;
			}
			$fields[ $k ] = array(
				'key'     => $k,
				'label'   => $f['label'] ?? $k,
				'type'    => $type,
				'options' => $opts,
				'help'    => $f['help'] ?? '',
			);
		}
		return array(
			'schema'     => self::SCHEMA,
			'id'         => $id,
			'title'      => $t['title'] ?? $id,
			'on'         => $on,
			'type_ids'   => array_values( array_filter( array_map( 'absint', (array) ( $t['type_ids'] ?? array() ) ) ) ),
			'type_slugs' => array_values( array_filter( array_map( 'sanitize_title', (array) ( $t['type_slugs'] ?? array() ) ) ) ),
			'fields'     => $fields,
			'order'      => (int) ( $t['order'] ?? 50 ),
		);
	}

	private static function load( $v ) {
		if ( is_string( $v ) && is_readable( $v ) ) {
			$v = json_decode( (string) file_get_contents( $v ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		return is_array( $v ) ? $v : null;
	}

	public static function builtin() {
		$out = array();
		foreach ( (array) apply_filters( 'flxb_detail_tabs', array() ) as $k => $t ) {
			$t = self::load( $t );
			if ( $t ) {
				$id         = sanitize_key( (string) ( $t['id'] ?? $k ) );
				$t['id']    = $id;
				$out[ $id ] = $t;
			}
		}
		return $out;
	}

	/** Site's own tabs: list of raw arrays (as edited in Settings). */
	public static function custom() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? array_values( $o ) : array();
	}

	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$raw = self::builtin();
		foreach ( self::custom() as $t ) {
			if ( is_array( $t ) && ! empty( $t['id'] ) ) {
				$raw[ sanitize_key( $t['id'] ) ] = $t;
			}
		}
		$out = array();
		foreach ( $raw as $t ) {
			$c = self::clean( $t );
			if ( $c ) {
				$out[ $c['id'] ] = $c;
			}
		}
		uasort(
			$out,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		self::$cache = $out;
		return $out;
	}

	/**
	 * Save the site's tabs from JSON text (an array of tab objects).
	 *
	 * @return true|WP_Error
	 */
	public static function save_json( $json ) {
		$raw = json_decode( (string) $json, true );
		if ( '' === trim( (string) $json ) ) {
			$raw = array();
		}
		if ( ! is_array( $raw ) ) {
			/* translators: %s: JSON error */
			return new WP_Error( 'flxb_json', sprintf( __( 'The JSON could not be read:%s', 'flx-donate' ), json_last_error_msg() ) );
		}
		if ( isset( $raw['id'] ) ) {
			$raw = array( $raw ); // a single tab is fine too
		}
		$e = array();
		foreach ( $raw as $t ) {
			self::clean( $t, $e );
		}
		if ( $e ) {
			return new WP_Error( 'flxb_tab', implode( ' ', $e ) );
		}
		update_option( self::OPTION, array_values( $raw ), false );
		self::$cache = null;
		return true;
	}

	/** Tabs that apply to a record (on = item|donation) of a given donation type. */
	public static function for_row( $on, $type_id ) {
		$out = array();
		foreach ( self::all() as $id => $t ) {
			if ( $t['on'] !== $on || ! $t['fields'] ) {
				continue;
			}
			$ids = $t['type_ids'];
			foreach ( $t['type_slugs'] as $slug ) {
				$ty    = FLXB_Types::get_by_slug( $slug );
				$ids[] = $ty ? (int) $ty['id'] : -1;
			}
			if ( $ids && ! in_array( (int) $type_id, $ids, true ) ) {
				continue;
			}
			$out[ $id ] = $t;
		}
		return $out;
	}

	private static function row( $on, $id ) {
		return 'donation' === $on ? FLXB_Donations::get( $id ) : FLXB_Track::get( $id );
	}

	/** Stored values of one tab on a row. */
	public static function values( $on, array $row, $tab_id ) {
		$m = 'donation' === $on ? FLXB_Donations::meta( $row ) : FLXB_Track::meta( $row );
		return (array) ( $m['ext'][ $tab_id ] ?? array() );
	}

	private static function clean_value( array $f, $v ) {
		switch ( $f['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $v );
			case 'number':
				return '' === trim( (string) $v ) ? '' : (float) str_replace( ',', '.', (string) $v );
			case 'date':
				$d = flxb_parse_date( (string) $v );
				return $d ? $d : '';
			case 'checkbox':
				return ! empty( $v ) ? 1 : 0;
			case 'select':
				return isset( $f['options'][ (string) $v ] ) ? (string) $v : '';
			case 'url':
				return esc_url_raw( (string) $v );
			case 'email':
				return sanitize_email( (string) $v );
			default:
				return sanitize_text_field( (string) $v );
		}
	}

	/**
	 * Store values of one tab (unknown keys are dropped).
	 *
	 * @return bool
	 */
	public static function save_values( $on, $id, $tab_id, array $in ) {
		global $wpdb;
		$row = self::row( $on, $id );
		$tab = self::all()[ $tab_id ] ?? null;
		if ( ! $row || ! $tab || $tab['on'] !== $on ) {
			return false;
		}
		$vals = array();
		foreach ( $tab['fields'] as $k => $f ) {
			$vals[ $k ] = self::clean_value( $f, $in[ $k ] ?? '' );
		}
		$old = self::values( $on, $row, $tab_id );
		if ( 'donation' === $on ) {
			$m                  = FLXB_Donations::meta( $row );
			$m['ext'][ $tab_id ] = $vals;
			FLXB_Donations::update_meta( $id, array( 'ext' => $m['ext'] ) );
		} else {
			$m                  = FLXB_Track::meta( $row );
			$m['ext']           = (array) ( $m['ext'] ?? array() );
			$m['ext'][ $tab_id ] = $vals;
			$wpdb->update( flxb_table( 'items' ), array( 'meta' => wp_json_encode( $m ), 'updated_at' => flxb_now() ), array( 'id' => (int) $id ) );
			$from = array();
			$to   = array();
			foreach ( $vals as $k => $v ) {
				if ( (string) ( $old[ $k ] ?? '' ) !== (string) $v ) {
					$label       = FLXB_Reports::text( $tab['fields'][ $k ]['label'] );
					// 2.11.1: values as people read them (select option label, Yes/No, date), not the stored key.
					$from[ $label ] = '' === (string) ( $old[ $k ] ?? '' ) ? '' : self::shown( $tab['fields'][ $k ], $old[ $k ] );
					$to[ $label ]   = '' === (string) $v ? '' : self::shown( $tab['fields'][ $k ], $v );
				}
			}
			if ( $to ) {
				FLXB_Track::log( $id, 'edited', FLXB_Reports::text( $tab['title'] ), array( 'from' => $from, 'to' => $to ) );
			}
		}
		do_action( 'flxb_detail_tab_saved', $on, (int) $id, $tab_id, $vals, $old );
		return true;
	}

	private static function shown( array $f, $v ) {
		if ( '' === (string) $v ) {
			return '—';
		}
		switch ( $f['type'] ) {
			case 'checkbox':
				return $v ? __( 'Yes', 'flx-donate' ) : __( 'No', 'flx-donate' );
			case 'select':
				return FLXB_Reports::text( $f['options'][ $v ] ?? $v );
			case 'date':
				return flx_date( strtotime( $v . ' 12:00' ) );
			case 'number':
				return number_format_i18n( (float) $v, floor( (float) $v ) == $v ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			default:
				return (string) $v;
		}
	}

	private static function input( array $f, $name, $v ) {
		$id = 'flxb-x-' . md5( $name );
		$l  = '<label for="' . esc_attr( $id ) . '">' . esc_html( FLXB_Reports::text( $f['label'] ) ) . '</label>';
		$h  = '' !== FLXB_Reports::text( $f['help'] ) ? '<p class="flxb-help">' . esc_html( FLXB_Reports::text( $f['help'] ) ) . '</p>' : '';
		switch ( $f['type'] ) {
			case 'textarea':
				$in = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( (string) $v ) . '</textarea>';
				break;
			case 'select':
				$in = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">—</option>';
				foreach ( $f['options'] as $ok => $ol ) {
					$in .= '<option value="' . esc_attr( $ok ) . '"' . selected( (string) $v, (string) $ok, false ) . '>' . esc_html( FLXB_Reports::text( $ol ) ) . '</option>';
				}
				$in .= '</select>';
				break;
			case 'checkbox':
				return '<div class="flxb-field"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label class="flxb-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $v ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html( FLXB_Reports::text( $f['label'] ) ) . '</label>' . $h . '</div>';
			default:
				$map = array( 'number' => 'number" step="any', 'date' => 'date', 'url' => 'url', 'email' => 'email', 'phone' => 'tel' );
				$in  = '<input id="' . esc_attr( $id ) . '" type="' . ( $map[ $f['type'] ] ?? 'text' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
		return '<div class="flxb-field">' . $l . $in . $h . '</div>';
	}

	/**
	 * Tabs card for a record or donation page. Empty string when no tab applies.
	 *
	 * @param string $on       item | donation.
	 * @param array  $row      The row.
	 * @param bool   $can_edit Whether the viewer may change values.
	 */
	public static function render( $on, array $row, $can_edit ) {
		$tabs = self::for_row( $on, (int) $row['type_id'] );
		if ( ! $tabs ) {
			return '';
		}
		$first = (string) array_key_first( $tabs );
		$cur   = sanitize_key( FLXB_Admin::q( 'xtab', $first ) );
		$cur   = isset( $tabs[ $cur ] ) ? $cur : $first;
		$base  = remove_query_arg( array( 'xtab', 'flxb_msg' ) );
		$h     = '<section class="flxb-card flxb-card--gap flxb-xtabs" id="flxb-xtabs"><nav class="flxb-tabs flxb-tabs--card">';
		foreach ( $tabs as $id => $t ) {
			$h .= '<a class="flxb-tab' . ( $id === $cur ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'xtab', $id, $base ) . '#flxb-xtabs' ) . '">' . esc_html( FLXB_Reports::text( $t['title'] ) ) . '</a>';
		}
		$h   .= '</nav>';
		$t    = $tabs[ $cur ];
		$vals = self::values( $on, $row, $cur );
		if ( $can_edit ) {
			$h .= '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_tab_save"><input type="hidden" name="on" value="' . esc_attr( $on ) . '"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="tab" value="' . esc_attr( $cur ) . '"><input type="hidden" name="back" value="' . esc_attr( $base ) . '">' . wp_nonce_field( 'flxb_tab_save', '_wpnonce', true, false ) . '<div class="flxb-fields-2">';
			foreach ( $t['fields'] as $k => $f ) {
				$h .= self::input( $f, 'x[' . $k . ']', $vals[ $k ] ?? '' );
			}
			$h .= '</div><div class="flxb-actions"><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form>';
		} else {
			$h .= '<dl class="flxb-dl">';
			foreach ( $t['fields'] as $k => $f ) {
				$h .= '<dt>' . esc_html( FLXB_Reports::text( $f['label'] ) ) . '</dt><dd>' . esc_html( self::shown( $f, $vals[ $k ] ?? '' ) ) . '</dd>';
			}
			$h .= '</dl>';
		}
		return $h . '</section>';
	}

	public static function handle_save() {
		$on = 'donation' === ( $_POST['on'] ?? '' ) ? 'donation' : 'item'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'donation' === $on ? ! flxb_can_manage() : ! FLXB_Track::can() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_tab_save' );
		$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? '' ) );
		$ok  = self::save_values( $on, absint( $_POST['id'] ?? 0 ), $tab, (array) wp_unslash( $_POST['x'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$back = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ?? '' ) ), admin_url( 'admin.php?page=flxb-donations' ) );
		wp_safe_redirect( add_query_arg( array( 'xtab' => $tab, 'flxb_msg' => $ok ? 'saved' : 'error' ), $back ) . '#flxb-xtabs' );
		exit;
	}
}
