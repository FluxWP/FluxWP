<?php
/**
 * Sayfa geçişlerini hızlandırma: tıklanmaya başlanan (fare basılı/dokunma) site içi link önceden indirilir
 * (Speculation Rules, prefetch + conservative). Fareyle üzerinden geçilen her linki tam sayfa olarak hazırlamak
 * (prerender + moderate) sunucuya ziyaret başına birkaç ek PHP isteği bindiriyor, paylaşımlı hostingde
 * işçi limitini doldurup siteyi kilitleyebiliyordu. Geçişte header yerinde kalır (CSS View Transitions, theme.css).
 * Hesap, sepet, ödeme ve yönetim adresleri hariçtir.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flx_hiz_exclude_paths() {
	return array( '/hesabim/*', '/my-account/*', '/sepet/*', '/cart/*', '/odeme/*', '/checkout/*', '/wp-login.php', '/*\\?*' );
}

if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
	// WordPress 6.8+: çekirdeğin kendi özelliği ayarlanır.
	add_filter(
		'wp_speculation_rules_configuration',
		function ( $config ) {
			if ( is_array( $config ) ) {
				$config = array( 'mode' => 'prefetch', 'eagerness' => 'conservative' );
			}
			return $config;
		}
	);
	add_filter(
		'wp_speculation_rules_href_exclude_paths',
		function ( $paths ) {
			return array_merge( $paths, flx_hiz_exclude_paths() );
		}
	);
} else {
	add_action(
		'wp_footer',
		function () {
			if ( is_user_logged_in() || is_admin() ) {
				return;
			}
			$base  = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			$not   = array_map( fn( $p ) => $base . $p, array_merge( array( '/wp-admin/*', '/wp-content/*', '/*.php' ), flx_hiz_exclude_paths() ) );
			$rules = array(
				'prefetch'  => array(
					array(
						'source'    => 'document',
						'where'     => array(
							'and' => array(
								array( 'href_matches' => $base . '/*' ),
								array( 'not' => array( 'href_matches' => $not ) ),
								array( 'not' => array( 'selector_matches' => 'a[rel~=nofollow], a[target=_blank], a[download]' ) ),
							),
						),
						'eagerness' => 'conservative',
					),
				),
			);
			echo '<script type="speculationrules">' . wp_json_encode( $rules, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		}
	);
}
