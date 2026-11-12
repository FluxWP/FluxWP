<?php
/**
 * Flux Donate 2.11: Excel / CSV import screen (upload → preview and matching → run).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Import {

	const SLUG = 'flxb-import';

	public static function init() {
		add_action( 'admin_post_flxb_import_upload', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_flxb_import_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxb_import_delete', array( __CLASS__, 'delete' ) );
		add_action( 'wp_ajax_flxb_import_step', array( __CLASS__, 'ajax_step' ) );
	}

	private static function url( array $args = array() ) {
		return FLXB_Admin_Url::page( self::SLUG, $args );
	}

	private static function post_form( $action, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . $extra . '><input type="hidden" name="action" value="flxb_import_' . esc_attr( $action ) . '">' . wp_nonce_field( 'flxb_import_' . $action, '_wpnonce', true, false );
	}

	private static function select( $name, array $choices, $cur, $extra = '' ) {
		$h = '<select name="' . esc_attr( $name ) . '"' . $extra . '>';
		foreach ( $choices as $k => $l ) {
			$h .= '<option value="' . esc_attr( (string) $k ) . '"' . selected( (string) $cur, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	public static function render() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$imp = FLXB_Import::get( sanitize_key( FLXB_Admin::q( 'import' ) ) );
		if ( $imp && 'preview' === $imp['state'] ) {
			self::preview( $imp );
			return;
		}
		if ( $imp ) {
			self::run_view( $imp );
			return;
		}
		self::upload_view();
	}

	private static function upload_view() {
		FLXB_Admin::header( __( 'Import from Excel', 'flx-donate' ), __( 'Bring in older donations and records from an Excel (.xlsx) or CSV file. You see everything before anything is written; a row is never imported twice.', 'flx-donate' ) );
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Upload a file', 'flx-donate' ) . '</h2></div>';
		echo self::post_form( 'upload', ' enctype="multipart/form-data" class="flxb-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field"><label for="flxb-if">' . esc_html__( 'File', 'flx-donate' ) . '</label><input id="flxb-if" type="file" name="file" accept=".xlsx,.csv,.txt,.tsv" required><p class="flxb-help">' . esc_html__( 'The first row names the columns. Recognised: date, donor, phone, e-mail, on behalf of, donation type, option, quantity, amount, currency, payment, status, partner, brought in by, target date, referral, note.', 'flx-donate' ) . '</p><p class="flxb-help">' . esc_html__( 'Nothing is sent to donors or staff while importing (no e-mail, WhatsApp or certificate).', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-actions"><button class="flxb-btn flxb-btn--primary" type="submit" data-flxb-busy="' . esc_attr__( 'Reading...', 'flx-donate' ) . '">' . FLXB_Admin::icon( 'download' ) . esc_html__( 'Read the file', 'flx-donate' ) . '</button></div></form></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<aside class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Earlier imports', 'flx-donate' ) . '</h2></div>';
		$all = FLXB_Import::all();
		if ( ! $all ) {
			echo '<p class="flxb-muted">' . esc_html__( 'None yet.', 'flx-donate' ) . '</p>';
		} else {
			echo '<ul class="flxb-list">';
			$st = array( 'preview' => __( 'Waiting', 'flx-donate' ), 'running' => __( 'Running', 'flx-donate' ), 'done' => __( 'Finished', 'flx-donate' ) );
			foreach ( $all as $d ) {
				/* translators: 1: rows, 2: created */
				$sub = sprintf( __( '%1$s rows, %2$s written', 'flx-donate' ), number_format_i18n( count( $d['rows'] ) ), number_format_i18n( (int) $d['result']['created'] ) );
				echo '<li><a href="' . esc_url( self::url( array( 'import' => $d['id'] ) ) ) . '"><b>' . esc_html( $d['file'] ) . '</b></a> · ' . esc_html( $st[ $d['state'] ] ?? $d['state'] ) . '<span class="flxb-sub2">' . esc_html( flxb_date( $d['created_at'] ) . ' · ' . $sub ) . '</span></li>';
			}
			echo '</ul>';
		}
		echo '</aside></div>';
		FLXB_Admin::footer();
	}

	private static function preview( array $imp ) {
		$a      = FLXB_Import::analyze( $imp );
		$fields = FLXB_Import::fields();
		$s      = $a['stats'];
		FLXB_Admin::header( $imp['file'], __( 'Check the columns and the matches, then start.', 'flx-donate' ), array(), array( __( 'Import from Excel', 'flx-donate' ), self::url() ) );

		echo '<div class="flxb-kpis">';
		foreach ( array( array( __( 'Rows', 'flx-donate' ), $s['total'], '' ), array( __( 'Ready', 'flx-donate' ), $s['ok'], '#1F7A4D' ), array( __( 'With a warning', 'flx-donate' ), $s['warn'], '#B7791F' ), array( __( 'Already imported', 'flx-donate' ), $s['dup'], '#6B7C8F' ), array( __( 'Errors', 'flx-donate' ), $s['error'], '#B42318' ) ) as $k ) {
			echo '<div class="flxb-kpi"' . ( $k[2] ? ' style="--accent:' . esc_attr( $k[2] ) . '"' : '' ) . '><div class="flxb-kpi__label">' . esc_html( $k[0] ) . '</div><div class="flxb-kpi__value">' . esc_html( number_format_i18n( $k[1] ) ) . '</div></div>';
		}
		echo '</div>';

		/* 2.26.0: what will be written, checked before starting */
		$sum = $a['summary'];
		$cur = $sum['currency'];
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Summary', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Only ready rows are counted. After changing a match, press "Apply and check again" to update it.', 'flx-donate' ) . '</p></div></div>';
		echo '<div class="flxb-kpis">';
		foreach ( array( array( __( 'Records', 'flx-donate' ), number_format_i18n( $sum['rows'] ), '' ), array( __( 'Units', 'flx-donate' ), number_format_i18n( $sum['units'] ), '' ), array( __( 'Paid', 'flx-donate' ), flxb_money( $sum['paid'], $cur ) . ' · ' . number_format_i18n( $sum['paid_n'] ), '#1F7A4D' ), array( __( 'Waiting for payment', 'flx-donate' ), flxb_money( $sum['waiting'], $cur ) . ' · ' . number_format_i18n( $sum['waiting_n'] ), '#B7791F' ) ) as $k ) {
			echo '<div class="flxb-kpi"' . ( $k[2] ? ' style="--accent:' . esc_attr( $k[2] ) . '"' : '' ) . '><div class="flxb-kpi__label">' . esc_html( $k[0] ) . '</div><div class="flxb-kpi__value">' . esc_html( $k[1] ) . '</div></div>';
		}
		echo '</div>';
		if ( $sum['by_ref'] ) {
			echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th>' . esc_html__( 'Referral', 'flx-donate' ) . '</th><th class="num">' . esc_html__( 'Records', 'flx-donate' ) . '</th><th class="num">' . esc_html__( 'Units', 'flx-donate' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th></tr></thead><tbody>';
			uasort( $sum['by_ref'], function ( $x, $y ) { return $y['rows'] <=> $x['rows']; } );
			foreach ( $sum['by_ref'] as $uid => $x ) {
				$u = $uid ? get_userdata( $uid ) : null;
				echo '<tr><td class="flxb-col-main">' . esc_html( $u ? $u->display_name : __( '(none)', 'flx-donate' ) ) . '</td><td class="num" data-label="' . esc_attr__( 'Records', 'flx-donate' ) . '"><span>' . esc_html( number_format_i18n( $x['rows'] ) ) . '</span></td><td class="num" data-label="' . esc_attr__( 'Units', 'flx-donate' ) . '"><span>' . esc_html( number_format_i18n( $x['units'] ) ) . '</span></td><td class="num" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $x['amount'], $cur ) ) . '</span></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
		/* translators: 1: records, 2: units, 3: paid amount, 4: amount waiting for payment */
		$confirm = sprintf( __( '%1$s records (%2$s units) will be written. Paid: %3$s, waiting for payment: %4$s. Nothing is sent to anyone.', 'flx-donate' ), number_format_i18n( $sum['rows'] ), number_format_i18n( $sum['units'] ), flxb_money( $sum['paid'], $cur ), flxb_money( $sum['waiting'], $cur ) );

		echo self::post_form( 'save', ' class="flxb-form"' ) . '<input type="hidden" name="import" value="' . esc_attr( $imp['id'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		/* Columns */
		$fch = array( '' => '— ' . __( 'skip', 'flx-donate' ) . ' —' );
		foreach ( $fields as $f => $def ) {
			$fch[ $f ] = $def[0];
		}
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Columns', 'flx-donate' ) . '</h2></div><div class="flxb-fields-3">';
		foreach ( $imp['headers'] as $c => $h ) {
			if ( '' === trim( (string) $h ) && ! isset( $imp['map'][ $c ] ) ) {
				continue;
			}
			$ex = '';
			foreach ( array_slice( $imp['rows'], 0, 3 ) as $row ) {
				if ( '' !== trim( (string) ( $row[ $c ] ?? '' ) ) ) {
					$ex = (string) $row[ $c ];
					$mf = $imp['map'][ $c ] ?? '';
					if ( 'created' === $mf && '' !== FLXB_Import::datetime( $ex ) ) {
						$ex = flxb_date( get_gmt_from_date( FLXB_Import::datetime( $ex ) ) );
					} elseif ( 'target' === $mf && '' !== flxb_parse_date( $ex ) ) {
						$ex = flx_date( strtotime( flxb_parse_date( $ex ) . ' 12:00' ) );
					}
					break;
				}
			}
			echo '<div class="flxb-field"><label>' . esc_html( '' !== trim( (string) $h ) ? $h : '#' . ( $c + 1 ) ) . '</label>' . self::select( 'map[' . (int) $c . ']', $fch, $imp['map'][ $c ] ?? '' ) . ( '' !== $ex ? '<p class="flxb-help">' . esc_html( wp_trim_words( $ex, 8 ) ) . '</p>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></section>';

		/* Options */
		$types = FLXB_Import::type_choices();
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Options', 'flx-donate' ) . '</h2></div><div class="flxb-fields-2">';
		echo '<div class="flxb-field"><label>' . esc_html__( 'Donation type when the row has none', 'flx-donate' ) . '</label>' . self::select( 'opts[type]', array( '' => '—' ) + $types, $imp['opts']['type'] ?? '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field"><label>' . esc_html__( 'Payment when the row does not say', 'flx-donate' ) . '</label>' . self::select( 'opts[paid]', array( '1' => __( 'Paid', 'flx-donate' ), '0' => __( 'Waiting for payment', 'flx-donate' ) ), (string) (int) ! empty( $imp['opts']['paid'] ) ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		/* 2.26.0: rows without a date */
		$nd = (string) ( $imp['opts']['nodate'] ?? 'today' );
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label>' . esc_html__( 'Date when the row has none', 'flx-donate' ) . '</label>' . self::select( 'opts[nodate]', array( 'above' => __( 'Same as the row above', 'flx-donate' ), 'target' => __( 'The target date of the row', 'flx-donate' ), 'day' => __( 'A day I choose', 'flx-donate' ), 'today' => __( 'Today', 'flx-donate' ) ), $nd, ' data-flxb-nodate' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="flxb-field" data-flxb-nodate-day' . ( 'day' === $nd ? '' : ' hidden' ) . '><label for="flxb-nd-day">' . esc_html__( 'Day', 'flx-donate' ) . '</label><input id="flxb-nd-day" type="date" name="opts[nodate_day]" value="' . esc_attr( (string) ( $imp['opts']['nodate_day'] ?? '' ) ) . '"></div></div>';
		echo '<p class="flxb-help">' . esc_html__( 'Imported records are history: no e-mail, WhatsApp message, certificate or ad conversion is sent to anyone.', 'flx-donate' ) . '</p>';
		echo '<script>(function(){var s=document.querySelector("[data-flxb-nodate]"),d=document.querySelector("[data-flxb-nodate-day]");if(s&&d){s.addEventListener("change",function(){d.hidden=s.value!=="day";});}})();</script>';
		echo '<input type="hidden" name="opts[keep_date]" value="0"><label class="flxb-switch"><input type="checkbox" name="opts[keep_date]" value="1"' . checked( ! empty( $imp['opts']['keep_date'] ), true, false ) . '><span class="flxb-switch__ui" aria-hidden="true"></span>' . esc_html__( 'Keep the date in the file (otherwise today)', 'flx-donate' ) . '</label></section>';

		/* Matches */
		$users = array( '0' => '—' );
		foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ), 'number' => 500 ) ) as $u ) {
			$users[ (string) $u->ID ] = $u->display_name;
		}
		$partners = array( '0' => '—', 'new' => __( '+ Add as new', 'flx-donate' ) );
		foreach ( FLXB_Partners::all() as $pid => $p ) {
			$partners[ (string) $pid ] = $p['name'];
		}
		$sets = array(
			'type'    => array( __( 'Donation types', 'flx-donate' ), array( '' => '— ' . __( 'choose', 'flx-donate' ) . ' —' ) + $types ),
			'partner' => array( FLXB_Track::label( 'partner' ), $partners ),
			'source'  => array( __( 'Brought in by', 'flx-donate' ), $partners ),
			'status'  => array( __( 'Status', 'flx-donate' ), FLXB_Track::statuses() ),
			'ref'     => array( __( 'Referral', 'flx-donate' ), $users ),
			'paid'    => array( __( 'Payment', 'flx-donate' ), array( '1' => __( 'Paid', 'flx-donate' ), '0' => __( 'Waiting for payment', 'flx-donate' ) ) ),
		);
		$shown = 0;
		$html  = '';
		foreach ( $sets as $g => $def ) {
			$field = 'type' === $g ? array( 'type', 'option' ) : array( $g );
			if ( ! array_intersect( $field, $imp['map'] ) && 'type' !== $g ) {
				continue;
			}
			if ( 'type' === $g && ! array_intersect( $field, $imp['map'] ) ) {
				continue;
			}
			$html .= '<div class="flxb-match"><h3 class="flxb-h3">' . esc_html( $def[0] ) . '</h3><table class="flxb-table flxb-table--compact"><tbody>';
			foreach ( $a['groups'][ $g ] as $k => $x ) {
				$html .= '<tr><td>' . esc_html( '' !== $x['raw'] ? $x['raw'] : __( '(empty)', 'flx-donate' ) ) . ' <span class="flxb-muted">×' . (int) $x['n'] . '</span></td><td>' . self::select( 'res[' . $g . '][k_' . $k . ']', $def[1], $x['to'] ) . '</td></tr>';
			}
			$html .= '</tbody></table></div>';
			++$shown;
		}
		if ( $shown ) {
			echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Matches', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Each different value in the file is matched once.', 'flx-donate' ) . '</p></div></div><div class="flxb-matches">' . $html . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="flxb-actions flxb-actions--sticky"><button class="flxb-btn" type="submit" name="do" value="apply">' . esc_html__( 'Apply and check again', 'flx-donate' ) . '</button> ';
		/* translators: %s: number of rows */
		echo '<button class="flxb-btn flxb-btn--primary" type="submit" name="do" value="run" data-flxb-run data-flx-confirm="' . esc_attr( $confirm ) . '" data-flx-confirm-title="' . esc_attr__( 'Start the import?', 'flx-donate' ) . '" data-flx-confirm-ok="' . esc_attr__( 'Import', 'flx-donate' ) . '"' . ( $s['ok'] ? '' : ' disabled' ) . '>' . esc_html( sprintf( _n( 'Import %s row', 'Import %s rows', $s['ok'], 'flx-donate' ), number_format_i18n( $s['ok'] ) ) ) . '</button><span class="flxb-help" data-flxb-stale hidden>' . esc_html__( 'You changed something: press "Apply and check again" first.', 'flx-donate' ) . '</span></div></form>';
		echo '<script>(function(){var f=document.querySelector("[data-flxb-run]");if(!f){return;}var m=f.form,h=m.querySelector("[data-flxb-stale]");m.addEventListener("change",function(){f.disabled=true;if(h){h.hidden=false;}});})();</script>';

		/* Rows */
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Rows', 'flx-donate' ) . '</h2></div><div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th>#</th><th>' . esc_html__( 'Donor', 'flx-donate' ) . '</th><th>' . esc_html__( 'Date', 'flx-donate' ) . '</th><th>' . esc_html__( 'Donation type', 'flx-donate' ) . '</th><th class="num">' . esc_html__( 'Quantity', 'flx-donate' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th><th>' . esc_html__( 'Status', 'flx-donate' ) . '</th><th>' . esc_html__( 'Check', 'flx-donate' ) . '</th></tr></thead><tbody>';
		$sts = FLXB_Track::statuses();
		$problems = array_filter(
			$a['rows'],
			function ( $r ) {
				return $r['errors'] || $r['warns'] || ! empty( $r['dup'] );
			}
		);
		$list = array_slice( $problems + $a['rows'], 0, 200, true );
		ksort( $list );
		foreach ( $list as $r ) {
			$t     = $r['type_id'] ? FLXB_Types::get( $r['type_id'] ) : null;
			$check = ! empty( $r['dup'] ) ? '<span class="flxb-pill flxb-pill--muted">' . esc_html__( 'Already imported', 'flx-donate' ) . '</span>' : '';
			foreach ( $r['errors'] as $e ) {
				$check .= '<span class="flxb-pill flxb-pill--failed">' . esc_html( $e ) . '</span> ';
			}
			foreach ( $r['warns'] as $w ) {
				$check .= '<span class="flxb-pill flxb-pill--pending">' . esc_html( $w ) . '</span> ';
			}
			if ( '' === $check ) {
				$check = '<span class="flxb-pill flxb-pill--ok">' . esc_html__( 'Ready', 'flx-donate' ) . '</span>';
			}
			$who = '' !== $r['donor'] ? $r['donor'] : $r['name'];
			echo '<tr><td>' . (int) $r['n'] . '</td><td class="flxb-col-main">' . esc_html( $who ) . ( '' !== $r['name'] && $r['name'] !== $who ? '<span class="flxb-sub2">' . esc_html( $r['name'] ) . '</span>' : '' ) . '</td><td data-label="' . esc_attr__( 'Date', 'flx-donate' ) . '"><span' . ( empty( $r['dated'] ) ? ' class="flxb-muted"' : '' ) . '>' . esc_html( ! empty( $imp['opts']['keep_date'] ) && '' !== $r['created'] ? flxb_date( get_gmt_from_date( $r['created'] ) ) : __( 'Today', 'flx-donate' ) ) . '</span></td><td data-label="' . esc_attr__( 'Donation type', 'flx-donate' ) . '"><span>' . esc_html( $t ? $t['title'] : '—' ) . '</span></td><td class="num" data-label="' . esc_attr__( 'Quantity', 'flx-donate' ) . '"><span>' . (int) $r['qty'] . '</span></td><td class="num" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( null === $r['amount'] ? '—' : flxb_money( $r['amount'], '' !== $r['currency'] ? $r['currency'] : ( $t ? $t['currency'] : '' ) ) ) . '</span></td><td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . esc_html( $t && ! FLXB_Types::tracks_items( $t ) ? ( $r['paid'] ? __( 'Paid', 'flx-donate' ) : __( 'Waiting for payment', 'flx-donate' ) ) : ( $sts[ $r['status'] ] ?? $r['status'] ) ) . '</span></td><td data-label="' . esc_attr__( 'Check', 'flx-donate' ) . '"><span>' . $check . '</span></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></div>';
		if ( count( $a['rows'] ) > count( $list ) ) {
			/* translators: %s: number of rows */
			echo '<p class="flxb-muted">' . esc_html( sprintf( __( 'Showing rows with a problem first; %s rows in all.', 'flx-donate' ), number_format_i18n( count( $a['rows'] ) ) ) ) . '</p>';
		}
		echo '</section>';
		echo self::post_form( 'delete', ' style="margin-top:12px"' ) . '<input type="hidden" name="import" value="' . esc_attr( $imp['id'] ) . '"><button class="flxb-btn flxb-btn--danger" type="submit">' . esc_html__( 'Discard this file', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		FLXB_Admin::footer();
	}

	private static function run_view( array $imp ) {
		$total = count( $imp['rows'] );
		$pct   = $total ? (int) floor( 100 * $imp['pos'] / $total ) : 100;
		$r     = $imp['result'];
		FLXB_Admin::header( $imp['file'], 'done' === $imp['state'] ? __( 'Finished.', 'flx-donate' ) : __( 'Importing... Keep this page open; if it closes, opening it again continues where it stopped.', 'flx-donate' ), array(), array( __( 'Import from Excel', 'flx-donate' ), self::url() ) );
		echo '<section class="flxb-card" id="flxb-import-run" data-id="' . esc_attr( $imp['id'] ) . '" data-state="' . esc_attr( $imp['state'] ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'flxb_import_step' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
		echo '<div class="flxb-progress"><span style="width:' . (int) $pct . '%"></span></div>';
		/* translators: 1: rows done, 2: rows in all */
		echo '<p data-flxb-progress>' . esc_html( sprintf( __( '%1$s / %2$s rows', 'flx-donate' ), number_format_i18n( $imp['pos'] ), number_format_i18n( $total ) ) ) . '</p>';
		echo '<div class="flxb-kpis">';
		foreach ( array( array( __( 'Written', 'flx-donate' ), $r['created'], '#1F7A4D' ), array( __( 'Skipped (already imported)', 'flx-donate' ), $r['skipped'], '#6B7C8F' ), array( __( 'Errors', 'flx-donate' ), $r['failed'], '#B42318' ) ) as $k ) {
			echo '<div class="flxb-kpi" style="--accent:' . esc_attr( $k[2] ) . '"><div class="flxb-kpi__label">' . esc_html( $k[0] ) . '</div><div class="flxb-kpi__value">' . esc_html( number_format_i18n( $k[1] ) ) . '</div></div>';
		}
		echo '</div>';
		if ( 'done' === $imp['state'] ) {
			echo '<div class="flxb-actions"><a class="flxb-btn flxb-btn--primary" href="' . esc_url( FLXB_Admin_Url::page( flxb_track_enabled() ? 'flxb-track' : 'flxb-donations' ) ) . '">' . esc_html( flxb_track_enabled() ? FLXB_Track::label( 'menu' ) : __( 'Donations', 'flx-donate' ) ) . '</a> <a class="flxb-btn" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Another file', 'flx-donate' ) . '</a></div>';
		}
		if ( $r['errors'] ) {
			echo '<h3 class="flxb-h3">' . esc_html__( 'Errors', 'flx-donate' ) . '</h3><div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th>#</th><th>' . esc_html__( 'Name', 'flx-donate' ) . '</th><th>' . esc_html__( 'Reason', 'flx-donate' ) . '</th></tr></thead><tbody>';
			foreach ( $r['errors'] as $e ) {
				echo '<tr><td>' . (int) $e['n'] . '</td><td>' . esc_html( $e['name'] ) . '</td><td>' . esc_html( $e['msg'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
		if ( 'running' === $imp['state'] ) {
			?>
<script>
(function () {
	var box = document.getElementById('flxb-import-run'), fail = 0;
	function step() {
		var body = new FormData();
		body.append('action', 'flxb_import_step');
		body.append('import', box.dataset.id);
		body.append('_wpnonce', box.dataset.nonce);
		fetch(box.dataset.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || !j.success) { throw new Error('step'); }
				fail = 0;
				var d = j.data, k = box.querySelectorAll('.flxb-kpi__value');
				box.querySelector('.flxb-progress span').style.width = Math.floor(100 * d.pos / Math.max(1, d.total)) + '%';
				box.querySelector('[data-flxb-progress]').textContent = d.pos + ' / ' + d.total;
				k[0].textContent = d.result.created; k[1].textContent = d.result.skipped; k[2].textContent = d.result.failed;
				if (d.done) { location.reload(); } else { step(); }
			})
			.catch(function () { if (++fail < 4) { setTimeout(step, 3000 * fail); } else { location.reload(); } });
	}
	step();
})();
</script>
			<?php
		}
		FLXB_Admin::footer();
	}

	public static function upload() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_import_upload' );
		$f = $_FILES['file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => __( 'Choose a file.', 'flx-donate' ) ) );
		}
		$name = sanitize_file_name( wp_unslash( (string) $f['name'] ) );
		if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'xlsx', 'csv', 'txt', 'tsv' ), true ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => __( 'Only .xlsx or .csv files.', 'flx-donate' ) ) );
		}
		$imp = FLXB_Import::from_file( $f['tmp_name'], $name );
		if ( is_wp_error( $imp ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => $imp->get_error_message() ) );
		}
		FLXB_Admin::redirect( self::SLUG, array( 'import' => $imp['id'] ) );
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_import_save' );
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is cleaned below
		$imp = FLXB_Import::get( sanitize_key( wp_unslash( $_POST['import'] ?? '' ) ) );
		if ( ! $imp || 'preview' !== $imp['state'] ) {
			FLXB_Admin::redirect( self::SLUG );
		}
		$fields = FLXB_Import::fields();
		$map    = array();
		foreach ( (array) wp_unslash( $_POST['map'] ?? array() ) as $c => $f ) {
			$f = sanitize_key( $f );
			if ( $f && isset( $fields[ $f ] ) && ! in_array( $f, $map, true ) ) {
				$map[ (int) $c ] = $f;
			}
		}
		$changed    = $map !== $imp['map'];
		$imp['map'] = $map;
		$res        = array();
		if ( ! $changed ) { // new columns make new values: earlier matches would point at the wrong ones
			foreach ( (array) wp_unslash( $_POST['res'] ?? array() ) as $g => $vals ) {
				if ( ! in_array( $g, array( 'type', 'partner', 'source', 'status', 'ref', 'paid' ), true ) ) {
					continue;
				}
				foreach ( (array) $vals as $k => $v ) {
					$res[ $g ][ substr( (string) $k, 2 ) ] = 'type' === $g ? preg_replace( '/[^a-z0-9|_-]/', '', (string) $v ) : sanitize_key( (string) $v );
				}
			}
		}
		$imp['res'] = $res;
		$o          = (array) wp_unslash( $_POST['opts'] ?? array() );
		// phpcs:enable
		$imp['opts'] = array(
			'type'      => preg_replace( '/[^a-z0-9|_-]/', '', (string) ( $o['type'] ?? '' ) ),
			'paid'      => empty( $o['paid'] ) ? 0 : 1,
			'keep_date' => empty( $o['keep_date'] ) ? 0 : 1,
			'nodate'    => in_array( (string) ( $o['nodate'] ?? '' ), array( 'above', 'target', 'day', 'today' ), true ) ? (string) $o['nodate'] : 'today',
			'nodate_day' => flxb_parse_date( sanitize_text_field( (string) ( $o['nodate_day'] ?? '' ) ) ),
		);
		if ( 'run' === ( $_POST['do'] ?? '' ) && ! $changed ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			FLXB_Import::prepare( $imp );
		} else {
			FLXB_Import::save( $imp );
		}
		FLXB_Admin::redirect( self::SLUG, array( 'import' => $imp['id'] ) );
	}

	public static function delete() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_import_delete' );
		FLXB_Import::delete( sanitize_key( wp_unslash( $_POST['import'] ?? '' ) ) );
		FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'deleted' ) );
	}

	public static function ajax_step() {
		check_ajax_referer( 'flxb_import_step' );
		if ( ! flxb_can_manage() ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to access this page.', 'flx-donate' ), 403 );
		}
		$r = FLXB_Import::step( sanitize_key( wp_unslash( $_POST['import'] ?? '' ) ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}
}
