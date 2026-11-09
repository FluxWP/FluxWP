<?php
/**
 * Optional mobile usage: two independent choices (device + site's FluxWP Panel).
 * Only successful authenticated app requests count. No request body, route, IP,
 * account ID, email, content or device identifier is included in telemetry.
 * Daily keyed account digests stay on this site until the scheduled two-day cleanup; aggregate
 * counts stay 30 days. The hub receives only completed-day cohorts of >=5.
 */
defined( 'ABSPATH' ) || exit;

const FLXAPP_USAGE_PREFIX = 'flxapp_usage_day_';
const FLXAPP_USAGE_SEEN   = 'flxapp_usage_seen_';
const FLXAPP_USAGE_HOOK   = 'flxapp_usage_cleanup';

function flxapp_usage_allowed() {
	return '1' === (string) get_option( 'flxapp_usage_share', '' ) && function_exists( 'flxk_telemetry_enabled' ) && flxk_telemetry_enabled();
}

function flxapp_usage_observe( $response, $server, $request ) {
	if ( ! flxapp_usage_allowed() || ! is_user_logged_in() || '1' !== $request->get_header( 'x-fluxwp-usage' ) || 0 !== strpos( $request->get_route(), '/flxapp/v1/' ) ) {
		return $response;
	}
	$p = $request->get_header( 'x-fluxwp-platform' );
	if ( ! in_array( $p, array( 'ios', 'android' ), true ) || is_wp_error( $response ) || ! is_object( $response ) || ! method_exists( $response, 'get_status' ) || $response->get_status() < 200 || $response->get_status() >= 400 ) {
		return $response;
	}
	flxapp_usage_count( get_current_user_id(), $p );
	return $response;
}

/** One account/platform/day. add_option's unique index prevents duplicate concurrent counts. */
function flxapp_usage_count( $user_id, $platform ) {
	if ( ! flxapp_usage_allowed() || (int) $user_id < 1 || ! in_array( $platform, array( 'ios', 'android' ), true ) ) {
		return;
	}
	global $wpdb;
	$day    = gmdate( 'Ymd' );
	$digest = hash_hmac( 'sha256', $day . '|' . (int) $user_id . '|' . $platform, wp_salt( 'auth' ) );
	$seen   = FLXAPP_USAGE_SEEN . $digest;
	if ( ! add_option( $seen, time() + DAY_IN_SECONDS, '', false ) ) {
		return;
	}
	$name = FLXAPP_USAGE_PREFIX . $day . '_' . $platform;
	add_option( $name, 0, '', false );
	$ok = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LEAST(CAST(option_value AS UNSIGNED) + 1, 1000000) WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB
	wp_cache_delete( $name, 'options' );
	if ( false === $ok ) {
		delete_option( $seen ); // A failed write must not permanently consume this account's day.
	}
	if ( ! wp_next_scheduled( FLXAPP_USAGE_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', FLXAPP_USAGE_HOOK );
	}
}

function flxapp_usage_cleanup() {
	global $wpdb;
	// Use delete_option so persistent option caches cannot retain pruned values.
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE (option_name LIKE %s AND CAST(option_value AS UNSIGNED) <= %d) OR (option_name LIKE %s AND option_name < %s) LIMIT 5000", $wpdb->esc_like( FLXAPP_USAGE_SEEN ) . '%', time(), $wpdb->esc_like( FLXAPP_USAGE_PREFIX ) . '%', FLXAPP_USAGE_PREFIX . gmdate( 'Ymd', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
	foreach ( $names as $name ) {
		delete_option( $name );
	}
	if ( count( $names ) >= 5000 && ! wp_next_scheduled( 'flxapp_usage_cleanup_batch' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'flxapp_usage_cleanup_batch' );
	}
}

/** Completed UTC days only. Low counts are withheld, never rounded up or presented as zero. */
function flxapp_usage_report() {
	if ( ! flxapp_usage_allowed() ) {
		return array();
	}
	$out = array();
	for ( $i = 1; $i <= 7; ++$i ) {
		$time = time() - $i * DAY_IN_SECONDS;
		$day  = gmdate( 'Y-m-d', $time );
		foreach ( array( 'ios', 'android' ) as $p ) {
			$n = (int) get_option( FLXAPP_USAGE_PREFIX . gmdate( 'Ymd', $time ) . '_' . $p, 0 );
			if ( $n >= 5 ) {
				$out[ $day ][ $p ] = min( 1000000, $n );
			}
		}
	}
	return $out;
}

function flxapp_usage_payload( $payload ) {
	if ( flxapp_usage_allowed() ) {
		$payload['app_usage'] = flxapp_usage_report();
	}
	return $payload;
}

add_filter( 'rest_post_dispatch', 'flxapp_usage_observe', 20, 3 );
add_filter( 'flxk_telemetry_payload', 'flxapp_usage_payload' );
add_action( FLXAPP_USAGE_HOOK, 'flxapp_usage_cleanup' );
add_action( 'flxapp_usage_cleanup_batch', 'flxapp_usage_cleanup' );

/** New scope requires a separate administrator choice, even if general telemetry was already on. */
function flxapp_usage_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="card" style="max-width:none;margin-top:20px">
		<h2><?php esc_html_e( 'Aggregate mobile app usage', 'flx-app' ); ?></h2>
		<p><?php esc_html_e( 'Optional and off by default. Only accounts that also opt in inside the app are counted. Completed UTC-day iOS/Android totals go to your telemetry hub only for cohorts of at least five accounts. No names, account IDs, content, site URLs or GPS location are included. Country comes from site settings. General usage sharing must also be on. Sign-in and app features do not depend on this choice.', 'flx-app' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="flxapp_usage_consent">
			<?php wp_nonce_field( 'flxapp_usage_consent' ); ?>
			<label><input type="checkbox" name="share" value="1" <?php checked( '1', get_option( 'flxapp_usage_share', '' ) ); ?>> <?php esc_html_e( 'Share aggregate mobile app usage', 'flx-app' ); ?></label>
			<p><button class="button button-primary"><?php esc_html_e( 'Save', 'flx-app' ); ?></button></p>
		</form>
	</div>
	<?php
}

function flxapp_usage_save_consent() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetkiniz yok.', 'flx-app' ) );
	}
	check_admin_referer( 'flxapp_usage_consent' );
	update_option( 'flxapp_usage_share', ! empty( $_POST['share'] ) ? '1' : '0', false );
	wp_safe_redirect( admin_url( 'admin.php?page=flx-setup-settings' ) );
	exit;
}

add_action( 'flxu_settings_after', 'flxapp_usage_settings' );
add_action( 'admin_post_flxapp_usage_consent', 'flxapp_usage_save_consent' );
