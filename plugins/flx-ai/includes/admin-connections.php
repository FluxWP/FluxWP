<?php
/**
 * Flux AI & MCP › Bağlantılar: dış MCP sunucuları (ekle/düzenle, oturum aç, araç izinleri).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'flx-ai', __( 'Connections', 'flx-ai' ), __( 'Connections', 'flx-ai' ), 'manage_options', 'flx-ai-connections', 'flxai_page_connections' );
	},
	20
);

add_action(
	'admin_init',
	function () {
		if ( empty( $_POST['flxai_mcpc_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'flxai_mcpc', 'flxai_mcpc_nonce' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidationSanitization
		$do   = sanitize_key( $post['do'] ?? '' );
		$id   = sanitize_key( $post['id'] ?? '' );
		$back = admin_url( 'admin.php?page=flx-ai-connections' );
		$go   = function ( array $q ) use ( $back ) {
			wp_safe_redirect( add_query_arg( $q, $back ) );
			exit;
		};
		$s = '' !== $id ? flxai_mcpc_get( $id ) : null;
		if ( $s && 'personal' === $s['scope'] && (int) $s['owner'] !== get_current_user_id() ) {
			$go( array( 'flxai_err' => rawurlencode( __( 'This is another user\'s personal connection.', 'flx-ai' ) ) ) );
		}
		if ( 'delete' === $do && $s ) {
			flxai_mcpc_delete( $id );
			$go( array( 'flxai_ok' => 'deleted' ) );
		}
		if ( 'refresh' === $do && $s ) {
			$r = flxai_mcpc_refresh( $id );
			$go( is_wp_error( $r ) ? array( 'edit' => $id, 'flxai_err' => rawurlencode( $r->get_error_message() ) ) : array( 'edit' => $id, 'flxai_ok' => 'refreshed' ) );
		}
		if ( 'connect' === $do && $s ) {
			$u = flxai_mcpc_oauth_start( $id );
			if ( is_wp_error( $u ) ) {
				$go( array( 'edit' => $id, 'flxai_err' => rawurlencode( $u->get_error_message() ) ) );
			}
			wp_redirect( $u ); // phpcs:ignore WordPress.Security.SafeRedirect -- yetki sunucusuna (dış adres) gidilir.
			exit;
		}
		if ( 'signout' === $do && $s ) {
			$s['oauth'] = array_diff_key( (array) $s['oauth'], array_flip( array( 'access', 'refresh', 'expires' ) ) );
			flxai_mcpc_put( $s );
			delete_transient( 'flxai_mcpc_sess_' . $id );
			$go( array( 'edit' => $id, 'flxai_ok' => 'signedout' ) );
		}
		if ( 'perms' === $do && $s ) {
			$perms = array();
			foreach ( (array) ( $post['perm'] ?? array() ) as $tool => $p ) {
				$p = sanitize_key( $p );
				if ( isset( $s['tools'][ $tool ] ) && in_array( $p, array( 'allow', 'ask', 'off' ), true ) ) {
					$perms[ (string) $tool ] = $p;
				}
			}
			$s['perms'] = $perms;
			flxai_mcpc_put( $s );
			$go( array( 'edit' => $id, 'flxai_ok' => 'saved' ) );
		}
		if ( 'save' !== $do ) {
			return;
		}
		$url = esc_url_raw( trim( (string) ( $post['url'] ?? '' ) ), array( 'http', 'https' ) );
		if ( ! flxai_mcpc_url_ok( $url ) ) {
			$go( array( 'edit' => $id ? $id : 'new', 'flxai_err' => rawurlencode( __( 'Invalid or blocked address. Use a public https address.', 'flx-ai' ) ) ) );
		}
		$new = ! $s;
		$s   = $s ? $s : flxai_mcpc_defaults();
		if ( $new ) {
			$s['id']      = flxai_mcpc_slug( (string) ( $post['name'] ?? '' ) );
			$s['owner']   = get_current_user_id();
			$s['created'] = time();
		}
		$changed_url   = $s['url'] !== $url;
		$s['name']     = sanitize_text_field( (string) ( $post['name'] ?? '' ) );
		$s['name']     = '' !== $s['name'] ? $s['name'] : (string) wp_parse_url( $url, PHP_URL_HOST );
		$s['url']      = $url;
		$s['auth']     = in_array( $post['auth'] ?? '', array( 'none', 'bearer', 'header', 'oauth' ), true ) ? $post['auth'] : 'none';
		$s['header']   = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $post['header'] ?? '' ) );
		$s['scope']    = 'shared' === ( $post['scope'] ?? '' ) ? 'shared' : 'personal';
		$s['roles']    = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $post['roles'] ?? array() ) ), array_keys( wp_roles()->get_names() ) ) );
		$s['enabled']  = empty( $post['enabled'] ) ? 0 : 1;
		$secret        = trim( (string) ( $post['secret'] ?? '' ) );
		if ( '' !== $secret ) {
			$s['secret'] = flxai_encrypt( $secret ); // Boş gönderilirse eskisi kalır.
		}
		$o              = (array) $s['oauth'];
		$cid            = sanitize_text_field( (string) ( $post['client_id'] ?? '' ) );
		if ( $changed_url ) {
			$o = array();
		}
		if ( '' !== $cid && $cid !== ( $o['client_id'] ?? '' ) ) {
			$o = array( 'client_id' => $cid );
		}
		$csec = trim( (string) ( $post['client_secret'] ?? '' ) );
		if ( '' !== $csec ) {
			$o['client_secret'] = flxai_encrypt( $csec );
		}
		$s['oauth'] = $o;
		flxai_mcpc_put( $s );
		delete_transient( 'flxai_mcpc_sess_' . $s['id'] );
		if ( 'oauth' === $s['auth'] && empty( $o['access'] ) ) {
			$go( array( 'edit' => $s['id'], 'flxai_ok' => 'needs_signin' ) );
		}
		$r = $s['enabled'] ? flxai_mcpc_refresh( $s['id'] ) : true;
		$go( is_wp_error( $r ) ? array( 'edit' => $s['id'], 'flxai_err' => rawurlencode( $r->get_error_message() ) ) : array( 'edit' => $s['id'], 'flxai_ok' => 'saved' ) );
	}
);

/** Bildirimler. */
function flxai_mcpc_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['flxai_err'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['flxai_err'] ) ) ) . '</p></div>';
	}
	$ok = sanitize_key( $_GET['flxai_ok'] ?? '' );
	$m  = array(
		'saved'        => __( 'Saved.', 'flx-ai' ),
		'deleted'      => __( 'Connection deleted.', 'flx-ai' ),
		'refreshed'    => __( 'Tool list updated.', 'flx-ai' ),
		'connected'    => __( 'Signed in. The server\'s tools are listed below.', 'flx-ai' ),
		'signedout'    => __( 'Signed out.', 'flx-ai' ),
		'needs_signin' => __( 'Saved. Now sign in with "Connect".', 'flx-ai' ),
	)[ $ok ] ?? '';
	if ( $m ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
	}
	// phpcs:enable
}

