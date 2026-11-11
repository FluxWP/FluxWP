<?php
/**
 * Bağlantılar: WhatsApp (Evolution) ve SMS (Netgsm); Flux Certificates'dan taşıma.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_connections() {
	$edit = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
	flxi_page_head( __( 'Connections', 'flx-mail' ), __( 'WhatsApp and SMS accounts in one place. Flux Certificates, Flux Chat and other plugins use these connections. Email settings are on the Settings tab.', 'flx-mail' ) );
	$types = flxi_connection_types();
	$tests = (array) get_option( 'flxi_conn_tests', array() );

	if ( '' !== $edit ) {
		$conn = 'new' === $edit ? null : flxi_get_connection( (int) $edit );
		$type = $conn ? $conn['type'] : sanitize_key( wp_unslash( $_GET['type'] ?? 'evolution' ) ); // phpcs:ignore
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'evolution';
		}
		$def = $types[ $type ];
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-card flxi-narrow" autocomplete="off">';
		wp_nonce_field( 'flxi_save_connection' );
		echo '<input type="hidden" name="action" value="flxi_save_connection"><input type="hidden" name="id" value="' . (int) ( $conn['id'] ?? 0 ) . '"><input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
		echo '<h2 class="flxi-card__title">' . esc_html( $def['label'] ) . '</h2><p class="flxi-muted">' . esc_html( $def['help'] ) . '</p>';
		if ( 'wacloud' === $type ) {
			flxi_wacloud_limits_note();
		}
		echo '<label class="flxi-field"><span>' . esc_html__( 'First name', 'flx-mail' ) . '</span><input type="text" name="name" value="' . esc_attr( $conn['name'] ?? $def['label'] ) . '"></label>';
		$evo     = flxi_is_evo_type( $type );
		$servers = array();
		if ( $evo && ! $conn ) {
			// Aynı sunucuya yeni numara: adres + API key mevcut bağlantıdan alınır (key tarayıcıya gelmez).
			foreach ( flxi_list_connections( 'whatsapp' ) as $o ) {
				$k = untrailingslashit( (string) $o['config']['base_url'] );
				if ( $o['type'] === $type && $k && ! isset( $servers[ $k ] ) ) {
					$servers[ $k ] = $o;
				}
			}
		}
		if ( $servers ) {
			echo '<label class="flxi-field"><span>' . esc_html__( 'Server', 'flx-mail' ) . '</span><select name="copy_from" data-wa-copy><option value="">' . esc_html__( 'I\'ll enter new server details', 'flx-mail' ) . '</option>';
			foreach ( $servers as $k => $o ) {
				/* translators: 1: bağlantı adı, 2: adres */
				printf( '<option value="%d" selected>%s</option>', (int) $o['id'], esc_html( sprintf( __( 'Same server: %1$s (%2$s)', 'flx-mail' ), $o['name'], wp_parse_url( $k, PHP_URL_HOST ) ) ) );
			}
			echo '</select><small>' . esc_html__( 'When adding a second number to the same server, the address and API key aren\'t entered again; just type the new instance name.', 'flx-mail' ) . '</small></label>';
		}
		foreach ( $def['fields'] as $f ) {
			$val = $conn['config'][ $f['key'] ] ?? ( $f['default'] ?? '' );
			$nm  = 'cfg[' . $f['key'] . ']';
			if ( 'checkbox' === $f['type'] ) {
				echo '<div class="flxi-field">' . flxi_switch( $nm, $val, $f['label'] ) . '</div>'; // phpcs:ignore
				continue;
			}
			echo '<label class="flxi-field"' . ( $evo && in_array( $f['key'], array( 'base_url', 'api_key' ), true ) ? ' data-wa-server' : '' ) . '><span>' . esc_html( $f['label'] ) . ( ! empty( $f['required'] ) ? ' <em aria-hidden="true">*</em>' : '' ) . '</span>';
			if ( 'select' === $f['type'] ) {
				echo '<select name="' . esc_attr( $nm ) . '">';
				foreach ( $f['options'] as $ov => $ol ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $ov ), selected( (string) $val, (string) $ov, false ), esc_html( $ol ) );
				}
				echo '</select>';
			} elseif ( 'password' === $f['type'] ) {
				echo '<input type="password" name="' . esc_attr( $nm ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $val ? flxi_mask( $val ) . ' · ' . __( 'type to change', 'flx-mail' ) : '' ) . '"' . ( ! empty( $f['required'] ) && ! $val ? ' required' : '' ) . '>';
			} else {
				echo '<input type="' . ( 'number' === $f['type'] ? 'number' : ( 'url' === $f['type'] ? 'url' : 'text' ) ) . '" name="' . esc_attr( $nm ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $f['placeholder'] ?? '' ) . '"' . ( ! empty( $f['required'] ) ? ' required' : '' ) . '>';
			}
			if ( ! empty( $f['help'] ) ) {
				echo '<small>' . esc_html( $f['help'] ) . '</small>';
			}
			echo '</label>';
		}
		echo '<div class="flxi-field">' . flxi_switch( 'active', $conn ? $conn['active'] : 1, __( 'Active', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
		echo '<div class="flxi-actions"><button class="button button-primary button-large">' . esc_html__( 'Save', 'flx-mail' ) . '</button> <a class="button button-large" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar' ) ) . '">' . esc_html__( 'Cancel', 'flx-mail' ) . '</a></div></form>';
		flxi_page_foot();
		return;
	}

	foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
		$list = flxi_list_connections( $ch );
		echo '<section class="flxi-card"><div class="flxi-card__head"><h2 class="flxi-card__title">' . esc_html( flxi_channels()[ $ch ] ) . '</h2><div class="flxi-card__tools">';
		foreach ( $types as $tp => $td ) {
			if ( $ch === $td['channel'] ) {
				echo '<a class="button" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => 'new', 'type' => $tp ) ) ) . '">+ ' . esc_html( trim( preg_replace( '/^(WhatsApp|SMS)\s*·\s*/u', '', $td['label'] ) ) ) . '</a> ';
			}
		}
		echo '</div></div>';
		if ( 'whatsapp' === $ch && $list ) {
			echo '<p class="flxi-muted flxi-card__note">' . esc_html__( 'Each row is one number. Press "Connect" to link a number, see its status or reconnect it; no separate panel needed.', 'flx-mail' ) . '</p>';
		}
		if ( ! $list ) {
			echo '<p class="flxi-muted">' . esc_html__( 'No connections yet.', 'flx-mail' ) . '</p>';
		}
		echo '<ul class="flxi-list">';
		foreach ( $list as $c ) {
			$t = $tests[ $c['id'] ] ?? null;
			echo '<li class="flxi-row flxi-row--conn' . ( $c['active'] ? '' : ' is-off' ) . '"><div class="flxi-row__main"><strong class="flxi-row__title">' . esc_html( $c['name'] ) . '</strong> ';
			if ( $c['is_default'] ) {
				echo flxi_badge( __( 'Default', 'flx-mail' ), 'green' ); // phpcs:ignore
			}
			if ( ! $c['active'] ) {
				echo flxi_badge( __( 'Disabled', 'flx-mail' ), 'off' ); // phpcs:ignore
			}
			$evo   = flxi_is_evo_type( $c['type'] );
			$cloud = 'wacloud' === $c['type'];
			$st = array( 'state' => '' );
			if ( $evo || $cloud ) {
				$st = flxi_wa_cached_state( $c['id'] );
				echo '<span class="flxi-wa-state" data-wa-state="' . (int) $c['id'] . '" data-state="' . esc_attr( $st['state'] ) . '">' . esc_html( flxi_wa_state_label( $st ) ) . '</span>';
			}
			$detail = $cloud ? 'Phone ID ' . ( $c['config']['phone_id'] ?? '' ) : ( $evo ? wp_parse_url( (string) ( $c['config']['base_url'] ?? '' ), PHP_URL_HOST ) . ' · ' . ( $c['config']['instance'] ?? '' ) : ( $c['config']['header'] ?? '' ) . ' · ' . ( $c['config']['username'] ?? '' ) );
			if ( $cloud && '' === (string) ( $c['config']['app_secret'] ?? '' ) ) {
				echo ' ' . flxi_badge( __( 'App Secret missing · webhook off', 'flx-mail' ), 'amber' ); // phpcs:ignore
			}
			echo '<div class="flxi-row__desc">' . esc_html( $types[ $c['type'] ]['label'] ?? $c['type'] ) . ' · ' . esc_html( $detail );
			if ( $t ) {
				echo ' · <span class="' . ( $t['ok'] ? 'flxi-ok' : 'flxi-err' ) . '">' . esc_html( $t['ok'] ? __( 'Test successful', 'flx-mail' ) : $t['error'] ) . '</span>';
			}
			if ( $c['origin'] ) {
				echo ' · <span class="flxi-muted">' . esc_html__( 'Migrated from Flux Certificates', 'flx-mail' ) . '</span>';
			}
			echo '</div></div><div class="flxi-row__actions flxi-conn-test">';
			if ( $cloud ) {
				echo '<button type="button" class="button button-primary" data-wac-setup="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '">' . esc_html__( 'Setup', 'flx-mail' ) . '</button> ';
			}
			if ( $evo ) {
				// Bağlıyken gizli; durum değişince JS açar.
				echo '<button type="button" class="button button-primary flxi-wa-connect-main" data-wa-connect="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '"' . ( 'open' === $st['state'] ? ' hidden' : '' ) . '>' . esc_html__( 'Connect', 'flx-mail' ) . '</button> ';
			}
			echo '<button type="button" class="button' . ( ( $evo && 'open' === $st['state'] ) || ! $evo ? ' button-primary' : '' ) . ' flxi-test-open" data-conn-test="' . (int) $c['id'] . '" data-type="' . esc_attr( $c['type'] ) . '" data-label="' . esc_attr( $types[ $c['type'] ]['label'] ?? $c['type'] ) . '" data-name="' . esc_attr( $c['name'] ) . '" data-scenarios="' . esc_attr( implode( ',', flxi_test_scenarios( $c['type'] ) ) ) . '">' . esc_html__( 'Test', 'flx-mail' ) . '</button>';
			echo ' <a class="button" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => $c['id'] ) ) ) . '">' . esc_html__( 'Edit', 'flx-mail' ) . '</a>';
			echo '<details class="flxi-more"><summary class="button" aria-label="' . esc_attr__( 'More actions', 'flx-mail' ) . '">⋯</summary><div class="flxi-more__menu">';
			if ( $evo ) {
				echo '<button type="button" class="button-link" data-wa-connect="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '">' . esc_html__( 'Status / reconnect (QR)', 'flx-mail' ) . '</button>';
				echo '<button type="button" class="button-link" data-wa-restart="' . (int) $c['id'] . '">' . esc_html__( 'Restart', 'flx-mail' ) . '</button>';
				echo '<button type="button" class="button-link" data-wa-logout="' . (int) $c['id'] . '">' . esc_html__( 'Log the number out', 'flx-mail' ) . '</button>';
				if ( 'flxwpapi' === $c['type'] ) {
					echo '<button type="button" class="button-link" data-wa-server="' . (int) $c['id'] . '">' . esc_html__( 'Server settings', 'flx-mail' ) . '</button>';
				}
			}
			if ( ! $c['is_default'] ) {
				echo flxi_post_button( 'flxi_default_connection', array( 'id' => $c['id'] ), __( 'Make default', 'flx-mail' ) ); // phpcs:ignore
			}
			echo flxi_post_button( 'flxi_delete_connection', array( 'id' => $c['id'] ), $evo ? __( 'Delete (number stays on the server)', 'flx-mail' ) : __( 'Delete', 'flx-mail' ), __( 'Delete this connection?', 'flx-mail' ) ); // phpcs:ignore
			if ( $evo ) {
				echo flxi_post_button( 'flxi_delete_connection', array( 'id' => $c['id'], 'remote' => 1 ), __( 'Also delete from the server', 'flx-mail' ), __( 'Also delete the number from the server? The session, message history and contacts are deleted on the server; adding it again needs a QR scan.', 'flx-mail' ), 'button-link flxi-danger' ); // phpcs:ignore
			}
			echo '</div></details><span class="flxi-status" aria-live="polite"></span></div></li>';
		}
		echo '</ul></section>';
	}

	flxi_wa_dialogs();

	if ( flxi_flxs_active() ) {
		echo '<p class="flxi-muted">' . esc_html__( 'Flux Certificates sends WhatsApp and SMS through the active connections here; certificate emails use the corporate template.', 'flx-mail' ) . '</p>';
	}
	flxi_page_foot();
}

