<?php
/**
 * Flux Donate: donation types (= campaigns). A type has a category, dates, a goal and its own
 * pricing: named options with a price each (one option = a fixed unit price), free amount
 * allowed or not, a minimum, quantity and recurring allowed or not. A Flux Forms form can be linked
 * to a type: its priced choices are mapped to the type's options and the amount is computed on the
 * server from the type (see price_rows() and FLXB_FLXForm_Bridge::server_amount()).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Types {

	const STATUSES = array( 'active', 'closed', 'archived' );

	public static function get( $id ) {
		global $wpdb;
		$t   = flxb_table( 'types' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::prepare( $row ) : null;
	}

	public static function get_by_slug( $slug ) {
		global $wpdb;
		$t   = flxb_table( 'types' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", sanitize_title( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::prepare( $row ) : null;
	}

	private static function prepare( array $row ) {
		$row['form_ids'] = array_values( array_filter( array_map( 'intval', explode( ',', (string) $row['form_ids'] ) ) ) );
		$opts            = json_decode( (string) $row['options'], true );
		$row['options']  = array_map( array( __CLASS__, 'option_defaults' ), is_array( $opts ) ? array_values( array_filter( $opts, 'is_array' ) ) : array() );
		$map             = json_decode( (string) $row['form_map'], true );
		$row['form_map'] = is_array( $map ) ? $map : array();
		$meta            = json_decode( (string) ( $row['meta'] ?? '' ), true );
		$row['meta']     = is_array( $meta ) ? $meta : array();
		return $row;
	}

	/** Options stored before 1.1 have no unit / active / units_per: read them with the defaults (no unit stays ''). */
	public static function option_defaults( array $o ) {
		return array(
			'key'       => (string) ( $o['key'] ?? '' ),
			'label'     => (string) ( $o['label'] ?? '' ),
			'price'     => (float) ( $o['price'] ?? 0 ),
			'unit'      => sanitize_key( (string) ( $o['unit'] ?? '' ) ), // '' = no unit set
			'active'    => ! isset( $o['active'] ) || ! empty( $o['active'] ),
			'units_per' => max( 1, (int) ( $o['units_per'] ?? 1 ) ),
			'badge'     => sanitize_key( (string) ( $o['badge'] ?? '' ) ), // 2.0: card badge on the donation page ('' = none)
			'prices'    => self::clean_prices( $o['prices'] ?? array() ), // 2.15: { EUR: 120, USD: 130 } prices in other currencies
		);
	}

	/**
	 * 2.15: translations of a type for Flux Lang: { en: { title, description, options: { key: label } } }.
	 * Empty strings are dropped; the type's own texts are the default language.
	 */
	public static function clean_i18n( array $raw ) {
		$out = array();
		foreach ( $raw as $lang => $tr ) {
			$lang = strtolower( sanitize_key( (string) $lang ) );
			if ( ! preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang ) || ! is_array( $tr ) ) {
				continue;
			}
			$row = array();
			foreach ( array( 'title' => 191, 'description' => 2000, 'subtitle' => 80 ) as $k => $max ) {
				$v = 'description' === $k ? sanitize_textarea_field( (string) ( $tr[ $k ] ?? '' ) ) : sanitize_text_field( (string) ( $tr[ $k ] ?? '' ) );
				if ( '' !== trim( $v ) ) {
					$row[ $k ] = mb_substr( trim( $v ), 0, $max );
				}
			}
			foreach ( (array) ( $tr['options'] ?? array() ) as $key => $label ) {
				$label = trim( sanitize_text_field( (string) $label ) );
				if ( '' !== $label ) {
					$row['options'][ sanitize_key( (string) $key ) ] = mb_substr( $label, 0, 120 );
				}
			}
			if ( $row ) {
				ksort( $row );
				$out[ $lang ] = $row;
			}
		}
		ksort( $out );
		return $out;
	}

	/** 2.15: the visitor's language (Flux Lang), '' for the default language or without Flux Lang. */
	public static function lang() {
		if ( ! function_exists( 'flxl_current_language' ) || ! function_exists( 'flxl_default_language' ) ) {
			return '';
		}
		$l = (string) flxl_current_language();
		return $l === (string) flxl_default_language() ? '' : $l;
	}

	/**
	 * 2.15: a type with its title, description and option labels in a language (default: the visitor's,
	 * Flux Lang). For display only; records keep the type's own texts.
	 */
	public static function localize( array $t, $lang = null ) {
		$lang = null === $lang ? self::lang() : strtolower( (string) $lang );
		$tr   = '' !== $lang ? (array) ( ( (array) self::meta( $t, 'i18n', array() ) )[ $lang ] ?? array() ) : array();
		if ( ! $tr ) {
			return $t;
		}
		foreach ( array( 'title', 'description' ) as $k ) {
			if ( ! empty( $tr[ $k ] ) ) {
				$t[ $k ] = (string) $tr[ $k ];
			}
		}
		if ( ! empty( $tr['subtitle'] ) ) {
			$t['meta']['subtitle'] = (string) $tr['subtitle']; // 2.16.0
		}
		foreach ( $t['options'] as $i => $o ) {
			if ( ! empty( $tr['options'][ $o['key'] ] ) ) {
				$t['options'][ $i ]['label'] = (string) $tr['options'][ $o['key'] ];
			}
		}
		return $t;
	}

	/** 2.15: { CODE: price > 0 } prices of an option in currencies other than the type's own. */
	public static function clean_prices( $raw ) {
		$out = array();
		foreach ( (array) $raw as $code => $v ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			$v    = is_numeric( $v ) ? (float) $v : (float) str_replace( ',', '.', (string) $v );
			if ( preg_match( '/^[A-Z]{3}$/', $code ) && $v > 0 ) {
				$out[ $code ] = round( $v, 2 );
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * 2.15: price of an option in a currency. The type's own currency uses `price`; any other currency
	 * uses the hand-set `prices[CODE]` (no exchange rates), 0 = not offered in that currency.
	 */
	public static function option_price( array $t, array $o, $currency = '' ) {
		$cur = '' === (string) $currency ? strtoupper( (string) $t['currency'] ) : strtoupper( (string) $currency );
		if ( strtoupper( (string) $t['currency'] ) === $cur ) {
			return (float) $o['price'];
		}
		return (float) ( $o['prices'][ $cur ] ?? 0 );
	}

	/** 2.15: can this option be charged now in this currency? */
	public static function option_on_sale_in( array $t, array $o, $currency = '' ) {
		return ! empty( $o['active'] ) && self::option_price( $t, $o, $currency ) > 0;
	}

	/** 2.15: lowest free amount in a currency (type meta min_by_currency for other currencies; none = 0). */
	public static function min_amount_in( array $t, $currency = '' ) {
		$cur = '' === (string) $currency ? strtoupper( (string) $t['currency'] ) : strtoupper( (string) $currency );
		if ( strtoupper( (string) $t['currency'] ) === $cur ) {
			return (float) $t['min_amount'];
		}
		$m = (array) self::meta( $t, 'min_by_currency', array() );
		return (float) ( $m[ $cur ] ?? 0 );
	}

	/** Can this option be charged now? Inactive options and options without a price (0 = "price not announced") cannot. */
	public static function option_on_sale( array $o ) {
		return ! empty( $o['active'] ) && (float) $o['price'] > 0;
	}

	/** Type meta value (kurban module and other add-ons; see `flxb_type_fields`). */
	public static function meta( array $t, $key, $default = null ) {
		return isset( $t['meta'] ) && is_array( $t['meta'] ) && array_key_exists( $key, $t['meta'] ) ? $t['meta'][ $key ] : $default;
	}

	/**
	 * Life-cycle state of a type today (site time): archived | closed | upcoming | ended | open.
	 */
	public static function state( array $t, $utc_datetime = '' ) {
		if ( 'archived' === $t['status'] ) {
			return 'archived';
		}
		if ( 'active' !== $t['status'] ) {
			return 'closed';
		}
		$ts  = $utc_datetime ? strtotime( $utc_datetime . ' UTC' ) : time();
		$day = wp_date( 'Y-m-d', $ts );
		if ( ! empty( $t['start_date'] ) && $day < $t['start_date'] ) {
			return 'upcoming';
		}
		if ( ! empty( $t['end_date'] ) && $day > $t['end_date'] ) {
			return 'ended';
		}
		return 'open';
	}

	/** Visitor-facing reason why a type does not take gifts now ('' when it does). */
	public static function closed_message( array $t ) {
		switch ( self::state( $t ) ) {
			case 'open':
				return '';
			case 'upcoming':
				/* translators: %s: start date */
				return sprintf( __( 'Donations for this cause open on %s.', 'flx-donate' ), flx_date( strtotime( $t['start_date'] . ' 12:00' ) ) );
			case 'ended':
				return __( 'Donations for this cause have ended.', 'flx-donate' );
			default:
				return __( 'Donations for this cause are closed at the moment.', 'flx-donate' );
		}
	}

	/**
	 * @param string $status '' = active + closed (not archived), 'all', or one status.
	 */
	public static function all( $status = '' ) {
		global $wpdb;
		$t = flxb_table( 'types' );
		if ( 'all' === $status ) {
			$where = '';
		} elseif ( in_array( $status, self::STATUSES, true ) ) {
			$where = $wpdb->prepare( ' WHERE status = %s', $status );
		} else {
			$where = " WHERE status <> 'archived'";
		}
		$rows = $wpdb->get_results( "SELECT * FROM {$t}{$where} ORDER BY sort_order ASC, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	/**
	 * Options from the editor or the API: [ {key, label, price, unit, active, units_per} ] with unique
	 * keys. Price 0 means "price not announced yet": the option is shown but cannot be charged.
	 */
	public static function clean_options( $raw ) {
		$out   = array();
		$seen  = array();
		foreach ( (array) $raw as $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $o['label'] ?? '' ) );
			$price = round( (float) str_replace( ',', '.', (string) ( $o['price'] ?? 0 ) ), 2 );
			if ( '' === $label || $price < 0 ) {
				continue;
			}
			$key = sanitize_key( (string) ( $o['key'] ?? '' ) );
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( array( ' ', '/', '.', '(', ')' ), '-', remove_accents( strtolower( $label ) ) ) );
				$key = trim( preg_replace( '/-+/', '-', $key ), '-' );
			}
			$key  = '' === $key ? 'opt' : substr( $key, 0, 48 );
			$base = $key;
			$n    = 2;
			while ( isset( $seen[ $key ] ) ) {
				$key = $base . '-' . $n++;
			}
			$seen[ $key ] = true;
			$unit         = substr( sanitize_key( (string) ( $o['unit'] ?? '' ) ), 0, 32 ); // '' = not set; keys outside flxb_units() are kept as custom units
			$out[]        = array(
				'key'       => $key,
				'label'     => mb_substr( $label, 0, 120 ),
				'price'     => $price,
				'unit'      => $unit,
				'active'    => ! isset( $o['active'] ) || ! empty( $o['active'] ),
				'units_per' => max( 1, min( 1000, absint( $o['units_per'] ?? 1 ) ) ),
				'badge'     => substr( sanitize_key( (string) ( $o['badge'] ?? '' ) ), 0, 32 ),
				'prices'    => self::clean_prices( $o['prices'] ?? array() ),
			);
		}
		return $out;
	}

	/** Notices from the last save() / set_status() (e.g. a form that could not be linked). */
	public static $notices = array();

	/** Do two types' date ranges share a day? (Empty dates = open-ended.) */
	private static function overlap( array $a, array $b ) {
		$as = ! empty( $a['start_date'] ) ? $a['start_date'] : '0000-00-00';
		$ae = ! empty( $a['end_date'] ) ? $a['end_date'] : '9999-12-31';
		$bs = ! empty( $b['start_date'] ) ? $b['start_date'] : '0000-00-00';
		$be = ! empty( $b['end_date'] ) ? $b['end_date'] : '9999-12-31';
		return $as <= $be && $bs <= $ae;
	}

	/**
	 * Forms of $t that are already linked to another active type whose dates overlap: a gift through
	 * such a form could belong to either type. form_id => other type.
	 */
	public static function form_conflicts( array $t ) {
		$out = array();
		if ( 'active' !== ( $t['status'] ?? 'active' ) || empty( $t['form_ids'] ) ) {
			return $out;
		}
		foreach ( self::all( 'active' ) as $o ) {
			if ( (int) $o['id'] === (int) ( $t['id'] ?? 0 ) || ! self::overlap( $t, $o ) ) {
				continue;
			}
			foreach ( array_intersect( array_map( 'intval', $t['form_ids'] ), $o['form_ids'] ) as $fid ) {
				$out[ (int) $fid ] = $o;
			}
		}
		return $out;
	}

	private static function conflict_notice( $fid, array $other ) {
		/* translators: 1: form title, 2: other donation type */
		return sprintf( __( 'The form “%1$s” is already linked to the open donation type “%2$s” for the same dates, so it was not linked here. Close that type, change the dates, or use another form.', 'flx-donate' ), flxb_form_title( $fid ), $other['title'] );
	}

	/**
	 * Create or update a type.
	 *
	 * @return int|WP_Error Type id.
	 */
	public static function save( array $d, $id = 0 ) {
		global $wpdb;
		$t     = flxb_table( 'types' );
		$title = sanitize_text_field( (string) ( $d['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'flxb_title', __( 'Please enter a title.', 'flx-donate' ) );
		}
		$slug = sanitize_title( '' !== (string) ( $d['slug'] ?? '' ) ? $d['slug'] : remove_accents( $title ) );
		$slug = '' === $slug ? 'bagis' : $slug;
		$base = $slug;
		$n    = 2;
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE slug = %s AND id <> %d", $slug, (int) $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug = $base . '-' . $n++;
		}
		$date = function ( $v ) {
			$v = sanitize_text_field( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
		};
		$start = $date( $d['start_date'] ?? '' );
		$end   = $date( $d['end_date'] ?? '' );
		if ( $start && $end && $end < $start ) {
			return new WP_Error( 'flxb_dates', __( 'The end date must be after the start date.', 'flx-donate' ) );
		}
		$options = self::clean_options( $d['options'] ?? array() );
		$free    = ! empty( $d['allow_free'] );
		if ( ! $options && ! $free ) {
			return new WP_Error( 'flxb_options', __( 'Add at least one option with a price, or allow a free amount.', 'flx-donate' ) );
		}
		self::$notices = array();
		$old      = $id ? self::get( $id ) : null;
		$forms    = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $d['form_ids'] ?? array() ) ) ) ) );
		/* Currency: kept when the request does not send one (older editor, API callers). */
		$cur      = strtoupper( sanitize_text_field( (string) ( $d['currency'] ?? ( $old ? $old['currency'] : flxb_default_currency() ) ) ) );
		$status   = in_array( $d['status'] ?? 'active', self::STATUSES, true ) ? ( $d['status'] ?? 'active' ) : 'active';
		/* A form linked to two open types at the same time would put gifts on the wrong one. */
		$conf = self::form_conflicts( array( 'id' => (int) $id, 'status' => $status, 'start_date' => $start, 'end_date' => $end, 'form_ids' => $forms ) );
		foreach ( $conf as $fid => $other ) {
			self::$notices[] = self::conflict_notice( $fid, $other );
		}
		$forms = array_values( array_diff( $forms, array_keys( $conf ) ) );
		$meta  = $old ? $old['meta'] : array();
		if ( isset( $d['meta'] ) && is_array( $d['meta'] ) ) {
			$meta = array_merge( $meta, self::clean_meta( $d['meta'] ) );
		}
		$category = sanitize_key( (string) ( $d['category'] ?? '' ) );
		$row      = array(
			'title'           => mb_substr( $title, 0, 191 ),
			'slug'            => $slug,
			'category'        => isset( flxb_categories()[ $category ] ) ? $category : '',
			'description'     => sanitize_textarea_field( (string) ( $d['description'] ?? '' ) ),
			'image_id'        => absint( $d['image_id'] ?? 0 ),
			'goal_amount'     => max( 0, round( (float) str_replace( ',', '.', (string) ( $d['goal_amount'] ?? 0 ) ), 2 ) ),
			'currency'        => preg_match( '/^[A-Z]{3}$/', $cur ) ? $cur : flxb_default_currency(),
			'start_date'      => $start,
			'end_date'        => $end,
			'status'          => $status,
			'options'         => wp_json_encode( $options ),
			'allow_free'      => $free ? 1 : 0,
			'min_amount'      => max( 0, round( (float) str_replace( ',', '.', (string) ( $d['min_amount'] ?? 0 ) ), 2 ) ),
			'allow_quantity'  => empty( $d['allow_quantity'] ) ? 0 : 1,
			'max_quantity'    => max( 0, absint( $d['max_quantity'] ?? 0 ) ),
			'allow_recurring' => empty( $d['allow_recurring'] ) ? 0 : 1,
			'form_ids'        => implode( ',', $forms ),
			'form_map'        => wp_json_encode( self::clean_map( array_key_exists( 'form_map', $d ) ? $d['form_map'] : ( $old ? $old['form_map'] : array() ), $forms, $options ) ),
			'meta'            => $meta ? wp_json_encode( $meta ) : null,
			'updated_at'      => flxb_now(),
		);
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			self::flush_progress( (int) $id );
			do_action( 'flxb_type_saved', (int) $id, $old );
			return (int) $id;
		}
		$row['sort_order'] = 1 + (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row['created_at'] = flxb_now();
		$wpdb->insert( $t, $row );
		$new = (int) $wpdb->insert_id;
		do_action( 'flxb_type_saved', $new, null );
		return $new;
	}

	/**
	 * Type meta from the editor or the API. Built-in keys (kurban module): requires_proxy (bool),
	 * intention (vacip|adak|akika|sifa|nafile|''), meat_to_needy (bool), grouping (buyukbas|kucukbas|none).
	 * Add-ons sanitise their own keys with the `flxb_type_meta_clean` filter.
	 */
	public static function clean_meta( array $m ) {
		$out = array();
		if ( array_key_exists( 'requires_proxy', $m ) ) {
			$out['requires_proxy'] = ! empty( $m['requires_proxy'] );
		}
		if ( array_key_exists( 'meat_to_needy', $m ) ) {
			$out['meat_to_needy'] = ! empty( $m['meat_to_needy'] );
		}
		if ( array_key_exists( 'intention', $m ) ) {
			$i                = sanitize_key( (string) $m['intention'] );
			$out['intention'] = isset( self::intentions()[ $i ] ) ? $i : '';
		}
		foreach ( array( 'track_items', 'file_completes' ) as $k ) {
			if ( array_key_exists( $k, $m ) ) {
				$out[ $k ] = ! empty( $m[ $k ] );
			}
		}
		if ( array_key_exists( 'labels', $m ) ) {
			$out['labels'] = FLXB_Track::clean_labels( (array) $m['labels'] );
		}
		if ( array_key_exists( 'lock_preselect', $m ) ) {
			$out['lock_preselect'] = ! empty( $m['lock_preselect'] );
		}
		if ( array_key_exists( 'i18n', $m ) ) {
			$out['i18n'] = self::clean_i18n( (array) $m['i18n'] );
		}
		/* 2.16.0: card subtitle and icon in the form layouts (Settings › Appearance). */
		if ( array_key_exists( 'subtitle', $m ) ) {
			$out['subtitle'] = mb_substr( sanitize_text_field( (string) $m['subtitle'] ), 0, 80 );
		}
		if ( array_key_exists( 'icon', $m ) ) {
			$out['icon'] = sanitize_key( (string) $m['icon'] );
		}
		if ( array_key_exists( 'grouping', $m ) ) {
			$g               = sanitize_key( (string) $m['grouping'] );
			$out['grouping'] = in_array( $g, array( 'buyukbas', 'kucukbas', 'none' ), true ) ? $g : 'none';
		}
		return (array) apply_filters( 'flxb_type_meta_clean', $out, $m );
	}

	public static function intentions() {
		return array(
			'vacip'  => __( 'Vacip', 'flx-donate' ),
			'adak'   => __( 'Adak', 'flx-donate' ),
			'akika'  => __( 'Akika', 'flx-donate' ),
			'sifa'   => __( 'Şifa', 'flx-donate' ),
			'nafile' => __( 'Nafile', 'flx-donate' ),
		);
	}

	/**
	 * 2.11: are the units of this type tracked one by one (code, partner, files, history)? Types given
	 * on behalf of a named person always are.
	 */
	public static function tracks_items( array $t ) {
		return self::requires_proxy( $t ) || ( flxb_track_enabled() && (bool) self::meta( $t, 'track_items', false ) );
	}

	/** Are gifts of this type given unit by unit on behalf of a named person each (named-gift module)? */
	public static function requires_proxy( array $t ) {
		return flxb_kurban_enabled() && (bool) self::meta( $t, 'requires_proxy', false );
	}

	/**
	 * Form mapping: form_id => { opts: { element id => { opt: option key, units: n } }, qty: field id,
	 * free: field id, beneficiaries: field id (names, one per line), consent: field id (proxy consent) }.
	 */
	public static function clean_map( $raw, array $forms, array $options ) {
		$keys = wp_list_pluck( $options, 'key' );
		$out  = array();
		foreach ( (array) $raw as $fid => $m ) {
			$fid = absint( $fid );
			if ( ! in_array( $fid, $forms, true ) || ! is_array( $m ) ) {
				continue;
			}
			$o = array( 'opts' => array(), 'qty' => '', 'free' => '' );
			foreach ( (array) ( $m['opts'] ?? array() ) as $el => $x ) {
				$el  = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $el );
				$opt = sanitize_key( (string) ( $x['opt'] ?? '' ) );
				if ( '' !== $el && in_array( $opt, $keys, true ) ) {
					$o['opts'][ $el ] = array( 'opt' => $opt, 'units' => max( 1, absint( $x['units'] ?? 1 ) ) );
				}
			}
			foreach ( array( 'qty', 'free', 'beneficiaries', 'consent' ) as $k ) {
				$o[ $k ] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $m[ $k ] ?? '' ) );
			}
			$out[ $fid ] = $o;
		}
		return $out;
	}

	/** @return bool false when the type cannot be opened (one of its forms is linked to another open type). */
	public static function set_status( $id, $status ) {
		global $wpdb;
		self::$notices = array();
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$t = self::get( $id );
		if ( $t && 'active' === $status ) {
			$t['status'] = 'active';
			foreach ( self::form_conflicts( $t ) as $fid => $other ) {
				self::$notices[] = self::conflict_notice( $fid, $other );
			}
			if ( self::$notices ) {
				return false;
			}
		}
		$wpdb->update( flxb_table( 'types' ), array( 'status' => $status, 'updated_at' => flxb_now() ), array( 'id' => (int) $id ) );
		self::flush_progress( (int) $id );
		return true;
	}

	/** Move a type one place up (-1) or down (+1) in the list. */
	public static function move( $id, $dir ) {
		global $wpdb;
		$list = self::all( 'all' );
		$ids  = wp_list_pluck( $list, 'id' );
		$ids  = array_map( 'intval', $ids );
		$pos  = array_search( (int) $id, $ids, true );
		if ( false === $pos ) {
			return;
		}
		$to = $pos + ( $dir < 0 ? -1 : 1 );
		if ( $to < 0 || $to >= count( $ids ) ) {
			return;
		}
		list( $ids[ $pos ], $ids[ $to ] ) = array( $ids[ $to ], $ids[ $pos ] );
		foreach ( $ids as $i => $tid ) {
			$wpdb->update( flxb_table( 'types' ), array( 'sort_order' => $i + 1 ), array( 'id' => $tid ) );
		}
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( flxb_table( 'types' ), array( 'id' => (int) $id ), array( '%d' ) );
		$wpdb->update( flxb_table( 'donations' ), array( 'type_id' => 0 ), array( 'type_id' => (int) $id ) );
		$wpdb->update( flxb_table( 'subscriptions' ), array( 'type_id' => 0 ), array( 'type_id' => (int) $id ) );
	}

	/** Is the type open for gifts on this day (site time)? */
	public static function is_open( array $t, $utc_datetime = '' ) {
		return 'open' === self::state( $t, $utc_datetime );
	}

	/** The type a form is linked to (open ones first), or null. */
	public static function for_form_type( $form_id, $utc_datetime = '', $open_only = true ) {
		$form_id = (int) $form_id;
		if ( ! $form_id ) {
			return null;
		}
		$fallback = null;
		foreach ( self::all( 'all' ) as $t ) {
			if ( ! in_array( $form_id, $t['form_ids'], true ) ) {
				continue;
			}
			if ( self::is_open( $t, $utc_datetime ) ) {
				return $t;
			}
			if ( ! $fallback && 'archived' !== $t['status'] ) {
				$fallback = $t;
			}
		}
		return $open_only ? null : $fallback;
	}

	/** Id of the type a gift through this form belongs to (0 when none). */
	public static function for_form( $form_id, $utc_datetime = '' ) {
		$t = self::for_form_type( $form_id, $utc_datetime, false );
		return $t ? (int) $t['id'] : 0;
	}

	public static function option( array $t, $key ) {
		foreach ( $t['options'] as $o ) {
			if ( $o['key'] === $key ) {
				return $o;
			}
		}
		return null;
	}

	/**
	 * Price a form submission with the type linked to its form. Never trusts a price from the browser:
	 * mapped choices get the type's option price; a free amount only when the type allows it.
	 *
	 * @param array $t       Type.
	 * @param int   $form_id Flux Forms form id.
	 * @param array $rows    Submitted rows (id_, type, value, price...).
	 * @return array{ok:bool, total:float, lines:array, quantity:int, mapped:bool, message:string}
	 */
	public static function price_rows( array $t, $form_id, array $rows ) {
		$map   = $t['form_map'][ (int) $form_id ] ?? ( $t['form_map'][ (string) (int) $form_id ] ?? null );
		$out   = array( 'ok' => true, 'total' => 0.0, 'lines' => array(), 'quantity' => 0, 'mapped' => false, 'message' => '' );
		$opts  = is_array( $map ) ? (array) ( $map['opts'] ?? array() ) : array();
		$qtyf  = is_array( $map ) ? (string) ( $map['qty'] ?? '' ) : '';
		$freef = is_array( $map ) ? (string) ( $map['free'] ?? '' ) : '';
		$qty   = 1;
		if ( $t['allow_quantity'] && '' !== $qtyf ) {
			foreach ( $rows as $r ) {
				$r = is_object( $r ) ? (array) $r : $r;
				if ( is_array( $r ) && (string) ( $r['id_'] ?? '' ) === $qtyf && is_numeric( $r['value'] ?? null ) ) {
					$qty = max( 1, (int) $r['value'] );
				}
			}
			if ( (int) $t['max_quantity'] > 0 && $qty > (int) $t['max_quantity'] ) {
				return array_merge( $out, array( 'ok' => false, 'message' => sprintf( /* translators: %s: maximum quantity */ __( 'You can choose at most %s.', 'flx-donate' ), number_format_i18n( (int) $t['max_quantity'] ) ) ) );
			}
		}
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) ) {
				continue;
			}
			/* Choice fields (payRadio / paySelect / payCheckbox) name the chosen option in id_ob. */
			$id = isset( $r['id_ob'] ) && isset( $opts[ (string) $r['id_ob'] ] ) ? (string) $r['id_ob'] : (string) ( $r['id_'] ?? '' );
			if ( '' !== $id && isset( $opts[ $id ] ) && array_key_exists( 'price', $r ) ) {
				$o = self::option( $t, $opts[ $id ]['opt'] );
				if ( ! $o ) {
					continue;
				}
				if ( ! self::option_on_sale( $o ) ) {
					return array_merge( $out, array( 'ok' => false, 'mapped' => true, 'message' => __( 'This option is not open for donations at the moment.', 'flx-donate' ) ) );
				}
				$units          = max( 1, (int) $opts[ $id ]['units'] ) * $qty;
				$amount         = round( $o['price'] * $units, 2 );
				$out['lines'][] = array( 'key' => $o['key'], 'label' => $o['label'], 'units' => $units, 'unit_price' => (float) $o['price'], 'amount' => $amount, 'row' => $id, 'unit' => $o['unit'], 'units_per' => (int) $o['units_per'] );
				$out['total']  += $amount;
				$out['quantity'] += $units;
				$out['mapped']   = true;
			} elseif ( '' !== $freef && $id === $freef ) {
				$out['mapped'] = true;
				$raw           = is_numeric( $r['price'] ?? null ) ? $r['price'] : ( $r['value'] ?? null );
				$v             = is_numeric( $raw ) ? round( abs( (float) $raw ), 2 ) : 0.0;
				if ( $v > 0 && $t['allow_free'] ) {
					$out['lines'][] = array( 'key' => '', 'label' => __( 'Free amount', 'flx-donate' ), 'units' => 1, 'unit_price' => $v, 'amount' => $v, 'row' => $id, 'unit' => '', 'units_per' => 1 );
					$out['total']  += $v;
					$out['quantity'] += 1;
				}
			}
		}
		$out['total'] = round( $out['total'], 2 );
		if ( $out['mapped'] && $out['lines'] ) {
			$proxy = self::proxy_from_rows( $t, $form_id, $rows );
			if ( '' !== $proxy['error'] ) {
				return array_merge( $out, array( 'ok' => false, 'message' => $proxy['error'] ) );
			}
			$out['beneficiaries'] = $proxy['names'];
			$out['proxy_consent'] = $proxy['consent'];
		}
		if ( $out['mapped'] && (float) $t['min_amount'] > 0 && $out['total'] < (float) $t['min_amount'] ) {
			$out['ok']      = false;
			$out['message'] = sprintf( /* translators: %s: minimum amount */ __( 'The minimum donation is %s.', 'flx-donate' ), flxb_money( $t['min_amount'], $t['currency'] ) );
		}
		return $out;
	}

	/**
	 * Names of the people a gift is made on behalf of, and the proxy (vekâlet) consent, from the form
	 * rows (fields chosen in the form mapping). Only for types that require a proxy (kurban module).
	 *
	 * @return array{names:string[], consent:array|null, error:string}
	 */
	public static function proxy_from_rows( array $t, $form_id, array $rows ) {
		$out = array( 'names' => array(), 'consent' => null, 'error' => '' );
		$map = $t['form_map'][ (int) $form_id ] ?? ( $t['form_map'][ (string) (int) $form_id ] ?? array() );
		$bf  = (string) ( $map['beneficiaries'] ?? '' );
		$cf  = (string) ( $map['consent'] ?? '' );
		$ok  = false;
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) ) {
				continue;
			}
			$id  = (string) ( $r['id_'] ?? '' );
			$val = $r['value'] ?? '';
			if ( '' !== $bf && $id === $bf ) {
				$out['names'] = flxb_split_names( is_array( $val ) ? implode( "\n", array_filter( $val, 'is_scalar' ) ) : (string) $val );
			}
			if ( '' !== $cf && ( $id === $cf || (string) ( $r['id_ob'] ?? '' ) === $cf ) ) {
				$v  = is_array( $val ) ? implode( ',', array_filter( $val, 'is_scalar' ) ) : (string) $val;
				$ok = '' !== trim( $v ) && ! in_array( strtolower( trim( $v ) ), array( '0', 'false', 'no', 'hayır', 'hayir' ), true );
			}
		}
		if ( ! self::requires_proxy( $t ) ) {
			return $out;
		}
		$text = flxb_proxy_text();
		if ( '' === $cf || ! $text ) {
			$out['error'] = __( 'This donation needs a proxy (vekâlet) consent, but the form is not set up for it yet. Please contact the site administrator.', 'flx-donate' );
		} elseif ( ! $ok ) {
			$out['error'] = __( 'Please accept the proxy (vekâlet) text to continue.', 'flx-donate' );
		} else {
			$out['consent'] = array( 'version' => (int) $text['version'], 'accepted_at' => flxb_now(), 'source' => 'form' );
		}
		return $out;
	}

	public static function flush_progress( $id ) {
		delete_transient( 'flxb_progress_' . (int) $id );
	}

	/**
	 * raised, donors, gifts, goal, percent, days_left, currency, other (sums in other currencies).
	 * Only gifts in the type's currency count towards "raised". Cached until a donation of the type changes.
	 */
	public static function progress( array $t ) {
		global $wpdb;
		$key = 'flxb_progress_' . (int) $t['id'];
		$c   = get_transient( $key );
		if ( ! is_array( $c ) || ( $c['currency'] ?? '' ) !== $t['currency'] ) {
			$dn    = flxb_table( 'donations' );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT currency, COALESCE(SUM(amount),0) AS raised, COUNT(*) AS gifts, COUNT(DISTINCT CASE WHEN donor_id > 0 THEN donor_id END) AS donors FROM {$dn} WHERE type_id = %d AND status IN ('completed','partially_refunded') GROUP BY currency", (int) $t['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$c     = array( 'currency' => $t['currency'], 'raised' => 0.0, 'gifts' => 0, 'donors' => 0, 'other' => array() );
			foreach ( (array) $rows as $r ) {
				if ( strtoupper( (string) $r['currency'] ) === strtoupper( (string) $t['currency'] ) ) {
					$c['raised'] = (float) $r['raised'];
					$c['gifts']  = (int) $r['gifts'];
					$c['donors'] = (int) $r['donors'];
				} else {
					$c['other'][ strtoupper( (string) $r['currency'] ) ] = (float) $r['raised'];
				}
			}
			set_transient( $key, $c, DAY_IN_SECONDS );
		}
		$goal = (float) $t['goal_amount'];
		$left = null;
		if ( ! empty( $t['end_date'] ) ) {
			$today = new DateTimeImmutable( wp_date( 'Y-m-d' ), wp_timezone() );
			$end   = new DateTimeImmutable( $t['end_date'], wp_timezone() );
			$left  = (int) $today->diff( $end )->format( '%r%a' );
		}
		return array(
			'raised'    => (float) $c['raised'],
			'gifts'     => (int) $c['gifts'],
			'donors'    => (int) $c['donors'],
			'goal'      => $goal,
			'percent'   => $goal > 0 ? min( 100, round( 100 * (float) $c['raised'] / $goal, 1 ) ) : 0,
			'days_left' => $left,
			'currency'  => (string) $t['currency'],
			'other'     => (array) $c['other'],
		);
	}

	/** Priced choices of a Flux Forms form (for the mapping editor): [ id => label (price) ], plus number/price fields. */
	public static function form_choices( $form_id ) {
		$s   = FLXB_FLXForm_Bridge::form_structure( (int) $form_id );
		$out = array( 'options' => array(), 'numbers' => array(), 'prices' => array(), 'texts' => array(), 'checks' => array() );
		foreach ( $s as $i => $el ) {
			if ( 0 === $i || ! is_array( $el ) || empty( $el['id_'] ) ) {
				continue;
			}
			$type  = (string) ( $el['type'] ?? '' );
			$label = trim( wp_strip_all_tags( (string) ( $el['value'] ?? ( $el['name'] ?? '' ) ) ) );
			if ( 'option' === $type && isset( $el['price'] ) && '' !== (string) $el['price'] ) {
				$out['options'][ (string) $el['id_'] ] = array( 'label' => '' !== $label ? $label : (string) $el['id_'], 'price' => (float) $el['price'] );
			} elseif ( 'prcfld' === $type ) {
				$out['prices'][ (string) $el['id_'] ] = trim( wp_strip_all_tags( (string) ( $el['name'] ?? $el['id_'] ) ) );
			} elseif ( in_array( $type, array( 'number', 'range' ), true ) ) {
				$out['numbers'][ (string) $el['id_'] ] = trim( wp_strip_all_tags( (string) ( $el['name'] ?? $el['id_'] ) ) );
			} elseif ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
				$out['texts'][ (string) $el['id_'] ] = trim( wp_strip_all_tags( (string) ( $el['name'] ?? $el['id_'] ) ) );
			} elseif ( in_array( $type, array( 'checkbox', 'trmCheckbox', 'switch', 'yesNo' ), true ) ) {
				$out['checks'][ (string) $el['id_'] ] = trim( wp_strip_all_tags( (string) ( $el['name'] ?? $el['id_'] ) ) );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Sample types (optional, never loaded automatically)
	 * ------------------------------------------------------------------ */

	public static function samples() {
		return array(
			'trees'   => array(
				'label'      => __( 'Tree planting (sapling donations)', 'flx-donate' ),
				'categories' => array( 'fidan' => __( 'Saplings', 'flx-donate' ) ),
				'types'      => array(
					array( 'title' => __( 'Sapling donation', 'flx-donate' ), 'category' => 'fidan', 'options' => array( array( 'key' => 'fidan', 'label' => __( 'Sapling', 'flx-donate' ), 'price' => 150, 'unit' => 'fidan' ) ), 'allow_quantity' => 1, 'allow_recurring' => 1, 'goal_amount' => 0 ),
				),
			),
			'charity' => array(
				'label'      => __( 'Charity (kurban, ramazan, general)', 'flx-donate' ),
				'categories' => array( 'kurban' => __( 'Kurban', 'flx-donate' ), 'ramazan' => __( 'Ramazan', 'flx-donate' ), 'general' => __( 'General', 'flx-donate' ) ),
				'types'      => array(
					array( 'title' => __( 'Kurban share (cattle)', 'flx-donate' ), 'category' => 'kurban', 'options' => array( array( 'key' => 'hisse', 'label' => __( 'One share (1/7)', 'flx-donate' ), 'price' => 0, 'unit' => 'hisse' ), array( 'key' => 'tam', 'label' => __( 'Whole animal (7 shares)', 'flx-donate' ), 'price' => 0, 'unit' => 'hisse', 'units_per' => 7 ) ), 'allow_quantity' => 1, 'meta' => array( 'requires_proxy' => true, 'intention' => 'vacip', 'grouping' => 'buyukbas' ) ),
					array( 'title' => __( 'Kurban (sheep or goat)', 'flx-donate' ), 'category' => 'kurban', 'options' => array( array( 'key' => 'kucukbas', 'label' => __( 'Sheep or goat', 'flx-donate' ), 'price' => 0 ) ), 'allow_quantity' => 1, 'meta' => array( 'requires_proxy' => true, 'intention' => 'vacip', 'grouping' => 'kucukbas' ) ),
					array( 'title' => __( 'Ramazan', 'flx-donate' ), 'category' => 'ramazan', 'options' => array( array( 'key' => 'iftar', 'label' => __( 'Iftar', 'flx-donate' ), 'price' => 0, 'unit' => 'kisi' ), array( 'key' => 'sahur', 'label' => __( 'Sahur', 'flx-donate' ), 'price' => 0, 'unit' => 'kisi' ), array( 'key' => 'fitre', 'label' => __( 'Fitre', 'flx-donate' ), 'price' => 0, 'unit' => 'kisi' ) ), 'allow_quantity' => 1 ),
					array( 'title' => __( 'General donation', 'flx-donate' ), 'category' => 'general', 'options' => array(), 'allow_free' => 1, 'min_amount' => 0, 'allow_recurring' => 1 ),
				),
			),
		);
	}

	/** @return int number of types created */
	public static function load_sample( $key ) {
		$all = self::samples();
		if ( ! isset( $all[ $key ] ) ) {
			return 0;
		}
		$cats = flxb_categories();
		foreach ( $all[ $key ]['categories'] as $slug => $label ) {
			if ( ! isset( $cats[ $slug ] ) ) {
				$cats[ $slug ] = $label;
			}
		}
		update_option( 'flxb_categories', $cats, false );
		$n = 0;
		foreach ( $all[ $key ]['types'] as $t ) {
			if ( self::get_by_slug( sanitize_title( remove_accents( $t['title'] ) ) ) ) {
				continue; // already loaded
			}
			/* Sample prices of 0 are placeholders: the type starts closed until prices are set. */
			$zero = false;
			foreach ( $t['options'] as $o ) {
				if ( 0.0 === (float) $o['price'] ) {
					$zero = true;
				}
			}
			$t['status'] = $zero ? 'closed' : 'active';
			$res         = self::save( $t );
			if ( ! is_wp_error( $res ) ) {
				++$n;
			}
		}
		return $n;
	}
}
