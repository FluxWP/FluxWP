<?php
/**
 * Kullanıcılar › Roller ve erişim: FluxWP rollerinin yetkileri, kişileri (telefon dahil), açılış ekranı, referans ayarı ve yeni rol ekleme.
 */

defined( 'ABSPATH' ) || exit;

function flxr_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'users.php?page=flx-roles' ) );
}

add_action(
	'admin_menu',
	function () {
		$hook = add_users_page( __( 'Roles and access', 'flx-roles' ), __( 'Roles and access', 'flx-roles' ), 'manage_options', 'flx-roles', 'flxr_render_page' );
		add_action(
			'admin_print_styles-' . $hook,
			function () {
				if ( flxr_flux_ui() ) {
					flxr_flux_assets();
					return;
				}
				wp_enqueue_style( 'flxr-admin', FLXR_URL . 'assets/admin.css', array(), FLXR_VERSION );
			}
		);
	}
);

/*
 * New view (1.5.0, GraphQL): the page opens the same React screen as the FluxWP app (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-roles). It is the default and has every feature of the classic page;
 * a user can switch back to the classic page from the screen header for now. Needs Flux GraphQL.
 */
function flxr_flux_available() {
	return file_exists( FLXR_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxr_flux_ui() {
	return flxr_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxr_ui', true );
}

/** Link that switches the current user between the views. */
function flxr_ui_switch_url( $ui ) {
	return wp_nonce_url( flxr_admin_url( array( 'flxr_ui' => 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxr_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxr_ui'], $_GET['page'] ) || 'flx-roles' !== $_GET['page'] || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxr_ui' );
		if ( 'classic' === $_GET['flxr_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxr_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxr_ui' );
		}
		wp_safe_redirect( flxr_admin_url() );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxr_flux_module_tag( $tag, $handle ) {
	if ( 'flxr-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		$tag,
		1
	);
}

/** Mount options of the screen besides what the shell adds (rest, nonce, locale…). */
function flxr_flux_options() {
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxr_ui_switch_url( 'classic' ),
	);
}

// Flux GraphQL screen shell: first data printed by PHP, in-place navigation from the menu.
add_action(
	'admin_menu',
	function () {
		if ( function_exists( 'flx_admin_screen' ) && flxr_flux_available() ) {
			flx_admin_screen(
				'flx-roles',
				array(
					'module'       => 'flxr-roles',
					'assets_dir'   => FLXR_DIR . 'assets/flux/',
					'assets_url'   => FLXR_URL . 'assets/flux/',
					'version'      => FLXR_VERSION,
					'enabled'      => 'flxr_flux_ui',
					'options'      => 'flxr_flux_options',
					'container_id' => 'flxr-app',
					'wrap_class'   => 'flxr-wrap',
					'preload'      => array( array( 'FlxRolesScreen' ) ),
				)
			);
		}
	},
	20
);

function flxr_flux_assets() {
	if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
		return; // The screen shell loads it.
	}
	$ver  = FLXR_VERSION . '-' . (int) filemtime( FLXR_DIR . 'assets/flux/flux-modules.js' );
	$opts = array_merge(
		array(
			'module'   => 'flxr-roles',
			'rest'     => rest_url(),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'locale'   => get_user_locale(),
			'siteName' => get_bloginfo( 'name' ),
			'adminUrl' => admin_url(),
			'theme'    => 'light',
		),
		flxr_flux_options()
	);
	wp_enqueue_style( 'flxr-flux', FLXR_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxr-flux', FLXR_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxr_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxr-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxr-app";slot.opts=o;function sync(){var el=document.getElementById("flxr-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action( 'admin_post_flxr_save', 'flxr_handle_save' );
add_action( 'admin_post_flxr_user', 'flxr_handle_user' );
add_action( 'admin_post_flxr_reset', 'flxr_handle_reset' );
add_action( 'admin_post_flxr_role', 'flxr_handle_role' );

function flxr_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-roles' ), 403 );
	}
	check_admin_referer( 'flxr_save' );
	$in    = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$home  = isset( $_POST['home'] ) && is_array( $_POST['home'] ) ? wp_unslash( $_POST['home'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$roles = array();
	foreach ( FLXR_Roles_Service::role_keys() as $rk ) {
		$on = array();
		foreach ( array_keys( FLXR_Roles_Service::capability_labels() ) as $cap ) {
			if ( ! empty( $in[ $rk ][ $cap ] ) ) {
				$on[] = $cap;
			}
		}
		$v       = sanitize_key( (string) ( $home[ $rk ] ?? 'auto' ) );
		$roles[] = array(
			'key'          => $rk,
			'capabilities' => $on,
			'home'         => isset( flxr_home_choices()[ $v ] ) ? $v : 'auto',
		);
	}
	FLXR_Roles_Service::save( $roles, max( 1, min( 365, absint( $_POST['ref_days'] ?? 30 ) ) ) );
	wp_safe_redirect( flxr_admin_url( array( 'flxr_msg' => 'saved' ) ) );
	exit;
}

function flxr_handle_reset() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-roles' ), 403 );
	}
	check_admin_referer( 'flxr_reset' );
	FLXR_Roles_Service::reset_role( sanitize_key( wp_unslash( $_GET['role'] ?? '' ) ) );
	wp_safe_redirect( flxr_admin_url( array( 'flxr_msg' => 'reset' ) ) );
	exit;
}

/** Panelden rol ekle / sil. */
function flxr_handle_role() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-roles' ), 403 );
	}
	check_admin_referer( 'flxr_role' );
	$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
	if ( 'delete' === $do ) {
		$msg = true === FLXR_Roles_Service::delete_role( sanitize_key( wp_unslash( $_POST['role'] ?? '' ) ) ) ? 'role_deleted' : 'user_err';
	} else {
		$r   = FLXR_Roles_Service::create_role( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) );
		$msg = is_wp_error( $r ) ? 'role_err' : 'role_added';
	}
	wp_safe_redirect( flxr_admin_url( array( 'flxr_msg' => $msg ) ) );
	exit;
}

