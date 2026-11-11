<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxc_save_settings', 'flxc_save_settings' );

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! isset( $_GET['page'] ) || 'flx-chat-ayarlar' !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		flxc_register_model_picker();
		wp_enqueue_style( 'flxc-settings', FLXC_URL . 'assets/css/settings.css', array( 'flxc-mp' ), FLXC_VERSION );
		wp_enqueue_script( 'flxc-settings', FLXC_URL . 'assets/js/settings.js', array( 'flxc-mp' ), FLXC_VERSION, true );
		wp_localize_script(
			'flxc-settings',
			'FLXC_SET',
			array(
				'rest'  => trailingslashit( rest_url( 'flx-chat/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'testing'     => __( 'Checking…', 'flx-chat' ),
					'open'        => __( 'Connected ✓', 'flx-chat' ),
					'notOpen'     => __( 'Not connected (state: %s). Connect with QR.', 'flx-chat' ),
					'whOk'        => __( 'Webhook points to this site ✓', 'flx-chat' ),
					/* translators: %s: Evolution API version */
					'evoVer'      => __( 'Evolution API version: %s', 'flx-chat' ),
					'evoOld'      => __( '(old: replies to hidden-number (@lid) contacts may fail, update to 2.3 or later)', 'flx-chat' ),
					'whMissing'   => __( 'The webhook is not pointed at this site. Press "Set up webhook".', 'flx-chat' ),
					'whSet'       => __( 'Webhook set up ✓ Incoming messages will now arrive here.', 'flx-chat' ),
					'importing'   => __( 'Importing recent chats, this may take a while…', 'flx-chat' ),
					/* translators: 1: chats, 2: messages */
					'stored'      => __( 'Messages stored in Evolution: %s', 'flx-chat' ),
					'noStore'     => __( 'Evolution does not store messages, so older messages cannot be loaded. On the Evolution server set DATABASE_SAVE_DATA_NEW_MESSAGE and DATABASE_SAVE_DATA_HISTORIC to true; for older conversations, keep "Sync full history" on in the instance settings when you reconnect the line with QR.', 'flx-chat' ),
					'imported'    => __( '%1$s chats, %2$s messages imported ✓', 'flx-chat' ),
					'qrScan'      => __( 'Scan this code via WhatsApp › Linked devices › Link a device.', 'flx-chat' ),
					'pairCode'    => __( 'Pairing code:', 'flx-chat' ),
					'alreadyOpen' => __( 'This line is already connected ✓', 'flx-chat' ),
					'saveFirst'   => __( 'Save the settings first.', 'flx-chat' ),
					'copied'      => __( 'Copied', 'flx-chat' ),
					'error'       => __( 'Error:', 'flx-chat' ),
					'confirmDel'  => __( 'Delete this connection? Chat history is kept.', 'flx-chat' ),
					'aiTesting'   => __( 'Asking the AI…', 'flx-chat' ),
					/* translators: 1: reply, 2: ms */
					'aiOk'        => __( 'Working ✓ (%2$s ms): %1$s', 'flx-chat' ),
					'saveKey'     => __( 'Save the key and model first.', 'flx-chat' ),
				),
			)
		);
	}
);

function flxc_settings_tabs() {
	return array(
		'baglantilar' => __( 'Connections', 'flx-chat' ),
		'genel'       => __( 'General', 'flx-chat' ),
		'otomatik'    => __( 'Auto replies', 'flx-chat' ),
		'etiketler'   => __( 'Labels', 'flx-chat' ),
		'kayitlar'    => __( 'Records', 'flx-chat' ),
		'ai'          => __( 'AI and assistant', 'flx-chat' ),
		'kontrol'     => __( 'System check', 'flx-chat' ),
	);
}

function flxc_render_settings() {
	if ( ! flxc_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'flx-chat' ) );
	}
	$tabs = flxc_settings_tabs();
	$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'baglantilar'; // phpcs:ignore
	$s    = flxc_settings();
	?>
	<div class="wrap flxcs">
		<header class="flxcs-head">
			<div>
				<h1><?php esc_html_e( 'Call Center Settings', 'flx-chat' ); ?></h1>
				<p><?php esc_html_e( 'Connect your WhatsApp line, manage auto replies and labels.', 'flx-chat' ); ?></p>
			</div>
			<a class="flxcs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat' ) ); ?>"><?php esc_html_e( 'Open chats', 'flx-chat' ); ?> →</a>
		</header>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-ok"><?php esc_html_e( 'Settings saved.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['err'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-err"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<nav class="flxcs-tabs">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=' . $k ) ); ?>" class="<?php echo $k === $tab ? 'is-on' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxcs-form">
			<input type="hidden" name="action" value="flxc_save_settings">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'flxc_save_settings' ); ?>
			<?php call_user_func( 'flxc_settings_tab_' . $tab, $s ); ?>
			<?php if ( ! in_array( $tab, array( 'ai', 'kontrol' ), true ) ) : ?>
				<div class="flxcs-save"><button type="submit" class="flxcs-btn flxcs-btn-primary"><?php esc_html_e( 'Save', 'flx-chat' ); ?></button></div>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

function flxc_settings_tab_kontrol( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'System check', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Connections and settings the call center needs. Saved status is shown; use the "test" buttons on each tab for a live check. The same checks also appear with the other plugins on the theme\'s system-check screen.', 'flx-chat' ); ?></p>
		<?php flxc_render_system_checks( flxc_system_checks() ); ?>
	</section>
	<?php
}

