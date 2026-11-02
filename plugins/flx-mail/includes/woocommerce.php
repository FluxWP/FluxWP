<?php
/**
 * WooCommerce e-postaları: listede görünür, açılıp kapanır, kayda düşer; görünüm WooCommerce'in kendi
 * e-posta ayarlarına (logo, renk, alt bilgi) tek tıkla uygulanır. İçerik WooCommerce › Ayarlar › E-postalar'da düzenlenir.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Her WooCommerce e-postasına şablon anahtarı eklenir (kayıt ve açma/kapama için). */
add_filter(
	'woocommerce_email_headers',
	function ( $headers, $id = '', $object = null, $email = null ) {
		if ( ! $id ) {
			return $headers;
		}
		$key = 'wc/' . sanitize_key( $id );
		$conf = flxi_template( $key );
		$add  = 'X-FLXI-Template: ' . $key . "\r\n";
		if ( $conf['sender'] ) {
			$add .= 'X-FLXI-Sender: ' . $conf['sender'] . "\r\n";
		}
		return rtrim( (string) $headers, "\r\n" ) . ( '' === trim( (string) $headers ) ? '' : "\r\n" ) . $add;
	},
	50,
	4
);

/** WooCommerce e-postaları listesi. */
function flxi_wc_emails() {
	if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Emails' ) ) {
		return array();
	}
	$out = array();
	foreach ( WC()->mailer()->get_emails() as $email ) {
		$id          = sanitize_key( $email->id );
		$out[ $id ] = array(
			'key'         => 'wc/' . $id,
			'id'          => $id,
			'label'       => $email->get_title(),
			'description' => $email->get_description(),
			'enabled'     => $email->is_enabled(),
			'manual'      => $email->is_manual(),
			'customer'    => $email->is_customer_email(),
			'edit_url'    => admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( get_class( $email ) ) ),
		);
	}
	return $out;
}

/** WooCommerce e-postasını açar/kapatır (WooCommerce'in kendi ayarı). */
function flxi_wc_set_enabled( $id, $on ) {
	if ( ! function_exists( 'WC' ) ) {
		return false;
	}
	foreach ( WC()->mailer()->get_emails() as $email ) {
		if ( sanitize_key( $email->id ) !== $id || $email->is_manual() ) {
			continue;
		}
		$opt            = $email->get_option_key();
		$settings       = (array) get_option( $opt, array() );
		$settings['enabled'] = $on ? 'yes' : 'no';
		update_option( $opt, $settings );
		return true;
	}
	return false;
}

/** Kurumsal görünümü WooCommerce e-posta ayarlarına uygular. */
function flxi_wc_apply_brand() {
	$b = flxi_brand();
	$s = flxi_settings();
	$map = array(
		'woocommerce_email_header_image'            => $b['logo'],
		'woocommerce_email_base_color'              => $b['primary'],
		'woocommerce_email_background_color'        => $b['bg'],
		'woocommerce_email_body_background_color'   => '#ffffff',
		'woocommerce_email_text_color'              => $b['text'],
		'woocommerce_email_footer_text'             => trim( $b['org'] . ( $b['address'] ? ' · ' . $b['address'] : '' ) . "\n" . $b['host'] ),
		'woocommerce_email_footer_text_color'       => '#5E707A',
		'woocommerce_email_from_name'               => $s['from_name'],
		'woocommerce_email_from_address'            => $s['from_email'],
		'woocommerce_email_header_image_width'      => $b['logo_width'],
		'woocommerce_email_header_alignment'        => 'left',
		'woocommerce_email_font_family'             => 'Helvetica',
	);
	foreach ( $map as $k => $v ) {
		update_option( $k, $v );
	}
	update_option( 'flxi_wc_branded', time(), false );
	return true;
}
