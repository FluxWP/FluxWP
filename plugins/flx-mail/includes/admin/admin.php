<?php
/**
 * Yönetim menüsü, ortak parçalar ve form işleyicileri.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/page-templates.php';
require_once __DIR__ . '/page-send.php';
require_once __DIR__ . '/page-queue.php';
require_once __DIR__ . '/page-log.php';
require_once __DIR__ . '/page-connections.php';
require_once __DIR__ . '/page-settings.php';
require_once __DIR__ . '/page-mailbox.php';

add_action(
	'admin_menu',
	function () {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxi_manage';
		// Posta kutusu her gün açılan bir ekran: WordPress'teki Yorumlar gibi sol menüde ayrı ana öğe.
		$unread = flxi_mailbox_enabled() ? flxi_mailbox_unread_count() + flxi_drafts_count( 'review' ) : 0;
		add_menu_page( __( 'Mailbox', 'flx-mail' ), __( 'Mailbox', 'flx-mail' ) . ( $unread ? ' <span class="awaiting-mod">' . (int) $unread . '</span>' : '' ), $cap, 'flxi-posta', 'flxi_page_mailbox', 'dashicons-email-alt', 26 );
		add_menu_page( __( 'Communication', 'flx-mail' ), __( 'Communication', 'flx-mail' ), $cap, 'flxi', 'flxi_page_templates', 'none', 58 );
		add_submenu_page( 'flxi', __( 'Email templates', 'flx-mail' ), __( 'Emails', 'flx-mail' ), $cap, 'flxi', 'flxi_page_templates' );
		add_submenu_page( 'flxi', __( 'Send', 'flx-mail' ), __( 'Send', 'flx-mail' ), $cap, 'flxi-gonder', 'flxi_page_send' );
		add_submenu_page( 'flxi', __( 'Delivery queue', 'flx-mail' ), __( 'Queue', 'flx-mail' ), $cap, 'flxi-kuyruk', 'flxi_page_queue' );
		add_submenu_page( 'flxi', __( 'Log', 'flx-mail' ), __( 'Log', 'flx-mail' ), $cap, 'flxi-kayitlar', 'flxi_page_log' );
		add_submenu_page( 'flxi', __( 'WhatsApp and SMS connections', 'flx-mail' ), __( 'Connections', 'flx-mail' ), $cap, 'flxi-baglantilar', 'flxi_page_connections' );
		add_submenu_page( 'flxi', __( 'Communication settings', 'flx-mail' ), __( 'Settings', 'flx-mail' ), $cap, 'flxi-ayarlar', 'flxi_page_settings' );
		if ( ! flxi_system_moved() ) {
			add_submenu_page( 'flxi', __( 'System check', 'flx-mail' ), __( 'System check', 'flx-mail' ), $cap, 'flxi-sistem', 'flxi_page_system' );
		}
	}
);

/** Sistem kontrolü Flux Speed'ta mı (Performans › Sistem Kontrolü). Öyleyse buradaki ekran gizlenir. */
function flxi_system_moved() {
	return function_exists( 'flxp_system_checks_url' );
}

