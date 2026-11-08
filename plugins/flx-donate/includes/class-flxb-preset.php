<?php
/**
 * Flux Donate 2.11: content types for Flux Setup presets (flx-preset/1).
 *
 *   flxb_type       donation type (key = slug)
 *   flxb_partner    partner with its agreements (key = pkey)
 *   flxb_report     JSON report (key = report id)
 *   flxb_detail_tab extra detail tab (key = tab id)
 *
 * Schemas: docs/flx-donate/ON-AYAR.md. data() and read() return the same shape so Flux Setup can tell
 * "same / will change / changed by hand". Reports and tabs have no numeric id; a small map in
 * option flxb_preset_ids gives them one.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Preset {

	public static function init() {
		add_filter( 'flx_preset_importers', array( __CLASS__, 'importers' ) );
		add_filter( 'flx_preset_export_options', array( __CLASS__, 'export_options' ) );
		add_filter( 'flx_preset_export_content', array( __CLASS__, 'export_content' ), 10, 2 );
	}

	public static function export_options( $o ) {
		return array_merge( (array) $o, array( 'flxb_track_labels', 'flxb_track_early_days', 'flxb_categories' ) );
	}

	/* ---------- Numeric ids for keyed things ---------- */

	private static function nid( $kind, $key, $create = true ) {
		$m = (array) get_option( 'flxb_preset_ids', array() );
		if ( isset( $m[ $kind ][ $key ] ) ) {
			return (int) $m[ $kind ][ $key ];
		}
		if ( ! $create ) {
			return 0;
		}
		$n                    = 1 + (int) ( $m['_next'] ?? 0 );
		$m['_next']           = $n;
		$m[ $kind ][ $key ]   = $n;
		update_option( 'flxb_preset_ids', $m, false );
		return $n;
	}

	private static function nkey( $kind, $id ) {
		$m = (array) get_option( 'flxb_preset_ids', array() );
		$k = array_search( (int) $id, (array) ( $m[ $kind ] ?? array() ), true );
		return false === $k ? '' : (string) $k;
	}

	private static function strip( array $item ) {
		unset( $item['type'], $item['key'], $item['__replace'] );
		return $item;
	}

	private static function type_id( $slug ) {
		$t = FLXB_Types::get_by_slug( (string) $slug );
		return $t ? (int) $t['id'] : 0;
	}

	private static function type_slug( $id ) {
		$t = (int) $id ? FLXB_Types::get( (int) $id ) : null;
		return $t ? (string) $t['slug'] : '';
	}

	/* ---------- Donation types ---------- */

	public static function type_data( array $item ) {
		$out  = array(
			'title'           => (string) ( $item['title'] ?? $item['key'] ?? '' ),
			'category'        => (string) ( $item['category'] ?? '' ),
			'description'     => (string) ( $item['description'] ?? '' ),
			'currency'        => strtoupper( (string) ( $item['currency'] ?? flxb_default_currency() ) ),
			'status'          => in_array( $item['status'] ?? 'active', FLXB_Types::STATUSES, true ) ? (string) ( $item['status'] ?? 'active' ) : 'active',
			'goal_amount'     => (float) ( $item['goal_amount'] ?? 0 ),
			'start_date'      => (string) ( $item['start_date'] ?? '' ),
			'end_date'        => (string) ( $item['end_date'] ?? '' ),
			'allow_free'      => (int) ! empty( $item['allow_free'] ),
			'min_amount'      => (float) ( $item['min_amount'] ?? 0 ),
			'allow_quantity'  => (int) ! empty( $item['allow_quantity'] ),
			'max_quantity'    => (int) ( $item['max_quantity'] ?? 0 ),
			'allow_recurring' => (int) ! empty( $item['allow_recurring'] ),
			'options'         => array(),
			'meta'            => FLXB_Types::clean_meta( (array) ( $item['meta'] ?? array() ) ),
		);
		foreach ( FLXB_Types::clean_options( (array) ( $item['options'] ?? array() ) ) as $o ) {
			$out['options'][] = array(
				'key'    => (string) $o['key'],
				'label'  => (string) $o['label'],
				'price'  => (float) $o['price'],
				'unit'   => (string) ( $o['unit'] ?? '' ),
				'active' => (int) ! empty( $o['active'] ),
			) + ( ! empty( $o['prices'] ) ? array( 'prices' => $o['prices'] ) : array() ) // 2.15: { CODE: price }
				+ ( (int) ( $o['units_per'] ?? 1 ) > 1 ? array( 'units_per' => (int) $o['units_per'] ) : array() ) // 2.15.1: kept on re-apply
				+ ( '' !== (string) ( $o['badge'] ?? '' ) ? array( 'badge' => (string) $o['badge'] ) : array() );
		}
		ksort( $out['meta'] );
		return $out;
	}

	public static function type_read( $id ) {
		$t = FLXB_Types::get( (int) $id );
		if ( ! $t ) {
			return array();
		}
		$keys = array_keys( FLXB_Types::clean_meta( array( 'requires_proxy' => 0, 'meat_to_needy' => 0, 'intention' => '', 'track_items' => 0, 'file_completes' => 0, 'labels' => array(), 'lock_preselect' => 0, 'grouping' => 'none', 'min_by_currency' => array(), 'presets_by_currency' => array(), 'i18n' => array() ) ) );
		$meta = array_intersect_key( (array) $t['meta'], array_flip( $keys ) );
		return self::type_data(
			array(
				'title'           => $t['title'],
				'category'        => $t['category'],
				'description'     => $t['description'],
				'currency'        => $t['currency'],
				'status'          => $t['status'],
				'goal_amount'     => $t['goal_amount'],
				'start_date'      => (string) $t['start_date'],
				'end_date'        => (string) $t['end_date'],
				'allow_free'      => $t['allow_free'],
				'min_amount'      => $t['min_amount'],
				'allow_quantity'  => $t['allow_quantity'],
				'max_quantity'    => $t['max_quantity'],
				'allow_recurring' => $t['allow_recurring'],
				'options'         => $t['options'],
				'meta'            => $meta,
			)
		);
	}

	public static function type_save( array $item, $id ) {
		$d         = self::type_data( $item );
		$d['slug'] = sanitize_title( (string) ( $item['key'] ?? '' ) );
		if ( $id ) {
			$old = FLXB_Types::get( (int) $id );
			if ( $old ) {
				$d['slug']     = $old['slug']; // an owned type keeps its address
				$d['form_ids'] = array_filter( array_map( 'absint', (array) $old['form_ids'] ) ); // FLXB_Types::get() gives an array
			}
		}
		return FLXB_Types::save( $d, (int) $id );
	}

	/* ---------- Partners ---------- */

	public static function partner_data( array $item ) {
		$types = array();
		foreach ( (array) ( $item['types'] ?? array() ) as $slug ) {
			$types[] = sanitize_title( (string) $slug );
		}
		sort( $types );
		$prices = array();
		foreach ( (array) ( $item['prices'] ?? array() ) as $p ) {
			$prices[] = array(
				'type'       => sanitize_title( (string) ( $p['type'] ?? '' ) ),
				'option'     => sanitize_key( (string) ( $p['option'] ?? '' ) ),
				'price'      => (float) ( $p['price'] ?? 0 ),
				'currency'   => strtoupper( (string) ( $p['currency'] ?? ( $item['currency'] ?? flxb_default_currency() ) ) ),
				'valid_from' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $p['valid_from'] ?? '' ) ) ? (string) $p['valid_from'] : '1970-01-01',
				'active'     => (int) ( ! isset( $p['active'] ) || ! empty( $p['active'] ) ),
			);
		}
		usort(
			$prices,
			function ( $a, $b ) {
				return array( $a['type'], $a['option'], $a['valid_from'] ) <=> array( $b['type'], $b['option'], $b['valid_from'] );
			}
		);
		return array(
			'name'         => (string) ( $item['name'] ?? $item['key'] ?? '' ),
			'group_name'   => (string) ( $item['group_name'] ?? '' ),
			'is_service'   => (int) ( ! isset( $item['is_service'] ) || ! empty( $item['is_service'] ) ),
			'is_source'    => (int) ! empty( $item['is_source'] ),
			'types'        => $types,
			'contact_name' => (string) ( $item['contact_name'] ?? '' ),
			'phone'        => (string) ( $item['phone'] ?? '' ),
			'email'        => (string) ( $item['email'] ?? '' ),
			'currency'     => strtoupper( (string) ( $item['currency'] ?? flxb_default_currency() ) ),
			'active'       => (int) ( ! isset( $item['active'] ) || ! empty( $item['active'] ) ),
			'note'         => (string) ( $item['note'] ?? '' ),
			'prices'       => $prices,
		);
	}

	public static function partner_read( $id ) {
		$p = FLXB_Partners::get( (int) $id );
		if ( ! $p ) {
			return array();
		}
		$types = array();
		foreach ( FLXB_Partners::type_ids( $p ) as $tid ) {
			$s = self::type_slug( $tid );
			if ( '' !== $s ) {
				$types[] = $s;
			}
		}
		$prices = array();
		foreach ( FLXB_Partners::prices( (int) $id ) as $pr ) {
			$prices[] = array(
				'type'       => self::type_slug( $pr['type_id'] ),
				'option'     => $pr['option_key'],
				'price'      => $pr['price'],
				'currency'   => $pr['currency'],
				'valid_from' => $pr['valid_from'],
				'active'     => $pr['active'],
			);
		}
		return self::partner_data( array_merge( $p, array( 'types' => $types, 'prices' => $prices ) ) );
	}

	public static function partner_save( array $item, $id ) {
		$d   = self::partner_data( $item );
		$row = $d;
		unset( $row['types'], $row['prices'] );
		$row['pkey']     = sanitize_key( (string) ( $item['key'] ?? '' ) );
		$row['type_ids'] = array_values( array_filter( array_map( array( __CLASS__, 'type_id' ), $d['types'] ) ) );
		$pid             = FLXB_Partners::save( $row, (int) $id );
		if ( is_wp_error( $pid ) ) {
			return $pid;
		}
		/* Agreements: the preset's list is the whole list. */
		foreach ( FLXB_Partners::prices( (int) $pid ) as $old ) {
			FLXB_Partners::delete_price( (int) $old['id'] );
		}
		foreach ( $d['prices'] as $p ) {
			$tid = self::type_id( $p['type'] );
			if ( ! $tid ) {
				continue;
			}
			FLXB_Partners::save_price(
				array(
					'partner_id' => (int) $pid,
					'type_id'    => $tid,
					'option_key' => $p['option'],
					'price'      => $p['price'],
					'currency'   => $p['currency'],
					'valid_from' => $p['valid_from'],
					'active'     => $p['active'],
				)
			);
		}
		FLXB_Partners::flush();
		return (int) $pid;
	}

	/* ---------- Reports and tabs ---------- */

	public static function report_data( array $item ) {
		$r = self::strip( $item );
		ksort( $r );
		return $r;
	}

	public static function report_read( $id ) {
		$key = self::nkey( 'report', $id );
		$own = FLXB_Reports::custom();
		return '' !== $key && isset( $own[ $key ] ) ? self::report_data( array_diff_key( $own[ $key ], array( 'id' => 1, 'schema' => 1 ) ) ) : array();
	}

	public static function report_save( array $item, $id ) {
		$key       = sanitize_key( (string) ( $item['key'] ?? self::nkey( 'report', $id ) ) );
		$r         = self::report_data( $item );
		$r['id']   = $key;
		$res       = FLXB_Reports::save( $r, $key );
		return is_wp_error( $res ) ? $res : self::nid( 'report', $key );
	}

	public static function tab_data( array $item ) {
		$t = self::strip( $item );
		ksort( $t );
		return $t;
	}

	private static function tab_raw( $key ) {
		foreach ( FLXB_Detail_Tabs::custom() as $t ) {
			if ( is_array( $t ) && sanitize_key( (string) ( $t['id'] ?? '' ) ) === $key ) {
				return $t;
			}
		}
		return null;
	}

	public static function tab_read( $id ) {
		$key = self::nkey( 'tab', $id );
		$t   = '' !== $key ? self::tab_raw( $key ) : null;
		return $t ? self::tab_data( array_diff_key( $t, array( 'id' => 1 ) ) ) : array();
	}

	public static function tab_save( array $item, $id ) {
		$key     = sanitize_key( (string) ( $item['key'] ?? self::nkey( 'tab', $id ) ) );
		$t       = self::tab_data( $item );
		$t['id'] = $key;
		$e       = array();
		if ( ! FLXB_Detail_Tabs::clean( $t, $e ) || $e ) {
			return new WP_Error( 'flxb_tab', implode( ' ', $e ) );
		}
		$list = array();
		$done = false;
		foreach ( FLXB_Detail_Tabs::custom() as $x ) {
			if ( is_array( $x ) && sanitize_key( (string) ( $x['id'] ?? '' ) ) === $key ) {
				$x    = $t;
				$done = true;
			}
			$list[] = $x;
		}
		if ( ! $done ) {
			$list[] = $t;
		}
		$res = FLXB_Detail_Tabs::save_json( wp_json_encode( $list ) );
		return is_wp_error( $res ) ? $res : self::nid( 'tab', $key );
	}

	/* ---------- Registration ---------- */

	public static function importers( $t ) {
		$t['flxb_type'] = array(
			'label'   => __( 'Donation type', 'flx-donate' ),
			'find'    => function ( array $item ) {
				return self::type_id( sanitize_title( (string) ( $item['key'] ?? '' ) ) );
			},
			'data'    => array( __CLASS__, 'type_data' ),
			'read'    => array( __CLASS__, 'type_read' ),
			'save'    => array( __CLASS__, 'type_save' ),
			'disable' => function ( $id ) {
				FLXB_Types::set_status( (int) $id, 'closed' );
			},
			'url'     => function ( $id ) {
				return FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => (int) $id ) );
			},
		);
		$t['flxb_partner'] = array(
			'label'   => __( 'Partner', 'flx-donate' ),
			'find'    => function ( array $item ) {
				$p = FLXB_Partners::by_key( sanitize_key( (string) ( $item['key'] ?? '' ) ) );
				if ( ! $p && ! empty( $item['name'] ) ) {
					$p = FLXB_Partners::find( (string) $item['name'] );
				}
				return $p ? (int) $p['id'] : 0;
			},
			'data'    => array( __CLASS__, 'partner_data' ),
			'read'    => array( __CLASS__, 'partner_read' ),
			'save'    => array( __CLASS__, 'partner_save' ),
			'disable' => function ( $id ) {
				$p = FLXB_Partners::get( (int) $id );
				if ( $p ) {
					$p['active'] = 0;
					FLXB_Partners::save( $p, (int) $id );
				}
			},
			'url'     => function ( $id ) {
				return FLXB_Admin_Url::page( 'flxb-partners', array( 'id' => (int) $id ) );
			},
		);
		$t['flxb_report'] = array(
			'label'   => __( 'Report', 'flx-donate' ),
			'find'    => function ( array $item ) {
				$k = sanitize_key( (string) ( $item['key'] ?? '' ) );
				return isset( FLXB_Reports::custom()[ $k ] ) ? self::nid( 'report', $k ) : 0;
			},
			'data'    => array( __CLASS__, 'report_data' ),
			'read'    => array( __CLASS__, 'report_read' ),
			'save'    => array( __CLASS__, 'report_save' ),
			'disable' => function ( $id ) {
				$k = self::nkey( 'report', $id );
				if ( '' !== $k ) {
					FLXB_Reports::delete( $k );
				}
			},
			'url'     => function ( $id ) {
				return FLXB_Admin_Url::page( 'flxb-reports', array( 'r' => self::nkey( 'report', $id ) ) );
			},
		);
		$t['flxb_detail_tab'] = array(
			'label'   => __( '\\nExtra tab\\n', 'flx-donate' ),
			'find'    => function ( array $item ) {
				$k = sanitize_key( (string) ( $item['key'] ?? '' ) );
				return self::tab_raw( $k ) ? self::nid( 'tab', $k ) : 0;
			},
			'data'    => array( __CLASS__, 'tab_data' ),
			'read'    => array( __CLASS__, 'tab_read' ),
			'save'    => array( __CLASS__, 'tab_save' ),
			'disable' => function ( $id ) {
				$k    = self::nkey( 'tab', $id );
				$list = array();
				foreach ( FLXB_Detail_Tabs::custom() as $x ) {
					if ( sanitize_key( (string) ( $x['id'] ?? '' ) ) !== $k ) {
						$list[] = $x;
					}
				}
				FLXB_Detail_Tabs::save_json( wp_json_encode( $list ) );
			},
			'url'     => function () {
				return FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => 'tabs' ) );
			},
		);
		return $t;
	}

	/** "Make a preset from this site": types, partners, own reports and tabs. */
	public static function export_content( $content, $args = array() ) {
		$content = (array) $content;
		foreach ( FLXB_Types::all() as $t ) {
			$content[] = array( 'type' => 'flxb_type', 'key' => $t['slug'] ) + self::type_read( (int) $t['id'] );
		}
		foreach ( FLXB_Partners::all() as $id => $p ) {
			$content[] = array( 'type' => 'flxb_partner', 'key' => '' !== (string) $p['pkey'] ? $p['pkey'] : sanitize_key( remove_accents( $p['name'] ) ) ) + self::partner_read( (int) $id );
		}
		foreach ( FLXB_Reports::custom() as $k => $r ) {
			$content[] = array( 'type' => 'flxb_report', 'key' => $k ) + self::report_data( array_diff_key( (array) $r, array( 'id' => 1, 'schema' => 1 ) ) );
		}
		foreach ( FLXB_Detail_Tabs::custom() as $tab ) {
			if ( ! empty( $tab['id'] ) ) {
				$content[] = array( 'type' => 'flxb_detail_tab', 'key' => sanitize_key( $tab['id'] ) ) + self::tab_data( array_diff_key( $tab, array( 'id' => 1 ) ) );
			}
		}
		return $content;
	}
}
