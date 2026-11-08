<?php
/**
 * WordPress çekirdeğinin e-postaları: yeni üye, parola sıfırlama, parola/e-posta değişti, yorum bildirimleri…
 * Düzenlenebilir olanların içeriği buradaki şablonla yeniden yazılır; diğerleri kurumsal çerçeveye sarılır.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_core_email_types() {
	$g      = 'WordPress';
	$sample = array(
		'ad'            => 'Ayşe',
		'kullanici_adi' => 'ayse.yilmaz',
		'eposta'        => 'ayse@example.com',
		'giris_linki'   => wp_login_url(),
	);
	$link   = home_url( '/wp-login.php?action=rp&key=ornek&login=ayse.yilmaz' );
	$post   = __( 'Our sapling planting event is complete', 'flx-mail' );
	$cvars  = array(
		'yazi'          => __( 'Post title', 'flx-mail' ),
		'yazi_linki'    => __( 'Post URL', 'flx-mail' ),
		'yorum_yazari'  => __( 'Commenter', 'flx-mail' ),
		'yorum_eposta'  => __( 'Commenter\'s email', 'flx-mail' ),
		'yorum'         => __( 'Comment text', 'flx-mail' ),
		'yorum_linki'   => __( 'Comment URL', 'flx-mail' ),
		'onayla_linki'  => __( 'Approval link', 'flx-mail' ),
		'cop_linki'     => __( 'Trash link', 'flx-mail' ),
		'spam_linki'    => __( 'Mark-as-spam link', 'flx-mail' ),
		'bekleyen_sayisi' => __( 'Number of comments awaiting approval', 'flx-mail' ),
	);
	$csample = array(
		'yazi'            => $post,
		'yazi_linki'      => home_url( '/etkinlikler/' ),
		'yorum_yazari'    => 'Mehmet K.',
		'yorum_eposta'    => 'mehmet@example.com',
		'yorum'           => __( 'Thanks to everyone who helped; we will join the next planting as a family!', 'flx-mail' ),
		'yorum_linki'     => home_url( '/etkinlikler/#comment-1' ),
		'onayla_linki'    => admin_url( 'comment.php?action=approve&c=1' ),
		'cop_linki'       => admin_url( 'comment.php?action=trash&c=1' ),
		'spam_linki'      => admin_url( 'comment.php?action=spam&c=1' ),
		'bekleyen_sayisi' => '3',
	);
	$user_vars = array(
		'ad'            => __( 'Person\'s name', 'flx-mail' ),
		'kullanici_adi' => __( 'Username', 'flx-mail' ),
		'eposta'        => __( 'Email address', 'flx-mail' ),
	);

	return array(
		'core/new_user_user'          => array(
			'label'       => __( 'New registration: set password', 'flx-mail' ),
			'group'       => $g,
			'description' => __( 'Sent to the person when someone registers or an admin adds a user. Not sent to people who sign up with Google (Settings › Google sign-in).', 'flx-mail' ),
			'recipient'   => 'user',
			'vars'        => $user_vars + array(
				'parola_linki' => __( 'Set password link', 'flx-mail' ),
				'giris_linki'  => __( 'Login page', 'flx-mail' ),
			),
			'sample'      => $sample + array( 'parola_linki' => $link ),
			'defaults'    => array(
				'subject'     => __( 'Your account is ready, set your password', 'flx-mail' ),
				'heading'     => __( 'Welcome aboard, {ad}', 'flx-mail' ),
				'body'        => __( '<p>Your {site} account has been created. Your username: <strong>{kullanici_adi}</strong></p><p>Use the button below to set your password and sign in to your account. For your security, this link expires after a short time.</p>', 'flx-mail' ),
				'button_text' => __( 'Set my password', 'flx-mail' ),
				'button_url'  => '{parola_linki}',
				'sender'      => 'noreply',
			),
		),
		'core/new_user_admin'         => array(
			'label'     => __( 'New registration (to admin)', 'flx-mail' ),
			'group'     => $g,
			'recipient' => 'admin',
			'vars'      => $user_vars + array( 'profil_linki' => __( 'User profile (dashboard)', 'flx-mail' ) ),
			'sample'    => $sample + array( 'profil_linki' => admin_url( 'user-edit.php?user_id=2' ) ),
			'defaults'  => array(
				'subject'     => __( 'New member: {kullanici_adi}', 'flx-mail' ),
				'heading'     => __( 'There is a new member on the site', 'flx-mail' ),
				'body'        => __( '<table class="flxi-kv"><tr><td>Username</td><td><strong>{kullanici_adi}</strong></td></tr><tr><td>Email</td><td>{eposta}</td></tr></table>', 'flx-mail' ),
				'button_text' => __( 'Open profile', 'flx-mail' ),
				'button_url'  => '{profil_linki}',
				'sender'      => 'noreply',
			),
		),
		'core/password_reset'         => array(
			'label'     => __( 'Password reset', 'flx-mail' ),
			'group'     => $g,
			'recipient' => 'user',
			'vars'      => $user_vars + array(
				'sifirlama_linki' => __( 'New password link', 'flx-mail' ),
				'ip'              => __( 'IP address of the request', 'flx-mail' ),
			),
			'sample'    => $sample + array( 'sifirlama_linki' => $link, 'ip' => '88.230.12.4' ),
			'defaults'  => array(
				'subject'     => __( 'Password reset request', 'flx-mail' ),
				'heading'     => __( 'Reset your password', 'flx-mail' ),
				'body'        => __( '<p>Hello {ad},</p><p>A password reset was requested for your account <strong>{kullanici_adi}</strong>. You can set a new password with the button below.</p><p>If you did not make this request, you can ignore this email; nothing will change in your account.</p>', 'flx-mail' ),
				'button_text' => __( 'Set new password', 'flx-mail' ),
				'button_url'  => '{sifirlama_linki}',
				'sender'      => 'noreply',
			),
		),
		'core/password_changed'       => array(
			'label'     => __( 'Password changed', 'flx-mail' ),
			'group'     => $g,
			'recipient' => 'user',
			'vars'      => $user_vars,
			'sample'    => $sample,
			'defaults'  => array(
				'subject'     => __( 'Your password has been changed', 'flx-mail' ),
				'heading'     => __( 'Your password has been changed', 'flx-mail' ),
				'body'        => __( '<p>Hello {ad},</p><p>The password of your {site} account was just changed.</p><p>If you did not make this change, write to {kurum_eposta} right away.</p>', 'flx-mail' ),
				'button_text' => __( 'Sign in', 'flx-mail' ),
				'button_url'  => '{giris_linki}',
				'sender'      => 'noreply',
			),
		),
		'core/email_changed'          => array(
			'label'     => __( 'Email address changed', 'flx-mail' ),
			'group'     => $g,
			'recipient' => 'user',
			'vars'      => $user_vars + array( 'yeni_eposta' => __( 'New email address', 'flx-mail' ) ),
			'sample'    => $sample + array( 'yeni_eposta' => 'ayse.yeni@example.com' ),
			'defaults'  => array(
				'subject'     => __( 'Your email address has been changed', 'flx-mail' ),
				'heading'     => __( 'Your email address has changed', 'flx-mail' ),
				'body'        => __( '<p>Hello {ad},</p><p>The email address of your {site} account has been changed to <strong>{yeni_eposta}</strong>. Notifications will be sent to this address from now on.</p><p>If you did not make this change, write to {kurum_eposta} right away.</p>', 'flx-mail' ),
				'sender'      => 'noreply',
			),
		),
		'core/password_changed_admin' => array(
			'label'     => __( 'A member changed their password (to admin)', 'flx-mail' ),
			'group'     => $g,
			'recipient' => 'admin',
			'vars'      => $user_vars,
			'sample'    => $sample,
			'defaults'  => array(
				'subject' => __( '{kullanici_adi} changed their password', 'flx-mail' ),
				'heading' => __( 'Password change', 'flx-mail' ),
				'body'    => __( '<p><strong>{kullanici_adi}</strong> ({eposta}) changed their password.</p>', 'flx-mail' ),
				'sender'  => 'noreply',
				'enabled' => 1,
			),
		),
		'core/comment_author'         => array(
			'label'       => __( 'New comment on a post (to author)', 'flx-mail' ),
			'group'       => $g,
			'recipient'   => 'admin',
			'description' => __( 'Notification of a new comment sent to the post\'s author.', 'flx-mail' ),
			'vars'        => $cvars,
			'sample'      => $csample,
			'defaults'    => array(
				'subject'     => __( 'New comment: {yazi}', 'flx-mail' ),
				'heading'     => __( 'New comment on “{yazi}”', 'flx-mail' ),
				'body'        => __( '<p><strong>{yorum_yazari}</strong> wrote:</p><blockquote>{yorum}</blockquote>', 'flx-mail' ),
				'button_text' => __( 'View comment', 'flx-mail' ),
				'button_url'  => '{yorum_linki}',
				'sender'      => 'noreply',
			),
		),
		'core/comment_moderation'     => array(
			'label'       => __( 'Comment awaiting approval', 'flx-mail' ),
			'group'       => $g,
			'recipient'   => 'admin',
			'description' => __( 'Sent to the site admin while a comment awaits approval.', 'flx-mail' ),
			'vars'        => $cvars,
			'sample'      => $csample,
			'defaults'    => array(
				'subject'     => __( 'Comment awaiting approval: {yazi}', 'flx-mail' ),
				'heading'     => __( 'A comment is waiting for your approval', 'flx-mail' ),
				'body'        => __( '<p><strong>{yorum_yazari}</strong> ({yorum_eposta}) commented on <a href="{yazi_linki}">{yazi}</a>:</p><blockquote>{yorum}</blockquote><p><a href="{cop_linki}">Trash</a> &nbsp;·&nbsp; <a href="{spam_linki}">Mark as spam</a></p><p>Total comments awaiting approval: {bekleyen_sayisi}</p>', 'flx-mail' ),
				'button_text' => __( 'Approve comment', 'flx-mail' ),
				'button_url'  => '{onayla_linki}',
				'sender'      => 'noreply',
			),
		),
		// Yalnız açma/kapama ve kurumsal çerçeve (içerik WordPress'ten gelir).
		'core/auto_update'            => flxi_core_wrap_only( __( 'Automatic update report', 'flx-mail' ), 'admin' ),
		'core/admin_email_confirm'    => flxi_core_wrap_only( __( 'Admin email change confirmation', 'flx-mail' ), 'admin', false ),
		'core/admin_email_changed'    => flxi_core_wrap_only( __( 'Admin email changed', 'flx-mail' ), 'admin' ),
		'core/email_change_confirm'   => flxi_core_wrap_only( __( 'Confirm new email address', 'flx-mail' ), 'user', false ),
		'core/privacy_request'        => flxi_core_wrap_only( __( 'Personal data request confirmation', 'flx-mail' ), 'user', false ),
		'core/privacy_export'         => flxi_core_wrap_only( __( 'Personal data export ready', 'flx-mail' ), 'user', false ),
		'core/privacy_erased'         => flxi_core_wrap_only( __( 'Personal data erased', 'flx-mail' ), 'user' ),
		'core/recovery_mode'          => flxi_core_wrap_only( __( 'Site error: recovery mode', 'flx-mail' ), 'admin', false ),
	);
}

function flxi_core_wrap_only( $label, $recipient, $can_disable = true ) {
	return array(
		'label'       => $label,
		'group'       => 'WordPress',
		'recipient'   => $recipient,
		'editable'    => false,
		'can_disable' => $can_disable,
		'description' => $can_disable ? __( 'The content is generated by WordPress; here you can turn it on or off and choose the branded layout.', 'flx-mail' ) : __( 'Cannot be disabled for security. The content is generated by WordPress and sent with the branded layout.', 'flx-mail' ),
		'defaults'    => array( 'sender' => 'noreply' ),
	);
}

/** Çekirdek fonksiyon adı → yalnız-çerçeve şablon anahtarı (flxi_core_caller_key'e ek). */
add_filter(
	'flxi_mail_context',
	function ( $ctx ) {
		$map = array(
			'wordpress:update_option_new_admin_email'               => 'core/admin_email_confirm',
			'wordpress:wp_site_admin_email_change_notification'     => 'core/admin_email_changed',
			'wordpress:send_confirmation_on_profile_email'          => 'core/email_change_confirm',
			'wordpress:wp_send_user_request'                        => 'core/privacy_request',
			'wordpress:wp_privacy_send_personal_data_export_email'  => 'core/privacy_export',
			'wordpress:_wp_privacy_send_erasure_fulfillment_notification' => 'core/privacy_erased',
			'wordpress:WP_Automatic_Updater::send_email'            => 'core/auto_update',
			'wordpress:WP_Automatic_Updater::send_plugin_theme_email' => 'core/auto_update',
			'wordpress:WP_Automatic_Updater::send_debug_email'      => 'core/auto_update',
			'wordpress:WP_Recovery_Mode_Email_Service::send_recovery_mode_email' => 'core/recovery_mode',
		);
		if ( isset( $map[ $ctx['key'] ] ) ) {
			$key             = $map[ $ctx['key'] ];
			$conf            = flxi_template( $key );
			$ctx['key']      = $key;
			$ctx['block']    = $conf['type']['can_disable'] ? ! $conf['enabled'] : false;
			$ctx['block_by'] = $ctx['block'] ? 'template' : '';
			if ( $conf['sender'] ) {
				$ctx['sender'] = $conf['sender'];
			}
		}
		return $ctx;
	},
	5
);