function flxc_settings_tab_baglantilar( $s ) {
	$flxs   = flxc_flxs_evolution_providers();
	$conns = flxc_connections();
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'WhatsApp lines', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc">
			<?php if ( flxc_flxi_on() ) : ?>
				<?php esc_html_e( 'Messages are sent and received through Evolution API. WhatsApp connections are managed in Flux Mail; choose which one to use here. The URL and API key stay there, so you do not need to update them here when they change.', 'flx-chat' ); ?>
				<a href="<?php echo esc_url( flxc_flxi_connections_url() ); ?>"><?php esc_html_e( 'Communication › Connections', 'flx-chat' ); ?> →</a>
			<?php else : ?>
				<?php esc_html_e( 'Messages are sent and received via Evolution API. If an Evolution provider is set up in Flux Certificates, you can pick the same connection; the URL and API key stay there.', 'flx-chat' ); ?>
			<?php endif; ?>
		</p>
		<div class="flxcs-conns" id="flxcs-conns">
			<?php
			$i = 0;
			foreach ( $conns as $c ) {
				flxc_settings_conn_row( $i++, $c, $flxs );
			}
			?>
		</div>
		<?php
		$flxi = flxc_flxi_whatsapp_choices();
		if ( $flxi ) {
			$tpl = array( 'source' => 'flxi', 'provider_id' => (int) $flxi[0]['id'] );
		} elseif ( $flxs && ! flxc_flxi_on() ) {
			$tpl = array( 'source' => 'flxs', 'provider_id' => (int) $flxs[0]['id'] );
		} else {
			$tpl = array( 'source' => 'own' );
		}
		?>
		<template id="flxcs-conn-tpl"><?php flxc_settings_conn_row( '__i__', flxc_new_connection( array( 'id' => '', 'secret' => '' ) + $tpl ), $flxs ); ?></template>
		<button type="button" class="flxcs-btn" id="flxcs-add-conn">+ <?php esc_html_e( 'Add line', 'flx-chat' ); ?></button>
	</section>
	<section class="flxcs-card flxcs-steps">
		<h2><?php esc_html_e( 'Setup steps', 'flx-chat' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Add the line and save.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( 'Use "Test connection" to see that Evolution is reachable. If the line is not linked, choose "Connect with QR" and scan it from the phone.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( 'Press "Set up webhook". Incoming messages, and messages sent from the phone and from Flux Certificates, then appear here live.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( 'Optionally bring in past messages with "Import recent chats".', 'flx-chat' ); ?></li>
		</ol>
	</section>
	<?php
}

/** Hattın "Bağlantı bilgisi" seçenekleri: Flux Mail bağlantıları, (Flux Mail yoksa) Flux Certificates sağlayıcıları, ayrı bilgi. */
function flxc_connection_source_choices( $c, $flxs ) {
	$out = array();
	$states = array(
		'open'       => __( 'connected', 'flx-chat' ),
		'connecting' => __( 'connecting', 'flx-chat' ),
		'close'      => __( 'not connected', 'flx-chat' ),
		'missing'    => __( 'not on server', 'flx-chat' ),
		'error'      => __( 'unreachable', 'flx-chat' ),
	);
	foreach ( flxc_flxi_whatsapp_choices() as $x ) {
		// 1.34.0: Flux Mail 1.9.0 satırlarında tür (evolution | flxwpapi) ve önbellekli durum gelir.
		$type  = (string) ( $x['type'] ?? '' );
		$extra = array_filter(
			array(
				'evolution' === $type ? 'Evolution API' : ( in_array( $type, array( 'flxwpapi', 'flx-wpapi' ), true ) ? 'Flux WPAPI' : ( flxc_wacloud_type( $type ) ? 'Cloud API' : '' ) ),
				(string) ( $x['me'] ?? '' ),
				$states[ (string) ( $x['state'] ?? '' ) ] ?? '',
			)
		);
		/* translators: %s: connection name */
		$out[ 'flxi:' . (int) $x['id'] ] = sprintf( __( 'Flux Mail connection: %s', 'flx-chat' ), $x['name'] ) . ( $extra ? ' (' . implode( ' · ', $extra ) . ')' : '' );
	}
	if ( 'flxi' === $c['source'] && ! isset( $out[ 'flxi:' . (int) $c['provider_id'] ] ) && flxc_flxi_on() ) {
		/* translators: %d: connection id */
		$out[ 'flxi:' . (int) $c['provider_id'] ] = sprintf( __( 'Flux Mail connection #%d (not found or disabled)', 'flx-chat' ), (int) $c['provider_id'] );
	}
	foreach ( $flxs as $p ) {
		// Flux Mail varken yalnız hâlâ bu sağlayıcıyı kullanan hat için gösterilir.
		if ( ! flxc_flxi_on() || ( 'flxs' === $c['source'] && (int) $c['provider_id'] === (int) $p['id'] ) ) {
			/* translators: %s: provider name */
			$out[ 'flxs:' . (int) $p['id'] ] = sprintf( __( 'Use Flux Certificates provider: %s', 'flx-chat' ), $p['name'] );
		}
	}
	// 1.34.0 (Ahmet): hesaplar Flux Mail'de yönetilir; İletişim etkinken ayrı bilgi yalnız zaten öyle kurulmuş hatta kalır.
	if ( ! flxc_flxi_on() || 'own' === $c['source'] ) {
		$out['own'] = __( 'Enter separate Evolution API details', 'flx-chat' );
	}
	return $out;
}

function flxc_connection_source_value( $c ) {
	if ( 'flxi' === $c['source'] && ! flxc_flxi_on() && (int) $c['flxs_id'] ) {
		return 'flxs:' . (int) $c['flxs_id']; // Flux Mail kapalı: taşınmadan önceki sağlayıcı
	}
	return in_array( $c['source'], array( 'flxi', 'flxs' ), true ) ? $c['source'] . ':' . (int) $c['provider_id'] : 'own';
}

function flxc_flxi_connections_url() {
	return function_exists( 'flxi_admin_url' ) ? flxi_admin_url( 'flxi-baglantilar' ) : admin_url( 'admin.php?page=flxi-baglantilar' );
}

function flxc_settings_conn_row( $i, $c, $flxs ) {
	$n     = 'connections[' . $i . ']';
	$saved = '' !== $c['id'];
	$st    = $saved ? flxc_connection_state( $c['id'] ) : array( 'state' => '' );
	$last  = $saved ? (int) get_option( 'flxc_last_webhook_' . $c['id'] ) : 0;
	?>
	<div class="flxcs-conn" data-conn="<?php echo esc_attr( $c['id'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $c['id'] ); ?>">
		<input type="hidden" class="flxcs-del-flag" name="<?php echo esc_attr( $n ); ?>[delete]" value="">
		<div class="flxcs-conn-head">
			<span class="flxcs-state flxcs-state-<?php echo esc_attr( $st['state'] ? $st['state'] : 'unknown' ); ?>"></span>
			<input type="text" class="flxcs-conn-label" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php esc_attr_e( 'Line name (e.g. Main line)', 'flx-chat' ); ?>">
			<button type="button" class="flxcs-link flxcs-del"><?php esc_html_e( 'Delete', 'flx-chat' ); ?></button>
		</div>
		<div class="flxcs-grid">
			<label class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Connection details', 'flx-chat' ); ?></span>
				<select name="<?php echo esc_attr( $n ); ?>[source]" class="flxcs-source">
					<?php foreach ( flxc_connection_source_choices( $c, $flxs ) as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( flxc_connection_source_value( $c ), $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="flxcs-field flxcs-own"><span><?php esc_html_e( 'API URL', 'flx-chat' ); ?></span>
				<input type="url" name="<?php echo esc_attr( $n ); ?>[base_url]" value="<?php echo esc_attr( $c['base_url'] ); ?>" placeholder="https://wa.ornek.com">
			</label>
			<label class="flxcs-field flxcs-own"><span><?php esc_html_e( 'Instance name', 'flx-chat' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $n ); ?>[instance]" value="<?php echo esc_attr( $c['instance'] ); ?>">
			</label>
			<label class="flxcs-field flxcs-own flxcs-span2"><span><?php esc_html_e( 'API key (apikey)', 'flx-chat' ); ?></span>
				<input type="password" name="<?php echo esc_attr( $n ); ?>[api_key]" value="" autocomplete="new-password" placeholder="<?php echo $c['api_key'] ? esc_attr__( '•••••••• (saved; type to change)', 'flx-chat' ) : ''; ?>">
			</label>
		</div>
		<?php if ( $saved ) : ?>
			<div class="flxcs-wh">
				<span><?php esc_html_e( 'Webhook URL', 'flx-chat' ); ?></span>
				<code class="flxcs-wh-url"><?php echo esc_html( flxc_webhook_url( $c ) ); ?></code>
				<button type="button" class="flxcs-link flxcs-copy"><?php esc_html_e( 'Copy', 'flx-chat' ); ?></button>
				<label class="flxcs-check"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[new_secret]" value="1"> <?php esc_html_e( 'Regenerate secret key', 'flx-chat' ); ?></label>
			</div>
			<p class="flxcs-small">
				<?php
				if ( $last ) {
					/* translators: %s: time ago */
					printf( esc_html__( 'Last webhook: %s', 'flx-chat' ), esc_html( flx_time_ago( $last ) ) );
				} else {
					esc_html_e( 'No webhook received yet.', 'flx-chat' );
				}
				?>
			</p>
			<div class="flxcs-actions">
				<button type="button" class="flxcs-btn" data-act="test"><?php esc_html_e( 'Test connection', 'flx-chat' ); ?></button>
				<button type="button" class="flxcs-btn flxcs-not-flxi" data-act="qr"><?php esc_html_e( 'Connect with QR', 'flx-chat' ); ?></button>
				<?php if ( flxc_flxi_on() ) : ?>
					<a class="flxcs-link flxcs-flxi-only" href="<?php echo esc_url( flxc_flxi_connections_url() ); ?>"><?php esc_html_e( 'QR, status and accounts: Communication › Connections', 'flx-chat' ); ?> →</a>
				<?php endif; ?>
				<button type="button" class="flxcs-btn flxcs-btn-primary" data-act="webhook"><?php esc_html_e( 'Set up webhook', 'flx-chat' ); ?></button>
				<button type="button" class="flxcs-btn" data-act="import"><?php esc_html_e( 'Import recent chats', 'flx-chat' ); ?></button>
			</div>
			<div class="flxcs-out" aria-live="polite"></div>
		<?php else : ?>
			<p class="flxcs-small"><?php esc_html_e( 'The webhook URL and test buttons appear after saving.', 'flx-chat' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function flxc_settings_tab_genel( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Appearance', 'flx-chat' ); ?></h2>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Panel title', 'flx-chat' ); ?></span>
				<input type="text" name="brand_name" value="<?php echo esc_attr( $s['brand_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<small><?php esc_html_e( 'Site name if empty. The {brand} variable uses this too.', 'flx-chat' ); ?></small>
			</label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Accent color', 'flx-chat' ); ?></span>
				<input type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ); ?>">
			</label>
		</div>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Behavior', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="send_read" value="1" <?php checked( $s['send_read'] ); ?>> <?php esc_html_e( 'Send read receipts (blue ticks) when a chat is opened', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="agent_signature" value="1" <?php checked( $s['agent_signature'] ); ?>> <?php esc_html_e( 'Prefix messages with the agent\'s name (*Name:*)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="restrict_agents" value="1" <?php checked( $s['restrict_agents'] ); ?>> <?php esc_html_e( 'Agents see only chats assigned to them and unassigned chats (admins see all)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="show_groups" value="1" <?php checked( $s['show_groups'] ); ?>> <?php esc_html_e( 'Also show WhatsApp group messages', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="ui_flux" value="1" <?php checked( $s['ui_flux'] ); ?> <?php disabled( ! file_exists( FLXC_DIR . 'assets/flux/flux-modules.js' ) ); ?>> <?php esc_html_e( 'New interface (experimental): the Chats page opens with the same screen as the desktop and mobile app', 'flx-chat' ); ?></label>
		<label class="flxcs-field flxcs-narrow"><span><?php esc_html_e( 'Delete for everyone time limit (hours)', 'flx-chat' ); ?></span>
			<input type="number" min="0" max="60" name="delete_everyone_hours" value="<?php echo (int) $s['delete_everyone_hours']; ?>">
			<small><?php esc_html_e( 'WhatsApp lets you delete a sent message for everyone only for about 2 days and 12 hours (60 hours). After this limit, or for received messages, the message is only hidden in the panel: it stays on the contact\'s phone. 0 turns off delete for everyone. Deleted messages are never removed from the server; the team can still open them.', 'flx-chat' ); ?></small>
		</label>
		<label class="flxcs-field flxcs-narrow"><span><?php esc_html_e( 'Live update interval (sec)', 'flx-chat' ); ?></span>
			<input type="number" min="3" max="60" name="poll_interval" value="<?php echo (int) $s['poll_interval']; ?>">
			<small><?php esc_html_e( 'The open tab checks for new messages at this interval (10 recommended), every 3 s while a reply is streaming, and up to 15 s when nothing changes. A background tab checks once a minute. Errors back off up to 60 s.', 'flx-chat' ); ?></small>
		</label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Agents', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc">
			<?php
			printf(
				/* translators: %s: role name */
				esc_html__( 'Give a user the %s role; they see only the chat screen and cannot open settings. Administrators always have access.', 'flx-chat' ),
				'<strong>' . esc_html__( 'Call Center Agent', 'flx-chat' ) . '</strong>'
			);
			?>
		</p>
		<ul class="flxcs-agents">
			<?php foreach ( flxc_agents() as $a ) : ?>
				<li><?php echo esc_html( $a['name'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<a class="flxcs-link" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add new user', 'flx-chat' ); ?> →</a>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Data', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="delete_data" value="1" <?php checked( $s['delete_data'] ); ?>> <?php esc_html_e( 'Also delete all chats, messages and media files when the plugin is deleted', 'flx-chat' ); ?></label>
		<p class="flxcs-small"><?php esc_html_e( 'Media files are kept in wp-content/uploads/flx-chat, closed to outside access. They move with uploads when the site is migrated.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_otomatik( $s ) {
	$days = array(
		1 => __( 'Mon', 'flx-chat' ),
		2 => __( 'Tue', 'flx-chat' ),
		3 => __( 'Wed', 'flx-chat' ),
		4 => __( 'Thu', 'flx-chat' ),
		5 => __( 'Fri', 'flx-chat' ),
		6 => __( 'Sat', 'flx-chat' ),
		7 => __( 'Sun', 'flx-chat' ),
	);
	$sel  = array_map( 'intval', (array) $s['away_days'] );
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Welcome message', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="welcome_enabled" value="1" <?php checked( $s['welcome_enabled'] ); ?>> <?php esc_html_e( 'Send automatically to people who write to us for the first time', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><textarea name="welcome_text" rows="3"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea></label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Away message', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="away_enabled" value="1" <?php checked( $s['away_enabled'] ); ?>> <?php esc_html_e( 'Auto reply to messages received outside business hours', 'flx-chat' ); ?></label>
		<div class="flxcs-days">
			<span><?php esc_html_e( 'Business days', 'flx-chat' ); ?></span>
			<?php foreach ( $days as $d => $l ) : ?>
				<label class="flxcs-day"><input type="checkbox" name="away_days[]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, $sel, true ) ); ?>><span><?php echo esc_html( $l ); ?></span></label>
			<?php endforeach; ?>
		</div>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Start', 'flx-chat' ); ?></span><input type="time" name="away_start" value="<?php echo esc_attr( $s['away_start'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'End', 'flx-chat' ); ?></span><input type="time" name="away_end" value="<?php echo esc_attr( $s['away_end'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'At most once per contact every (hours)', 'flx-chat' ); ?></span><input type="number" min="1" max="72" name="away_throttle" value="<?php echo (int) $s['away_throttle']; ?>"></label>
		</div>
		<label class="flxcs-field"><textarea name="away_text" rows="3"><?php echo esc_textarea( $s['away_text'] ); ?></textarea></label>
		<p class="flxcs-small">
			<?php
			/* translators: 1: timezone, 2: now open/closed */
			printf( esc_html__( 'Timezone: %1$s. Now: %2$s.', 'flx-chat' ), esc_html( wp_timezone_string() ), flxc_is_open_hours() ? esc_html__( 'within business hours', 'flx-chat' ) : esc_html__( 'outside business hours', 'flx-chat' ) );
			?>
		</p>
		<p class="flxcs-small"><?php esc_html_e( 'The {first_name}, {name}, {brand}, {site}, {date} variables can be used in the texts.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_etiketler( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Chat labels', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Label chats and filter the list (e.g. Donation, Complaint, Follow-up).', 'flx-chat' ); ?></p>
		<div id="flxcs-labels" class="flxcs-labels">
			<?php
			foreach ( (array) $s['labels'] as $i => $l ) {
				flxc_settings_label_row( $i, $l );
			}
			?>
		</div>
		<template id="flxcs-label-tpl"><?php flxc_settings_label_row( '__i__', array( 'id' => '', 'name' => '', 'color' => '#25d366' ) ); ?></template>
		<button type="button" class="flxcs-btn" id="flxcs-add-label">+ <?php esc_html_e( 'Add label', 'flx-chat' ); ?></button>
	</section>
	<?php
}

function flxc_settings_label_row( $i, $l ) {
	$n = 'labels[' . $i . ']';
	?>
	<div class="flxcs-label">
		<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $l['id'] ); ?>">
		<input type="color" name="<?php echo esc_attr( $n ); ?>[color]" value="<?php echo esc_attr( $l['color'] ); ?>">
		<input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $l['name'] ); ?>" placeholder="<?php esc_attr_e( 'Label name', 'flx-chat' ); ?>">
		<button type="button" class="flxcs-link flxcs-del-label" aria-label="<?php esc_attr_e( 'Delete', 'flx-chat' ); ?>">✕</button>
	</div>
	<?php
}

function flxc_settings_tab_kayitlar( $s ) {
	$flxb = flxc_flxb_on();
	$flxs = flxc_flxs_on();
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Creating records from chats', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Buttons in the chat header create a donation record (Flux Donate) and a certificate (Flux Certificates), and an incoming receipt is matched with a record. If a plugin is not installed, its button is hidden.', 'flx-chat' ); ?></p>
		<ul class="flxcs-agents">
			<li><?php echo $flxb ? '✓ ' : '✕ '; ?>Flux Donate</li>
			<li><?php echo $flxs ? '✓ ' : '✕ '; ?>Flux Certificates</li>
		</ul>
		<label class="flxcs-check"><input type="checkbox" name="agents_donations" value="1" <?php checked( $s['agents_donations'] ); ?>> <?php esc_html_e( 'Agents can create donation records and match receipts', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="agents_certificates" value="1" <?php checked( $s['agents_certificates'] ); ?>> <?php esc_html_e( 'Agents can create and send certificates', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="chips_bar" value="1" <?php checked( $s['chips_bar'] ); ?>> <?php esc_html_e( 'Show quick reply bubbles above the message box', 'flx-chat' ); ?></label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Donation messages', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Ready texts sent to the person when a record is created (editable before sending). If left empty, the default text is used.', 'flx-chat' ); ?></p>
		<label class="flxcs-field"><span><?php esc_html_e( 'Awaiting payment (promised transfer)', 'flx-chat' ); ?></span><textarea name="msg_donation_pending" rows="5" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_donation_pending' ) ); ?>"><?php echo esc_textarea( $s['msg_donation_pending'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Donation received', 'flx-chat' ); ?></span><textarea name="msg_donation_done" rows="4" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_donation_done' ) ); ?>"><?php echo esc_textarea( $s['msg_donation_done'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Receipt confirmed', 'flx-chat' ); ?></span><textarea name="msg_receipt" rows="3" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_receipt' ) ); ?>"><?php echo esc_textarea( $s['msg_receipt'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Bank details ({bank})', 'flx-chat' ); ?></span><textarea name="bank_text" rows="3" placeholder="<?php echo esc_attr( flxc_bank_text() ? flxc_bank_text() : __( 'Recipient name, bank, IBAN', 'flx-chat' ) ); ?>"><?php echo esc_textarea( $s['bank_text'] ); ?></textarea><small><?php esc_html_e( 'If empty, the account in WooCommerce › Payments › Bank transfer is used.', 'flx-chat' ); ?></small></label>
		<p class="flxcs-small"><?php esc_html_e( 'Variables: {first_name}, {name}, {donation}, {amount}, {quantity}, {bank}, {certificate_link}, {agent}, {brand}, {date}.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_ai( $s ) {
	$list  = flxc_ai_providers();
	$usage = get_option( 'flxc_ai_usage', array() );
	$types = function_exists( 'flxb_get_types' ) ? (array) flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) : array();
	$sel   = array_map( 'intval', (array) $s['bot_types'] );
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'AI connection', 'flx-chat' ); ?></h2>
		<?php if ( flxc_ai_hub() ) : ?>
		<p class="flxcs-desc"><?php esc_html_e( 'Keys, prompt caching, spending limits and usage logs come from the Flux AI & MCP plugin; all of the Call Center\'s AI requests go through it. Model choices stay here; if a field is empty, the model chosen for this plugin in Flux AI (or the default) is used.', 'flx-chat' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-ai-settings' ) ); ?>"><?php esc_html_e( 'Open Flux AI settings', 'flx-chat' ); ?></a></p>
		<?php else : ?>
		<div class="flxcs-note flxcs-note-warn"><?php esc_html_e( 'AI needs the Flux AI & MCP plugin. Install and activate it, then enter the OpenRouter and/or Anthropic key in Flux AI & MCP › Settings. Until then suggestions, the assistant, receipt reading, voice messages and summaries are off.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Creativity (0–1)', 'flx-chat' ); ?></span>
				<input type="number" name="ai_temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr( (string) $s['ai_temperature'] ); ?>">
				<small><?php esc_html_e( 'Lower values give more consistent replies, higher values freer ones. 0.4 recommended.', 'flx-chat' ); ?></small>
			</label>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Model (for suggestions)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model" data-value="<?php echo esc_attr( $s['ai_model'] ); ?>" data-empty="<?php esc_attr_e( 'Choose a model', 'flx-chat' ); ?>"><input type="hidden" name="ai_model" value="<?php echo esc_attr( $s['ai_model'] ); ?>"></div>
			</div>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Assistant model (optional)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_bot" data-tools="1" data-def="<?php echo esc_attr( $s['ai_model'] ); ?>" data-value="<?php echo esc_attr( $s['ai_model_bot'] ); ?>" data-empty="<?php esc_attr_e( 'Model above', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_bot" value="<?php echo esc_attr( $s['ai_model_bot'] ); ?>"></div>
				<small><?php esc_html_e( 'Only models that support tools are listed.', 'flx-chat' ); ?></small>
			</div>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Receipt reading model (optional)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_vision" data-vision="1" data-def="<?php echo esc_attr( '' !== $s['ai_model_bot'] ? $s['ai_model_bot'] : $s['ai_model'] ); ?>" data-value="<?php echo esc_attr( $s['ai_model_vision'] ); ?>" data-empty="<?php esc_attr_e( 'Assistant model', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_vision" value="<?php echo esc_attr( $s['ai_model_vision'] ); ?>"></div>
				<small><?php esc_html_e( 'If the selected model cannot read images, a cheap vision model is used automatically.', 'flx-chat' ); ?></small>
			</div>
		</div>
		<p class="flxcs-row">
			<button type="button" class="flxcs-btn" id="flxcs-ai-test"><?php esc_html_e( 'Test connection', 'flx-chat' ); ?></button>
			<span class="flxcs-out" id="flxcs-ai-out"></span>
		</p>
		<?php if ( ! empty( $usage['month'] ) ) : ?>
			<p class="flxcs-small">
				<?php
				/* translators: 1: month, 2: requests, 3: tokens, 4: cost */
				printf( esc_html__( 'This month (%1$s): %2$s requests, %3$s tokens, about $%4$s.', 'flx-chat' ), esc_html( $usage['month'] ), esc_html( number_format_i18n( (int) $usage['calls'] ) ), esc_html( number_format_i18n( (int) $usage['tokens'] ) ), esc_html( number_format_i18n( (float) $usage['cost'], 4 ) ) );
				?>
			</p>
		<?php endif; ?>
	</section>

	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Reply suggestions', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Above the message box, the next message is suggested from the conversation. The agent clicks "Send" or takes it into the box with "Edit". A suggestion is never sent on its own.', 'flx-chat' ); ?></p>
		<label class="flxcs-check"><input type="checkbox" name="ai_auto_suggest" value="1" <?php checked( $s['ai_auto_suggest'] ); ?>> <?php esc_html_e( 'Prepare a suggestion automatically when the last message came from the person (if off, ask with the ✨ button)', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Organization info and tone', 'flx-chat' ); ?></span>
			<textarea name="ai_instructions" rows="6" placeholder="<?php esc_attr_e( 'E.g. Our association plants saplings in Istanbul. Planting is done every year between November and March. The certificate is sent on WhatsApp after the donation. Use a warm but formal tone.', 'flx-chat' ); ?>"><?php echo esc_textarea( $s['ai_instructions'] ); ?></textarea>
			<small><?php esc_html_e( 'Suggestions and the assistant use this. Donation types, prices, bank details and quick replies are added automatically.', 'flx-chat' ); ?></small>
		</label>
		<?php if ( count( $list ) > 1 ) : ?>
			<label class="flxcs-field flxcs-narrow"><span><?php esc_html_e( 'Suggestion provider', 'flx-chat' ); ?></span>
				<select name="ai_provider">
					<?php foreach ( $list as $id => $p ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['ai_provider'], $id ); ?>><?php echo esc_html( $p['label'] ?? $id ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
	</section>

	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Donation assistant (automatic replies)', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'When on, the assistant replies itself: it greets, asks the name to address them, sends donation options and prices, optionally collects email and "in whose name", and asks for confirmation after each change. After confirmation it opens an "awaiting payment" record in Flux Donate and sends the donation link, recipient, IBAN and reference as separate messages. If the topic leaves donations, it hands the chat to the team.', 'flx-chat' ); ?></p>
		<?php if ( ! flxc_flxb_on() ) : ?>
			<div class="flxcs-note flxcs-note-warn"><?php esc_html_e( 'The Flux Donate plugin is not active; the assistant cannot create donation records.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<p><a class="flxcs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-asistan' ) ); ?>"><?php esc_html_e( 'Instructions, documents, AI chats and test chat', 'flx-chat' ); ?> →</a></p>
		<label class="flxcs-check"><input type="checkbox" name="bot_enabled" value="1" <?php checked( $s['bot_enabled'] ); ?>> <strong><?php esc_html_e( 'Turn on the donation assistant (untick to turn it off everywhere)', 'flx-chat' ); ?></strong></label>
		<div class="flxcs-field"><span><?php esc_html_e( 'Which chats should it reply to', 'flx-chat' ); ?></span>
			<?php
			foreach ( array(
				'all'        => __( 'In all chats (recommended)', 'flx-chat' ),
				'unassigned' => __( 'In chats not assigned to an agent', 'flx-chat' ),
				'away'       => __( 'Only outside working hours', 'flx-chat' ),
			) as $k => $l ) :
				?>
				<label class="flxcs-check"><input type="radio" name="bot_scope" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['bot_scope'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
			<?php endforeach; ?>
			<small><?php esc_html_e( 'When an agent writes in a chat (from the panel or the phone), the assistant stays quiet there for the time below. Use the assistant button in the chat header to turn it on or off per chat.', 'flx-chat' ); ?></small>
		</div>
		<label class="flxcs-check"><input type="checkbox" name="bot_test_only" value="1" <?php checked( $s['bot_test_only'] ); ?>> <?php esc_html_e( 'Test mode: reply only to the numbers below', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Test numbers', 'flx-chat' ); ?></span><textarea name="bot_test_numbers" rows="2" placeholder="905321234567, 905551112233"><?php echo esc_textarea( $s['bot_test_numbers'] ); ?></textarea><small><?php esc_html_e( 'Separate with commas or new lines. Not used when test mode is off.', 'flx-chat' ); ?></small></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Quiet after an agent writes (hours)', 'flx-chat' ); ?></span><input type="number" min="1" max="720" name="bot_pause_hours" value="<?php echo (int) $s['bot_pause_hours']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Daily reply limit per chat', 'flx-chat' ); ?></span><input type="number" min="5" max="500" name="bot_daily_limit" value="<?php echo (int) $s['bot_daily_limit']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Quantities in the price list', 'flx-chat' ); ?></span><input type="text" name="bot_presets" value="<?php echo esc_attr( $s['bot_presets'] ); ?>" placeholder="1, 3, 5, 10"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Conversation history (hours)', 'flx-chat' ); ?></span><input type="number" min="1" max="168" name="bot_context_hours" value="<?php echo (int) $s['bot_context_hours']; ?>"><small><?php esc_html_e( 'Before replying, the assistant reads all messages from this period.', 'flx-chat' ); ?></small></label>
		</div>
		<?php if ( $types ) : ?>
			<div class="flxcs-field"><span><?php esc_html_e( 'Donation types the assistant offers', 'flx-chat' ); ?></span>
				<?php foreach ( $types as $t ) : ?>
					<label class="flxcs-check"><input type="checkbox" name="bot_types[]" value="<?php echo (int) $t['id']; ?>" <?php checked( in_array( (int) $t['id'], $sel, true ) ); ?>> <?php echo esc_html( $t['title'] ); ?></label>
				<?php endforeach; ?>
				<small><?php esc_html_e( 'If none is selected, all open types are offered. When a new type is opened in Flux Donate, the assistant knows it too.', 'flx-chat' ); ?></small>
			</div>
		<?php endif; ?>
		<label class="flxcs-check"><input type="checkbox" name="bot_receipt_ai" value="1" <?php checked( $s['bot_receipt_ai'] ); ?>> <?php esc_html_e( 'Receipt pre-check: when the contact sends a receipt, AI compares the amount, recipient and IBAN with the donation, tells the contact the result, attaches the file to the donation record and hands the chat to the team for final approval (does not mark it paid)', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Last line of the price list', 'flx-chat' ); ?></span><input type="text" name="bot_price_footer" value="<?php echo esc_attr( $s['bot_price_footer'] ); ?>" placeholder="<?php esc_attr_e( 'Just write which one and how many you would like.', 'flx-chat' ); ?>"></label>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Payment details', 'flx-chat' ); ?></h3>
		<p class="description"><?php esc_html_e( 'If the recipient and IBAN are empty, those in WooCommerce › Payments › Bank transfer are used (the account shown on orders). Fill them in only if the assistant should use a different account.', 'flx-chat' ); ?></p>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Recipient name', 'flx-chat' ); ?></span><input type="text" name="bank_holder" value="<?php echo esc_attr( $s['bank_holder'] ); ?>" placeholder="<?php echo esc_attr( flxc_bot_bank_account()['holder'] ); ?>"></label>
			<label class="flxcs-field"><span>IBAN</span><input type="text" name="bank_iban" value="<?php echo esc_attr( $s['bank_iban'] ); ?>" placeholder="<?php echo esc_attr( flxc_bot_bank_account()['iban'] ? flxc_bot_bank_account()['iban'] : 'TR00 0000 0000 0000 0000 0000 00' ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Transfer description', 'flx-chat' ); ?></span><input type="text" name="bank_desc" value="<?php echo esc_attr( $s['bank_desc'] ); ?>" placeholder="{type} {name}"><small><?php esc_html_e( 'Variables: {type}, {name}, {amount}, {quantity}', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Donation page URL', 'flx-chat' ); ?></span><input type="url" name="donate_url" value="<?php echo esc_attr( $s['donate_url'] ); ?>" placeholder="<?php echo esc_attr( flxc_donate_url() ? flxc_donate_url() : home_url( '/bagis/' ) ); ?>"><small><?php esc_html_e( 'The selected type and quantity are added to the link (?flxb_opt=…&flxb_qty=…).', 'flx-chat' ); ?></small></label>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message after the payment details', 'flx-chat' ); ?></span><input type="text" name="bot_after_payment" value="<?php echo esc_attr( $s['bot_after_payment'] ); ?>" placeholder="<?php esc_attr_e( 'After paying, you can send the receipt here. Thank you so much for your support 🤲', 'flx-chat' ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Donation cancellation question', 'flx-chat' ); ?></span><textarea name="bot_cancel_text" rows="3" placeholder="<?php echo esc_attr( flxc_bot_cancel_ask( '{bagis}', '{tutar}' ) ); ?>"><?php echo esc_textarea( $s['bot_cancel_text'] ); ?></textarea><small><?php esc_html_e( 'The assistant cancels only unpaid donations, with the person\'s confirmation. Empty = default text. Variables: {bagis}, {tutar}.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message sent when the daily limit is reached', 'flx-chat' ); ?></span><input type="text" name="bot_limit_text" value="<?php echo esc_attr( $s['bot_limit_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'We\'ve received your message and will get back to you as soon as possible 🌱', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'Sent once when a chat reaches the day\'s reply limit; the chat is handed to the team. Reset it from the chat\'s ⋮ menu › Assistant usage.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message sent when handing over to the team', 'flx-chat' ); ?></span><input type="text" name="bot_handoff_text" value="<?php echo esc_attr( $s['bot_handoff_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'I\'m passing you to a colleague from our team, they will get back to you here shortly 🌱', 'flx-chat' ) ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message sent when the assistant cannot answer', 'flx-chat' ); ?></span><input type="text" name="bot_fallback_text" value="<?php echo esc_attr( $s['bot_fallback_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'We are sorry to keep you waiting. We will get back to you as soon as possible. Thank you for your understanding.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'If the AI fails, the time limit is hit or the queue gets too long, this message is sent and the chat is handed to the team; the person is never left without an answer.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Minimum wait before the holding message (s)', 'flx-chat' ); ?></span><input type="number" min="0" max="45" name="bot_fallback_wait" value="<?php echo (int) $s['bot_fallback_wait']; ?>"><small><?php esc_html_e( 'The holding message is not sent until this many seconds after the person\'s message; meanwhile the assistant retries.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Longest time for one answer (s)', 'flx-chat' ); ?></span><input type="number" min="20" max="110" name="bot_time_budget" value="<?php echo (int) $s['bot_time_budget']; ?>"><small><?php esc_html_e( 'Hostinger can cut requests at ~60 s; 50 is recommended.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Numbers notified on handoff', 'flx-chat' ); ?></span><textarea name="bot_notify_numbers" rows="2" placeholder="905321234567, 905551112233"><?php echo esc_textarea( $s['bot_notify_numbers'] ); ?></textarea><small><?php esc_html_e( 'When the assistant hands a chat over to the team, a WhatsApp message is sent to these numbers from the same line: the contact\'s name, phone, reason, last messages and panel link. Separate with commas or new lines. The assistant does not talk to these numbers.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Agent request phrases', 'flx-chat' ); ?></span><textarea name="bot_handoff_words" rows="2" placeholder="<?php echo esc_attr( implode( ', ', flxc_bot_handoff_words_default() ) ); ?>"><?php echo esc_textarea( $s['bot_handoff_words'] ); ?></textarea><small><?php esc_html_e( 'When the contact writes one of these, the assistant immediately sends the hand-off message above and turns off in that chat. If left empty, the example list is used.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Extra instructions for the assistant', 'flx-chat' ); ?></span><textarea name="bot_instructions" rows="4" placeholder="<?php esc_attr_e( 'E.g. Saplings are planted in Kocaeli. Certificates are sent once the donation is confirmed.', 'flx-chat' ); ?>"><?php echo esc_textarea( $s['bot_instructions'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Last message when a contact opts out', 'flx-chat' ); ?></span><input type="text" name="bot_optout_text" value="<?php echo esc_attr( $s['bot_optout_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Understood, you will not receive any more messages from us. Have a nice day.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'If someone writes something like "don\'t message me", the assistant sends this message and blocklists them. A blocklisted person gets no WhatsApp, email or SMS; manage the list under Chats › ☰ › Blocklist.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Contact memory and background jobs', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="mem_enabled" value="1" <?php checked( $s['mem_enabled'] ); ?>> <?php esc_html_e( 'Contact memory: the assistant remembers lasting details (form of address, relatives\' names, numbers, preferences); expired chats are summarized', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Summarize a chat after it has been quiet for (hours)', 'flx-chat' ); ?></span><input type="number" min="1" max="168" name="mem_after_hours" value="<?php echo (int) $s['mem_after_hours']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Maximum jobs at the same time', 'flx-chat' ); ?></span><input type="number" min="1" max="30" name="workers_max" value="<?php echo (int) $s['workers_max']; ?>"><small><?php esc_html_e( 'How many people can be answered at the same time. Assistant replies always come first; summaries do not start while someone is being answered or waiting. Each job uses one PHP worker: on shared hosting, stay under half of the total worker limit of your sites (e.g. 20-30 of 60 workers).', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Workers reserved for replies', 'flx-chat' ); ?></span><input type="number" min="0" max="30" name="workers_reserved" value="<?php echo (int) $s['workers_reserved']; ?>"><small><?php esc_html_e( 'This many workers never take summaries or reminders.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Summary hours', 'flx-chat' ); ?></span><span class="flxcs-inline"><input type="time" name="mem_window_from" value="<?php echo esc_attr( $s['mem_window_from'] ); ?>"> – <input type="time" name="mem_window_to" value="<?php echo esc_attr( $s['mem_window_to'] ); ?>"></span><small><?php esc_html_e( 'Summaries run only between these hours (except a manual "Summarize now"). If both are the same, any time.', 'flx-chat' ); ?></small></label>
		</div>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Voice message', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_voice" value="1" <?php checked( $s['bot_voice'] ); ?>> <?php esc_html_e( 'When someone sends a voice message, the assistant turns it into text and replies (the text also appears in the panel as a system line)', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Longest voice message (sec)', 'flx-chat' ); ?></span><input type="number" min="10" max="300" name="bot_voice_max" value="<?php echo (int) $s['bot_voice_max']; ?>"><small><?php esc_html_e( 'For longer messages the assistant says "the voice message was too long for us to listen to".', 'flx-chat' ); ?></small></label>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Voice message model (optional)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_audio" data-value="<?php echo esc_attr( $s['ai_model_audio'] ); ?>" data-empty="<?php esc_attr_e( 'Cheap audio model (Gemini Flash Lite)', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_audio" value="<?php echo esc_attr( $s['ai_model_audio'] ); ?>"></div>
				<small><?php esc_html_e( 'Must be an OpenRouter model that accepts audio (Claude does not). An OpenRouter key is required.', 'flx-chat' ); ?></small>
			</div>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Images', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_widgets" value="1" <?php checked( $s['bot_widgets'] ); ?>> <?php esc_html_e( 'Let the assistant draw an image when useful (table, report, diagram); it goes to WhatsApp as a picture, the text is sent separately', 'flx-chat' ); ?></label>
		<p class="flxcs-help"><?php echo esc_html( function_exists( 'flxai_render_widget' ) ? ( flxc_widgets_on() || ! (int) $s['bot_widgets'] ? __( 'The Flux AI image renderer is used (Flux AI & MCP › Settings › Images).', 'flx-chat' ) : __( 'No image renderer on the server: enter a Gotenberg address in Flux AI & MCP › Settings › Images or install Imagick (SVG). Until then the assistant does not draw images.', 'flx-chat' ) ) : __( 'Works once Flux AI 0.2.0 or later is installed.', 'flx-chat' ) ); ?></p>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Donation on behalf of someone', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="benef_cert_notify" value="1" <?php checked( $s['benef_cert_notify'] ); ?>> <?php esc_html_e( 'If the donation is in someone else\'s name and their number was given (contact card or typed number), also send the certificate to that number on WhatsApp once the payment is approved', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message sent before the certificate', 'flx-chat' ); ?></span><input type="text" name="benef_cert_text" value="<?php echo esc_attr( $s['benef_cert_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_benef_cert_default_text() ); ?>"><small><?php esc_html_e( 'Then the WhatsApp certificate message from Flux Certificates is sent. Variables: {ad}, {bagisci}, {bagis}, {brand}.', 'flx-chat' ); ?></small></label>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Reminders', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="rem_enabled" value="1" <?php checked( $s['rem_enabled'] ); ?>> <?php esc_html_e( 'If a person asks, the assistant sets up a reminder (e.g. "remind me of sapling planting every month", "send me your latest projects every month", "ask me for a sapling every month"); when it is due, the assistant writes and sends the message', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'When no time is given', 'flx-chat' ); ?></span><input type="time" name="rem_time" value="<?php echo esc_attr( $s['rem_time'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'At most per person', 'flx-chat' ); ?></span><input type="number" min="1" max="50" name="rem_max" value="<?php echo (int) $s['rem_max']; ?>"></label>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Staff (Flux Roles)', 'flx-chat' ); ?></h3>
		<?php if ( ! function_exists( 'flxr_phone_identity' ) ) : ?>
			<p class="flxcs-help"><?php esc_html_e( 'Works once Flux Roles 1.1.0 is installed: users whose number is set in Profile › WhatsApp number get extra assistant tools based on their permissions.', 'flx-chat' ); ?></p>
		<?php endif; ?>
		<label class="flxcs-check"><input type="checkbox" name="staff_on" value="1" <?php checked( $s['staff_on'] ); ?>> <?php esc_html_e( 'Give staff numbers extra assistant tools by permission (donation on behalf of someone with payment link, my donors, general report)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="ref_notify" value="1" <?php checked( $s['ref_notify'] ); ?>> <?php esc_html_e( 'Notify staff on WhatsApp when a donation they referred is paid or cancelled', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'First message to the donor for a donation opened by staff', 'flx-chat' ); ?></span><input type="text" name="staff_donor_text" value="<?php echo esc_attr( $s['staff_donor_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Hello {ad} 🌱 Your {bagis} donation request via {personel} has been received. You can pay with the details below.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'The payment link and bank details follow. Variables: {ad}, {adsoyad}, {personel}, {bagis}.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-check"><input type="checkbox" name="ref_cert_notify" value="1" <?php checked( $s['ref_cert_notify'] ); ?>> <?php esc_html_e( 'When a donation referred by a staff member is paid and its certificates are made, send them to the staff member on WhatsApp too (just like the donor)', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Message sent to the staff member before the certificate', 'flx-chat' ); ?></span><input type="text" name="ref_cert_text" value="<?php echo esc_attr( $s['ref_cert_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_ref_cert_default_text() ); ?>"><small><?php esc_html_e( 'Variables: {ad}, {bagisci}, {bagis}, {no}, {brand}.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'How many times staff can resend a donation\'s certificate', 'flx-chat' ); ?></span><input type="number" min="0" max="20" name="staff_cert_max" value="<?php echo (int) $s['staff_cert_max']; ?>"><small><?php esc_html_e( 'The assistant lists the recipients and asks for confirmation before sending. 0: off.', 'flx-chat' ); ?></small></label>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Code verification for record lookups', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="email_verify" value="1" <?php checked( $s['email_verify'] ); ?>> <?php esc_html_e( 'When a person asks for their records and their number is hidden (or they donated with another number/e-mail), send a code to the number or e-mail they give; once verified, its records are shown too', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Code validity (min)', 'flx-chat' ); ?></span><input type="number" min="1" max="30" name="verify_ttl" value="<?php echo (int) $s['verify_ttl']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Maximum failed attempts', 'flx-chat' ); ?></span><input type="number" min="1" max="10" name="verify_max_fails" value="<?php echo (int) $s['verify_max_fails']; ?>"><small><?php esc_html_e( 'Codes that could not be sent, wrong codes and expired codes count. At the limit the person gets the message below and the team is notified; the assistant keeps answering other questions but shares no record details for 24 hours.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Code to phone', 'flx-chat' ); ?></span><select name="verify_phone_channel">
				<?php foreach ( array( 'auto' => __( 'WhatsApp, SMS if it fails', 'flx-chat' ), 'whatsapp' => __( 'WhatsApp only', 'flx-chat' ), 'sms' => __( 'SMS only', 'flx-chat' ) ) as $flxc_k => $flxc_l ) : ?>
					<option value="<?php echo esc_attr( $flxc_k ); ?>" <?php selected( $s['verify_phone_channel'], $flxc_k ); ?>><?php echo esc_html( $flxc_l ); ?></option>
				<?php endforeach; ?>
			</select><small><?php esc_html_e( 'SMS is used if Flux Mail has an SMS connection (e.g. Netgsm).', 'flx-chat' ); ?></small></label>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Message when verification fails', 'flx-chat' ); ?></span><input type="text" name="verify_fail_text" value="<?php echo esc_attr( $s['verify_fail_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_verify_fail_text() ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'WhatsApp/SMS code message', 'flx-chat' ); ?></span><textarea name="verify_msg" rows="3" placeholder="<?php echo esc_attr( flxc_verify_default_text() ); ?>"><?php echo esc_textarea( $s['verify_msg'] ); ?></textarea><small><?php esc_html_e( 'Default text if empty. Variables: {kod}, {dk}, {marka}. The e-mail text is edited in Flux Mail › E-mail templates.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Site content', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_site_search" value="1" <?php checked( $s['bot_site_search'] ); ?>> <?php esc_html_e( 'Let the assistant search published site content (e.g. "do you have a post about tree planting")', 'flx-chat' ); ?></label>
		<div class="flxcs-field"><span><?php esc_html_e( 'Content types to search', 'flx-chat' ); ?></span>
			<?php
			$flxc_sel_types = (array) $s['bot_site_types'];
			$flxc_auto      = flxc_site_types();
			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $flxc_pt ) :
				if ( 'attachment' === $flxc_pt->name ) {
					continue;
				}
				$flxc_on = $flxc_sel_types ? in_array( $flxc_pt->name, $flxc_sel_types, true ) : isset( $flxc_auto[ $flxc_pt->name ] );
				?>
				<label class="flxcs-check"><input type="checkbox" name="bot_site_types[]" value="<?php echo esc_attr( $flxc_pt->name ); ?>" <?php checked( $flxc_on ); ?>> <?php echo esc_html( $flxc_pt->labels->name ); ?></label>
			<?php endforeach; ?>
			<small><?php esc_html_e( 'Only published content without a password is returned. Drafts, private and password-protected content are never shown.', 'flx-chat' ); ?></small>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Categories and tags never to share', 'flx-chat' ); ?></span><input type="text" name="bot_site_exclude" value="<?php echo esc_attr( $s['bot_site_exclude'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. corporate-offers, internal-notice', 'flx-chat' ); ?>"><small><?php esc_html_e( 'Name or slug, comma separated. To hide a single item, tick "Do not let the assistant share this content" on its edit screen.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Team group', 'flx-chat' ); ?></h3>
		<?php $flxc_groups = flxc_mirror_groups( isset( $_GET['groups'] ) ); // phpcs:ignore ?>
		<label class="flxcs-field"><span><?php esc_html_e( 'WhatsApp group', 'flx-chat' ); ?></span>
			<select name="mirror_group" style="background-repeat:no-repeat">
				<option value=""><?php esc_html_e( '— Not selected —', 'flx-chat' ); ?></option>
				<?php foreach ( $flxc_groups as $flxc_jid => $flxc_gname ) : ?>
					<option value="<?php echo esc_attr( $flxc_jid ); ?>" <?php selected( $s['mirror_group'], $flxc_jid ); ?>><?php echo esc_html( $flxc_gname ); ?></option>
				<?php endforeach; ?>
				<?php if ( '' !== $s['mirror_group'] && ! isset( $flxc_groups[ $s['mirror_group'] ] ) ) : ?>
					<option value="<?php echo esc_attr( $s['mirror_group'] ); ?>" selected><?php echo esc_html( $s['mirror_group'] ); ?></option>
				<?php endif; ?>
			</select>
			<small><?php esc_html_e( 'Groups the line is a member of. If a group you just created is missing,', 'flx-chat' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=ai&groups=1' ) ); ?>"><?php esc_html_e( 'refresh the list', 'flx-chat' ); ?></a>.</small>
		</label>
		<label class="flxcs-check"><input type="checkbox" name="mirror_enabled" value="1" <?php checked( $s['mirror_enabled'] ); ?>> <?php esc_html_e( 'Post incoming messages and replies (assistant, team, automatic) to this group', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="notify_to_group" value="1" <?php checked( $s['notify_to_group'] ); ?>> <?php esc_html_e( 'Send handoff notifications to this group instead of each number; the numbers above are mentioned in the message', 'flx-chat' ); ?></label>
		<?php $flxc_merr = get_option( 'flxc_mirror_error' ); ?>
		<?php if ( is_array( $flxc_merr ) ) : ?>
			<p class="flxcs-warn"><?php echo esc_html( __( 'Last post to the group failed: ', 'flx-chat' ) . $flxc_merr['error'] ); ?></p>
		<?php endif; ?>
		<?php
		$preview = flxc_bot_price_text();
		if ( '' !== $preview ) :
			?>
			<details class="flxcs-details"><summary><?php esc_html_e( 'Price list preview', 'flx-chat' ); ?></summary><pre class="flxcs-pre"><?php echo esc_html( $preview ); ?></pre></details>
		<?php endif; ?>
		<?php if ( ! flxc_ai_ready() ) : ?>
			<p class="flxcs-small"><?php esc_html_e( 'Enter an AI key and model first so the assistant can work.', 'flx-chat' ); ?></p>
		<?php endif; ?>
		<?php $flxc_problem = flxc_ai_types_problem(); ?>
		<?php if ( '' !== $flxc_problem ) : ?>
			<p class="flxcs-warn"><?php echo esc_html( __( 'The assistant will not reply right now: ', 'flx-chat' ) . $flxc_problem ); ?></p>
		<?php endif; ?>
	</section>
	<div class="flxcs-save"><button type="submit" class="flxcs-btn flxcs-btn-primary"><?php esc_html_e( 'Save', 'flx-chat' ); ?></button></div>
	<?php
}

function flxc_save_settings() {
	if ( ! flxc_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'flx-chat' ) );
	}
	check_admin_referer( 'flxc_save_settings' );
	$tab = sanitize_key( $_POST['tab'] ?? 'baglantilar' );
	$in  = wp_unslash( $_POST );
	$cur = flxc_settings();
	$upd = array();

	if ( 'baglantilar' === $tab ) {
		$old  = flxc_connections();
		$list = array();
		foreach ( (array) ( $in['connections'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( $row['id'] ?? '' );
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}
			$prev = $id && isset( $old[ $id ] ) ? $old[ $id ] : null;
			$c    = $prev ? $prev : flxc_new_connection();
			$src  = (string) ( $row['source'] ?? 'own' );
			if ( 0 === strpos( $src, 'flxi:' ) ) {
				$pid = (int) substr( $src, 5 );
				if ( 'flxi' !== $c['source'] || (int) $c['provider_id'] !== $pid ) {
					$c['flxs_id'] = 0; // başka bir bağlantı seçildi; eski Flux Certificates geri dönüşü geçersiz
				}
				$c['source']      = 'flxi';
				$c['provider_id'] = $pid;
			} elseif ( 0 === strpos( $src, 'flxs:' ) ) {
				$c['source']      = 'flxs';
				$c['provider_id'] = (int) substr( $src, 5 );
			} else {
				$c['source']      = 'own';
				$c['provider_id'] = 0;
			}
			$c['label']    = sanitize_text_field( $row['label'] ?? '' );
			$c['label']    = '' !== $c['label'] ? $c['label'] : 'WhatsApp';
			$c['base_url'] = esc_url_raw( trim( (string) ( $row['base_url'] ?? '' ) ) );
			$c['instance'] = sanitize_text_field( $row['instance'] ?? '' );
			$key           = trim( (string) ( $row['api_key'] ?? '' ) );
			if ( '' !== $key ) {
				$c['api_key'] = sanitize_text_field( $key );
			}
			if ( ! empty( $row['new_secret'] ) ) {
				$c['secret'] = wp_generate_password( 32, false, false );
			}
			$list[] = $c;
		}
		$upd['connections'] = $list;
	} elseif ( 'genel' === $tab ) {
		$upd['brand_name']      = sanitize_text_field( $in['brand_name'] ?? '' );
		$upd['accent']          = sanitize_hex_color( $in['accent'] ?? '' ) ? sanitize_hex_color( $in['accent'] ) : '#00a884';
		$upd['send_read']       = empty( $in['send_read'] ) ? 0 : 1;
		$upd['agent_signature'] = empty( $in['agent_signature'] ) ? 0 : 1;
		$upd['restrict_agents'] = empty( $in['restrict_agents'] ) ? 0 : 1;
		$upd['show_groups']     = empty( $in['show_groups'] ) ? 0 : 1;
		$upd['ui_flux']         = empty( $in['ui_flux'] ) ? 0 : 1;
		$upd['delete_data']     = empty( $in['delete_data'] ) ? 0 : 1;
		$upd['poll_interval']   = min( 60, max( 3, (int) ( $in['poll_interval'] ?? 10 ) ) );
		$upd['delete_everyone_hours'] = min( 60, max( 0, (int) ( $in['delete_everyone_hours'] ?? 60 ) ) );
	} elseif ( 'otomatik' === $tab ) {
		$upd['welcome_enabled'] = empty( $in['welcome_enabled'] ) ? 0 : 1;
		$upd['welcome_text']    = sanitize_textarea_field( $in['welcome_text'] ?? '' );
		$upd['away_enabled']    = empty( $in['away_enabled'] ) ? 0 : 1;
		$upd['away_text']       = sanitize_textarea_field( $in['away_text'] ?? '' );
		$upd['away_days']       = array_values( array_filter( array_map( 'intval', (array) ( $in['away_days'] ?? array() ) ), function ( $d ) {
			return $d >= 1 && $d <= 7;
		} ) );
		$upd['away_start']      = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['away_start'] ?? '' ) ) ? $in['away_start'] : '09:00';
		$upd['away_end']        = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['away_end'] ?? '' ) ) ? $in['away_end'] : '18:00';
		$upd['away_throttle']   = min( 72, max( 1, (int) ( $in['away_throttle'] ?? 6 ) ) );
	} elseif ( 'etiketler' === $tab ) {
		$labels = array();
		$seen   = array();
		foreach ( (array) ( $in['labels'] ?? array() ) as $l ) {
			$name = sanitize_text_field( $l['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$id = sanitize_key( $l['id'] ?? '' );
			if ( '' === $id ) {
				$id = substr( sanitize_key( remove_accents( $name ) ), 0, 30 );
				$id = '' !== $id ? $id : 'l' . strtolower( wp_generate_password( 5, false, false ) );
			}
			while ( isset( $seen[ $id ] ) ) {
				$id .= '2';
			}
			$seen[ $id ] = 1;
			$labels[]    = array(
				'id'    => $id,
				'name'  => $name,
				'color' => sanitize_hex_color( $l['color'] ?? '' ) ? sanitize_hex_color( $l['color'] ) : '#25d366',
			);
		}
		$upd['labels'] = $labels;
	} elseif ( 'kayitlar' === $tab ) {
		foreach ( array( 'agents_donations', 'agents_certificates', 'chips_bar' ) as $k ) {
			$upd[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'msg_donation_pending', 'msg_donation_done', 'msg_receipt', 'bank_text' ) as $k ) {
			$upd[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
		}
	} elseif ( 'ai' === $tab ) {
		$upd['ai_provider'] = sanitize_key( $in['ai_provider'] ?? '' );
		foreach ( array( 'ai_model', 'ai_model_bot', 'ai_model_vision', 'ai_model_audio' ) as $f ) {
			// OpenRouter "en güncel" takma adları ~ ile başlar (ör. ~deepseek/deepseek-v4-flash-latest).
			$upd[ $f ] = preg_replace( '/[^a-zA-Z0-9_.:\/@~+-]/', '', trim( (string) ( $in[ $f ] ?? '' ) ) );
		}
		$upd['ai_temperature']  = min( 1, max( 0, (float) str_replace( ',', '.', (string) ( $in['ai_temperature'] ?? 0.4 ) ) ) );
		$upd['ai_auto_suggest'] = empty( $in['ai_auto_suggest'] ) ? 0 : 1;
		$upd['bot_enabled']     = empty( $in['bot_enabled'] ) ? 0 : 1;
		$upd['bot_receipt_ai']  = empty( $in['bot_receipt_ai'] ) ? 0 : 1;
		$upd['bot_scope']       = in_array( $in['bot_scope'] ?? '', array( 'all', 'away', 'unassigned' ), true ) ? $in['bot_scope'] : 'all';
		$upd['bot_test_only']   = empty( $in['bot_test_only'] ) ? 0 : 1;
		$upd['bot_test_numbers'] = implode( ', ', array_filter( array_map( 'flxc_normalize_phone', preg_split( '/[\s,;]+/', (string) ( $in['bot_test_numbers'] ?? '' ) ) ) ) );
		$upd['bot_notify_numbers'] = implode( ', ', array_filter( array_map( 'flxc_normalize_phone', preg_split( '/[\s,;]+/', (string) ( $in['bot_notify_numbers'] ?? '' ) ) ) ) );
		$upd['bot_context_hours'] = min( 168, max( 1, (int) ( $in['bot_context_hours'] ?? 24 ) ) );
		$upd['bot_pause_hours'] = min( 720, max( 1, (int) ( $in['bot_pause_hours'] ?? 12 ) ) );
		$upd['bot_daily_limit'] = min( 500, max( 5, (int) ( $in['bot_daily_limit'] ?? 40 ) ) );
		$upd['bot_types']       = array_values( array_filter( array_map( 'absint', (array) ( $in['bot_types'] ?? array() ) ) ) );
		$upd['bot_presets']     = implode( ', ', array_slice( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) ( $in['bot_presets'] ?? '' ) ) ) ), 0, 8 ) );
		$upd['bank_iban']       = strtoupper( preg_replace( '/[^A-Za-z0-9 ]/', '', (string) ( $in['bank_iban'] ?? '' ) ) );
		$upd['donate_url']      = esc_url_raw( (string) ( $in['donate_url'] ?? '' ) );
		$upd['mem_enabled']     = empty( $in['mem_enabled'] ) ? 0 : 1;
		$upd['mem_after_hours'] = min( 168, max( 1, (int) ( $in['mem_after_hours'] ?? 24 ) ) );
		$upd['workers_max']     = min( 30, max( 1, (int) ( $in['workers_max'] ?? 20 ) ) );
		$upd['workers_reserved'] = min( 30, max( 0, (int) ( $in['workers_reserved'] ?? 5 ) ) );
		foreach ( array( 'mem_window_from' => '03:00', 'mem_window_to' => '05:00' ) as $f => $def ) {
			$upd[ $f ] = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in[ $f ] ?? '' ) ) ? (string) $in[ $f ] : $def;
		}
		$upd['bot_site_search'] = empty( $in['bot_site_search'] ) ? 0 : 1;
		$upd['bot_site_types']  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['bot_site_types'] ?? array() ) ), get_post_types( array( 'public' => true ) ) ) );
		$upd['mirror_enabled']  = empty( $in['mirror_enabled'] ) ? 0 : 1;
		$upd['bot_time_budget'] = min( 110, max( 20, (int) ( $in['bot_time_budget'] ?? 50 ) ) );
		$upd['rem_enabled']     = empty( $in['rem_enabled'] ) ? 0 : 1;
		$upd['rem_time']        = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['rem_time'] ?? '' ) ) ? (string) $in['rem_time'] : '09:00';
		$upd['rem_max']         = min( 50, max( 1, (int) ( $in['rem_max'] ?? 10 ) ) );
		$upd['email_verify']    = empty( $in['email_verify'] ) ? 0 : 1;
		$upd['verify_ttl']      = min( 30, max( 1, (int) ( $in['verify_ttl'] ?? 5 ) ) );
		$upd['verify_phone_channel'] = in_array( $in['verify_phone_channel'] ?? '', array( 'auto', 'whatsapp', 'sms' ), true ) ? $in['verify_phone_channel'] : 'auto';
		$upd['verify_msg']      = sanitize_textarea_field( $in['verify_msg'] ?? '' );
		$upd['bot_cancel_text'] = sanitize_textarea_field( $in['bot_cancel_text'] ?? '' );
		$upd['verify_max_fails'] = min( 10, max( 1, (int) ( $in['verify_max_fails'] ?? 3 ) ) );
		$upd['verify_fail_text'] = sanitize_text_field( $in['verify_fail_text'] ?? '' );
		$upd['bot_fallback_wait'] = min( 45, max( 0, (int) ( $in['bot_fallback_wait'] ?? 30 ) ) );
		$upd['notify_to_group'] = empty( $in['notify_to_group'] ) ? 0 : 1;
		$upd['staff_on']        = empty( $in['staff_on'] ) ? 0 : 1;
		$upd['ref_notify']      = empty( $in['ref_notify'] ) ? 0 : 1;
		$upd['benef_cert_notify'] = empty( $in['benef_cert_notify'] ) ? 0 : 1;
		$upd['ref_cert_notify']   = empty( $in['ref_cert_notify'] ) ? 0 : 1;
		$upd['staff_cert_max']    = min( 20, max( 0, (int) ( $in['staff_cert_max'] ?? 3 ) ) );
		$upd['bot_widgets']     = empty( $in['bot_widgets'] ) ? 0 : 1;
		$upd['bot_voice']       = empty( $in['bot_voice'] ) ? 0 : 1;
		$upd['bot_voice_max']   = min( 300, max( 10, (int) ( $in['bot_voice_max'] ?? 60 ) ) );
		$upd['mirror_group']    = preg_match( '/^[0-9-]+@g\.us$/', (string) ( $in['mirror_group'] ?? '' ) ) ? (string) $in['mirror_group'] : '';
		foreach ( array( 'bot_price_footer', 'bank_holder', 'bank_desc', 'bot_after_payment', 'bot_handoff_text', 'bot_limit_text', 'staff_donor_text', 'benef_cert_text', 'ref_cert_text', 'bot_optout_text', 'bot_site_exclude', 'bot_fallback_text' ) as $f ) {
			$upd[ $f ] = sanitize_text_field( $in[ $f ] ?? '' );
		}
		foreach ( array( 'ai_instructions', 'bot_instructions', 'bot_handoff_words' ) as $f ) {
			$upd[ $f ] = sanitize_textarea_field( $in[ $f ] ?? '' );
		}
	}
	unset( $cur );
	flxc_update_settings( $upd );
	wp_safe_redirect( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=' . $tab . '&saved=1' ) );
	exit;
}
