<?php
/**
 * Posta kutusu şablonları: Kurumsal (kart), Sade (mektup) ve kullanıcının alt şablonları
 * (ör. "Örnek Vakfı × Firma" işbirliği teklifi: firma logosu, vurgu rengi, hazır içerik).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Kullanıcının alt şablonları (option flxi_layouts). */
function flxi_custom_layouts() {
	$l = get_option( 'flxi_layouts', array() );
	return is_array( $l ) ? $l : array();
}

/**
 * Bütün şablonlar: id => [ name, base (kurumsal|sade), partner_name, partner_logo, partner_url, accent, heading, body, builtin ].
 */
function flxi_layouts() {
	$out = array(
		'kurumsal' => array(
			'name'    => __( 'Corporate', 'flx-mail' ),
			'desc'    => __( 'Logo card, button, corporate footer. For announcements and new emails.', 'flx-mail' ),
			'base'    => 'kurumsal',
			'builtin' => true,
		),
		'sade'     => array(
			'name'    => __( 'Plain', 'flx-mail' ),
			'desc'    => __( 'Personal letter look with a small logo signature below. For replies.', 'flx-mail' ),
			'base'    => 'sade',
			'builtin' => true,
		),
	);
	foreach ( flxi_custom_layouts() as $id => $l ) {
		$out[ $id ] = wp_parse_args(
			$l,
			array(
				'name'         => $id,
				'desc'         => '',
				'base'         => 'kurumsal',
				'partner_name' => '',
				'partner_logo' => '',
				'partner_url'  => '',
				'accent'       => '',
				'heading'      => '',
				'subject'      => '',
				'body'         => '',
				'builtin'      => false,
			)
		);
	}
	return $out;
}

function flxi_layout_exists( $id ) {
	return isset( flxi_layouts()[ (string) $id ] );
}

/** Kaydeder; dönen: id. */
function flxi_layout_save( array $d, $id = '' ) {
	$all = flxi_custom_layouts();
	$id  = $id && isset( $all[ $id ] ) ? $id : 'l' . substr( md5( uniqid( '', true ) ), 0, 8 );
	$all[ $id ] = array(
		'name'         => sanitize_text_field( (string) ( $d['name'] ?? '' ) ) ?: __( 'Untitled layout', 'flx-mail' ),
		'desc'         => sanitize_text_field( (string) ( $d['desc'] ?? '' ) ),
		'base'         => 'sade' === ( $d['base'] ?? '' ) ? 'sade' : 'kurumsal',
		'partner_name' => sanitize_text_field( (string) ( $d['partner_name'] ?? '' ) ),
		'partner_logo' => esc_url_raw( (string) ( $d['partner_logo'] ?? '' ) ),
		'partner_url'  => esc_url_raw( (string) ( $d['partner_url'] ?? '' ) ),
		'accent'       => (string) ( sanitize_hex_color( (string) ( $d['accent'] ?? '' ) ) ?: '' ),
		'heading'      => sanitize_text_field( (string) ( $d['heading'] ?? '' ) ),
		'subject'      => sanitize_text_field( (string) ( $d['subject'] ?? '' ) ),
		'body'         => wp_kses_post( (string) ( $d['body'] ?? '' ) ),
	);
	update_option( 'flxi_layouts', $all, false );
	return $id;
}

function flxi_layout_delete( $id ) {
	$all = flxi_custom_layouts();
	unset( $all[ $id ] );
	update_option( 'flxi_layouts', $all, false );
}

/** Şablona göre flxi_render_email argümanları. */
function flxi_layout_args( $id ) {
	$all = flxi_layouts();
	$l   = $all[ $id ] ?? $all['kurumsal'];
	$a   = array( 'style' => 'sade' === $l['base'] ? 'letter' : 'card' );
	if ( ! empty( $l['partner_name'] ) || ! empty( $l['partner_logo'] ) ) {
		$a['partner'] = array(
			'name' => $l['partner_name'],
			'logo' => $l['partner_logo'],
			'url'  => $l['partner_url'],
		);
	}
	if ( ! empty( $l['accent'] ) ) {
		$a['accent'] = $l['accent'];
	}
	if ( ! empty( $l['heading'] ) ) {
		$a['heading'] = esc_html( $l['heading'] );
	}
	return $a;
}

/** Posta kutusu imzası: ayardaki metin; boşsa imza eklenmez (kapanışı yazan kişi yazar). */
function flxi_mailbox_signature( $user_id = 0 ) {
	$sig = trim( (string) flxi_setting( 'mailbox_signature' ) );
	$u   = get_userdata( $user_id ? $user_id : get_current_user_id() );
	$vars = array(
		'ad_soyad' => $u ? $u->display_name : '',
		'kurum'    => flxi_brand()['org'],
	);
	if ( '' === $sig ) {
		return '';
	}
	return wpautop( flxi_fill( esc_html( $sig ), $vars ) );
}

/**
 * Posta kutusu e-postasının HTML'i.
 *
 * @param array $d subject, body (HTML), layout, quote (HTML), heading
 */
function flxi_mailbox_render( array $d ) {
	$args = array_merge(
		array(
			'subject'   => (string) ( $d['subject'] ?? '' ),
			'body'      => flxi_body_html( wp_kses_post( (string) ( $d['body'] ?? '' ) ) ),
			'sender'    => 'info',
			'signature' => flxi_mailbox_signature(),
			'quote'     => (string) ( $d['quote'] ?? '' ),
		),
		flxi_layout_args( $d['layout'] ?? 'kurumsal' )
	);
	if ( isset( $d['heading'] ) && '' !== $d['heading'] ) {
		$args['heading'] = esc_html( $d['heading'] );
	}
	$args['preheader'] = wp_strip_all_tags( $args['body'] );
	return flxi_render_email( $args );
}