/**
 * Şablonu çekirdek e-posta dizisine uygular (to/subject/message/headers).
 */
function flxi_apply_core_template( array $email, $key, array $vars ) {
	$built            = flxi_build_template( $key, $vars );
	$headers          = flxi_header_lines( $email['headers'] ?? array() );
	$headers          = flxi_header_remove( $headers, 'Content-Type' );
	$headers[]        = 'Content-Type: text/html; charset=UTF-8';
	$headers[]        = 'X-FLXI-Template: ' . $key;
	$headers[]        = 'X-FLXI-Rendered: 1';
	$email['subject'] = $built['subject'];
	$email['message'] = $built['html'];
	$email['headers'] = $headers;
	return $email;
}

/** Çekirdek mesajından bağlantıyı çıkarır (WordPress'in ürettiği adresi aynen kullanmak için). */
function flxi_extract_url( $text, $needle ) {
	if ( preg_match_all( '~https?://[^\s<>"]+~', (string) $text, $m ) ) {
		foreach ( $m[0] as $u ) {
			if ( false !== strpos( $u, $needle ) ) {
				return rtrim( $u, '>.' );
			}
		}
	}
	return '';
}

function flxi_user_vars( $user ) {
	return array(
		'ad'              => flxi_user_first_name( $user ),
		'kullanici_adi'   => $user->user_login,
		'eposta'          => $user->user_email,
		'giris_linki'     => wp_login_url(),
		
	);
}

