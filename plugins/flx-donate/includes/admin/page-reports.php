<?php
/**
 * Flux Donate 2.11: Reports screen. Pick a report, choose a period, read the table, download CSV.
 * Managers can edit any report's JSON, add new ones and put shipped reports back as they were.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Reports {

	const SLUG = 'flxb-reports';

	public static function init() {
		foreach ( array( 'save', 'delete', 'csv' ) as $a ) {
			add_action( 'admin_post_flxb_report_' . $a, array( __CLASS__, $a ) );
		}
	}

	public static function enabled() {
		return (bool) apply_filters( 'flxb_reports_enabled', ! empty( flxb_setting( 'module_reports', 0 ) ) || flxb_track_enabled() );
	}

	private static function url( array $args = array() ) {
		return FLXB_Admin_Url::page( self::SLUG, $args );
	}

	/** Period from the query string: [from, to, key]. */
	private static function period( array $r ) {
		$p = sanitize_key( FLXB_Admin::q( 'period', '' ) );
		if ( 'custom' === $p ) {
			$ok = function ( $d ) {
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
			};
			return array( $ok( FLXB_Admin::q( 'from' ) ), $ok( FLXB_Admin::q( 'to' ) ), 'custom' );
		}
		$p = isset( FLXB_Reports::periods()[ $p ] ) ? $p : $r['period'];
		list( $f, $t ) = FLXB_Reports::range( $p );
		return array( $f, $t, $p );
	}

	private static function cell( array $col, $v, $cur ) {
		if ( $col['dim'] ) {
			return FLXB_Reports::show( $col['kind'], $v );
		}
		if ( 'money' === $col['kind'] ) {
			return flxb_money( $v, $cur );
		}
		return number_format_i18n( (float) $v, floor( (float) $v ) == $v ? 0 : 1 ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	public static function render() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		$all = FLXB_Reports::all();
		if ( '' !== FLXB_Admin::q( 'edit' ) || '1' === FLXB_Admin::q( 'new' ) ) {
			self::editor( sanitize_key( FLXB_Admin::q( 'edit' ) ) );
			return;
		}
		$id = sanitize_key( FLXB_Admin::q( 'r', (string) array_key_first( $all ) ) );
		$r  = $all[ $id ] ?? ( $all ? reset( $all ) : null );
		$actions = array();
		if ( flxb_can_manage() ) {
			$actions[] = array( 'label' => __( 'New report', 'flx-donate' ), 'url' => self::url( array( 'new' => 1 ) ), 'icon' => 'plus' );
			if ( $r ) {
				$actions[] = array( 'label' => __( 'Edit JSON', 'flx-donate' ), 'url' => self::url( array( 'edit' => $r['id'] ) ), 'icon' => 'edit' );
			}
		}
		FLXB_Admin::header( __( 'Reports', 'flx-donate' ), __( 'Counts and totals grouped the way each report says. Reports are JSON and can be edited.', 'flx-donate' ), $actions );
		if ( ! $r ) {
			echo '<div class="flxb-panel"><div class="flxb-emptystate"><h2>' . esc_html__( 'No reports', 'flx-donate' ) . '</h2></div></div>';
			FLXB_Admin::footer();
			return;
		}
		echo '<nav class="flxb-chipnav" aria-label="' . esc_attr__( 'Reports', 'flx-donate' ) . '">';
		foreach ( $all as $k => $x ) {
			echo '<a class="flxb-chipnav__a' . ( $k === $r['id'] ? ' is-active' : '' ) . '" href="' . esc_url( self::url( array( 'r' => $k ) ) ) . '">' . esc_html( FLXB_Reports::text( $x['title'] ) ) . '</a>';
		}
		echo '</nav>';

		list( $from, $to, $pk ) = self::period( $r );
		echo '<form class="flxb-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="r" value="' . esc_attr( $r['id'] ) . '">';
		echo '<label><select name="period" data-flxb-period>';
		foreach ( FLXB_Reports::periods() + array( 'custom' => __( 'Custom dates', 'flx-donate' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $pk, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'From', 'flx-donate' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '"></label><label>' . esc_html__( 'To', 'flx-donate' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '"></label>';
		$csv = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_report_csv&r=' . $r['id'] . '&from=' . $from . '&to=' . $to ), 'flxb_report_csv' );
		echo '<div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Show', 'flx-donate' ) . '</button> <a class="flxb-btn" href="' . esc_url( $csv ) . '">' . FLXB_Admin::icon( 'download' ) . esc_html__( 'CSV', 'flx-donate' ) . '</a></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>document.querySelectorAll("[data-flxb-period]").forEach(function(s){var f=s.form;f.querySelectorAll("input[type=date]").forEach(function(i){i.addEventListener("change",function(){s.value="custom";});});s.addEventListener("change",function(){if(s.value!=="custom"){f.from.value="";f.to.value="";f.submit();}});});</script>';

		$res = FLXB_Reports::run( $r, array( 'from' => $from, 'to' => $to ) );
		if ( '' !== FLXB_Reports::text( $r['description'] ) ) {
			echo '<p class="flxb-muted">' . esc_html( FLXB_Reports::text( $r['description'] ) ) . '</p>';
		}
		self::summary( $res );
		self::table( $r, $res );
		FLXB_Admin::footer();
	}

	/** Totals as stat tiles; money in the site currency first, other currencies below. */
	private static function summary( array $res ) {
		if ( ! $res['rows'] ) {
			return;
		}
		$main = flxb_default_currency();
		echo '<div class="flxb-kpis">';
		foreach ( $res['cols'] as $k => $c ) {
			if ( $c['dim'] ) {
				continue;
			}
			$sub = '';
			if ( 'money' === $c['kind'] ) {
				$curs = array_keys( $res['totals'] );
				$top  = in_array( $main, $curs, true ) ? $main : (string) reset( $curs );
				$val  = self::cell( $c, $res['totals'][ $top ][ $k ] ?? 0, $top );
				$rest = array();
				foreach ( $res['totals'] as $cur => $t ) {
					if ( $cur !== $top ) {
						$rest[] = self::cell( $c, $t[ $k ] ?? 0, $cur );
					}
				}
				$sub = $rest ? '+ ' . implode( ' · ', $rest ) : '';
			} else {
				$val = self::cell( $c, $res['total'][ $k ] ?? 0, '' );
			}
			echo '<div class="flxb-kpi"><div class="flxb-kpi__label">' . esc_html( $c['label'] ) . '</div><div class="flxb-kpi__value">' . esc_html( $val ) . '</div>' . ( '' !== $sub ? '<div class="flxb-kpi__sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div>';
		}
		echo '</div>';
	}

	private static function table( array $r, array $res ) {
		echo '<div class="flxb-panel">';
		if ( ! $res['rows'] ) {
			echo '<div class="flxb-emptystate"><h2>' . esc_html__( 'Nothing in this period', 'flx-donate' ) . '</h2></div></div>';
			return;
		}
		$cols  = $res['cols'];
		$first = '';
		foreach ( $cols as $k => $c ) {
			if ( ! $c['dim'] && '' === $first ) {
				$first = $k;
			}
		}
		$max = 0;
		if ( 'bar' === $r['chart'] ) {
			foreach ( $res['rows'] as $row ) {
				$max = max( $max, (float) $row[ $first ] );
			}
		}
		$show_cur = isset( $cols['currency'] ) && ! in_array( 'currency', $r['group'], true );
		echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-report"><thead><tr>';
		foreach ( $cols as $k => $c ) {
			if ( 'currency' === $k && $show_cur ) {
				continue;
			}
			echo '<th scope="col"' . ( $c['dim'] ? '' : ' class="num"' ) . '>' . esc_html( $c['label'] ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $res['rows'] as $row ) {
			$cur = (string) ( $row['currency'] ?? '' );
			echo '<tr>';
			$i = 0;
			foreach ( $cols as $k => $c ) {
				if ( 'currency' === $k && $show_cur ) {
					continue;
				}
				$txt = self::cell( $c, $row[ $k ], $cur );
				$cls = $c['dim'] ? ( 0 === $i ? ' class="flxb-col-main"' : '' ) : ' class="num"';
				$bar = '';
				if ( $max > 0 && $k === $first ) {
					$bar = '<span class="flxb-rbar" style="width:' . esc_attr( (string) round( 100 * (float) $row[ $k ] / $max, 1 ) ) . '%"></span>';
				}
				echo '<td' . $cls . ' data-label="' . esc_attr( $c['label'] ) . '"><span>' . esc_html( $txt ) . '</span>' . $bar . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				++$i;
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		if ( $res['cut'] ) {
			/* translators: %s: row limit */
			echo '<p class="flxb-muted">' . esc_html( sprintf( __( 'Only the first %s rows are shown. The CSV has the same rows.', 'flx-donate' ), number_format_i18n( $r['limit'] ) ) ) . '</p>';
		}
		echo '</div>';
	}

	private static function template() {
		return array(
			'id'          => 'my-report',
			'title'       => array( 'tr' => 'Yeni rapor', 'en' => 'New report' ),
			'description' => '',
			'source'      => 'donations',
			'filter'      => array( 'paid' => true ),
			'group'       => array( 'type' ),
			'metrics'     => array( 'count', 'amount', 'donors' ),
			'period'      => 'this_year',
			'chart'       => 'bar',
		);
	}

	private static function editor( $id ) {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$raw  = '' !== $id ? FLXB_Reports::raw( $id ) : null;
		$raw  = $raw ? $raw : self::template();
		$def  = '' !== $id ? FLXB_Reports::get( $id ) : null;
		$json = get_transient( 'flxb_report_draft_' . get_current_user_id() );
		if ( false !== $json ) {
			delete_transient( 'flxb_report_draft_' . get_current_user_id() );
		} else {
			$json = wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		FLXB_Admin::header( $def ? FLXB_Reports::text( $def['title'] ) : __( 'New report', 'flx-donate' ), '', array(), array( __( 'Reports', 'flx-donate' ), self::url( $id ? array( 'r' => $id ) : array() ) ) );
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card">';
		echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_report_save"><input type="hidden" name="old" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'flxb_report_save' );
		echo '<div class="flxb-field"><label for="flxb-rj">' . esc_html__( 'Report (JSON)', 'flx-donate' ) . '</label><textarea id="flxb-rj" name="json" rows="22" class="flxb-code" spellcheck="false">' . esc_textarea( (string) $json ) . '</textarea></div>';
		echo '<div class="flxb-actions"><button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form>';
		if ( $def && $def['edited'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px"><input type="hidden" name="action" value="flxb_report_delete"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">' . wp_nonce_field( 'flxb_report_delete', '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<button class="flxb-btn flxb-btn--danger" type="submit" onclick="return confirm(this.dataset.q)" data-q="' . esc_attr__( 'Are you sure?', 'flx-donate' ) . '">' . esc_html( $def['builtin'] ? __( 'Put back the original', 'flx-donate' ) : __( 'Delete report', 'flx-donate' ) ) . '</button></form>';
		}
		echo '</section><aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'What you can use', 'flx-donate' ) . '</h2></div>';
		foreach ( FLXB_Reports::sources() as $s => $sl ) {
			if ( 'items' === $s && ! flxb_track_enabled() ) {
				continue;
			}
			echo '<h3 class="flxb-h3"><code>"source": "' . esc_html( $s ) . '"</code> ' . esc_html( $sl ) . '</h3><p class="flxb-help"><b>group</b> ';
			$bits = array();
			foreach ( FLXB_Reports::dims( $s ) as $k => $d ) {
				$bits[] = '<code>' . esc_html( $k ) . '</code> ' . esc_html( $d[0] );
			}
			echo implode( ', ', $bits ) . '</p><p class="flxb-help"><b>metrics</b> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$bits = array();
			foreach ( FLXB_Reports::metrics( $s ) as $k => $d ) {
				$bits[] = '<code>' . esc_html( $k ) . '</code> ' . esc_html( $d[0] );
			}
			echo implode( ', ', $bits ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="flxb-help"><b>filter</b> <code>paid</code>, <code>type_slugs</code>, <code>type_ids</code>, <code>status</code>, <code>currency</code>, <code>partner_ids</code>, <code>source_ids</code>, <code>tracked</code></p>';
		echo '<p class="flxb-help"><b>period</b> ' . esc_html( implode( ', ', array_keys( FLXB_Reports::periods() ) ) ) . ' · <b>date</b> created, done, target · <b>sort</b> "-amount" · <b>chart</b> "bar" · <b>limit</b></p>';
		echo '<p class="flxb-help">' . esc_html__( 'Titles can be one text or {"tr": "...", "en": "..."}.', 'flx-donate' ) . '</p></aside></div>';
		FLXB_Admin::footer();
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_report_save' );
		$json = (string) wp_unslash( $_POST['json'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, checked by FLXB_Reports::clean
		$old  = sanitize_key( wp_unslash( $_POST['old'] ?? '' ) );
		$res  = FLXB_Reports::save( $json, $old );
		if ( is_wp_error( $res ) ) {
			set_transient( 'flxb_report_draft_' . get_current_user_id(), $json, 600 );
			wp_safe_redirect( self::url( array( $old ? 'edit' : 'new' => $old ? $old : 1, 'flxb_msg' => 'error', 'flxb_err' => rawurlencode( $res->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'r' => $res, 'flxb_msg' => 'saved' ) ) );
		exit;
	}

	public static function delete() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_report_delete' );
		$id = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		FLXB_Reports::delete( $id );
		wp_safe_redirect( self::url( array( 'r' => $id, 'flxb_msg' => 'saved' ) ) );
		exit;
	}

	public static function csv() {
		if ( ! flxb_can_view() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_report_csv' );
		$r = FLXB_Reports::get( sanitize_key( FLXB_Admin::q( 'r' ) ) );
		if ( ! $r ) {
			wp_die( esc_html__( 'Report not found.', 'flx-donate' ) );
		}
		$res = FLXB_Reports::run( $r, array( 'from' => FLXB_Admin::q( 'from' ), 'to' => FLXB_Admin::q( 'to' ) ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'report-' . $r['id'] . ( $res['from'] ? '-' . $res['from'] : '' ) . ( $res['to'] ? '_' . $res['to'] : '' ) . '.csv' ) . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, wp_list_pluck( $res['cols'], 'label' ) );
		foreach ( $res['rows'] as $row ) {
			$line = array();
			foreach ( $res['cols'] as $k => $c ) {
				$line[] = $c['dim'] ? FLXB_Reports::show( $c['kind'], $row[ $k ] ) : $row[ $k ];
			}
			fputcsv( $out, $line );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
