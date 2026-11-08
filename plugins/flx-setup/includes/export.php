<?php
/**
 * "Bu siteden şablon çıkar": mevcut siteyi flx-preset/1 JSON'una çevirir.
 * Gizli alanlar (şifre, anahtar, token) atlanır. Gövdeler satır içi markup olarak yazılır.
 */

defined( 'ABSPATH' ) || exit;

/** Dışa aktarılan ayar option'ları. Paketler `flx_preset_export_options` ile ekler. */
function flxk_export_options() {
	return array_values(
		array_unique(
			(array) apply_filters(
				'flx_preset_export_options',
				array( 'flx_ayarlar', 'flxb_settings', 'flxb_wc', 'flxb_proxy_texts', 'flxb_terms_pages', 'flxs_settings', 'flxseo', 'flxi_settings', 'flxi_templates', 'flxc_settings', 'flxai_settings', 'flxa_settings', 'flxa_menus', 'flxg_settings', 'flxl_settings', 'flxq_settings', 'flxr_settings', 'flxu_settings', 'flxy_settings', 'flxp_settings', 'flxan_settings', 'flxsm_settings', 'flxst_settings', 'flxapp_settings', 'flxf_settings' )
			)
		)
	);
}

/** Diziden gizli anahtarları çıkarır. */
function flxk_strip_secrets( $v ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	foreach ( $v as $k => $x ) {
		if ( is_string( $k ) && flxk_is_secret_key( $k ) ) {
			unset( $v[ $k ] );
			continue;
		}
		$v[ $k ] = flxk_strip_secrets( $x );
	}
	return $v;
}

/**
 * @param array $args id, name, pages (bool), sections (profile, wp, settings, theme, content, media)
 */
function flxk_export( array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'id'       => sanitize_key( flxk_domain() ? str_replace( '.', '-', flxk_domain() ) : 'site' ),
			'name'     => get_bloginfo( 'name' ),
			'pages'    => true,
			'sections' => array( 'profile', 'wp', 'settings', 'theme', 'content', 'media' ),
		)
	);
	$s    = (array) $args['sections'];
	$out  = array(
		'format'      => FLXK_FORMAT,
		'id'          => substr( preg_replace( '/[^a-z0-9_-]/', '-', strtolower( (string) $args['id'] ) ), 0, 40 ),
		'name'        => (string) $args['name'],
		'version'     => 1,
		'description' => sprintf( /* translators: 1: site, 2: tarih */ __( 'Exported from %1$s on %2$s.', 'flx-setup' ), home_url(), wp_date( 'Y-m-d' ) ),
		'requires'    => array(
			'theme'   => get_stylesheet(),
			'plugins' => array(),
		),
	);
	foreach ( (array) get_option( 'active_plugins', array() ) as $b ) {
		$slug = dirname( $b );
		if ( 0 === strpos( $slug, 'flx-' ) || 'woocommerce' === $slug ) {
			$out['requires']['plugins'][] = $slug;
		}
	}

	$page_ref = function ( $id ) {
		$p = $id ? get_post( (int) $id ) : null;
		return $p ? '@page:' . $p->post_name : 0;
	};

	if ( in_array( 'profile', $s, true ) && flxk_profile_all() ) {
		$out['profile'] = flxk_strip_secrets( flxk_profile_all() );
	}

	if ( in_array( 'wp', $s, true ) ) {
		$wp = array();
		foreach ( array( 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'start_of_week', 'WPLANG', 'show_on_front', 'posts_per_page', 'permalink_structure' ) as $o ) {
			$v = get_option( $o );
			if ( false !== $v && '' !== $v ) {
				$wp[ $o ] = $v;
			}
		}
		foreach ( array( 'page_on_front', 'page_for_posts' ) as $o ) {
			$r = $page_ref( get_option( $o ) );
			if ( $r ) {
				$wp[ $o ] = $r;
			}
		}
		$out['wp'] = $wp;
	}

	if ( in_array( 'settings', $s, true ) ) {
		$set = array();
		foreach ( flxk_export_options() as $o ) {
			$v = get_option( $o, null );
			if ( is_array( $v ) && $v && flxk_option_allowed( $o ) ) {
				$set[ $o ] = flxk_strip_secrets( $v );
			}
		}
		$out['settings'] = $set;
	}

	if ( in_array( 'theme', $s, true ) ) {
		$th  = array();
		$sid = class_exists( 'WP_Theme_JSON_Resolver' ) ? WP_Theme_JSON_Resolver::get_user_global_styles_post_id() : 0;
		if ( $sid ) {
			$j = json_decode( (string) get_post_field( 'post_content', $sid ), true );
			if ( is_array( $j ) ) {
				unset( $j['isGlobalStylesUserThemeJSON'], $j['version'] );
				if ( $j ) {
					$th['styles'] = $j;
				}
			}
		}
		foreach ( array(
			'wp_template'      => 'templates',
			'wp_template_part' => 'parts',
		) as $pt => $key ) {
			foreach ( get_block_templates( array(), $pt ) as $tpl ) {
				if ( 'custom' !== $tpl->source ) {
					continue;
				}
				$d = array(
					'title' => $tpl->title,
					'body'  => $tpl->content,
				);
				if ( 'wp_template_part' === $pt ) {
					$d['area'] = $tpl->area;
				}
				$th[ $key ][ $tpl->slug ] = $d;
			}
		}
		if ( $th ) {
			$out['theme'] = $th;
		}
	}

	if ( in_array( 'content', $s, true ) ) {
		$content = array();
		if ( $args['pages'] ) {
			foreach ( get_pages( array( 'post_status' => 'publish,private' ) ) as $pg ) {
				$item = array(
					'type'  => 'page',
					'key'   => $pg->post_name,
					'title' => $pg->post_title,
					'body'  => $pg->post_content,
				);
				$tpl  = get_page_template_slug( $pg );
				if ( $tpl ) {
					$item['template'] = $tpl;
				}
				if ( $pg->post_parent ) {
					$item['parent'] = $page_ref( $pg->post_parent );
				}
				$content[] = $item;
			}
		}
		foreach ( get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'posts_per_page' => 20,
			)
		) as $nav ) {
			$content[] = array(
				'type'  => 'navigation',
				'key'   => $nav->post_name ? $nav->post_name : 'nav-' . $nav->ID,
				'title' => $nav->post_title,
				'body'  => $nav->post_content,
			);
		}
		$out['content'] = (array) apply_filters( 'flx_preset_export_content', $content, $args );
	}

	if ( in_array( 'media', $s, true ) ) {
		$media = array();
		foreach ( array(
			'logo' => get_theme_mod( 'custom_logo' ),
			'icon' => get_option( 'site_icon' ),
		) as $k => $id ) {
			if ( $id && wp_get_attachment_url( (int) $id ) ) {
				$media[ $k ] = array( 'url' => wp_get_attachment_url( (int) $id ) );
			}
		}
		if ( $media ) {
			$out['media'] = $media;
		}
	}
	return (array) apply_filters( 'flx_preset_export', $out, $args );
}
