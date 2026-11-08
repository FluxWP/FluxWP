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
			'label'        => __( 'Wiki addresses', 'flx-wiki' ),
			'status'       => $pretty ? 'ok' : 'error',
			'message'      => $pretty
				/* translators: %s: wiki adresi */
				? sprintf( __( 'The wiki is at %s; .md and llms.txt addresses work.', 'flx-wiki' ), wp_make_link_relative( flxwk_home_url() ) )
				: __( 'Permalinks are set to "Plain"; /wiki, .md and llms.txt addresses do not work. Choose "Post name" in Settings › Permalinks.', 'flx-wiki' ),
			'action_url'   => $pretty ? flxwk_home_url() : admin_url( 'options-permalink.php' ),
			'action_label' => $pretty ? __( 'Open wiki', 'flx-wiki' ) : __( 'Permalinks', 'flx-wiki' ),
		);
		if ( 'off' !== flxwk_opt( 'ask' ) ) {
			$ready    = flxwk_ai_ready();
			$checks[] = array(
				'id'           => 'flxwk.ask',
				'source'       => 'Flux Wiki',
				'group'        => 'Config',
				'label'        => __( 'Ask the wiki', 'flx-wiki' ),
				'status'       => $ready ? 'ok' : 'info',
				'message'      => $ready ? __( 'Flux AI is ready; visitors can get answers from the wiki content.', 'flx-wiki' ) : __( 'Flux AI is not installed, has no key or the Flux Wiki system is off; "Ask the wiki" is hidden.', 'flx-wiki' ),
				'action_url'   => flxwk_admin_url(),
				'action_label' => __( 'Settings', 'flx-wiki' ),
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
				'enabled'   => array( 'type' => 'bool', 'default' => true, 'desc' => 'Wiki open to visitors; off = 404 for everyone except editors.' ),
				'base'      => array( 'type' => 'string', 'default' => 'wiki', 'desc' => 'URL base (lowercase, digits, hyphen).' ),
				'accent'    => array( 'type' => 'string', 'default' => '', 'desc' => 'Accent colour (#hex); empty = theme colour.' ),
				'width'     => array( 'type' => 'enum', 'enum' => array( 'normal', 'wide' ), 'default' => 'normal', 'desc' => 'Layout width.' ),
				'nav'       => array( 'type' => 'bool', 'default' => true, 'desc' => 'Left page menu and search.' ),
				'toc'       => array( 'type' => 'bool', 'default' => true, 'desc' => 'Right "On this page" table of contents.' ),
			),
		);
		return $s;
	}
);