/** Kişi ekle / rolden çıkar. */
function flxr_handle_user() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'promote_users' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-roles' ), 403 );
	}
	check_admin_referer( 'flxr_user' );
	$rk  = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
	$do  = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
	$msg = 'user_err';
	if ( isset( flxr_roles()[ $rk ] ) ) {
		if ( 'remove' === $do ) {
			$msg = true === FLXR_Roles_Service::remove_member( $rk, absint( $_POST['user'] ?? 0 ) ) ? 'user_removed' : 'user_err';
		} else {
			$who = sanitize_text_field( wp_unslash( $_POST['who'] ?? '' ) );
			$u   = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
			if ( ! $u ) {
				$msg = 'user_missing';
			} elseif ( user_can( $u, 'manage_options' ) ) {
				$msg = 'user_admin';
			} else {
				$msg = is_wp_error( FLXR_Roles_Service::add_member( $rk, $who ) ) ? 'user_err' : 'user_added';
			}
		}
	}
	wp_safe_redirect( flxr_admin_url( array( 'flxr_msg' => $msg ) ) . '#flxr-people' );
	exit;
}

function flxr_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( flxr_flux_ui() && function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
		return;
	}
	if ( flxr_flux_ui() ) {
		echo '<style>#flxr-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxr-wrap"><div id="flxr-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-roles' ) . '</noscript></div></div>';
		return;
	}
	$roles    = flxr_roles();
	$catalog  = flxr_catalog();
	$settings = flxr_settings();
	$msgs     = array(
		'saved'        => array( 'success', __( 'Saved. Permission changes apply immediately.', 'flx-roles' ) ),
		'reset'        => array( 'success', __( 'The role is back to its default permissions.', 'flx-roles' ) ),
		'user_added'   => array( 'success', __( 'Person added to the role.', 'flx-roles' ) ),
		'user_removed' => array( 'success', __( 'Person removed from the role.', 'flx-roles' ) ),
		'user_missing' => array( 'error', __( 'No user with this username or email. Create the user first.', 'flx-roles' ) ),
		'user_admin'   => array( 'error', __( 'Administrators can\'t get this role; they already see everything.', 'flx-roles' ) ),
		'user_err'     => array( 'error', __( 'The action could not be completed.', 'flx-roles' ) ),
		'role_added'   => array( 'success', __( 'Role added; it starts with admin panel access only. Turn on its permissions in the table.', 'flx-roles' ) ),
		'role_deleted' => array( 'success', __( 'Role deleted; its people moved to the default role.', 'flx-roles' ) ),
		'role_err'     => array( 'error', __( 'Role name must be 1-40 characters.', 'flx-roles' ) ),
	);
	$m = sanitize_key( wp_unslash( $_GET['flxr_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap flxr">';
	echo '<h1>' . esc_html__( 'Roles and access', 'flx-roles' ) . '</h1>';
	if ( flxr_flux_available() ) {
		echo '<p><a class="button" href="' . esc_url( flxr_ui_switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-roles' ) . '</a> <span class="flxr-muted">' . esc_html__( 'Open the new view (same features, works in the FluxWP app too).', 'flx-roles' ) . '</span></p>';
	}
	if ( isset( $msgs[ $m ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $msgs[ $m ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $m ][1] ) . '</p></div>';
	}
	echo '<p class="flxr-lead">' . esc_html__( 'Turn on and off what each role can access. A disabled action disappears from the menu and can\'t be done even by URL. The WordPress Administrator sees everything.', 'flx-roles' ) . '</p>';

	// Rol kartları.
	echo '<div class="flxr-roles">';
	$counts = count_users();
	foreach ( $roles as $rk => $r ) {
		$n = (int) ( $counts['avail_roles'][ $rk ] ?? 0 );
		echo '<div class="flxr-card flxr-role"><h2>' . esc_html( $r['label'] ) . ' <span class="flxr-count">' . esc_html(
			sprintf(
				/* translators: %s: number of people */
				_n( '%s person', '%s people', $n, 'flx-roles' ),
				number_format_i18n( $n )
			)
		) . '</span></h2><p>' . esc_html( $r['desc'] ) . '</p>';
		if ( ! empty( $r['custom'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'flxr_role' );
			echo '<input type="hidden" name="action" value="flxr_role"><input type="hidden" name="do" value="delete"><input type="hidden" name="role" value="' . esc_attr( $rk ) . '"><button type="submit" class="button-link flxr-remove" data-flx-confirm-danger data-flx-confirm="' . esc_attr__( 'Delete this role? Its people move to the default role.', 'flx-roles' ) . '">' . esc_html__( 'Delete role', 'flx-roles' ) . '</button></form>';
		}
		echo '</div>';
	}
	echo '<div class="flxr-card flxr-role flxr-newrole"><h2>' . esc_html__( 'New role', 'flx-roles' ) . '</h2><p>' . esc_html__( 'For example Volunteer or Accounting. Starts with admin panel access only.', 'flx-roles' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxr-add">';
	wp_nonce_field( 'flxr_role' );
	echo '<input type="hidden" name="action" value="flxr_role"><input type="hidden" name="do" value="add"><label class="screen-reader-text" for="flxr-newrole">' . esc_html__( 'Role name', 'flx-roles' ) . '</label><input type="text" id="flxr-newrole" name="name" maxlength="40" required placeholder="' . esc_attr__( 'Role name', 'flx-roles' ) . '"> <button type="submit" class="button">' . esc_html__( 'Add role', 'flx-roles' ) . '</button></form></div>';
	echo '</div>';

	// Yetki tablosu + ayarlar (tek form).
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'flxr_save' );
	echo '<input type="hidden" name="action" value="flxr_save">';
	echo '<div class="flxr-card flxr-matrix-wrap"><table class="flxr-matrix"><thead><tr><th scope="col">' . esc_html__( 'Access', 'flx-roles' ) . '</th>';
	foreach ( $roles as $rk => $r ) {
		echo '<th scope="col" class="flxr-col">' . esc_html( $r['label'] ) . '</th>';
	}
	echo '</tr></thead>';
	foreach ( $catalog as $g ) {
		$active = ! empty( $g['active'] );
		echo '<tbody' . ( $active ? '' : ' class="is-off"' ) . '><tr class="flxr-group"><th colspan="' . ( count( $roles ) + 1 ) . '" scope="rowgroup">' . esc_html( $g['label'] );
		if ( ! $active && ! empty( $g['plugin'] ) ) {
			echo ' <span class="flxr-badge">' . esc_html(
				sprintf(
					/* translators: %s: plugin name */
					__( '%s is not installed', 'flx-roles' ),
					$g['plugin']
				)
			) . '</span>';
		}
		echo '</th></tr>';
		foreach ( $g['caps'] as $cap => $c ) {
			$pending = $active && ! empty( $c['new'] ) && ! flxr_cap_supported( $cap );
			echo '<tr><th scope="row"><span class="flxr-label">' . esc_html( $c['label'] ) . '</span>';
			if ( $pending ) {
				echo ' <span class="flxr-badge is-wait" title="' . esc_attr__( 'The setting is saved; it takes effect once the plugin is updated to a version that enforces this permission.', 'flx-roles' ) . '">' . esc_html(
					sprintf(
						/* translators: %s: plugin name */
						__( 'Waiting for %s update', 'flx-roles' ),
						$g['plugin']
					)
				) . '</span>';
			}
			if ( ! empty( $c['warn'] ) ) {
				echo ' <span class="flxr-badge is-warn">' . esc_html__( 'broad access', 'flx-roles' ) . '</span>';
			}
			echo '<code>' . esc_html( $cap ) . '</code></th>';
			foreach ( $roles as $rk => $r ) {
				$on  = flxr_role_has( $rk, $cap );
				$id  = 'flxr-' . $rk . '-' . $cap;
				$dis = ! empty( $c['locked'] ) ? ' disabled' : '';
				echo '<td class="flxr-col"><label class="flxr-switch" for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="caps[' . esc_attr( $rk ) . '][' . esc_attr( $cap ) . ']" value="1"' . checked( $on, true, false ) . $dis . '><span aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $r['label'] . ': ' . $c['label'] ) . '</span></label></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		echo '</tbody>';
	}
	echo '</table></div>';

	// Ayarlar.
	echo '<div class="flxr-card"><h2>' . esc_html__( 'Settings', 'flx-roles' ) . '</h2><table class="form-table" role="presentation">';
	foreach ( $roles as $rk => $r ) {
		echo '<tr><th scope="row"><label for="flxr-home-' . esc_attr( $rk ) . '">' . esc_html(
			sprintf(
				/* translators: %s: role name */
				__( '%s: screen after login', 'flx-roles' ),
				$r['label']
			)
		) . '</label></th><td><select id="flxr-home-' . esc_attr( $rk ) . '" name="home[' . esc_attr( $rk ) . ']">';
		foreach ( flxr_home_choices() as $k => $ch ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $settings['home'][ $rk ] ?? 'auto', $k, false ) . '>' . esc_html( $ch[0] ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '<tr><th scope="row"><label for="flxr-days">' . esc_html__( 'Referral link lifetime', 'flx-roles' ) . '</label></th><td><input type="number" id="flxr-days" name="ref_days" min="1" max="365" class="small-text" value="' . (int) $settings['ref_days'] . '"> ' . esc_html__( 'days', 'flx-roles' ) . '<p class="description">' . esc_html__( 'If a visitor who came through a referral link donates within this period, the donation is credited to that person. If they come through another referral link, the latest one wins.', 'flx-roles' ) . '</p></td></tr>';
	echo '</table></div>';

	echo '<p class="flxr-actions">';
	submit_button( __( 'Save', 'flx-roles' ), 'primary', 'submit', false );
	foreach ( $roles as $rk => $r ) {
		if ( ! empty( $r['custom'] ) ) {
			continue;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxr_reset&role=' . $rk ), 'flxr_reset' );
		echo ' <a class="button" href="' . esc_url( $url ) . '" data-flx-confirm-danger data-flx-confirm="' . esc_attr__( 'Reset this role\'s permissions to the defaults?', 'flx-roles' ) . '">' . esc_html(
			sprintf(
				/* translators: %s: role name */
				__( '%s: reset to defaults', 'flx-roles' ),
				$r['label']
			)
		) . '</a>';
	}
	echo '</p></form>';

	// Kişiler.
	echo '<h2 id="flxr-people" class="flxr-h2">' . esc_html__( 'People', 'flx-roles' ) . '</h2>';
	echo '<div class="flxr-roles">';
	foreach ( $roles as $rk => $r ) {
		$users = get_users( array( 'role' => $rk, 'orderby' => 'display_name', 'number' => 300 ) );
		echo '<div class="flxr-card"><h3>' . esc_html( $r['label'] ) . '</h3>';
		if ( $users ) {
			echo '<ul class="flxr-people">';
			foreach ( $users as $u ) {
				echo '<li><div class="flxr-person"><a href="' . esc_url( get_edit_user_link( $u->ID ) ) . '"><strong>' . esc_html( $u->display_name ) . '</strong></a><span>' . esc_html( $u->user_email ) . '</span>';
				$ph = flxr_user_phone( $u->ID );
				echo '<span>' . ( '' !== $ph ? esc_html( '+' . $ph ) : '<a href="' . esc_url( get_edit_user_link( $u->ID ) . '#flx-phone' ) . '">' . esc_html__( 'Add WhatsApp number', 'flx-roles' ) . '</a>' ) . '</span>';
				if ( user_can( $u, FLXR_CAP_REF ) ) {
					$link = flxr_ref_link( $u->ID );
					echo '<span class="flxr-ref"><input type="text" readonly value="' . esc_attr( $link ) . '" aria-label="' . esc_attr__( 'Referral link', 'flx-roles' ) . '" onclick="this.select()"><button type="button" class="button button-small" data-flxr-copy="' . esc_attr( $link ) . '">' . esc_html__( 'Copy', 'flx-roles' ) . '</button></span>';
				}
				echo '</div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'flxr_user' );
				echo '<input type="hidden" name="action" value="flxr_user"><input type="hidden" name="do" value="remove"><input type="hidden" name="role" value="' . esc_attr( $rk ) . '"><input type="hidden" name="user" value="' . (int) $u->ID . '">';
				echo '<button type="submit" class="button-link flxr-remove" data-flx-confirm-danger data-flx-confirm="' . esc_attr__( 'Remove this person from the role?', 'flx-roles' ) . '">' . esc_html__( 'Remove from role', 'flx-roles' ) . '</button></form></li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="flxr-muted">' . esc_html__( 'Nobody yet.', 'flx-roles' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxr-add">';
		wp_nonce_field( 'flxr_user' );
		echo '<input type="hidden" name="action" value="flxr_user"><input type="hidden" name="do" value="add"><input type="hidden" name="role" value="' . esc_attr( $rk ) . '">';
		echo '<label class="screen-reader-text" for="flxr-who-' . esc_attr( $rk ) . '">' . esc_html__( 'Username or email', 'flx-roles' ) . '</label><input type="text" id="flxr-who-' . esc_attr( $rk ) . '" name="who" required placeholder="' . esc_attr__( 'Username or email', 'flx-roles' ) . '"> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Add', 'flx-roles' ) . '</button></form>';
		echo '</div>';
	}
	echo '</div>';
	echo '<p class="flxr-muted">' . wp_kses(
		sprintf(
			/* translators: %s: link to add a new user */
			__( 'For a new person, first <a href="%s">create a user</a> and choose one of these roles.', 'flx-roles' ),
			esc_url( admin_url( 'user-new.php' ) )
		),
		array( 'a' => array( 'href' => array() ) )
	);
	if ( defined( 'FLXA_VERSION' ) ) {
		echo ' ' . wp_kses(
			sprintf(
				/* translators: %s: link to Flux Admin permissions tab */
				__( 'For every permission of every role, see <a href="%s">Admin theme › Permissions</a>.', 'flx-roles' ),
				esc_url( admin_url( 'options-general.php?page=flx-admin&tab=caps' ) )
			),
			array( 'a' => array( 'href' => array() ) )
		);
	}
	echo '</p>';
	echo '<script>document.addEventListener("click",function(e){var b=e.target.closest("[data-flxr-copy]");if(!b)return;var v=b.getAttribute("data-flxr-copy"),t=b.textContent;function ok(){b.textContent="' . esc_js( __( 'Copied', 'flx-roles' ) ) . '";setTimeout(function(){b.textContent=t;},1500);}if(navigator.clipboard){navigator.clipboard.writeText(v).then(ok);}else{var i=b.previousElementSibling;i.select();document.execCommand("copy");ok();}});</script>';
	echo '</div>';
}
