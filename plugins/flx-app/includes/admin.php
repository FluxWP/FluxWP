<?php
/**
 * Kullanıcılar › Uygulama erişimi (yönetici olmayanlarda Profil altında): kendi oturumları ve API anahtarları.
 * Sol menüde "Uygulama" (yöneticiler): Genel, Görünüm, Uygulamalar, Modüller, Menü, OAuth istemcileri, Tüm erişimler.
 * Görünüm ve Uygulamalar sekmeleri admin-appearance.php'de.
 */

defined( 'ABSPATH' ) || exit;

/** Yönetici sekmeleri "Uygulama" sayfasında, kişisel erişim Kullanıcılar altında. */
function flxapp_admin_tabs() {
	return array(
		'settings'   => __( 'General', 'flx-app' ),
		'appearance' => __( 'Appearance', 'flx-app' ),
		'mobile'     => __( 'Applications', 'flx-app' ),
		'modules'    => __( 'Modules', 'flx-app' ),
		'menu'       => __( 'Menu', 'flx-app' ),
		'apps'       => __( 'OAuth clients', 'flx-app' ),
		'all'      => __( 'All access', 'flx-app' ),
	);
}

function flxapp_admin_url( $args = array() ) {
	if ( isset( $args['tab'] ) && isset( flxapp_admin_tabs()[ $args['tab'] ] ) ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=flxapp-settings' ) );
	}
	return add_query_arg( $args, admin_url( current_user_can( 'list_users' ) ? 'users.php?page=flx-app' : 'profile.php?page=flx-app' ) );
}

add_action(
	'admin_menu',
	function () {
		$css   = function () {
			wp_enqueue_style( 'flxapp-admin', FLXAPP_URL . 'assets/admin.css', array(), FLXAPP_VERSION );
		};
		$hooks = array(
			add_users_page( __( 'App access', 'flx-app' ), __( 'App access', 'flx-app' ), 'read', 'flx-app', 'flxapp_render_page' ),
			add_menu_page( __( 'Application', 'flx-app' ), __( 'Application', 'flx-app' ), 'manage_options', 'flxapp-settings', 'flxapp_render_settings_page', 'dashicons-smartphone', 72 ),
		);
		foreach ( $hooks as $hook ) {
			add_action( 'admin_print_styles-' . $hook, $css );
		}
	}
);

foreach ( array( 'flxapp_key', 'flxapp_revoke', 'flxapp_client', 'flxapp_client_delete', 'flxapp_settings', 'flxapp_modules', 'flxapp_appearance', 'flxapp_mobile', 'flxapp_device', 'flxapp_push_test' ) as $flxapp_action ) {
	add_action( 'admin_post_' . $flxapp_action, 'flxapp_handle_' . substr( $flxapp_action, 7 ) );
}

function flxapp_deny() {
	wp_die( esc_html__( 'You are not allowed to do this.', 'flx-app' ), 403 );
}

function flxapp_handle_key() {
	check_admin_referer( 'flxapp_key' );
	if ( ! current_user_can( (string) flxapp_opt( 'keys_cap' ) ) ) {
		flxapp_deny();
	}
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$days = absint( $_POST['days'] ?? 0 );
	$days = in_array( $days, array( 0, 30, 90, 365 ), true ) ? $days : 0;
	if ( '' === $name ) {
		wp_safe_redirect( flxapp_admin_url( array( 'flxapp_msg' => 'noname' ) ) );
		exit;
	}
	$key = flxapp_issue(
		'key',
		array(
			'user_id' => get_current_user_id(),
			'name'    => $name,
			'scope'   => 'flx mcp',
			'ttl'     => $days * DAY_IN_SECONDS,
		)
	);
	// Düz anahtar yalnız bir kez, kısa ömürlü kullanıcıya özel geçici kayıtla gösterilir.
	set_transient( 'flxapp_show_' . get_current_user_id(), $key, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( flxapp_admin_url( array( 'flxapp_msg' => 'key' ) ) );
	exit;
}

function flxapp_handle_revoke() {
	global $wpdb;
	check_admin_referer( 'flxapp_revoke' );
	$id  = absint( $_POST['id'] ?? 0 );
	$t   = flxapp_table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
		flxapp_deny();
	}
	if ( '' !== $row['grant_id'] ) {
		flxapp_revoke_grant( $row['grant_id'] );
	} else {
		flxapp_revoke_id( $row['id'] );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : flxapp_admin_url() );
	exit;
}

