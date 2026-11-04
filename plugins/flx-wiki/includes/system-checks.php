<?php
/**
 * Ortak sistem kontrolü (`flx_system_checks`) ve ayar şeması (`flx_settings_schema`).
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-wiki'] = FLXWK_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$pretty = (bool) get_option( 'permalink_structure' );
		$checks[] = array(
			'id'           => 'flxwk.permalinks',
			'source'       => 'Flux Wiki',
			'group'        => 'Config',
			'label'        => __( 'Wiki adresleri', 'flx-wiki' ),
			'status'       => $pretty ? 'ok' : 'error',
			'message'      => $pretty
				/* translators: %s: wiki adresi */
				? sprintf( __( 'Wiki %s adresinde; .md ve llms.txt adresleri çalışıyor.', 'flx-wiki' ), wp_make_link_relative( flxwk_home_url() ) )
				: __( 'Kalıcı bağlantılar "Düz" ayarında; /wiki, .md ve llms.txt adresleri çalışmaz. Ayarlar › Kalıcı bağlantılar\'dan "Yazı adı"nı seçin.', 'flx-wiki' ),
			'action_url'   => $pretty ? flxwk_home_url() : admin_url( 'options-permalink.php' ),
			'action_label' => $pretty ? __( 'Wiki\'yi aç', 'flx-wiki' ) : __( 'Kalıcı bağlantılar', 'flx-wiki' ),
		);
		if ( 'off' !== flxwk_opt( 'ask' ) ) {
			$ready    = flxwk_ai_ready();
			$checks[] = array(
				'id'           => 'flxwk.ask',
				'source'       => 'Flux Wiki',
				'group'        => 'Config',
				'label'        => __( 'Wiki\'ye sor', 'flx-wiki' ),
				'status'       => $ready ? 'ok' : 'info',
				'message'      => $ready ? __( 'Flux AI hazır; ziyaretçiler wiki içeriğinden cevap alabilir.', 'flx-wiki' ) : __( 'Flux AI kurulu değil, anahtar yok ya da Flux Wiki sistemi kapalı; "Wiki\'ye sor" gizli.', 'flx-wiki' ),
				'action_url'   => flxwk_admin_url(),
				'action_label' => __( 'Ayarlar', 'flx-wiki' ),
			);
		}
		return $checks;
	}
);

add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$s['flxwk_settings'] = array(
			'label'  => 'Flux Wiki',
			'fields' => array(
				'title'     => array( 'type' => 'string', 'default' => '', 'desc' => 'Wiki name; empty = "Developer Wiki" in the visitor language.' ),
				'intro'     => array( 'type' => 'text', 'default' => '', 'desc' => 'Intro on the wiki home, meta description and llms.txt.' ),
				'ask'       => array( 'type' => 'enum', 'enum' => array( 'off', 'users', 'all' ), 'default' => 'users', 'desc' => 'Who can use "Ask the wiki" (Flux AI).' ),
				'ask_limit' => array( 'type' => 'int', 'default' => 20, 'desc' => 'Questions per person per hour.' ),
				'ai_links'  => array( 'type' => 'bool', 'default' => true, 'desc' => 'Show "Open in ChatGPT / Claude / Perplexity".' ),
				'edit_link' => array( 'type' => 'bool', 'default' => true, 'desc' => 'Show "Edit" to editors.' ),
			),
		);
		return $s;
	}
);
