<?php
/**
 * Flux Donate: tracking page (tracking module). List of tracked items with their payment, partner,
 * target date and files; an item's page shows the whole flow and lets staff send it to a partner or
 * take it back, upload files and send them to the owner. The words come from FLXB_Track::labels().
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Track {

	const SLUG = 'flxb-track';

	public static function init() {
		foreach ( array( 'bulk', 'step', 'edit', 'file_upload', 'file_send', 'file_delete', 'note' ) as $a ) {
			add_action( 'admin_post_flxb_track_' . $a, array( __CLASS__, $a ) );
		}
		/* Links from before 2.11 (page=flxb-kurban). */
		add_action(
			'admin_init',
			function () {
				if ( 'flxb-kurban' === FLXB_Admin::q( 'page' ) ) {
					wp_safe_redirect( FLXB_Admin_Url::page( self::SLUG, array_filter( array( 'item' => absint( FLXB_Admin::q( 'item' ) ) ) ) ) );
					exit;
				}
			}
		);
	}

	/** Address of the list, or of one item. */
	public static function url( $item_id = 0 ) {
		return FLXB_Admin_Url::page( self::SLUG, $item_id ? array( 'item' => (int) $item_id ) : array() );
	}

	private static function steps( $tid = 0 ) {
		$l = FLXB_Track::labels( $tid );
		return array(
			'approve'   => __( 'Approve', 'flx-donate' ),
			'assign'    => $l['do_assign'],
			'revoke'    => $l['do_revoke'],
			'done'      => $l['do_done'],
			'delivered' => __( 'Mark delivered to owner', 'flx-donate' ),
			'cancel'    => __( 'Cancel', 'flx-donate' ),
			'reset'     => __( 'Back to waiting', 'flx-donate' ),
		);
	}

	/** Stored numbers may be digits only; show them as +90 5xx… */
	private static function phone( $p ) {
		$p = (string) $p;
		return flxb_format_phone( ctype_digit( $p ) ? '+' . $p : $p );
	}

	private static function place_select( $name, $cur = '', $empty = '', $tid = 0 ) {
		$h = '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( '' !== $empty ? $empty : '—' ) . '</option>';
		foreach ( FLXB_Track::places( $tid ) as $ref => $label ) {
			$h .= '<option value="' . esc_attr( $ref ) . '"' . selected( (string) $cur, (string) $ref, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function result_msg( array $r ) {
		if ( ! empty( $r['error'] ) ) {
			return array( 'error', $r['error'] );
		}
		/* translators: %d: number of items */
		$msg = sprintf( _n( '%d item updated.', '%d items updated.', count( $r['done'] ), 'flx-donate' ), count( $r['done'] ) );
		if ( $r['skipped'] ) {
			$why = array();
			foreach ( $r['skipped'] as $id => $reason ) {
				$it    = FLXB_Track::get( $id );
				$why[] = ( $it ? $it['code'] : '#' . $id ) . ': ' . $reason;
			}
			/* translators: %s: list of codes with the reason */
			$msg .= ' ' . sprintf( __( 'Skipped: %s', 'flx-donate' ), implode( ', ', array_slice( $why, 0, 8 ) ) . ( count( $why ) > 8 ? '…' : '' ) );
		}
		return array( $r['skipped'] ? 'warn' : 'saved', $msg );
	}

	/** Target date cell: day + where it stands (in the window, late). */
	public static function target_html( array $it ) {
		if ( empty( $it['target_date'] ) ) {
			return '—';
		}
		$st  = FLXB_Track::target_state( $it );
		$map = array(
			'window' => array( 'pending', __( 'due', 'flx-donate' ) ),
			'late'   => array( 'failed', __( 'late', 'flx-donate' ) ),
		);
		$h = esc_html( date_i18n( 'j M Y', strtotime( (string) $it['target_date'] ) ) );
		if ( isset( $map[ $st ] ) ) {
			$h .= ' <span class="flxb-pill flxb-pill--' . esc_attr( $map[ $st ][0] ) . '">' . esc_html( $map[ $st ][1] ) . '</span>';
		}
		return $h;
	}

	public static function render() {
		if ( ! FLXB_Track::can_view() ) {
			FLXB_Admin::deny();
		}
		$item = absint( FLXB_Admin::q( 'item' ) );
		if ( $item ) {
			self::detail( $item );
			return;
		}
		$f = array(
			'status'  => sanitize_key( FLXB_Admin::q( 'status' ) ),
			'place'   => sanitize_text_field( FLXB_Admin::q( 'place' ) ),
			'type_id' => absint( FLXB_Admin::q( 'type' ) ),
			'paid'    => FLXB_Admin::q( 'paid' ),
			'due'     => FLXB_Admin::q( 'due' ) ? 1 : 0,
			'q'       => sanitize_text_field( FLXB_Admin::q( 's' ) ),
		);
		$tid    = (int) $f['type_id'];
		$l      = FLXB_Track::labels( $tid );
		$page   = max( 1, absint( FLXB_Admin::q( 'paged', '1' ) ) );
		$res    = FLXB_Track::query( $f + array( 'per_page' => 50, 'page' => $page, 'order' => $f['due'] ? 'target' : '' ) );
		$counts = FLXB_Track::counts( $f );
		$args   = array_filter( array( 'page' => self::SLUG, 'status' => $f['status'], 'place' => $f['place'], 'type' => $f['type_id'] ? (string) $f['type_id'] : '', 'paid' => $f['paid'], 'due' => $f['due'] ? '1' : '', 's' => $f['q'] ), 'strlen' );

		$acts = array();
		if ( FLXB_Refs::can_create() ) {
			$acts[] = array( 'label' => __( 'New record', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-add' ), 'primary' => true, 'icon' => 'plus' );
		}
		$acts = (array) apply_filters( 'flxb_kurban_page_actions', $acts ); // before 2.11
		FLXB_Admin::header( $l['title'], __( 'Every item with its code, payment, partner, target date and files. Open one to see its whole flow.', 'flx-donate' ), (array) apply_filters( 'flxb_track_page_actions', $acts ) );

		echo '<div class="flxb-seg flxb-kseg">';
		$all = array( '' => __( 'All', 'flx-donate' ) ) + FLXB_Track::statuses( $tid );
		foreach ( $all as $k => $label ) {
			$n = '' === $k ? array_sum( $counts ) : (int) $counts[ $k ];
			echo '<a class="' . ( $k === $f['status'] ? 'is-on' : '' ) . '" href="' . esc_url( FLXB_Admin_Url::page( self::SLUG, array_filter( array( 'status' => $k ) + array_diff_key( $args, array( 'page' => 1, 'status' => 1 ) ), 'strlen' ) ) ) . '">' . esc_html( $label ) . ' <b>' . esc_html( number_format_i18n( $n ) ) . '</b></a>';
		}
		echo '</div>';

		$lib = FLXB_Admin::list_lib();
		echo '<form class="flxb-filters' . ( $lib ? ' flxb-filters--lib' : '' ) . '" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"' . ( $lib ? FLXB_Admin::list_attrs( self::SLUG ) : '' ) . '><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== $f['status'] ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $f['status'] ) . '">';
		}
		echo '<input type="search" name="s" value="' . esc_attr( $f['q'] ) . '" placeholder="' . esc_attr__( 'Code, name, phone, donation no…', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'Search', 'flx-donate' ) . '">';
		$types = FLXB_Track::tracked_types();
		if ( count( $types ) > 1 ) {
			echo '<label><span class="screen-reader-text">' . esc_html__( 'Donation type', 'flx-donate' ) . '</span><select name="type"><option value="">' . esc_html__( 'All types', 'flx-donate' ) . '</option>';
			foreach ( $types as $t ) {
				echo '<option value="' . (int) $t['id'] . '"' . selected( $tid, (int) $t['id'], false ) . '>' . esc_html( $t['title'] ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<label><span class="screen-reader-text">' . esc_html( $l['partner'] ) . '</span>' . self::place_select( 'place', $f['place'], '— ' . $l['partner'] . ' —', $tid ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label><span class="screen-reader-text">' . esc_html__( 'Payment', 'flx-donate' ) . '</span><select name="paid"><option value="">' . esc_html__( 'Any payment', 'flx-donate' ) . '</option><option value="1"' . selected( $f['paid'], '1', false ) . '>' . esc_html__( 'Paid', 'flx-donate' ) . '</option><option value="0"' . selected( $f['paid'], '0', false ) . '>' . esc_html__( 'Not paid', 'flx-donate' ) . '</option></select></label>';
		echo '<label class="flxb-check"><input type="checkbox" name="due" value="1"' . checked( $f['due'], 1, false ) . '> ' . esc_html__( 'Due now', 'flx-donate' ) . '</label>';
		echo '<div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . FLXB_Admin::icon( 'search' ) . esc_html__( 'Search', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div class="flxb-panel">';
		if ( ! $res['items'] ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'heart', 32 ) . '<h2>' . esc_html__( 'Nothing to track yet', 'flx-donate' ) . '</h2><p>' . esc_html__( 'An item is created for every unit of a donation type that is tracked one by one (Donation types › Tracking).', 'flx-donate' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			FLXB_Admin::footer();
			return;
		}
		$can = FLXB_Track::can();
		if ( $can ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="flxb-kbulk"><input type="hidden" name="action" value="flxb_track_bulk"><input type="hidden" name="back" value="' . esc_attr( wp_json_encode( $args ) ) . '">';
			wp_nonce_field( 'flxb_track_bulk' );
			echo '<div class="flxb-kbulk"><label><span>' . esc_html__( 'Selected', 'flx-donate' ) . '</span><select name="step" data-flxb-kstep>';
			foreach ( self::steps( $tid ) as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select></label><label data-flxb-kplace hidden><span>' . esc_html( $l['partner'] ) . '</span>' . self::place_select( 'place', '', '', $tid ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<label class="flxb-kbulk__note"><span>' . esc_html__( 'Note', 'flx-donate' ) . '</span><input type="text" name="note" maxlength="300"></label><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Apply', 'flx-donate' ) . '</button></div>';
		}
		echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr>' . ( $can ? '<th scope="col" class="flxb-col-cb"><input type="checkbox" data-flxb-kall aria-label="' . esc_attr__( 'Select all', 'flx-donate' ) . '"></th>' : '' ) . '<th scope="col">' . esc_html__( 'Code', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Donation', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Payment', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Target date', 'flx-donate' ) . '</th><th scope="col">' . esc_html( $l['partner'] ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col">' . esc_html( $l['files'] ) . '</th>';
		$cols = (array) apply_filters( 'flxb_kurban_columns', array() ); // before 2.11
		$cols = (array) apply_filters( 'flxb_track_columns', $cols ); // key => label, cells from `flxb_track_cell`
		foreach ( $cols as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $res['items'] as $it ) {
			$url = self::url( (int) $it['id'] );
			$fil = FLXB_Track::files( $it );
			$snt = 0;
			foreach ( $fil as $v ) {
				$snt += count( (array) ( $v['sent'] ?? array() ) ) ? 1 : 0;
			}
			echo '<tr>' . ( $can ? '<td class="flxb-col-cb"><input type="checkbox" name="ids[]" value="' . (int) $it['id'] . '" form="flxb-kbulk" aria-label="' . esc_attr( $it['code'] ) . '"></td>' : '' );
			echo '<td class="flxb-col-main"><a class="flxb-mono" href="' . esc_url( $url ) . '">' . esc_html( $it['code'] ) . '</a><span class="flxb-sub2">' . esc_html( $it['option_label'] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'On behalf of', 'flx-donate' ) . '"><span>' . esc_html( '' !== (string) $it['beneficiary_name'] ? $it['beneficiary_name'] : '—' ) . ( '' !== (string) $it['beneficiary_phone'] ? '<span class="flxb-sub2">' . esc_html( flxb_format_phone( $it['beneficiary_phone'] ) ) . '</span>' : '' ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Donation', 'flx-donate' ) . '"><span><a href="' . esc_url( FLXB_Admin_Url::donation( (int) $it['donation_id'] ) ) . '">#' . (int) $it['donation_id'] . '</a> ' . esc_html( (string) $it['donor_name'] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Payment', 'flx-donate' ) . '"><span>' . ( $it['pay_status'] ? FLXB_Admin::pill( $it['pay_status'] ) : '—' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td data-label="' . esc_attr__( 'Target date', 'flx-donate' ) . '"><span>' . self::target_html( $it ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td data-label="' . esc_attr( $l['partner'] ) . '"><span>' . esc_html( '' !== (string) $it['place_name'] ? $it['place_name'] : '—' ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . FLXB_Track::status_pill( $it['status'], (int) $it['type_id'] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			/* translators: 1: files, 2: sent */
			echo '<td data-label="' . esc_attr( $l['files'] ) . '"><span>' . ( $fil ? esc_html( sprintf( __( '%1$d · %2$d sent', 'flx-donate' ), count( $fil ), $snt ) ) : '—' ) . '</span></td>';
			foreach ( $cols as $key => $label ) {
				$cell = (string) apply_filters( 'flxb_kurban_cell', '', $key, $it ); // before 2.11
				echo '<td data-label="' . esc_attr( $label ) . '"><span>' . wp_kses_post( (string) apply_filters( 'flxb_track_cell', $cell, $key, $it ) ) . '</span></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		if ( $can ) {
			echo '</form>';
		}
		echo '<div class="flxb-tablefoot"><span>';
		/* translators: %s: number of items */
		echo esc_html( sprintf( _n( '%s item', '%s items', $res['total'], 'flx-donate' ), number_format_i18n( $res['total'] ) ) );
		echo '</span>' . FLXB_Admin::list_pagination( $res['total'], 50, $page, $args ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		FLXB_Admin::footer();
	}

	/** Hidden fields + nonce of a form on the item page. */
	private static function form_open( $action, $id, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-kform"' . $extra . '><input type="hidden" name="action" value="flxb_track_' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $id . '">' . wp_nonce_field( 'flxb_track_' . $action . '_' . (int) $id, '_wpnonce', true, false );
	}

	/** Preview of a kept file: player for video/audio, picture for images, link otherwise. */
	private static function file_preview( array $v ) {
		$url  = (string) wp_get_attachment_url( (int) $v['att'] );
		$mime = (string) ( $v['mime'] ?? '' );
		if ( 0 === strpos( $mime, 'video/' ) ) {
			return '<video controls preload="metadata" src="' . esc_url( $url ) . '"></video>';
		}
		if ( 0 === strpos( $mime, 'image/' ) ) {
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $url ) . '" alt="" loading="lazy"></a>';
		}
		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return '<audio controls preload="metadata" src="' . esc_url( $url ) . '"></audio>';
		}
		return '<a class="flxb-kfile" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . FLXB_Admin::icon( 'file' ) . esc_html( basename( (string) get_attached_file( (int) $v['att'] ) ) ) . '</a>';
	}

	private static function detail( $id ) {
		$it = FLXB_Track::get( $id );
		if ( ! $it || '' === (string) $it['code'] ) {
			FLXB_Admin::header( FLXB_Track::label( 'title' ) );
			echo '<div class="flxb-notice flxb-notice--error">' . esc_html__( 'Item not found.', 'flx-donate' ) . '</div>';
			FLXB_Admin::footer();
			return;
		}
		$tid   = (int) $it['type_id'];
		$l     = FLXB_Track::labels( $tid );
		$d     = FLXB_Donations::get( (int) $it['donation_id'] );
		$donor = $d && (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$can   = FLXB_Track::can();
		FLXB_Admin::header( $l['item'] . ' ' . $it['code'], (string) $it['option_label'], array(), array( $l['title'], self::url() ) );

		/* Flow strip */
		$order = array( 'pending', 'approved', 'sent', 'done', 'delivered' );
		$at    = array_search( $it['status'], $order, true );
		$sts   = FLXB_Track::statuses( $tid );
		echo '<ol class="flxb-kflow' . ( 'canceled' === $it['status'] ? ' is-canceled' : '' ) . '">';
		$when = array( 'pending' => $it['created_at'], 'approved' => $it['approved_at'], 'sent' => $it['sent_at'], 'done' => $it['done_at'], 'delivered' => $it['notified_at'] );
		foreach ( $order as $i => $s ) {
			$cls = false !== $at && $i < $at ? 'is-done' : ( false !== $at && $i === $at ? 'is-on' : '' );
			echo '<li class="' . esc_attr( $cls ) . '"><b>' . esc_html( $sts[ $s ] ) . '</b><span>' . esc_html( false !== $at && $i <= $at && $when[ $s ] ? flxb_date( $when[ $s ] ) : '' ) . '</span></li>';
		}
		echo '</ol>';
		if ( 'canceled' === $it['status'] ) {
			echo '<div class="flxb-notice flxb-notice--warn">' . esc_html__( 'This item is canceled.', 'flx-donate' ) . '</div>';
		}
		if ( 'late' === FLXB_Track::target_state( $it ) ) {
			echo '<div class="flxb-notice flxb-notice--warn">' . esc_html__( 'The target date has passed and it is not completed yet.', 'flx-donate' ) . '</div>';
		}

		echo '<div class="flxb-grid flxb-grid--1-1">';
		/* Item */
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html( $l['item'] ) . '</h2>' . FLXB_Track::status_pill( $it['status'], $tid ) . '</div><dl class="flxb-dl">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<dt>' . esc_html__( 'Code', 'flx-donate' ) . '</dt><dd class="flxb-mono">' . esc_html( $it['code'] ) . '</dd>';
		echo '<dt>' . esc_html__( 'Option', 'flx-donate' ) . '</dt><dd>' . esc_html( $it['option_label'] ) . ' · ' . esc_html( flxb_money( $it['unit_price'], $it['currency'] ) ) . '</dd>';
		if ( '' !== (string) $it['intention'] ) {
			$ints = FLXB_Types::intentions();
			echo '<dt>' . esc_html__( 'Intention', 'flx-donate' ) . '</dt><dd>' . esc_html( is_array( $ints[ $it['intention'] ] ?? null ) ? ( $ints[ $it['intention'] ]['label'] ?? $it['intention'] ) : ( $ints[ $it['intention'] ] ?? $it['intention'] ) ) . '</dd>';
		}
		echo '<dt>' . esc_html__( 'Target date', 'flx-donate' ) . '</dt><dd>' . self::target_html( $it ) . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<dt>' . esc_html( $l['partner'] ) . '</dt><dd>' . esc_html( '' !== (string) $it['place_name'] ? $it['place_name'] : '—' );
		if ( (float) $it['cost'] > 0 && flxb_can_view() ) {
			echo ' · ' . esc_html( flxb_money( $it['cost'], $it['cost_currency'] ) );
		}
		echo '</dd>';
		if ( (int) $it['source_id'] ) {
			echo '<dt>' . esc_html__( 'Came through', 'flx-donate' ) . '</dt><dd>' . esc_html( FLXB_Partners::name( (int) $it['source_id'] ) ) . '</dd>';
		}
		$rows = (array) apply_filters( 'flxb_kurban_detail_rows', array(), $it ); // before 2.11
		foreach ( (array) apply_filters( 'flxb_track_detail_rows', $rows, $it ) as $label => $html ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . wp_kses_post( (string) $html ) . '</dd>';
		}
		echo '</dl>';
		if ( $can ) {
			echo self::form_open( 'edit', $id ) . '<div class="flxb-grid flxb-grid--1-1"><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</span><input type="text" name="name" maxlength="190" value="' . esc_attr( $it['beneficiary_name'] ) . '"></label><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Owner phone', 'flx-donate' ) . '</span><input type="tel" name="phone" maxlength="32" value="' . esc_attr( $it['beneficiary_phone'] ) . '" placeholder="' . esc_attr__( 'Empty = donor phone', 'flx-donate' ) . '"></label></div><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Target date', 'flx-donate' ) . '</span><input type="date" name="target_date" value="' . esc_attr( (string) $it['target_date'] ) . '"></label><div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<dl class="flxb-dl"><dt>' . esc_html__( 'On behalf of', 'flx-donate' ) . '</dt><dd>' . esc_html( $it['beneficiary_name'] ) . '</dd></dl>';
		}
		echo '</section>';

		/* Donation & payment */
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Donation and payment', 'flx-donate' ) . '</h2>' . ( $d ? FLXB_Admin::pill( $d['status'] ) : '' ) . '</div><dl class="flxb-dl">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $d ) {
			echo '<dt>' . esc_html__( 'Donation', 'flx-donate' ) . '</dt><dd><a href="' . esc_url( FLXB_Admin_Url::donation( (int) $d['id'] ) ) . '">#' . (int) $d['id'] . '</a> · ' . esc_html( flxb_money( $d['amount'], $d['currency'] ) ) . ' · ' . esc_html( flxb_date( $d['created_at'] ) ) . '</dd>';
			echo '<dt>' . esc_html__( 'Donor', 'flx-donate' ) . '</dt><dd>' . esc_html( $donor ? FLXB_Admin::donor_name( $donor, '' ) : '—' ) . ( $donor && '' !== (string) $donor['phone'] ? ' · ' . esc_html( flxb_format_phone( $donor['phone'] ) ) : '' ) . '</dd>';
			if ( (int) $d['ref_user_id'] ) {
				$ru = get_userdata( (int) $d['ref_user_id'] );
				echo '<dt>' . esc_html__( 'Referral', 'flx-donate' ) . '</dt><dd>' . esc_html( $ru ? $ru->display_name : '#' . (int) $d['ref_user_id'] ) . '</dd>';
			}
			$order_obj = (int) $d['wc_order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $d['wc_order_id'] ) : null;
			if ( $order_obj ) {
				echo '<dt>' . esc_html__( 'Payment method', 'flx-donate' ) . '</dt><dd>' . esc_html( $order_obj->get_payment_method_title() ) . '</dd>';
				if ( class_exists( 'FLXB_WC_Receipts' ) ) {
					$rec = FLXB_WC_Receipts::of( $order_obj );
					echo '<dt>' . esc_html__( 'Receipt', 'flx-donate' ) . '</dt><dd>';
					if ( $rec ) {
						foreach ( $rec as $i => $r ) {
							echo '<a href="' . esc_url( FLXB_WC_Receipts::url( $order_obj, $i, true ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) $r['name'] ) . '</a><br>';
						}
					} else {
						echo '<span class="flxb-muted">' . esc_html__( 'No receipt uploaded yet.', 'flx-donate' ) . '</span>';
					}
					echo '</dd>';
				}
			}
			$sibs = FLXB_Track::query( array( 'donation_id' => (int) $d['id'], 'per_page' => 100 ) )['items'];
			if ( count( $sibs ) > 1 ) {
				$links = array();
				foreach ( $sibs as $s ) {
					$links[] = (int) $s['id'] === (int) $id ? '<strong class="flxb-mono">' . esc_html( $s['code'] ) . '</strong>' : '<a class="flxb-mono" href="' . esc_url( self::url( (int) $s['id'] ) ) . '">' . esc_html( $s['code'] ) . '</a>';
				}
				echo '<dt>' . esc_html__( 'Others in this donation', 'flx-donate' ) . '</dt><dd>' . implode( ' · ', $links ) . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</dl>';
		if ( $d && ! in_array( $d['status'], FLXB_Donations::PAID, true ) ) {
			echo '<p class="flxb-notice flxb-notice--warn">' . esc_html__( 'Payment not received: it cannot be sent to a partner until the payment is approved.', 'flx-donate' ) . '</p>';
		}
		echo '</section></div>';

		echo '<div class="flxb-grid flxb-grid--1-1">';
		/* Actions */
		if ( $can ) {
			echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Status', 'flx-donate' ) . '</h2></div>';
			if ( in_array( $it['status'], array( 'approved', 'sent' ), true ) ) {
				echo self::form_open( 'step', $id ) . '<input type="hidden" name="step" value="assign"><label class="flxb-field"><span class="flxb-label">' . esc_html( $l['do_assign'] ) . '</span>' . self::place_select( 'place', '', '— ' . $l['partner'] . ' —', $tid ) . '</label><label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Note', 'flx-donate' ) . '</span><input type="text" name="note" maxlength="300"></label><div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html( $l['do_assign'] ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			$btn  = array(
				'pending'   => array( 'approve', 'cancel' ),
				'approved'  => array( 'reset', 'cancel' ),
				'sent'      => array( 'revoke', 'done' ),
				'done'      => array( 'delivered' ),
				'canceled'  => array( 'reset' ),
				'delivered' => array(),
			);
			$conf = array(
				/* translators: %s: button text, e.g. "Take back from the partner" */
				'revoke' => sprintf( __( '%s? It goes back to approved and can be sent to another partner.', 'flx-donate' ), $l['do_revoke'] ),
				'cancel' => __( 'Cancel this item?', 'flx-donate' ),
			);
			$steps = self::steps( $tid );
			echo '<div class="flxb-kbtns">';
			foreach ( $btn[ $it['status'] ] ?? array() as $s ) {
				echo self::form_open( 'step', $id, isset( $conf[ $s ] ) ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $conf[ $s ] ) ) . ')"' : '' ) . '<input type="hidden" name="step" value="' . esc_attr( $s ) . '"><button type="submit" class="flxb-btn' . ( in_array( $s, array( 'approve', 'done', 'delivered' ), true ) ? ' flxb-btn--primary' : ( in_array( $s, array( 'cancel', 'revoke' ), true ) ? ' flxb-btn--danger' : '' ) ) . '">' . esc_html( $steps[ $s ] ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			echo self::form_open( 'note', $id ) . '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Add a note to the history', 'flx-donate' ) . '</span><input type="text" name="note" maxlength="300" required></label><div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Add note', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</section>';
		}

		/* Files */
		$files = FLXB_Track::files( $it );
		$hint  = FLXB_Track::file_completes( $it ) ? __( 'Files stay on the item. The first file marks an item that is with a partner as completed.', 'flx-donate' ) : __( 'Files stay on the item.', 'flx-donate' );
		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html( $l['files'] ) . '</h2><p class="flxb-card__hint">' . esc_html( $hint ) . '</p></div></div>';
		if ( ! $files ) {
			echo '<p class="flxb-muted">' . esc_html__( 'No file yet.', 'flx-donate' ) . '</p>';
		}
		$phone = FLXB_Track::owner_phone( $it );
		foreach ( $files as $v ) {
			$att = (int) $v['att'];
			$by  = get_userdata( (int) ( $v['by'] ?? 0 ) );
			echo '<div class="flxb-kvideo">' . self::file_preview( $v ) . '<div class="flxb-kvideo__meta">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<span>' . esc_html( flxb_date( (string) $v['at'] ) . ( $by ? ' · ' . $by->display_name : '' ) ) . '</span>';
			foreach ( (array) ( $v['sent'] ?? array() ) as $s ) {
				/* translators: 1: phone, 2: date */
				echo '<span class="flxb-pill flxb-pill--ok">' . esc_html( sprintf( __( 'Sent to %1$s · %2$s', 'flx-donate' ), self::phone( (string) $s['to'] ), flxb_date( (string) $s['at'] ) ) ) . '</span>';
			}
			if ( $can ) {
				echo '<div class="flxb-kbtns">';
				if ( FLXB_Track::can_send() && '' !== $phone ) {
					/* translators: %s: phone */
					echo self::form_open( 'file_send', $id, ' onsubmit="return confirm(' . esc_attr( wp_json_encode( sprintf( __( 'Send this file to %s over WhatsApp?', 'flx-donate' ), self::phone( $phone ) ) ) ) . ')"' ) . '<input type="hidden" name="att" value="' . $att . '"><button type="submit" class="flxb-btn flxb-btn--primary flxb-btn--sm">' . esc_html__( 'Send to owner', 'flx-donate' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo self::form_open( 'file_delete', $id, ' onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Remove this file from the item? It stays in the media library.', 'flx-donate' ) ) ) . ')"' ) . '<input type="hidden" name="att" value="' . $att . '"><button type="submit" class="flxb-linkbtn flxb-linkbtn--danger">' . esc_html__( 'Remove', 'flx-donate' ) . '</button></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></div>';
		}
		if ( $can ) {
			if ( ! FLXB_Track::can_send() ) {
				echo '<p class="flxb-muted">' . esc_html__( 'Install Flux Chat to send files over WhatsApp.', 'flx-donate' ) . '</p>';
			} elseif ( '' === $phone ) {
				echo '<p class="flxb-muted">' . esc_html__( 'There is no phone number for the owner. Add one above to send the file.', 'flx-donate' ) . '</p>';
			}
			echo self::form_open( 'file_upload', $id, ' enctype="multipart/form-data"' ) . '<label class="flxb-field"><span class="flxb-label">' . esc_html__( 'Upload a file', 'flx-donate' ) . '</span><input type="file" name="file" accept="video/*,image/*,audio/*,application/pdf" required></label><div class="flxb-formactions"><button type="submit" class="flxb-btn">' . esc_html__( 'Upload', 'flx-donate' ) . '</button></div></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</section>';
		if ( ! $can ) {
			echo '<div></div>';
		}
		echo '</div>';

		echo FLXB_Detail_Tabs::render( 'item', $it, FLXB_Track::can() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside

		/* History */
		$ev = FLXB_Track::events( $tid );
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'History', 'flx-donate' ) . '</h2></div><ol class="flxb-ktime">';
		foreach ( FLXB_Track::history( $id ) as $h ) {
			$data = json_decode( (string) $h['data'], true );
			$data = is_array( $data ) ? $data : array();
			$u    = (int) $h['user_id'] ? get_userdata( (int) $h['user_id'] ) : null;
			$what = $ev[ $h['event'] ] ?? $h['event'];
			$more = '';
			if ( ! empty( $data['place_name'] ) ) {
				$more = (string) $data['place_name'];
			}
			if ( 'assigned' === $h['event'] && ! empty( $data['from'] ) ) {
				$from = FLXB_Track::place_name( (string) $data['from'] );
				/* translators: 1: new partner, 2: previous partner */
				$more = sprintf( __( '%1$s (moved from %2$s)', 'flx-donate' ), (string) $data['place_name'], '' !== $from ? $from : (string) $data['from'] );
			}
			if ( in_array( $h['event'], array( 'file_sent', 'video_sent' ), true ) && ! empty( $data['to'] ) ) {
				$more = self::phone( (string) $data['to'] );
			}
			if ( 'edited' === $h['event'] && ! empty( $data['to'] ) ) {
				$bits = array();
				foreach ( (array) $data['to'] as $k => $v ) {
					$bits[] = (string) ( $data['from'][ $k ] ?? '' ) . ' → ' . (string) $v;
				}
				$more = implode( ', ', $bits );
			}
			echo '<li class="flxb-ktime--' . esc_attr( $h['event'] ) . '"><time>' . esc_html( flxb_date( $h['created_at'] ) ) . '</time><div><b>' . esc_html( $what ) . '</b>' . ( '' !== $more ? ' · ' . esc_html( $more ) : '' ) . ( '' !== (string) $h['note'] ? '<p>' . esc_html( $h['note'] ) . '</p>' : '' ) . '<span class="flxb-sub2">' . esc_html( $u ? $u->display_name : __( 'System', 'flx-donate' ) ) . '</span></div></li>';
		}
		echo '</ol></section>';
		FLXB_Admin::footer();
	}

	/* ---------- Handlers ---------- */

	private static function guard( $action, $id = null ) {
		if ( ! FLXB_Track::can() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_track_' . $action . ( null === $id ? '' : '_' . (int) $id ) );
	}

	private static function back_item( $id, array $msg ) {
		FLXB_Admin::redirect( self::SLUG, array( 'item' => (int) $id, 'flxb_msg' => $msg[0], 'flxb_err' => $msg[1] ) );
	}

	private static function post( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function bulk() {
		self::guard( 'bulk' );
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$back = json_decode( wp_unslash( $_POST['back'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$back = is_array( $back ) ? array_map( 'sanitize_text_field', $back ) : array();
		unset( $back['page'] );
		if ( ! $ids ) {
			FLXB_Admin::redirect( self::SLUG, $back + array( 'flxb_msg' => 'error', 'flxb_err' => __( 'Select at least one item.', 'flx-donate' ) ) );
		}
		$m = self::result_msg( FLXB_Track::apply( $ids, sanitize_key( self::post( 'step' ) ), array( 'place' => self::post( 'place' ), 'note' => self::post( 'note' ) ) ) );
		FLXB_Admin::redirect( self::SLUG, $back + array( 'flxb_msg' => $m[0], 'flxb_err' => $m[1] ) );
	}

	public static function step() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'step', $id );
		self::back_item( $id, self::result_msg( FLXB_Track::apply( array( $id ), sanitize_key( self::post( 'step' ) ), array( 'place' => self::post( 'place' ), 'note' => self::post( 'note' ) ) ) ) );
	}

	public static function edit() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'edit', $id );
		FLXB_Track::edit( $id, self::post( 'name' ), self::post( 'phone' ) );
		if ( isset( $_POST['target_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			FLXB_Track::set_target( $id, self::post( 'target_date' ) );
		}
		self::back_item( $id, array( 'saved', '' ) );
	}

	public static function note() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'note', $id );
		if ( '' !== self::post( 'note' ) ) {
			FLXB_Track::log( $id, 'note', self::post( 'note' ) );
		}
		self::back_item( $id, array( 'saved', '' ) );
	}

	public static function file_upload() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'file_upload', $id );
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			self::back_item( $id, array( 'error', __( 'Choose a file.', 'flx-donate' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( 'file', 0 ); // WordPress checks the file type against the allowed list
		$r   = is_wp_error( $att ) ? $att : FLXB_Track::add_file( $id, $att );
		self::back_item( $id, is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'saved', __( 'File added.', 'flx-donate' ) ) );
	}

	public static function file_send() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'file_send', $id );
		$r = FLXB_Track::send_file( $id, absint( $_POST['att'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::back_item( $id, is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'saved', __( 'The file was sent to the owner over WhatsApp.', 'flx-donate' ) ) );
	}

	public static function file_delete() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::guard( 'file_delete', $id );
		FLXB_Track::remove_file( $id, absint( $_POST['att'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::back_item( $id, array( 'saved', '' ) );
	}
}

class_alias( 'FLXB_Page_Track', 'FLXB_Page_Kurban' ); // before 2.11
