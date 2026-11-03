<?php
/**
 * Flux Donate: donation list, detail and CSV export.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Donations {

	const PER_PAGE = 25;

	/** Filters from the query string. */
	public static function filters( array $src ) {
		$g = function ( $k ) use ( $src ) {
			return isset( $src[ $k ] ) ? sanitize_text_field( wp_unslash( $src[ $k ] ) ) : '';
		};
		$re = '/^\d{4}-\d{2}-\d{2}$/';
		return array(
			'search'      => $g( 's' ),
			'from'        => preg_match( $re, $g( 'from' ) ) ? $g( 'from' ) : '',
			'to'          => preg_match( $re, $g( 'to' ) ) ? $g( 'to' ) : '',
			'status'      => $g( 'status' ),
			'type'        => $g( 'type' ),
			'type_id' => '' !== $g( 'dtype' ) ? (string) absint( $g( 'dtype' ) ) : '',
			'form_id'     => '' !== $g( 'form' ) ? (string) absint( $g( 'form' ) ) : '',
			'donor_id'    => '' !== $g( 'donor_id' ) ? (string) absint( $g( 'donor_id' ) ) : '',
			'ref_user_id' => '' !== $g( 'ref' ) ? (string) absint( $g( 'ref' ) ) : '',
		);
	}

	/** 2.2.0: staff with flxb_view_own always see only the donations they referred. */
	private static function scoped( array $f ) {
		if ( FLXB_Refs::own_only() ) {
			$f['ref_user_id'] = (string) get_current_user_id();
		}
		return $f;
	}

	/** May the current user open this donation? */
	private static function may_see( array $d ) {
		return flxb_can_view() || ( FLXB_Refs::own_only() && (int) ( $d['ref_user_id'] ?? 0 ) === get_current_user_id() );
	}

	public static function render() {
		if ( ! FLXB_Refs::can_list() ) {
			FLXB_Admin::deny();
		}
		$own = FLXB_Refs::own_only();
		$id  = absint( FLXB_Admin::q( 'donation', '0' ) );
		if ( $id ) {
			self::detail( $id );
			return;
		}
		if ( ! $own && flxb_can_manage() && class_exists( 'FLXB_WC_Ledger' ) && function_exists( 'wc_get_order' ) ) {
			FLXB_WC_Ledger::reconcile(); // 2.5.0: donations of orders deleted before
		}
		$f     = self::filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$page  = max( 1, absint( FLXB_Admin::q( 'paged', '1' ) ) );
		$per   = FLXB_Admin::per_page( 'flxb-donations', self::PER_PAGE );
		$res   = FLXB_Donations::query( array_merge( self::scoped( $f ), array( 'per_page' => $per, 'page' => $page, 'group' => true ) ) );
		$args  = array_filter( array( 'page' => 'flxb-donations', 's' => $f['search'], 'from' => $f['from'], 'to' => $f['to'], 'status' => $f['status'], 'type' => $f['type'], 'dtype' => $f['type_id'], 'form' => $f['form_id'], 'donor_id' => $f['donor_id'], 'ref' => $own ? '' : $f['ref_user_id'] ), 'strlen' );
		$csv   = wp_nonce_url( add_query_arg( array_merge( $args, array( 'action' => 'flxb_csv' ) ), admin_url( 'admin-post.php' ) ), 'flxb_csv' );

		FLXB_Admin::header(
			$own ? __( 'My referrals', 'flx-donate' ) : __( 'Donations', 'flx-donate' ),
			$own ? __( 'Donations made through your referral link or recorded with you as the referral.', 'flx-donate' ) : __( 'Every gift received through your forms, one-time and recurring.', 'flx-donate' ),
			array_merge( array( array( 'label' => __( 'Export CSV', 'flx-donate' ), 'url' => $csv, 'icon' => 'download' ) ), flxb_can_manage() ? array( array( 'label' => __( 'Add donation', 'flx-donate' ), 'url' => FLXB_Admin_Url::page( 'flxb-add' ), 'icon' => 'plus', 'primary' => true ) ) : array() )
		);
		$trash = flxb_can_manage() && ! $own && 'trash' !== $f['status'] ? FLXB_Donations::trash_count() : 0;
		FLXB_Admin::list_lib() ? self::filter_bar_lib( $f ) : self::filter_bar( $f, $trash );

		echo '<div class="flxb-panel">';
		if ( ! $res['items'] ) {
			echo '<div class="flxb-emptystate">' . FLXB_Admin::icon( 'heart', 32 ) . '<h2>' . esc_html__( 'No donations found', 'flx-donate' ) . '</h2><p>' . esc_html__( 'Try a wider date range or clear the filters.', 'flx-donate' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			$bulk = self::bulk_actions( 'trash' === $f['status'] );
			if ( $bulk ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-bulkform" data-flxb-bulk>';
				wp_nonce_field( 'flxb_bulk' );
				echo '<input type="hidden" name="action" value="flxb_bulk"><input type="hidden" name="back" value="' . esc_attr( add_query_arg( array_merge( $args, array( 'paged' => $page ) ), admin_url( 'admin.php' ) ) ) . '">';
				echo '<div class="flxb-bulkbar" hidden><span class="flxb-bulkbar__n" data-flxb-bulk-n></span><label class="screen-reader-text" for="flxb-bulk-do">' . esc_html__( 'Bulk action', 'flx-donate' ) . '</label><select id="flxb-bulk-do" name="do">';
				foreach ( $bulk as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
				}
				echo '</select><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Apply', 'flx-donate' ) . '</button><button type="button" class="flxb-btn" data-flxb-bulk-clear>' . esc_html__( 'Clear selection', 'flx-donate' ) . '</button></div>';
			}
			self::table( $res['items'], true, ! $own, (bool) $bulk );
			if ( $bulk ) {
				echo '</form>';
			}
			echo '<div class="flxb-tablefoot"><span>';
			/* translators: 1: number of donations, 2: received amount */
			echo esc_html( sprintf( _n( '%1$s donation · %2$s received', '%1$s donations · %2$s received', $res['total'], 'flx-donate' ), number_format_i18n( $res['total'] ), flxb_money( $res['sum'] ) ) );
			if ( $trash && FLXB_Admin::list_lib() ) {
				/* translators: %s: number of gifts in the trash */
				echo ' · <a class="flxb-filters__trash" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', array( 'status' => 'trash' ) ) ) . '">' . esc_html( sprintf( __( 'Trash (%s)', 'flx-donate' ), number_format_i18n( $trash ) ) ) . '</a>';
			}
			echo '</span>' . FLXB_Admin::list_pagination( $res['total'], $per, $page, $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		FLXB_Admin::footer();
	}

	/**
	 * 2.6.0: filter bar on the shared Flux Admin list library. Every field is a direct child of the form:
	 * search on its own row, status and donation type beside it, the rest under "Filters".
	 */
	private static function filter_bar_lib( array $f ) {
		$forms  = flxb_forms();
		$dtypes = FLXB_Types::all( 'all' );
		$sel    = function ( $name, $label, $all, array $opts, $value, $primary = false ) {
			echo '<label' . ( $primary ? ' data-flxa-primary' : '' ) . '><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $all ) . '</option>';
			foreach ( $opts as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></label>';
		};
		echo '<form class="flxb-filters flxb-filters--lib" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"' . FLXB_Admin::list_attrs( 'flxb-donations' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="page" value="flxb-donations">';
		if ( '' !== $f['donor_id'] ) {
			echo '<input type="hidden" name="donor_id" value="' . esc_attr( $f['donor_id'] ) . '">';
		}
		echo '<input type="search" name="s" value="' . esc_attr( $f['search'] ) . '" placeholder="' . esc_attr__( 'Name, e-mail, phone, code…', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'Search', 'flx-donate' ) . '">';
		$st = array();
		foreach ( FLXB_Donations::STATUSES as $s ) {
			$st[ $s ] = flxb_status_label( $s )[1];
		}
		$sel( 'status', __( 'Status', 'flx-donate' ), __( 'All statuses', 'flx-donate' ), $st, $f['status'], true );
		if ( $dtypes ) {
			$dt = array( '0' => __( 'No donation type', 'flx-donate' ) );
			foreach ( $dtypes as $c ) {
				$dt[ (int) $c['id'] ] = $c['title'];
			}
			$sel( 'dtype', __( 'Donation type', 'flx-donate' ), __( 'All donation types', 'flx-donate' ), $dt, $f['type_id'], true );
		}
		$sel( 'type', __( 'Type', 'flx-donate' ), __( 'All types', 'flx-donate' ), array( 'one_time' => flxb_type_label( 'one_time' ), 'recurring' => flxb_type_label( 'recurring' ) ), $f['type'], ! $dtypes );
		echo '<label><span>' . esc_html__( 'From', 'flx-donate' ) . '</span><input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'To', 'flx-donate' ) . '</span><input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"></label>';
		$fo = array();
		foreach ( $forms as $fid => $fm ) {
			if ( 'payment' === $fm['type'] ) {
				$fo[ (int) $fid ] = $fm['title'];
			}
		}
		if ( $fo ) {
			$sel( 'form', __( 'Form', 'flx-donate' ), __( 'All forms', 'flx-donate' ), $fo, $f['form_id'] );
		}
		$refs = FLXB_Refs::own_only() ? array() : FLXB_Refs::users();
		if ( $refs || ( '' !== $f['ref_user_id'] && ! FLXB_Refs::own_only() ) ) {
			$sel( 'ref', __( 'Referral', 'flx-donate' ), __( 'All referrals', 'flx-donate' ), array( '0' => __( 'No referral', 'flx-donate' ) ) + $refs, $f['ref_user_id'] );
		}
		echo '<button class="flxb-btn flxb-btn--primary" type="submit">' . esc_html__( 'Filter', 'flx-donate' ) . '</button>';
		echo '</form>';
	}

	private static function filter_bar( array $f, $trash = 0 ) {
		$forms     = flxb_forms();
		$dtypes = FLXB_Types::all( 'all' );
		echo '<form class="flxb-filters" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="flxb-donations">';
		if ( '' !== $f['donor_id'] ) {
			echo '<input type="hidden" name="donor_id" value="' . esc_attr( $f['donor_id'] ) . '">';
		}
		/* 2.2.3: search on one line; the other filters open under "Detailed filter". */
		$adv = count( array_filter( array( $f['from'], $f['to'], $f['status'], $f['type'], $f['type_id'], $f['form_id'], FLXB_Refs::own_only() ? '' : $f['ref_user_id'] ), 'strlen' ) );
		echo '<div class="flxb-filters__main">';
		echo '<label class="screen-reader-text" for="flxb-s">' . esc_html__( 'Search', 'flx-donate' ) . '</label>';
		echo '<input type="search" id="flxb-s" name="s" value="' . esc_attr( $f['search'] ) . '" placeholder="' . esc_attr__( 'Name, e-mail, phone, code…', 'flx-donate' ) . '">';
		echo '<button class="flxb-btn flxb-btn--primary" type="submit">' . FLXB_Admin::icon( 'search' ) . '<span class="screen-reader-text">' . esc_html__( 'Search', 'flx-donate' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<button class="flxb-btn flxb-filters__toggle" type="button" aria-controls="flxb-filters-adv" aria-expanded="' . ( $adv ? 'true' : 'false' ) . '">' . esc_html__( 'Detailed filter', 'flx-donate' ) . ( $adv ? ' <span class="flxb-filters__n">' . (int) $adv . '</span>' : '' ) . '</button>';
		if ( array_filter( $f, 'strlen' ) ) {
			echo '<a class="flxb-btn flxb-btn--ghost" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations' ) ) . '">' . esc_html__( 'Clear', 'flx-donate' ) . '</a>';
		}
		if ( $trash ) {
			/* translators: %s: number of gifts in the trash */
			echo '<a class="flxb-btn flxb-filters__trash" href="' . esc_url( FLXB_Admin_Url::page( 'flxb-donations', array( 'status' => 'trash' ) ) ) . '">' . esc_html( sprintf( __( 'Trash (%s)', 'flx-donate' ), number_format_i18n( $trash ) ) ) . '</a>';
		}
		echo '</div>';
		echo '<div class="flxb-filters__adv" id="flxb-filters-adv"' . ( $adv ? '' : ' hidden' ) . '>';
		echo '<div class="flxb-filters__dates"><label for="flxb-from"><span>' . esc_html__( 'From', 'flx-donate' ) . '</span>';
		echo '<input type="date" id="flxb-from" name="from" value="' . esc_attr( $f['from'] ) . '"></label>';
		echo '<label for="flxb-to"><span>' . esc_html__( 'To', 'flx-donate' ) . '</span><input type="date" id="flxb-to" name="to" value="' . esc_attr( $f['to'] ) . '"></label></div>';
		echo '<label class="screen-reader-text" for="flxb-status">' . esc_html__( 'Status', 'flx-donate' ) . '</label><select id="flxb-status" name="status"><option value="">' . esc_html__( 'All statuses', 'flx-donate' ) . '</option>';
		foreach ( FLXB_Donations::STATUSES as $s ) {
			echo '<option value="' . esc_attr( $s ) . '"' . selected( $f['status'], $s, false ) . '>' . esc_html( flxb_status_label( $s )[1] ) . '</option>';
		}
		echo '</select>';
		echo '<label class="screen-reader-text" for="flxb-type">' . esc_html__( 'Type', 'flx-donate' ) . '</label><select id="flxb-type" name="type"><option value="">' . esc_html__( 'All types', 'flx-donate' ) . '</option>';
		foreach ( array( 'one_time', 'recurring' ) as $t ) {
			echo '<option value="' . esc_attr( $t ) . '"' . selected( $f['type'], $t, false ) . '>' . esc_html( flxb_type_label( $t ) ) . '</option>';
		}
		echo '</select>';
		if ( $dtypes ) {
			echo '<label class="screen-reader-text" for="flxb-dtype">' . esc_html__( 'Donation type', 'flx-donate' ) . '</label><select id="flxb-dtype" name="dtype"><option value="">' . esc_html__( 'All donation types', 'flx-donate' ) . '</option><option value="0"' . selected( $f['type_id'], '0', false ) . '>' . esc_html__( 'No donation type', 'flx-donate' ) . '</option>';
			foreach ( $dtypes as $c ) {
				echo '<option value="' . (int) $c['id'] . '"' . selected( $f['type_id'], (string) $c['id'], false ) . '>' . esc_html( $c['title'] ) . '</option>';
			}
			echo '</select>';
		}
		if ( $forms ) {
			echo '<label class="screen-reader-text" for="flxb-form">' . esc_html__( 'Form', 'flx-donate' ) . '</label><select id="flxb-form" name="form"><option value="">' . esc_html__( 'All forms', 'flx-donate' ) . '</option>';
			foreach ( $forms as $fid => $fm ) {
				if ( 'payment' !== $fm['type'] ) {
					continue;
				}
				echo '<option value="' . (int) $fid . '"' . selected( $f['form_id'], (string) $fid, false ) . '>' . esc_html( $fm['title'] ) . '</option>';
			}
			echo '</select>';
		}
		$refs = FLXB_Refs::own_only() ? array() : FLXB_Refs::users();
		if ( $refs || '' !== $f['ref_user_id'] ) {
			echo '<label class="screen-reader-text" for="flxb-ref">' . esc_html__( 'Referral', 'flx-donate' ) . '</label><select id="flxb-ref" name="ref"><option value="">' . esc_html__( 'All referrals', 'flx-donate' ) . '</option><option value="0"' . selected( $f['ref_user_id'], '0', false ) . '>' . esc_html__( 'No referral', 'flx-donate' ) . '</option>';
			foreach ( $refs as $uid => $label ) {
				echo '<option value="' . (int) $uid . '"' . selected( $f['ref_user_id'], (string) $uid, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}
		echo '<div class="flxb-filters__end"><button class="flxb-btn flxb-btn--primary" type="submit">' . FLXB_Admin::icon( 'search' ) . esc_html__( 'Filter', 'flx-donate' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></form>';
	}

	/** Donation rows (also used on the donor card). */
	public static function table( array $items, $show_donor, $show_ref = false, $bulk = false ) {
		$donor_links = FLXB_Refs::can_donors(); // 2.3.0: own donors open their card
		echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--gifts' . ( $show_donor ? ' flxb-table--list' : '' ) . ( $bulk ? ' flxb-table--bulk' : '' ) . '"><thead><tr>';
		if ( $bulk ) {
			echo '<th scope="col" class="flxb-c-check"><input type="checkbox" data-flxb-check-all aria-label="' . esc_attr__( 'Select all', 'flx-donate' ) . '"></th>';
		}
		echo '<th scope="col">' . esc_html__( 'Date', 'flx-donate' ) . '</th>';
		if ( $show_donor ) {
			echo '<th scope="col">' . esc_html__( 'Donor', 'flx-donate' ) . '</th>';
		}
		echo '<th scope="col" class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Type', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Donation type / form', 'flx-donate' ) . '</th>' . ( $show_donor ? ( $show_ref ? '<th scope="col">' . esc_html__( 'Referral', 'flx-donate' ) . '</th>' : '' ) . '<th scope="col">' . esc_html__( 'Confirmation code', 'flx-donate' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-donate' ) . '</span></th>' : '' );
		echo '</tr></thead><tbody>';
		$camps = array();
		foreach ( FLXB_Types::all( 'all' ) as $c ) {
			$camps[ (int) $c['id'] ] = $c['title'];
		}
		foreach ( $items as $r ) {
			$url = FLXB_Admin_Url::donation( (int) $r['id'] );
			echo '<tr class="flxb-row-link" data-href="' . esc_url( $url ) . '">'; // 2.3.1: the whole row opens the donation
			if ( $bulk ) {
				/* translators: %s: donor name */
				echo '<td class="flxb-c-check"><input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '" aria-label="' . esc_attr( sprintf( __( 'Select the donation of %s', 'flx-donate' ), FLXB_Admin::donor_name( $r ) ) ) . '"></td>';
			}
			echo '<td class="flxb-col-date' . ( $show_donor ? '' : ' flxb-col-main' ) . '" data-label="' . esc_attr__( 'Date', 'flx-donate' ) . '"><span>' . ( $show_donor ? esc_html( flxb_date( $r['created_at'] ) ) : '<a href="' . esc_url( $url ) . '">' . esc_html( flxb_date( $r['created_at'] ) ) . '</a>' ) . '</span></td>';
			if ( $show_donor ) {
				$dn = esc_html( FLXB_Admin::donor_name( $r ) );
				echo '<td class="flxb-col-main">' . ( ! (int) $r['donor_id'] ? '<span class="flxb-muted">' . esc_html__( 'Anonymous', 'flx-donate' ) . '</span>' : ( $donor_links ? '<a href="' . esc_url( FLXB_Admin_Url::donor( (int) $r['donor_id'] ) ) . '">' . $dn . '</a>' : $dn ) );
				$contact = '' !== (string) $r['donor_email'] ? $r['donor_email'] : flxb_format_phone( $r['donor_phone'] );
				if ( '' !== (string) $contact && $contact !== FLXB_Admin::donor_name( $r ) ) {
					echo '<span class="flxb-sub2">' . esc_html( $contact ) . '</span>';
				}
				echo '</td>';
			}
			$lines = (int) ( $r['group_lines'] ?? 1 );
			echo '<td class="num flxb-c-amount" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $r['group_amount'] ?? $r['amount'], $r['currency'] ) ) . '</span></td>';
			echo '<td class="flxb-c-type" data-label="' . esc_attr__( 'Type', 'flx-donate' ) . '"><span>' . FLXB_Admin::type_pill( $r['type'] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="flxb-c-status" data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . FLXB_Admin::pill( $r['status'] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$where = array();
			if ( (int) $r['type_id'] && isset( $camps[ (int) $r['type_id'] ] ) ) {
				$where[] = $camps[ (int) $r['type_id'] ];
			}
			if ( (int) $r['form_id'] ) {
				$where[] = flxb_form_title( (int) $r['form_id'] );
			}
			if ( $lines > 1 ) {
				/* translators: %s: number of lines in the order */
				$where = array( sprintf( _n( '%s item', '%s items', $lines, 'flx-donate' ), number_format_i18n( $lines ) ) );
				foreach ( FLXB_Donations::siblings( $r ) as $sib ) {
					if ( (int) $sib['type_id'] && isset( $camps[ (int) $sib['type_id'] ] ) && ! in_array( $camps[ (int) $sib['type_id'] ], $where, true ) ) {
						$where[] = $camps[ (int) $sib['type_id'] ];
					}
				}
			}
			echo '<td class="flxb-c-where" data-label="' . esc_attr__( 'Donation type / form', 'flx-donate' ) . '"><span>' . esc_html( $where ? implode( ' · ', $where ) : '—' ) . '</span></td>';
			if ( $show_ref ) {
				$ref = (int) ( $r['ref_user_id'] ?? 0 );
				echo '<td class="flxb-c-ref" data-label="' . esc_attr__( 'Referral', 'flx-donate' ) . '"><span>' . ( $ref ? esc_html( FLXB_Refs::name( $ref ) ) : '—' ) . '</span></td>';
			}
			if ( $show_donor ) {
				echo '<td class="flxb-c-code" data-label="' . esc_attr__( 'Confirmation code', 'flx-donate' ) . '"><span>' . ( '' !== (string) $r['track'] ? '<code>' . esc_html( $r['track'] ) . '</code>' : '—' ) . '</span></td>';
				echo '<td class="flxb-col-act"><a class="flxb-btn flxb-btn--sm flxb-row-go" href="' . esc_url( $url ) . '">' . esc_html__( 'Details', 'flx-donate' ) . '<span aria-hidden="true">›</span></a></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * 2.5.0: bulk actions the current user may run on the list.
	 *
	 * @return array action => label
	 */
	private static function bulk_actions( $in_trash ) {
		if ( FLXB_Refs::own_only() || ! flxb_can_manage() ) {
			return array();
		}
		$a = $in_trash ? array( 'restore' => __( 'Restore', 'flx-donate' ) ) : array( 'trash' => __( 'Move to trash', 'flx-donate' ) );
		if ( current_user_can( 'manage_options' ) ) {
			$a['delete'] = __( 'Delete permanently', 'flx-donate' );
		}
		return $a;
	}

	/** 2.5.0: run a bulk action. An order's donations always move together, with the order. */
	public static function bulk() {
		check_admin_referer( 'flxb_bulk' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( ! isset( self::bulk_actions( 'restore' === $do )[ $do ] ) ) {
			FLXB_Admin::deny();
		}
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
		$back = $back && 0 === strpos( $back, admin_url() ) ? $back : FLXB_Admin_Url::page( 'flxb-donations' );
		$ids  = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) : array();
		$done = 0;
		$seen = array();
		foreach ( $ids as $id ) {
			$d = FLXB_Donations::get( $id );
			if ( ! $d ) {
				continue;
			}
			$oid = (int) $d['wc_order_id'];
			if ( $oid ) {
				if ( isset( $seen[ $oid ] ) ) {
					continue;
				}
				$seen[ $oid ] = true;
				$order        = function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
				if ( $order && ! current_user_can( 'delete_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
					continue;
				}
				if ( $order ) {
					if ( 'trash' === $do ) {
						$order->delete( false ); // moves the order to the trash; the ledger follows
					} elseif ( 'delete' === $do ) {
						$order->delete( true );
					} elseif ( 'restore' === $do ) {
						$store = $order->get_data_store();
						if ( 'trash' === $order->get_status() && is_callable( array( $store, 'untrash_order' ) ) ) {
							$store->untrash_order( $order );
						} else {
							FLXB_Donations::restore_rows( FLXB_Donations::ids_of_order( $oid ) );
						}
					}
					// Anything the hooks missed (e.g. an order not in the trash) is brought in line here.
					$left = FLXB_Donations::ids_of_order( $oid );
					if ( 'delete' === $do && $left ) {
						FLXB_Donations::delete_rows( $left );
					} elseif ( 'trash' === $do ) {
						FLXB_Donations::trash_rows( $left );
					}
					++$done;
					continue;
				}
				$rows = FLXB_Donations::ids_of_order( $oid ); // the order is gone already
			} else {
				$rows = array( $id );
			}
			if ( 'trash' === $do ) {
				FLXB_Donations::trash_rows( $rows );
			} elseif ( 'delete' === $do ) {
				FLXB_Donations::delete_rows( $rows );
			} else {
				FLXB_Donations::restore_rows( $rows );
			}
			++$done;
		}
		$msg = array(
			/* translators: %s: number of gifts */
			'trash'   => _n( '%s donation moved to the trash.', '%s donations moved to the trash.', $done, 'flx-donate' ),
			/* translators: %s: number of gifts */
			'restore' => _n( '%s donation restored.', '%s donations restored.', $done, 'flx-donate' ),
			/* translators: %s: number of gifts */
			'delete'  => _n( '%s donation deleted permanently.', '%s donations deleted permanently.', $done, 'flx-donate' ),
		);
		wp_safe_redirect( add_query_arg( array( 'flxb_msg' => 'saved', 'flxb_err' => rawurlencode( sprintf( $msg[ $do ], number_format_i18n( $done ) ) ) ), $back ) );
		exit;
	}

	/** Printable receipt for one paid donation (organisation details from the settings). */
	public static function receipt() {
		if ( ! FLXB_Refs::can_list() ) {
			FLXB_Admin::deny();
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'flxb_receipt_' . $id );
		$d = FLXB_Donations::get( $id );
		if ( ! $d || ! in_array( $d['status'], FLXB_Donations::PAID, true ) || ! self::may_see( $d ) ) {
			wp_die( esc_html__( 'Donation not found.', 'flx-donate' ), '', array( 'response' => 404 ) );
		}
		$b     = flxb_brand();
		$donor = (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$type  = (int) $d['type_id'] ? FLXB_Types::get( (int) $d['type_id'] ) : null;
		$rows  = array(
			__( 'Receipt no.', 'flx-donate' )  => 'B-' . str_pad( (string) (int) $d['id'], 6, '0', STR_PAD_LEFT ),
			__( 'Date', 'flx-donate' )         => flxb_date( $d['created_at'] ),
			__( 'Donor', 'flx-donate' )        => $donor ? FLXB_Admin::donor_name( array( 'donor_name' => $donor['name'], 'donor_email' => $donor['email'], 'donor_phone' => $donor['phone'], 'donor_id' => $donor['id'] ) ) : __( 'Anonymous', 'flx-donate' ),
			__( 'Donation type', 'flx-donate' ) => $type ? $type['title'] : ( (int) $d['form_id'] ? flxb_form_title( (int) $d['form_id'] ) : '—' ),
		);
		if ( '' !== (string) $d['option_label'] ) {
			$rows[ __( 'Option', 'flx-donate' ) ] = (int) $d['quantity'] > 1 ? sprintf( '%s × %s (%s)', number_format_i18n( (int) $d['quantity'] ), $d['option_label'], flxb_money( $d['unit_price'], $d['currency'] ) ) : $d['option_label'];
		}
		$rows[ __( 'Kind', 'flx-donate' ) ] = flxb_type_label( $d['type'] );
		if ( '' !== (string) $d['track'] ) {
			$rows[ __( 'Confirmation code', 'flx-donate' ) ] = $d['track'];
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="' . esc_attr( str_replace( '_', '-', get_user_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( sprintf( /* translators: %s: organisation name */ __( 'Donation receipt · %s', 'flx-donate' ), $b['name'] ) ) . '</title>';
		echo '<style>:root{--b:' . esc_html( $b['primary'] ) . '}*{box-sizing:border-box}body{margin:0;background:#f3f5f4;color:#1E1B4B;font:15px/1.55 "Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}.r{max-width:720px;margin:32px auto;padding:36px 40px;background:#fff;border-radius:16px;border-top:6px solid var(--b);box-shadow:0 1px 3px rgba(20,50,74,.08)}.h{display:flex;align-items:center;gap:16px;margin:0 0 24px}.h img{max-height:64px;max-width:160px}.h b{font-size:20px}.t{margin:0 0 4px;font-size:24px}.a{margin:18px 0;padding:18px 20px;border-radius:12px;background:color-mix(in srgb,var(--b) 8%,#fff);font-size:30px;font-weight:800;color:var(--b)}table{width:100%;border-collapse:collapse}th,td{padding:9px 0;border-bottom:1px solid #e3ebe6;text-align:left;vertical-align:top}th{width:40%;color:#4B5563;font-weight:600}.n{margin:22px 0 0;white-space:pre-line}.f{margin:28px 0 0;padding:14px 0 0;border-top:1px solid #e3ebe6;color:#4B5563;font-size:13px;white-space:pre-line}.p{display:block;margin:0 auto 32px;padding:10px 22px;border:0;border-radius:10px;background:var(--b);color:#fff;font:700 14px inherit;cursor:pointer}@media print{body{background:#fff}.r{margin:0;box-shadow:none;max-width:none}.p{display:none}}@media (max-width:600px){.r{margin:0;padding:24px 18px;border-radius:0}}</style></head><body>';
		echo '<main class="r"><div class="h">' . ( $b['logo'] ? '<img src="' . esc_url( $b['logo'] ) . '" alt="">' : '' ) . '<b>' . esc_html( $b['name'] ) . '</b></div>';
		echo '<h1 class="t">' . esc_html__( 'Donation receipt', 'flx-donate' ) . '</h1>';
		echo '<div class="a">' . esc_html( flxb_money( $d['amount'], $d['currency'] ) ) . '</div><table>';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</table>';
		if ( '' !== trim( $b['receipt_text'] ) ) {
			echo '<p class="n">' . esc_html( $b['receipt_text'] ) . '</p>';
		}
		if ( '' !== trim( $b['footer_text'] ) ) {
			echo '<div class="f">' . esc_html( $b['footer_text'] ) . '</div>';
		}
		echo '</main><button type="button" class="p" onclick="window.print()">' . esc_html__( 'Print', 'flx-donate' ) . '</button></body></html>';
		exit;
	}

	/** Refund (amount) / cancel through the provider, when Flux Donate takes the payments. */
	private static function refund_button( array $d ) {
		/* 2.0: a donation paid through WooCommerce is refunded on its order (also when the 1.x payment layer is off). */
		if ( class_exists( 'FLXB_WC_Admin' ) && FLXB_WC::ready() && FLXB_WC_Admin::donation_actions( $d ) ) {
			return;
		}
		if ( ! function_exists( 'flxb_owns_payments' ) || ! flxb_owns_payments() || ! class_exists( 'FLXB_Page_Payments' ) ) {
			return;
		}
		$m   = FLXB_Donations::meta( $d );
		$row = (int) $d['pay_id'] && empty( $m['renewal'] ) ? FLXB_Payment_Records::get( (int) $d['pay_id'] ) : null;
		echo '<div class="flxb-formactions">';
		FLXB_Page_Payments::money_form( $row, $d, FLXB_Admin_Url::donation( (int) $d['id'] ) );
		echo '</div>';
	}

	/** Receipts linked by Flux Chat (meta.receipts) and the change log (meta.audit). */
	private static function records_card( array $d ) {
		$m        = FLXB_Donations::meta( $d );
		// 2.4.0: while the gift awaits approval its receipts are in the approval card.
		$receipts = FLXB_Approval::pending( $d ) ? array() : array_values( array_filter( (array) ( $m['receipts'] ?? array() ), 'is_array' ) );
		$contacts = array_values( array_filter( (array) ( $m['beneficiary_contacts'] ?? array() ), 'is_array' ) );
		$audit    = array_values( array_filter( (array) ( $m['audit'] ?? array() ), 'is_array' ) );
		if ( ! $receipts && ! $contacts && ! $audit ) {
			return;
		}
		$user = function ( $id ) {
			$u = (int) $id ? get_userdata( (int) $id ) : null;
			return $u ? $u->display_name : ( (int) $id ? '#' . (int) $id : __( 'System', 'flx-donate' ) );
		};
		echo '<div class="flxb-grid flxb-grid--1-1 flxb-card--gap">';
		if ( $contacts ) {
			echo '<section class="flxb-card flxb-contacts"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Contacts of the people on whose behalf', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Given to the call center so they can be told when the donation is carried out.', 'flx-donate' ) . '</p></div></div><div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Name', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Phone', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'E-mail', 'flx-donate' ) . '</th></tr></thead><tbody>';
			foreach ( $contacts as $c ) {
				$phone = (string) ( $c['phone'] ?? '' );
				$email = (string) ( $c['email'] ?? '' );
				echo '<tr><td class="flxb-col-main">' . esc_html( '' !== (string) ( $c['name'] ?? '' ) ? (string) $c['name'] : '—' ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Phone', 'flx-donate' ) . '"><span>' . ( '' !== $phone ? '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( flxb_format_phone( $phone ) ) . '</a>' : '—' ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'E-mail', 'flx-donate' ) . '"><span>' . ( is_email( $email ) ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '—' ) . '</span></td></tr>';
			}
			echo '</tbody></table></div></section>';
		}
		if ( $receipts || $audit ) {
			echo '<section class="flxb-card flxb-history">';
			if ( $receipts ) {
				echo '<div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Receipts', 'flx-donate' ) . '</h2></div><ul class="flxb-list flxb-receipts">';
				foreach ( $receipts as $r ) {
					$url  = (string) ( $r['url'] ?? '' );
					$url  = preg_match( '#^https?://#i', $url ) ? $url : '';
					$name = '' !== (string) ( $r['file'] ?? '' ) ? (string) $r['file'] : __( 'Receipt', 'flx-donate' );
					$bits = array_filter( array( ! empty( $r['at'] ) ? flxb_date( (string) $r['at'] ) : '', isset( $r['user'] ) ? $user( $r['user'] ) : '', ! empty( $r['source'] ) ? ( 'whatsapp' === $r['source'] ? 'WhatsApp' : (string) $r['source'] ) : '' ) );
					echo '<li>' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . ( $bits ? ' <span class="flxb-muted">· ' . esc_html( implode( ' · ', $bits ) ) . '</span>' : '' ) . '</li>';
				}
				echo '</ul>';
			}
			if ( $audit ) {
				echo '<div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'History', 'flx-donate' ) . '</h2></div><ul class="flxb-list flxb-audit">';
				foreach ( array_reverse( $audit ) as $a ) {
					echo '<li><span class="flxb-muted">' . esc_html( flxb_date( (string) ( $a['at'] ?? '' ) ) ) . '</span> · ' . esc_html( self::audit_text( $a ) ) . ' <span class="flxb-muted">· ' . esc_html( $user( $a['user'] ?? 0 ) ) . '</span></li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}
		echo '</div>';
	}

	/** One change-log line in words. */
	private static function audit_text( array $a ) {
		$c   = isset( $a['changes'] ) && is_array( $a['changes'] ) ? $a['changes'] : array();
		$via = ! empty( $c['via'] ) ? ( 'flx-chat' === $c['via'] ? ' (' . __( 'call center', 'flx-donate' ) . ')' : ' (' . (string) $c['via'] . ')' ) : '';
		switch ( (string) ( $a['what'] ?? '' ) ) {
			case 'receipt':
				return __( 'Receipt linked', 'flx-donate' ) . $via;
			case 'rejected':
				/* translators: %s: reason */
				return sprintf( __( 'Payment rejected: %s', 'flx-donate' ), (string) ( $c['reason'] ?? '' ) ) . ( ! empty( $c['sent'] ) ? ' (' . implode( ', ', array_map( 'strval', (array) $c['sent'] ) ) . ')' : '' );
			case 'status':
				$st = isset( $c['status'] ) && is_array( $c['status'] ) ? $c['status'] : array( '', '' );
				/* translators: 1: old status, 2: new status */
				return sprintf( __( 'Status changed: %1$s → %2$s', 'flx-donate' ), flxb_status_label( (string) ( $st[0] ?? '' ) )[1], flxb_status_label( (string) ( $st[1] ?? '' ) )[1] ) . $via;
			case 'refund':
				/* translators: %s: amount */
				return sprintf( __( 'Refunded %s', 'flx-donate' ), isset( $c['amount'] ) ? number_format_i18n( (float) $c['amount'], 2 ) : '' ) . $via;
			case 'cancel':
				return __( 'Payment canceled at the provider', 'flx-donate' ) . $via;
			case 'beneficiaries':
				return __( 'Names on whose behalf changed', 'flx-donate' ) . $via;
			case 'ref':
				$r = isset( $c['ref'] ) && is_array( $c['ref'] ) ? $c['ref'] : array( '', '' );
				/* translators: 1: old referral, 2: new referral */
				return sprintf( __( 'Referral changed: %1$s → %2$s', 'flx-donate' ), '' !== (string) ( $r[0] ?? '' ) ? (string) $r[0] : '—', '' !== (string) ( $r[1] ?? '' ) ? (string) $r[1] : '—' );
		}
		return (string) ( $a['what'] ?? '' ) . $via;
	}

	private static function detail( $id ) {
		$d = FLXB_Donations::get( $id );
		if ( ! $d || ! self::may_see( $d ) ) {
			FLXB_Admin::header( __( 'Donation not found', 'flx-donate' ), '', array(), array( __( 'Donations', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-donations' ) ) );
			FLXB_Admin::footer();
			return;
		}
		$donor = (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$camp  = (int) $d['type_id'] ? FLXB_Types::get( (int) $d['type_id'] ) : null;
		$sub   = (int) $d['subscription_id'] ? FLXB_Subscriptions::get( (int) $d['subscription_id'] ) : null;
		$sibs  = FLXB_Donations::siblings( $d );
		$multi = count( $sibs ) > 1;
		/* translators: %s: amount */
		FLXB_Admin::header( sprintf( __( 'Donation of %s', 'flx-donate' ), flxb_money( $multi ? array_sum( array_map( 'floatval', wp_list_pluck( $sibs, 'amount' ) ) ) : $d['amount'], $d['currency'] ) ), flxb_date( $d['created_at'] ), in_array( $d['status'], FLXB_Donations::PAID, true ) ? array( array( 'label' => __( 'Print receipt', 'flx-donate' ), 'icon' => 'print', 'url' => wp_nonce_url( admin_url( 'admin-post.php?action=flxb_receipt&id=' . (int) $d['id'] ), 'flxb_receipt_' . (int) $d['id'] ) ) ) : array(), array( __( 'Donations', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-donations' ) ) );
		FLXB_Approval::card( $d, $sibs );
		self::order_card( $d, $sibs );
		echo '<div class="flxb-grid flxb-grid--2-1"><section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html( $multi ? __( 'This item', 'flx-donate' ) : __( 'Payment', 'flx-donate' ) ) . '</h2><div class="flxb-chips">' . FLXB_Admin::type_pill( $d['type'] ) . FLXB_Admin::pill( $d['status'] ) . '</div></div><dl class="flxb-dl">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$rows = array(
			__( 'Amount', 'flx-donate' )            => esc_html( flxb_money( $d['amount'], $d['currency'], 2 ) ),
			__( 'Date', 'flx-donate' )              => esc_html( flxb_date( $d['created_at'] ) ),
			__( 'Payment provider', 'flx-donate' )  => esc_html( flxb_provider_label( $d['provider'] ) ),
			__( 'Provider reference', 'flx-donate' ) => '' !== (string) $d['provider_ref'] ? '<code>' . esc_html( $d['provider_ref'] ) . '</code>' : '—',
			__( 'Confirmation code', 'flx-donate' ) => '' !== (string) $d['track'] ? '<code>' . esc_html( $d['track'] ) . '</code>' : '—',
			__( 'Option', 'flx-donate' )            => '' !== (string) $d['option_label'] ? esc_html( $d['option_label'] ) : '—',
			__( 'Quantity', 'flx-donate' )          => esc_html( number_format_i18n( (int) $d['quantity'] ) . ( (int) $d['quantity'] > 1 || '' !== (string) $d['option_key'] ? ' × ' . flxb_money( $d['unit_price'], $d['currency'], 2 ) : '' ) ),
			__( 'Form', 'flx-donate' )              => (int) $d['form_id'] ? esc_html( flxb_form_title( (int) $d['form_id'] ) ) : '—',
			__( 'Donation type', 'flx-donate' )          => $camp && ! flxb_can_view() ? esc_html( $camp['title'] ) : ( $camp ? '<a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-types', array( 'edit' => (int) $camp['id'] ) ) ) . '">' . esc_html( $camp['title'] ) . '</a>' : '—' ),
			__( 'Recorded from', 'flx-donate' )     => esc_html( self::source_label( $d['source'] ) ),
			__( 'Referral', 'flx-donate' )          => self::ref_cell( $d ),
		);
		if ( $sub && ! flxb_can_view() ) {
			$rows[ __( 'Recurring donation', 'flx-donate' ) ] = esc_html( sprintf( '%1$s · %2$s', flxb_money( $sub['amount'], $sub['currency'] ), flxb_interval_label( $sub['interval_unit'] ) ) ) . ' ' . FLXB_Admin::pill( $sub['status'] );
		} elseif ( $sub ) {
			/* translators: 1: amount, 2: interval */
			$rows[ __( 'Recurring donation', 'flx-donate' ) ] = '<a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-subscriptions', array( 's' => $sub['provider_ref'] ) ) ) . '">' . esc_html( sprintf( '%1$s · %2$s', flxb_money( $sub['amount'], $sub['currency'] ), flxb_interval_label( $sub['interval_unit'] ) ) ) . '</a> ' . FLXB_Admin::pill( $sub['status'] );
		}
		if ( '' !== (string) $d['certificate_code'] ) {
			$codes = array_values( array_unique( array_filter( array_map( 'strval', (array) ( FLXB_Donations::meta( $d )['certificates'] ?? array() ) ) ) ) );
			$codes = $codes ? $codes : array( (string) $d['certificate_code'] );
			$links = array();
			foreach ( $codes as $code ) {
				$c       = flxb_certificates_available() ? flxs_get_certificate_by_code( $code ) : null;
				$links[] = $c ? '<a href="' . esc_url( flxs_certificate_url( $c ) ) . '" target="_blank" rel="noopener">' . esc_html( ( '' !== (string) $c['number'] ? $c['number'] : $c['code'] ) . ' · ' . (string) ( $c['data']['values']['ad_soyad'] ?? '' ) ) . '</a>' . ( 'revoked' === ( $c['status'] ?? '' ) ? ' <span class="flxb-muted">(' . esc_html__( 'revoked', 'flx-donate' ) . ')</span>' : '' ) : '<code>' . esc_html( $code ) . '</code>';
			}
			$rows[ __( 'Certificate', 'flx-donate' ) ] = implode( '<br>', $links ) . self::cert_actions( $d );
		}
		$meta = FLXB_Donations::meta( $d );
		if ( ! empty( $meta['account'] ) ) {
			$rows[ __( 'Account', 'flx-donate' ) ] = esc_html( (string) $meta['account'] );
		}
		if ( ! empty( $meta['price_override'] ) ) {
			/* translators: 1: type price, 2: reason */
			$rows[ __( 'Different price', 'flx-donate' ) ] = esc_html( sprintf( __( 'type price %1$s · %2$s', 'flx-donate' ), flxb_money( $meta['price_override']['type_price'] ?? 0, $d['currency'] ), (string) ( $meta['price_override']['reason'] ?? '' ) ) );
		}
		if ( ! empty( $meta['proxy_consent']['version'] ) ) {
			/* translators: 1: version, 2: date */
			$rows[ __( 'Proxy consent', 'flx-donate' ) ] = esc_html( sprintf( __( 'version %1$s · %2$s', 'flx-donate' ), number_format_i18n( (int) $meta['proxy_consent']['version'] ), flxb_date( (string) ( $meta['proxy_consent']['accepted_at'] ?? '' ) ) ) );
		}
		if ( ! empty( $meta['note'] ) ) {
			$rows[ __( 'Notes', 'flx-donate' ) ] = nl2br( esc_html( (string) $meta['note'] ) );
		}
		if ( ! empty( $meta['created_by'] ) ) {
			$u = get_userdata( (int) $meta['created_by'] );
			$rows[ __( 'Entered by', 'flx-donate' ) ] = esc_html( $u ? $u->display_name : '#' . (int) $meta['created_by'] );
		}
		if ( ! empty( $meta['source_detail'] ) ) {
			$sd = array(
				'flx-chat-bot' => __( 'Call center · WhatsApp assistant', 'flx-donate' ),
				'flx-chat'     => __( 'Call center · agent', 'flx-donate' ),
			);
			$rows[ __( 'Recorded from', 'flx-donate' ) ] = esc_html( $sd[ (string) $meta['source_detail'] ] ?? self::source_label( $d['source'] ) . ' · ' . (string) $meta['source_detail'] );
		}
		if ( ! empty( $meta['reference'] ) && is_scalar( $meta['reference'] ) ) {
			$rows[ __( 'Payment reference', 'flx-donate' ) ] = esc_html( (string) $meta['reference'] );
		}
		if ( ! empty( $meta['iyzico']['paymentId'] ) ) {
			$iz   = $meta['iyzico'];
			$info = 'iyzico ' . (string) $iz['paymentId'] . ( ! empty( $iz['card'] ) ? ' · ' . (string) $iz['card'] : '' );
			$rows[ __( 'Card payment', 'flx-donate' ) ] = '<code>' . esc_html( $info ) . '</code>';
			if ( ! empty( $iz['refunds'] ) ) {
				$sum = array_sum( array_map( 'floatval', wp_list_pluck( (array) $iz['refunds'], 'amount' ) ) );
				$rows[ __( 'Refunded', 'flx-donate' ) ] = esc_html( flxb_money( $sum, $d['currency'], 2 ) );
			}
			if ( ! empty( $iz['canceled_at'] ) ) {
				$rows[ __( 'Canceled', 'flx-donate' ) ] = esc_html( flxb_date( (string) $iz['canceled_at'] ) );
			}
		}
		foreach ( $rows as $k => $v ) {
			echo '<dt>' . esc_html( $k ) . '</dt><dd>' . $v . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</dl>';
		self::refund_button( $d );
		echo '</section>';
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Donor', 'flx-donate' ) . '</h2></div>';
		if ( $donor ) {
			echo '<dl class="flxb-dl"><dt>' . esc_html__( 'Name', 'flx-donate' ) . '</dt><dd>' . ( FLXB_Refs::can_donors() ? '<a href="' . esc_url( FLXB_Admin_Url::donor( (int) $donor['id'] ) ) . '">' . esc_html( FLXB_Admin::donor_name( $donor, '' ) ) . '</a>' : esc_html( FLXB_Admin::donor_name( $donor, '' ) ) ) . '</dd>';
			echo '<dt>' . esc_html__( 'E-mail', 'flx-donate' ) . '</dt><dd>' . esc_html( '' !== (string) $donor['email'] ? $donor['email'] : '—' ) . '</dd>';
			echo '<dt>' . esc_html__( 'Phone', 'flx-donate' ) . '</dt><dd>' . esc_html( '' !== (string) $donor['phone'] ? flxb_format_phone( $donor['phone'] ) : '—' ) . '</dd>';
			global $wpdb;
			$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT ' . FLXB_Donations::GROUP_KEY . ') FROM ' . flxb_table( 'donations' ) . " x WHERE x.donor_id = %d AND x.status = 'pending'", (int) $donor['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			/* translators: 1: number of paid gifts, 2: total */
			$all = sprintf( _n( '%1$s paid gift · %2$s', '%1$s paid gifts · %2$s', (int) $donor['donation_count'], 'flx-donate' ), number_format_i18n( (int) $donor['donation_count'] ), flxb_money( $donor['total_amount'] ) );
			if ( $open ) {
				/* translators: %s: number of gifts waiting for payment */
				$all .= ' · ' . sprintf( _n( '%s waiting', '%s waiting', $open, 'flx-donate' ), number_format_i18n( $open ) );
			}
			echo '<dt>' . esc_html__( 'All gifts', 'flx-donate' ) . '</dt><dd>' . esc_html( $all ) . '</dd></dl>';
		} else {
			echo '<p class="flxb-muted">' . esc_html__( 'No contact details were given with this gift.', 'flx-donate' ) . '</p>';
		}
		echo '</section></div>';
		if ( ! $multi ) {
			self::items_card( $d );
		}
		self::records_card( $d );
		echo FLXB_Detail_Tabs::render( 'donation', $d, flxb_can_manage() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		FLXB_Admin::footer();
	}

	/**
	 * 2.0.9: a basket (one WooCommerce order with several donation lines) is one gift: every line
	 * with its details, the order total and, for a bank transfer awaiting approval, one confirm button.
	 */
	private static function order_card( array $d, array $sibs ) {
		$oid   = (int) $d['wc_order_id'];
		$order = $oid && function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
		$wait  = false; // 2.4.0: approval lives in the "Payment awaiting approval" card.
		if ( count( $sibs ) < 2 ) {
			return;
		}
		echo '<section class="flxb-card flxb-card--gap flxb-basket"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Basket', 'flx-donate' ) . '</h2>';
		if ( $order ) {
			/* translators: %s: order number */
			echo '<p class="flxb-card__hint">' . esc_html( sprintf( __( 'WooCommerce order #%s', 'flx-donate' ), $order->get_order_number() ) ) . ' · ' . esc_html( $order->get_payment_method_title() ) . '</p>';
		}
		echo '</div>';
		if ( $wait && current_user_can( 'edit_shop_orders' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_approve&order=' . $oid . '&back=' . (int) $d['id'] ), 'flxb_approve_' . $oid );
			echo '<a class="flxb-btn flxb-btn--primary" href="' . esc_url( $url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Has the payment reached the bank? When you confirm, the certificate is made and sent to the donor.', 'flx-donate' ) ) ) . ')">' . esc_html__( 'Confirm payment', 'flx-donate' ) . '</a>';
		}
		echo '</div>';
		if ( $wait && class_exists( 'FLXB_WC_Receipts' ) ) {
			$rec = FLXB_WC_Receipts::of( $order );
			if ( $rec ) {
				echo '<ul class="flxb-list flxb-receipts">';
				foreach ( $rec as $i => $r ) {
					echo '<li><a href="' . esc_url( FLXB_WC_Receipts::url( $order, $i, true ) ) . '" target="_blank" rel="noopener">' . esc_html( $r['name'] ) . '</a>';
					if ( ! empty( $r['dup'] ) ) {
						echo '<p class="flxb-notice flxb-notice--warn">' . esc_html( FLXB_WC_Receipts::dup_text( $r ) ) . '</p>';
					}
					echo '</li>';
				}
				echo '</ul>';
			} else {
				echo '<p class="flxb-muted">' . esc_html__( 'No receipt uploaded yet.', 'flx-donate' ) . '</p>';
			}
		}
		$sum = 0.0;
		echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Item', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Details', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th></tr></thead><tbody>';
		foreach ( $sibs as $s ) {
			$sum  += (float) $s['amount'];
			$item  = $order && class_exists( 'FLXB_WC_Ledger' ) ? $order->get_item( FLXB_WC_Ledger::item_of( $s ) ) : null;
			$name  = $item ? $item->get_name() : ( '' !== (string) $s['option_label'] ? $s['option_label'] : __( 'Donation', 'flx-donate' ) );
			$bits  = array();
			if ( $item ) {
				foreach ( $item->get_formatted_meta_data() as $m ) {
					$bits[] = wp_strip_all_tags( $m->display_key ) . ': ' . trim( wp_strip_all_tags( $m->display_value ) );
				}
			}
			$here = (int) $s['id'] === (int) $d['id'];
			echo '<tr' . ( $here ? ' class="is-current"' : '' ) . '><td class="flxb-col-main">' . ( $here ? '<strong>' . esc_html( $name ) . '</strong>' : '<a href="' . esc_url( FLXB_Admin_Url::donation( (int) $s['id'] ) ) . '">' . esc_html( $name ) . '</a>' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Details', 'flx-donate' ) . '"><span>' . ( $bits ? implode( '<br>', array_map( 'esc_html', $bits ) ) : '—' ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . FLXB_Admin::pill( $s['status'] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $s['amount'], $s['currency'], 2 ) ) . '</span></td></tr>';
		}
		echo '</tbody><tfoot><tr><th scope="row" colspan="3">' . esc_html__( 'Total', 'flx-donate' ) . '</th><td class="num"><strong>' . esc_html( flxb_money( $sum, $d['currency'], 2 ) ) . '</strong></td></tr></tfoot></table></div></section>';
	}

	/** 2.4.1: "Make the certificates again" (one per name, not sent) and "Send the certificates". */
	private static function cert_actions( array $d ) {
		$oid = (int) $d['wc_order_id'];
		if ( ! $oid || ! current_user_can( 'edit_shop_orders' ) || ! class_exists( 'FLXB_WC_Certificates' ) || ! in_array( $d['status'], FLXB_Donations::PAID, true ) ) {
			return '';
		}
		$form = function ( $action, $label, $confirm, $cls ) use ( $d, $oid ) {
			return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')">' . wp_nonce_field( $action . '_' . $oid, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="order" value="' . (int) $oid . '"><input type="hidden" name="back" value="' . (int) $d['id'] . '"><button type="submit" class="flxb-btn ' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
		};
		return '<div class="flxb-cert-actions">'
			. $form( 'flxb_cert_remake', __( 'Make the certificates again', 'flx-donate' ), __( 'The current certificates are revoked and new ones are made, one for each name. They are not sent until you press "Send the certificates".', 'flx-donate' ), '' )
			. $form( 'flxb_cert_send', __( 'Send the certificates', 'flx-donate' ), __( 'Send the certificates to the donor now?', 'flx-donate' ), 'flxb-btn--ghost' )
			. '</div>';
	}

	public static function cert_post() {
		$oid    = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		$back   = isset( $_POST['back'] ) ? absint( $_POST['back'] ) : 0;
		$action = isset( $_POST['action'] ) && 'flxb_cert_send' === $_POST['action'] ? 'flxb_cert_send' : 'flxb_cert_remake';
		check_admin_referer( $action . '_' . $oid );
		$order = $oid && current_user_can( 'edit_shop_orders' ) ? wc_get_order( $oid ) : null;
		if ( ! $order ) {
			FLXB_Admin::deny();
		}
		if ( 'flxb_cert_send' === $action ) {
			$sum = FLXB_WC_Certificates::send_all( $order );
			/* translators: %s: summary */
			$msg = '' !== $sum ? sprintf( __( 'Certificates sent: %s', 'flx-donate' ), $sum ) : __( 'There is no certificate to send.', 'flx-donate' );
		} else {
			$made = FLXB_WC_Certificates::remake( $order );
			$msg  = $made
				/* translators: %d: number of certificates */
				? sprintf( _n( '%d certificate made. Check it, then press "Send the certificates".', '%d certificates made. Check them, then press "Send the certificates".', count( $made ), 'flx-donate' ), count( $made ) )
				: __( 'No certificate could be made; see the order notes.', 'flx-donate' );
		}
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $back, 'flxb_msg' => 'saved', 'flxb_err' => $msg ) );
	}

	/** Line items (and the names given for each unit, editable for proxy types). */
	private static function items_card( array $d ) {
		$items = FLXB_Items::for_donation( (int) $d['id'] );
		if ( ! $items ) {
			return;
		}
		$named = (bool) array_filter( wp_list_pluck( $items, 'beneficiary_name' ), 'strlen' ) || ( (int) $d['type_id'] && ( $t = FLXB_Types::get( (int) $d['type_id'] ) ) && FLXB_Types::requires_proxy( $t ) );
		$coded = flxb_kurban_enabled() && (bool) array_filter( wp_list_pluck( $items, 'code' ), 'strlen' ); // 2.10.0
		echo '<section class="flxb-card flxb-card--gap"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Items', 'flx-donate' ) . '</h2></div>';
		if ( $named && flxb_can_manage() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-items-edit"><input type="hidden" name="action" value="flxb_items_save"><input type="hidden" name="id" value="' . (int) $d['id'] . '">';
			wp_nonce_field( 'flxb_items_save_' . (int) $d['id'] );
		}
		echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-table--compact"><thead><tr><th scope="col">' . esc_html__( 'Option', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Quantity', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Unit price', 'flx-donate' ) . '</th>' . ( $named ? '<th scope="col">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</th>' : '' ) . ( $coded ? '<th scope="col">' . esc_html__( 'Kurban share', 'flx-donate' ) . '</th>' : '' ) . '</tr></thead><tbody>';
		foreach ( $items as $it ) {
			$unit = '' !== (string) $it['unit'] ? ' ' . flxb_unit_label( $it['unit'], (int) $it['quantity'] ) : '';
			echo '<tr><td class="flxb-col-main">' . esc_html( '' !== (string) $it['option_label'] ? $it['option_label'] : __( 'Donation', 'flx-donate' ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Quantity', 'flx-donate' ) . '"><span>' . esc_html( number_format_i18n( (int) $it['quantity'] ) . $unit ) . '</span></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Unit price', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $it['unit_price'], $it['currency'] ) ) . '</span></td>';
			if ( $named ) {
				echo '<td data-label="' . esc_attr__( 'On behalf of', 'flx-donate' ) . '">' . ( flxb_can_manage() ? '<input type="text" class="flxb-input" name="names[' . (int) $it['id'] . ']" maxlength="190" value="' . esc_attr( $it['beneficiary_name'] ) . '" aria-label="' . esc_attr__( 'On behalf of', 'flx-donate' ) . '">' : '<span>' . esc_html( $it['beneficiary_name'] ) . '</span>' ) . '</td>';
			}
			if ( $coded ) {
				echo '<td data-label="' . esc_attr__( 'Kurban share', 'flx-donate' ) . '"><span>' . ( '' !== (string) $it['code'] ? '<a class="flxb-mono" href="' . esc_url( FLXB_Admin_Url::page( FLXB_Page_Track::SLUG, array( 'item' => (int) $it['id'] ) ) ) . '">' . esc_html( $it['code'] ) . '</a> ' . FLXB_Track::status_pill( $it['status'] ) . ( '' !== (string) $it['place_name'] ? '<span class="flxb-sub2">' . esc_html( $it['place_name'] ) . '</span>' : '' ) : '—' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		if ( $named && flxb_can_manage() ) {
			echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Save names', 'flx-donate' ) . '</button></div></form>';
		}
		echo '</section>';
	}

	/** Referral: name, and for managers a picker (applies to the whole order, its recurring donation and certificates). */
	private static function ref_cell( array $d ) {
		$ref  = (int) ( $d['ref_user_id'] ?? 0 );
		$text = $ref ? esc_html( FLXB_Refs::name( $ref ) ) : '<span class="flxb-muted">' . esc_html__( 'None', 'flx-donate' ) . '</span>';
		if ( ! flxb_can_manage() ) {
			return $text;
		}
		$users = FLXB_Refs::users();
		if ( $ref && ! isset( $users[ $ref ] ) ) {
			$users[ $ref ] = FLXB_Refs::name( $ref );
		}
		$h  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxb-ref-edit"><input type="hidden" name="action" value="flxb_ref_save"><input type="hidden" name="id" value="' . (int) $d['id'] . '">' . wp_nonce_field( 'flxb_ref_save_' . (int) $d['id'], '_wpnonce', true, false );
		$h .= '<label class="screen-reader-text" for="flxb-ref-pick">' . esc_html__( 'Referral', 'flx-donate' ) . '</label><select id="flxb-ref-pick" name="ref"><option value="0">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
		foreach ( $users as $uid => $label ) {
			$h .= '<option value="' . (int) $uid . '"' . selected( $ref, (int) $uid, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $h . '</select> <button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Change', 'flx-donate' ) . '</button></form>';
	}

	public static function ref_save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_ref_save_' . $id );
		$ref = isset( $_POST['ref'] ) ? absint( $_POST['ref'] ) : 0;
		if ( $ref && ! FLXB_Refs::valid( $ref ) ) {
			FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'error', 'flxb_err' => __( 'This person cannot be a referral.', 'flx-donate' ) ) );
		}
		FLXB_Refs::set( $id, $ref );
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'saved' ) );
	}

	public static function items_save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_items_save_' . $id );
		$names = isset( $_POST['names'] ) && is_array( $_POST['names'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['names'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		FLXB_Items::update_names( $id, array_combine( array_map( 'intval', array_keys( $names ) ), array_values( $names ) ) );
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'saved' ) );
	}

	public static function source_label( $s ) {
		$map = array(
			'submit'   => __( 'Form submission', 'flx-donate' ),
			'webhook'  => __( 'Payment provider notification', 'flx-donate' ),
			'sync'     => __( 'Automatic sync', 'flx-donate' ),
			'backfill' => __( 'Import of earlier payments', 'flx-donate' ),
			'manual'   => __( 'Manual entry', 'flx-donate' ),
			'callback' => __( 'Return from the payment page', 'flx-donate' ),
			'renewal'  => __( 'Automatic renewal', 'flx-donate' ),
			'callcenter' => __( 'Call center', 'flx-donate' ),
			'staff'      => __( 'Staff request', 'flx-donate' ),
		);
		return $map[ (string) $s ] ?? (string) $s;
	}

	/** CSV export of the filtered donations (formula-injection safe). */
	public static function csv() {
		if ( ! FLXB_Refs::can_list() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_csv' );
		$f   = self::scoped( self::filters( $_GET ) );
		$res = FLXB_Donations::query( array_merge( $f, array( 'per_page' => 0 ) ) );
		$delim = 0 === strpos( strtolower( (string) get_user_locale() ), 'tr' ) ? ';' : ',';
		$name  = 'bagislar-' . ( $f['from'] ? $f['from'] : 'tumu' ) . ( $f['to'] ? '_' . $f['to'] : '' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$head = array( __( 'Donation ID', 'flx-donate' ), __( 'Date', 'flx-donate' ), __( 'Donor', 'flx-donate' ), __( 'E-mail', 'flx-donate' ), __( 'Phone', 'flx-donate' ), __( 'Amount', 'flx-donate' ), __( 'Currency', 'flx-donate' ), __( 'Type', 'flx-donate' ), __( 'Status', 'flx-donate' ), __( 'Donation type', 'flx-donate' ), __( 'Option', 'flx-donate' ), __( 'Quantity', 'flx-donate' ), __( 'Unit price', 'flx-donate' ), __( 'Form', 'flx-donate' ), __( 'Confirmation code', 'flx-donate' ), __( 'Payment provider', 'flx-donate' ), __( 'Provider reference', 'flx-donate' ), __( 'Certificate', 'flx-donate' ), __( 'Items', 'flx-donate' ), __( 'On behalf of', 'flx-donate' ), __( 'Proxy consent version', 'flx-donate' ), __( 'Recorded from', 'flx-donate' ), __( 'Referral', 'flx-donate' ) );
		fputcsv( $out, array_map( 'flxb_csv_cell', $head ), $delim, '"', '' );
		$camps = array();
		foreach ( FLXB_Types::all( 'all' ) as $c ) {
			$camps[ (int) $c['id'] ] = $c['title'];
		}
		foreach ( $res['items'] as $r ) {
			$items = FLXB_Items::for_donation( (int) $r['id'] );
			$meta  = FLXB_Donations::meta( $r );
			fputcsv(
				$out,
				array_map(
					'flxb_csv_cell',
					array(
						$r['id'],
						flxb_date( $r['created_at'], 'Y-m-d H:i' ),
						(string) $r['donor_name'],
						(string) $r['donor_email'],
						(string) $r['donor_phone'],
						number_format( (float) $r['amount'], 2, ',' === $delim ? '.' : ',', '' ),
						$r['currency'],
						flxb_type_label( $r['type'] ),
						flxb_status_label( $r['status'] )[1],
						$camps[ (int) $r['type_id'] ] ?? '',
						$r['option_label'],
						(int) $r['quantity'],
						number_format( (float) $r['unit_price'], 2, ',' === $delim ? '.' : ',', '' ),
						(int) $r['form_id'] ? flxb_form_title( (int) $r['form_id'] ) : '',
						$r['track'],
						flxb_provider_label( $r['provider'] ),
						$r['provider_ref'],
						$r['certificate_code'],
						implode( ' | ', FLXB_Items::summary( array_map( function ( $it ) {
							$it['beneficiary_name'] = '';
							return $it;
						}, $items ) ) ),
						implode( ' | ', array_filter( wp_list_pluck( $items, 'beneficiary_name' ), 'strlen' ) ),
						! empty( $meta['proxy_consent']['version'] ) ? (int) $meta['proxy_consent']['version'] : '',
						self::source_label( $r['source'] ),
						(int) ( $r['ref_user_id'] ?? 0 ) ? FLXB_Refs::name( (int) $r['ref_user_id'] ) : '',
					)
				),
				$delim,
				'"',
				''
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
