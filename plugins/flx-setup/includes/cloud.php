<?php
/**
 * FluxWP account: "Sign in with FluxWP" connects this site to a fluxwp.com account (Flux Cloud).
 *
 * OAuth 2.1 authorization code + PKCE. The site gets its update key once (saved as the main FluxWP Panel key),
 * a 15-minute Ed25519 JWT access token and a 60-day refresh token that rotates on every use.
 * Contract: wp-extras plugins/flx-cloud/docs/API.md.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Cloud {

	const OPTION      = 'flxk_cloud';
	const DEFAULT_URL = 'https://fluxwp.com';
	const SUMMARY_TTL = 6 * HOUR_IN_SECONDS;
	const CONTEXT     = 'flxk_cloud';
	const CARD_OPTION = 'flxk_cloud_card_days';
	const CARD_META   = 'flxk_cloud_card_hidden';
	const CARD_DAYS   = array( 0, 1, 3, 7, 14, 30 );

	public static function init(): void {
		add_action( 'admin_post_flxk_cloud_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_flxk_cloud_back', array( __CLASS__, 'back' ) );
		add_action( 'admin_post_flxk_cloud_refresh', array( __CLASS__, 'refresh_action' ) );
		add_action( 'admin_post_flxk_cloud_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'flxu_settings_before', array( __CLASS__, 'card' ) );
		add_filter( 'flx_system_checks', array( __CLASS__, 'system_checks' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'proof_first' ), 0, 3 );
	}

	/**
	 * Answers the ownership proof before any other plugin can refuse it. Sites that hide or lock down the REST API
	 * (private/"invisible" sites, security plugins) answered 404 here, and fluxwp.com then reported "FluxWP Panel is
	 * too old to connect" although Flux Setup was current. The answer reveals nothing without a valid pending state.
	 *
	 * @param mixed           $result  Short-circuit value.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function proof_first( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( 'GET' !== $request->get_method() || '/flx-setup/v1/cloud/proof' !== untrailingslashit( strtolower( $request->get_route() ) ) ) {
			return $result;
		}
		return self::rest_proof( $request );
	}

	/**
	 * Ownership proof: fluxwp.com asks this site, before it approves a connect request, whether the request
	 * (state) really started here and with which PKCE challenge. Someone who starts a connect with this site's
	 * address from elsewhere cannot answer it, so the site cannot be claimed by another account.
	 * Public: the challenge is already in the browser URL; an unknown state answers 404.
	 */
	public static function rest(): void {
		register_rest_route(
			'flx-setup/v1',
			'/cloud/proof',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'state' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => array( __CLASS__, 'rest_proof' ),
			)
		);
	}

	public static function rest_proof( WP_REST_Request $req ): WP_REST_Response {
		$state   = (string) $req->get_param( 'state' );
		$pending = preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $state ) ? get_transient( 'flxk_cloud_' . $state ) : false;
		$res     = is_array( $pending ) && ! empty( $pending['c'] )
			? new WP_REST_Response(
				array(
					'state'     => $state,
					'challenge' => (string) $pending['c'],
				),
				200
			)
			: new WP_REST_Response( array( 'error' => 'unknown_state' ), 404 );
		$res->header( 'Cache-Control', 'no-store, private' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		do_action( 'litespeed_control_set_nocache', 'flx-setup cloud proof' );
		return $res;
	}

	/** Account server. https only (http allowed for localhost while developing). */
	public static function url(): string {
		$u = defined( 'FLX_CLOUD_URL' ) && FLX_CLOUD_URL ? (string) FLX_CLOUD_URL : self::DEFAULT_URL;
		$u = untrailingslashit( (string) apply_filters( 'flxk_cloud_url', $u ) );
		$host   = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) );
		$scheme = (string) wp_parse_url( $u, PHP_URL_SCHEME );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) ) {
			$u = self::DEFAULT_URL;
		}
		return $u;
	}

	public static function state(): array {
		$s = get_site_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'refresh'   => '',
				'access'    => '',
				'access_exp' => 0,
				'summary'   => array(),
				'checked'   => 0,
				'connected' => 0,
				'key_hash'  => '',
				'error'     => '',
			)
		);
	}

	private static function save_state( array $s ): void {
		update_site_option( self::OPTION, $s );
	}

	public static function connected(): bool {
		return '' !== self::state()['refresh'];
	}

	private static function b64u( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function seal( string $v ): string {
		return function_exists( 'flx_secret_seal' ) ? flx_secret_seal( $v, self::CONTEXT ) : $v;
	}

	private static function open( string $v ): string {
		return function_exists( 'flx_secret_open' ) ? flx_secret_open( $v, self::CONTEXT ) : $v;
	}

	private static function settings_url( array $args = array() ): string {
		return FLXU_Admin::url( $args, FLXU_Admin::SETTINGS_SLUG );
	}

	private static function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-setup' ), 403 );
		}
	}

	/** Shows an error once on the settings page. */
	private static function fail( string $msg ): void {
		set_transient( 'flxk_cloud_msg_' . get_current_user_id(), $msg, 120 );
		wp_safe_redirect( self::settings_url( array( 'flxk_cloud' => 'fail' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Connect                                                             */
	/* ------------------------------------------------------------------ */

	/** Starts the flow: PKCE verifier stays on this server, the browser goes to fluxwp.com. */
	public static function connect(): void {
		self::guard();
		check_admin_referer( 'flxk_cloud_connect' );
		$verifier = self::b64u( random_bytes( 48 ) );
		$state    = self::b64u( random_bytes( 24 ) );
		$chal     = self::b64u( hash( 'sha256', $verifier, true ) );
		set_transient(
			'flxk_cloud_' . $state,
			array(
				'v' => self::seal( $verifier ),
				'c' => $chal,
				'u' => get_current_user_id(),
			),
			30 * MINUTE_IN_SECONDS // Room for signing up on fluxwp.com (the password link comes by e-mail).
		);
		$args = array(
			'flxcl'     => 'connect',
			'site'      => home_url(),
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'return'    => admin_url( 'admin-post.php?action=flxk_cloud_back' ),
			'state'     => $state,
			'challenge' => $chal,
			'wp'        => get_bloginfo( 'version' ),
			'v'         => FLXK_VERSION,
			'lang'      => determine_locale(), // The fluxwp.com consent page opens in the site's language.
		);
		wp_redirect( add_query_arg( array_map( 'rawurlencode', $args ), self::url() . '/' ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- Account server.
		exit;
	}

	/** The browser comes back with a one-time code; exchange it from this server. */
	public static function back(): void {
		self::guard();
		// phpcs:disable WordPress.Security.NonceVerification -- state + PKCE replace the nonce here.
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		$code  = sanitize_text_field( wp_unslash( $_GET['flxcl_code'] ?? '' ) );
		$err   = sanitize_key( wp_unslash( $_GET['flxcl_error'] ?? '' ) );
		// phpcs:enable
		$pending = preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $state ) ? get_transient( 'flxk_cloud_' . $state ) : false;
		if ( ! is_array( $pending ) || (int) $pending['u'] !== get_current_user_id() ) {
			self::fail( __( 'The sign-in link expired. Start again with "Sign in with FluxWP".', 'flx-setup' ) );
		}
		delete_transient( 'flxk_cloud_' . $state );
		if ( '' !== $err ) {
			wp_safe_redirect( self::settings_url( array( 'flxk_cloud' => 'denied' ) ) );
			exit;
		}
		$r = self::post(
			'/token',
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'code_verifier' => self::open( (string) $pending['v'] ),
			)
		);
		if ( is_wp_error( $r ) ) {
			self::fail( $r->get_error_message() );
		}
		$key = (string) ( $r['update_key'] ?? '' );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{20,128}$/', $key ) || empty( $r['refresh_token'] ) ) {
			self::fail( __( 'FluxWP did not return a key. Try again.', 'flx-setup' ) );
		}
		if ( ! FLXU_Updater::key_from_config() ) {
			$set        = FLXU_Updater::settings();
			$set['key'] = $key;
			update_site_option( FLXU_Updater::SETTINGS_OPTION, $set );
		}
		$s              = self::state();
		$s['key_hash']  = hash( 'sha256', $key );
		$s['connected'] = time();
		self::store_tokens( $s, $r );
		FLXU_Updater::instance()->check_now();
		wp_safe_redirect( self::settings_url( array( 'flxk_cloud' => 'ok' ) ) );
		exit;
	}

	private static function store_tokens( array $s, array $r ): void {
		$s['refresh']    = self::seal( (string) $r['refresh_token'] );
		$s['access']     = self::seal( (string) ( $r['access_token'] ?? '' ) );
		$s['access_exp'] = time() + max( 60, (int) ( $r['expires_in'] ?? 900 ) ) - 30;
		if ( ! empty( $r['site'] ) && is_array( $r['site'] ) ) {
			$s['summary'] = $r['site'];
			$s['checked'] = time();
		}
		$s['error'] = '';
		self::save_state( $s );
	}

	/* ------------------------------------------------------------------ */
	/* HTTP                                                                */
	/* ------------------------------------------------------------------ */

	/** @return array|WP_Error */
	private static function decode( $res ) {
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error message */
			return new WP_Error( 'flxk_cloud_http', sprintf( __( 'FluxWP could not be reached: %s', 'flx-setup' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
			return $body;
		}
		$msg = is_array( $body ) && ! empty( $body['error_description'] ) ? (string) $body['error_description'] : (
			/* translators: %d: HTTP status */
			sprintf( __( 'FluxWP answered with HTTP %d.', 'flx-setup' ), $code )
		);
		return new WP_Error( is_array( $body ) && ! empty( $body['error'] ) ? (string) $body['error'] : 'flxk_cloud_http', $msg, array( 'status' => $code ) );
	}

	/** @return array|WP_Error */
	private static function post( string $path, array $body, string $bearer = '' ) {
		$args = array(
			'timeout' => 15,
			'body'    => $body,
			'headers' => array( 'Accept' => 'application/json' ),
		);
		if ( '' !== $bearer ) {
			$args['headers']['Authorization'] = 'Bearer ' . $bearer;
		}
		return self::decode( wp_remote_post( self::url() . '/wp-json/flx-cloud/v1' . $path, $args ) );
	}

	/**
	 * A valid access token: the cached one, or a new pair from the refresh token. One rotation at a time:
	 * a refresh token used twice ends the session on the server.
	 *
	 * @return string|WP_Error
	 */
	public static function access_token( bool $force = false ) {
		$s = self::state();
		if ( '' === $s['refresh'] ) {
			return new WP_Error( 'flxk_cloud_off', __( 'This site is not connected to a FluxWP account.', 'flx-setup' ) );
		}
		if ( ! $force && $s['access'] && (int) $s['access_exp'] > time() ) {
			return self::open( $s['access'] );
		}
		if ( ! add_option( 'flxk_cloud_lock', time(), '', 'no' ) ) {
			if ( time() - (int) get_option( 'flxk_cloud_lock' ) < 30 ) {
				return new WP_Error( 'flxk_cloud_busy', __( 'Another request is renewing the session. Try again in a moment.', 'flx-setup' ) );
			}
			update_option( 'flxk_cloud_lock', time(), false );
		}
		$r = self::post(
			'/token',
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => self::open( $s['refresh'] ),
				'wp'            => get_bloginfo( 'version' ),
				'v'             => FLXK_VERSION,
			)
		);
		if ( is_wp_error( $r ) ) {
			if ( 'invalid_grant' === $r->get_error_code() ) {
				// Session ended on fluxwp.com (disconnected, revoked or expired). The update key stays until replaced.
				$s['refresh'] = '';
				$s['access']  = '';
				$s['error']   = $r->get_error_message();
				self::save_state( $s );
			}
			delete_option( 'flxk_cloud_lock' );
			return $r;
		}
		self::store_tokens( self::state(), $r );
		delete_option( 'flxk_cloud_lock' );
		return (string) $r['access_token'];
	}

	/** Plan summary from fluxwp.com (cached SUMMARY_TTL). */
	public static function summary( bool $force = false ): array {
		$s = self::state();
		if ( '' === $s['refresh'] || ( ! $force && time() - (int) $s['checked'] < self::SUMMARY_TTL ) ) {
			return (array) $s['summary'];
		}
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return (array) self::state()['summary'];
		}
		$url = add_query_arg(
			array(
				'wp' => rawurlencode( get_bloginfo( 'version' ) ),
				'v'  => rawurlencode( FLXK_VERSION ),
			),
			self::url() . '/wp-json/flx-cloud/v1/site'
		);
		$get = static function ( $t ) use ( $url ) {
			return self::decode(
				wp_remote_get(
					$url,
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept'        => 'application/json',
							'Authorization' => 'Bearer ' . $t,
						),
					)
				)
			);
		};
		$r = $get( $token );
		if ( is_wp_error( $r ) && 'invalid_token' === $r->get_error_code() ) {
			$token = self::access_token( true );
			$r     = is_wp_error( $token ) ? $token : $get( $token );
		}
		$s = self::state();
		if ( is_wp_error( $r ) ) {
			$s['error']   = $r->get_error_message();
			$s['checked'] = time() - self::SUMMARY_TTL + 15 * MINUTE_IN_SECONDS; // Retry in 15 minutes.
		} else {
			$s['summary'] = $r;
			$s['checked'] = time();
			$s['error']   = '';
		}
		self::save_state( $s );
		return (array) $s['summary'];
	}

	public static function refresh_action(): void {
		self::guard();
		check_admin_referer( 'flxk_cloud_refresh' );
		self::summary( true );
		$err = self::state()['error'];
		if ( '' !== $err ) {
			self::fail( $err );
		}
		FLXU_Updater::instance()->check_now();
		wp_safe_redirect( self::settings_url( array( 'flxk_cloud' => 'refreshed' ) ) );
		exit;
	}

	/** Disconnects on fluxwp.com too (the key is deleted there) and removes the key it gave. */
	public static function disconnect(): void {
		self::guard();
		check_admin_referer( 'flxk_cloud_disconnect' );
		$s = self::state();
		if ( '' !== $s['refresh'] ) {
			$token = $s['access'] && (int) $s['access_exp'] > time() ? self::open( $s['access'] ) : '';
			self::post( '/site/disconnect', array( 'refresh_token' => self::open( $s['refresh'] ) ), $token );
		}
		if ( $s['key_hash'] && ! FLXU_Updater::key_from_config() && hash_equals( $s['key_hash'], hash( 'sha256', FLXU_Updater::key() ) ) ) {
			$set        = FLXU_Updater::settings();
			$set['key'] = '';
			update_site_option( FLXU_Updater::SETTINGS_OPTION, $set );
		}
		delete_site_option( self::OPTION );
		FLXU_Updater::instance()->check_now();
		wp_safe_redirect( self::settings_url( array( 'flxk_cloud' => 'off' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Screen                                                              */
	/* ------------------------------------------------------------------ */

	/** `{tr, en}` name by the admin language. */
	private static function text( $v ): string {
		if ( ! is_array( $v ) ) {
			return (string) $v;
		}
		$tr = 0 === strpos( determine_locale(), 'tr' );
		return (string) ( $tr ? ( $v['tr'] ?? $v['en'] ?? '' ) : ( $v['en'] ?? $v['tr'] ?? '' ) );
	}

	private static function button( string $action, string $label, string $class = 'button' ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/** Settings page card (FluxWP Panel › Settings, top). */
	public static function card(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = isset( $_GET['flxk_cloud'] ) ? sanitize_key( wp_unslash( $_GET['flxk_cloud'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'fail' === $msg ) {
			$text = (string) get_transient( 'flxk_cloud_msg_' . get_current_user_id() );
			delete_transient( 'flxk_cloud_msg_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $text ? $text : __( 'Connecting failed. Try again.', 'flx-setup' ) ) . '</p></div>';
		} elseif ( 'ok' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Connected to your FluxWP account. Your plan\'s packages are on the Packages screen.', 'flx-setup' ) . '</p></div>';
		} elseif ( 'denied' === $msg ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Connecting was cancelled on FluxWP.', 'flx-setup' ) . '</p></div>';
		} elseif ( 'off' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Disconnected. Installed plugins keep working; updates of paid packages stop.', 'flx-setup' ) . '</p></div>';
		} elseif ( 'refreshed' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Plan information refreshed.', 'flx-setup' ) . '</p></div>';
		}
		$s   = self::state();
		$sum = self::summary();
		$s   = self::state();
		if ( self::card_hidden() && ! $msg ) {
			return;
		}
		?>
		<div class="flxu-card flxk-cloud <?php echo $s['refresh'] ? 'flxu-card--connected' : 'flxu-card--unknown'; ?>">
			<span class="flxk-cloud__logo" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 18h10a4 4 0 0 0 .6-7.96A6 6 0 0 0 6.2 9.1 4.5 4.5 0 0 0 7 18z"/><path d="m9.5 13.5 2 2 3.5-4"/></svg></span>
			<div class="flxu-card__body">
				<?php if ( $s['refresh'] && $sum ) : ?>
					<?php
					$plan    = (array) ( $sum['plan'] ?? array() );
					$sites   = (array) ( $sum['sites'] ?? array() );
					$sets    = array_filter( array_map( static function ( $x ) {
						return is_array( $x ) ? self::text( $x['name'] ?? '' ) : '';
					}, (array) ( $sum['sets'] ?? array() ) ) );
					$expires = (int) ( $plan['expires'] ?? 0 );
					?>
					<strong class="flxu-card__title">
						<?php
						/* translators: 1: plan name, 2: account name */
						echo esc_html( sprintf( __( 'FluxWP %1$s · %2$s', 'flx-setup' ), self::text( $plan['name'] ?? '' ), (string) ( $sum['account']['name'] ?? '' ) ) );
						?>
					</strong>
					<span>
						<?php
						$bits = array();
						if ( 'ended' === ( $plan['status'] ?? '' ) ) {
							$bits[] = __( 'Subscription ended: the Free plan applies, paid updates are paused.', 'flx-setup' );
						} elseif ( $expires ) {
							/* translators: %s: date */
							$bits[] = sprintf( __( 'Renews or ends on %s', 'flx-setup' ), flx_date( $expires ) );
						}
						if ( $sets ) {
							/* translators: %s: set names */
							$bits[] = sprintf( __( 'Sets: %s', 'flx-setup' ), implode( ', ', $sets ) );
						}
						if ( isset( $sites['used'] ) ) {
							$limit = (int) ( $sites['limit'] ?? 0 );
							/* translators: 1: connected sites, 2: site limit */
							$bits[] = $limit < 0 ? sprintf( __( '%d sites connected', 'flx-setup' ), (int) $sites['used'] ) : sprintf( __( '%1$d of %2$d sites', 'flx-setup' ), (int) $sites['used'], $limit );
						}
						echo esc_html( implode( ' · ', $bits ) );
						?>
					</span>
					<?php if ( $s['error'] ) : ?>
						<small><?php echo esc_html( $s['error'] ); ?></small>
					<?php elseif ( $s['checked'] ) : ?>
						<?php /* translators: %s: time ago */ ?>
						<small><?php echo esc_html( sprintf( __( 'Checked %s', 'flx-setup' ), flx_time_ago( (int) $s['checked'] ) ) ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<strong class="flxu-card__title"><?php esc_html_e( 'Connect your FluxWP account', 'flx-setup' ); ?></strong>
					<span><?php esc_html_e( 'Sign in or sign up on fluxwp.com: this site gets its update key and the packages of your plan automatically. The Free plan needs no card.', 'flx-setup' ); ?></span>
					<?php if ( $s['error'] ) : ?>
						<small><?php echo esc_html( $s['error'] ); ?></small>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="flxk-cloud__actions">
				<?php if ( $s['refresh'] ) : ?>
					<?php if ( ! empty( $sum['manage_url'] ) && 0 === strpos( (string) $sum['manage_url'], self::url() ) ) : ?>
						<a class="button" href="<?php echo esc_url( (string) $sum['manage_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage account', 'flx-setup' ); ?></a>
					<?php endif; ?>
					<?php self::button( 'flxk_cloud_refresh', __( 'Refresh', 'flx-setup' ) ); ?>
					<?php self::button( 'flxk_cloud_disconnect', __( 'Disconnect', 'flx-setup' ), 'button-link flxk-cloud__off' ); ?>
				<?php else : ?>
					<?php self::button( 'flxk_cloud_connect', __( 'Sign in with FluxWP', 'flx-setup' ), 'button button-primary' ); ?>
				<?php endif; ?>
			</div>
		</div>
		<style>
			.flxk-cloud__logo{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none;color:#fff;background:linear-gradient(135deg,#38bdf8,#0369a1)}
			.flxk-cloud__actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
			.flxk-cloud__actions form{margin:0}
			.flxk-cloud__off{color:var(--flxa-error, #b32d2e)!important}
			@media (max-width:782px){.flxk-cloud__actions{width:100%}.flxk-cloud__actions .button-primary{width:100%;text-align:center}}
		</style>
		<?php
	}

	/**
	 * The account card as data, for the React settings screen (same text and buttons as card()).
	 * Buttons post to admin-post.php with their own nonce; the flow goes to fluxwp.com and back.
	 *
	 * @return array{connected:bool,title:string,text:string,note:string,error:string,manageUrl:string,postUrl:string,flash:string,flashType:string,actions:array<int,array{action:string,label:string,nonce:string,kind:string}>}
	 */
	public static function view(): array {
		$s   = self::state();
		$sum = $s['refresh'] ? self::summary() : array();
		$s   = self::state();
		$on  = '' !== $s['refresh'];
		$out = array(
			'connected' => $on,
			'title'     => __( 'Connect your FluxWP account', 'flx-setup' ),
			'text'      => __( 'Sign in or sign up on fluxwp.com: this site gets its update key and the packages of your plan automatically. The Free plan needs no card.', 'flx-setup' ),
			'note'      => '',
			'error'     => (string) $s['error'],
			'manageUrl' => '',
			'postUrl'   => admin_url( 'admin-post.php' ),
			'flash'     => '',
			'flashType' => '',
			'actions'   => array(),
			'hidden'    => self::card_hidden(),
			'hideDays'  => self::card_days(),
		);
		if ( $on && $sum ) {
			$plan    = (array) ( $sum['plan'] ?? array() );
			$sites   = (array) ( $sum['sites'] ?? array() );
			$sets    = array_filter(
				array_map(
					static function ( $x ) {
						return is_array( $x ) ? self::text( $x['name'] ?? '' ) : '';
					},
					(array) ( $sum['sets'] ?? array() )
				)
			);
			$expires = (int) ( $plan['expires'] ?? 0 );
			/* translators: 1: plan name, 2: account name */
			$out['title'] = sprintf( __( 'FluxWP %1$s · %2$s', 'flx-setup' ), self::text( $plan['name'] ?? '' ), (string) ( $sum['account']['name'] ?? '' ) );
			$bits         = array();
			if ( 'ended' === ( $plan['status'] ?? '' ) ) {
				$bits[] = __( 'Subscription ended: the Free plan applies, paid updates are paused.', 'flx-setup' );
			} elseif ( $expires ) {
				/* translators: %s: date */
				$bits[] = sprintf( __( 'Renews or ends on %s', 'flx-setup' ), flx_date( $expires ) );
			}
			if ( $sets ) {
				/* translators: %s: set names */
				$bits[] = sprintf( __( 'Sets: %s', 'flx-setup' ), implode( ', ', $sets ) );
			}
			if ( isset( $sites['used'] ) ) {
				$limit = (int) ( $sites['limit'] ?? 0 );
				/* translators: 1: connected sites, 2: site limit */
				$bits[] = $limit < 0 ? sprintf( __( '%d sites connected', 'flx-setup' ), (int) $sites['used'] ) : sprintf( __( '%1$d of %2$d sites', 'flx-setup' ), (int) $sites['used'], $limit );
			}
			$out['text'] = implode( ' · ', $bits );
			if ( ! $s['error'] && $s['checked'] ) {
				/* translators: %s: time ago */
				$out['note'] = sprintf( __( 'Checked %s', 'flx-setup' ), flx_time_ago( (int) $s['checked'] ) );
			}
			if ( ! empty( $sum['manage_url'] ) && 0 === strpos( (string) $sum['manage_url'], self::url() ) ) {
				$out['manageUrl'] = (string) $sum['manage_url'];
			}
		}
		$out['actions'] = $on
			? array(
				array( 'action' => 'flxk_cloud_refresh', 'label' => __( 'Refresh', 'flx-setup' ), 'nonce' => wp_create_nonce( 'flxk_cloud_refresh' ), 'kind' => 'secondary' ),
				array( 'action' => 'flxk_cloud_disconnect', 'label' => __( 'Disconnect', 'flx-setup' ), 'nonce' => wp_create_nonce( 'flxk_cloud_disconnect' ), 'kind' => 'danger' ),
			)
			: array(
				array( 'action' => 'flxk_cloud_connect', 'label' => __( 'Sign in with FluxWP', 'flx-setup' ), 'nonce' => wp_create_nonce( 'flxk_cloud_connect' ), 'kind' => 'primary' ),
			);
		// The message of the last sign-in step (the redirect back adds ?flxk_cloud=…; the failure text waits in a transient).
		$fail = (string) get_transient( 'flxk_cloud_msg_' . get_current_user_id() );
		if ( '' !== $fail ) {
			$out['flash']     = $fail;
			$out['flashType'] = 'error';
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* "Connect your FluxWP account" card: hide with ×, back after N days   */
	/* ------------------------------------------------------------------ */

	/** Days after which a hidden card shows again (site-wide setting); 0 = never. Default 7. */
	public static function card_days(): int {
		$d = (int) get_site_option( self::CARD_OPTION, 7 );
		return in_array( $d, self::CARD_DAYS, true ) ? $d : 7;
	}

	/** The current user hid the connect card and it is not due again. A connected site's card is never hidden. */
	public static function card_hidden( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id || self::connected() ) {
			return false;
		}
		$at = (int) get_user_meta( $user_id, self::CARD_META, true );
		if ( $at <= 0 ) {
			return false;
		}
		$days = self::card_days();
		return 0 === $days || time() - $at < $days * DAY_IN_SECONDS;
	}

	/**
	 * Hides or shows the card for the current user and/or changes the days setting.
	 *
	 * @param bool|null $hide true hides, false shows again, null leaves it.
	 * @param int|null  $days New days setting (one of CARD_DAYS), null leaves it.
	 * @return true|WP_Error
	 */
	public static function card_save( ?bool $hide, ?int $days ) {
		if ( null !== $days ) {
			if ( ! in_array( $days, self::CARD_DAYS, true ) ) {
				return new WP_Error( 'flxk_cloud_days', __( 'Choose one of the listed periods.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'days' ) );
			}
			update_site_option( self::CARD_OPTION, $days );
		}
		if ( true === $hide ) {
			update_user_meta( get_current_user_id(), self::CARD_META, time() );
		} elseif ( false === $hide ) {
			delete_user_meta( get_current_user_id(), self::CARD_META );
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* System checks                                                       */
	/* ------------------------------------------------------------------ */

	public static function system_checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$s      = self::state();
		$plan   = (array) ( $s['summary']['plan'] ?? array() );
		$checks[] = array(
			'id'           => 'flxk.cloud',
			'source'       => 'FluxWP Panel',
			'group'        => 'Config',
			'label'        => __( 'FluxWP account', 'flx-setup' ),
			'status'       => $s['refresh'] ? ( $s['error'] ? 'warning' : 'ok' ) : 'info',
			'message'      => $s['refresh']
				/* translators: %s: plan name */
				? ( $s['error'] ? $s['error'] : sprintf( __( 'Connected, plan %s.', 'flx-setup' ), self::text( $plan['name'] ?? '' ) ) )
				: ( $s['error'] ? $s['error'] : __( 'Not connected. Sign in with FluxWP to get the packages of your plan.', 'flx-setup' ) ),
			'action_url'   => self::settings_url(),
			'action_label' => __( 'Settings', 'flx-setup' ),
		);
		return $checks;
	}
}
