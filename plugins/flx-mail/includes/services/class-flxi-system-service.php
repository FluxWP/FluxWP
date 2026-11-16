<?php
/**
 * System-check rows for this package, and the stored DNS lookup.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux Mail system checks.
 */
class FLXI_System_Service {

	/**
	 * flxi.* rows from the shared system check.
	 *
	 * @return list<array<string,string>>
	 */
	public static function checks(): array {
		if ( function_exists( 'flx_system_checks_sorted' ) ) {
			$all = flx_system_checks_sorted();
		} else {
			$all = apply_filters( 'flx_system_checks', array() );
		}
		$out = array();
		foreach ( (array) $all as $c ) {
			if ( ! is_array( $c ) || 0 !== strpos( (string) ( $c['id'] ?? '' ), 'flxi.' ) ) {
				continue;
			}
			$out[] = array(
				'id'          => (string) $c['id'],
				'group'       => (string) ( $c['group'] ?? '' ),
				'label'       => (string) ( $c['label'] ?? '' ),
				'status'      => (string) ( $c['status'] ?? '' ),
				'message'     => (string) ( $c['message'] ?? '' ),
				'actionUrl'   => (string) ( $c['action_url'] ?? '' ),
				'actionLabel' => (string) ( $c['action_label'] ?? '' ),
			);
		}
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:settings' );
		}
		return $out;
	}

	/**
	 * Stored DNS result, without a new lookup.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function stored_dns(): ?array {
		$dns = get_option( 'flxi_dns_check' );
		if ( ! is_array( $dns ) ) {
			return null;
		}
		return array(
			'domain'    => (string) ( $dns['domain'] ?? '' ),
			'spf'       => (string) ( $dns['spf'] ?? '' ),
			'dmarc'     => (string) ( $dns['dmarc'] ?? '' ),
			'mx'        => array_values( array_map( 'strval', (array) ( $dns['mx'] ?? array() ) ) ),
			'error'     => (string) ( $dns['error'] ?? '' ),
			'checkedAt' => (int) ( $dns['at'] ?? 0 ),
		);
	}

	/**
	 * Runs the lookup and stores it. Same array POST /dns returns, plus the stored shape is separate.
	 *
	 * @return array<string,mixed>
	 */
	public static function check_dns(): array {
		$res = flxi_dns_check();
		update_option( 'flxi_dns_check', $res, false );
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( 'flx-mail:settings' );
		}
		return $res;
	}
}