add_filter(
	'wp_new_user_notification_email',
	function ( $email, $user ) {
		$url = flxi_extract_url( $email['message'] ?? '', 'action=rp' );
		return flxi_apply_core_template( $email, 'core/new_user_user', flxi_user_vars( $user ) + array( 'parola_linki' => $url ? $url : wp_login_url() ) );
	},
	20,
	2
);

add_filter(
	'wp_new_user_notification_email_admin',
	function ( $email, $user ) {
		return flxi_apply_core_template( $email, 'core/new_user_admin', flxi_user_vars( $user ) + array( 'profil_linki' => admin_url( 'user-edit.php?user_id=' . (int) $user->ID ) ) );
	},
	20,
	2
);

add_filter(
	'retrieve_password_notification_email',
	function ( $email, $key, $login, $user ) {
		$url = flxi_extract_url( $email['message'] ?? '', 'action=rp' );
		if ( ! $url ) {
			$url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $login ), 'login' );
		}
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return flxi_apply_core_template( $email, 'core/password_reset', flxi_user_vars( $user ) + array( 'sifirlama_linki' => $url, 'ip' => $ip ) );
	},
	20,
	4
);

add_filter(
	'password_change_email',
	function ( $email, $user ) {
		$u = get_userdata( (int) $user['ID'] );
		return $u ? flxi_apply_core_template( $email, 'core/password_changed', flxi_user_vars( $u ) ) : $email;
	},
	20,
	2
);

