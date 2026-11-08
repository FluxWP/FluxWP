<?php
/**
 * WordPress kullanıcıları → kişi + "Üyelik" olayı. Yalnız müşteri/abone gibi ziyaretçi rolleri alınır;
 * personel ve yöneticiler kişi listesine girmez (filtre `flxcrm_user_roles`).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Users {

	const KEY = 'users';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_users' );
	}

	public static function label() {
		return __( 'Site members', 'flx-crm' );
	}

	public static function wp_roles() {
		return (array) apply_filters( 'flxcrm_user_roles', array( 'customer', 'subscriber' ) );
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 30, 1 );
		add_action( 'profile_update', array( __CLASS__, 'on_update' ), 30, 1 );
	}

	private static function eligible( $user ) {
		return $user instanceof WP_User && array_intersect( (array) $user->roles, self::wp_roles() );
	}

	public static function sync( $user, $event = true ) {
		$user = $user instanceof WP_User ? $user : get_userdata( (int) $user );
		if ( ! self::eligible( $user ) ) {
			return 0;
		}
		$name  = trim( $user->first_name . ' ' . $user->last_name );
		$role  = (string) flxcrm_setting( 'role_users' );
		$phone = (string) get_user_meta( $user->ID, 'billing_phone', true );
		$cid   = FLXCRM_Contacts::upsert(
			array(
				'name'    => '' !== $name ? $name : $user->display_name,
				'email'   => $user->user_email,
				'phone'   => $phone,
				'user_id' => (int) $user->ID,
				'city'    => (string) get_user_meta( $user->ID, 'billing_city', true ),
				'roles'   => '' !== $role ? array( $role ) : array(),
				'source'  => self::KEY,
				'link'    => array( self::KEY, 'user:' . (int) $user->ID ),
				'at'      => $user->user_registered,
			)
		);
		if ( $cid && $event ) {
			FLXCRM_Events::add(
				$cid,
				'signup',
				array(
					'source' => self::KEY,
					'uniq'   => 'users:signup:' . (int) $user->ID,
					'title'  => __( 'Created an account on the site', 'flx-crm' ),
					'url'    => 'user-edit.php?user_id=' . (int) $user->ID,
					'at'     => $user->user_registered,
				)
			);
		}
		return $cid;
	}

	public static function on_register( $user_id ) {
		try {
			self::sync( (int) $user_id );
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm users: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function on_update( $user_id ) {
		if ( FLXCRM_Contacts::by_link( self::KEY, 'user:' . (int) $user_id ) ) {
			self::sync( (int) $user_id, false );
		}
	}

	public static function backfill( $page, $per ) {
		$q = new WP_User_Query(
			array(
				'role__in'    => self::wp_roles(),
				'number'      => $per,
				'paged'       => $page,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'count_total' => true,
			)
		);
		foreach ( $q->get_results() as $u ) {
			self::sync( $u );
		}
		$total = (int) $q->get_total();
		return array( 'done' => count( $q->get_results() ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
