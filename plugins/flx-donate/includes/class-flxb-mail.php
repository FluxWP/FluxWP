<?php
/**
 * Donor and admin e-mails of Flux Donate.
 *
 * Every e-mail the plugin sends is a registered template. When Flux Mail is active the
 * templates are listed (and editable, or switchable off) under İletişim › E-postalar through the
 * `flxi_email_types` filter, and they are sent with `flxi_send_email()` in the shared design.
 * Without Flux Mail the same template defaults are sent as plain text with wp_mail().
 *
 * Add one: filter `flxb_email_types`. Send: FLXB_Mail::send( 'flx-donate/<key>', $to, $vars ).
 *
 * Flux Donate is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of
 * the License, or (at your option) any later version. It is distributed WITHOUT ANY WARRANTY;
 * see the GNU General Public License for more details.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_Mail {

	const GROUP = 'Flux Donate';

	public static function init() {
		add_filter( 'flxi_email_types', array( __CLASS__, 'register' ) );
	}

	/** True when Flux Mail sends the e-mails. */
	public static function iletisim() {
		return function_exists( 'flxi_send_email' ) && (bool) apply_filters( 'flxb_use_iletisim', true );
	}

	/**
	 * The templates: key => label, description, recipient (user|admin), vars, sample, defaults.
	 *
	 * @return array
	 */
	public static function types() {
		$types = array(
			'flx-donate/recurring-started'  => array(
				'label'       => __( '\\nRecurring donation started\\n', 'flx-donate' ),
				'description' => __( '\\nSent to the donor when a monthly (card) donation is set up.\\n', 'flx-donate' ),
				'recipient'   => 'user',
				'vars'        => array(
					'ad'         => __( '\\nDonor name\\n', 'flx-donate' ),
					'tutar'      => __( 'Amount', 'flx-donate' ),
					'aralik'     => __( '\\nInterval (monthly...)\\n', 'flx-donate' ),
					'sonraki'    => __( '\\nDate of the next payment\\n', 'flx-donate' ),
					'iptal_link' => __( '\\nCancel link\\n', 'flx-donate' ),
				),
				'sample'      => array( 'ad' => 'Ayşe', 'tutar' => '150 ₺', 'aralik' => __( '\\nmonthly\\n', 'flx-donate' ), 'sonraki' => flx_date( time() + MONTH_IN_SECONDS ), 'iptal_link' => home_url( '/' ) ),
				'defaults'    => array(
					/* translators: {kurum} stays as is: it is replaced by the organisation name. */
					'subject'     => __( '\\nYour recurring donation to{kurum}\\n', 'flx-donate' ),
					'heading'     => __( '\\nThank you,{ad}\\n', 'flx-donate' ),
					'body'        => '<p>' . __( '\\nYour{aralik}donation of{tutar}is set up. The next payment will be taken on{sonraki}.\\n', 'flx-donate' ) . '</p><p>' . __( '\\nYou can cancel your recurring donation at any time with the button below.\\n', 'flx-donate' ) . '</p>',
					'button_text' => __( '\\nCancel recurring donation\\n', 'flx-donate' ),
					'button_url'  => '{iptal_link}',
					'sender'      => 'info',
					'enabled'     => 1,
				),
			),
			'flx-donate/recurring-failed'   => array(
				'label'       => __( '\\nRecurring donation could not be charged\\n', 'flx-donate' ),
				'description' => __( '\\nSent to the donor when the card was declined on every attempt and the recurring donation is paused.\\n', 'flx-donate' ),
				'recipient'   => 'user',
				'vars'        => array(
					'ad'         => __( '\\nDonor name\\n', 'flx-donate' ),
					'tutar'      => __( 'Amount', 'flx-donate' ),
					'aralik'     => __( '\\nInterval (monthly...)\\n', 'flx-donate' ),
					'deneme'     => __( '\\nNumber of attempts\\n', 'flx-donate' ),
					'bagis_link' => __( 'Donation page', 'flx-donate' ),
					'iptal_link' => __( '\\nCancel link\\n', 'flx-donate' ),
				),
				'sample'      => array( 'ad' => 'Ayşe', 'tutar' => '150 ₺', 'aralik' => __( '\\nmonthly\\n', 'flx-donate' ), 'deneme' => '3', 'bagis_link' => home_url( '/' ), 'iptal_link' => home_url( '/' ) ),
				'defaults'    => array(
					'subject'     => __( '\\nWe could not take your donation to{kurum}\', \'en\']]', 'flx-donate' ),
					'heading'     => __( '\\nYour recurring donation is paused\\n', 'flx-donate' ),
					'body'        => '<p>' . __( '\\nDear{ad},\\n', 'flx-donate' ) . '</p><p>' . __( '\\nWe tried{deneme}times but could not charge your card for your{aralik}donation of{tutar}, so it is paused. If you would like to continue, please make a new donation on our website or contact us.\\n', 'flx-donate' ) . '</p>',
					'button_text' => __( '\\nDonate again\\n', 'flx-donate' ),
					'button_url'  => '{bagis_link}',
					'sender'      => 'info',
					'enabled'     => 1,
				),
			),
			'flx-donate/receipt-uploaded'   => array(
				'label'       => __( '\\nBank transfer receipt uploaded\\n', 'flx-donate' ),
				'description' => __( '\\nSent to the donations manager when a donor uploads a transfer receipt (dekont).\\n', 'flx-donate' ),
				'recipient'   => 'admin',
				'vars'        => array(
					'siparis'  => __( '\\nOrder number\\n', 'flx-donate' ),
					'ad'       => __( '\\nDonor name\\n', 'flx-donate' ),
					'tutar'    => __( 'Amount', 'flx-donate' ),
					'aciklama' => __( '\\nTransfer reference\\n', 'flx-donate' ),
					'dosya'    => __( '\\nFile name\\n', 'flx-donate' ),
					'link'     => __( '\\nOrder link (admin)\\n', 'flx-donate' ),
				),
				'sample'      => array( 'siparis' => '1024', 'ad' => 'Ayşe Yılmaz', 'tutar' => '1.000 ₺', 'aciklama' => 'YT1024', 'dosya' => 'dekont.pdf', 'link' => admin_url() ),
				'defaults'    => array(
					'subject'     => __( '\\nReceipt uploaded: donation #{siparis}({ad})\\n', 'flx-donate' ),
					'heading'     => __( '\\nA transfer receipt was uploaded\', \'en\']]', 'flx-donate' ),
					'body'        => '<p>' . __( '\\nAmount:{tutar}\\n', 'flx-donate' ) . '<br>' . __( '\\nReference:{aciklama}\\n', 'flx-donate' ) . '<br>' . __( '\\nFile:{dosya}\\n', 'flx-donate' ) . '</p><p>' . __( '\\nCheck the payment at the bank and press "Confirm payment" on the order; the certificate is then issued and sent automatically.\\n', 'flx-donate' ) . '</p>',
					'button_text' => __( '\\nOpen the order\\n', 'flx-donate' ),
					'button_url'  => '{link}',
					'sender'      => 'noreply',
					'enabled'     => 1,
				),
			),
			'flx-donate/payment-rejected'   => array(
				'label'       => __( 'Payment rejected', 'flx-donate' ),
				'description' => __( '\\nSent to the donor when a manager rejects a bank transfer, with the reason.\\n', 'flx-donate' ),
				'recipient'   => 'user',
				'vars'        => array(
					'ad'       => __( '\\nDonor name\\n', 'flx-donate' ),
					'tutar'    => __( 'Amount', 'flx-donate' ),
					'aciklama' => __( 'Reason', 'flx-donate' ),
					'siparis'  => __( '\\nOrder number\\n', 'flx-donate' ),
				),
				'sample'      => array( 'ad' => 'Ayşe Yılmaz', 'tutar' => '300 ₺', 'aciklama' => 'Dekonttaki alıcı adı hesabımızla eşleşmiyor.', 'siparis' => '1024' ),
				'defaults'    => array(
					'subject'     => __( '\\nWe could not confirm your donation payment\\n', 'flx-donate' ),
					'heading'     => __( '\\nWe could not confirm your payment\\n', 'flx-donate' ),
					'body'        => '<p>' . __( '\\nHello{ad},\\n', 'flx-donate' ) . '</p><p>' . __( '\\nWe could not confirm the payment of your{tutar}donation.\\n', 'flx-donate' ) . '</p><p><strong>' . __( '\\nReason:{aciklama}\\n', 'flx-donate' ) . '</strong></p><p>' . __( '\\nIf you think this is a mistake, please reply to this e-mail. Thank you.\\n', 'flx-donate' ) . '</p>',
					'button_text' => '',
					'button_url'  => '',
					'sender'      => 'info',
					'enabled'     => 1,
				),
			),
		);
		return (array) apply_filters( 'flxb_email_types', $types );
	}

	/** Filter flxi_email_types. */
	public static function register( $types ) {
		foreach ( self::types() as $key => $t ) {
			$t['group']       = self::GROUP;
			$t['can_disable'] = true;
			$types[ $key ]    = $t;
		}
		return $types;
	}

	/**
	 * Send one template.
	 *
	 * @param string       $key  Template key (flx-donate/…).
	 * @param string|array $to   Address(es).
	 * @param array        $vars Values for the {placeholders}.
	 * @param array        $args Extra flxi_send_email arguments (reply_to, attachments, facts…).
	 * @return bool
	 */
	public static function send( $key, $to, array $vars, array $args = array() ) {
		$types = self::types();
		if ( ! isset( $types[ $key ] ) ) {
			return false;
		}
		$to = array_values( array_filter( array_map( 'trim', (array) $to ), 'is_email' ) );
		if ( ! $to ) {
			return false;
		}
		$vars = array_map( 'strval', $vars );
		if ( self::iletisim() ) {
			$ok = true;
			foreach ( $to as $addr ) {
				$ok = (bool) flxi_send_email( array_merge( $args, array( 'to' => $addr, 'template' => $key, 'vars' => $vars ) ) ) && $ok;
			}
			return $ok;
		}
		list( $subject, $body ) = self::plain( $types[ $key ]['defaults'], $vars );
		return (bool) wp_mail( $to, $subject, $body, ! empty( $args['reply_to'] ) && is_email( $args['reply_to'] ) ? array( 'Reply-To: ' . $args['reply_to'] ) : array(), (array) ( $args['attachments'] ?? array() ) );
	}

	/**
	 * Plain-text version of a template (fallback without Flux Mail).
	 *
	 * @return array { subject, body }
	 */
	public static function plain( array $d, array $vars ) {
		$b    = function_exists( 'flxb_brand' ) ? flxb_brand() : array( 'name' => get_bloginfo( 'name' ) );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$all  = array_merge(
			array(
				'site'  => $site,
				'kurum' => $b['name'],
				'yil'   => gmdate( 'Y' ),
				'tarih' => flx_date( time() ),
			),
			$vars
		);
		$fill = static function ( $s ) use ( $all ) {
			$s = (string) $s;
			foreach ( $all as $k => $v ) {
				$s = str_replace( '{' . $k . '}', (string) $v, $s );
			}
			/* An empty value leaves no gap: "Dear {ad}," → "Dear,". */
			$s = preg_replace( '/\s*\{[a-z0-9_]+\}/i', '', $s );
			return $s;
		};
		$text = static function ( $html ) {
			$html = preg_replace( '#<br\s*/?>#i', "\n", (string) $html );
			$html = preg_replace( '#</p>\s*#i', "\n\n", $html );
			return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
		};
		$subject = $text( $fill( $d['subject'] ?? '' ) );
		$parts   = array();
		$heading = $text( $fill( $d['heading'] ?? '' ) );
		if ( '' !== $heading ) {
			$parts[] = $heading;
		}
		$parts[] = $text( $fill( $d['body'] ?? '' ) );
		$url     = trim( $fill( $d['button_url'] ?? '' ) );
		if ( '' !== $url ) {
			$parts[] = trim( $text( $fill( $d['button_text'] ?? '' ) ) . ': ' . $url, ': ' );
		}
		$parts[] = $b['name'] . "\n" . home_url( '/' );
		return array( $subject, implode( "\n\n", array_filter( $parts, 'strlen' ) ) . "\n" );
	}

	/**
	 * WhatsApp / SMS message through Flux Mail when present, else Flux Certificates's queue.
	 *
	 * @return bool|null True sent/queued, false failed, null no route.
	 */
	public static function message( $channel, $phone, $text, $name = '', $source = 'flx-donate' ) {
		if ( function_exists( 'flxi_send_message' ) && apply_filters( 'flxb_use_iletisim', true ) && ( ! function_exists( 'flxi_get_channel_connection' ) || flxi_get_channel_connection( $channel ) ) ) {
			$r = flxi_send_message( $channel, $phone, $text, array( 'source' => $source ) );
			return is_array( $r ) ? ! empty( $r['ok'] ) : (bool) $r;
		}
		if ( function_exists( 'flxs_enqueue' ) && ( ! function_exists( 'flxs_resolve_provider' ) || flxs_resolve_provider( $channel ) ) ) {
			$r = flxs_enqueue(
				array(
					'channel'        => $channel,
					'recipient'      => $phone,
					'recipient_name' => $name,
					'body'           => $text,
					'source'         => $source,
				)
			);
			if ( is_wp_error( $r ) ) {
				return false;
			}
			if ( function_exists( 'flxs_kick_queue' ) ) {
				flxs_kick_queue();
			}
			return true;
		}
		return null;
	}

	/** Is a WhatsApp/SMS route configured for $channel? */
	public static function has_channel( $channel ) {
		if ( function_exists( 'flxi_send_message' ) && apply_filters( 'flxb_use_iletisim', true ) && function_exists( 'flxi_get_channel_connection' ) && flxi_get_channel_connection( $channel ) ) {
			return true;
		}
		return function_exists( 'flxs_enqueue' ) && function_exists( 'flxs_resolve_provider' ) && (bool) flxs_resolve_provider( $channel );
	}
}
