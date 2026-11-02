<?php
/**
 * Flux Forms 2.1.0: form e-mails through Flux Mail.
 *
 * When Flux Mail is active, the e-mails visitors and admins get from forms (new message, "we
 * received your message", reply, subscription, survey, registration, new user, password recovery)
 * are registered templates (`flxi_email_types`, group "Flux Forms") that can be edited or switched off
 * under İletişim › E-postalar, and they are sent with `flxi_send_email()` in the shared design.
 * The submitted values table and the tracking code that Flux Forms builds are passed as {mesaj_html}.
 * Without Flux Mail (or with the filter `flxf_use_iletisim` returning false) Flux Forms sends its own
 * e-mails as before. Diagnostic e-mails (test mail, problem reports, weekly report…) always stay on
 * Flux Forms's own sender.
 *
 * GPL v2 or later.
 *
 * @package flx-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Iletisim {

	const GROUP = 'Flux Forms';

	public static function init() {
		add_filter( 'flxi_email_types', array( __CLASS__, 'register' ) );
	}

	/** Send through Flux Mail? */
	public static function active() {
		return function_exists( 'flxi_send_email' ) && (bool) apply_filters( 'flxf_use_iletisim', true );
	}

	/** Flux Forms e-mail state => template key. Unlisted states keep Flux Forms's own sender. */
	public static function map() {
		return array(
			'newMessage'                        => 'flx-forms/new-message',
			'notiToUserFormFilled'              => 'flx-forms/form-received',
			'notiToUserFormFilled_TrackingCode' => 'flx-forms/form-received',
			'respRecivedMessage'                => 'flx-forms/reply-received',
			'subscribe'                         => 'flx-forms/subscribed',
			'survey'                            => 'flx-forms/survey-done',
			'register'                          => 'flx-forms/register',
			'newUser'                           => 'flx-forms/new-user',
			'recovery'                          => 'flx-forms/password-recovery',
		);
	}

	public static function key_for( $state ) {
		$m = self::map();
		return is_string( $state ) && isset( $m[ $state ] ) ? $m[ $state ] : '';
	}

	public static function types() {
		$vars   = array(
			'konu'       => __( 'Subject made by Flux Forms (with the tracking code)', 'flx-forms' ),
			'mesaj_html' => __( 'Message: tracking code and submitted values (HTML)', 'flx-forms' ),
			'takip_kodu' => __( 'Tracking code', 'flx-forms' ),
			'link'       => __( 'Link to the message / response page', 'flx-forms' ),
			'kullanici'  => __( 'User name (registration, password recovery)', 'flx-forms' ),
		);
		$sample = array(
			'konu'       => __( 'We have received your message', 'flx-forms' ) . ' [A1B2C3]',
			'mesaj_html' => '<p>' . __( 'Tracking code', 'flx-forms' ) . ': <strong>A1B2C3</strong></p><table><tr><td>' . __( 'Name', 'flx-forms' ) . '</td><td>Ayşe Yılmaz</td></tr></table>',
			'takip_kodu' => 'A1B2C3',
			'link'       => home_url( '/?track=A1B2C3' ),
			'kullanici'  => 'ayse',
		);
		$def = static function ( $heading, $button = '', $sender = 'noreply' ) {
			return array(
				'subject'     => '{konu}',
				'heading'     => $heading,
				'body'        => '{mesaj_html}',
				'button_text' => $button,
				'button_url'  => '' !== $button ? '{link}' : '',
				'sender'      => $sender,
				'enabled'     => true,
			);
		};
		$types = array(
			'flx-forms/new-message'       => array(
				'label'       => __( 'New form message', 'flx-forms' ),
				'description' => __( 'Sent to the form\'s notification addresses when a form is filled in or a message gets a new reply.', 'flx-forms' ),
				'recipient'   => 'admin',
				'defaults'    => $def( __( 'New message', 'flx-forms' ), __( 'View message', 'flx-forms' ) ),
			),
			'flx-forms/form-received'     => array(
				'label'       => __( 'Form received (to the visitor)', 'flx-forms' ),
				'description' => __( 'Sent to the visitor after they fill in a form (with the tracking code when the form gives one).', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'We have received your message', 'flx-forms' ), __( 'Track your message', 'flx-forms' ) ),
			),
			'flx-forms/reply-received'    => array(
				'label'       => __( 'Reply to a message', 'flx-forms' ),
				'description' => __( 'Sent when a message gets a reply.', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'You have a reply', 'flx-forms' ), __( 'View reply', 'flx-forms' ) ),
			),
			'flx-forms/subscribed'        => array(
				'label'       => __( 'Subscription confirmed', 'flx-forms' ),
				'description' => __( 'Sent to the visitor after a subscription form.', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'Thank you for subscribing', 'flx-forms' ) ),
			),
			'flx-forms/survey-done'       => array(
				'label'       => __( 'Survey answered', 'flx-forms' ),
				'description' => __( 'Sent to the visitor after a survey form.', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'Thank you for taking part', 'flx-forms' ) ),
			),
			'flx-forms/register'          => array(
				'label'       => __( 'Registration (to the new user)', 'flx-forms' ),
				'description' => __( 'Sent to a visitor who registered with a registration form; contains the confirmation link.', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'Welcome!', 'flx-forms' ), '', 'info' ),
			),
			'flx-forms/new-user'          => array(
				'label'       => __( 'New user registered', 'flx-forms' ),
				'description' => __( 'Sent to the site administrator when someone registers with a form.', 'flx-forms' ),
				'recipient'   => 'admin',
				'defaults'    => $def( __( 'New user registration', 'flx-forms' ) ),
			),
			'flx-forms/password-recovery' => array(
				'label'       => __( 'Password recovery', 'flx-forms' ),
				'description' => __( 'Sent when a visitor asks for a new password with a login form; contains the reset link.', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( 'Password Reset', 'flx-forms' ) ),
			),
			'flx-forms/automation-email'  => array(
				'label'       => __( 'Automation e-mail', 'flx-forms' ),
				'description' => __( 'The "Send e-mail" step of Forms › Automations. Subject and text are written in each flow; this template is the frame around them.', 'flx-forms' ),
				'recipient'   => 'admin',
				'defaults'    => $def( '' ),
			),
		);
		foreach ( $types as $k => $t ) {
			$types[ $k ] += array( 'group' => self::GROUP, 'vars' => $vars, 'sample' => $sample, 'can_disable' => true );
		}
		return (array) apply_filters( 'flxf_email_types', $types );
	}

	public static function register( $types ) {
		return array_merge( is_array( $types ) ? $types : array(), self::types() );
	}

	/** Tracking code from a "…?track=CODE" link, else from the content. */
	public static function track_of( $link, $m ) {
		if ( is_string( $link ) && preg_match( '/[?&]track=([A-Za-z0-9_-]+)/', $link, $x ) ) {
			return $x[1];
		}
		$t = is_array( $m ) ? (string) ( $m[0] ?? '' ) : (string) $m;
		return preg_match( '/^[A-Za-z0-9_-]{4,40}$/', $t ) ? $t : '';
	}

	/**
	 * Send one message.
	 *
	 * @return bool
	 */
	public static function send( $key, $to, $subject, $html, $link, $track, $user = '' ) {
		$link = is_string( $link ) && 'null' !== $link && preg_match( '#^https?://#i', $link ) ? $link : '';
		return (bool) flxi_send_email(
			array(
				'to'       => $to,
				'template' => $key,
				'vars'     => array(
					'konu'       => wp_strip_all_tags( (string) $subject ),
					'mesaj_html' => (string) $html,
					'takip_kodu' => (string) $track,
					'link'       => $link,
					'kullanici'  => (string) $user,
				),
			)
		);
	}
}
