<?php
/**
 * FluxWP system checks: shared contract and screen (same file ships in Flux Donate and Flux Forms;
 * other FluxWP plugins and the theme may copy it). Whichever copy loads first defines the functions.
 *
 * Contract
 * --------
 * Register checks with the `flx_system_checks` filter. Each check is an array:
 *   id           string  unique, "<plugin>.<what>", e.g. 'flxb.cron'
 *   source       string  plugin or theme name shown to the admin, e.g. 'Flux Donate'
 *   group        string  'Cron' | 'Payments' | 'Plugins' | 'Config' | 'Email' | 'Security' | any other
 *   label        string  what is checked
 *   status       string  'ok' | 'warning' | 'error' | 'info'
 *   message      string  plain text for a person: what was found and what to do
 *   action_url   string  optional admin link (settings page, or an admin-post test URL with a nonce)
 *   action_label string  optional link text
 * Checks must be cheap: options, constants, a few DB queries. No external HTTP unless the admin
 * clicks a "Test connection" link (action_url) that runs it and stores the result for the check.
 *
 *   add_filter( 'flx_system_checks', function ( $checks ) {
 *       $checks[] = array( 'id' => 'mytheme.logo', 'source' => 'Theme', 'group' => 'Config',
 *           'label' => 'Logo', 'status' => 'ok', 'message' => 'Logo is set.' );
 *       return $checks;
 *   } );
 *
 * Functions (each defined only if missing):
 *   flx_system_checks_run()            every check, fields completed (same as Flux Certificates 1.2.4)
 *   flx_system_checks_sorted()         the same list ordered for display
 *   flx_system_checks_summary( $list ) array( ok, warning, error, info )
 *   flx_system_checks_render( $args )  prints the screen (summary, grouped table, re-check link)
 *   flx_system_checks_boot()           dashboard notice + admin bar item when an error exists
 * Filters: flx_system_checks, flx_system_checks_labels (screen texts), flx_system_checks_page_url
 * (where the notice links), flx_system_checks_versions (notice dismissals reset on new versions).
 *
 * GPL v2 or later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FLX_SYSTEM_CHECKS_VERSION' ) ) {
	define( 'FLX_SYSTEM_CHECKS_VERSION', '1.0' );
}

if ( ! function_exists( 'flx_system_checks_labels' ) ) {
	/** Screen texts; plugins translate them through the `flx_system_checks_labels` filter. */
	function flx_system_checks_labels() {
		$en = array(
			'title'       => 'System check',
			'intro'       => 'Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.',
			'ok'          => 'OK',
			'warning'     => 'Warning',
			'error'       => 'Error',
			'info'        => 'Info',
			'summary'     => '%1$d OK · %2$d warnings · %3$d errors',
			'recheck'     => 'Check again',
			'filter_hint' => 'Show only these (click again to show all)',
			'checked'     => 'Checked: %s',
			'col_status'  => 'Status',
			'col_check'   => 'Check',
			'col_detail'  => 'Details',
			'col_source'  => 'Source',
			'col_action'  => 'Action',
			'none'        => 'No checks are registered.',
			'notice'      => 'System check found %d problem(s) that can stop donations or automations.',
			'notice_link' => 'Open system check',
			'dismiss'     => 'Hide until the next update',
			'bar'         => 'System: %d error(s)',
			'broken'      => 'This check could not run: %s',
			'groups'      => array(
				'Cron'     => 'Scheduled tasks (cron)',
				'Payments' => 'Payments',
				'Plugins'  => 'Plugins',
				'Config'   => 'Settings',
				'Email'    => 'E-mail',
				'Security' => 'Security',
				'Forms'    => 'Forms',
				'Other'    => 'Other',
			),
		);
		$l = apply_filters( 'flx_system_checks_labels', $en );
		$l = is_array( $l ) ? array_merge( $en, $l ) : $en;
		$l['groups'] = array_merge( $en['groups'], is_array( $l['groups'] ?? null ) ? $l['groups'] : array() );
		return $l;
	}
}

if ( ! function_exists( 'flx_system_checks_run' ) ) {
	/** Tüm FluxWP kontrollerini toplar ve biçimini düzeltir. */
	function flx_system_checks_run() {
		$out = array();
		foreach ( (array) apply_filters( 'flx_system_checks', array() ) as $c ) {
			if ( ! is_array( $c ) || empty( $c['label'] ) ) {
				continue;
			}
			$c = wp_parse_args( $c, array( 'id' => '', 'source' => '', 'group' => '', 'label' => '', 'status' => 'info', 'message' => '', 'action_url' => '', 'action_label' => '' ) );
			if ( ! in_array( $c['status'], array( 'ok', 'warning', 'error', 'info' ), true ) ) {
				$c['status'] = 'info';
			}
			$out[] = $c;
		}
		return $out;
	}
}