/** Tek düğmelik POST formu. */
function flxi_post_button( $action, array $fields, $label, $confirm = '', $class = 'button-link' ) {
	$o  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-inline-form">';
	$o .= wp_nonce_field( $action, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	foreach ( $fields as $k => $v ) {
		$o .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$o .= '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' onclick="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
	return $o;
}

add_action(
	'admin_post_flxi_save_connection',
	function () {
		flxi_check( 'flxi_save_connection' );
		$id   = (int) ( $_POST['id'] ?? 0 );
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$cfg  = (array) wp_unslash( $_POST['cfg'] ?? array() ); // phpcs:ignore
		$from = $id ? null : flxi_get_connection( (int) ( $_POST['copy_from'] ?? 0 ) );
		if ( $from && $from['type'] === $type ) {
			$cfg['base_url'] = $from['config']['base_url'] ?? '';
			$cfg['api_key']  = $from['config']['api_key'] ?? '';
		}
		$res = flxi_save_connection( $id, $type, sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), $cfg, ! empty( $_POST['active'] ) );
		if ( is_wp_error( $res ) ) {
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => $id ? $id : 'new', 'type' => $type ) ), '', $res->get_error_message() );
		}
		if ( ! $id && 'wacloud' === $type ) {
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'wac_setup' => $res ) ), __( 'Connection saved. Now enter the webhook address in your Meta app.', 'flx-mail' ) );
		}
		if ( ! $id && flxi_is_evo_type( $type ) ) {
			// Yeni numara: liste açılınca QR penceresi kendiliğinden gelir.
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'wa_connect' => $res ) ), __( 'Connection saved. On the phone, scan the QR in WhatsApp › Linked devices › Link a device.', 'flx-mail' ) );
		}
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Connection saved. Try it with "Test".', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_delete_connection',
	function () {
		flxi_check( 'flxi_delete_connection' );
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! empty( $_POST['remote'] ) ) {
			$r = flxi_wa_delete_remote( $id );
			if ( is_wp_error( $r ) ) {
				flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), '', $r->get_error_message() );
			}
		}
		flxi_delete_connection( $id );
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Connection deleted.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_default_connection',
	function () {
		flxi_check( 'flxi_default_connection' );
		flxi_set_default_connection( (int) ( $_POST['id'] ?? 0 ) );
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Default connection changed.', 'flx-mail' ) );
	}
);


