<?php
/**
 * Ayarlar: gönderen, gönderim yöntemi (SMTP), görünüm, Google ile giriş, WooCommerce, kayıt, DNS.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_settings() {
	$s = flxi_settings();
	$b = flxi_brand();
	flxi_page_head( __( 'Settings', 'flx-mail' ), __( 'Sender name and addresses, sending method, and email appearance.', 'flx-mail' ) );
	$n = function ( $k ) {
		return 'flxi[' . $k . ']';
	};
	$text = function ( $k, $label, $help = '', $type = 'text', $ph = '' ) use ( $s, $n ) {
		echo '<label class="flxi-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n( $k ) ) . '" value="' . esc_attr( is_array( $s[ $k ] ) ? '' : $s[ $k ] ) . '" placeholder="' . esc_attr( $ph ) . '" data-brand>' . ( $help ? '<small>' . wp_kses_post( $help ) . '</small>' : '' ) . '</label>';
	};

	echo '<nav class="flxi-subnav" aria-label="' . esc_attr__( 'Sections', 'flx-mail' ) . '">';
	foreach ( array( 'gonderen' => __( 'Sender', 'flx-mail' ), 'smtp' => __( 'Sending method', 'flx-mail' ), 'posta' => __( 'Mailbox', 'flx-mail' ), 'test' => __( 'Test', 'flx-mail' ), 'gorunum' => __( 'Appearance', 'flx-mail' ), 'google' => __( 'Google sign-in', 'flx-mail' ), 'woocommerce' => 'WooCommerce', 'kayit' => __( 'Log and bulk send', 'flx-mail' ), 'iptal' => __( 'Opt-out (STOP)', 'flx-mail' ), 'sinirlar' => __( 'Mailbox limits', 'flx-mail' ) ) as $id => $l ) {
		echo '<a href="#' . esc_attr( $id ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" autocomplete="off" class="flxi-settings">';
	wp_nonce_field( 'flxi_save_settings' );
	echo '<input type="hidden" name="action" value="flxi_save_settings">';

	// Gönderen
	echo '<section class="flxi-card" id="gonderen"><h2 class="flxi-card__title">' . esc_html__( 'Sender', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'In the inbox, your organization\'s name and address appear instead of "WordPress <wordpress@…>". Emails that expect a reply are sent from the bilgi@ address; emails that don\'t, such as passwords and notifications, are sent from the no-reply@ address. You can choose which one to use for each email separately.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-grid2">';
	$text( 'from_name', __( 'Sender name', 'flx-mail' ) );
	$text( 'from_email', __( 'Sender address', 'flx-mail' ), '', 'email' );
	$text( 'noreply_name', __( 'No-reply name', 'flx-mail' ) );
	$text( 'noreply_email', __( 'No-reply address', 'flx-mail' ), '', 'email' );
	echo '</div>';
	$text( 'reply_to', __( 'Reply-to address (optional)', 'flx-mail' ), __( 'If empty, replies go to the sender address.', 'flx-mail' ), 'email' );
	echo '<div class="flxi-field">' . flxi_switch( $n( 'force_from' ), $s['force_from'], __( 'Also change the sender address of other plugins to this (recommended; for SPF/DKIM, all emails should be sent from the same domain)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// SMTP
	$other = flxi_other_smtp_plugin();
	echo '<section class="flxi-card" id="smtp"><h2 class="flxi-card__title">' . esc_html__( 'Sending method', 'flx-mail' ) . '</h2>';
	if ( $other ) {
		/* translators: %s: eklenti */
		echo '<div class="flxi-callout flxi-callout--info">' . esc_html( sprintf( __( '%s is active; it sends the emails and the SMTP settings here are not used. Do not use both at the same time: turn one off.', 'flx-mail' ), $other ) ) . '</div>';
	}
	echo '<div class="flxi-seg flxi-seg--wide" role="radiogroup">';
	printf( '<label><input type="radio" name="%s" value="smtp"%s data-mailer> %s</label>', esc_attr( $n( 'mailer' ) ), checked( $s['mailer'], 'smtp', false ), esc_html__( 'SMTP (recommended)', 'flx-mail' ) );
	printf( '<label><input type="radio" name="%s" value="php"%s data-mailer> %s</label>', esc_attr( $n( 'mailer' ) ), checked( $s['mailer'], 'php', false ), esc_html__( 'Server PHP mail()', 'flx-mail' ) );
	echo '</div>';
	echo '<p class="flxi-muted" data-for-mailer="php">' . esc_html__( 'With emails sent via PHP mail(), Gmail shows "via srv….main-hosting.eu" and the risk of landing in spam is high.', 'flx-mail' ) . '</p>';
	echo '<div data-for-mailer="smtp"><div class="flxi-callout"><strong>Hostinger:</strong> ' . esc_html__( 'In hPanel › Emails › Email accounts, create the bilgi@ account (a separate account or an alias for no-reply@). Server smtp.hostinger.com, port 465, SSL; the username is the full email address.', 'flx-mail' ) . ' <button type="button" class="button button-small" data-preset-hostinger>' . esc_html__( 'Fill in Hostinger settings', 'flx-mail' ) . '</button></div>';
	echo '<div class="flxi-grid3">';
	$text( 'smtp_host', __( 'SMTP server', 'flx-mail' ), '', 'text', 'smtp.hostinger.com' );
	$text( 'smtp_port', __( 'Port', 'flx-mail' ), '', 'number' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Encryption', 'flx-mail' ) . '</span><select name="' . esc_attr( $n( 'smtp_secure' ) ) . '">';
	foreach ( array( 'ssl' => 'SSL (465)', 'tls' => 'TLS (587)', '' => __( 'None', 'flx-mail' ) ) as $v => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['smtp_secure'], $v, false ), esc_html( $l ) );
	}
	echo '</select></label></div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'smtp_auth' ), $s['smtp_auth'], __( 'Authentication', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid2">';
	$text( 'smtp_user', __( 'Username (bilgi@)', 'flx-mail' ), '', 'text', flxi_setting( 'from_email' ) );
	$pw  = flxi_smtp_password();
	$cst = defined( 'FLXI_SMTP_PASS' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Password', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'smtp_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $cst ? __( 'defined in wp-config.php', 'flx-mail' ) : ( $pw ? '••••••••' . ' · ' . __( 'type to change', 'flx-mail' ) : '' ) ) . '"' . disabled( $cst, true, false ) . '><small>' . esc_html__( 'Stored encrypted, not shown in the panel.', 'flx-mail' ) . '</small></label>';
	$text( 'smtp_nr_user', __( 'Separate user for no-reply (optional)', 'flx-mail' ), __( 'If no-reply@ is a separate account. If empty, no-reply emails are also sent with the account above (no-reply@ must be an alias of the bilgi@ account).', 'flx-mail' ), 'text', flxi_setting( 'noreply_email' ) );
	$pw2 = flxi_smtp_password( true );
	echo '<label class="flxi-field"><span>' . esc_html__( 'No-reply password', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'smtp_nr_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $pw2 ? '••••••••' . ' · ' . __( 'type to change', 'flx-mail' ) : '' ) . '"></label>';
	echo '</div>';
	foreach ( flxi_smtp_account_notes() as $note ) {
		echo '<div class="flxi-callout flxi-callout--' . esc_attr( $note['level'] ) . '">' . esc_html( $note['text'] ) . '</div>';
	}
	echo '</div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'set_sender' ), $s['set_sender'], __( 'Set Return-Path to the sender address (recommended for SPF alignment)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// Posta kutusu (IMAP)
	$ipw = defined( 'FLXI_IMAP_PASS' ) ? 'const' : ( '' !== flxi_decrypt( $s['imap_pass'] ) ? 'own' : '' );
	echo '<section class="flxi-card" id="posta"><h2 class="flxi-card__title">' . esc_html__( 'Mailbox', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Email arriving at bilgi@ and email sent from webmail appear on the Mailbox screen in the left menu, where you can reply. The site connects over IMAP and fetches a few emails every few minutes (light on shared hosting). Attachments are not stored on the site; they are read from the server when opened.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'mailbox_enabled' ), $s['mailbox_enabled'], __( 'Enable mailbox', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid3">';
	$text( 'imap_host', __( 'IMAP server', 'flx-mail' ), '', 'text', 'imap.hostinger.com' );
	$text( 'imap_port', __( 'Port', 'flx-mail' ), '', 'number' );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Encryption', 'flx-mail' ) . '</span><select name="' . esc_attr( $n( 'imap_secure' ) ) . '">';
	foreach ( array( 'ssl' => 'SSL (993)', 'tls' => 'STARTTLS (143)', '' => __( 'None', 'flx-mail' ) ) as $v => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['imap_secure'], $v, false ), esc_html( $l ) );
	}
	echo '</select></label></div>';
	echo '<div class="flxi-grid2">';
	$text( 'imap_user', __( 'Username', 'flx-mail' ), __( 'If empty, the SMTP user (bilgi@) is used.', 'flx-mail' ), 'text', $s['smtp_user'] ? $s['smtp_user'] : $s['from_email'] );
	echo '<label class="flxi-field"><span>' . esc_html__( 'Password', 'flx-mail' ) . '</span><input type="password" name="' . esc_attr( $n( 'imap_pass' ) ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( 'const' === $ipw ? __( 'defined in wp-config.php', 'flx-mail' ) : ( 'own' === $ipw ? '••••••••' . ' · ' . __( 'type to change', 'flx-mail' ) : __( 'If empty, the SMTP password is used', 'flx-mail' ) ) ) . '"' . disabled( 'const' === $ipw, true, false ) . '><small>' . esc_html__( 'Stored encrypted, not shown in the panel.', 'flx-mail' ) . '</small></label>';
	$text( 'mailbox_days', __( 'How many days of email to fetch on first setup', 'flx-mail' ), '', 'number' );
	$text( 'mailbox_keep_days', __( 'How many days to keep on the site', 'flx-mail' ), __( 'Emails on the server are not deleted; only the copy on the site.', 'flx-mail' ), 'number' );
	echo '</div>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Signature (optional)', 'flx-mail' ) . '</span><textarea name="' . esc_attr( $n( 'mailbox_signature' ) ) . '" rows="3" placeholder="{ad_soyad}">' . esc_textarea( $s['mailbox_signature'] ) . '</textarea><small>' . esc_html__( 'Added to the end of emails written from the mailbox; the organization info with logo comes below it. {ad_soyad} becomes the writer\'s name. If empty, nothing is added.', 'flx-mail' ) . '</small></label>';
	echo '<div class="flxi-inline"><button type="button" class="button" data-mailbox-test>' . esc_html__( 'Test connection', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	echo '<p class="flxi-muted">' . esc_html__( 'Save first, then test.', 'flx-mail' ) . '</p>';
	echo '</section>';

	echo '<div class="flxi-sticky"><button class="button button-primary button-large">' . esc_html__( 'Save settings', 'flx-mail' ) . '</button></div>';

	// Test + DNS
	$last = get_option( 'flxi_last_test' );
	$dns  = get_option( 'flxi_dns_check' );
	echo '<section class="flxi-card" id="test"><h2 class="flxi-card__title">' . esc_html__( 'Test', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Save the settings first, then test.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-inline"><input type="email" id="flxi-test-to" value="' . esc_attr( wp_get_current_user()->user_email ) . '" aria-label="' . esc_attr__( 'Test address', 'flx-mail' ) . '"><select id="flxi-test-sender" aria-label="' . esc_attr__( 'Sender', 'flx-mail' ) . '"><option value="info">' . esc_html( $s['from_email'] ) . '</option><option value="noreply">' . esc_html( $s['noreply_email'] ) . '</option></select><button type="button" class="button" data-test-settings>' . esc_html__( 'Send test email', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div>';
	if ( is_array( $last ) ) {
		/* translators: 1: tarih, 2: adres */
		echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Last test: %1$s · %2$s', 'flx-mail' ), wp_date( 'd.m.Y H:i', (int) $last['at'] ), $last['to'] ) ) . ' · ' . ( $last['ok'] ? '<span class="flxi-ok">' . esc_html__( 'sent', 'flx-mail' ) . '</span>' : '<span class="flxi-err">' . esc_html( $last['error'] ) . '</span>' ) . '</p>';
	}
	echo '<h3 id="dns">' . esc_html__( 'Domain records (SPF / DMARC)', 'flx-mail' ) . '</h3><p class="flxi-muted">' . esc_html__( 'To keep emails out of spam, the domain\'s DNS must have SPF, DKIM and DMARC records. When you create an email account on Hostinger, SPF and DKIM are usually added automatically.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-inline"><button type="button" class="button" data-dns-check>' . esc_html__( 'Check DNS', 'flx-mail' ) . '</button><span class="flxi-status" aria-live="polite"></span></div><dl class="flxi-dl" data-dns-out>';
	if ( is_array( $dns ) ) {
		echo '<dt>SPF</dt><dd>' . ( $dns['spf'] ? '<code>' . esc_html( $dns['spf'] ) . '</code>' : '<span class="flxi-err">' . esc_html__( 'none', 'flx-mail' ) . '</span>' ) . '</dd>';
		echo '<dt>DMARC</dt><dd>' . ( $dns['dmarc'] ? '<code>' . esc_html( $dns['dmarc'] ) . '</code>' : '<span class="flxi-muted">' . esc_html__( 'none', 'flx-mail' ) . '</span>' ) . '</dd>';
		echo '<dt>MX</dt><dd>' . esc_html( implode( ', ', (array) $dns['mx'] ) ) . '</dd>';
	}
	echo '</dl></section>';

	// Görünüm
	echo '<section class="flxi-card" id="gorunum"><h2 class="flxi-card__title">' . esc_html__( 'Appearance', 'flx-mail' ) . '</h2>';
	echo '<div class="flxi-split"><div>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Logo', 'flx-mail' ) . '</span><div class="flxi-media" data-media><img src="' . esc_url( $b['logo'] ) . '" alt="" data-media-img><input type="hidden" name="' . esc_attr( $n( 'logo' ) ) . '" value="' . esc_attr( $s['logo'] ) . '" data-media-input data-brand><button type="button" class="button" data-media-pick>' . esc_html__( 'Change', 'flx-mail' ) . '</button><button type="button" class="button-link" data-media-clear>' . esc_html__( 'Default', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'PNG or JPG (email clients do not display SVG). Default: the site\'s own logo (organisation profile, Flux SEO or the theme\'s Site Logo). Without a logo the site name is written.', 'flx-mail' ) . '</small></div>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Dark background logo', 'flx-mail' ) . '</span><div class="flxi-media flxi-media--dark" data-media><img src="' . esc_url( $b['logo_white'] ) . '" alt="" data-media-img><input type="hidden" name="' . esc_attr( $n( 'logo_white' ) ) . '" value="' . esc_attr( $s['logo_white'] ) . '" data-media-input data-brand><button type="button" class="button" data-media-pick>' . esc_html__( 'Change', 'flx-mail' ) . '</button><button type="button" class="button-link" data-media-clear>' . esc_html__( 'Default', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'Shown in dark mode (Apple Mail, iOS). Default: the light logo from the organisation profile; without one the normal logo stays.', 'flx-mail' ) . '</small></div>';
	echo '<div class="flxi-grid2">';
	$text( 'logo_width', __( 'Logo width (px)', 'flx-mail' ), '', 'number' );
	echo '</div><div class="flxi-grid4">';
	foreach ( array( 'color_primary' => __( 'Primary color', 'flx-mail' ), 'color_dark' => __( 'Dark color', 'flx-mail' ), 'color_bg' => __( 'Background', 'flx-mail' ), 'color_text' => __( 'Heading color', 'flx-mail' ) ) as $k => $l ) {
		echo '<label class="flxi-field flxi-color"><span>' . esc_html( $l ) . '</span><input type="color" name="' . esc_attr( $n( $k ) ) . '" value="' . esc_attr( flxi_hex( $s[ $k ], flxi_theme_color( $k ) ) ) . '" data-brand></label>';
	}
	echo '</div>';
	echo '<h3>' . esc_html__( 'Footer (organization)', 'flx-mail' ) . '</h3><p class="flxi-muted">' . esc_html__( 'Empty fields are taken from SEO › Organization and social settings.', 'flx-mail' ) . '</p>';
	$text( 'org_name', __( 'Organization name', 'flx-mail' ), '', 'text', $b['org'] );
	$text( 'tagline', __( 'Tagline', 'flx-mail' ) );
	$text( 'address', __( 'Address', 'flx-mail' ), '', 'text', $b['address'] );
	echo '<div class="flxi-grid2">';
	$text( 'phone', __( 'Phone', 'flx-mail' ), '', 'text', $b['phone'] );
	$text( 'contact_email', __( 'Contact email', 'flx-mail' ), '', 'email', $b['email'] );
	echo '</div>';
	$text( 'website', __( 'Website', 'flx-mail' ), '', 'url', home_url( '/' ) );
	echo '<div class="flxi-grid2">';
	foreach ( flxi_social_services() as $k => $l ) {
		echo '<label class="flxi-field"><span>' . esc_html( $l ) . '</span><input type="url" name="' . esc_attr( $n( 'social' ) ) . '[' . esc_attr( $k ) . ']" value="' . esc_attr( $s['social'][ $k ] ?? '' ) . '" placeholder="' . esc_attr( $b['social'][ $k ] ?? 'https://' ) . '" data-brand></label>';
	}
	echo '</div>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Footnote (optional)', 'flx-mail' ) . '</span><textarea name="' . esc_attr( $n( 'footer_note' ) ) . '" rows="2" data-brand>' . esc_textarea( $s['footer_note'] ) . '</textarea><small>' . esc_html__( 'E.g. the association registration number or a data protection (KVKK) note. Shown in small print at the very bottom of all emails.', 'flx-mail' ) . '</small></label>';
	echo '<div class="flxi-field"><span>' . esc_html__( 'Footer illustration', 'flx-mail' ) . '</span><div class="flxi-arts">';
	foreach ( flxi_footer_arts() as $ak => $al ) {
		$img = 'none' === $ak ? '' : '<img src="' . esc_url( FLXI_URL . 'assets/img/footer/' . $ak . '.png' ) . '" alt="">';
		echo '<label class="flxi-art"><input type="radio" name="' . esc_attr( $n( 'footer_art' ) ) . '" value="' . esc_attr( $ak ) . '"' . checked( $s['footer_art'], $ak, false ) . ' data-brand><span class="flxi-art__img">' . $img . '</span><span>' . esc_html( $al ) . '</span></label>'; // phpcs:ignore
	}
	echo '</div><small>' . esc_html__( 'A tree silhouette at the very bottom of every email. A darker drawing is shown in dark mode.', 'flx-mail' ) . '</small></div>';
	echo '<h3>' . esc_html__( 'Images', 'flx-mail' ) . '</h3>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'email_images_fix' ), $s['email_images_fix'], __( 'Make images work in every email app (WebP/AVIF → JPG/PNG, full URLs)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'embed_images' ), $s['embed_images'], __( 'Embed the site\'s images in the email (recipients see them without clicking "show images")', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<h3>' . esc_html__( 'Emails from other plugins', 'flx-mail' ) . '</h3>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'wrap_plain' ), $s['wrap_plain'], __( 'Wrap plain-text emails in the branded template', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'wrap_html' ), $s['wrap_html'], __( 'Also wrap simple HTML emails (those with their own full design are left untouched)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</div><div class="flxi-split__preview"><div class="flxi-preview"><div class="flxi-preview__bar"><strong>' . esc_html__( 'Preview', 'flx-mail' ) . '</strong><div class="flxi-seg" role="group"><button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text">' . esc_html__( 'Desktop', 'flx-mail' ) . '</span></button><button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text">' . esc_html__( 'Phone', 'flx-mail' ) . '</span></button><button type="button" data-device="dark" aria-pressed="false"><span class="dashicons dashicons-admin-appearance"></span><span class="screen-reader-text">' . esc_html__( 'Dark mode', 'flx-mail' ) . '</span></button></div></div><div class="flxi-preview__frame"><iframe title="' . esc_attr__( 'Email preview', 'flx-mail' ) . '" data-preview-frame data-brand-preview sandbox="allow-same-origin"></iframe></div><p class="flxi-muted flxi-preview__note">' . esc_html__( 'Appearance changes are applied to all emails when you save.', 'flx-mail' ) . '</p></div></div></div>';
	echo '</section>';

	// Google
	$social = flxi_active_social_plugins();
	echo '<section class="flxi-card" id="google"><h2 class="flxi-card__title">' . esc_html__( 'Google sign-in', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'Because Google has already verified the email address, people who sign up with Google do not receive the "set your password / login details" email, and their account is treated as verified.', 'flx-mail' ) . '</p>';
	/* translators: %s: eklenti listesi */
	echo '<p>' . ( $social ? esc_html( sprintf( __( 'Detected plugin: %s', 'flx-mail' ), implode( ', ', $social ) ) ) : esc_html__( 'No known Google sign-in plugin was detected; verification applies when a supported plugin records a Google login (or via the flxi_user_is_social filter).', 'flx-mail' ) ) . '</p>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_block_core' ), $s['social_block_core'], __( 'Do not send the WordPress "login details / set password" email', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_block_wc' ), $s['social_block_wc'], __( 'Do not send the WooCommerce "your account has been created" email', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'social_verified' ), $s['social_verified'], __( 'Mark the email as verified (for verification plugins)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '</section>';

	// WooCommerce
	echo '<section class="flxi-card" id="woocommerce"><h2 class="flxi-card__title">WooCommerce</h2>';
	if ( class_exists( 'WooCommerce' ) ) {
		echo '<p class="flxi-muted">' . esc_html__( 'WooCommerce emails use their own template. You can apply our logo, colors, sender name and organization footer to WooCommerce › Settings › Emails with one click.', 'flx-mail' ) . '</p>';
		$at = (int) get_option( 'flxi_wc_branded' );
		if ( $at ) {
			/* translators: %s: tarih */
			echo '<p>' . esc_html( sprintf( __( 'Last applied: %s', 'flx-mail' ), wp_date( 'd.m.Y H:i', $at ) ) ) . '</p>';
		}
		echo '</form>';
		echo flxi_post_button( 'flxi_wc_brand', array(), __( 'Apply appearance to WooCommerce', 'flx-mail' ), '', 'button' ); // phpcs:ignore
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-settings flxi-settings--tail">';
		wp_nonce_field( 'flxi_save_settings' );
		echo '<input type="hidden" name="action" value="flxi_save_settings"><input type="hidden" name="partial" value="1">';
	} else {
		echo '<p class="flxi-muted">' . esc_html__( 'WooCommerce is not active.', 'flx-mail' ) . '</p>';
	}
	echo '</section>';

	// Kayıt
	echo '<section class="flxi-card" id="kayit"><h2 class="flxi-card__title">' . esc_html__( 'Log and bulk send', 'flx-mail' ) . '</h2>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'log_enabled' ), $s['log_enabled'], __( 'Log outgoing emails and messages', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'log_body' ), $s['log_body'], __( 'Also store the content (to view the email in the log)', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid2">';
	$text( 'log_days', __( 'Keep log for how many days', 'flx-mail' ), '', 'number' );
	$text( 'bulk_per_run', __( 'Number to send per round in bulk email', 'flx-mail' ), __( 'Hostinger shared hosting has an hourly sending limit; the default is 20 (one round per minute).', 'flx-mail' ), 'number' );
	echo '</div>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'bulk_unsubscribe' ), $s['bulk_unsubscribe'], __( 'Add an "unsubscribe" link to bulk emails', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	$unsub = flxi_unsubscribed();
	/* translators: %s: sayı */
	echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Unsubscribed: %s people', 'flx-mail' ), number_format_i18n( count( $unsub ) ) ) ) . '</p>';
	echo '</section>';

	// IPTAL: tanıtım mesajlarından çıkış
	echo '<section class="flxi-card" id="iptal"><h2 class="flxi-card__title">' . esc_html__( 'Opt-out (STOP)', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'A line at the bottom of promotional WhatsApp and SMS messages tells people how to stop them. When someone replies with only the opt-out word, promotional messages are no longer sent to them on that channel. Information messages (event details, receipts, certificates) and replies to their own messages still go out. If they reply with the start word, they get promotional messages again.', 'flx-mail' ) . '</p>';
	echo '<div class="flxi-field">' . flxi_switch( $n( 'optout_enabled' ), $s['optout_enabled'], __( 'Use opt-out', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-grid2"><div class="flxi-field">' . flxi_switch( $n( 'optout_footer_wa' ), $s['optout_footer_wa'], __( 'Add the opt-out line to promotional WhatsApp messages', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
	echo '<div class="flxi-field">' . flxi_switch( $n( 'optout_footer_sms' ), $s['optout_footer_sms'], __( 'Add the opt-out line to promotional SMS (costs extra characters; Netgsm İYS rules apply separately)', 'flx-mail' ) ) . '</div></div>'; // phpcs:ignore
	$text( 'optout_text', __( 'Opt-out line', 'flx-mail' ), __( 'Leave empty to use the default in the site language.', 'flx-mail' ), 'text', __( 'If you don\'t want messages like this, reply STOP.', 'flx-mail' ) );
	echo '<div class="flxi-grid2">';
	/* translators: %s: varsayılan kelimeler */
	$text( 'optout_keywords', __( 'Opt-out words (comma separated)', 'flx-mail' ), esc_html( sprintf( __( 'The whole message must be the word; "I want to cancel my donation" is not counted. Default: %s', 'flx-mail' ), flxi_optout_default_words() ) ), 'text', flxi_optout_default_words() );
	/* translators: %s: varsayılan kelimeler */
	$text( 'optin_keywords', __( 'Start words (comma separated)', 'flx-mail' ), esc_html( sprintf( __( 'Default: %s', 'flx-mail' ), flxi_optin_default_words() ) ), 'text', flxi_optin_default_words() );
	echo '</div>';
	$text( 'optout_reply', __( 'Reply after opting out', 'flx-mail' ), __( 'Sent once on WhatsApp. Leave empty for the default; type - to send nothing.', 'flx-mail' ), 'text', flxi_optout_reply_text( 'out' ) );
	$text( 'optin_reply', __( 'Reply after starting again', 'flx-mail' ), '', 'text', flxi_optout_reply_text( 'in' ) );
	/* translators: 1: WhatsApp sayısı, 2: SMS sayısı */
	echo '<p class="flxi-muted"><strong>' . esc_html( sprintf( __( 'Opted out: %1$s on WhatsApp, %2$s on SMS', 'flx-mail' ), number_format_i18n( flxi_optout_count( 'whatsapp' ) ), number_format_i18n( flxi_optout_count( 'sms' ) ) ) ) . '</strong></p>';
	echo '<p><a href="#iptal-liste">' . esc_html__( 'See the list', 'flx-mail' ) . '</a></p>';
	echo '</section>';

	// Posta kutusu sınırları
	echo '<section class="flxi-card" id="sinirlar"><h2 class="flxi-card__title">' . esc_html__( 'Mailbox limits', 'flx-mail' ) . '</h2>';
	echo '<p class="flxi-muted">' . esc_html__( 'The limits of your Hostinger email plan. The send counter uses them; bulk sends never exceed the daily limit, and the rest go out as allowance frees up. If you hit the limits often, upgrade to a higher email plan at Hostinger and update the values here.', 'flx-mail' ) . '</p>';
	echo flxi_quota_meter_html(); // phpcs:ignore
	$full = get_option( 'flxi_quota_full_days', array() );
	$full = is_array( $full ) ? array_filter( $full, function ( $x ) {
		return strtotime( $x ) > time() - 30 * DAY_IN_SECONDS;
	} ) : array();
	if ( $full ) {
		/* translators: %s: number of days */
		echo '<p class="flxi-notice-inline">' . esc_html( sprintf( _n( 'The daily limit was hit on %s day in the last 30 days.', 'The daily limit was hit on %s days in the last 30 days.', count( $full ), 'flx-mail' ), number_format_i18n( count( $full ) ) ) ) . '</p>';
	}
	echo '<div class="flxi-grid2">';
	$text( 'limit_daily', __( 'Daily sending (per 24 hours, per mailbox)', 'flx-mail' ), __( 'Each To, Cc and Bcc recipient counts as one. 0 = no limit.', 'flx-mail' ), 'number' );
	$text( 'limit_reserve', __( 'Reserve for individual emails', 'flx-mail' ), __( 'Bulk sending leaves this many sends for password reset, donation and certificate emails.', 'flx-mail' ), 'number' );
	$text( 'limit_recipients', __( 'Recipients per message (To, Cc, Bcc)', 'flx-mail' ), '', 'number' );
	$text( 'limit_size_mb', __( 'Outgoing email size (MB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_attach_mb', __( 'Attachment size (MB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_storage_gb', __( 'Storage per mailbox (GB)', 'flx-mail' ), '', 'number' );
	$text( 'limit_mailbox_emails', __( 'Emails per mailbox', 'flx-mail' ), '', 'number' );
	$text( 'limit_aliases', __( 'Aliases per mailbox', 'flx-mail' ), '', 'number' );
	$text( 'limit_forwarders', __( 'Mailbox forwarders', 'flx-mail' ), '', 'number' );
	echo '</div>';
	echo '</section>';

	echo '<div class="flxi-actions"><button class="button button-primary button-large">' . esc_html__( 'Save settings', 'flx-mail' ) . '</button></div>';
	echo '</form>';
	flxi_optout_admin_list();
	flxi_page_foot();
}

add_action(
	'admin_post_flxi_save_settings',
	function () {
		flxi_check( 'flxi_save_settings' );
		$in    = (array) wp_unslash( $_POST['flxi'] ?? array() ); // phpcs:ignore
		$d     = flxi_defaults();
		$clean = array();
		// İşaret kutuları formda yoksa kapalıdır (yalnız bu formdaki bölümler için).
		$bools = array( 'force_from', 'set_sender', 'smtp_auth', 'wrap_plain', 'wrap_html', 'email_images_fix', 'embed_images', 'social_block_core', 'social_block_wc', 'social_verified', 'log_enabled', 'log_body', 'bulk_unsubscribe', 'mailbox_enabled', 'optout_enabled', 'optout_footer_wa', 'optout_footer_sms' );
		$partial = ! empty( $_POST['partial'] );
		foreach ( $d as $k => $def ) {
			if ( in_array( $k, $bools, true ) ) {
				if ( $partial && ! array_key_exists( $k, $in ) && ! in_array( $k, array( 'log_enabled', 'log_body', 'bulk_unsubscribe', 'optout_enabled', 'optout_footer_wa', 'optout_footer_sms' ), true ) ) {
					continue;
				}
				$clean[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				continue;
			}
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			if ( in_array( $k, array( 'smtp_pass', 'smtp_nr_pass', 'imap_pass' ), true ) ) {
				$v = trim( (string) $in[ $k ] );
				if ( '' !== $v ) {
					$clean[ $k ] = flxi_encrypt( $v );
				}
				continue;
			}
			try {
				$clean[ $k ] = flxi_sanitize_setting( $k, $in[ $k ] );
			} catch ( Exception $e ) {
				flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ), '', $e->getMessage() );
			}
		}
		foreach ( array( 'from_name', 'noreply_name' ) as $k ) {
			if ( isset( $clean[ $k ] ) && '' === $clean[ $k ] ) {
				$clean[ $k ] = $d[ $k ];
			}
		}
		foreach ( array( 'from_email', 'noreply_email' ) as $k ) {
			if ( isset( $clean[ $k ] ) && '' === $clean[ $k ] ) {
				$clean[ $k ] = $d[ $k ];
			}
		}
		if ( isset( $clean['log_days'] ) ) {
			$clean['log_days'] = max( 1, min( 3650, (int) $clean['log_days'] ) );
		}
		foreach ( array( 'limit_daily', 'limit_recipients', 'limit_size_mb', 'limit_attach_mb', 'limit_storage_gb', 'limit_mailbox_emails', 'limit_aliases', 'limit_forwarders', 'limit_reserve' ) as $k ) {
			if ( isset( $clean[ $k ] ) ) {
				$clean[ $k ] = max( 0, min( 1000000, (int) $clean[ $k ] ) );
			}
		}
		if ( isset( $clean['limit_reserve'], $clean['limit_daily'] ) && $clean['limit_daily'] ) {
			$clean['limit_reserve'] = min( $clean['limit_reserve'], max( 0, $clean['limit_daily'] - 1 ) );
		}
		if ( isset( $clean['bulk_per_run'] ) ) {
			$clean['bulk_per_run'] = max( 1, min( 500, (int) $clean['bulk_per_run'] ) );
		}
		flxi_update_settings( $clean );
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi-ayarlar' ) ), __( 'Settings saved.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_wc_brand',
	function () {
		flxi_check( 'flxi_wc_brand' );
		flxi_wc_apply_brand();
		flxi_redirect( flxi_admin_url( 'flxi-ayarlar' ) . '#woocommerce', __( 'Logo, colors, sender and footer applied to WooCommerce emails.', 'flx-mail' ) );
	}
);

/** IPTAL listesi + elle ekleme (ayar formunun dışında; ayrı formlar). */
function flxi_optout_admin_list() {
	$rows = flxi_optout_list( '', 200 );
	$chs  = flxi_channels();
	echo '<section class="flxi-card" id="iptal-liste"><h2 class="flxi-card__title">' . esc_html__( 'Opted-out people', 'flx-mail' ) . '</h2>';
	if ( $rows ) {
		echo '<table class="flxi-optouts"><thead><tr><th>' . esc_html__( 'Phone', 'flx-mail' ) . '</th><th>' . esc_html__( 'Channel', 'flx-mail' ) . '</th><th>' . esc_html__( 'How', 'flx-mail' ) . '</th><th>' . esc_html__( 'Date', 'flx-mail' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$how = 'inbound' === $r['source'] ? ( '' !== $r['keyword'] ? sprintf( /* translators: %s: kelime */ __( 'Replied "%s"', 'flx-mail' ), $r['keyword'] ) : __( 'Replied', 'flx-mail' ) ) : __( 'Added by staff', 'flx-mail' );
			echo '<tr><td>+' . esc_html( $r['recipient'] ) . '</td><td>' . esc_html( $chs[ $r['channel'] ] ?? $r['channel'] ) . '</td><td>' . esc_html( $how ) . '</td><td>' . esc_html( flx_date( strtotime( $r['created_at'] . ' UTC' ), 'short' ) ) . '</td><td>';
			echo flxi_post_button( 'flxi_optout_remove', array( 'channel' => $r['channel'], 'recipient' => $r['recipient'] ), __( 'Remove', 'flx-mail' ), __( 'Promotional messages will be sent to this person again. Remove only if they asked for it.', 'flx-mail' ) ); // phpcs:ignore
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="flxi-muted">' . esc_html__( 'Nobody has opted out yet.', 'flx-mail' ) . '</p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-optout-add">';
	wp_nonce_field( 'flxi_optout' );
	echo '<input type="hidden" name="action" value="flxi_optout_add">';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Add a phone by hand', 'flx-mail' ) . '</span><input type="tel" name="recipient" placeholder="0532 123 45 67" required></label>';
	echo '<label class="flxi-field"><span>' . esc_html__( 'Channel', 'flx-mail' ) . '</span><select name="channel"><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option></select></label>';
	echo '<button class="button">' . esc_html__( 'Add', 'flx-mail' ) . '</button></form>';
	echo '</section>';
}
