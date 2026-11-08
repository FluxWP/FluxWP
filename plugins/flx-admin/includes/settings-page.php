<?php
/** Ayarlar › Yönetim teması: Genel, Görünüm, Menü (rol bazlı), Yetkiler, Başlangıç, PWA, Sistem kontrolü. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxa_settings_menu' );
add_action( 'admin_post_flxa_save', 'flxa_settings_save' );
add_action( 'admin_enqueue_scripts', 'flxa_settings_assets' );
add_action( 'load-settings_page_flx-admin', 'flxa_system_tab_redirect' );

/** Flux Speed kuruluysa Sistem kontrolü orada (Performans › Sistem Kontrolü); buradaki sekme gizlenir, eski adres oraya gider. */
function flxa_system_moved() {
	return function_exists( 'flxp_system_checks_url' );
}

function flxa_system_tab_redirect() {
	if ( flxa_system_moved() && 'system' === sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( flxp_system_checks_url() );
		exit;
	}
}

function flxa_settings_menu() {
	add_options_page( __( 'Admin theme', 'flx-admin' ), __( 'Admin theme', 'flx-admin' ), 'manage_options', 'flx-admin', 'flxa_settings_render' );
}

function flxa_settings_tabs() {
	$tabs = array(
		'general'   => __( 'General', 'flx-admin' ),
		'look'      => __( 'Appearance', 'flx-admin' ),
		'menu'      => __( 'Menu and bottom bar', 'flx-admin' ),
		'caps'      => __( 'Permissions', 'flx-admin' ),
		'dashboard' => __( 'Dashboard', 'flx-admin' ),
		'pwa'       => __( 'App (PWA)', 'flx-admin' ),
		'system'    => __( 'System check', 'flx-admin' ),
	);
	if ( flxa_system_moved() ) {
		unset( $tabs['system'] );
	}
	return $tabs;
}

function flxa_settings_tab() {
	$t = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return array_key_exists( $t, flxa_settings_tabs() ) ? $t : 'general';
}

function flxa_settings_assets( $hook ) {
	if ( 'settings_page_flx-admin' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'flxa-settings', FLXA_URL . 'assets/css/settings.css', array(), FLXA_VERSION );
	$tab = flxa_settings_tab();
	if ( 'general' === $tab ) {
		wp_enqueue_media();
		wp_enqueue_script( 'flxa-settings', FLXA_URL . 'assets/js/settings.js', array( 'jquery' ), FLXA_VERSION, true );
		wp_localize_script( 'flxa-settings', 'FLXA_SET', array( 'pick' => __( 'Choose logo', 'flx-admin' ) ) );
	}
	if ( 'menu' === $tab ) {
		wp_enqueue_script( 'flxa-menu-editor', FLXA_URL . 'assets/js/menu-editor.js', array(), FLXA_VERSION, true );
		wp_localize_script( 'flxa-menu-editor', 'FLXA_ME', flxa_menu_editor_data() );
	}
}

