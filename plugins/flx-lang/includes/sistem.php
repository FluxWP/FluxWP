<?php
/**
 * Ortak sistem kontrolü (flx_system_checks). Dış HTTP yok.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-lang'] = FLXL_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = __( 'Flux Language', 'flx-lang' );
		$url    = admin_url( 'options-general.php?page=flx-lang' );
		$langs  = flxl_languages();

		$checks[] = array(
			'id'           => 'flxl.languages',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'Languages', 'flx-lang' ),
			'status'       => count( $langs ) > 1 ? 'ok' : 'info',
			'message'      => count( $langs ) > 1
				? implode( ' · ', array_map( function ( $l ) {
					return $l['name'] . ' (' . ( $l['code'] === flxl_default_language() ? '/' : '/' . $l['code'] . '/' ) . ')';
				}, $langs ) )
				: __( 'Only the default language is active; the site is single-language.', 'flx-lang' ),
			'action_url'   => $url,
			'action_label' => __( 'Languages', 'flx-lang' ),
		);

		// Çekirdek dil paketleri: İngilizce dışındaki dillerde WordPress/WooCommerce çevirisi kurulu olmalı.
		$installed = function_exists( 'get_available_languages' ) ? get_available_languages() : array();
		$missing   = array();
		foreach ( $langs as $code => $l ) {
			$loc = flxl_language_locale( $code );
			if ( 0 !== strpos( $loc, 'en_' ) && ! in_array( $loc, $installed, true ) ) {
				$missing[] = $l['name'] . ' (' . $loc . ')';
			}
		}
		$checks[] = array(
			'id'           => 'flxl.core_lang',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'WordPress language packs', 'flx-lang' ),
			'status'       => $missing ? 'warning' : 'ok',
			'message'      => $missing
				? sprintf(
					/* translators: %s: language list */
					__( 'Not installed: %s. Pick it once in Settings › General › Site Language and save (the pack downloads), then switch back.', 'flx-lang' ),
					implode( ', ', $missing )
				)
				: __( 'All active languages have WordPress translations.', 'flx-lang' ),
			'action_url'   => admin_url( 'options-general.php' ),
			'action_label' => __( 'General settings', 'flx-lang' ),
		);

		// Çeviri kapsamı (yayındaki varsayılan dil içeriği).
		foreach ( $langs as $code => $l ) {
			if ( $code === flxl_default_language() ) {
				continue;
			}
			$total = new WP_Query( array( 'post_type' => array( 'page' ), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'flxl_lang' => flxl_default_language() ) );
			$done  = count( array_filter( flxl_default_ids_translated_to( $code ), function ( $id ) {
				return 'page' === get_post_type( $id );
			} ) );
			$all   = max( 0, (int) $total->found_posts - count( flxl_shared_ids() ) );
			$checks[] = array(
				'id'           => 'flxl.coverage.' . $code,
				'source'       => $src,
				'group'        => 'Config',
				/* translators: %s: language name */
				'label'        => sprintf( __( 'Page translations: %s', 'flx-lang' ), $l['name'] ),
				'status'       => 'info',
				'message'      => sprintf(
					/* translators: 1: translated count, 2: total, 3: fallback info */
					__( '%1$d / %2$d pages translated. %3$s', 'flx-lang' ),
					$done,
					$all,
					flxl_fallback() ? __( 'Untranslated ones are shown in Turkish (canonical address is Turkish).', 'flx-lang' ) : __( 'Untranslated ones are hidden in this language.', 'flx-lang' )
				),
				'action_url'   => admin_url( 'edit.php?post_type=page&flxl_filter=' . flxl_default_language() ),
				'action_label' => __( 'Pages', 'flx-lang' ),
			);
		}

		// Ülke bilgisi (ziyaretçi önerisi).
		if ( 'off' !== flxl_settings()['suggest'] ) {
			$seen = get_option( 'flxl_geo_seen' );
			$src2 = is_array( $seen ) ? (string) $seen['source'] : '';
			$checks[] = array(
				'id'           => 'flxl.geo',
				'source'       => $src,
				'group'        => 'Config',
				'label'        => __( 'Visitor country', 'flx-lang' ),
				'status'       => '' === $src2 ? 'info' : ( 'none' === $src2 ? 'info' : 'ok' ),
				'message'      => '' === $src2
					? __( 'No visitor has been checked for a language suggestion yet.', 'flx-lang' )
					: ( 'none' === $src2
						? __( 'The server sends no country information; the browser time zone is used (Türkiye time = Turkish). If Cloudflare is enabled, the country code is used automatically.', 'flx-lang' )
						/* translators: %s: header name */
						: sprintf( __( 'Country information comes from the %s header.', 'flx-lang' ), $src2 ) ),
				'action_url'   => $url,
				'action_label' => __( 'Languages', 'flx-lang' ),
			);
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			$checks[] = array(
				'id'           => 'flxl.permalinks',
				'source'       => $src,
				'group'        => 'Config',
				'label'        => __( 'Permalinks', 'flx-lang' ),
				'status'       => 'warning',
				'message'      => __( 'Plain (?p=) links are in use; choose the "Post name" structure for language addresses.', 'flx-lang' ),
				'action_url'   => admin_url( 'options-permalink.php' ),
				'action_label' => __( 'Permalinks', 'flx-lang' ),
			);
		}
		return $checks;
	}
);