add_filter(
	'email_change_email',
	function ( $email, $user, $userdata ) {
		$u = get_userdata( (int) $user['ID'] );
		return $u ? flxi_apply_core_template( $email, 'core/email_changed', flxi_user_vars( $u ) + array( 'eposta' => $user['user_email'], 'yeni_eposta' => $userdata['user_email'] ) ) : $email;
	},
	20,
	3
);

add_filter(
	'wp_password_change_notification_email',
	function ( $email, $user ) {
		return flxi_apply_core_template( $email, 'core/password_changed_admin', flxi_user_vars( $user ) );
	},
	20,
	2
);

/* ---- Yorum bildirimleri: metin → konu → başlık sırasıyla çağrılır. ---- */

$GLOBALS['flxi_comment_built'] = array();

function flxi_comment_vars( $comment_id ) {
	$c = get_comment( $comment_id );
	if ( ! $c ) {
		return array();
	}
	$post = get_post( $c->comment_post_ID );
	return array(
		'yazi'            => $post ? wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ) : '',
		'yazi_linki'      => $post ? get_permalink( $post ) : home_url( '/' ),
		'yorum_yazari'    => $c->comment_author ? $c->comment_author : __( 'Anonymous', 'flx-mail' ),
		'yorum_eposta'    => $c->comment_author_email,
		'yorum'           => wp_specialchars_decode( $c->comment_content, ENT_QUOTES ),
		'yorum_linki'     => get_comment_link( $c ),
		'onayla_linki'    => admin_url( 'comment.php?action=approve&c=' . (int) $comment_id . '#wpbody-content' ),
		'cop_linki'       => admin_url( 'comment.php?action=trash&c=' . (int) $comment_id . '#wpbody-content' ),
		'spam_linki'      => admin_url( 'comment.php?action=spam&c=' . (int) $comment_id . '#wpbody-content' ),
		'bekleyen_sayisi' => number_format_i18n( (int) wp_count_comments()->moderated ),
	);
}

