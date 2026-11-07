<?php
/**
 * Flux App: wiki okuma, arama ve Markdown düzenleme.
 * Markdown `wp/v2/flx_wiki` alanında `markdown` olarak açılır (_flxwk_md).
 *
 * @package flx-wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxwk_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxwk_app_texts( $v, $domain );
		}
	}
	return $node;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_field(
			FLXWK_PT,
			'markdown',
			array(
				'get_callback'    => function ( $post ) {
					$id = is_array( $post ) ? (int) $post['id'] : (int) $post->ID;
					return (string) get_post_meta( $id, '_flxwk_md', true );
				},
				'update_callback' => function ( $value, $post ) {
					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return;
					}
					$md = str_replace( "\r\n", "\n", (string) $value );
					update_post_meta( $post->ID, '_flxwk_md', wp_slash( $md ) );
					if ( function_exists( 'flxwk_render' ) ) {
						$rendered                 = flxwk_render( $md );
						$GLOBALS['flxwk_saving']  = true;
						wp_update_post(
							array(
								'ID'           => $post->ID,
								'post_content' => $rendered['html'],
							)
						);
						unset( $GLOBALS['flxwk_saving'] );
					}
					if ( function_exists( 'flxwk_bump' ) ) {
						flxwk_bump();
					}
				},
				'schema'          => array(
					'description' => 'Markdown source',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
		// İçerik alanı CPT desteklerinde yok (Markdown kutusu kullanılıyor); uygulama okuma ekranı HTML'i buradan alır.
		register_rest_field(
			FLXWK_PT,
			'html',
			array(
				'get_callback' => function ( $post ) {
					$id = is_array( $post ) ? (int) $post['id'] : (int) $post->ID;
					return (string) get_post_field( 'post_content', $id );
				},
				'schema'       => array(
					'description' => 'Rendered HTML',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			function ( $m ) {
				$loaded     = flxapp_manifest_file(
					FLXWK_DIR . 'app/manifest.json',
					'flx-wiki',
					array(
						'version' => FLXWK_VERSION,
						'caps'    => array(
							'view' => 'edit_posts',
							'edit' => 'edit_posts',
						),
					)
				);
				$m['wiki'] = flxwk_app_texts( $loaded, 'flx-wiki' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
