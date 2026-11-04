<?php
/**
 * Ortak sistem kontrolü (`flx_system_checks`), ayar şeması (`flx_settings_schema`) ve Flux SEO MCP durum aracı. Dış HTTP yok.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxrc_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-recaptcha'] = FLXRC_VERSION;
		return $v;
	}
);

function flxrc_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = 'Flux reCAPTCHA';
	$url    = flxrc_admin_url();

	if ( flxrc_disabled_by_constant() ) {
		$status  = 'warning';
		$message = __( 'Koruma wp-config.php\'deki FLXRC_DISABLE sabitiyle kapalı. Sorun çözüldüyse satırı silin.', 'flx-recaptcha' );
	} elseif ( ! flxrc_has_keys() ) {
		$status  = 'warning';
		$message = __( 'Google reCAPTCHA anahtarları girilmemiş; formlar korunmuyor.', 'flx-recaptcha' );
	} elseif ( ! flxrc_keys_verified() ) {
		$status  = 'warning';
		$message = __( 'Anahtarlar test edilmemiş; test geçene kadar koruma kapalı.', 'flx-recaptcha' );
	} else {
		$status  = 'ok';
		$message = sprintf( /* translators: %s: v2 or v3 */ __( 'Koruma açık (%s).', 'flx-recaptcha' ), 'v3' === flxrc_version() ? __( 'v3 görünmez', 'flx-recaptcha' ) : __( 'v2 kutucuk', 'flx-recaptcha' ) );
	}
	$checks[] = array(
		'id'           => 'flxrc.keys',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'reCAPTCHA anahtarları', 'flx-recaptcha' ),
		'status'       => $status,
		'message'      => $message,
		'action_url'   => $url . '#flxrc-keys',
		'action_label' => __( 'Ayarlar', 'flx-recaptcha' ),
	);

	if ( flxrc_active() ) {
		$names = array();
		$off   = array();
		foreach ( flxrc_contexts() as $k => $c ) {
			if ( empty( $c['available'] ) ) {
				continue;
			}
			if ( flxrc_context_on( $k ) ) {
				$names[] = $c['label'];
			} elseif ( in_array( $k, array( 'login', 'lostpassword' ), true ) ) {
				$off[] = $c['label'];
			}
		}
		$checks[] = array(
			'id'           => 'flxrc.forms',
			'source'       => $src,
			'group'        => 'Security',
			'label'        => __( 'Korunan formlar', 'flx-recaptcha' ),
			'status'       => $off ? 'warning' : ( $names ? 'ok' : 'info' ),
			'message'      => ( $names ? implode( ', ', $names ) : __( 'Hiçbir form seçilmemiş.', 'flx-recaptcha' ) ) . ( $off ? ' · ' . sprintf( /* translators: %s: form names */ __( 'Korunmuyor: %s', 'flx-recaptcha' ), implode( ', ', $off ) ) : '' ),
			'action_url'   => $url . '#flxrc-forms',
			'action_label' => __( 'Formlar', 'flx-recaptcha' ),
		);

		$err = flxrc_status( 'last_error' );
		if ( is_array( $err ) && ! empty( $err['time'] ) && $err['time'] > time() - DAY_IN_SECONDS ) {
			$checks[] = array(
				'id'           => 'flxrc.remote',
				'source'       => $src,
				'group'        => 'Security',
				'label'        => __( 'Google doğrulaması', 'flx-recaptcha' ),
				'status'       => 'config' === ( $err['kind'] ?? '' ) ? 'error' : 'warning',
				'message'      => sprintf(
					/* translators: 1: date, 2: error */
					__( '%1$s: %2$s. Anahtarları yeniden test edin; Google erişilemiyorsa Ayarlar > Google adresi: recaptcha.net.', 'flx-recaptcha' ),
					wp_date( 'd.m.Y H:i', (int) $err['time'] ),
					(string) ( $err['why'] ?? '' )
				),
				'action_url'   => $url,
				'action_label' => __( 'Test et', 'flx-recaptcha' ),
			);
		}
	}
	return $checks;
}

add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$s['flxrc_settings'] = array(
			'label'  => 'Flux reCAPTCHA',
			'fields' => array(
				'version'        => array( 'type' => 'enum', 'enum' => array( 'v3', 'v2' ), 'default' => 'v3', 'label' => 'reCAPTCHA type' ),
				'threshold'      => array( 'type' => 'string', 'default' => '0.5', 'label' => 'v3 score threshold 0.1-0.9' ),
				'domain'         => array( 'type' => 'enum', 'enum' => array( 'google', 'recaptcha' ), 'default' => 'google', 'label' => 'API host' ),
				'v2_theme'       => array( 'type' => 'enum', 'enum' => array( 'auto', 'light', 'dark' ), 'default' => 'auto' ),
				'v2_size'        => array( 'type' => 'enum', 'enum' => array( 'normal', 'compact' ), 'default' => 'normal' ),
				'hide_badge'     => array( 'type' => 'bool', 'default' => 0 ),
				'skip_logged_in' => array( 'type' => 'bool', 'default' => 1 ),
				'fail_open'      => array( 'type' => 'bool', 'default' => 1 ),
				'forms'          => array( 'type' => 'object', 'label' => 'Protected forms: context key => 0/1 (login, register, lostpassword, comments, wc_login, wc_register, checkout, donate, forms)' ),
			),
		);
		return $s;
	}
);

/* Flux SEO MCP: salt okunur durum aracı (anahtar göstermez). */
add_filter(
	'flxseo_mcp_tools',
	function ( $tools ) {
		$tools['flxrc_status'] = array(
			'title'       => 'reCAPTCHA status',
			'description' => 'Flux reCAPTCHA: whether protection is on, version, protected forms and last 30 days passed/blocked counts. Never returns keys.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$stats = flxrc_stats_totals( 30 );
				$forms = array();
				foreach ( flxrc_contexts() as $k => $c ) {
					$forms[] = array(
						'context'   => $k,
						'label'     => $c['label'],
						'installed' => (bool) $c['available'],
						'enabled'   => flxrc_context_on( $k ),
						'passed'    => (int) ( $stats[ $k ][0] ?? 0 ),
						'blocked'   => (int) ( $stats[ $k ][1] ?? 0 ),
					);
				}
				return array(
					'active'    => flxrc_active(),
					'version'   => flxrc_version(),
					'has_keys'  => flxrc_has_keys(),
					'verified'  => flxrc_keys_verified(),
					'threshold' => (float) flxrc_settings( 'threshold' ),
					'forms'     => $forms,
				);
			},
		);
		return $tools;
	}
);