if ( ! function_exists( 'flx_system_checks_sorted' ) ) {
	/**
	 * flx_system_checks_run() ordered for display: fixed group order (Cron, Payments, Plugins,
	 * Config, Email, Security, Forms, then others A–Z), errors first inside a group. Checks with
	 * an empty group go to 'Other'. A check whose callback throws becomes one error line.
	 * Also remembers the error ids for the admin bar item (option flx_system_checks_last).
	 */
	function flx_system_checks_sorted() {
		try {
			$out = flx_system_checks_run();
		} catch ( Throwable $e ) {
			$out = array(
				array(
					'id'           => 'flx.checks',
					'source'       => 'FluxWP',
					'group'        => 'Config',
					'label'        => flx_system_checks_labels()['title'],
					'status'       => 'error',
					'message'      => sprintf( flx_system_checks_labels()['broken'], $e->getMessage() ),
					'action_url'   => '',
					'action_label' => '',
				),
			);
		}
		$order = array_flip( array( 'Cron', 'Payments', 'Plugins', 'Config', 'Email', 'Security', 'Forms' ) );
		$rank  = array( 'error' => 0, 'warning' => 1, 'ok' => 2, 'info' => 3 );
		foreach ( $out as $i => $c ) {
			$out[ $i ]['group'] = '' !== trim( (string) $c['group'] ) ? (string) $c['group'] : 'Other';
			$out[ $i ]['_i']    = $i;
		}
		usort(
			$out,
			function ( $a, $b ) use ( $order, $rank ) {
				$ga = $order[ $a['group'] ] ?? 50;
				$gb = $order[ $b['group'] ] ?? 50;
				if ( $ga !== $gb ) {
					return $ga - $gb;
				}
				if ( $a['group'] !== $b['group'] ) {
					return strcmp( $a['group'], $b['group'] );
				}
				return ( $rank[ $a['status'] ] - $rank[ $b['status'] ] ) ?: ( $a['_i'] - $b['_i'] );
			}
		);
		$errors = array();
		foreach ( $out as $i => $c ) {
			unset( $out[ $i ]['_i'] );
			if ( 'error' === $c['status'] ) {
				$errors[] = (string) $c['id'] . '|' . (string) $c['label'];
			}
		}
		$prev = get_option( 'flx_system_checks_last' );
		if ( ! is_array( $prev ) || ( $prev['errors'] ?? null ) !== $errors || time() - (int) ( $prev['at'] ?? 0 ) > 600 ) {
			update_option( 'flx_system_checks_last', array( 'errors' => $errors, 'at' => time() ), false );
		}
		return $out;
	}
}

