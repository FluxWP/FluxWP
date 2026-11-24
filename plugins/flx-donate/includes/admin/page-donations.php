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

	/**
	 * 2.19.0: query arguments for the list and the CSV. The status filter also takes two stages of a
	 * pending gift: 'receipt' (receipt awaiting approval) and 'payment' (awaiting payment).
	 */
	private static function query_args( array $f ) {
		$f = self::scoped( $f );
		if ( in_array( $f['status'], array( 'receipt', 'payment' ), true ) ) {
			$f['stage']  = FLXB_Approval::stage_filter( $f['status'] );
			$f['status'] = '';
		}
		return $f;
	}

	/** May the current user open this donation? */
	private static function may_see( array $d ) {
		return flxb_can_view() || ( FLXB_Refs::own_only() && (int) ( $d['ref_user_id'] ?? 0 ) === get_current_user_id() );
	}

	/**
	 * The list and the donation page (?donation=ID) are the React screen (packages/modules/src/donate,
	 * Flux GraphQL shell).
	 */
	public static function render() {
		if ( ! FLXB_Refs::can_list() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen(); // 2.38.0: the donation page (?donation=) is part of the screen too
	}

	/** Donation rows (also used on the donor card). */
	public static function table( array $items, $show_donor, $show_ref = false, $bulk = false ) {
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
		$donor_refs = $show_ref ? FLXB_Refs::donor_refs( wp_list_pluck( array_filter( $items, function ( $r ) { return ! (int) ( $r['ref_user_id'] ?? 0 ); } ), 'donor_id' ) ) : array();
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
				echo '<td class="flxb-col-main">' . ( ! (int) $r['donor_id'] ? '<span class="flxb-muted">' . esc_html__( 'Anonymous', 'flx-donate' ) . '</span>' : '<a class="flxb-row-main" href="' . esc_url( $url ) . '">' . $dn . '</a>' ); // 2.19.0: the name opens the donation too; the donor card is linked from there
				$contact = '' !== (string) $r['donor_email'] ? $r['donor_email'] : flxb_format_phone( $r['donor_phone'] );
				if ( '' !== (string) $contact && $contact !== FLXB_Admin::donor_name( $r ) ) {
					echo '<span class="flxb-sub2">' . esc_html( $contact ) . '</span>';
				}
				echo '</td>';
			}
			$lines = (int) ( $r['group_lines'] ?? 1 );
			echo '<td class="num flxb-c-amount" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $r['group_amount'] ?? $r['amount'], $r['currency'] ) ) . '</span></td>';
			echo '<td class="flxb-c-type" data-label="' . esc_attr__( 'Type', 'flx-donate' ) . '"><span>' . FLXB_Admin::type_pill( $r['type'] ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="flxb-c-status" data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span>' . FLXB_Approval::pill( $r ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
				$ref  = (int) ( $r['ref_user_id'] ?? 0 );
				$dref = ! $ref ? (int) ( $donor_refs[ (int) $r['donor_id'] ] ?? 0 ) : 0; // 2.20.0: the donor's referral when the gift has none
				/* translators: %s: referral name */
				echo '<td class="flxb-c-ref" data-label="' . esc_attr__( 'Referral', 'flx-donate' ) . '"><span>' . ( $ref ? '<span class="flxb-ref-chip">' . esc_html( FLXB_Refs::name( $ref ) ) . '</span>' : ( $dref ? '<span class="flxb-ref-chip flxb-ref-chip--donor" title="' . esc_attr( sprintf( __( 'The donor\'s earlier gifts came through %s.', 'flx-donate' ), FLXB_Refs::name( $dref ) ) ) . '">' . esc_html( FLXB_Refs::name( $dref ) ) . '</span>' : '—' ) ) . '</span></td>';
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
		$ids = isset( $_POST['ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) ) : array();
		$res = FLXB_Admin_Donations_Service::bulk( $ids, $do ); // 2.36.0: same service as the React list
		if ( is_wp_error( $res ) ) {
			FLXB_Admin::deny();
		}
		wp_safe_redirect( add_query_arg( array( 'flxb_msg' => 'saved', 'flxb_err' => rawurlencode( $res['message'] ) ), $back ) );
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
		echo '<style>:root{--b:' . esc_html( $b['primary'] ) . '}*{box-sizing:border-box}body{margin:0;background:#f3f5f4;color:#111827;font:15px/1.55 "Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}.r{max-width:720px;margin:32px auto;padding:36px 40px;background:#fff;border-radius:16px;border-top:6px solid var(--b);box-shadow:0 1px 3px rgba(20,50,74,.08)}.h{display:flex;align-items:center;gap:16px;margin:0 0 24px}.h img{max-height:64px;max-width:160px}.h b{font-size:20px}.t{margin:0 0 4px;font-size:24px}.a{margin:18px 0;padding:18px 20px;border-radius:12px;background:color-mix(in srgb,var(--b) 8%,#fff);font-size:30px;font-weight:800;color:var(--b)}table{width:100%;border-collapse:collapse}th,td{padding:9px 0;border-bottom:1px solid #e3ebe6;text-align:left;vertical-align:top}th{width:40%;color:#4B5563;font-weight:600}.n{margin:22px 0 0;white-space:pre-line}.f{margin:28px 0 0;padding:14px 0 0;border-top:1px solid #e3ebe6;color:#4B5563;font-size:13px;white-space:pre-line}.p{display:block;margin:0 auto 32px;padding:10px 22px;border:0;border-radius:10px;background:var(--b);color:#fff;font:700 14px inherit;cursor:pointer}@media print{body{background:#fff}.r{margin:0;box-shadow:none;max-width:none}.p{display:none}}@media (max-width:600px){.r{margin:0;padding:24px 18px;border-radius:0}}</style></head><body>';
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

	/** One change-log line in words. */
	public static function audit_text( array $a ) {
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

	/** Statuses an admin may set by hand. */
	public static function manual_statuses() {
		return array(
			'pending'   => __( 'Pending (on hold)', 'flx-donate' ),
			'completed' => __( 'Completed (paid)', 'flx-donate' ),
			'canceled'  => __( 'Canceled', 'flx-donate' ),
			'failed'    => __( 'Failed', 'flx-donate' ),
		);
	}

	/** 2.14.0: change the status from the detail page; every item of the same order follows. */
	public static function status_post() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'flxb_status_' . $id );
		if ( FLXB_Refs::own_only() || ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$d      = FLXB_Donations::get( $id );
		$back   = FLXB_Admin_Url::donation( $id );
		if ( ! $d || ! isset( self::manual_statuses()[ $status ] ) ) {
			wp_safe_redirect( add_query_arg( 'flxb_msg', 'error', $back ) );
			exit;
		}
		if ( (int) $d['wc_order_id'] && ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			FLXB_Admin::deny();
		}
		$done = 0;
		foreach ( FLXB_Donations::siblings( $d ) as $s ) {
			$done += FLXB_Donations::set_status( (int) $s['id'], $status ) ? 1 : 0;
		}
		/* translators: %s: new status */
		$msg = $done ? sprintf( __( 'Status changed to "%s".', 'flx-donate' ), flxb_status_label( $status )[1] ) : __( 'The status was not changed.', 'flx-donate' );
		wp_safe_redirect( add_query_arg( array( 'flxb_msg' => $done ? 'saved' : 'warn', 'flxb_err' => rawurlencode( $msg ) ), $back ) );
		exit;
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
			'woocommerce' => __( 'Website checkout', 'flx-donate' ),
		);
		return $map[ (string) $s ] ?? (string) $s;
	}

	/** CSV export of the filtered donations (formula-injection safe). */
	public static function csv() {
		if ( ! FLXB_Refs::can_list() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_csv' );
		$f   = self::query_args( self::filters( $_GET ) );
		$res = FLXB_Donations::query( array_merge( $f, array( 'per_page' => 0 ) ) );
		$delim = 0 === strpos( strtolower( (string) get_user_locale() ), 'tr' ) ? ';' : ',';
		$name  = 'bagislar-' . ( $f['from'] ? $f['from'] : 'tumu' ) . ( $f['to'] ? '_' . $f['to'] : '' ) . '.csv';
		$head  = array( __( 'Donation ID', 'flx-donate' ), __( 'Date', 'flx-donate' ), __( 'Donor', 'flx-donate' ), __( 'E-mail', 'flx-donate' ), __( 'Phone', 'flx-donate' ), __( 'Amount', 'flx-donate' ), __( 'Currency', 'flx-donate' ), __( 'Type', 'flx-donate' ), __( 'Status', 'flx-donate' ), __( 'Donation type', 'flx-donate' ), __( 'Option', 'flx-donate' ), __( 'Quantity', 'flx-donate' ), __( 'Unit price', 'flx-donate' ), __( 'Form', 'flx-donate' ), __( 'Confirmation code', 'flx-donate' ), __( 'Payment provider', 'flx-donate' ), __( 'Provider reference', 'flx-donate' ), __( 'Certificate', 'flx-donate' ), __( 'Items', 'flx-donate' ), __( 'On behalf of', 'flx-donate' ), __( 'Proxy consent version', 'flx-donate' ), __( 'Recorded from', 'flx-donate' ), __( 'Referral', 'flx-donate' ) );
		$camps = array();
		foreach ( FLXB_Types::all( 'all' ) as $c ) {
			$camps[ (int) $c['id'] ] = $c['title'];
		}
		flx_csv_stream(
			$name,
			$head,
			( static function () use ( $res, $camps, $delim ) {
				foreach ( $res['items'] as $r ) {
					$items = FLXB_Items::for_donation( (int) $r['id'] );
					$meta  = FLXB_Donations::meta( $r );
					yield array(
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
					);
				}
			} )(),
			array(
				'delimiter' => $delim,
				'enclosure' => '"',
				'escape'    => '',
			)
		);
		exit;
	}
}
