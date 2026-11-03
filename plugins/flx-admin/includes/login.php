<?php
/** Giriş ekranı: marka logosu, panelle aynı renkler, cihaza göre açık/koyu. */
defined( 'ABSPATH' ) || exit;

add_action( 'login_enqueue_scripts', 'flxa_login_assets' );
add_filter( 'login_headerurl', 'flxa_login_url' );
add_filter( 'login_headertext', 'flxa_login_text' );

function flxa_login_on() {
	return flxa_enabled() && flxa_setting( 'login' );
}

function flxa_login_assets() {
	if ( ! flxa_login_on() ) {
		return;
	}
	wp_enqueue_style( 'flxa-login', FLXA_URL . 'assets/css/login.css', array( 'login' ), FLXA_VERSION );
	wp_add_inline_style( 'flxa-login', ':root{--flxa-accent:' . flxa_accent() . '}@media (prefers-color-scheme: dark){:root{--flxa-accent:' . flxa_accent_dark() . '}}#login h1 a{background-image:url("' . esc_url( flxa_logo_url( 192 ) ) . '")}' );
}

function flxa_login_url( $url ) {
	return flxa_login_on() ? home_url( '/' ) : $url;
}

function flxa_login_text( $text ) {
	return flxa_login_on() ? flxa_brand() : $text;
}
