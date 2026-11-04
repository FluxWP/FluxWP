<?php
/**
 * Ortak Sistem Kontrolü satırları (flx_system_checks) ve düzeltmeleri (flx_system_fixes). Dış HTTP yok.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks   = is_array( $checks ) ? $checks : array();
		$src      = 'Flux AI';
		$settings = admin_url( 'admin.php?page=flx-ai-settings' );
		$ready    = flxai_providers_ready();

		$checks[] = array(
			'id'           => 'flxai.keys',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Yapay zekâ anahtarları', 'flx-ai' ),
			'status'       => $ready ? 'ok' : 'warning',
			'message'      => $ready
				/* translators: %s: provider names */
				? sprintf( __( 'Bağlı sağlayıcılar: %s.', 'flx-ai' ), implode( ', ', $ready ) )
				: __( 'OpenRouter ya da Anthropic anahtarı girilmemiş; yapay zekâ özellikleri çalışmaz.', 'flx-ai' ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-ai' ),
		);

		$model = flxai_model_for( '' );
		$p     = flxai_parse_model( $model );
		$ok    = '' !== $model && isset( $ready[ $p['provider'] ] );
		$checks[] = array(
			'id'           => 'flxai.model',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Varsayılan model', 'flx-ai' ),
			'status'       => $ok ? 'ok' : ( $ready ? 'warning' : 'info' ),
			'message'      => $ok
				/* translators: %s: model */
				? sprintf( __( '%s kullanılıyor.', 'flx-ai' ), flxai_model_label( $model ) )
				: __( 'Seçili modelin sağlayıcısının anahtarı yok ya da model seçilmemiş.', 'flx-ai' ),
			'action_url'   => $settings,
			'action_label' => __( 'Ayarlar', 'flx-ai' ),
		);

		$lim = flxai_limits();
		if ( ! array_filter( $lim ) ) {
			$checks[] = array(
				'id'           => 'flxai.limit',
				'source'       => $src,
				'group'        => 'AI',
				'label'        => __( 'Harcama sınırı', 'flx-ai' ),
				'status'       => 'info',
				'message'      => __( 'Günlük/haftalık/aylık harcama sınırı tanımlı değil.', 'flx-ai' ),
				'action_url'   => $settings . '#limits',
				'action_label' => __( 'Sınır koy', 'flx-ai' ),
			);
		} else {
			$worst = null;
			foreach ( flxai_budget_status() as $row ) {
				if ( $row['limit'] > 0 && ( ! $worst || $row['pct'] > $worst['pct'] ) ) {
					$worst = $row;
				}
			}
			$checks[] = array(
				'id'           => 'flxai.limit',
				'source'       => $src,
				'group'        => 'AI',
				'label'        => __( 'Harcama sınırı', 'flx-ai' ),
				'status'       => $worst['over'] ? 'error' : ( $worst['pct'] >= (int) flxai_setting( 'limit_warn_pct', 80 ) ? 'warning' : 'ok' ),
				'message'      => sprintf(
					/* translators: 1: period, 2: spent, 3: limit */
					__( '%1$s: %2$s / %3$s.', 'flx-ai' ),
					$worst['label'],
					flxai_usd( $worst['spent'], 2 ),
					flxai_usd( $worst['limit'], 2 )
				) . ( $worst['over'] && 'block' === flxai_setting( 'limit_action' ) ? ' ' . __( 'Sınır doldu, yapay zekâ istekleri durdu.', 'flx-ai' ) : '' ),
				'action_url'   => admin_url( 'admin.php?page=flx-ai-billing' ),
				'action_label' => __( 'Faturalandırma', 'flx-ai' ),
			);
		}

		global $wpdb;
		$day_err = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'log' ) . " WHERE kind='model' AND ok=0 AND error <> 'limit' AND ts >= %d", time() - DAY_IN_SECONDS ) ); // phpcs:ignore
		$day_all = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'log' ) . " WHERE kind='model' AND ts >= %d", time() - DAY_IN_SECONDS ) ); // phpcs:ignore
		$checks[] = array(
			'id'           => 'flxai.errors',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Son 24 saat hata oranı', 'flx-ai' ),
			'status'       => $day_all && $day_err / $day_all > 0.2 ? 'warning' : ( $day_all ? 'ok' : 'info' ),
			'message'      => $day_all
				/* translators: 1: errors, 2: total */
				? sprintf( __( '%1$s / %2$s istek hatalı.', 'flx-ai' ), number_format_i18n( $day_err ), number_format_i18n( $day_all ) )
				: __( 'Son 24 saatte istek yok.', 'flx-ai' ),
			'action_url'   => admin_url( 'admin.php?page=flx-ai&tab=log' ),
			'action_label' => __( 'Kayıtlar', 'flx-ai' ),
		);

		$checks[] = array(
			'id'           => 'flxai.mcp',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'MCP sunucusu', 'flx-ai' ),
			'status'       => flxai_mcp_enabled() ? 'ok' : 'info',
			'message'      => sprintf(
				/* translators: 1: on/off sentence, 2: tool count */
				__( '%1$s %2$d araç kayıtlı; Flux AI sohbeti de bunları kullanır, değişiklik yapanlar onay ister.', 'flx-ai' ),
				flxai_mcp_enabled() ? __( 'MCP sunucusu açık.', 'flx-ai' ) : __( 'MCP sunucusu kapalı.', 'flx-ai' ),
				count( flxai_mcp_tools() )
			),
			'action_url'   => flxai_mcp_admin_url(),
			'action_label' => 'MCP',
		);
		$rnd      = flxai_widget_renderer();
		$checks[] = array(
			'id'           => 'flxai.render',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Görsel çevirici (WhatsApp/e-posta)', 'flx-ai' ),
			'status'       => '' === $rnd ? 'info' : 'ok',
			'message'      => 'gotenberg' === $rnd
				? __( 'Gotenberg: HTML ve SVG görseller PNG olarak gönderilebilir.', 'flx-ai' )
				: ( 'imagick' === $rnd ? __( 'Imagick: yalnız SVG görseller PNG olarak gönderilebilir.', 'flx-ai' ) : __( 'Yok: WhatsApp gibi kanallarda görsel yerine metin gider.', 'flx-ai' ) ),
			'action_url'   => $settings . '#widgets',
			'action_label' => __( 'Ayarlar', 'flx-ai' ),
		);
		return $checks;
	}
);

