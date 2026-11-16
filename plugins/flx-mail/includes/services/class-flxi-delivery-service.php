<?php
/**
 * Preview and test send. REST callbacks delegate here.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview and test email.
 */
class FLXI_Delivery_Service {

	/**
	 * Same array as POST /preview.
	 *
	 * @param array<string,mixed> $in key, fields, dark.
	 * @return array{subject:string,html:string}
	 */
	public static function preview( array $in ): array {
		$key = sanitize_text_field( (string) ( $in['key'] ?? '' ) );
		$f   = (array) ( $in['fields'] ?? array() );
		if ( $key && isset( flxi_email_types()[ $key ] ) ) {
			$stored = get_option( 'flxi_templates', array() );
			add_filter(
				'pre_option_flxi_templates',
				static function () use ( $key, $f, $stored ) {
					$all         = is_array( $stored ) ? $stored : array();
					$cur         = $all[ $key ] ?? array();
					$all[ $key ] = array_merge( $cur, array_intersect_key( $f, array_flip( array( 'subject', 'heading', 'body', 'button_text', 'button_url', 'sender' ) ) ) );
					return $all;
				}
			);
			$b = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
			return array( 'subject' => $b['subject'], 'html' => $b['html'] );
		}
		$vars = array( 'ad' => 'Ayşe', 'ad_soyad' => 'Ayşe Yılmaz', 'eposta' => 'ayse@example.com', 'telefon' => '905321234567' );
		$args = array(
			'subject'     => flxi_fill( (string) ( $f['subject'] ?? '' ), $vars ),
			'heading'     => flxi_fill( (string) ( $f['heading'] ?? '' ), $vars, true ),
			'body'        => flxi_fill( flxi_body_html( wp_kses_post( (string) ( $f['body'] ?? '' ) ) ), $vars, true ),
			'button_text' => (string) ( $f['button_text'] ?? '' ),
			'button_url'  => (string) ( $f['button_url'] ?? '' ),
			'sender'      => (string) ( $f['sender'] ?? 'info' ),
		);
		if ( ! empty( $f['unsubscribe'] ) ) {
			$args['unsubscribe_url'] = home_url( '/' );
		}
		if ( 'brand' === $key ) {
			$brand = (array) ( $f['brand'] ?? array() );
			$allow = array( 'logo', 'logo_white', 'logo_width', 'color_primary', 'color_dark', 'color_bg', 'color_text', 'org_name', 'tagline', 'address', 'phone', 'contact_email', 'website', 'social', 'footer_note', 'footer_art', 'from_name' );
			$tmp   = array();
			foreach ( array_intersect_key( $brand, array_flip( $allow ) ) as $bk => $bv ) {
				try {
					$tmp[ $bk ] = flxi_sanitize_setting( $bk, $bv );
				} catch ( Exception $e ) {
					unset( $e );
					continue;
				}
			}
			if ( $tmp ) {
				$saved = get_option( 'flxi_settings', array() );
				add_filter(
					'pre_option_flxi_settings',
					static function () use ( $tmp, $saved ) {
						return array_merge( is_array( $saved ) ? $saved : array(), $tmp );
					}
				);
				flxi_settings( true );
			}
			$args = array(
				'subject'     => __( 'Sample email', 'flx-mail' ),
				'heading'     => __( 'Branded email layout', 'flx-mail' ),
				'body'        => __( '<p>Hello Ayşe,</p><p>Every email sent from our site uses this layout: our logo on top, the content in the middle, and our organization details and social media accounts at the bottom.</p>', 'flx-mail' ),
				'button_text' => __( 'Visit the site', 'flx-mail' ),
				'button_url'  => home_url( '/' ),
				'sender'      => 'info',
			);
		}
		$html = flxi_render_email( $args );
		if ( 'brand' === $key ) {
			flxi_settings( true );
		}
		return array( 'subject' => $args['subject'], 'html' => $html );
	}

