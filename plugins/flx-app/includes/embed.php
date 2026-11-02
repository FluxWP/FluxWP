<?php
/**
 * Uygulama içi panel görünümü: masaüstü/mobil uygulama sayfayı gömülü web görünümünde açar
 * (user agent'ta "FLXApp/"). Uygulamanın kendi sol menüsü ve üst çubuğu olduğu için panelin
 * menüsü, üst çubuğu, alt barı, altlığı ve sayfa yükleme çubuğu gizlenir (uygulamanın kendi
 * çubuğu var; ikisi birden çift çizgi gibi görünüyordu). Sayfa tüm alanı kullanır.
 */

defined( 'ABSPATH' ) || exit;

function flxapp_is_embed() {
	return isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( (string) $_SERVER['HTTP_USER_AGENT'], 'FLXApp/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

add_filter(
	'admin_body_class',
	function ( $c ) {
		return flxapp_is_embed() ? $c . ' flxapp-embed' : $c;
	}
);

add_action(
	'admin_head',
	function () {
		if ( ! flxapp_is_embed() ) {
			return;
		}
		// Seçiciler Flux Admin uygulama modu kurallarından (html body.flxa-appmode …) güçlü olsun diye sınıf iki kez yazılır.
		$b   = 'html body.flxapp-embed.flxapp-embed';
		$css = 'html.wp-toolbar{padding-top:0!important}'
			. $b . '{--flxa-app-w:0px;--flxa-side-w:0px;--flxa-rail-w:0px;--wp-admin--admin-bar--height:0px}'
			. implode( ',', array_map( fn( $x ) => "$b $x", array( '#wpadminbar', '#adminmenumain', '#adminmenuback', '#adminmenuwrap', '#wpfooter', '#flxa-bottom', '.flxa-resizer', '.flxa-sw', '#screen-meta-links', '#flxa-progress' ) ) ) . '{display:none!important}'
			. "$b #wpcontent{margin-left:0!important}$b #wpbody-content{padding-bottom:24px!important}"
			. "$b #flxg-app,$b #flxc-app,$b #flxst-app{top:0!important;left:0!important;right:0!important;bottom:0!important;width:auto!important;height:auto!important}";
		echo '<style id="flxapp-embed">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	99
);