/** Menü düzenleyicinin verisi. */
function flxa_menu_editor_data() {
	$catalog = flxa_menu_catalog();
	$slugs   = wp_list_pluck( $catalog, 'slug' );
	$roles   = array( '_default' => __( 'All other roles', 'flx-admin' ) ) + flxa_roles();
	$access  = array();
	foreach ( array_keys( $roles ) as $r ) {
		$obj = '_default' === $r ? null : get_role( $r );
		foreach ( $catalog as $it ) {
			$ok = '_default' === $r || ( $obj && ( $obj->has_cap( $it['cap'] ) || $obj->has_cap( 'manage_options' ) ) );
			if ( ! $ok && $obj ) {
				foreach ( $it['sub'] as $s ) {
					if ( $obj->has_cap( $s['cap'] ) ) {
						$ok = true;
						break;
					}
				}
			}
			$access[ $r ][ $it['slug'] ] = $ok;
		}
	}
	$counts = count_users();
	$icons  = array();
	foreach ( $catalog as $it ) {
		$icons[ $it['slug'] ] = flxa_icon( $it['icon'] ? $it['icon'] : 'dot', 16 );
	}
	return array(
		'catalog'  => $catalog,
		'roles'    => $roles,
		'users'    => (array) ( $counts['avail_roles'] ?? array() ),
		'access'   => $access,
		'saved'    => (array) ( flxa_menus_option()['roles'] ?? array() ),
		'defaults' => flxa_default_entries( $slugs ),
		'defBottom'=> array_slice( flxa_default_bottom(), 0, 4 ),
		'capsUrl'  => flxa_settings_url( 'caps' ),
		'unblockable' => flxa_unblockable(),
		'icons'    => $icons,
		// Platform seçimi (web / mobil / masaüstü): uygulama paketi (Flux App) kaydeder; yoksa düğmeler çıkmaz.
		'platforms'=> flxa_menu_platforms(),
		'ui'       => array(
			'drag'      => flxa_icon( 'drag', 16 ),
			'eye'       => flxa_icon( 'eye', 16 ),
			'eyeOff'    => flxa_icon( 'eye-off', 16 ),
			'chevron'   => flxa_icon( 'chevron', 14 ),
			'x'         => flxa_icon( 'x', 14 ),
			'lock'      => flxa_icon( 'lock', 16 ),
			'up'        => '↑',
			'down'      => '↓',
		),
		'i18n'     => array(
			'custom'      => __( 'Custom layout', 'flx-admin' ),
			'inherit'     => __( 'Default layout', 'flx-admin' ),
			'group'       => __( 'Group', 'flx-admin' ),
			'groupName'   => __( 'Group name', 'flx-admin' ),
			'newGroup'    => __( 'New group', 'flx-admin' ),
			'addGroup'    => __( '+ Add group', 'flx-admin' ),
			'removeGroup' => __( 'Remove group', 'flx-admin' ),
			'rename'      => __( 'Menu name (empty = original name)', 'flx-admin' ),
			'show'        => __( 'Visible', 'flx-admin' ),
			'hide'        => __( 'Hidden', 'flx-admin' ),
			'noAccess'    => __( 'This role has no access', 'flx-admin' ),
			'subs'        => __( 'Submenus', 'flx-admin' ),
			'moveUp'      => __( 'Move up', 'flx-admin' ),
			'moveDown'    => __( 'Move down', 'flx-admin' ),
			'reset'       => __( 'Reset this role to default', 'flx-admin' ),
			'copyFrom'    => __( 'Copy from another role…', 'flx-admin' ),
			'preview'     => __( 'Preview', 'flx-admin' ),
			'bottom'      => __( 'Mobile bottom bar', 'flx-admin' ),
			'bottomHelp'  => __( 'Up to 4 shortcuts; the fifth button "More" opens the side panel.', 'flx-admin' ),
			'none'        => __( '— empty —', 'flx-admin' ),
			'users'       => __( 'users', 'flx-admin' ),
			'other'       => __( 'Menus of newly installed plugins automatically land in the "Other" group.', 'flx-admin' ),
			'unsaved'     => __( 'You have unsaved changes.', 'flx-admin' ),
			'block'       => __( 'Block access', 'flx-admin' ),
			'blocked'     => __( 'Access blocked: not in the menu, opening by URL shows "no permission"', 'flx-admin' ),
			'collapsed'   => __( 'Start collapsed', 'flx-admin' ),
			'capsLink'    => __( 'All permissions: Permissions tab', 'flx-admin' ),
			'adminNote'   => __( 'Access can\'t be blocked for the Administrator role.', 'flx-admin' ),
			'platforms'   => __( 'Where to show', 'flx-admin' ),
			/* translators: %s: platform name */
			'platformOff' => __( '%s: hidden', 'flx-admin' ),
		),
	);
}