if ( ! function_exists( 'flx_system_checks_summary' ) ) {
	function flx_system_checks_summary( array $checks ) {
		$n = array( 'ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0 );
		foreach ( $checks as $c ) {
			if ( isset( $n[ $c['status'] ?? '' ] ) ) {
				++$n[ $c['status'] ];
			}
		}
		return $n;
	}
}

if ( ! function_exists( 'flx_system_checks_icon' ) ) {
	function flx_system_checks_icon( $status ) {
		$p = array(
			'ok'      => '<circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.6 2.5L16 9.5"/>',
			'warning' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
			'error'   => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
			'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		);
		return '<svg class="flxsc-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $status ] ?? $p['info'] ) . '</svg>';
	}
}

if ( ! function_exists( 'flx_system_checks_css' ) ) {
	function flx_system_checks_css() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return '<style id="flx-system-checks-css">
.flxsc{--flxsc-ink:#14324A;--flxsc-text:#4A5B66;--flxsc-muted:#6B7C86;--flxsc-line:#DCE9E0;--flxsc-surface:#fff;--flxsc-soft:#F5FAF7;--flxsc-ok:#0A6B46;--flxsc-ok-bg:#EAF5EE;--flxsc-warn:#7A4B00;--flxsc-warn-bg:#FFF7E6;--flxsc-err:#B32D2E;--flxsc-err-bg:#FCEDED;--flxsc-info:#234E73;--flxsc-info-bg:#E8F0F7;color:var(--flxsc-text);font-size:14px;max-width:1180px}
.flxsc *{box-sizing:border-box}
.flxsc-intro{margin:0 0 14px;color:var(--flxsc-muted);max-width:760px}
.flxsc-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:0 0 18px}
.flxsc-chips{display:flex;flex-wrap:wrap;gap:8px}
.flxsc-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;font-weight:700;font-size:13px;line-height:1.2}
.flxsc-chip strong{font-size:16px}
.flxsc-chip{border:1px solid transparent;font-family:inherit;cursor:pointer}
.flxsc-chip:hover:not(:disabled){border-color:currentColor}
.flxsc-chip:focus-visible{outline:2px solid currentColor;outline-offset:2px}
.flxsc-chip:disabled{opacity:.5;cursor:default}
.flxsc-chip[aria-pressed="true"]{border-color:currentColor;box-shadow:inset 0 0 0 1px currentColor}
.flxsc-hidden{display:none!important}
.flxsc-s-ok{color:var(--flxsc-ok);background:var(--flxsc-ok-bg)}
.flxsc-s-warning{color:var(--flxsc-warn);background:var(--flxsc-warn-bg)}
.flxsc-s-error{color:var(--flxsc-err);background:var(--flxsc-err-bg)}
.flxsc-s-info{color:var(--flxsc-info);background:var(--flxsc-info-bg)}
.flxsc-when{color:var(--flxsc-muted);font-size:12.5px}
.flxsc-btn{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:6px 14px;border-radius:9px;border:1px solid var(--flxsc-line);background:var(--flxsc-surface);color:var(--flxsc-ink);font-weight:700;text-decoration:none;cursor:pointer;font-size:13px}
.flxsc-btn:hover,.flxsc-btn:focus{border-color:var(--flxsc-ok);color:var(--flxsc-ok)}
.flxsc-btn:focus-visible{outline:2px solid var(--flxsc-ok);outline-offset:2px}
.flxsc-top .flxsc-btn{margin-left:auto}
.flxsc-group{background:var(--flxsc-surface);border:1px solid var(--flxsc-line);border-radius:14px;margin:0 0 16px;overflow:hidden}
.flxsc-group h2{margin:0;padding:12px 16px;font-size:15px;color:var(--flxsc-ink);background:var(--flxsc-soft);border-bottom:1px solid var(--flxsc-line);display:flex;gap:8px;align-items:center}
.flxsc-group h2 .flxsc-count{margin-left:auto;font-size:12px;font-weight:600;color:var(--flxsc-muted)}
.flxsc-table{width:100%;border-collapse:collapse;table-layout:fixed}
.flxsc-table th{font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--flxsc-muted);text-align:left;font-weight:700;padding:8px 16px;border-bottom:1px solid var(--flxsc-line)}
.flxsc-table td{padding:12px 16px;border-bottom:1px solid var(--flxsc-line);vertical-align:top;line-height:1.5}
.flxsc-table tr:last-child td{border-bottom:0}
.flxsc-st{display:inline-flex;align-items:center;gap:6px;padding:3px 10px 3px 7px;border-radius:99px;font-weight:700;font-size:12.5px;white-space:nowrap}
.flxsc-label{font-weight:700;color:var(--flxsc-ink)}
.flxsc-msg{white-space:pre-line;overflow-wrap:anywhere}
.flxsc-src{color:var(--flxsc-muted);font-size:12.5px;white-space:nowrap}
.flxsc-act{overflow-wrap:anywhere}
.flxsc-act a{font-weight:700}
.flxsc-col-st{width:118px}.flxsc-col-check{width:24%}.flxsc-col-src{width:96px}.flxsc-col-act{width:170px}
.flxsc-empty{padding:24px;text-align:center;color:var(--flxsc-muted)}
@media (max-width:782px){
.flxsc-top .flxsc-btn{margin-left:0}
.flxsc-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.flxsc-table,.flxsc-table tbody,.flxsc-table tr,.flxsc-table td{display:block;width:100%}
.flxsc-table tr{padding:12px 14px;border-bottom:1px solid var(--flxsc-line)}
.flxsc-table tr:last-child{border-bottom:0}
.flxsc-table td{border:0;padding:3px 0}
.flxsc-act,.flxsc-src{white-space:normal}
.flxsc-act a{display:inline-block;padding:6px 0}
}
</style>';
	}
}