	/**
	 * Same array as POST /preview-pdf.
	 *
	 * @param array<string,mixed> $in Preview input plus dark.
	 * @return array{name:string,data:string}|WP_Error
	 */
	public static function preview_pdf( array $in ) {
		$p = self::preview( $in );
		$html = (string) $p['html'];
		if ( ! empty( $in['dark'] ) ) {
			$html = (string) preg_replace( '/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/i', '@media all', $html );
		}
		$pdf = flxi_render_pdf( $html );
		if ( is_wp_error( $pdf ) ) {
			return new WP_Error( $pdf->get_error_code(), $pdf->get_error_message(), array( 'status' => 502 ) );
		}
		return array(
			'name' => flxi_pdf_filename( $p['subject'] ?? '' ),
			'data' => base64_encode( $pdf ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Same array as POST /test-email.
	 *
	 * @param array<string,mixed> $in to, key, sender, free.
	 * @return array{ok:bool,to:string,error:string,at:int}
	 */
	public static function test_email( array $in ): array {
		$to = sanitize_email( (string) ( $in['to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			$to = wp_get_current_user()->user_email;
		}
		$key   = sanitize_text_field( (string) ( $in['key'] ?? '' ) );
		$error = '';
		$catch = static function ( $e ) use ( &$error ) {
			$error = $e->get_error_message();
			$data  = $e->get_error_data();
			if ( is_array( $data ) && ! empty( $data['phpmailer_exception_code'] ) ) {
				$error .= ' (' . $data['phpmailer_exception_code'] . ')';
			}
		};
		add_action( 'wp_mail_failed', $catch );
		$GLOBALS['flxi_strict_sender'] = true;
		if ( $key && isset( flxi_email_types()[ $key ] ) ) {
			$b  = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
			$ok = wp_mail(
				$to,
				'[' . __( 'Test', 'flx-mail' ) . '] ' . $b['subject'],
				$b['html'],
				array( 'Content-Type: text/html; charset=UTF-8', 'X-FLXI-Template: flx-mail/test', 'X-FLXI-Rendered: 1', 'X-FLXI-Sender: ' . ( $b['sender'] ? $b['sender'] : 'info' ) )
			);
		} elseif ( is_array( $in['free'] ?? null ) ) {
			$f    = $in['free'];
			$vars = array( 'ad' => flxi_user_first_name( wp_get_current_user() ), 'ad_soyad' => wp_get_current_user()->display_name, 'eposta' => $to, 'telefon' => '' );
			$ok   = flxi_send_email(
				array(
					'to'          => $to,
					'template'    => 'flx-mail/test',
					'sender'      => 'noreply' === ( $f['sender'] ?? '' ) ? 'noreply' : 'info',
					'vars'        => $vars,
					'subject'     => '[' . __( 'Test', 'flx-mail' ) . '] ' . sanitize_text_field( (string) ( $f['subject'] ?? '' ) ),
					'heading'     => sanitize_text_field( (string) ( $f['heading'] ?? '' ) ),
					'body'        => wp_kses_post( (string) ( $f['body'] ?? '' ) ),
					'button_text' => sanitize_text_field( (string) ( $f['button_text'] ?? '' ) ),
					'button_url'  => esc_url_raw( (string) ( $f['button_url'] ?? '' ) ),
				)
			);
		} else {
			$sender = 'noreply' === ( $in['sender'] ?? '' ) ? 'noreply' : 'info';
			$ok     = flxi_send_email(
				array(
					'to'          => $to,
					'template'    => 'flx-mail/test',
					'sender'      => $sender,
					'subject'     => __( 'Test email', 'flx-mail' ),
					'heading'     => __( 'Your email settings are working 🎉', 'flx-mail' ),
					'body'        => sprintf(
						/* translators: 1: sender address, 2: method */
						__( '<p>If you can see this email, your site\'s email sending is working.</p><table class="flxi-kv"><tr><td>Sender</td><td><strong>%1$s</strong></td></tr><tr><td>Method</td><td>%2$s</td></tr></table>', 'flx-mail' ),
						esc_html( flxi_sender_address( $sender )[0] ),
						esc_html( flxi_smtp_ready() ? 'SMTP · ' . flxi_setting( 'smtp_host' ) : ( flxi_other_smtp_plugin() ? flxi_other_smtp_plugin() : __( 'Server PHP mail()', 'flx-mail' ) ) )
					),
					'button_text' => __( 'Go to the site', 'flx-mail' ),
					'button_url'  => home_url( '/' ),
				)
			);
		}
		remove_action( 'wp_mail_failed', $catch );
		$GLOBALS['flxi_strict_sender'] = false;
		$res = array(
			'ok'    => (bool) $ok,
			'to'    => $to,
			'error' => $error,
			'at'    => time(),
		);
		if ( ! $key && ! is_array( $in['free'] ?? null ) ) {
			update_option( 'flxi_last_test', $res, false );
			if ( function_exists( 'flx_gql_purge' ) ) {
				flx_gql_purge( 'flx-mail:settings' );
			}
		}
		return $res;
	}
}