/** Durum rozeti metni. */
function flxi_wa_state_label( array $st ) {
	switch ( $st['state'] ) {
		case 'open':
			return __( 'Connected', 'flx-mail' ) . ( $st['me'] ? ' · ' . $st['me'] : '' );
		case 'connecting':
			return __( 'Waiting for QR', 'flx-mail' );
		case 'close':
			return __( 'Not connected', 'flx-mail' );
		case 'missing':
			return __( 'Not on the server', 'flx-mail' );
		case 'error':
			return __( 'Server unreachable', 'flx-mail' );
	}
	return __( 'Status unknown', 'flx-mail' );
}

/** QR ve sunucu ayarları pencereleri (içeriği admin.js doldurur). */
function flxi_wa_dialogs() {
	$close = '<button type="button" class="button-link" data-close aria-label="' . esc_attr__( 'Close', 'flx-mail' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog" id="flxi-wa-dialog" aria-labelledby="flxi-wa-title"><div class="flxi-dialog__head"><h2 id="flxi-wa-title"></h2>' . $close . '</div><div class="flxi-wa" data-wa-body>'; // phpcs:ignore
	echo '<p class="flxi-wa__state" data-wa-msg aria-live="polite"></p>';
	echo '<div class="flxi-wa__qr" data-wa-qrbox><img data-wa-qr alt="' . esc_attr__( 'WhatsApp QR code', 'flx-mail' ) . '" width="264" height="264" hidden><div class="flxi-wa__code" data-wa-code hidden></div></div>';
	echo '<ol class="flxi-wa__steps" data-wa-steps><li>' . esc_html__( 'Open WhatsApp on the phone.', 'flx-mail' ) . '</li><li>' . esc_html__( 'Settings › Linked devices › Link a device.', 'flx-mail' ) . '</li><li>' . esc_html__( 'Scan this QR. The code refreshes by itself.', 'flx-mail' ) . '</li></ol>';
	echo '<details class="flxi-wa__pair" data-wa-pair><summary>' . esc_html__( 'Can\'t scan the QR: get a code with the phone number', 'flx-mail' ) . '</summary><div class="flxi-wa__pairrow"><input type="tel" data-wa-number placeholder="905301234567" aria-label="' . esc_attr__( 'Phone number', 'flx-mail' ) . '"><button type="button" class="button" data-wa-paircode>' . esc_html__( 'Get code', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'Type this code in WhatsApp › Linked devices › Link a device › "Link with phone number instead".', 'flx-mail' ) . '</small></details>';
	echo '</div></dialog>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog flxi-wac-dialog" id="flxi-wac-setup" aria-labelledby="flxi-wac-title"><div class="flxi-dialog__head"><h2 id="flxi-wac-title"></h2>' . $close . '</div><div class="flxi-wa" data-wac-body></div><div class="flxi-wa flxi-wac-limits">'; // phpcs:ignore
	flxi_wacloud_limits_note();
	echo '</div></dialog>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog flxi-test-dialog" id="flxi-test-dialog" aria-labelledby="flxi-test-title"><div class="flxi-dialog__head"><h2 id="flxi-test-title"></h2>' . $close . '</div><div class="flxi-wa flxi-test" data-test-body></div></dialog>'; // phpcs:ignore
	echo '<dialog class="flxi-dialog flxi-wa-dialog" id="flxi-wa-server" aria-labelledby="flxi-wa-server-title"><div class="flxi-dialog__head"><h2 id="flxi-wa-server-title">' . esc_html__( 'Flux WPAPI server', 'flx-mail' ) . '</h2>' . $close . '</div><div class="flxi-wa" data-ws-body></div></dialog>'; // phpcs:ignore
}

/** Cloud API'de farklı/eksik olanlar (form ve kurulum penceresinde). */
function flxi_wacloud_limits_note() {
	echo '<div class="flxi-callout"><strong>' . esc_html__( 'What\'s different in Cloud API', 'flx-mail' ) . '</strong><ul>';
	echo '<li>' . esc_html__( '24-hour rule: if the person hasn\'t written in the last 24 hours, only a Meta-approved template can be sent.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'No group messages: Call Center\'s team group mirror doesn\'t work on this line.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Sent messages can\'t be edited; past chats can\'t be imported.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( '"Typing…" only shows while replying to an incoming message.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Cost: template messages and replies beyond 1000 a month are charged by Meta.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'No ban risk; the number can stay in the WhatsApp Business app on the phone too (coexistence).', 'flx-mail' ) . '</li>';
	echo '</ul></div>';
}