function flxa_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab  = flxa_settings_tab();
	$s    = flxa_settings();
	$base = flxa_settings_url();
	echo '<div class="wrap flxa-settings">';
	echo '<h1 class="flxa-settings-title"><img src="' . esc_url( FLXA_URL . 'assets/icon.svg' ) . '" alt="" width="32" height="32"> ' . esc_html__( 'Admin theme', 'flx-admin' ) . ' <small>Flux Admin ' . esc_html( FLXA_VERSION ) . '</small></h1>';
	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'flx-admin' ) . '</p></div>';
	}
	echo '<nav class="nav-tab-wrapper flxa-tabs">';
	foreach ( flxa_settings_tabs() as $k => $l ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';

	if ( 'system' === $tab ) {
		if ( function_exists( 'flx_system_checks_render' ) ) {
			flx_system_checks_render( array( 'recheck_url' => flxa_settings_url( 'system' ) ) );
		} else {
			flxa_render_own_checks();
		}
		echo '</div>';
		return;
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxa-form" id="flxa-form">';
	wp_nonce_field( 'flxa_save' );
	echo '<input type="hidden" name="action" value="flxa_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';

	if ( 'general' === $tab ) {
		$logo = (int) $s['logo_id'];
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Brand', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="flxa-enabled"><?php esc_html_e( 'Admin theme', 'flx-admin' ); ?></label></th>
					<td><label><input type="checkbox" id="flxa-enabled" name="s[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'On (turning it off restores the standard WordPress dashboard)', 'flx-admin' ); ?></label></td></tr>
				<tr><th scope="row"><label for="flxa-brand"><?php esc_html_e( 'Name', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-brand" name="s[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
				<tr><th scope="row"><label for="flxa-subtitle"><?php esc_html_e( 'Subtitle', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-subtitle" name="s[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>" placeholder="<?php esc_attr_e( 'Admin', 'flx-admin' ); ?>"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Logo', 'flx-admin' ); ?></th>
					<td class="flxa-logo-field"><img src="<?php echo esc_url( flxa_logo_url( 96 ) ); ?>" alt="" width="48" height="48" data-flxa-logo-preview>
						<input type="hidden" name="s[logo_id]" value="<?php echo (int) $logo; ?>" data-flxa-logo-id>
						<button type="button" class="button" data-flxa-logo-pick><?php esc_html_e( 'Choose logo', 'flx-admin' ); ?></button>
						<button type="button" class="button-link" data-flxa-logo-clear<?php echo $logo ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'flx-admin' ); ?></button>
						<p class="description"><?php esc_html_e( 'If empty, the site icon is used; if there is none, the Flux Admin icon.', 'flx-admin' ); ?></p></td></tr>
			</table>
		</div>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Options', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Features', 'flx-admin' ); ?></th>
					<td><fieldset>
						<label><input type="checkbox" name="s[bottom_bar]" value="1" <?php checked( $s['bottom_bar'] ); ?>> <?php esc_html_e( 'Bottom bar on mobile', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[app_rail]" value="1" <?php checked( $s['app_rail'] ); ?>> <?php esc_html_e( 'Open WhatsApp and Tasks right next to the side menu (menu shrinks to icons)', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[app_switcher]" value="1" <?php checked( $s['app_switcher'] ); ?>> <?php esc_html_e( 'Show the app switcher on WhatsApp and Tasks screens on phones', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[fast_nav]" value="1" <?php checked( $s['fast_nav'] ); ?>> <?php esc_html_e( 'Fast navigation: the clicked menu page is prepared in advance, transitions are smooth', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[palette]" value="1" <?php checked( $s['palette'] ); ?>> <?php esc_html_e( 'Command palette (Ctrl+K / ⌘K)', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[login]" value="1" <?php checked( $s['login'] ); ?>> <?php esc_html_e( 'Style the login screen too', 'flx-admin' ); ?></label>
					</fieldset></td></tr>
				<tr><th scope="row"><label for="flxa-hb"><?php esc_html_e( 'Live counter interval', 'flx-admin' ); ?></label></th>
					<td><select id="flxa-hb" name="s[heartbeat]">
						<?php foreach ( array( 30, 60, 120 ) as $n ) : ?>
							<option value="<?php echo (int) $n; ?>" <?php selected( (int) $s['heartbeat'], $n ); ?>><?php echo esc_html( sprintf( /* translators: %d: seconds */ __( '%d s', 'flx-admin' ), $n ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Badges (WhatsApp, tasks, pending donations, updates) ride on the WordPress Heartbeat request; no extra requests. 60 s is recommended for Hostinger.', 'flx-admin' ); ?></p></td></tr>
			</table>
		</div>
		<?php
	} elseif ( 'look' === $tab ) {
		flxa_settings_look( $s );
	} elseif ( 'menu' === $tab ) {
		echo '<p class="flxa-lead">' . esc_html__( 'Each role sees its own side menu and mobile bottom bar. Drag to reorder, rename, hide, group. Hiding does not change permissions; it only removes the item from the menu.', 'flx-admin' ) . '</p>';
		if ( ! flxa_menu_catalog() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'The menu list is built on the next page load. Reload the page.', 'flx-admin' ) . '</p></div>';
		}
		echo '<div id="flxa-menu-editor" class="flxa-me"></div>';
		echo '<p class="description">' . esc_html__( '"Block access" really closes that menu for this role: it isn\'t shown, and opening it by URL shows a "no permission" page. See the Permissions tab for detailed permissions.', 'flx-admin' ) . '</p>';
		echo '<input type="hidden" name="menus" id="flxa-menus-json" value="">';
	} elseif ( 'caps' === $tab ) {
		flxa_caps_render();
	} elseif ( 'dashboard' === $tab ) {
		$labels = array(
			'donations_today' => __( 'Donations today', 'flx-admin' ),
			'donations_month' => __( 'This month', 'flx-admin' ),
			'pending'         => __( 'Donation awaiting approval', 'flx-admin' ),
			'whatsapp'        => __( '\\nOkunmamış WhatsApp\\n', 'flx-admin' ),
			'tasks'           => __( 'My open tasks', 'flx-admin' ),
			'system'          => __( 'System check', 'flx-admin' ),
		);
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Welcome and summary', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Top section', 'flx-admin' ); ?></th>
					<td><label><input type="checkbox" name="s[dash_header]" value="1" <?php checked( $s['dash_header'] ); ?>> <?php esc_html_e( 'Greeting, date and summary tiles', 'flx-admin' ); ?></label></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Summary tiles', 'flx-admin' ); ?></th>
					<td><fieldset>
					<?php foreach ( $labels as $k => $l ) : ?>
						<label><input type="checkbox" name="s[dash_stats][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $s['dash_stats'], true ) ); ?>> <?php echo esc_html( $l ); ?></label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'People only see tiles they have permission for. Plugins can add tiles with the `flxa_dashboard_stats` filter.', 'flx-admin' ); ?></p>
					</fieldset></td></tr>
				<tr><th scope="row"><label for="flxa-ql"><?php esc_html_e( 'Extra quick actions', 'flx-admin' ); ?></label></th>
					<td><textarea id="flxa-ql" name="s[quick_links]" rows="4" class="large-text code" placeholder="<?php esc_attr_e( 'Events|edit.php?post_type=etkinlik
Live site|https://', 'flx-admin' ); ?>"><?php echo esc_textarea( $s['quick_links'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One "Label|Address" per line. The address can be relative to wp-admin or a full https:// URL.', 'flx-admin' ); ?></p></td></tr>
			</table>
			<p class="description"><?php esc_html_e( 'Module order and visibility are personal: open "Edit modules" on the dashboard and drag the boxes.', 'flx-admin' ); ?></p>
		</div>
		<?php
	} elseif ( 'pwa' === $tab ) {
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Install as an app', 'flx-admin' ); ?></h2>
			<?php if ( ! flxa_pwa_possible() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The site is not HTTPS; browsers will not allow installing.', 'flx-admin' ); ?></p></div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'PWA', 'flx-admin' ); ?></th>
					<td><label><input type="checkbox" name="s[pwa]" value="1" <?php checked( $s['pwa'] ); ?>> <?php esc_html_e( 'Allow installing the dashboard as an app on phones and computers', 'flx-admin' ); ?></label>
					<p class="description"><?php esc_html_e( 'Opens full screen, shows the unread count on the app icon, and shows an offline screen when the connection drops. Admin pages are never cached.', 'flx-admin' ); ?></p></td></tr>
				<tr><th scope="row"><label for="flxa-pn"><?php esc_html_e( 'App name', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-pn" name="s[pwa_name]" value="<?php echo esc_attr( $s['pwa_name'] ); ?>" placeholder="<?php echo esc_attr( flxa_brand() ); ?>"></td></tr>
				<tr><th scope="row"><label for="flxa-ps"><?php esc_html_e( 'Short name (under the icon)', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-ps" name="s[pwa_short]" maxlength="20" value="<?php echo esc_attr( $s['pwa_short'] ); ?>" placeholder="<?php echo esc_attr( flxa_pwa_short() ); ?>"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Icon', 'flx-admin' ); ?></th>
					<td><img src="<?php echo esc_url( flxa_pwa_icons()[0]['src'] ); ?>" alt="" width="64" height="64" style="border-radius:14px">
					<p class="description"><?php esc_html_e( 'The site icon (Appearance › Customize › Site Identity) is used if set.', 'flx-admin' ); ?></p></td></tr>
			</table>
			<h3><?php esc_html_e( 'How to install?', 'flx-admin' ); ?></h3>
			<ul class="flxa-howto">
				<li><strong>Android / Chrome:</strong> <?php esc_html_e( 'The "Install" button on the dashboard, or menu › Install the app.', 'flx-admin' ); ?></li>
				<li><strong>iPhone / Safari:</strong> <?php esc_html_e( 'Share › Add to Home Screen.', 'flx-admin' ); ?></li>
				<li><strong><?php esc_html_e( 'Computer', 'flx-admin' ); ?>:</strong> <?php esc_html_e( 'The install icon in the address bar.', 'flx-admin' ); ?></li>
			</ul>
		</div>
		<?php
	}
	submit_button( __( 'Save', 'flx-admin' ) );
	echo '</form></div>';
}

function flxa_render_own_checks() {
	$rows = flxa_system_checks( array() );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'flx-admin' ) . '</th><th>' . esc_html__( 'Status', 'flx-admin' ) . '</th><th>' . esc_html__( 'Description', 'flx-admin' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( $r['status'] ) . '</td><td>' . esc_html( $r['message'] );
		if ( ! empty( $r['action_url'] ) ) {
			echo ' <a href="' . esc_url( $r['action_url'] ) . '">' . esc_html( $r['action_label'] ) . '</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function flxa_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'flx-admin' ), 403 );
	}
	check_admin_referer( 'flxa_save' );
	$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? 'general' ) );
	$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$old = get_option( 'flxa_settings', array() );
	$s   = is_array( $old ) ? $old : array();

	if ( 'general' === $tab ) {
		foreach ( array( 'enabled', 'bottom_bar', 'app_rail', 'app_switcher', 'fast_nav', 'palette', 'login' ) as $k ) {
			$s[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$s['brand']        = sanitize_text_field( (string) ( $in['brand'] ?? '' ) );
		$s['subtitle']     = sanitize_text_field( (string) ( $in['subtitle'] ?? '' ) );
		$s['logo_id']      = absint( $in['logo_id'] ?? 0 );
		$hb                = (int) ( $in['heartbeat'] ?? 60 );
		$s['heartbeat']    = in_array( $hb, array( 30, 60, 120 ), true ) ? $hb : 60;
	} elseif ( 'look' === $tab ) {
		$s['line_icons']   = empty( $in['line_icons'] ) ? 0 : 1;
		$s['logo_style']   = 'pastel' === ( $in['logo_style'] ?? '' ) ? 'pastel' : 'gradient';
		$c                 = sanitize_hex_color( (string) ( $in['accent'] ?? '' ) );
		// Temanın rengiyle aynıysa saklanmaz: tema/ön ayar rengi değişince yönetim paneli de değişir.
		$s['accent']       = ( $c && strtolower( $c ) !== strtolower( flxa_theme_primary() ) ) ? $c : '';
		$m                 = (string) ( $in['mode'] ?? 'auto' );
		$s['mode']         = in_array( $m, array( 'auto', 'light', 'dark' ), true ) ? $m : 'auto';
		$s['dark_exclude'] = implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) ( $in['dark_exclude'] ?? '' ) ) ) ) );
		$s['theme']        = flxa_theme_sanitize( is_array( $in['theme'] ?? null ) ? $in['theme'] : array() );
	} elseif ( 'caps' === $tab ) {
		flxa_caps_save();
	} elseif ( 'dashboard' === $tab ) {
		$s['dash_header'] = empty( $in['dash_header'] ) ? 0 : 1;
		$allowed          = array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' );
		$s['dash_stats']  = array_values( array_intersect( $allowed, array_map( 'strval', (array) ( $in['dash_stats'] ?? array() ) ) ) );
		$lines            = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $in['quick_links'] ?? '' ) ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $p ) && '' !== $p[0] && '' !== $p[1] ) {
				$url     = preg_match( '#^https?://#i', $p[1] ) ? esc_url_raw( $p[1] ) : ltrim( preg_replace( '#[^A-Za-z0-9._\-/?=&%\#]#', '', $p[1] ), '/' );
				$lines[] = sanitize_text_field( $p[0] ) . '|' . $url;
			}
		}
		$s['quick_links'] = implode( "\n", $lines );
	} elseif ( 'pwa' === $tab ) {
		$s['pwa']       = empty( $in['pwa'] ) ? 0 : 1;
		$s['pwa_name']  = sanitize_text_field( (string) ( $in['pwa_name'] ?? '' ) );
		$s['pwa_short'] = mb_substr( sanitize_text_field( (string) ( $in['pwa_short'] ?? '' ) ), 0, 20 );
	} elseif ( 'menu' === $tab ) {
		$raw = json_decode( (string) wp_unslash( $_POST['menus'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			update_option( 'flxa_menus', array( 'roles' => flxa_sanitize_menus( $raw ) ), true );
		}
	}
	if ( ! in_array( $tab, array( 'menu', 'caps' ), true ) ) {
		update_option( 'flxa_settings', $s, true );
	}
	delete_transient( 'flxa_syscheck' );
	wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'updated' => 1 ), flxa_settings_url() ) );
	exit;
}

