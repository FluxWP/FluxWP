<?php
/**
 * Kayıtlı dinamik segmentler: kurallar her sorguda yeniden çalışır (liste canlıdır), sayı önbellekte tutulur.
 *
 * Kural biçimi: { match: all|any, rules: [ { field, op, value } ] }
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Segments {

	/**
	 * Kural alanları: field => [ etiket, tür, işlemler ].
	 * Tür: role | tag | rfm | number | days | consent | source | text | bool
	 */
	public static function fields() {
		return (array) apply_filters(
			'flxcrm_segment_fields',
			array(
				'role'          => array( __( 'Role', 'flx-crm' ), 'role', array( 'has', 'not' ) ),
				'tag'           => array( __( 'Tag', 'flx-crm' ), 'tag', array( 'has', 'not' ) ),
				'rfm'           => array( __( 'RFM segment', 'flx-crm' ), 'rfm', array( 'is', 'not' ) ),
				'ltv'           => array( __( 'Total value', 'flx-crm' ), 'number', array( 'gte', 'lte' ) ),
				'paid_days'     => array( __( 'Days with a payment', 'flx-crm' ), 'number', array( 'gte', 'lte' ) ),
				'last_paid'     => array( __( 'Last payment', 'flx-crm' ), 'days', array( 'within', 'before' ) ),
				'last_activity' => array( __( 'Last activity', 'flx-crm' ), 'days', array( 'within', 'before' ) ),
				'created'       => array( __( 'Added', 'flx-crm' ), 'days', array( 'within', 'before' ) ),
				'churn'         => array( __( 'Churn risk (%)', 'flx-crm' ), 'number', array( 'gte', 'lte' ) ),
				'consent'       => array( __( 'Consent', 'flx-crm' ), 'consent', array( 'yes', 'no' ) ),
				'source'        => array( __( 'First source', 'flx-crm' ), 'source', array( 'is', 'not' ) ),
				'city'          => array( __( 'City', 'flx-crm' ), 'text', array( 'contains', 'is' ) ),
				'has_email'     => array( __( 'Has an e-mail', 'flx-crm' ), 'bool', array( 'yes', 'no' ) ),
				'has_phone'     => array( __( 'Has a phone', 'flx-crm' ), 'bool', array( 'yes', 'no' ) ),
			)
		);
	}

	public static function op_labels() {
		return array(
			'has'      => __( 'has', 'flx-crm' ),
			'not'      => __( 'is not / has not', 'flx-crm' ),
			'is'       => __( 'is', 'flx-crm' ),
			'gte'      => __( 'at least', 'flx-crm' ),
			'lte'      => __( 'at most', 'flx-crm' ),
			'within'   => __( 'in the last … days', 'flx-crm' ),
			'before'   => __( 'more than … days ago', 'flx-crm' ),
			'yes'      => __( 'yes', 'flx-crm' ),
			'no'       => __( 'no', 'flx-crm' ),
			'contains' => __( 'contains', 'flx-crm' ),
		);
	}

	/** Kuralları temizler. */
	public static function sanitize_rules( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$fields = self::fields();
		$out    = array( 'match' => 'any' === ( $raw['match'] ?? '' ) ? 'any' : 'all', 'rules' => array() );
		foreach ( (array) ( $raw['rules'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$field = sanitize_key( (string) ( $r['field'] ?? '' ) );
			if ( ! isset( $fields[ $field ] ) ) {
				continue;
			}
			$op = sanitize_key( (string) ( $r['op'] ?? '' ) );
			if ( ! in_array( $op, $fields[ $field ][2], true ) ) {
				$op = $fields[ $field ][2][0];
			}
			$val = $r['value'] ?? '';
			switch ( $fields[ $field ][1] ) {
				case 'number':
				case 'days':
					$val = is_numeric( $val ) ? (float) $val : 0;
					break;
				case 'tag':
					$val = absint( $val );
					break;
				case 'text':
					$val = mb_substr( sanitize_text_field( (string) $val ), 0, 100 );
					break;
				case 'bool':
					$val = '';
					break;
				default:
					$val = sanitize_key( (string) $val );
			}
			$out['rules'][] = array( 'field' => $field, 'op' => $op, 'value' => $val );
		}
		return $out;
	}

	/**
	 * Kuralları SQL'e çevirir (c = kişiler tablosu takma adı).
	 *
	 * @return array [ " AND (…)", params ]
	 */
	public static function compile( array $rules ) {
		global $wpdb;
		$rules = self::sanitize_rules( $rules );
		$parts = array();
		$p     = array();
		foreach ( $rules['rules'] as $r ) {
			$v = $r['value'];
			switch ( $r['field'] ) {
				case 'role':
					$parts[] = ( 'not' === $r['op'] ? 'c.roles NOT LIKE %s' : 'c.roles LIKE %s' );
					$p[]     = '%,' . $wpdb->esc_like( $v ) . ',%';
					break;
				case 'tag':
					$parts[] = ( 'not' === $r['op'] ? 'NOT ' : '' ) . 'EXISTS (SELECT 1 FROM ' . flxcrm_table( 'contact_tags' ) . ' ctr WHERE ctr.contact_id = c.id AND ctr.tag_id = %d)';
					$p[]     = (int) $v;
					break;
				case 'rfm':
					$parts[] = 'c.segment ' . ( 'not' === $r['op'] ? '<>' : '=' ) . ' %s';
					$p[]     = 'none' === $v ? '' : $v;
					break;
				case 'ltv':
				case 'paid_days':
				case 'churn':
					$parts[] = 'c.' . $r['field'] . ( 'lte' === $r['op'] ? ' <= ' : ' >= ' ) . ( 'ltv' === $r['field'] ? '%f' : '%d' );
					$p[]     = 'ltv' === $r['field'] ? (float) $v : (int) $v;
					break;
				case 'last_paid':
				case 'last_activity':
				case 'created':
					$col  = 'last_paid' === $r['field'] ? 'c.last_paid_at' : ( 'last_activity' === $r['field'] ? 'c.last_activity_at' : 'c.created_at' );
					$date = gmdate( 'Y-m-d H:i:s', time() - (int) $v * DAY_IN_SECONDS );
					$parts[] = 'within' === $r['op'] ? "$col >= %s" : "($col IS NOT NULL AND $col < %s)";
					$p[]     = $date;
					break;
				case 'consent':
					if ( in_array( $v, FLXCRM_Contacts::CONSENTS, true ) ) {
						$parts[] = 'c.consent_' . $v . ' = ' . ( 'no' === $r['op'] ? '0' : '1' );
					}
					break;
				case 'source':
					$parts[] = 'c.source ' . ( 'not' === $r['op'] ? '<>' : '=' ) . ' %s';
					$p[]     = $v;
					break;
				case 'city':
					if ( 'is' === $r['op'] ) {
						$parts[] = 'c.city = %s';
						$p[]     = $v;
					} else {
						$parts[] = 'c.city LIKE %s';
						$p[]     = '%' . $wpdb->esc_like( $v ) . '%';
					}
					break;
				case 'has_email':
					$parts[] = 'no' === $r['op'] ? "c.email = ''" : "c.email <> ''";
					break;
				case 'has_phone':
					$parts[] = 'no' === $r['op'] ? "c.phone = ''" : "c.phone <> ''";
					break;
				default:
					$custom = apply_filters( 'flxcrm_segment_compile', null, $r );
					if ( is_array( $custom ) && isset( $custom[0] ) ) {
						$parts[] = $custom[0];
						$p       = array_merge( $p, (array) ( $custom[1] ?? array() ) );
					}
			}
		}
		if ( ! $parts ) {
			return array( '', array() );
		}
		return array( ' AND (' . implode( 'any' === $rules['match'] ? ' OR ' : ' AND ', $parts ) . ')', $p );
	}

	/** Kuralın okunur özeti: "Rol: Bağışçı · Son ödeme: 180 günden eski". */
	public static function describe( array $rules ) {
		$rules  = self::sanitize_rules( $rules );
		$fields = self::fields();
		$ops    = self::op_labels();
		$roles  = flxcrm_roles();
		$tags   = FLXCRM_Tags::all();
		$segs   = flxcrm_rfm_segments();
		$cons   = FLXCRM_Contacts::consent_labels();
		$out    = array();
		foreach ( $rules['rules'] as $r ) {
			$v = $r['value'];
			switch ( $fields[ $r['field'] ][1] ) {
				case 'role':
					$v = $roles[ $v ]['label'] ?? $v;
					break;
				case 'tag':
					$v = $tags[ (int) $v ]['name'] ?? '#' . $v;
					break;
				case 'rfm':
					$v = $segs[ $v ][0] ?? flxcrm_segment_label( $v );
					break;
				case 'consent':
					$v = $cons[ $v ] ?? $v;
					break;
				case 'source':
					$v = flxcrm_source_label( $v );
					break;
				case 'number':
					$v = number_format_i18n( (float) $v );
					break;
				case 'days':
					$n     = number_format_i18n( (int) $v );
					/* translators: 1: field, 2: number of days */
					$out[] = sprintf( 'before' === $r['op'] ? __( '%1$s: more than %2$s days ago', 'flx-crm' ) : __( '%1$s: in the last %2$s days', 'flx-crm' ), $fields[ $r['field'] ][0], $n );
					continue 2;
				case 'bool':
					$out[] = $fields[ $r['field'] ][0] . ': ' . $ops[ $r['op'] ];
					continue 2;
			}
			if ( 'consent' === $fields[ $r['field'] ][1] ) {
				/* translators: %s: channel */
				$out[] = sprintf( 'no' === $r['op'] ? __( 'no %s consent', 'flx-crm' ) : __( 'has %s consent', 'flx-crm' ), $v );
				continue;
			}
			$out[] = $fields[ $r['field'] ][0] . ' ' . $ops[ $r['op'] ] . ( '' !== (string) $v ? ': ' . $v : '' );
		}
		return implode( 'any' === $rules['match'] ? ' · ' . __( 'or', 'flx-crm' ) . ' · ' : ' · ', $out );
	}

	public static function all() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . flxcrm_table( 'segments' ) . ' ORDER BY name', ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $i => $r ) {
			$rules              = json_decode( (string) $r['rules'], true );
			$rows[ $i ]['rules'] = is_array( $rules ) ? $rules : array( 'match' => 'all', 'rules' => array() );
		}
		return $rows;
	}

	public static function get( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'segments' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $r ) {
			return null;
		}
		$r['rules'] = json_decode( (string) $r['rules'], true );
		$r['rules'] = is_array( $r['rules'] ) ? $r['rules'] : array( 'match' => 'all', 'rules' => array() );
		return $r;
	}

	/** @return int|WP_Error */
	public static function save( array $d, $id = 0 ) {
		global $wpdb;
		$name = mb_substr( sanitize_text_field( (string) ( $d['name'] ?? '' ) ), 0, 190 );
		if ( '' === $name ) {
			return new WP_Error( 'flxcrm_name', __( 'A segment name is required.', 'flx-crm' ) );
		}
		$rules = self::sanitize_rules( $d['rules'] ?? array() );
		if ( ! $rules['rules'] ) {
			return new WP_Error( 'flxcrm_rules', __( 'Add at least one rule.', 'flx-crm' ) );
		}
		$row = array(
			'name'        => $name,
			'description' => mb_substr( sanitize_text_field( (string) ( $d['description'] ?? '' ) ), 0, 255 ),
			'rules'       => wp_json_encode( $rules ),
			'count'       => FLXCRM_Contacts::count( array( 'rules' => $rules ) ),
			'counted_at'  => flxcrm_now(),
			'updated_at'  => flxcrm_now(),
		);
		$t = flxcrm_table( 'segments' );
		if ( $id && self::get( $id ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
			return (int) $id;
		}
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = flxcrm_now();
		$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( flxcrm_table( 'segments' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	public static function recount_all() {
		global $wpdb;
		foreach ( self::all() as $s ) {
			$wpdb->update( flxcrm_table( 'segments' ), array( 'count' => FLXCRM_Contacts::count( array( 'rules' => $s['rules'] ) ), 'counted_at' => flxcrm_now() ), array( 'id' => (int) $s['id'] ) ); // phpcs:ignore WordPress.DB
		}
	}

	/** İlk kurulumda örnek segmentler (bir kez). */
	public static function seed() {
		if ( get_option( 'flxcrm_segments_seeded' ) || self::all() ) {
			return;
		}
		update_option( 'flxcrm_segments_seeded', 1, false );
		$risk = (int) flxcrm_setting( 'risk_threshold' );
		$list = array(
			array(
				'name'        => __( 'Donors to win back', 'flx-crm' ),
				'description' => __( 'Donors with a high churn risk. For a check-in or a new project announcement.', 'flx-crm' ),
				'rules'       => array( 'match' => 'all', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'donor' ), array( 'field' => 'churn', 'op' => 'gte', 'value' => $risk ) ) ),
			),
			array(
				'name'        => __( 'New contacts (30 days)', 'flx-crm' ),
				'description' => __( 'Contacts added in the last 30 days. For a welcome message.', 'flx-crm' ),
				'rules'       => array( 'match' => 'all', 'rules' => array( array( 'field' => 'created', 'op' => 'within', 'value' => 30 ) ) ),
			),
			array(
				'name'        => __( 'Champions and loyal supporters', 'flx-crm' ),
				'description' => __( 'The people who support most often and most recently.', 'flx-crm' ),
				'rules'       => array( 'match' => 'any', 'rules' => array( array( 'field' => 'rfm', 'op' => 'is', 'value' => 'champion' ), array( 'field' => 'rfm', 'op' => 'is', 'value' => 'loyal' ) ) ),
			),
		);
		foreach ( $list as $s ) {
			self::save( $s );
		}
	}
}
