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
			'konu'       => __( '\\nSubject made by Flux Forms (with the tracking code)\\n', 'flx-forms' ),
			'mesaj_html' => __( '\\nMessage: tracking code and submitted values (HTML)\\n', 'flx-forms' ),
			'takip_kodu' => __( 'Tracking code', 'flx-forms' ),
			'link'       => __( '\\nLink to the message / response page\\n', 'flx-forms' ),
			'kullanici'  => __( '\\nUser name (registration, password recovery)\\n', 'flx-forms' ),
		);
		$sample = array(
			'konu'       => __( '\\nWe have received your message\\n', 'flx-forms' ) . ' [A1B2C3]',
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
				'label'       => __( '\\nNew form message\\n', 'flx-forms' ),
				'description' => __( '\\nSent to the form\\\'s notification addresses when a form is filled in or a message gets a new reply.\\n', 'flx-forms' ),
				'recipient'   => 'admin',
				'defaults'    => $def( __( '\\nNew message\\n', 'flx-forms' ), __( '\\nView message\\n', 'flx-forms' ) ),
			),
			'flx-forms/form-received'     => array(
				'label'       => __( '\\nForm received (to the visitor)\\n', 'flx-forms' ),
				'description' => __( '\\nSent to the visitor after they fill in a form (with the tracking code when the form gives one).\\n', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nWe have received your message\\n', 'flx-forms' ), __( '\\nTrack your message\\n', 'flx-forms' ) ),
			),
			'flx-forms/reply-received'    => array(
				'label'       => __( '\\nReply to a message\\n', 'flx-forms' ),
				'description' => __( '\\nSent when a message gets a reply.\\n', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nYou have a reply\\n', 'flx-forms' ), __( '\\nView reply\\n', 'flx-forms' ) ),
			),
			'flx-forms/subscribed'        => array(
				'label'       => __( '[[\'Subscription confirmed\\n', 'flx-forms' ),
				'description' => __( '\\nSent to the visitor after a subscription form.\', \'en\']]', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nThank you for subscribing\\n', 'flx-forms' ) ),
			),
			'flx-forms/survey-done'       => array(
				'label'       => __( '\\nSurvey answered\\n', 'flx-forms' ),
				'description' => __( '[["Sent to the visitor after a survey form.\\n', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nThank you for taking part\\n', 'flx-forms' ) ),
			),
			'flx-forms/register'          => array(
				'label'       => __( '\\nRegistration (to the new user)\\n', 'flx-forms' ),
				'description' => __( '\\nSent to a visitor who registered with a registration form; contains the confirmation link.\\n', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nWelcome!\\n', 'flx-forms' ), '', 'info' ),
			),
			'flx-forms/new-user'          => array(
				'label'       => __( '\\nNew user registered\\n', 'flx-forms' ),
				'description' => __( '\\nSent to the site administrator when someone registers with a form.\\n', 'flx-forms' ),
				'recipient'   => 'admin',
				'defaults'    => $def( __( '\\nNew user registration\', \'en\']]', 'flx-forms' ) ),
			),
			'flx-forms/password-recovery' => array(
				'label'       => __( '\\nPassword recovery\\n', 'flx-forms' ),
				'description' => __( '\\nSent when a visitor asks for a new password with a login form; contains the reset link.\\n', 'flx-forms' ),
				'recipient'   => 'user',
				'defaults'    => $def( __( '\\nPassword Reset\\n', 'flx-forms' ) ),
			),
			'flx-forms/automation-email'  => array(
				'label'       => __( '\\nAutomation e-mail\\n', 'flx-forms' ),
				'description' => __( '\\nThe "Send e-mail" step of Forms › Automations. Subject and text are written in each flow; this template is the frame around them.\\n', 'flx-forms' ),
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
