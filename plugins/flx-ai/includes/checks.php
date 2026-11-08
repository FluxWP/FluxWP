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
		$src      = 'Flux AI & MCP';
		$settings = admin_url( 'admin.php?page=flx-ai-settings' );
		$ready    = flxai_providers_ready();

		$checks[] = array(
			'id'           => 'flxai.keys',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'AI keys', 'flx-ai' ),
			'status'       => $ready ? 'ok' : 'warning',
			'message'      => $ready
				/* translators: %s: provider names */
				? sprintf( __( 'Connected providers: %s.', 'flx-ai' ), implode( ', ', $ready ) )
				: __( 'No OpenRouter or Anthropic key entered; AI features will not work.', 'flx-ai' ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-ai' ),
		);

		$model = flxai_model_for( '' );
		$p     = flxai_parse_model( $model );
		$ok    = '' !== $model && isset( $ready[ $p['provider'] ] );
		$checks[] = array(
			'id'           => 'flxai.model',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Default model', 'flx-ai' ),
			'status'       => $ok ? 'ok' : ( $ready ? 'warning' : 'info' ),
			'message'      => $ok
				/* translators: %s: model */
				? sprintf( __( 'Using %s.', 'flx-ai' ), flxai_model_label( $model ) )
				: __( 'The selected model\'s provider has no key, or no model is selected.', 'flx-ai' ),
			'action_url'   => $settings,
			'action_label' => __( 'Settings', 'flx-ai' ),
		);

		$lim = flxai_limits();
		if ( ! array_filter( $lim ) ) {
			$checks[] = array(
				'id'           => 'flxai.limit',
				'source'       => $src,
				'group'        => 'AI',
				'label'        => __( 'Spending limit', 'flx-ai' ),
				'status'       => 'info',
				'message'      => __( 'No daily/weekly/monthly spending limit set.', 'flx-ai' ),
				'action_url'   => $settings . '#limits',
				'action_label' => __( 'Set a limit', 'flx-ai' ),
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
				'label'        => __( 'Spending limit', 'flx-ai' ),
				'status'       => $worst['over'] ? 'error' : ( $worst['pct'] >= (int) flxai_setting( 'limit_warn_pct', 80 ) ? 'warning' : 'ok' ),
				'message'      => sprintf(
					/* translators: 1: period, 2: spent, 3: limit */
					__( '%1$s: %2$s / %3$s.', 'flx-ai' ),
					$worst['label'],
					flxai_usd( $worst['spent'], 2 ),
					flxai_usd( $worst['limit'], 2 )
				) . ( $worst['over'] && 'block' === flxai_setting( 'limit_action' ) ? ' ' . __( 'Limit reached, AI requests stopped.', 'flx-ai' ) : '' ),
				'action_url'   => admin_url( 'admin.php?page=flx-ai-billing' ),
				'action_label' => __( 'Billing', 'flx-ai' ),
			);
		}

		global $wpdb;
		$day_err = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'log' ) . " WHERE kind='model' AND ok=0 AND error <> 'limit' AND ts >= %d", time() - DAY_IN_SECONDS ) ); // phpcs:ignore
		$day_all = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxai_table( 'log' ) . " WHERE kind='model' AND ts >= %d", time() - DAY_IN_SECONDS ) ); // phpcs:ignore
		$checks[] = array(
			'id'           => 'flxai.errors',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'Error rate, last 24 hours', 'flx-ai' ),
			'status'       => $day_all && $day_err / $day_all > 0.2 ? 'warning' : ( $day_all ? 'ok' : 'info' ),
			'message'      => $day_all
				/* translators: 1: errors, 2: total */
				? sprintf( __( '%1$s / %2$s requests failed.', 'flx-ai' ), number_format_i18n( $day_err ), number_format_i18n( $day_all ) )
				: __( 'No requests in the last 24 hours.', 'flx-ai' ),
			'action_url'   => admin_url( 'admin.php?page=flx-ai&tab=log' ),
			'action_label' => __( 'Logs', 'flx-ai' ),
		);

		$checks[] = array(
			'id'           => 'flxai.mcp',
			'source'       => $src,
			'group'        => 'AI',
			'label'        => __( 'MCP server', 'flx-ai' ),
			'status'       => flxai_mcp_enabled() ? 'ok' : 'info',
			'message'      => sprintf(
				/* translators: 1: on/off sentence, 2: tool count */
				__( '%1$s %2$d tools registered; Flux AI chat uses them too, and tools that change data ask for approval.', 'flx-ai' ),
				flxai_mcp_enabled() ? __( 'MCP server is on.', 'flx-ai' ) : __( 'MCP server is off.', 'flx-ai' ),
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
			'label'        => __( 'Visual renderer (WhatsApp/email)', 'flx-ai' ),
			'status'       => '' === $rnd ? 'info' : 'ok',
			'message'      => 'gotenberg' === $rnd
				? __( 'Gotenberg: HTML and SVG visuals can be sent as PNG.', 'flx-ai' )
				: ( 'imagick' === $rnd ? __( 'Imagick: only SVG visuals can be sent as PNG.', 'flx-ai' ) : __( 'None: channels like WhatsApp get text instead of visuals.', 'flx-ai' ) ),
			'action_url'   => $settings . '#widgets',
			'action_label' => __( 'Settings', 'flx-ai' ),
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
				'label'       => sprintf( __( '%s AI spending limit (USD)', 'flx-ai' ), flxai_period_label( $p ) ),
				'description' => __( '0 = unlimited. When reached, AI requests stop (or only warn, per setting).', 'flx-ai' ),
				'source'      => 'Flux AI & MCP',
				'checks'      => array( 'flxai.limit' ),
				'value'       => array( 'type' => 'string' ),
				'current'     => function () use ( $p ) {
					return (string) flxai_setting( 'limit_' . $p, 0 );
				},
				'run'         => function ( $value ) use ( $p ) {
					$v = str_replace( ',', '.', trim( (string) $value ) );
					if ( ! is_numeric( $v ) || (float) $v < 0 || (float) $v > 100000 ) {
						throw new Exception( __( 'Enter a valid amount (0 or a positive number).', 'flx-ai' ) );
					}
					flxai_update_settings( array( 'limit_' . $p => round( (float) $v, 2 ) ) );
					/* translators: %s: amount */
					return sprintf( __( 'Limit saved as %s.', 'flx-ai' ), flxai_usd( (float) $v, 2 ) );
				},
			);
		}
		return $fixes;
	}
);
