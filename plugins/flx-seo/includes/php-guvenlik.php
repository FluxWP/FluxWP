<?php
/**
 * PHP güvenlik sürümü kontrolü: bilinen açıkları kapatan en düşük yama sürümü (dal başına).
 * Tablo `flxseo_php_security_table` filtresiyle güncellenebilir; Sistem Kontrolü ve Site denetimi kullanır.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dal => açığı kapatan ilk sürüm. Listede olmayan eski dallar (ör. 8.1) desteklenmiyor sayılır;
 * listedeki en yeni daldan yeni dallar güvenli sayılır.
 *
 * @return array{advisory: string, url: string, fixed: array<string, string>}
 */
function flxseo_php_security_table() {
	return (array) apply_filters(
		'flxseo_php_security_table',
		array(
			// PHP2Shell: CVE-2026-109618, CVE-2026-109619 (2026-11).
			'advisory' => 'PHP2Shell (CVE-2026-109618, CVE-2026-109619)',
			'url'      => 'https://www.php.net/ChangeLog-8.php',
			'fixed'    => array(
				'8.2' => '8.2.35',
				'8.3' => '8.3.36',
				'8.4' => '8.4.27',
				'8.5' => '8.5.12',
			),
		)
	);
}

/**
 * Çalışan PHP'nin durumu.
 *
 * @param string|null $version Test için sürüm (varsayılan: çalışan PHP).
 * @return array{status: string, version: string, branch: string, fixed: string, advisory: string, url: string}
 *         status: ok | unpatched | unsupported
 */
function flxseo_php_security_status( $version = null ) {
	$version = $version ? (string) $version : PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
	$parts   = explode( '.', $version );
	$branch  = ( $parts[0] ?? '0' ) . '.' . ( $parts[1] ?? '0' );
	$t       = flxseo_php_security_table();
	$fixed   = (array) ( $t['fixed'] ?? array() );
	$out     = array(
		'status'   => 'ok',
		'version'  => $version,
		'branch'   => $branch,
		'fixed'    => '',
		'advisory' => (string) ( $t['advisory'] ?? '' ),
		'url'      => (string) ( $t['url'] ?? '' ),
	);
	if ( ! $fixed ) {
		return $out;
	}
	if ( isset( $fixed[ $branch ] ) ) {
		$out['fixed'] = (string) $fixed[ $branch ];
		if ( version_compare( $version, $out['fixed'], '<' ) ) {
			$out['status'] = 'unpatched';
		}
		return $out;
	}
	$branches = array_keys( $fixed );
	usort( $branches, 'version_compare' );
	if ( version_compare( $branch, $branches[0], '<' ) ) {
		$out['status'] = 'unsupported';
		$out['fixed']  = (string) $fixed[ end( $branches ) ];
	}
	return $out;
}

/** session.upload_progress.enabled açık mı (PHP2Shell'in istismar yolu). */
function flxseo_php_upload_progress_on() {
	return filter_var( ini_get( 'session.upload_progress.enabled' ), FILTER_VALIDATE_BOOLEAN );
}

/** Durumun okunur açıklaması (Sistem Kontrolü ve denetim bulgusu için). */
function flxseo_php_security_message( $s ) {
	if ( 'unpatched' === $s['status'] ) {
		/* translators: 1: çalışan PHP sürümü, 2: açık adı, 3: yamalı sürüm */
		return sprintf( __( 'PHP %1$s is not patched against %2$s. Update to at least %3$s (hosting panel › PHP version). A distribution package may carry the fix without changing the version number; ask your provider if unsure.', 'flx-seo' ), $s['version'], $s['advisory'], $s['fixed'] );
	}
	if ( 'unsupported' === $s['status'] ) {
		/* translators: 1: çalışan PHP sürümü, 2: açık adı, 3: önerilen sürüm */
		return sprintf( __( 'PHP %1$s no longer gets security fixes; holes such as %2$s stay open. Move to PHP %3$s or another patched, newer version.', 'flx-seo' ), $s['version'], $s['advisory'], $s['fixed'] );
	}
	/* translators: 1: çalışan PHP sürümü, 2: açık adı */
	return sprintf( __( 'PHP %1$s is patched against %2$s.', 'flx-seo' ), $s['version'], $s['advisory'] );
}

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$s   = flxseo_php_security_status();
		$row = array(
			'id'      => 'flxseo.php-security',
			'source'  => 'flx-seo',
			'group'   => __( 'Security', 'flx-seo' ),
			'label'   => __( 'PHP security patch', 'flx-seo' ),
			'status'  => 'ok' === $s['status'] ? 'ok' : 'error',
			'message' => flxseo_php_security_message( $s ),
		);
		if ( 'ok' !== $s['status'] && $s['url'] ) {
			$row['action_url']   = $s['url'];
			$row['action_label'] = __( 'Release notes', 'flx-seo' );
		}
		$checks[] = $row;
		if ( flxseo_php_upload_progress_on() ) {
			$checks[] = array(
				'id'      => 'flxseo.php-upload-progress',
				'source'  => 'flx-seo',
				'group'   => __( 'Security', 'flx-seo' ),
				'label'   => 'session.upload_progress',
				'status'  => 'ok' === $s['status'] ? 'warning' : 'error',
				'message' => __( 'session.upload_progress.enabled is on. WordPress does not use it; turning it off shrinks the attack surface. Set "session.upload_progress.enabled = Off" in php.ini or the hosting panel.', 'flx-seo' ),
			);
		}
		return $checks;
	},
	25
);
