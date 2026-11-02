<?php
/**
 * Flux Donate is the single source of truth for prices; Flux Forms follows it.
 *
 *  - When a type is saved, the priced choices of its linked forms that are mapped to an option get
 *    the option's price (× units) and the amount in their label is rewritten ("1 fidan — 150 ₺" →
 *    "1 fidan — 200 ₺"), so the form and the builder show what will be charged. The charge itself is
 *    always computed on the server from the type (FLXB_FLXForm_Bridge::server_amount()).
 *  - `flxf_sapling_unit_price` (Flux Forms's preset donation form and automation defaults) returns the
 *    unit price of the type linked to that form.
 *  - `flxf_automation_unit_price` (certificate count = amount ÷ unit price) returns the unit price the
 *    donor actually paid (stored with the payment), else the linked type's current unit price. The
 *    unit price stored in the form's certificate flows is kept equal as well (works without Flux Donate).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Form_Sync {

	public static function init() {
		add_action( 'flxb_type_saved', array( __CLASS__, 'sync_type' ), 10, 1 );
		add_filter( 'flxf_sapling_unit_price', array( __CLASS__, 'sapling_unit_price' ), 10, 2 );
		add_filter( 'flxf_automation_unit_price', array( __CLASS__, 'automation_unit_price' ), 10, 2 );
	}

	/**
	 * Unit price of the option behind the "one unit" choice of a form linked to a type (open types
	 * first), or null.
	 */
	public static function unit_price_for_form( $form_id ) {
		$t = FLXB_Types::for_form_type( (int) $form_id, '', false );
		if ( ! $t ) {
			return null;
		}
		$map  = (array) ( $t['form_map'][ (int) $form_id ]['opts'] ?? ( $t['form_map'][ (string) (int) $form_id ]['opts'] ?? array() ) );
		$best = null;
		foreach ( $map as $m ) {
			$o = FLXB_Types::option( $t, (string) ( $m['opt'] ?? '' ) );
			if ( ! $o || (float) $o['price'] <= 0 ) {
				continue;
			}
			if ( 1 === (int) $m['units'] ) {
				return (float) $o['price'];
			}
			$best = null === $best ? (float) $o['price'] : $best;
		}
		return $best;
	}

	/** The donation form Flux Forms seeds has the stock choice ids flxfdp1/5/10; find a form linked with them. */
	private static function default_form() {
		foreach ( FLXB_Types::all() as $t ) {
			foreach ( $t['form_map'] as $fid => $m ) {
				if ( isset( $m['opts']['flxfdp1'] ) ) {
					return (int) $fid;
				}
			}
		}
		return 0;
	}

	/** Filter `flxf_sapling_unit_price` ( $price, $form_id = 0 ). */
	public static function sapling_unit_price( $price, $form_id = 0 ) {
		$form_id = (int) $form_id ? (int) $form_id : self::default_form();
		$p       = $form_id ? self::unit_price_for_form( $form_id ) : null;
		return null !== $p ? $p : $price;
	}

	/** Filter `flxf_automation_unit_price` ( $unit, $ctx ): unit price for "amount ÷ unit price" counts. */
	public static function automation_unit_price( $unit, $ctx ) {
		$ctx   = is_array( $ctx ) ? $ctx : array();
		$track = (string) ( $ctx['track'] ?? '' );
		if ( '' !== $track ) {
			$row  = FLXB_FLXForm_Bridge::pay_row_by_track( $track );
			$meta = $row && ! empty( $row->meta ) ? json_decode( (string) $row->meta, true ) : null;
			if ( is_array( $meta ) && ! empty( $meta['flxb']['unit_price'] ) && 'mixed' !== ( $meta['flxb']['option_key'] ?? '' ) && (float) $meta['flxb']['unit_price'] > 0 ) {
				return (float) $meta['flxb']['unit_price'];
			}
		}
		$p = ! empty( $ctx['form_id'] ) ? self::unit_price_for_form( (int) $ctx['form_id'] ) : null;
		return null !== $p ? $p : $unit;
	}

	/** Sync every type (run once on the upgrade to 1.1). */
	public static function sync_all() {
		foreach ( FLXB_Types::all() as $t ) {
			self::sync_type( (int) $t['id'] );
		}
	}

	/** Write the type's prices into the mapped choices of its linked forms. */
	public static function sync_type( $type_id ) {
		global $wpdb;
		$t = FLXB_Types::get( (int) $type_id );
		if ( ! $t || 'archived' === $t['status'] ) {
			return 0;
		}
		$table   = $wpdb->prefix . 'emsfb_form';
		$changed = 0;
		foreach ( $t['form_ids'] as $fid ) {
			$map = (array) ( $t['form_map'][ (int) $fid ]['opts'] ?? ( $t['form_map'][ (string) (int) $fid ]['opts'] ?? array() ) );
			if ( ! $map ) {
				continue;
			}
			$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM `{$table}` WHERE form_id = %d", (int) $fid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fs  = FLXB_FLXForm_Bridge::decode_json( $raw );
			if ( ! $fs ) {
				continue;
			}
			/* The prices are in the type's currency: never write them into a form charging in another one. */
			$form_cur = strtoupper( (string) ( $fs[0]['currency'] ?? 'USD' ) );
			if ( $form_cur !== strtoupper( (string) $t['currency'] ) ) {
				continue;
			}
			self::sync_flows( (int) $fid );
			$dirty = false;
			foreach ( $fs as $i => $el ) {
				$id = is_array( $el ) ? (string) ( $el['id_'] ?? '' ) : '';
				if ( '' === $id || 'option' !== ( $el['type'] ?? '' ) || ! isset( $map[ $id ] ) ) {
					continue;
				}
				$o = FLXB_Types::option( $t, (string) $map[ $id ]['opt'] );
				if ( ! $o || ! FLXB_Types::option_on_sale( $o ) ) {
					continue;
				}
				$new = round( (float) $o['price'] * max( 1, (int) $map[ $id ]['units'] ), 2 );
				$old = (float) ( $el['price'] ?? 0 );
				if ( abs( $new - $old ) < 0.005 ) {
					continue;
				}
				$fs[ $i ]['price'] = self::num( $new );
				if ( isset( $el['value'] ) && is_string( $el['value'] ) ) {
					$fs[ $i ]['value'] = self::relabel( $el['value'], $old, $new );
				}
				$dirty = true;
			}
			if ( ! $dirty ) {
				continue;
			}
			$json = wp_json_encode( $fs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( false !== strpos( $raw, '\\"' ) ) {
				$json = str_replace( '"', '\\"', $json ); // keep Flux Forms's escaped storage format
			}
			$wpdb->update( $table, array( 'form_structer' => $json ), array( 'form_id' => (int) $fid ) );
			wp_cache_delete( 'efb_form_' . (int) $fid . '_' . md5( 'form_structer_form_type' ), 'emsfb' );
			if ( function_exists( 'wp_cache_flush_group' ) && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
				wp_cache_flush_group( 'emsfb' );
			}
			do_action( 'flxb_form_prices_synced', (int) $fid, (int) $t['id'] );
			++$changed;
		}
		return $changed;
	}

	/**
	 * Flux Forms certificate flows of a form store the unit price they divide the paid amount by
	 * ("amount ÷ unit price"). While Flux Donate is active the filter above supplies it; the stored
	 * value is kept equal too, so the count stays right if Flux Donate is ever turned off.
	 */
	public static function sync_flows( $form_id ) {
		if ( ! class_exists( 'FLXF_Automations' ) || ! method_exists( 'FLXF_Automations', 'get_flows' ) || ! method_exists( 'FLXF_Automations', 'save_flow' ) ) {
			return 0;
		}
		$unit = self::unit_price_for_form( (int) $form_id );
		if ( null === $unit || $unit <= 0 ) {
			return 0;
		}
		$n = 0;
		foreach ( (array) FLXF_Automations::get_flows() as $flow ) {
			if ( (int) ( $flow['trigger']['form_id'] ?? 0 ) !== (int) $form_id || empty( $flow['actions'] ) ) {
				continue;
			}
			$changed = false;
			foreach ( (array) $flow['actions'] as $a => $act ) {
				foreach ( (array) ( $act['vars'] ?? array() ) as $k => $v ) {
					if ( is_array( $v ) && 'amount_div' === ( $v['mode'] ?? '' ) && abs( (float) ( $v['unit_price'] ?? 0 ) - $unit ) >= 0.005 ) {
						$flow['actions'][ $a ]['vars'][ $k ]['unit_price'] = $unit;
						$changed = true;
					}
				}
			}
			if ( $changed && ! is_wp_error( FLXF_Automations::save_flow( $flow, (int) $flow['id'] ) ) ) {
				++$n;
			}
		}
		return $n;
	}

	private static function num( $v ) {
		return abs( $v - round( $v ) ) < 0.005 ? (string) (int) round( $v ) : number_format( $v, 2, '.', '' );
	}

	/**
	 * Replace the old amount in a choice label with the new one, in the same number style
	 * ("1,500" / "1.500" / "1500"). The label is left alone when the old amount is not in it.
	 */
	public static function relabel( $label, $old, $new ) {
		$dec  = abs( $old - round( $old ) ) >= 0.005 || abs( $new - round( $new ) ) >= 0.005 ? 2 : 0;
		$opts = array(
			array( '.', ',' ),
			array( ',', '.' ),
			array( '.', '' ),
			array( ',', ' ' ),
		);
		foreach ( $opts as $o ) {
			foreach ( array_unique( array( $dec, 0, 2 ) ) as $d ) {
				$needle = number_format( $old, $d, $o[0], $o[1] );
				$re     = '/(?<![\d.,])' . preg_quote( $needle, '/' ) . '(?![\d]|[.,]\d)/u';
				if ( preg_match( $re, $label ) ) {
					return preg_replace( $re, number_format( $new, $d, $o[0], $o[1] ), $label, 1 );
				}
			}
		}
		return $label;
	}
}