add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$fixes = is_array( $fixes ) ? $fixes : array();
		foreach ( array( 'day', 'week', 'month' ) as $p ) {
			$fixes[ 'flxai.limit_' . $p ] = array(
				/* translators: %s: period */
				'label'       => sprintf( __( '%s yapay zekâ harcama sınırı (USD)', 'flx-ai' ), flxai_period_label( $p ) ),
				'description' => __( '0 = sınırsız. Dolunca yapay zekâ istekleri durur (ayara göre yalnız uyarı).', 'flx-ai' ),
				'source'      => 'Flux AI',
				'checks'      => array( 'flxai.limit' ),
				'value'       => array( 'type' => 'string' ),
				'current'     => function () use ( $p ) {
					return (string) flxai_setting( 'limit_' . $p, 0 );
				},
				'run'         => function ( $value ) use ( $p ) {
					$v = str_replace( ',', '.', trim( (string) $value ) );
					if ( ! is_numeric( $v ) || (float) $v < 0 || (float) $v > 100000 ) {
						throw new Exception( __( 'Geçerli bir tutar girin (0 veya pozitif sayı).', 'flx-ai' ) );
					}
					flxai_update_settings( array( 'limit_' . $p => round( (float) $v, 2 ) ) );
					/* translators: %s: amount */
					return sprintf( __( 'Sınır %s olarak kaydedildi.', 'flx-ai' ), flxai_usd( (float) $v, 2 ) );
				},
			);
		}
		return $fixes;
	}
);