function flxapp_handle_client() {
	check_admin_referer( 'flxapp_client' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$uris = preg_split( '/[\r\n]+/', (string) wp_unslash( $_POST['redirect_uris'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$res  = flxapp_add_client( (string) wp_unslash( $_POST['name'] ?? '' ), array_map( 'esc_url_raw', (array) $uris ), ! empty( $_POST['confidential'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( is_wp_error( $res ) ) {
		set_transient( 'flxapp_err_' . get_current_user_id(), $res->get_error_message(), MINUTE_IN_SECONDS );
		wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'apps', 'flxapp_msg' => 'err' ) ) );
		exit;
	}
	if ( $res[1] ) {
		set_transient( 'flxapp_show_' . get_current_user_id(), $res[0]['id'] . "\n" . $res[1], 5 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'apps', 'flxapp_msg' => $res[1] ? 'secret' : 'client' ) ) );
	exit;
}

function flxapp_handle_client_delete() {
	check_admin_referer( 'flxapp_client_delete' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	flxapp_delete_client( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'apps' ) ) );
	exit;
}

function flxapp_handle_settings() {
	check_admin_referer( 'flxapp_settings' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$caps = array_keys( flxapp_key_cap_choices() );
	$cap  = sanitize_key( wp_unslash( $_POST['keys_cap'] ?? 'read' ) );
	update_option(
		'flxapp_settings',
		array_merge(
			flxapp_settings(),
			array(
			'access_minutes'  => max( 5, min( 1440, absint( $_POST['access_minutes'] ?? 60 ) ) ),
			'refresh_days'    => max( 1, min( 365, absint( $_POST['refresh_days'] ?? 60 ) ) ),
			'dynamic_clients' => empty( $_POST['dynamic_clients'] ) ? 0 : 1,
			'web_session'     => empty( $_POST['web_session'] ) ? 0 : 1,
			'otp_enabled'       => empty( $_POST['otp_enabled'] ) ? 0 : 1,
			'otp_create'        => empty( $_POST['otp_create'] ) ? 0 : 1,
			'otp_staff'         => empty( $_POST['otp_staff'] ) ? 0 : 1,
			'phone_self_verify' => empty( $_POST['phone_self_verify'] ) ? 0 : 1,
			'keys_cap'        => in_array( $cap, $caps, true ) ? $cap : 'read',
			)
		),
		false
	);
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'settings', 'flxapp_msg' => 'saved' ) ) );
	exit;
}