// Eski adres (yer imi, bildirim linki) Performans'taki ekrana gider. Sayfa kayıtlı olmadığından WordPress
// erişim reddinden hemen önce (admin_page_access_denied) yönlendirilir.
add_action(
	'admin_page_access_denied',
	function () {
		if ( flxi_system_moved() && 'flxi-sistem' === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore
			wp_safe_redirect( flxp_system_checks_url() );
			exit;
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( 0 !== strpos( $page, 'flxi' ) ) {
			return;
		}
		wp_enqueue_style( 'flxi-admin', FLXI_URL . 'assets/css/admin.css', array(), FLXI_VERSION );
		if ( 'flxi-ayarlar' === $page ) {
			wp_enqueue_media();
		}
		if ( 'flxi-posta' === $page ) {
			wp_enqueue_media();
			wp_enqueue_style( 'flxi-mailbox', FLXI_URL . 'assets/css/mailbox.css', array( 'flxi-admin' ), FLXI_VERSION );
			wp_enqueue_script( 'flxi-mailbox', FLXI_URL . 'assets/js/mailbox.js', array(), FLXI_VERSION, true );
			wp_localize_script( 'flxi-mailbox', 'FLXIMB', flxi_mailbox_boot() );
		}
		if ( 'flxi-kuyruk' === $page ) {
			wp_enqueue_style( 'flxi-queue', FLXI_URL . 'assets/css/queue.css', array( 'flxi-admin' ), FLXI_VERSION );
			wp_enqueue_script( 'flxi-queue', FLXI_URL . 'assets/js/queue.js', array(), FLXI_VERSION, true );
			wp_localize_script( 'flxi-queue', 'FLXIQ', array( 'rest' => esc_url_raw( rest_url( 'flxi/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'labels' => flxi_queue_labels(), 'channels' => flxi_channels(), 'i18n' => flxi_queue_ui_strings() ) );
		}
		wp_enqueue_script( 'flxi-admin', FLXI_URL . 'assets/js/admin.js', array(), FLXI_VERSION, true );
		wp_localize_script(
			'flxi-admin',
			'FLXI',
			array(
				'rest'  => esc_url_raw( rest_url( 'flxi/v1/' ) ),
				'pdf'   => flxi_pdf_available(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'sending'   => __( 'Sending…', 'flx-mail' ),
					'sent'      => __( 'Sent', 'flx-mail' ),
					/* translators: %s: adres */
					'sentTo'    => __( 'Sent to %s. Check your inbox (and spam folder).', 'flx-mail' ),
					'failed'    => __( 'Could not send', 'flx-mail' ),
					'testing'   => __( 'Trying…', 'flx-mail' ),
					'ok'        => __( 'Success', 'flx-mail' ),
					'error'     => __( 'Error', 'flx-mail' ),
					/* translators: %s: sayı */
					'recipients' => __( '%s recipients', 'flx-mail' ),
					'noRecipients' => __( 'No recipients', 'flx-mail' ),
					'chooseLogo' => __( 'Choose logo', 'flx-mail' ),
					'use'       => __( 'Use', 'flx-mail' ),
					'confirmSend' => __( 'Start sending?', 'flx-mail' ),
					'checking'  => __( 'Checking…', 'flx-mail' ),
					'copied'    => __( 'Copied', 'flx-mail' ),
					'pdf'       => __( 'Download PDF', 'flx-mail' ),
					'pdfWait'   => __( 'Preparing PDF…', 'flx-mail' ),
					'test'      => array(
						'title'      => __( 'Test', 'flx-mail' ),
						'check'      => __( 'Check connection', 'flx-mail' ),
						'checkHelp'  => __( 'Sends no message; only checks the connection.', 'flx-mail' ),
						'to'         => __( 'Recipient number', 'flx-mail' ),
						'toHelp'     => __( 'With country code, e.g. 905301234567. Messages go to this number.', 'flx-mail' ),
						'scenarios'  => __( 'What to send?', 'flx-mail' ),
						'text'       => __( 'Text', 'flx-mail' ),
						'image'      => __( 'Image', 'flx-mail' ),
						'audio'      => __( 'Voice message', 'flx-mail' ),
						'document'   => __( 'Document (PDF)', 'flx-mail' ),
						'template'   => __( 'Layout', 'flx-mail' ),
						'message'    => __( 'Message text', 'flx-mail' ),
						'caption'    => __( 'Image / document caption', 'flx-mail' ),
						/* translators: %s: site adı */
						'sampleText' => sprintf( __( 'Hello, this is a %s test message. 🌱', 'flx-mail' ), get_bloginfo( 'name' ) ),
						/* translators: %s: site adı */
						'sampleCap'  => sprintf( __( 'Test image · %s', 'flx-mail' ), get_bloginfo( 'name' ) ),
						/* translators: %s: site adı */
						'sampleSms'  => sprintf( __( 'Test SMS · %s', 'flx-mail' ), get_bloginfo( 'name' ) ),
						'preview'    => __( 'Preview', 'flx-mail' ),
						'send'       => __( 'Send', 'flx-mail' ),
						/* translators: %d: mesaj sayısı */
						'sendN'      => __( 'Send %d messages', 'flx-mail' ),
						'needTo'     => __( 'Enter the recipient number first.', 'flx-mail' ),
						'needOne'    => __( 'Pick at least one scenario.', 'flx-mail' ),
						'notOpen'    => __( 'The number is not connected; messages will not go out. Scan the QR with "Connect" first.', 'flx-mail' ),
						'window'     => __( '24-hour rule: if the person has not written to this line in the last 24 hours, text, image, audio and documents will not go out; only approved templates. Send a message to this line from that number first.', 'flx-mail' ),
						'tplPick'    => __( 'Choose a template', 'flx-mail' ),
						'tplLoad'    => __( 'Loading templates…', 'flx-mail' ),
						'tplNone'    => __( 'No approved templates.', 'flx-mail' ),
						/* translators: %d: değişken sırası */
						'tplParam'   => __( 'Variable {{%d}}', 'flx-mail' ),
						'smsNote'    => __( 'SMS is paid; each message is deducted from the balance.', 'flx-mail' ),
						'pdfName'    => 'test-belgesi.pdf',
						'pdfMeta'    => __( '1 page · PDF', 'flx-mail' ),
						'sending'    => __( 'Sending…', 'flx-mail' ),
						'delivered'  => __( 'Sent', 'flx-mail' ),
						/* translators: 1: başarılı, 2: toplam */
						'summary'    => __( '%1$d / %2$d sent.', 'flx-mail' ),
						'checkPhone' => __( 'Check the phone.', 'flx-mail' ),
						'sms'        => __( 'SMS', 'flx-mail' ),
					),
					'samples'   => array(
						'image' => FLXI_URL . 'assets/test/ornek.jpg',
						'audio' => FLXI_URL . 'assets/test/ornek.ogg',
					),
					'wa'        => array(
						'open'          => __( 'Connected', 'flx-mail' ),
						'connecting'    => __( 'Waiting for QR', 'flx-mail' ),
						'close'         => __( 'Not connected', 'flx-mail' ),
						'missing'       => __( 'Not on the server', 'flx-mail' ),
						'unreach'       => __( 'Server unreachable', 'flx-mail' ),
						'unknown'       => __( 'Status unknown', 'flx-mail' ),
						'wait'          => __( 'Please wait…', 'flx-mail' ),
						'scan'          => __( 'Scan the QR with the phone.', 'flx-mail' ),
						'pairHint'      => __( 'Type this code in WhatsApp on the phone.', 'flx-mail' ),
						'done'          => __( 'Connected.', 'flx-mail' ),
						'okDone'        => __( 'OK', 'flx-mail' ),
						'confirmLogout' => __( 'Log the number out? Messages won\'t arrive; reconnecting needs a QR scan.', 'flx-mail' ),
						'version'       => __( 'Version', 'flx-mail' ),
						'store'         => __( 'Data store', 'flx-mail' ),
						'file'          => __( 'File', 'flx-mail' ),
						'lastKa'        => __( 'Last keep-alive', 'flx-mail' ),
						'never'         => __( 'none yet', 'flx-mail' ),
						'voice'         => __( 'Voice message converter', 'flx-mail' ),
						'yes'           => __( 'Yes', 'flx-mail' ),
						'no'            => __( 'None', 'flx-mail' ),
						'publicUrl'     => __( 'Bridge public address', 'flx-mail' ),
						'publicUrlHelp' => __( 'Keep-alive sends requests to this address. E.g. https://example.org', 'flx-mail' ),
						'keepAlive'     => __( 'Keep awake (so Hostinger doesn\'t put the app to sleep)', 'flx-mail' ),
						'db'            => __( 'Database', 'flx-mail' ),
						'dbLocked'      => __( 'The database comes from the bridge\'s environment variables; it can\'t be changed here.', 'flx-mail' ),
						'driver'        => __( 'Store', 'flx-mail' ),
						'host'          => __( 'Server', 'flx-mail' ),
						'dbName'        => __( 'Database name', 'flx-mail' ),
						'dbUser'        => __( 'User', 'flx-mail' ),
						'dbPass'        => __( 'Password', 'flx-mail' ),
						'passKeep'      => __( 'Saved · type to change', 'flx-mail' ),
						'prefix'        => __( 'Table prefix', 'flx-mail' ),
						'dbWarn'        => __( 'The password is only sent to the bridge; it isn\'t stored in WordPress.', 'flx-mail' ),
						'dbSwitch'      => __( 'Changing the store closes the numbers\' connections and they need a new QR scan. Continue?', 'flx-mail' ),
						'dbTest'        => __( 'Test database', 'flx-mail' ),
						'save'          => __( 'Save', 'flx-mail' ),
						'copy'          => __( 'Copy', 'flx-mail' ),
						'quality'       => __( 'Number quality', 'flx-mail' ),
						'qGreen'        => __( 'High', 'flx-mail' ),
						'qYellow'       => __( 'Medium', 'flx-mail' ),
						'qRed'          => __( 'Low', 'flx-mail' ),
						'hookTitle'     => __( 'Webhook (incoming messages)', 'flx-mail' ),
						'hookHelp'      => __( 'Meta for Developers › your app › WhatsApp › Configuration › Webhook: enter the address and verify token below, press "Verify and save", then subscribe to the "messages" field.', 'flx-mail' ),
						'callback'      => __( 'Callback URL', 'flx-mail' ),
						'verifyTok'     => __( 'Verify token', 'flx-mail' ),
						'lastVerify'    => __( 'Meta verification', 'flx-mail' ),
						'lastEvent'     => __( 'Last incoming event', 'flx-mail' ),
						'badSig'        => __( 'Request with a bad signature', 'flx-mail' ),
						'noSecretHit'   => __( 'Rejected (App Secret missing)', 'flx-mail' ),
						'openChats'     => __( 'Open 24-hour windows', 'flx-mail' ),
						'noSecret'      => __( 'App Secret not entered: incoming webhooks are rejected until it is entered. Add it under Edit.', 'flx-mail' ),
						'templates'     => __( 'Approved templates', 'flx-mail' ),
						'needWaba'      => __( 'WABA ID is required for templates.', 'flx-mail' ),
						'noTpl'         => __( 'No templates. Add them in WhatsApp Manager › Message templates.', 'flx-mail' ),
					),
				),
			)
		);
	}
);

/** Sayfa başlığı + sekme çubuğu. */
function flxi_page_head( $title, $sub = '', $tabs_on = true ) {
	$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxi'; // phpcs:ignore
	$tabs  = array(
		'flxi'             => __( 'Emails', 'flx-mail' ),
		'flxi-gonder'      => __( 'Send', 'flx-mail' ),
		'flxi-kuyruk'      => __( 'Queue', 'flx-mail' ),
		'flxi-kayitlar'    => __( 'Log', 'flx-mail' ),
		'flxi-baglantilar' => __( 'Connections', 'flx-mail' ),
		'flxi-ayarlar'     => __( 'Settings', 'flx-mail' ),
		'flxi-sistem'      => __( 'System check', 'flx-mail' ),
	);
	if ( flxi_system_moved() ) {
		unset( $tabs['flxi-sistem'] );
	}
	echo '<div class="wrap flxi-wrap"><div class="flxi-head"><div class="flxi-head__title"><img class="flxi-logo" src="' . esc_url( FLXI_URL . 'assets/icon.svg' ) . '" alt="" width="42" height="42"><div><h1>' . esc_html( $title ) . '</h1>';
	if ( $sub ) {
		echo '<p class="flxi-head__sub">' . wp_kses_post( $sub ) . '</p>';
	}
	echo '</div></div>';
	if ( $tabs_on ) {
		echo '<nav class="flxi-tabs" aria-label="' . esc_attr__( 'Communication', 'flx-mail' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			printf( '<a href="%s" class="flxi-tab%s"%s>%s</a>', esc_url( flxi_admin_url( $slug ) ), $slug === $page ? ' is-active' : '', $slug === $page ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}
	echo '</div>';
	flxi_notices();
}

function flxi_page_foot() {
	echo '</div>';
}

/** İşlem sonrası bildirim (flxi_msg / flxi_err sorgu parametresi). */
function flxi_notices() {
	// phpcs:disable WordPress.Security.NonceVerification
	if ( ! empty( $_GET['flxi_msg'] ) ) {
		echo '<div class="notice notice-success is-dismissible flxi-notice"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['flxi_msg'] ) ) ) . '</p></div>';
	}
	if ( ! empty( $_GET['flxi_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible flxi-notice"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['flxi_err'] ) ) ) . '</p></div>';
	}
	// phpcs:enable
}

function flxi_redirect( $url, $msg = '', $err = '' ) {
	$args = array();
	if ( $msg ) {
		$args['flxi_msg'] = rawurlencode( $msg );
	}
	if ( $err ) {
		$args['flxi_err'] = rawurlencode( $err );
	}
	wp_safe_redirect( add_query_arg( $args, $url ) );
	exit;
}

function flxi_check( $action ) {
	if ( ! flxi_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission.', 'flx-mail' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( $action );
}

function flxi_back( $default ) {
	$ref = wp_get_referer();
	$ref = $ref ? remove_query_arg( array( 'flxi_msg', 'flxi_err' ), $ref ) : $default;
	return $ref;
}

/** Açık/kapalı anahtarı (checkbox). */
function flxi_switch( $name, $on, $label = '', $attrs = '' ) {
	return '<label class="flxi-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $on, true, false ) . ' ' . $attrs . '><span class="flxi-switch__track" aria-hidden="true"></span>' . ( $label ? '<span class="flxi-switch__label">' . esc_html( $label ) . '</span>' : '' ) . '</label>';
}

function flxi_badge( $text, $kind = '' ) {
	return '<span class="flxi-badge' . ( $kind ? ' flxi-badge--' . esc_attr( $kind ) : '' ) . '">' . esc_html( $text ) . '</span>';
}

function flxi_sender_label( $sender ) {
	return 'noreply' === $sender ? flxi_setting( 'noreply_email' ) : flxi_setting( 'from_email' );
}

/* ------------------------------------------------------------------ */
/* Sistem kontrolü sayfası                                             */
/* ------------------------------------------------------------------ */

function flxi_page_system() {
	flxi_page_head( __( 'System check', 'flx-mail' ), __( 'Rows related to email, WhatsApp and SMS. Checks for all plugins are on the Appearance › System Check screen.', 'flx-mail' ) );
	add_filter( 'flx_system_checks_labels', 'flxi_system_checks_labels', 20 );
	$all  = function_exists( 'flx_system_checks_sorted' ) ? flx_system_checks_sorted() : array();
	$mine = array_values(
		array_filter(
			$all,
			function ( $c ) {
				return 0 === strpos( (string) $c['id'], 'flxi.' );
			}
		)
	);
	echo '<div class="flxi-card">';
	if ( function_exists( 'flx_system_checks_render' ) ) {
		flx_system_checks_render( array( 'checks' => $mine, 'recheck_url' => flxi_admin_url( 'flxi-sistem' ), 'heading' => false ) );
	}
	echo '</div>';
	flxi_page_foot();
}

/** Renkli menü ikonu (WordPress menü ikonlarını tek renge boyadığı için CSS ile). */
add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flxi .wp-menu-image{background:url(' . esc_url( FLXI_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}
);
