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
		foreach ( flxi_rows( $all ) as $c ) {
			if ( 0 !== strpos( flxi_str( $c['id'] ?? '' ), 'flxi.' ) ) {
				continue;
			}
			$out[] = array(
				'id'          => flxi_str( $c['id'] ?? '' ),
				'group'       => flxi_str( $c['group'] ?? '' ),
				'label'       => flxi_str( $c['label'] ?? '' ),
				'status'      => flxi_str( $c['status'] ?? '' ),
				'message'     => flxi_str( $c['message'] ?? '' ),
				'actionUrl'   => flxi_str( $c['action_url'] ?? '' ),
				'actionLabel' => flxi_str( $c['action_label'] ?? '' ),
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
		$mx = array();
		foreach ( (array) ( $dns['mx'] ?? array() ) as $host ) {
			$mx[] = flxi_str( $host );
		}
		return array(
			'domain'    => flxi_str( $dns['domain'] ?? '' ),
			'spf'       => flxi_str( $dns['spf'] ?? '' ),
			'dmarc'     => flxi_str( $dns['dmarc'] ?? '' ),
			'mx'        => $mx,
			'error'     => flxi_str( $dns['error'] ?? '' ),
			'checkedAt' => flxi_int( $dns['at'] ?? 0 ),
		);
	}

	/**
	 * Runs the lookup and stores it. Same array POST /dns returns, plus the stored shape is separate.
	 *
	 * @return array<string,mixed>
	 */
	public static function check_dns(): array {
		$raw = flxi_dns_check();
		/** @var array<string,mixed> $res */
		$res = is_array( $raw ) ? $raw : array();
		update_option( 'flxi_dns_check', $res, false );
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( 'flx-mail:settings' );
		}
		return $res;
	}
}
