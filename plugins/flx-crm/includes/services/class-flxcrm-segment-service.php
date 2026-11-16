<?php
/**
 * Segments: saved rule lists, the live preview and the rule editor's vocabulary.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Segment_Service {

	public const TAG = 'flx-crm:segments';

	/**
	 * Row → typed segment.
	 *
	 * @param array<mixed> $row Segment row (rules decoded).
	 */
	public static function build( array $row ): FLXCRM_Segment {
		$rules = FLXCRM_Val::arr( $row['rules'] ?? array() );
		$list  = array();
		foreach ( FLXCRM_Val::arr( $rules['rules'] ?? array() ) as $r ) {
			$r      = FLXCRM_Val::arr( $r );
			$list[] = array(
				'field' => FLXCRM_Val::str( $r['field'] ?? '' ),
				'op'    => FLXCRM_Val::str( $r['op'] ?? '' ),
				'value' => FLXCRM_Val::str( $r['value'] ?? '' ),
			);
		}
		return new FLXCRM_Segment(
			FLXCRM_Val::int( $row['id'] ?? 0 ),
			FLXCRM_Val::str( $row['name'] ?? '' ),
			FLXCRM_Val::str( $row['description'] ?? '' ),
			FLXCRM_Val::int( $row['count'] ?? 0 ),
			FLXCRM_Val::str( FLXCRM_Segments::describe( $rules ) ),
			'any' === ( $rules['match'] ?? '' ) ? 'any' : 'all',
			$list
		);
	}

	/**
	 * @return list<FLXCRM_Segment>
	 */
	public static function all(): array {
		$out = array();
		foreach ( FLXCRM_Val::arr( FLXCRM_Segments::all() ) as $row ) {
			$out[] = self::build( FLXCRM_Val::arr( $row ) );
		}
		return $out;
	}

	public static function get( int $id ): ?FLXCRM_Segment {
		$row = FLXCRM_Segments::get( $id );
		return is_array( $row ) ? self::build( $row ) : null;
	}

	/**
	 * Rules input → the raw rule array the storage sanitises.
	 *
	 * @param string                                          $match all | any.
	 * @param list<array{field: string, op: string, value: string}> $rules Rules.
	 * @return array{match: string, rules: list<array{field: string, op: string, value: string}>}
	 */
	public static function rules_of( string $match, array $rules ): array {
		return array( 'match' => 'any' === $match ? 'any' : 'all', 'rules' => $rules );
	}

	/**
	 * Saves a segment (new when $id is 0).
	 *
	 * @param array{name: string, description: string, match: string, rules: list<array{field: string, op: string, value: string}>} $in Fields.
	 * @return FLXCRM_Segment|WP_Error
	 */
	public static function save( array $in, int $id = 0 ) {
		if ( $id > 0 && null === FLXCRM_Segments::get( $id ) ) {
			return FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Segment not found.', 'flx-crm' ), 'NOT_FOUND' );
		}
		$res = FLXCRM_Segments::save(
			array(
				'name'        => $in['name'],
				'description' => $in['description'],
				'rules'       => self::rules_of( $in['match'], $in['rules'] ),
			),
			$id
		);
		if ( $res instanceof WP_Error ) {
			return FLXCRM_Contact_Service::error( (string) $res->get_error_code(), $res->get_error_message(), 'INVALID_INPUT', 'flxcrm_name' === $res->get_error_code() ? 'name' : 'rules' );
		}
		self::changed();
		$s = self::get( $res );
		return $s ? $s : FLXCRM_Contact_Service::error( 'flxcrm_missing', __( 'Segment not found.', 'flx-crm' ), 'NOT_FOUND' );
	}

	public static function delete( int $id ): bool {
		if ( null === FLXCRM_Segments::get( $id ) ) {
			return false;
		}
		FLXCRM_Segments::delete( $id );
		self::changed();
		return true;
	}

	public static function changed(): void {
		FLXCRM_Contacts::flush_stats();
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::TAG, FLXCRM_Contact_Service::TAG_OVERVIEW, FLXCRM_Contact_Service::TAG_META ) );
		}
	}

	/**
	 * Live count while a rule list is edited.
	 *
	 * @param string                                          $match all | any.
	 * @param list<array{field: string, op: string, value: string}> $rules Rules.
	 * @return array{count: int, summary: string, sample: list<array{id: int, name: string}>}
	 */
	public static function preview( string $match, array $rules ): array {
		$clean  = FLXCRM_Segments::sanitize_rules( self::rules_of( $match, $rules ) );
		$q      = FLXCRM_Val::arr( FLXCRM_Contacts::query( array( 'rules' => $clean, 'per_page' => 5, 'orderby' => 'ltv' ) ) );
		$sample = array();
		foreach ( FLXCRM_Val::arr( $q['rows'] ?? array() ) as $c ) {
			$c        = FLXCRM_Val::arr( $c );
			$sample[] = array( 'id' => FLXCRM_Val::int( $c['id'] ?? 0 ), 'name' => FLXCRM_Val::str( flxcrm_display_name( $c ) ) );
		}
		return array(
			'count'   => FLXCRM_Val::int( $q['total'] ?? 0 ),
			'summary' => FLXCRM_Val::str( FLXCRM_Segments::describe( $clean ) ),
			'sample'  => $sample,
		);
	}

	/**
	 * What the rule editor offers: fields with their operators, operator names, and the choices of
	 * each value type.
	 *
	 * @return array{fields: list<array{key: string, label: string, type: string, ops: list<string>}>, ops: list<array{key: string, label: string}>, options: list<array{type: string, values: list<array{value: string, label: string}>}>}
	 */
	public static function editor(): array {
		$fields = array();
		foreach ( FLXCRM_Val::arr( FLXCRM_Segments::fields() ) as $k => $f ) {
			$f        = FLXCRM_Val::arr( $f );
			$fields[] = array(
				'key'   => (string) $k,
				'label' => FLXCRM_Val::str( $f[0] ?? '' ),
				'type'  => FLXCRM_Val::str( $f[1] ?? '' ),
				'ops'   => FLXCRM_Val::strings( $f[2] ?? array() ),
			);
		}
		$ops = array();
		foreach ( FLXCRM_Val::arr( FLXCRM_Segments::op_labels() ) as $k => $l ) {
			$ops[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $l ) );
		}
		$opt = array( 'role' => array(), 'tag' => array(), 'rfm' => array(), 'consent' => array(), 'source' => array() );
		foreach ( flxcrm_roles() as $k => $r ) {
			$opt['role'][] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( FLXCRM_Val::arr( $r )['label'] ?? $k ) );
		}
		foreach ( FLXCRM_Val::arr( FLXCRM_Tags::all() ) as $t ) {
			$t             = FLXCRM_Val::arr( $t );
			$opt['tag'][]  = array( 'value' => FLXCRM_Val::str( $t['id'] ?? '' ), 'label' => FLXCRM_Val::str( $t['name'] ?? '' ) );
		}
		foreach ( flxcrm_rfm_segments() as $k => $s ) {
			$opt['rfm'][] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( FLXCRM_Val::arr( $s )[0] ?? $k ) );
		}
		$opt['rfm'][] = array( 'value' => 'none', 'label' => __( 'No payments', 'flx-crm' ) );
		foreach ( FLXCRM_Val::arr( FLXCRM_Contacts::consent_labels() ) as $k => $l ) {
			$opt['consent'][] = array( 'value' => (string) $k, 'label' => $l );
		}
		foreach ( flxcrm_sources() as $k => $l ) {
			$opt['source'][] = array( 'value' => (string) $k, 'label' => FLXCRM_Val::str( $l ) );
		}
		$options = array();
		foreach ( $opt as $type => $values ) {
			$options[] = array( 'type' => $type, 'values' => $values );
		}
		return array( 'fields' => $fields, 'ops' => $ops, 'options' => $options );
	}
}