if ( ! function_exists( 'flx_system_checks_render' ) ) {
	/**
	 * Print the screen. $args: recheck_url (link of the "Check again" button; default: this page),
	 * checks (precomputed list), heading (bool, print an h2 title; default false).
	 */
	function flx_system_checks_render( array $args = array() ) {
		$l      = flx_system_checks_labels();
		$checks = isset( $args['checks'] ) && is_array( $args['checks'] ) ? $args['checks'] : flx_system_checks_sorted();
		$n      = flx_system_checks_summary( $checks );
		$again  = ! empty( $args['recheck_url'] ) ? (string) $args['recheck_url'] : remove_query_arg( array( 'flxsc', '_wpnonce' ) );
		$again  = add_query_arg( 'flxsc', time(), $again );
		echo flx_system_checks_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
		echo '<div class="flxsc">';
		if ( ! empty( $args['heading'] ) ) {
			echo '<h2>' . esc_html( $l['title'] ) . '</h2>';
		}
		echo '<p class="flxsc-intro">' . esc_html( $l['intro'] ) . '</p>';
		echo '<div class="flxsc-top"><div class="flxsc-chips" role="group" aria-label="' . esc_attr( sprintf( $l['summary'], $n['ok'], $n['warning'], $n['error'] ) ) . '">';
		foreach ( array( 'ok', 'warning', 'error', 'info' ) as $s ) {
			if ( 'info' === $s && ! $n['info'] ) {
				continue;
			}
			echo '<button type="button" class="flxsc-chip flxsc-s-' . esc_attr( $s ) . '" data-filter="' . esc_attr( $s ) . '" aria-pressed="false" title="' . esc_attr( $l['filter_hint'] ) . '"' . ( $n[ $s ] ? '' : ' disabled' ) . '>' . flx_system_checks_icon( $s ) . '<strong>' . (int) $n[ $s ] . '</strong> ' . esc_html( $l[ $s ] ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><span class="flxsc-when">' . esc_html( sprintf( $l['checked'], wp_date( 0 === strpos( determine_locale(), 'tr' ) ? 'H:i' : get_option( 'time_format', 'H:i' ) ) ) ) . '</span>';
		echo '<a class="flxsc-btn" href="' . esc_url( $again ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4L21 8"/><path d="M21 3v5h-5"/></svg>' . esc_html( $l['recheck'] ) . '</a></div>';
		if ( ! $checks ) {
			echo '<div class="flxsc-group"><p class="flxsc-empty">' . esc_html( $l['none'] ) . '</p></div></div>';
			return;
		}
		$groups = array();
		foreach ( $checks as $c ) {
			$groups[ $c['group'] ][] = $c;
		}
		$gi = 0;
		foreach ( $groups as $g => $rows ) {
			$g   = (string) $g;
			$gn  = flx_system_checks_summary( $rows );
			$gid = 'flxsc-g-' . ( ++$gi );
			echo '<section class="flxsc-group" aria-labelledby="' . esc_attr( $gid ) . '"><h2 id="' . esc_attr( $gid ) . '">' . esc_html( $l['groups'][ $g ] ?? $g );
			echo '<span class="flxsc-count">' . esc_html( sprintf( $l['summary'], $gn['ok'], $gn['warning'], $gn['error'] ) ) . '</span></h2>';
			echo '<table class="flxsc-table"><thead><tr><th scope="col" class="flxsc-col-st">' . esc_html( $l['col_status'] ) . '</th><th scope="col" class="flxsc-col-check">' . esc_html( $l['col_check'] ) . '</th><th scope="col">' . esc_html( $l['col_detail'] ) . '</th><th scope="col" class="flxsc-col-src">' . esc_html( $l['col_source'] ) . '</th><th scope="col" class="flxsc-col-act">' . esc_html( $l['col_action'] ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $c ) {
				$c = array_map(
					function ( $v ) {
						return is_scalar( $v ) ? (string) $v : '';
					},
					$c
				);
				echo '<tr' . ( '' !== $c['id'] ? ' id="flxsc-' . esc_attr( $c['id'] ) . '"' : '' ) . ' data-status="' . esc_attr( $c['status'] ) . '">';
				echo '<td><span class="flxsc-st flxsc-s-' . esc_attr( $c['status'] ) . '">' . flx_system_checks_icon( $c['status'] ) . esc_html( $l[ $c['status'] ] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td class="flxsc-label">' . esc_html( $c['label'] ) . '</td>';
				echo '<td class="flxsc-msg">' . esc_html( $c['message'] ) . '</td>';
				echo '<td class="flxsc-src">' . esc_html( $c['source'] ) . '</td>';
				echo '<td class="flxsc-act">' . ( '' !== $c['action_url'] ? '<a href="' . esc_url( $c['action_url'] ) . '">' . esc_html( '' !== $c['action_label'] ? $c['action_label'] : $l['col_action'] ) . '</a>' : '' ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></section>';
		}
		// Same behavior as the theme's screen: a chip shows only that status, clicking it again shows all; #error opens filtered.
		echo "<script>(function(){var root=document.currentScript.parentNode,pills=root.querySelectorAll('.flxsc-chip[data-filter]');function apply(st){pills.forEach(function(b){b.setAttribute('aria-pressed',b.dataset.filter===st?'true':'false');});root.querySelectorAll('.flxsc-group').forEach(function(g){var shown=0;g.querySelectorAll('tr[data-status]').forEach(function(r){var on=!st||r.dataset.status===st;r.classList.toggle('flxsc-hidden',!on);shown+=on?1:0;});g.classList.toggle('flxsc-hidden',!shown);});try{history.replaceState(null,'',st?'#'+st:location.pathname+location.search);}catch(e){}}pills.forEach(function(b){b.addEventListener('click',function(){apply('true'===b.getAttribute('aria-pressed')?'':b.dataset.filter);});});var h=location.hash.slice(1);if(/^(ok|warning|error|info)$/.test(h)){apply(h);}})();</script>";
		echo '</div>';
	}
}

if ( ! function_exists( 'flx_system_checks_boot' ) ) {
	/** Dashboard notice and admin bar item, only while an error exists (hidden per version). */
	function flx_system_checks_boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		$key = function ( array $errors ) {
			$v = (array) apply_filters( 'flx_system_checks_versions', array() );
			ksort( $v );
			return md5( wp_json_encode( array( $errors, $v ) ) );
		};
		$dismissed = function ( array $errors ) use ( $key ) {
			return (string) get_user_meta( get_current_user_id(), 'flx_system_checks_dismissed', true ) === $key( $errors );
		};

		add_action(
			'admin_notices',
			function () use ( $dismissed ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				$url    = (string) apply_filters( 'flx_system_checks_page_url', '' );
				if ( ! $screen || 'dashboard' !== $screen->id || '' === $url || ! current_user_can( 'manage_options' ) ) {
					return;
				}
				flx_system_checks_sorted();
				$last   = get_option( 'flx_system_checks_last' );
				$errors = is_array( $last ) && ! empty( $last['errors'] ) ? array_values( (array) $last['errors'] ) : array();
				if ( ! $errors || $dismissed( $errors ) ) {
					return;
				}
				$l    = flx_system_checks_labels();
				$hide = wp_nonce_url( admin_url( 'admin-post.php?action=flx_system_checks_dismiss' ), 'flx_system_checks_dismiss' );
				echo '<div class="notice notice-error flx-system-checks-notice"><p><strong>' . esc_html( sprintf( $l['notice'], count( $errors ) ) ) . '</strong> <a href="' . esc_url( $url ) . '">' . esc_html( $l['notice_link'] ) . '</a> · <a href="' . esc_url( $hide ) . '">' . esc_html( $l['dismiss'] ) . '</a></p></div>';
			}
		);

		add_action(
			'admin_bar_menu',
			function ( $bar ) use ( $dismissed ) {
				if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
					return;
				}
				$last   = get_option( 'flx_system_checks_last' );
				$errors = is_array( $last ) && ! empty( $last['errors'] ) ? array_values( (array) $last['errors'] ) : array();
				$url    = (string) apply_filters( 'flx_system_checks_page_url', '' );
				if ( ! $errors || '' === $url || $dismissed( $errors ) ) {
					return;
				}
				$l = flx_system_checks_labels();
				$bar->add_node(
					array(
						'id'    => 'flx-system-checks',
						'title' => '<span class="ab-icon dashicons dashicons-warning" style="top:2px" aria-hidden="true"></span><span class="ab-label">' . esc_html( sprintf( $l['bar'], count( $errors ) ) ) . '</span>',
						'href'  => $url . '#error',
						'meta'  => array( 'class' => 'flx-system-checks-bar' ),
					)
				);
			},
			90
		);

		add_action(
			'admin_post_flx_system_checks_dismiss',
			function () use ( $key ) {
				check_admin_referer( 'flx_system_checks_dismiss' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( '', 403 );
				}
				$last   = get_option( 'flx_system_checks_last' );
				$errors = is_array( $last ) && ! empty( $last['errors'] ) ? array_values( (array) $last['errors'] ) : array();
				update_user_meta( get_current_user_id(), 'flx_system_checks_dismissed', $key( $errors ) );
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
				exit;
			}
		);
	}
}