foreach ( array( 'notification' => 'core/comment_author', 'moderation' => 'core/comment_moderation' ) as $flxi_kind => $flxi_key ) {
	add_filter(
		'comment_' . $flxi_kind . '_text',
		function ( $text, $comment_id ) use ( $flxi_key ) {
			$built                                      = flxi_build_template( $flxi_key, flxi_comment_vars( $comment_id ) );
			$GLOBALS['flxi_comment_built'][ $flxi_key ] = $built;
			return $built['html'];
		},
		20,
		2
	);
	add_filter(
		'comment_' . $flxi_kind . '_subject',
		function ( $subject ) use ( $flxi_key ) {
			return isset( $GLOBALS['flxi_comment_built'][ $flxi_key ] ) ? $GLOBALS['flxi_comment_built'][ $flxi_key ]['subject'] : $subject;
		},
		20
	);
	add_filter(
		'comment_' . $flxi_kind . '_headers',
		function ( $headers ) use ( $flxi_key ) {
			if ( ! isset( $GLOBALS['flxi_comment_built'][ $flxi_key ] ) ) {
				return $headers;
			}
			unset( $GLOBALS['flxi_comment_built'][ $flxi_key ] );
			$lines   = flxi_header_remove( flxi_header_lines( $headers ), 'Content-Type' );
			$lines[] = 'Content-Type: text/html; charset=UTF-8';
			$lines[] = 'X-FLXI-Template: ' . $flxi_key;
			$lines[] = 'X-FLXI-Rendered: 1';
			return implode( "\n", $lines ) . "\n";
		},
		20
	);
}
unset( $flxi_kind, $flxi_key );
