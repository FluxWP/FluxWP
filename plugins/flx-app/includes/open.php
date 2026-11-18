<?php
/**
 * "Open in app": a button any visitor can press on the site. It opens fluxwp://connect?site=<url>, which adds this
 * site to the FluxWP app (iOS, Android, macOS, Windows). The link carries only the site address, no secret; the
 * app then runs the normal OAuth + PKCE sign-in. When the app is not installed, the page offers the download.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_OPEN_DEFAULT_APPLE = 'https://apps.apple.com/tr/app/fluxwp-wordpress-manager/id6831326518';
const FLXAPP_OPEN_UPDATER       = 'https://updater.fluxwp.com/v1/fluxapp/flux/latest';

/** Page that tries the app first, then shows the download for the visitor's system. */
function flxapp_open_url() {
	return add_query_arg( 'action', 'flxapp_open', site_url( 'wp-login.php', 'login' ) );
}

/** fluxwp://connect?site=… (filter flxapp_open_scheme for a branded app). */
function flxapp_open_deeplink() {
	$scheme = (string) apply_filters( 'flxapp_open_scheme', 'fluxwp' );
	$site   = untrailingslashit( home_url() );
	return $scheme . '://connect?site=' . rawurlencode( $site );
}

/**
 * Download links by system. Store links from Flux App settings win; the main FluxWP app fills the rest.
 * Windows and the macOS disk image come from the update server (cached), so they follow new releases.
 *
 * @return array{ios:string,android:string,mac:string,macdmg:string,windows:string}
 */
