<?php
/**
 * Certificate people: the shared people table, scoped to this package.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * People reads and writes shared by REST, admin-post and GraphQL.
 */
final class FLXS_Person_Service {

	/**
	 * REST GET /people (picker, at most 8).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_rest( WP_REST_Request $r ): array {
		$rows = flxs_search_people( FLXS_Val::str( $r->get_param( 'search' ) ), 8, flxs_own_ref() );
		$out  = array();
		foreach ( $rows as $row ) {
			$pub = flxs_person_public( $row );
			if ( is_array( $pub ) ) {
				$out[] = $pub;
			}
		}
		return $out;
	}

	/**
	 * REST GET /directory.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public static function directory_rest( WP_REST_Request $r ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$per   = min( 100, max( 1, FLXS_Val::int( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
		$page  = max( 1, FLXS_Val::int( $r->get_param( 'page' ) ) );
		$s     = sanitize_text_field( FLXS_Val::str($r->get_param( 's' )) );
		$t     = flxs_table( 'people' );
		$where = array( flxs_people_mine_sql( 'p' ) );
		$prm   = array();
		if ( '' !== $s ) {
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			$where[] = '(p.name LIKE %s OR p.email LIKE %s OR p.phone LIKE %s)';
			array_push( $prm, $like, $like, $like );
		}
		$scope = flxs_people_scope_sql( 'p' );
		if ( $scope ) {
			$where[] = $scope;
		}
		$w     = implode( ' AND ', $where );
		$total = FLXS_Val::int(( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} p WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} p WHERE {$w}" ) )); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$sql   = "SELECT p.* FROM {$t} p WHERE {$w} ORDER BY p.id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $per, ( $page - 1 ) * $per );
		$rows  = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$items = array();
		foreach ( (array) $rows as $raw ) {
			$row = FLXS_Val::row( $raw );
			$pub = flxs_person_public( $row );
			if ( ! is_array( $pub ) ) {
				continue;
			}
			$pub['date_label'] = flxs_date( $row['created_at'] );
			$pub['certs']      = FLXS_Val::int( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id = %d', FLXS_Val::int( $row['id'] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- @phpstan-ignore argument.type
			$items[]           = $pub;
		}
		return array( 'items' => $items, 'total' => $total, 'pages' => flxs_rest_pages( $total, $per ) );
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function directory( string $search, int $offset, int $limit ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$t     = flxs_table( 'people' );
		$where = array( flxs_people_mine_sql( 'p' ) );
		$prm   = array();
		$s     = trim( $search );
		if ( '' !== $s ) {
			$like  = '%' . $wpdb->esc_like( $s ) . '%';
			$dig   = preg_replace( '/\D+/', '', $s );
			$where[] = '(p.name LIKE %s OR p.email LIKE %s' . ( is_string( $dig ) && strlen( $dig ) >= 3 ? ' OR p.phone LIKE %s' : '' ) . ')';
			$prm   = array( $like, $like );
		if ( is_string( $dig ) && strlen( $dig ) >= 3 ) {
			$prm[] = '%' . $wpdb->esc_like( ltrim( $dig, '0' ) ) . '%';
		}
		}
		$scope = self::scope_sql( 'p' );
		if ( $scope ) {
			$where[] = $scope;
		}
		$w     = implode( ' AND ', $where );
		$total = FLXS_Val::int(( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} p WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} p WHERE {$w}" ) )); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$cscope = flxs_scope_sql( 'c' );
		$count  = '(SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' c WHERE c.person_id = p.id' . ( $cscope ? ' AND ' . $cscope : '' ) . ')';
		$sql    = "SELECT p.*, {$count} AS certs FROM {$t} p WHERE {$w} ORDER BY p.id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		$rows   = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$items  = array();
		foreach ( (array) $rows as $raw ) {
			$items[] = FLXS_Val::row( $raw );
		}
		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * Picker. Own-ref staff only see people they may see.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( string $term, int $limit = 8 ): array {
		$rows = flxs_search_people( $term, $limit, flxs_scoped() );
		if ( ! flxs_own_only() ) {
			return $rows;
		}
		return array_values( array_filter( $rows, static fn( $row ): bool => self::can_see( FLXS_Val::int(( $row['id'] ?? 0 )) ) ) );
	}

	/**
	 * GraphQL visibility. own_ref uses the classic person scope; staff_own_only also hides people
	 * who are not on a certificate this user created (the classic people page does not).
	 */
	public static function can_see( int $id ): bool {
		if ( $id <= 0 || ! is_array( flxs_get_person( $id ) ) ) {
			return false;
		}
		if ( flxs_own_ref() ) {
			return flxs_can_see_person( $id );
		}
		if ( ! flxs_own_only() ) {
			return true;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$uid = get_current_user_id();
		$own = FLXS_Val::int( $wpdb->get_var( $wpdb->prepare( 'SELECT created_by FROM ' . flxs_table( 'people' ) . ' WHERE id = %d', $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- @phpstan-ignore argument.type
		if ( $own === $uid ) {
			return true;
		}
		$hit = FLXS_Val::int( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id = %d AND created_by = %d LIMIT 1', $id, $uid ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- @phpstan-ignore argument.type
		return $hit > 0;
	}

	/**
	 * SQL for the GraphQL people list. Empty when the viewer is not scoped.
	 */
	public static function scope_sql( string $alias = 'p' ): string {
		if ( flxs_own_ref() ) {
			return flxs_people_scope_sql( $alias );
		}
		if ( ! flxs_own_only() ) {
			return '';
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$a = $alias ? $alias . '.' : '';
		$sql = $wpdb->prepare( "({$a}created_by = %d OR {$a}id IN (SELECT person_id FROM " . flxs_table( 'certificates' ) . ' WHERE created_by = %d))', get_current_user_id(), get_current_user_id() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- @phpstan-ignore argument.type
		return is_string( $sql ) ? $sql : '';
	}

	/**
	 * @param int[] $ids Ids.
	 * @return array<int,array<string,mixed>>
	 */
	public static function by_ids( array $ids ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$ids = array_values( array_filter( array_map( static fn ( $v ): int => FLXS_Val::int( $v ), $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . flxs_table( 'people' ) . ' WHERE id IN (' . implode( ',', $ids ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array();
		foreach ( (array) $rows as $raw ) {
			$row = FLXS_Val::row( $raw );
			$out[ FLXS_Val::int( $row['id'] ) ] = $row;
		}
		return $out;
	}

	/**
	 * @param int[] $ids Person ids.
	 * @return array<int,int>
	 */
	public static function certificate_counts( array $ids ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$ids = array_values( array_filter( array_map( static fn ( $v ): int => FLXS_Val::int( $v ), $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$where = 'person_id IN (' . implode( ',', $ids ) . ')';
		$scope = flxs_scope_sql( '' );
		if ( $scope ) {
			$where .= ' AND ' . $scope;
		}
		$rows = $wpdb->get_results( 'SELECT person_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . " WHERE {$where} GROUP BY person_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ FLXS_Val::int($r['person_id']) ] = FLXS_Val::int($r['n']);
		}
		return $out;
	}

	/**
	 * Admin-post save. Redirect args match the classic handler.
	 *
	 * @return array{page:string,args:array<string,string|int>}
	 */
	public static function save_admin( int $id, string $name, string $phone, string $email, string $note ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$name  = sanitize_text_field( $name );
		$phone = sanitize_text_field( $phone );
		$email = sanitize_text_field( $email );
		$note  = sanitize_textarea_field( $note );
		if ( ! $id ) {
			$r = FLXS_Val::mix( flxs_upsert_person( $name, $phone, $email ) );
			if ( is_wp_error( $r ) || ! is_array( $r ) ) {
				return array( 'page' => 'flxs-kisiler', 'args' => array( 'add' => 1, 'flxs_err' => is_wp_error( $r ) ? $r->get_error_message() : 'Kişi kaydedilemedi.' ) );
			}
			$holder = FLXS_Val::row( $r['person'] ?? null );
			$id     = FLXS_Val::int( $holder['id'] );
			if ( $note ) {
				$wpdb->update( flxs_table( 'people' ), array( 'note' => $note ), array( 'id' => $id ) );
			}
			self::changed( $id );
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_msg' => ! empty( $r['created'] ) ? 'Kişi eklendi.' : 'Bu telefon/e-posta zaten kayıtlıydı, kişi güncellendi.' ) );
		}
		if ( ! flxs_can_see_person( $id ) ) {
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'flxs_err' => 'Yetki yok' ) );
		}
		$p      = flxs_get_person( $id );
		$nphone = flxs_normalize_phone( $phone );
		$nemail = strtolower( sanitize_email( $email ) );
		if ( $phone && ! $nphone ) {
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_err' => 'Telefon numarası geçersiz.' ) );
		}
		if ( $nphone && ( $o = flxs_find_person_by_phone( $nphone ) ) && FLXS_Val::int($o['id']) !== $id ) {
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_err' => 'Bu telefon başka bir kişide kayıtlı: ' . FLXS_Val::str( $o['name'] ) ) );
		}
		if ( $nemail && ( $o = flxs_find_person_by_email( $nemail ) ) && FLXS_Val::int($o['id']) !== $id ) {
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_err' => 'Bu e-posta başka bir kişide kayıtlı: ' . FLXS_Val::str( $o['name'] ) ) );
		}
		$data = array( 'name' => $name, 'phone' => $nphone ? $nphone : '', 'email' => $nemail ? $nemail : '', 'note' => $note );
		if ( function_exists( 'flx_person_update' ) ) {
			$res = flx_person_update( $id, $data );
			if ( is_wp_error( $res ) ) {
				return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_err' => $res->get_error_message() ) );
			}
		} else {
			$wpdb->update( flxs_table( 'people' ), $data + array( 'updated_at' => flxs_now() ), array( 'id' => $id ) );
		}
		flxs_log( 'person_updated', 'person', $id, $name, array( 'before' => array_intersect_key( (array) $p, $data ), 'after' => $data ) );
		self::changed( $id );
		return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_msg' => 'Kaydedildi.' ) );
	}

	/**
	 * @return array{page:string,args:array<string,string|int>}
	 */
	public static function delete_admin( int $id ): array {
		$r = flxs_delete_person( $id );
		if ( is_wp_error( $r ) ) {
			return array( 'page' => 'flxs-kisiler', 'args' => array( 'person' => $id, 'flxs_err' => $r->get_error_message() ) );
		}
		self::changed( $id );
		return array( 'page' => 'flxs-kisiler', 'args' => array( 'flxs_msg' => 'Kişi silindi.' ) );
	}

	/**
	 * @param array<string,mixed> $in Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save( array $in ) {
		global $wpdb;
		/** @var wpdb $wpdb */
		$id    = FLXS_Val::int(( $in['id'] ?? 0 ));
		$name  = sanitize_text_field( FLXS_Val::str(( $in['name'] ?? '' )) );
		$phone = sanitize_text_field( FLXS_Val::str(( $in['phone'] ?? '' )) );
		$email = sanitize_text_field( FLXS_Val::str(( $in['email'] ?? '' )) );
		$note  = sanitize_textarea_field( FLXS_Val::str(( $in['note'] ?? '' )) );
		if ( ! $id ) {
			$r = FLXS_Val::mix( flxs_upsert_person( $name, $phone, $email ) );
			if ( is_wp_error( $r ) || ! is_array( $r ) ) {
				return is_wp_error( $r ) ? FLXS_Records::classify( $r ) : FLXS_Records::error( 'flxs_person_db', __( 'The person could not be saved.', 'flx-certificates' ), 'INTERNAL' );
			}
			$holder = FLXS_Val::row( $r['person'] ?? null );
			$id     = FLXS_Val::int( $holder['id'] );
			if ( '' !== $note ) {
				$wpdb->update( flxs_table( 'people' ), array( 'note' => $note ), array( 'id' => $id ) );
			}
			self::changed( $id );
			$row = flxs_get_person( $id );
			return is_array( $row ) ? $row : FLXS_Records::error( 'flxs_person_db', __( 'The person could not be saved.', 'flx-certificates' ), 'INTERNAL' );
		}
		if ( ! self::can_see( $id ) ) {
			return FLXS_Records::error( 'flxs_404', __( 'Person not found.', 'flx-certificates' ), 'NOT_FOUND', 'id', 404 );
		}
		$p      = flxs_get_person( $id );
		$nphone = flxs_normalize_phone( $phone );
		$nemail = strtolower( sanitize_email( $email ) );
		if ( '' !== $phone && ! $nphone ) {
			return FLXS_Records::error( 'flxs_phone', __( 'That phone number is not valid.', 'flx-certificates' ), 'INVALID_INPUT', 'phone' );
		}
		if ( $nphone && ( $o = flxs_find_person_by_phone( $nphone ) ) && FLXS_Val::int($o['id']) !== $id ) {
			return FLXS_Records::error( 'flxs_phone', __( 'That phone number belongs to someone else.', 'flx-certificates' ), 'CONFLICT', 'phone' );
		}
		if ( $nemail && ( $o = flxs_find_person_by_email( $nemail ) ) && FLXS_Val::int($o['id']) !== $id ) {
			return FLXS_Records::error( 'flxs_email', __( 'That e-mail belongs to someone else.', 'flx-certificates' ), 'CONFLICT', 'email' );
		}
		$data = array( 'name' => $name, 'phone' => $nphone ? $nphone : '', 'email' => $nemail ? $nemail : '', 'note' => $note );
		if ( function_exists( 'flx_person_update' ) ) {
			$res = flx_person_update( $id, $data );
			if ( is_wp_error( $res ) ) {
				return FLXS_Records::classify( $res );
			}
		} else {
			$wpdb->update( flxs_table( 'people' ), $data + array( 'updated_at' => flxs_now() ), array( 'id' => $id ) );
		}
		flxs_log( 'person_updated', 'person', $id, $name, array( 'before' => array_intersect_key( (array) $p, $data ), 'after' => $data ) );
		self::changed( $id );
		$row = flxs_get_person( $id );
		return is_array( $row ) ? $row : FLXS_Records::error( 'flxs_404', __( 'Person not found.', 'flx-certificates' ), 'NOT_FOUND', 'id', 404 );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! flxs_can_manage() ) {
			return FLXS_Records::error( 'flxs_forbidden', __( 'Only a manager can delete a person.', 'flx-certificates' ), 'FORBIDDEN', '', 403 );
		}
		if ( ! self::can_see( $id ) ) {
			return FLXS_Records::error( 'flxs_404', __( 'Person not found.', 'flx-certificates' ), 'NOT_FOUND', 'id', 404 );
		}
		$r = flxs_delete_person( $id );
		if ( is_wp_error( $r ) ) {
			return FLXS_Records::classify( $r );
		}
		self::changed( $id );
		return true;
	}

	public static function changed( int $id ): void {
		FLXS_Records::purge( array( FLXS_Records::TAG_PEOPLE, 'flx-certificates:person:' . $id, FLXS_Records::TAG_LIST ) );
	}
}