function flxai_page_connections() {
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	flxai_page_head( __( 'Connections', 'flx-ai' ), __( 'Connect external MCP servers (for example a CRM, a project tool or another site). Their tools become available in chat, scheduled tasks and agents. Only remote servers over HTTPS work here; local (stdio) servers need a computer that runs them.', 'flx-ai' ) );
	flxai_mcpc_notices();
	if ( '' !== $edit ) {
		$s = 'new' === $edit ? null : flxai_mcpc_get( $edit );
		flxai_mcpc_form( $s );
		if ( $s ) {
			flxai_mcpc_tools_card( $s );
		}
		echo '</div>';
		return;
	}
	$all = flxai_mcpc_visible();
	echo '<section class="flxai-card">';
	echo '<div class="flxai-bill-head"><h2>' . esc_html__( 'MCP servers', 'flx-ai' ) . '</h2><div class="flxai-tasks-acts">';
	echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-connections&edit=new' ) ) . '">' . esc_html__( 'Add connection', 'flx-ai' ) . '</a></div></div>';
	if ( ! $all ) {
		echo '<p class="flxai-note">' . esc_html__( 'No connections yet.', 'flx-ai' ) . '</p></section></div>';
		return;
	}
	$auth = flxai_mcpc_auth_labels();
	echo '<table class="widefat striped flxai-mcpc-list"><thead><tr><th>' . esc_html__( 'Name', 'flx-ai' ) . '</th><th>' . esc_html__( 'Address', 'flx-ai' ) . '</th><th>' . esc_html__( 'Sign-in', 'flx-ai' ) . '</th><th>' . esc_html__( 'Tools', 'flx-ai' ) . '</th><th>' . esc_html__( 'Status', 'flx-ai' ) . '</th></tr></thead><tbody>';
	foreach ( $all as $id => $s ) {
		$on    = count(
			array_filter(
				array_keys( $s['tools'] ),
				function ( $t ) use ( $s ) {
					return 'off' !== flxai_mcpc_perm( $s, $t );
				}
			)
		);
		$state = ! $s['enabled'] ? '<span class="flxai-pill">' . esc_html__( 'Off', 'flx-ai' ) . '</span>' : ( '' !== $s['error'] ? '<span class="flxai-pill is-err" title="' . esc_attr( $s['error'] ) . '">' . esc_html__( 'Error', 'flx-ai' ) . '</span>' : ( 'oauth' === $s['auth'] && empty( $s['oauth']['access'] ) ? '<span class="flxai-pill is-warn">' . esc_html__( 'Sign-in needed', 'flx-ai' ) . '</span>' : '<span class="flxai-pill is-ok">' . esc_html__( 'Connected', 'flx-ai' ) . '</span>' ) );
		echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-connections&edit=' . $id ) ) . '"><strong>' . esc_html( $s['name'] ) . '</strong></a><br><code>mcp_' . esc_html( $id ) . '_*</code>';
		echo ' <span class="flxai-note">' . esc_html( 'shared' === $s['scope'] ? __( 'Shared', 'flx-ai' ) : __( 'Personal', 'flx-ai' ) ) . '</span></td>';
		echo '<td><code>' . esc_html( $s['url'] ) . '</code></td><td>' . esc_html( $auth[ $s['auth'] ] ?? $s['auth'] ) . '</td>';
		/* translators: 1: enabled tools, 2: all tools */
		echo '<td>' . esc_html( sprintf( __( '%1$s of %2$s', 'flx-ai' ), number_format_i18n( $on ), number_format_i18n( count( $s['tools'] ) ) ) ) . '</td><td>' . wp_kses_post( $state );
		if ( $s['checked'] ) {
			echo '<br><span class="flxai-note">' . esc_html( function_exists( 'flx_time_ago' ) ? flx_time_ago( $s['checked'] ) : '' ) . '</span>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></section></div>';
}

function flxai_mcpc_auth_labels() {
	return array(
		'none'   => __( 'None', 'flx-ai' ),
		'bearer' => __( 'API key / token (Bearer)', 'flx-ai' ),
		'header' => __( 'Custom header', 'flx-ai' ),
		'oauth'  => __( 'Sign in (OAuth 2.1)', 'flx-ai' ),
	);
}

/** Ekle / düzenle formu. */
function flxai_mcpc_form( $s ) {
	$d = $s ? $s : flxai_mcpc_defaults();
	echo '<section class="flxai-card flxai-mcpc-form"><h2>' . esc_html( $s ? $s['name'] : __( 'Add connection', 'flx-ai' ) ) . '</h2>';
	if ( $s && $s['info'] ) {
		/* translators: 1: server name, 2: version, 3: protocol */
		echo '<p class="flxai-note">' . esc_html( sprintf( __( 'Server: %1$s %2$s · protocol %3$s', 'flx-ai' ), $s['info']['name'] ?? '', $s['info']['version'] ?? '', $s['info']['protocol'] ?? '' ) ) . '</p>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'flxai_mcpc', 'flxai_mcpc_nonce' );
	echo '<input type="hidden" name="id" value="' . esc_attr( $d['id'] ) . '"><table class="form-table" role="presentation">';
	echo '<tr><th><label for="flxai-mcpc-name">' . esc_html__( 'Name', 'flx-ai' ) . '</label></th><td><input id="flxai-mcpc-name" class="regular-text" name="name" value="' . esc_attr( $d['name'] ) . '" placeholder="Linear"></td></tr>';
	echo '<tr><th><label for="flxai-mcpc-url">' . esc_html__( 'Server address', 'flx-ai' ) . '</label></th><td><input id="flxai-mcpc-url" class="large-text code" name="url" type="url" required value="' . esc_attr( $d['url'] ) . '" placeholder="https://mcp.example.com/mcp"><p class="description">' . esc_html__( 'The Streamable HTTP address the provider gives for MCP clients.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Sign-in', 'flx-ai' ) . '</th><td><fieldset class="flxai-mcpc-auth">';
	foreach ( flxai_mcpc_auth_labels() as $k => $label ) {
		echo '<label class="flxai-check"><input type="radio" name="auth" value="' . esc_attr( $k ) . '"' . checked( $d['auth'], $k, false ) . '> ' . esc_html( $label ) . '</label><br>';
	}
	echo '</fieldset></td></tr>';
	$has = '' !== (string) $d['secret'];
	echo '<tr class="flxai-mcpc-if" data-auth="header"><th><label for="flxai-mcpc-header">' . esc_html__( 'Header name', 'flx-ai' ) . '</label></th><td><input id="flxai-mcpc-header" class="regular-text code" name="header" value="' . esc_attr( $d['header'] ) . '" placeholder="X-API-Key"></td></tr>';
	echo '<tr class="flxai-mcpc-if" data-auth="bearer header"><th><label for="flxai-mcpc-secret">' . esc_html__( 'Key / token', 'flx-ai' ) . '</label></th><td><input id="flxai-mcpc-secret" class="regular-text code" name="secret" type="password" autocomplete="off" value="" placeholder="' . esc_attr( $has ? __( 'Saved (leave empty to keep)', 'flx-ai' ) : '' ) . '"><p class="description">' . esc_html__( 'Stored encrypted; never shown to the AI.', 'flx-ai' ) . '</p></td></tr>';
	$o = (array) $d['oauth'];
	echo '<tr class="flxai-mcpc-if" data-auth="oauth"><th>' . esc_html__( 'OAuth client', 'flx-ai' ) . '</th><td><input class="regular-text code" name="client_id" value="' . esc_attr( empty( $o['registered'] ) ? (string) ( $o['client_id'] ?? '' ) : '' ) . '" placeholder="' . esc_attr__( 'Client ID (optional)', 'flx-ai' ) . '"> <input class="regular-text code" name="client_secret" type="password" autocomplete="off" placeholder="' . esc_attr( ! empty( $o['client_secret'] ) && empty( $o['registered'] ) ? __( 'Saved (leave empty to keep)', 'flx-ai' ) : __( 'Client secret (optional)', 'flx-ai' ) ) . '">';
	echo '<p class="description">' . esc_html__( 'Leave empty: Flux AI registers itself automatically when the server allows it. Otherwise create an OAuth app at the provider with this redirect address:', 'flx-ai' ) . ' <code>' . esc_html( flxai_mcpc_oauth_callback() ) . '</code></p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Who can use it', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="radio" name="scope" value="personal"' . checked( $d['scope'], 'personal', false ) . '> ' . esc_html__( 'Only me (personal)', 'flx-ai' ) . '</label><br><label class="flxai-check"><input type="radio" name="scope" value="shared"' . checked( $d['scope'], 'shared', false ) . '> ' . esc_html__( 'Shared: administrators and these roles', 'flx-ai' ) . '</label><div class="flxai-mcpc-roles">';
	foreach ( wp_roles()->get_names() as $rk => $rn ) {
		if ( 'administrator' === $rk ) {
			continue;
		}
		echo '<label class="flxai-check"><input type="checkbox" name="roles[]" value="' . esc_attr( $rk ) . '"' . checked( in_array( $rk, (array) $d['roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $rn ) ) . '</label> ';
	}
	echo '</div><p class="description">' . esc_html__( 'Agents and scheduled tasks run as their owner, so they can use the connections that user can use.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Active', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $d['enabled'] ), true, false ) . '> ' . esc_html__( 'Offer this server\'s tools', 'flx-ai' ) . '</label></td></tr>';
	echo '</table><p class="submit"><button type="submit" name="do" value="save" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> ';
	if ( $s ) {
		if ( 'oauth' === $s['auth'] ) {
			echo '<button type="submit" name="do" value="connect" class="button">' . esc_html( empty( $o['access'] ) ? __( 'Connect', 'flx-ai' ) : __( 'Sign in again', 'flx-ai' ) ) . '</button> ';
			if ( ! empty( $o['access'] ) ) {
				echo '<button type="submit" name="do" value="signout" class="button">' . esc_html__( 'Sign out', 'flx-ai' ) . '</button> ';
			}
		}
		echo '<button type="submit" name="do" value="refresh" class="button">' . esc_html__( 'Test and update tools', 'flx-ai' ) . '</button> ';
		printf(
			'<button type="submit" name="do" value="delete" class="button button-link-delete" formnovalidate data-flx-confirm="%1$s" data-flx-confirm-ok="%2$s" data-flx-confirm-danger>%2$s</button>',
			/* translators: %s: connection name */
			esc_attr( sprintf( __( 'Delete the connection "%s"? Its tools disappear from chats, tasks and agents.', 'flx-ai' ), $s['name'] ) ),
			esc_html__( 'Delete', 'flx-ai' )
		);
	}
	echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-connections' ) ) . '">' . esc_html__( 'Back', 'flx-ai' ) . '</a></p></form>';
	echo '<script>(function(){var f=document.querySelector(".flxai-mcpc-form form");if(!f)return;function u(){var v=(f.querySelector("input[name=auth]:checked")||{}).value;f.querySelectorAll(".flxai-mcpc-if").forEach(function(r){r.hidden=(" "+r.getAttribute("data-auth")+" ").indexOf(" "+v+" ")<0;});var sh=(f.querySelector("input[name=scope]:checked")||{}).value==="shared";f.querySelector(".flxai-mcpc-roles").hidden=!sh;}f.addEventListener("change",u);u();})();</script>';
	echo '</section>';
}

/** Araç listesi ve izinleri. */
function flxai_mcpc_tools_card( array $s ) {
	echo '<section class="flxai-card flxai-mcpc-tools"><h2>' . esc_html__( 'Tools', 'flx-ai' ) . '</h2>';
	if ( '' !== $s['error'] ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $s['error'] ) . '</p></div>';
	}
	if ( ! $s['tools'] ) {
		echo '<p class="flxai-note">' . esc_html__( 'No tools listed yet. Save the connection (and sign in) to load them.', 'flx-ai' ) . '</p></section>';
		return;
	}
	echo '<p class="flxai-note">' . esc_html__( 'Allowed: runs without asking (only tools the server marks read-only). Ask first: shows an approval card. Off: never offered. Tools that change data always ask.', 'flx-ai' ) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'flxai_mcpc', 'flxai_mcpc_nonce' );
	echo '<input type="hidden" name="id" value="' . esc_attr( $s['id'] ) . '"><input type="hidden" name="do" value="perms">';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Tool', 'flx-ai' ) . '</th><th>' . esc_html__( 'Allowed', 'flx-ai' ) . '</th><th>' . esc_html__( 'Ask first', 'flx-ai' ) . '</th><th>' . esc_html__( 'Off', 'flx-ai' ) . '</th></tr></thead><tbody>';
	foreach ( $s['tools'] as $name => $t ) {
		$p = flxai_mcpc_perm( $s, $name );
		echo '<tr><td><strong>' . esc_html( $t['title'] ) . '</strong> <code>' . esc_html( flxai_mcpc_tool_name( $s['id'], $name ) ) . '</code>';
		echo $t['readonly'] ? ' <span class="flxai-pill is-ok">' . esc_html__( 'Read-only', 'flx-ai' ) . '</span>' : ( $t['destructive'] ? ' <span class="flxai-pill is-err">' . esc_html__( 'Destructive', 'flx-ai' ) . '</span>' : ' <span class="flxai-pill is-warn">' . esc_html__( 'Changes data', 'flx-ai' ) . '</span>' );
		echo '<br><span class="flxai-note">' . esc_html( wp_html_excerpt( $t['description'], 220, '…' ) ) . '</span></td>';
		foreach ( array( 'allow', 'ask', 'off' ) as $k ) {
			$dis = 'allow' === $k && ! $t['readonly'];
			echo '<td><input type="radio" name="perm[' . esc_attr( $name ) . ']" value="' . esc_attr( $k ) . '"' . checked( $p, $k, false ) . disabled( $dis, true, false ) . ' aria-label="' . esc_attr( $k ) . '"></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table><p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save permissions', 'flx-ai' ) . '</button></p></form></section>';
}