/** Menü düzenleyiciden gelen veriyi temizler: yalnız bilinen roller ve menüde olan kısa adlar. */
function flxa_sanitize_menus( array $raw ) {
	$roles = array_merge( array( '_default' ), array_keys( flxa_roles() ) );
	$known = array();
	foreach ( flxa_menu_catalog() as $it ) {
		$known[ $it['slug'] ] = wp_list_pluck( $it['sub'], 'slug' );
	}
	$out = array();
	foreach ( $raw as $role => $cfg ) {
		if ( ! in_array( $role, $roles, true ) || ! is_array( $cfg ) || empty( $cfg['entries'] ) || ! is_array( $cfg['entries'] ) ) {
			continue;
		}
		$entries = array();
		$seen    = array();
		$gn      = 0;
		foreach ( array_slice( $cfg['entries'], 0, 300 ) as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			if ( 'g' === ( $e['t'] ?? '' ) ) {
				$label = mb_substr( sanitize_text_field( (string) ( $e['label'] ?? '' ) ), 0, 40 );
				$entries[] = array(
					't'     => 'g',
					'id'    => 'g' . ( ++$gn ),
					'label' => $label,
					'c'     => empty( $e['c'] ) ? 0 : 1,
				);
				continue;
			}
			$slug = (string) ( $e['slug'] ?? '' );
			if ( ! isset( $known[ $slug ] ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$item          = array( 't' => 'i', 'slug' => $slug );
			$label         = mb_substr( sanitize_text_field( (string) ( $e['label'] ?? '' ) ), 0, 40 );
			if ( '' !== $label ) {
				$item['label'] = $label;
			}
			if ( ! empty( $e['hidden'] ) ) {
				$item['hidden'] = 1;
			}
			if ( ! empty( $e['blocked'] ) && 'administrator' !== $role && ! in_array( $slug, flxa_unblockable(), true ) ) {
				$item['blocked'] = 1;
			}
			$pf_off = array_values( array_intersect( array_keys( flxa_menu_platforms() ), array_map( 'strval', (array) ( $e['pf_off'] ?? array() ) ) ) );
			if ( $pf_off ) {
				$item['pf_off'] = $pf_off;
			}
			$subs = array_values( array_intersect( $known[ $slug ], array_map( 'strval', (array) ( $e['sub_hidden'] ?? array() ) ) ) );
			if ( $subs ) {
				$item['sub_hidden'] = $subs;
			}
			$entries[] = $item;
		}
		$bottom = array_slice( array_values( array_unique( array_intersect( array_map( 'strval', (array) ( $cfg['bottom'] ?? array() ) ), array_keys( $known ) ) ) ), 0, 4 );
		if ( $entries ) {
			$out[ $role ] = array(
				'entries' => $entries,
				'bottom'  => $bottom,
			);
		}
	}
	return $out;
}