function flxapp_open_downloads() {
	$apple = (string) flxapp_opt( 'store_ios' );
	$d     = array(
		'ios'     => $apple ? $apple : FLXAPP_OPEN_DEFAULT_APPLE,
		'android' => (string) flxapp_opt( 'store_android' ),
		'mac'     => $apple ? $apple : FLXAPP_OPEN_DEFAULT_APPLE,
		'macdmg'  => '',
		'windows' => '',
	);
	$latest = get_transient( 'flxapp_open_latest' );
	if ( ! is_array( $latest ) ) {
		$latest = array();
		$res    = wp_remote_get( FLXAPP_OPEN_UPDATER, array( 'timeout' => 4 ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$j      = json_decode( wp_remote_retrieve_body( $res ), true );
			$assets = is_array( $j ) && is_array( $j['assets'] ?? null ) ? $j['assets'] : array();
			foreach ( array( 'macdmg' => 'dmg', 'windows' => 'exe' ) as $key => $asset ) {
				$u = (string) ( $assets[ $asset ]['url'] ?? '' );
				if ( 'windows' === $key && '' === $u ) {
					$u = (string) ( $assets['msi']['url'] ?? '' );
				}
				if ( 0 === strpos( $u, 'https://' ) ) {
					$latest[ $key ] = $u;
				}
			}
		}
		set_transient( 'flxapp_open_latest', $latest, $latest ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	}
	$d = array_merge( $d, $latest );
	return apply_filters( 'flxapp_open_downloads', $d );
}

/** [flxapp_open label="Open in app"]: a button for pages, posts and widgets. */
add_shortcode(
	'flxapp_open',
	function ( $atts ) {
		$a = shortcode_atts( array( 'label' => __( 'Open in app', 'flx-app' ) ), $atts, 'flxapp_open' );
		return '<a class="flxapp-open wp-element-button" href="' . esc_url( flxapp_open_url() ) . '">' . esc_html( $a['label'] ) . '</a>';
	}
);

add_action(
	'login_form_flxapp_open',
	function () {
		flxapp_nocache_headers();
		$dl    = flxapp_open_downloads();
		$brand = get_bloginfo( 'name' );
		add_filter( 'login_display_language_dropdown', '__return_false' );
		login_header( __( 'Open in app', 'flx-app' ) );
		?>
		<div class="flxapp-consent" id="flxapp-open" data-link="<?php echo esc_attr( flxapp_open_deeplink() ); ?>">
			<div class="flxapp-brand">
				<img src="<?php echo esc_url( flxapp_brand_logo() ); ?>" alt="" width="56" height="56">
				<span><?php echo esc_html( $brand ); ?></span>
			</div>
			<h1 class="flxapp-title"><?php esc_html_e( 'Open in the FluxWP app', 'flx-app' ); ?></h1>
			<p id="flxapp-open-wait"><?php esc_html_e( 'Opening the app…', 'flx-app' ); ?></p>
			<div id="flxapp-open-more" hidden>
				<p><?php esc_html_e( 'The app did not open. Install it, then come back and press the button again.', 'flx-app' ); ?></p>
				<p class="flxapp-actions">
					<?php if ( $dl['ios'] ) : ?>
						<a class="button button-primary" data-os="ios" href="<?php echo esc_url( $dl['ios'] ); ?>"><?php esc_html_e( 'Download on the App Store', 'flx-app' ); ?></a>
					<?php endif; ?>
					<?php if ( $dl['android'] ) : ?>
						<a class="button button-primary" data-os="android" href="<?php echo esc_url( $dl['android'] ); ?>"><?php esc_html_e( 'Get it on Google Play', 'flx-app' ); ?></a>
					<?php endif; ?>
					<?php if ( $dl['macdmg'] || $dl['mac'] ) : ?>
						<a class="button button-primary" data-os="mac" href="<?php echo esc_url( $dl['macdmg'] ? $dl['macdmg'] : $dl['mac'] ); ?>"><?php esc_html_e( 'Download for Mac', 'flx-app' ); ?></a>
					<?php endif; ?>
					<?php if ( $dl['windows'] ) : ?>
						<a class="button button-primary" data-os="windows" href="<?php echo esc_url( $dl['windows'] ); ?>"><?php esc_html_e( 'Download for Windows', 'flx-app' ); ?></a>
					<?php endif; ?>
				</p>
				<p class="flxapp-actions"><a class="button" id="flxapp-open-again" href="<?php echo esc_url( flxapp_open_deeplink() ); ?>"><?php esc_html_e( 'Open the app', 'flx-app' ); ?></a></p>
			</div>
		</div>
		<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '← Back to site', 'flx-app' ); ?></a></p>
		<script>
		(function () {
			var box = document.getElementById('flxapp-open');
			var ua = navigator.userAgent || '';
			var os = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1) ? 'ios'
				: /Android/.test(ua) ? 'android' : /Mac/.test(ua) ? 'mac' : /Windows/.test(ua) ? 'windows' : '';
			var left = false;
			var gone = function () { left = true; };
			document.addEventListener('visibilitychange', function () { if (document.hidden) gone(); });
			window.addEventListener('pagehide', gone);
			window.addEventListener('blur', gone);
			// This system's download first; the others stay below it.
			var row = box.querySelector('#flxapp-open-more .flxapp-actions');
			var mine = row && row.querySelector('[data-os="' + os + '"]');
			if (mine) row.insertBefore(mine, row.firstChild);
			if (os && row) { row.querySelectorAll('a[data-os]').forEach(function (a) { if (a.getAttribute('data-os') !== os) a.className = 'button'; }); }
			window.location.href = box.getAttribute('data-link');
			setTimeout(function () {
				if (left) return;
				document.getElementById('flxapp-open-wait').hidden = true;
				document.getElementById('flxapp-open-more').hidden = false;
			}, 1800);
		})();
		</script>
		<?php
		login_footer();
		exit;
	}
);

add_action(
	'login_enqueue_scripts',
	function () {
		if ( in_array( $_REQUEST['action'] ?? '', array( 'flxapp_open' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_enqueue_style( 'flxapp-consent', FLXAPP_URL . 'assets/consent.css', array( 'login' ), FLXAPP_VERSION );
		}
	}
);