function flxapp_handle_modules() {
	check_admin_referer( 'flxapp_modules' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$all = array_map( 'sanitize_key', array_keys( flxapp_admin_modules() ) );
	$on  = array_map( 'sanitize_key', (array) wp_unslash( $_POST['on'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$s   = flxapp_settings();
	// Listede olmayan (eklentisi şu an kapalı) modüllerin eski seçimi korunur.
	$s['modules_off'] = array_values( array_unique( array_merge( array_diff( (array) $s['modules_off'], $all ), array_diff( $all, $on ) ) ) );
	update_option( 'flxapp_settings', $s, false );
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'modules', 'flxapp_msg' => 'saved' ) ) );
	exit;
}

function flxapp_key_cap_choices() {
	return array(
		'read'           => __( 'All users', 'flx-app' ),
		'edit_posts'     => __( 'Users who can edit content', 'flx-app' ),
		'manage_options' => __( 'Administrators only', 'flx-app' ),
	);
}

function flxapp_when( $ts ) {
	if ( ! $ts ) {
		return '—';
	}
	return flx_date( (int) $ts, 'datetime' );
}

/** Oturum (grant) ve anahtar satırlarını tablo olarak basar. */
function flxapp_render_tokens( array $rows, $show_user ) {
	if ( ! $rows ) {
		echo '<p class="flxapp-empty">' . esc_html__( 'Nothing here yet.', 'flx-app' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped flxapp-table"><thead><tr>';
	if ( $show_user ) {
		echo '<th>' . esc_html__( 'User', 'flx-app' ) . '</th>';
	}
	echo '<th>' . esc_html__( 'Name', 'flx-app' ) . '</th><th>' . esc_html__( 'Type', 'flx-app' ) . '</th><th>' . esc_html__( 'Created', 'flx-app' ) . '</th><th>' . esc_html__( 'Last used', 'flx-app' ) . '</th><th>' . esc_html__( 'Expires', 'flx-app' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$is_key = 'key' === $r['kind'];
		$last   = $is_key ? (int) $r['last_used'] : flxapp_grant_last_used( $r['grant_id'] );
		echo '<tr>';
		if ( $show_user ) {
			$u = get_userdata( (int) $r['user_id'] );
			echo '<td data-label="' . esc_attr__( 'User', 'flx-app' ) . '">' . esc_html( $u ? $u->display_name : '#' . $r['user_id'] ) . '</td>';
		}
		echo '<td data-label="' . esc_attr__( 'Name', 'flx-app' ) . '"><strong>' . esc_html( $r['name'] ) . '</strong>' . ( $is_key ? ' <code>flxak_…' . esc_html( $r['hint'] ) . '</code>' : '' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Type', 'flx-app' ) . '">' . ( $is_key ? esc_html__( 'API key', 'flx-app' ) : esc_html__( 'App session', 'flx-app' ) ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Created', 'flx-app' ) . '">' . esc_html( flxapp_when( $r['created'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Last used', 'flx-app' ) . '">' . esc_html( flxapp_when( $last ) ) . ( $r['last_ip'] && $is_key ? ' <span class="flxapp-muted">' . esc_html( $r['last_ip'] ) . '</span>' : '' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Expires', 'flx-app' ) . '">' . ( (int) $r['expires'] ? esc_html( flxapp_when( $r['expires'] ) ) : esc_html__( 'Never', 'flx-app' ) ) . '</td>';
		echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this access?', 'flx-app' ) ) . '\')">';
		echo '<input type="hidden" name="action" value="flxapp_revoke"><input type="hidden" name="id" value="' . (int) $r['id'] . '">';
		wp_nonce_field( 'flxapp_revoke' );
		echo '<button class="button button-link-delete">' . esc_html__( 'Remove', 'flx-app' ) . '</button></form></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/** Kayıt sonrası bildirimler ve bir kez gösterilen anahtar. */
function flxapp_render_notices( $uid ) {
	$msg = sanitize_key( wp_unslash( $_GET['flxapp_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$shown = get_transient( 'flxapp_show_' . $uid );
	if ( in_array( $msg, array( 'key', 'secret' ), true ) && $shown ) {
		delete_transient( 'flxapp_show_' . $uid );
		echo '<div class="notice notice-success flxapp-secret"><p><strong>' . esc_html__( 'Copy it now; it will not be shown again.', 'flx-app' ) . '</strong></p>';
		echo '<textarea readonly rows="' . ( 'secret' === $msg ? 2 : 1 ) . '" onclick="this.select()">' . esc_textarea( $shown ) . '</textarea></div>';
	} elseif ( 'saved' === $msg ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'flx-app' ) . '</p></div>';
	} elseif ( 'push' === $msg ) {
		$r = get_transient( 'flxapp_push_test_' . $uid );
		delete_transient( 'flxapp_push_test_' . $uid );
		$r = is_array( $r ) ? $r : array( 'sent' => 0, 'failed' => 0, 'skipped' => 0 );
		/* translators: 1: sent, 2: failed, 3: skipped */
		echo '<div class="notice notice-' . ( $r['sent'] ? 'success' : 'warning' ) . '"><p>' . esc_html( sprintf( __( 'Test notification: %1$d sent, %2$d failed, %3$d skipped.', 'flx-app' ), (int) $r['sent'], (int) $r['failed'], (int) $r['skipped'] ) ) . '</p></div>';
	} elseif ( 'noname' === $msg ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Give the key a name.', 'flx-app' ) . '</p></div>';
	} elseif ( 'err' === $msg ) {
		$e = get_transient( 'flxapp_err_' . $uid );
		echo '<div class="notice notice-error"><p>' . esc_html( $e ? $e : __( 'Could not save.', 'flx-app' ) ) . '</p></div>';
	}

}

/**
 * Masaüstü uygulamasının indirme adresi. Güncelleme sunucusunun kalıcı bağlantısı son sürüme yönlendirir.
 * Sunucu Flux Setup'tan (yoksa updater.fluxwp.com), marka `flxapp_desktop_brand` filtresinden.
 *
 * @param string $os macos | windows | windows-msi
 */
function flxapp_desktop_download_url( $os ) {
	$server = class_exists( 'FLXU_Updater' ) ? FLXU_Updater::server() : 'https://updater.fluxwp.com';
	$brand  = sanitize_key( (string) apply_filters( 'flxapp_desktop_brand', 'flux' ) );
	$url    = untrailingslashit( $server ) . '/v1/fluxapp/' . $brand . '/download/' . $os;
	return (string) apply_filters( 'flxapp_desktop_download_url', $url, $os, $brand );
}

/** "Masaüstü uygulaması" kartı: macOS ve Windows indirme düğmeleri, ziyaretçinin sistemi öne çıkar. */
function flxapp_render_download() {
	$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$win = false !== stripos( $ua, 'Windows' );
	$btn = function ( $os, $label, $primary ) {
		return '<a class="button' . ( $primary ? ' button-primary' : '' ) . '" href="' . esc_url( flxapp_desktop_download_url( $os ) ) . '">' . esc_html( $label ) . '</a>';
	};
	echo '<h2>' . esc_html__( 'Desktop app', 'flx-app' ) . '</h2>';
	echo '<div class="flxapp-card flxapp-download"><p>' . esc_html__( 'Use the panel as a separate app on your Mac or Windows computer. New versions install themselves.', 'flx-app' ) . '</p><p class="flxapp-row">';
	$mac = $btn( 'macos', __( 'Download for macOS', 'flx-app' ), ! $win );
	$exe = $btn( 'windows', __( 'Download for Windows', 'flx-app' ), $win );
	echo $win ? $exe . $mac : $mac . $exe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $btn.
	echo '</p><p class="description">' . esc_html__( 'macOS 11 or later (Apple Silicon and Intel). Windows 10 and 11 (64-bit).', 'flx-app' );
	echo ' <a href="' . esc_url( flxapp_desktop_download_url( 'windows-msi' ) ) . '">' . esc_html__( 'Windows MSI package (managed deployment)', 'flx-app' ) . '</a></p></div>';
}

/** Kullanıcılar › Uygulama erişimi: kendi oturumları ve API anahtarları. */
function flxapp_render_page() {
	$uid = get_current_user_id();
	$s   = flxapp_settings();
	echo '<div class="wrap flxapp"><h1>' . esc_html__( 'App access', 'flx-app' ) . '</h1>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<p><a class="button" href="' . esc_url( flxapp_admin_url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( 'App settings', 'flx-app' ) . '</a></p>';
	}
	flxapp_render_notices( $uid );
	echo '<p class="flxapp-lead">' . esc_html__( 'In the Flux Desktop app, press "Sign in": you sign in to this site in the browser and approve; the app then works with your permissions. For other tools (MCP clients, scripts) you can create a personal API key.', 'flx-app' ) . '</p>';
	flxapp_render_download();
	echo '<h2>' . esc_html__( 'My devices', 'flx-app' ) . '</h2>';
	flxapp_render_devices( flxapp_user_devices( $uid ), false );
	echo '<h2>' . esc_html__( 'Connected apps', 'flx-app' ) . '</h2>';
	flxapp_render_tokens( flxapp_user_tokens( $uid, array( 'refresh' ) ), false );
	echo '<h2>' . esc_html__( 'API keys', 'flx-app' ) . '</h2>';
	flxapp_render_tokens( flxapp_user_tokens( $uid, array( 'key' ) ), false );
	if ( current_user_can( (string) $s['keys_cap'] ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxapp-card flxapp-row">';
		echo '<input type="hidden" name="action" value="flxapp_key">';
		wp_nonce_field( 'flxapp_key' );
		echo '<label>' . esc_html__( 'Name', 'flx-app' ) . ' <input type="text" name="name" required maxlength="100" placeholder="' . esc_attr__( 'e.g. Claude MCP', 'flx-app' ) . '"></label>';
		echo '<label>' . esc_html__( 'Valid for', 'flx-app' ) . ' <select name="days"><option value="0">' . esc_html__( 'Never', 'flx-app' ) . '</option>';
		foreach ( array( 30, 90, 365 ) as $d ) {
			/* translators: %d: days */
			echo '<option value="' . (int) $d . '">' . esc_html( sprintf( __( '%d days', 'flx-app' ), $d ) ) . '</option>';
		}
		echo '</select></label><button class="button button-primary">' . esc_html__( 'Create key', 'flx-app' ) . '</button></form>';
		echo '<p class="description">' . esc_html__( 'Usage: send Authorization: Bearer flxak_… to any REST endpoint and MCP (/wp-json/flx-mcp/v1/mcp). The key acts with your permissions.', 'flx-app' ) . '</p>';
	}
	echo '</div>';
}

/** Sol menü › Uygulama: uygulamayla ilgili tüm ayarlar (yöneticiler). */
function flxapp_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = flxapp_admin_tabs();
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'settings';
	$uid  = get_current_user_id();
	$s    = flxapp_settings();
	echo '<div class="wrap flxapp"><h1 class="flxapp-title"><img src="' . esc_url( FLXAPP_URL . 'assets/icon.svg' ) . '" alt="" width="32" height="32"> ' . esc_html__( 'Application', 'flx-app' ) . ' <small>Flux App ' . esc_html( FLXAPP_VERSION ) . '</small></h1>';
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( flxapp_admin_url( array( 'tab' => $k ) ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	flxapp_render_notices( $uid );

	if ( 'settings' === $tab ) {
		echo '<p class="flxapp-lead">' . esc_html__( 'Sign-in, session and panel settings for the desktop (macOS, Windows) and mobile (iOS, Android) app.', 'flx-app' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxapp_settings">';
		wp_nonce_field( 'flxapp_settings' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="flxapp-am">' . esc_html__( 'Access token lifetime', 'flx-app' ) . '</label></th><td><input id="flxapp-am" type="number" min="5" max="1440" name="access_minutes" value="' . (int) $s['access_minutes'] . '" class="small-text"> ' . esc_html__( 'minutes', 'flx-app' ) . '</td></tr>';
		echo '<tr><th><label for="flxapp-rd">' . esc_html__( 'Session lifetime (when unused)', 'flx-app' ) . '</label></th><td><input id="flxapp-rd" type="number" min="1" max="365" name="refresh_days" value="' . (int) $s['refresh_days'] . '" class="small-text"> ' . esc_html__( 'days', 'flx-app' ) . '<p class="description">' . esc_html__( 'The period renews while the app is used; after this many days without use, sign-in is required again.', 'flx-app' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'MCP clients', 'flx-app' ) . '</th><td><label><input type="checkbox" name="dynamic_clients" value="1"' . checked( $s['dynamic_clients'], 1, false ) . '> ' . esc_html__( 'Allow self-registration (MCP clients such as Claude connect via OAuth; every connection still needs your approval)', 'flx-app' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Admin session', 'flx-app' ) . '</th><td><label><input type="checkbox" name="web_session" value="1"' . checked( $s['web_session'], 1, false ) . '> ' . esc_html__( 'Let the app open menus without a native screen as admin pages (one-time sign-in link)', 'flx-app' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sign in with code', 'flx-app' ) . '</th><td>'
			. '<label><input type="checkbox" name="otp_enabled" value="1"' . checked( $s['otp_enabled'], 1, false ) . '> ' . esc_html__( 'Let donors sign in with a code sent to their email, or to the phone number saved on their account', 'flx-app' ) . '</label><br>'
			. '<label><input type="checkbox" name="otp_create" value="1"' . checked( $s['otp_create'], 1, false ) . '> ' . esc_html__( 'Create a customer account for an email after its sign-in code is confirmed', 'flx-app' ) . '</label><br>'
			. '<label><input type="checkbox" name="otp_staff" value="1"' . checked( $s['otp_staff'], 1, false ) . '> ' . esc_html__( 'Let staff sign in with a code too (not recommended; staff use OAuth in the browser)', 'flx-app' ) . '</label>'
			. '<p class="description">' . esc_html__( 'Phone codes go out through Flux Call Center (WhatsApp) when that channel is available; otherwise the code is emailed to the account. A phone code signs in only the account whose saved number is exactly that full number. Donations and orders never open or claim an account.', 'flx-app' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Own phone number', 'flx-app' ) . '</th><td>'
			. '<label><input type="checkbox" name="phone_self_verify" value="1"' . checked( $s['phone_self_verify'], 1, false ) . '> ' . esc_html__( 'Let a signed-in donor confirm a phone number and save it on their account', 'flx-app' ) . '</label>'
			. '<p class="description">' . esc_html__( 'The code is sent only to that phone (WhatsApp or an SMS provider), never by email. People who can edit posts keep an administrator-managed number unless staff code sign-in is turned on.', 'flx-app' ) . '</p></td></tr>';
		echo '<tr><th><label for="flxapp-kc">' . esc_html__( 'Can create API keys', 'flx-app' ) . '</label></th><td><select id="flxapp-kc" name="keys_cap">';
		foreach ( flxapp_key_cap_choices() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['keys_cap'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr></table>';
		submit_button( __( 'Save', 'flx-app' ) );
		echo '</form>';
	} elseif ( 'appearance' === $tab ) {
		flxapp_render_appearance_tab();
	} elseif ( 'mobile' === $tab ) {
		flxapp_render_mobile_tab( $s );
	} elseif ( 'modules' === $tab ) {
		flxapp_render_modules_tab( $s );
	} elseif ( 'menu' === $tab ) {
		flxapp_render_menu_tab();
	} elseif ( 'all' === $tab ) {
		echo '<h2>' . esc_html__( 'Devices', 'flx-app' ) . '</h2>';
		flxapp_render_devices( flxapp_user_devices( 0 ), true );
		echo '<h2>' . esc_html__( 'Sessions and keys', 'flx-app' ) . '</h2>';
		flxapp_render_tokens( flxapp_user_tokens( 0, array( 'refresh', 'key' ) ), true );
	} elseif ( 'apps' === $tab ) {
		echo '<table class="widefat striped flxapp-table"><thead><tr><th>' . esc_html__( 'Application', 'flx-app' ) . '</th><th>client_id</th><th>' . esc_html__( 'Redirect URIs', 'flx-app' ) . '</th><th>' . esc_html__( 'Type', 'flx-app' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( flxapp_clients() as $c ) {
			$type = ! empty( $c['builtin'] ) ? __( 'Built-in', 'flx-app' ) : ( ! empty( $c['dynamic'] ) ? __( 'Self-registered (MCP)', 'flx-app' ) : __( 'Added manually', 'flx-app' ) );
			if ( ! empty( $c['secret_hash'] ) ) {
				$type .= ' · ' . __( 'with secret', 'flx-app' );
			}
			echo '<tr><td data-label="' . esc_attr__( 'Application', 'flx-app' ) . '"><strong>' . esc_html( $c['name'] ) . '</strong></td><td data-label="client_id"><code>' . esc_html( $c['id'] ) . '</code></td><td data-label="' . esc_attr__( 'Redirect URIs', 'flx-app' ) . '">' . implode( '<br>', array_map( 'esc_html', (array) $c['redirect_uris'] ) ) . '</td><td data-label="' . esc_attr__( 'Type', 'flx-app' ) . '">' . esc_html( $type ) . '</td><td>';
			if ( empty( $c['builtin'] ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete the application and all of its sessions?', 'flx-app' ) ) . '\')"><input type="hidden" name="action" value="flxapp_client_delete"><input type="hidden" name="id" value="' . esc_attr( $c['id'] ) . '">';
				wp_nonce_field( 'flxapp_client_delete' );
				echo '<button class="button button-link-delete">' . esc_html__( 'Delete', 'flx-app' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Add application', 'flx-app' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxapp-card"><input type="hidden" name="action" value="flxapp_client">';
		wp_nonce_field( 'flxapp_client' );
		echo '<p><label>' . esc_html__( 'Name', 'flx-app' ) . '<br><input type="text" name="name" required maxlength="80" class="regular-text"></label></p>';
		echo '<p><label>' . esc_html__( 'Redirect URIs (one per line)', 'flx-app' ) . '<br><textarea name="redirect_uris" rows="3" class="large-text" required placeholder="https://ornek.com/oauth/callback"></textarea></label></p>';
		echo '<p><label><input type="checkbox" name="confidential" value="1"> ' . esc_html__( 'Generate a client secret (for server-side apps; not needed for desktop/mobile)', 'flx-app' ) . '</label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Add', 'flx-app' ) . '</button></p></form>';
		$m = flxapp_metadata();
		echo '<h2>' . esc_html__( 'Connection details', 'flx-app' ) . '</h2><table class="form-table flxapp-meta">';
		foreach ( array( 'issuer', 'authorization_endpoint', 'token_endpoint', 'revocation_endpoint', 'registration_endpoint' ) as $k ) {
			if ( ! empty( $m[ $k ] ) ) {
				echo '<tr><th>' . esc_html( $k ) . '</th><td><code>' . esc_html( $m[ $k ] ) . '</code></td></tr>';
			}
		}
		echo '<tr><th>metadata</th><td><code>' . esc_html( flxapp_issuer() . '/.well-known/oauth-authorization-server' ) . '</code></td></tr>';
		echo '<tr><th>MCP</th><td><code>' . esc_html( rest_url( 'flx-mcp/v1/mcp' ) ) . '</code></td></tr></table>';
	}
	echo '</div>';
}

/** Modüller + manifestler, kimliğe göre: [ title, menu, cap, version, kind: manifest|native|web, errors ]. */
function flxapp_admin_modules() {
	$out = array();
	foreach ( (array) apply_filters( 'flxapp_modules', array() ) as $id => $m ) {
		$id         = sanitize_key( $id );
		$out[ $id ] = array(
			'title'   => (string) ( $m['title'] ?? $id ),
			'menu'    => (string) ( $m['menu'] ?? '' ),
			'cap'     => (string) ( $m['cap'] ?? '' ),
			'version' => (string) ( $m['version'] ?? '' ),
			'kind'    => 'native',
			'errors'  => array(),
		);
	}
	foreach ( flxapp_manifests_raw() as $id => $m ) {
		$out[ $id ] = array(
			'title'   => (string) ( $m['title'] ?? $id ),
			'menu'    => (string) ( $m['menu'] ?? ( $out[ $id ]['menu'] ?? '' ) ),
			'cap'     => is_string( $m['caps']['view'] ?? null ) ? $m['caps']['view'] : ( $out[ $id ]['cap'] ?? '' ),
			'version' => (string) ( $m['version'] ?? '' ),
			'kind'    => 'manifest',
			'screens' => count( (array) ( $m['screens'] ?? array() ) ),
			'errors'  => flxapp_manifest_validate( $m ),
		);
	}
	return $out;
}

/** Modüller: uygulamadaki ekranlar (manifest, yerel ya da web); kapatılan modülün menüsü panel sayfası olarak açılır. */
function flxapp_render_modules_tab( array $s ) {
	$mods  = flxapp_admin_modules();
	$kinds = array(
		'manifest' => __( 'Manifest', 'flx-app' ),
		'native'   => __( 'Native screen', 'flx-app' ),
		'web'      => __( 'Web view', 'flx-app' ),
	);
	echo '<p class="flxapp-lead">' . esc_html__( 'Plugin screens in the app. Manifest modules are drawn from the same definition on desktop and mobile; native modules open with the app\'s own screen. A disabled module\'s menu opens as an admin page in the app. Who sees what follows capabilities and the role menu.', 'flx-app' ) . '</p>';
	if ( ! $mods ) {
		echo '<p class="flxapp-empty">' . esc_html__( 'No modules registered.', 'flx-app' ) . '</p>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxapp_modules">';
	wp_nonce_field( 'flxapp_modules' );
	echo '<table class="widefat striped flxapp-table"><thead><tr><th>' . esc_html__( 'On', 'flx-app' ) . '</th><th>' . esc_html__( 'Module', 'flx-app' ) . '</th><th>' . esc_html__( 'In the app', 'flx-app' ) . '</th><th>' . esc_html__( 'Menu', 'flx-app' ) . '</th><th>' . esc_html__( 'Capability', 'flx-app' ) . '</th><th>' . esc_html__( 'Version', 'flx-app' ) . '</th></tr></thead><tbody>';
	foreach ( $mods as $id => $m ) {
		$on   = ! in_array( $id, (array) $s['modules_off'], true );
		$kind = $on ? $m['kind'] : 'web';
		echo '<tr><td data-label="' . esc_attr__( 'On', 'flx-app' ) . '"><label class="flxapp-switch"><input type="checkbox" name="on[]" value="' . esc_attr( $id ) . '"' . checked( $on, true, false ) . '><span></span></label></td>';
		echo '<td data-label="' . esc_attr__( 'Module', 'flx-app' ) . '"><strong>' . esc_html( $m['title'] ) . '</strong> <code>' . esc_html( $id ) . '</code>';
		if ( $m['errors'] ) {
			/* translators: %s: list of invalid manifest fields */
			echo '<br><span class="flxapp-bad">' . esc_html( sprintf( __( 'Manifest error: %s', 'flx-app' ), implode( ', ', array_slice( $m['errors'], 0, 6 ) ) ) ) . '</span>';
		}
		echo '</td><td data-label="' . esc_attr__( 'In the app', 'flx-app' ) . '"><span class="flxapp-pf is-on">' . esc_html( $kinds[ $kind ] ) . '</span>';
		if ( 'manifest' === $kind && ! empty( $m['screens'] ) ) {
			/* translators: %d: screen count */
			echo ' <span class="flxapp-muted">' . esc_html( sprintf( _n( '%d screen', '%d screens', (int) $m['screens'], 'flx-app' ), (int) $m['screens'] ) ) . '</span>';
		}
		echo '</td><td data-label="' . esc_attr__( 'Menu', 'flx-app' ) . '">' . ( '' === $m['menu'] ? '<span class="flxapp-muted">' . esc_html__( 'Separate item', 'flx-app' ) . '</span>' : '<code>' . esc_html( $m['menu'] ) . '</code>' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Capability', 'flx-app' ) . '">' . ( '' === $m['cap'] ? '—' : '<code>' . esc_html( $m['cap'] ) . '</code>' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Version', 'flx-app' ) . '">' . esc_html( '' !== $m['version'] ? $m['version'] : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	submit_button( __( 'Save', 'flx-app' ) );
	echo '</form>';
}

/** Menü: uygulama menüsü rol menüsüyle aynı; öğe başına web / mobil / masaüstü seçimi Flux Admin menü düzenleyicisinde. */
function flxapp_render_menu_tab() {
	echo '<p class="flxapp-lead">' . esc_html__( 'The app\'s side menu is the same as the panel menu for the user\'s role (order, groups, names, hidden items). Whether each item shows on web, mobile or desktop is chosen in the menu editor. An item with Web off shows only in the app.', 'flx-app' ) . '</p>';
	if ( ! function_exists( 'flxa_settings_url' ) || ! function_exists( 'flxa_menus_option' ) ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Platform selection needs Flux Admin 1.3.0 or later.', 'flx-app' ) . '</p></div>';
		return;
	}
	echo '<p><a class="button button-primary" href="' . esc_url( flxa_settings_url( 'menu' ) ) . '">' . esc_html__( 'Open menu editor', 'flx-app' ) . '</a></p>';
	$pfs    = flxapp_platforms();
	$names  = wp_roles()->get_names();
	$labels = array();
	foreach ( function_exists( 'flxa_menu_catalog' ) ? (array) flxa_menu_catalog() : array() as $it ) {
		$labels[ (string) $it['slug'] ] = (string) $it['label'];
	}
	$rows  = array();
	foreach ( (array) ( flxa_menus_option()['roles'] ?? array() ) as $role => $cfg ) {
		foreach ( (array) ( $cfg['entries'] ?? array() ) as $e ) {
			if ( ! empty( $e['pf_off'] ) && ! empty( $e['slug'] ) ) {
				$rows[] = array( '_default' === $role ? __( 'Default', 'flx-app' ) : translate_user_role( $names[ $role ] ?? $role ), $e );
			}
		}
	}
	echo '<h2>' . esc_html__( 'Items hidden by platform', 'flx-app' ) . '</h2>';
	if ( ! $rows ) {
		echo '<p class="flxapp-empty">' . esc_html__( 'None: every item shows everywhere.', 'flx-app' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped flxapp-table"><thead><tr><th>' . esc_html__( 'Role', 'flx-app' ) . '</th><th>' . esc_html__( 'Menu', 'flx-app' ) . '</th>';
	foreach ( $pfs as $l ) {
		echo '<th>' . esc_html( $l ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td data-label="' . esc_attr__( 'Role', 'flx-app' ) . '">' . esc_html( $r[0] ) . '</td><td data-label="' . esc_attr__( 'Menu', 'flx-app' ) . '"><strong>' . esc_html( ! empty( $r[1]['label'] ) ? $r[1]['label'] : ( $labels[ $r[1]['slug'] ] ?? $r[1]['slug'] ) ) . '</strong></td>';
		foreach ( $pfs as $k => $l ) {
			$on = ! in_array( $k, (array) $r[1]['pf_off'], true );
			echo '<td data-label="' . esc_attr( $l ) . '"><span class="flxapp-pf' . ( $on ? ' is-on' : '' ) . '">' . ( $on ? esc_html__( 'Visible', 'flx-app' ) : esc_html__( 'Hidden', 'flx-app' ) ) . '</span></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}
