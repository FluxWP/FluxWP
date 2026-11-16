<?php
/**
 * System checks FluxWP Panel adds to the shared Flux system check (PHP version, Flux GraphQL, update server,
 * other update sources, packages waiting for an update). The same rows feed the Flux Admin system check page
 * (flx_system_checks filter) and GraphQL.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_System_Service {

	/**
	 * FluxWP Panel's own rows.
	 *
	 * @return list<array{id:string,source:string,group:string,label:string,status:string,message:string,actionUrl:string,actionLabel:string}>
	 */
	public static function checks(): array {
		$rows = array();
		if ( function_exists( 'flxk_graphql_checks' ) ) {
			$rows = array_merge( $rows, FLXK_Val::rows( flxk_graphql_checks( array() ) ) );
		}
		if ( FLXK_Packages_Service::ready() ) {
			$rows = array_merge( $rows, FLXK_Val::rows( FLXU_Updater::instance()->system_checks( array() ) ) );
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'id'          => FLXK_Val::str( $r['id'] ?? '' ),
				'source'      => FLXK_Val::str( $r['source'] ?? 'FluxWP Panel' ),
				'group'       => FLXK_Val::str( $r['group'] ?? '' ),
				'label'       => FLXK_Val::str( $r['label'] ?? '' ),
				'status'      => FLXK_Val::str( $r['status'] ?? 'info' ),
				'message'     => FLXK_Val::str( $r['message'] ?? '' ),
				'actionUrl'   => FLXK_Val::str( $r['action_url'] ?? '' ),
				'actionLabel' => FLXK_Val::str( $r['action_label'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * State of Flux GraphQL on this site and what the admin can do about it.
	 *
	 * @return array{state:string,version:string,phpVersion:string,phpMin:string,phpOk:bool,actionUrl:string,actionLabel:string}
	 */
	public static function graphql(): array {
		$st = flxk_graphql_state();
		list( $url, $label ) = flxk_graphql_action( $st );
		return array(
			'state'       => $st['state'],
			'version'     => $st['version'],
			'phpVersion'  => PHP_VERSION,
			'phpMin'      => FLXK_GRAPHQL_PHP_MIN,
			'phpOk'       => version_compare( PHP_VERSION, FLXK_GRAPHQL_PHP_MIN, '>=' ),
			'actionUrl'   => $url,
			'actionLabel' => $label,
		);
	}
}
